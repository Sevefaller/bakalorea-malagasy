import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export interface Player { id: number; nickname: string; score: number; online: boolean }
export interface Answer { id: number; player_id: number; answer: string; flagged: boolean; invalid_reason: string | null; verdict: boolean | null; points: number; tie_decision: boolean | null; referee_id: number | null; votes: { valid: number; invalid: number; uncertain: number; count: number; tied: boolean }; my_vote: string | null }
export interface RoundComment { id: number; player_id: number; body: string; created_at: string }
export interface Round { id: number; number: number; category: string; letter: string; status: string; started_at: number; answer_deadline: number; own_answer: string; own_revision: number; participating: boolean; ready: boolean; answers?: Answer[]; comments?: RoundComment[] }
export interface Game { id: number; code: string; name: string; status: string; host_id: number; winner_id: number | null; me_id: number; target_score: number; answer_duration: number; anti_cheat_mode: string; unique_points: number; duplicate_points: number; letters: string; no_repeat: boolean; server_now: number; players: Player[]; round: Round | null; history: { number: number; letter: string; category: string; answers: { player_id: number; answer: string; points_awarded: number; verdict: boolean }[] }[] }

export const useGame = defineStore('game', () => {
  const game = ref<Game | null>(null), error = ref(''), busy = ref(false), disconnected = ref(false), replaced = ref(false), terminal = ref(false)
  const token = ref(localStorage.getItem('bakalorea.token') || '')
  const code = ref(localStorage.getItem('bakalorea.code') || '')
  const bytes = crypto.getRandomValues(new Uint8Array(16))
  bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128
  const hex = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('')
  const sessionId = `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`
  sessionStorage.setItem('bakalorea.session', sessionId)
  let timer: ReturnType<typeof setInterval> | undefined, echo: Echo<'reverb'> | undefined, fetching = false
  let anchorServer = 0, anchorPerf = 0
  const isHost = computed(() => game.value?.host_id === game.value?.me_id)
  const ranking = computed(() => [...(game.value?.players || [])].sort((a,b) => b.score - a.score || a.id - b.id))
  const serverNow = () => anchorServer + performance.now() - anchorPerf
  async function request(path: string, method = 'GET', body?: unknown, quiet = false) {
    let res: Response
    try { res = await fetch(`/api${path}`, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token.value}`, 'X-Session-ID': sessionId }, ...(body !== undefined ? { body: JSON.stringify(body) } : {}), cache: 'no-store', signal: AbortSignal.timeout(8000) }) }
    catch { if (!quiet) error.value = 'network'; throw new Error('network') }
    const data = await res.json().catch(() => ({}))
    if (!res.ok) {
      const message = res.status === 401 ? 'unauthorized' : res.status === 404 ? 'not_found' : res.status === 429 ? 'rate_limit' : data.message || 'generic'
      if (message === 'session_replaced') { replaced.value = true; stop() }
      if (res.status === 401 || message === 'left_game') { terminal.value = true; stop() }
      if (!quiet) error.value = message
      throw new Error(message)
    }
    return data
  }
  async function refresh() {
    if (!token.value || fetching || replaced.value || terminal.value) return
    fetching = true
    const before = performance.now()
    try {
      const data: Game = await request(`/games/${code.value}`, 'GET', undefined, true)
      anchorServer = data.server_now + (performance.now() - before) / 2; anchorPerf = performance.now()
      game.value = data; disconnected.value = false
    } catch { disconnected.value = true }
    finally { fetching = false }
  }
  async function connect() {
    stop(); await refresh()
    if (replaced.value || terminal.value) return
    timer = setInterval(refresh, 1500)
    try {
      const c = await request('/config', 'GET', undefined, true)
      if (!c.realtime) return
      ;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher
      echo = new Echo({ broadcaster: 'reverb', key: c.key, wsHost: c.host || location.hostname, wsPort: c.port, wssPort: c.port, forceTLS: c.scheme === 'https', enabledTransports: ['ws','wss'], authEndpoint: '/api/broadcasting/auth', auth: { headers: { Authorization: `Bearer ${token.value}`, 'X-Session-ID': sessionId, Accept: 'application/json' } } })
      echo.private(`game.${code.value}`).listen('.GameChanged', refresh)
    } catch { /* Polling also recovers missed websocket events. */ }
  }
  function stop() { if (timer) clearInterval(timer); echo?.disconnect(); echo = undefined }
  async function enter(mode: string, fields: Record<string, unknown>) {
    busy.value = true; error.value = ''; terminal.value = false; replaced.value = false
    try {
      const data = await request(mode === 'create' ? '/games' : `/games/${String(fields.code).toUpperCase()}/join`, 'POST', { ...fields, session_id: sessionId })
      token.value = data.token; code.value = data.code
      localStorage.setItem('bakalorea.token', data.token); localStorage.setItem('bakalorea.code', data.code)
      await connect(); return true
    } catch { return false } finally { busy.value = false }
  }
  async function resume() {
    error.value = ''; replaced.value = false; terminal.value = false
    try { await request('/session/claim', 'POST', { session_id: sessionId }); await connect() } catch { disconnected.value = true }
  }
  async function action(path: string, body?: unknown) {
    if (busy.value) return false
    busy.value = true; error.value = ''
    try { await request(path, 'POST', body); await refresh(); return true } catch { await refresh(); return false } finally { busy.value = false }
  }
  function forget() { stop(); token.value = ''; code.value = ''; game.value = null; error.value = ''; terminal.value = false; disconnected.value = false; localStorage.removeItem('bakalorea.token'); localStorage.removeItem('bakalorea.code') }
  return { game, error, busy, disconnected, replaced, terminal, token, code, isHost, ranking, serverNow, request, refresh, enter, resume, connect, stop, action, forget }
})
