// Integration check against a running, disposable test environment.
// Creates its own rooms; does not change existing rooms or database schemas.
import { randomUUID } from 'node:crypto'
import assert from 'node:assert/strict'
const base = process.env.GAME_URL || 'http://localhost:8088'
const pause = ms => new Promise(resolve => setTimeout(resolve, ms))
async function api(path, session, body, method=body===undefined?'GET':'POST') {
  const res=await fetch(base+'/api'+path,{method,headers:{Accept:'application/json','Content-Type':'application/json',...(session?.token?{Authorization:`Bearer ${session.token}`,'X-Session-ID':session.id}:{})},...(body!==undefined?{body:JSON.stringify(body)}:{})})
  const result=await res.json()
  assert.ok(res.ok, `${method} ${path}: ${res.status} ${result.message || ''}`)
  return result
}
async function room(count) {
  const host={id:randomUUID()}
  Object.assign(host,await api('/games',null,{nickname:'Test Host',locale:'fr',session_id:host.id,name:`Validation ${count} joueurs`,target_score:10,answer_duration:10,anti_cheat_mode:'normal',letters:'AB',no_repeat:true,unique_points:10,duplicate_points:5}))
  const players=[host]
  for(let i=1;i<count;i++) { const p={id:randomUUID()}; Object.assign(p,await api(`/games/${host.code}/join`,null,{nickname:`Test ${i}`,locale:i%2?'mg':'en',session_id:p.id})); players.push(p) }
  let state=await api(`/games/${host.code}`,host)
  assert.equal(state.players.length,count)
  await api(`/games/${state.id}/start`,host,{})
  state=await api(`/games/${host.code}`,host)
  const snapshots=await Promise.all(players.map(p=>api(`/games/${host.code}`,p)))
  assert.ok(snapshots.every(s=>s.round.letter===state.round.letter && !('answers' in s.round)))
  await pause(Math.max(0,state.round.started_at-state.server_now)+120)
  await Promise.all(players.map((p,i)=>api(`/rounds/${state.round.id}/answer`,p,{answer:state.round.letter+(i?'shared':'unique'),revision:1},'PATCH')))
  const hidden=await api(`/games/${host.code}`,players[1])
  assert.ok(!JSON.stringify(hidden.round).includes('unique'))
  await pause(Math.max(0,state.round.answer_deadline-state.round.started_at)+100)
  state=await api(`/games/${host.code}`,host)
  assert.equal(state.round.status,'judging')
  await Promise.all(players.flatMap((p,i)=>state.round.answers.filter(a=>a.player_id!==snapshots[i].me_id).map(a=>api(`/answers/${a.id}/votes`,p,{vote:'valid'}))))
  await api(`/rounds/${state.round.id}/finish-judging`,host,{})
  state=await api(`/games/${host.code}`,host)
  assert.equal(state.status,'finished')
  assert.equal(state.winner_id,state.me_id)
  assert.equal(state.players.find(p=>p.id===state.me_id).score,10)
  assert.ok(state.players.filter(p=>p.id!==state.me_id).every(p=>p.score===5))
  await Promise.all(players.map(p=>api(`/games/${state.id}/leave`,p,{})))
  console.log(`${count} joueurs : synchronisation, confidentialité, votes simultanés, doublons et victoire OK`)
}
await room(4)
await room(8)
