<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { Users, Plus, KeyRound, ChevronDown, Settings2, Trophy, Timer, ShieldCheck, Check, X, HelpCircle, Copy, Crown, LogOut, Volume2, VolumeX, Globe2, Sparkles, LockKeyhole, Leaf, Apple, MapPin, Mic2, Music2, UserRound, Flag, CheckCircle2, Download, RotateCcw, History, WifiOff } from 'lucide-vue-next'
import { useGame } from './store'
import { soundOn, soundBlocked, toggleSound, unlockSound, playSound } from './sound'

const { t, te, locale } = useI18n(), store = useGame(), router = useRouter(), route = useRoute()
const mode = ref('create'), advanced = ref(false), rulesDialog = ref<HTMLDialogElement>(), leaveDialog = ref<HTMLDialogElement>()
const nickname = ref(localStorage.getItem('bakalorea.nickname') || ''), roomName = ref(''), roomCode = ref('')
const target = ref(200), duration = ref(15), antiCheat = ref('normal'), letters = ref('ABDEFGHIJKLMNOPRSTV'), noRepeat = ref(true), uniquePoints = ref(10), duplicatePoints = ref(5)
const copied = ref(false), now = ref(0), draft = ref(''), savedState = ref(''), answerInput = ref<HTMLInputElement>(), revision = ref(0)
const installPrompt = ref<any>(null), showHistory = ref(false)
let clock: ReturnType<typeof setInterval>, saveTimer: ReturnType<typeof setTimeout> | undefined, lastBeep = -1, away = false, activeSave = 0
const categories = [ { code: 'male_name', icon: UserRound, color: 'purple' }, { code: 'female_name', icon: UserRound, color: 'pink' }, { code: 'plant', icon: Leaf, color: 'green' }, { code: 'fruit', icon: Apple, color: 'orange' }, { code: 'malagasy_artist', icon: Mic2, color: 'pink' }, { code: 'international_artist', icon: Music2, color: 'purple' }, { code: 'malagasy_place', icon: MapPin, color: 'orange' }, { code: 'international_place', icon: Globe2, color: 'blue' } ]
const g = computed(() => store.game), round = computed(() => g.value?.round)
const inRoom = computed(() => route.path.startsWith('/salle/') && !!store.token)
const spinning = computed(() => round.value?.status === 'answering' && now.value < round.value.started_at)
const seconds = computed(() => round.value ? Math.max(0, Math.ceil((round.value.answer_deadline - now.value) / 1000)) : 0)
const answering = computed(() => round.value?.status === 'answering' && !spinning.value && seconds.value > 0)
const spinLetter = computed(() => g.value?.letters[Math.floor(now.value / 90) % g.value.letters.length] || 'B')
const ranking = computed(() => store.ranking)
const ties = computed(() => round.value?.answers?.some(a => !a.invalid_reason && a.votes.tied && a.tie_decision === null))
const errorText = computed(() => te(`error.${store.error}`) ? t(`error.${store.error}`) : t('error.generic'))
const onlineCount = computed(() => g.value?.players.filter(p => p.online).length || 0)
const tiedLeaders = computed(() => ranking.value.length > 1 && ranking.value[0].score >= (g.value?.target_score || 200) && ranking.value[0].score === ranking.value[1].score)
const playerName = (id: number | null) => g.value?.players.find(p => p.id === id)?.nickname || '—'
const initials = (name: string) => name.slice(0, 2).toUpperCase()
const reasonLabel = (reason: string) => t(({ empty: 'emptyReason', letter: 'letterReason', strict: 'strictReason' } as Record<string,string>)[reason] || 'invalid')

watch(locale, value => { localStorage.setItem('bakalorea.locale', value); document.documentElement.lang = value })
watch(() => round.value?.id, async () => { clearTimeout(saveTimer); draft.value = round.value?.own_answer || ''; revision.value = round.value?.own_revision || 0; savedState.value = draft.value ? 'saved' : ''; lastBeep = -1; away = false; if (spinning.value) playSound('spin'); await nextTick() })
watch(answering, async value => { if (value) { await nextTick(); answerInput.value?.focus(); if(document.hidden || !document.hasFocus()) reportAway(true) } else clearTimeout(saveTimer) })
watch(seconds, value => { if (answering.value && value <= 5 && value !== lastBeep) { lastBeep = value; playSound('tick') } if (value === 0 && round.value?.status === 'answering') { playSound('stop'); store.refresh() } })
watch(() => g.value?.status, value => { if (value === 'finished') playSound('win') })

async function enter() {
  await unlockSound(); localStorage.setItem('bakalorea.nickname', nickname.value.trim())
  const ok = await store.enter(mode.value, { nickname: nickname.value.trim(), locale: locale.value, code: roomCode.value.trim(), name: roomName.value.trim() || t('roomPlaceholder'), target_score: Number(target.value), answer_duration: Number(duration.value), anti_cheat_mode: antiCheat.value, letters: letters.value.toUpperCase().replace(/[^A-Z]/g, ''), no_repeat: noRepeat.value, unique_points: Number(uniquePoints.value), duplicate_points: Number(duplicatePoints.value) })
  if (ok) router.push(`/salle/${store.code}`)
}
function changedAnswer() { savedState.value = 'saving'; clearTimeout(saveTimer); saveTimer = setTimeout(saveAnswer, 400) }
async function saveAnswer() {
  clearTimeout(saveTimer)
  if (!round.value || !answering.value || !round.value.participating) return
  const id = round.value.id, thisRevision = ++revision.value, value = draft.value
  activeSave = thisRevision; savedState.value = 'saving'
  try { await store.request(`/rounds/${id}/answer`, 'PATCH', { answer: value, revision: thisRevision }, true); if (round.value?.id === id && activeSave === thisRevision && draft.value === value) savedState.value = 'saved' }
  catch { if (round.value?.id === id && activeSave === thisRevision) savedState.value = 'notSaved' }
}
async function start() { await unlockSound(); if (g.value) await store.action(`/games/${g.value.id}/rounds/start`) }
async function copyCode() { try { await navigator.clipboard.writeText(store.code); copied.value = true; setTimeout(() => copied.value = false, 2500) } catch { const el = document.querySelector('.room-code'); if(el) { const range = document.createRange(); range.selectNodeContents(el); window.getSelection()?.removeAllRanges(); window.getSelection()?.addRange(range) } } }
async function leave() { if (g.value && await store.action(`/games/${g.value.id}/leave`)) { leaveDialog.value?.close(); store.forget(); router.push('/') } }
async function newGame() { if(g.value) await store.action(`/games/${g.value.id}/leave`); store.forget(); router.push('/') }
function reportAway(isAway: boolean) {
  if (!answering.value || !round.value?.participating || away === isAway || store.replaced) return
  away = isAway
  store.request('/anti-cheat/events', 'POST', { round_id: round.value.id, event: isAway ? 'away' : 'back' }, true).catch(() => {})
}
const onVisibility = () => reportAway(document.hidden)
const onBlur = () => reportAway(true)
const onFocus = () => { reportAway(false); store.refresh() }
const captureInstall = (e: Event) => { e.preventDefault(); installPrompt.value = e }
async function install() { await installPrompt.value?.prompt(); installPrompt.value = null }
onMounted(async () => {
  document.documentElement.lang = locale.value
  clock = setInterval(() => now.value = store.serverNow(), 80)
  document.addEventListener('visibilitychange', onVisibility); window.addEventListener('blur', onBlur); window.addEventListener('focus', onFocus); window.addEventListener('beforeinstallprompt', captureInstall)
  await router.isReady()
  if (store.token) { await store.resume(); if (!store.terminal) router.replace(`/salle/${store.code}`) }
  else if (route.params.code) { roomCode.value = String(route.params.code); mode.value = 'join'; router.replace('/') }
})
onUnmounted(() => { clearInterval(clock); clearTimeout(saveTimer); store.stop(); document.removeEventListener('visibilitychange', onVisibility); window.removeEventListener('blur', onBlur); window.removeEventListener('focus', onFocus); window.removeEventListener('beforeinstallprompt', captureInstall) })
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <a href="/" class="brand" @click.prevent="inRoom ? null : router.push('/')"><span class="brand-mark">B<span></span></span><span>Bakalorea<small>{{ t('tagline') }}</small></span></a>
      <div class="header-actions">
        <button class="text-button rules-link" @click="rulesDialog?.showModal()"><HelpCircle :size="18"/>{{ t('rules') }}</button>
        <button v-if="installPrompt" class="icon-button" :aria-label="t('install')" @click="install"><Download :size="19"/></button>
        <button class="icon-button" :aria-label="t('sound')" :aria-pressed="soundOn" @click="toggleSound"><Volume2 v-if="soundOn" :size="20"/><VolumeX v-else :size="20"/></button>
        <label class="language-picker"><Globe2 :size="17"/><span class="sr-only">{{ t('language') }}</span><select v-model="locale"><option value="mg">MG</option><option value="fr">FR</option><option value="en">EN</option></select></label>
      </div>
    </header>

    <div v-if="store.error" class="toast error" role="alert"><span>{{ errorText }}</span><button class="icon-button" :aria-label="t('close')" @click="store.error = ''"><X :size="18"/></button></div>
    <div v-if="store.replaced || store.terminal" class="session-screen card">
      <KeyRound :size="36"/><h1>{{ store.replaced ? t('sessionReplaced') : t('error.unauthorized') }}</h1>
      <button v-if="store.replaced" class="primary" @click="store.resume">{{ t('resume') }}</button><button class="secondary" @click="store.forget(); router.push('/')">{{ t('backHome') }}</button>
    </div>
    <template v-else>
      <main v-if="!inRoom" class="home-layout">
        <section class="home-main">
          <div class="eyebrow"><span class="mini-tiles"><i>B</i><i>A</i><i>K</i></span>{{ t('playTogether') }}</div>
          <h1>{{ t('welcome') }}<span class="heading-spark">✳</span></h1>
          <p class="intro">{{ t('intro') }}</p>
          <div class="card setup-card">
            <div class="tabs" role="tablist"><button id="create-tab" :class="{ active: mode === 'create' }" role="tab" :aria-selected="mode === 'create'" aria-controls="setup-panel" @click="mode = 'create'"><Plus :size="20"/>{{ t('create') }}</button><button id="join-tab" :class="{ active: mode === 'join' }" role="tab" :aria-selected="mode === 'join'" aria-controls="setup-panel" @click="mode = 'join'"><KeyRound :size="19"/>{{ t('join') }}</button></div>
            <form id="setup-panel" role="tabpanel" :aria-labelledby="mode === 'create' ? 'create-tab' : 'join-tab'" @submit.prevent="enter">
              <label class="field">{{ t('nickname') }}<div class="input-icon"><UserRound :size="19"/><input v-model="nickname" required minlength="2" maxlength="24" :placeholder="t('nicknamePlaceholder')" autocomplete="nickname" data-testid="nickname"></div></label>
              <label v-if="mode === 'create'" class="field">{{ t('roomName') }}<input v-model="roomName" maxlength="60" :placeholder="t('roomPlaceholder')"></label>
              <label v-else class="field">{{ t('code') }}<input v-model="roomCode" class="code-input" required pattern="[a-zA-Z0-9]{6}" maxlength="6" :placeholder="t('codePlaceholder')" autocomplete="off" autocapitalize="characters" data-testid="join-code"></label>
              <template v-if="mode === 'create'">
                <div class="quick-settings"><label class="field"><span><Trophy :size="16"/>{{ t('target') }}</span><div class="suffix-input"><input v-model="target" type="number" min="10" max="2000" required data-testid="target"><span>{{ t('points') }}</span></div></label><label class="field"><span><Timer :size="16"/>{{ t('duration') }}</span><select v-model="duration"><option :value="30">{{ t('relax') }} · 30 s</option><option :value="15">{{ t('normal') }} · 15 s</option><option :value="10">{{ t('speed') }} · 10 s</option></select></label></div>
                <button type="button" class="advanced-toggle" :aria-expanded="advanced" @click="advanced = !advanced"><Settings2 :size="17"/>{{ t('settings') }}<ChevronDown :size="17" :class="{ flipped: advanced }"/></button>
                <div v-if="advanced" class="advanced-settings">
                  <label class="field">{{ t('antiCheat') }}<select v-model="antiCheat"><option value="soft">{{ t('soft') }}</option><option value="normal">{{ t('normal') }}</option><option value="strict">{{ t('strict') }}</option></select><small>{{ t(`${antiCheat}Help`) }}</small></label>
                  <label class="field">{{ t('letters') }}<input v-model="letters" required minlength="2" maxlength="26" pattern="[a-zA-Z]{2,26}" autocapitalize="characters" data-testid="letters"></label>
                  <label class="checkbox"><input v-model="noRepeat" type="checkbox">{{ t('noRepeat') }}</label>
                  <div class="quick-settings"><label class="field">{{ t('uniquePoints') }}<input v-model="uniquePoints" required type="number" min="1" max="100"></label><label class="field">{{ t('duplicatePoints') }}<input v-model="duplicatePoints" required type="number" min="0" :max="uniquePoints"></label></div>
                </div>
              </template>
              <button class="primary full" :disabled="store.busy" type="submit"><Plus v-if="mode === 'create'" :size="20"/><Users v-else :size="20"/>{{ store.busy ? t('loading') : t(mode === 'create' ? 'createAction' : 'joinAction') }}</button>
              <div class="form-foot"><Users :size="14"/>{{ t('playersRange') }}<span>·</span><CheckCircle2 :size="14"/>{{ t('noAccount') }}</div>
            </form>
          </div>
        </section>
        <aside class="home-aside">
          <section class="rule-card"><div class="rule-card-top"><span class="eyebrow">BAKALOREA</span><Sparkles :size="22"/></div><h2>{{ t('howTo') }}</h2><div v-for="n in 3" :key="n" class="rule-step"><span>{{ n.toString().padStart(2, '0') }}</span><div><h3>{{ t(`rule${n}Title`) }}</h3><p>{{ t(`rule${n}`) }}</p></div></div><div class="score-tip"><Trophy :size="23"/><p>{{ t('scoring') }}</p></div></section>
          <section class="categories-block"><h2>{{ t('categories') }}<span>08</span></h2><div class="category-grid"><div v-for="c in categories" :key="c.code" class="category-item"><span :class="['category-icon', c.color]"><component :is="c.icon" :size="18"/></span>{{ t(`category.${c.code}`) }}</div></div></section>
        </aside>
      </main>

      <main v-else-if="g" class="room-layout">
        <div v-if="store.disconnected" class="connection-banner" role="status"><WifiOff :size="18"/>{{ t('connectionLost') }}</div>
        <div class="room-topline"><div class="breadcrumbs">{{ t('room') }} <span>/</span> <strong>{{ g.name }}</strong></div><button class="text-button" @click="leaveDialog?.showModal()"><LogOut :size="17"/>{{ t('leave') }}</button></div>
        <section class="game-main">
          <template v-if="g.status === 'lobby'">
            <div class="eyebrow"><Users :size="17"/>{{ t('privateRooms') }}</div><h1>{{ t('ready') }}</h1><p class="intro">{{ t('lobbyText') }}</p>
            <div class="card lobby-card"><div class="invite-block"><span class="eyebrow">{{ t('invite') }}</span><div class="room-code" data-testid="room-code">{{ g.code }}</div><button class="secondary" @click="copyCode"><Check v-if="copied" :size="17"/><Copy v-else :size="17"/>{{ t(copied ? 'copied' : 'copy') }}</button></div><div class="player-heading"><h2>{{ t('players') }}</h2><span class="pill">{{ onlineCount }} / 12</span></div><div class="lobby-players"><div v-for="(p, index) in g.players" :key="p.id" class="lobby-player"><span :class="['avatar', 'avatar-' + index % 5]">{{ initials(p.nickname) }}</span><span class="player-info"><strong>{{ p.nickname }} <small v-if="p.id === g.me_id">({{ t('you') }})</small></strong><small>{{ p.id === g.host_id ? t('host') : t(p.online ? 'online' : 'offline') }}</small></span><Crown v-if="p.id === g.host_id" :size="18" class="gold"/><span v-else-if="p.online" class="online-dot" :aria-label="t('online')"></span></div><div v-if="onlineCount < 2" class="empty-player"><Plus :size="22"/>{{ t('waitingPlayer') }}</div></div><button v-if="store.isHost" class="primary full" :disabled="store.busy || onlineCount < 2 || store.disconnected" @click="start"><Sparkles :size="19"/>{{ t('start') }}</button><p v-else class="wait-message">{{ t('waitingHost') }}</p><p v-if="onlineCount < 2" class="hint center">{{ t('needPlayers') }}</p></div>
          </template>

          <template v-else-if="g.status === 'finished'">
            <div class="card victory-card"><div class="trophy-medal"><Trophy :size="64"/></div><span class="eyebrow">BAKALOREA</span><h1>{{ t('winner', { name: playerName(g.winner_id) }) }}</h1><p>{{ t('winnerText') }}</p><div class="winner-score">{{ ranking[0]?.score }} <small>{{ t('points') }}</small></div><button class="primary" @click="newGame"><RotateCcw :size="18"/>{{ t('newGame') }}</button></div>
          </template>

          <template v-else-if="round">
            <div class="round-title"><span class="eyebrow">{{ t('round') }} {{ String(round.number).padStart(2,'0') }}</span><span class="pill">{{ t(`category.${round.category}`) }}</span></div>
            <div v-if="round.status === 'answering'" :class="['card play-card', { urgent: seconds <= 5 && !spinning }]">
              <div class="play-top"><h2>{{ t(spinning ? 'getReady' : seconds === 0 ? 'stop' : 'yourTurn') }}</h2><div v-if="!spinning" class="timer" role="timer" :aria-label="t('duration')"><Timer :size="20"/><strong>{{ seconds }}</strong><span>s</span></div></div>
              <div class="letter-stage"><p>{{ t(spinning ? 'spin' : 'startsWith') }}</p><div :class="['letter-tile', { spinning }]" data-testid="letter">{{ spinning ? spinLetter : round.letter }}</div><h2>{{ t(`category.${round.category}`) }}</h2></div>
              <div class="time-track"><div :style="{ width: `${spinning ? 100 : seconds / g.answer_duration * 100}%` }"></div></div>
              <form v-if="round.participating" @submit.prevent="saveAnswer"><label class="field">{{ t('answer') }}<div class="answer-field"><input ref="answerInput" v-model="draft" :disabled="!answering || store.replaced" :placeholder="t('answerPlaceholder')" maxlength="120" autocomplete="off" autocorrect="off" autocapitalize="off" :spellcheck="false" data-testid="answer" @input="changedAnswer"><button class="icon-button" :disabled="!answering" :aria-label="t('save')" type="submit"><Check :size="22"/></button></div></label><div class="answer-status" aria-live="polite"><span v-if="savedState" :class="{ danger: savedState === 'notSaved' }"><CheckCircle2 v-if="savedState === 'saved'" :size="15"/>{{ t(savedState) }}</span><span v-else><LockKeyhole :size="14"/>{{ t('privateAnswer') }}</span></div></form><p v-else>{{ t('spectating') }}</p>
              <p class="focus-hint"><ShieldCheck :size="15"/>{{ t('stayFocused') }}</p>
            </div>

            <template v-else-if="round.status === 'judging'">
              <div class="section-title"><div><h1>{{ t('judging') }}<span class="title-dot">.</span></h1><p class="intro">{{ t('judgingText') }}</p></div><div class="small-letter">{{ round.letter }}</div></div>
              <div class="stop-banner"><LockKeyhole :size="17"/><strong>STOP</strong>{{ t('stopText') }}</div>
              <div class="answer-cards"><article v-for="a in round.answers" :key="a.id" class="card judgment-card"><div class="judgment-top"><span class="avatar avatar-small">{{ initials(playerName(a.player_id)) }}</span><strong>{{ playerName(a.player_id) }}</strong><span v-if="a.player_id === g.me_id" class="pill">{{ t('you') }}</span><span v-if="a.flagged" class="flag" :title="t('flagged')"><Flag :size="17"/><span>{{ t('flagged') }}</span></span></div><h3 class="revealed-answer">{{ a.answer || t('empty') }}</h3><p v-if="a.invalid_reason" class="invalid-reason"><X :size="16"/>{{ reasonLabel(a.invalid_reason) }}</p><template v-else><div v-if="a.player_id !== g.me_id && round.participating" class="vote-buttons"><button v-for="(icon, key) in { valid: Check, invalid: X, uncertain: HelpCircle }" :key="key" :class="['vote', key, { selected: a.my_vote === key }]" :disabled="store.busy || store.disconnected" :aria-pressed="a.my_vote === key" @click="store.action(`/answers/${a.id}/votes`, { vote: key })"><component :is="icon" :size="18"/>{{ t(key) }}</button></div><p v-else class="own-answer"><LockKeyhole :size="14"/>{{ t('ownAnswer') }}</p><div class="vote-counts"><span><Check :size="14"/>{{ a.votes.valid }}</span><span><X :size="14"/>{{ a.votes.invalid }}</span><span><HelpCircle :size="14"/>{{ a.votes.uncertain }}</span></div><div v-if="round.ready && a.votes.tied" class="tie-box"><span>{{ t('tie') }} · {{ t('referee', { name: playerName(a.referee_id) }) }}</span><div v-if="a.referee_id === g.me_id"><button class="secondary small" :class="{ chosen: a.tie_decision === true }" :disabled="store.busy" @click="store.action(`/answers/${a.id}/decision`, { valid: true })">{{ t('valid') }}</button><button class="secondary small" :class="{ chosen: a.tie_decision === false }" :disabled="store.busy" @click="store.action(`/answers/${a.id}/decision`, { valid: false })">{{ t('invalid') }}</button></div><strong v-else-if="a.tie_decision !== null">{{ t(a.tie_decision ? 'valid' : 'invalid') }}</strong></div></template></article></div>
              <div class="judging-actions"><p class="hint">{{ t(!round.ready ? 'votesPending' : ties ? 'tiesPending' : 'waitingResults') }}</p><button v-if="store.isHost" class="primary full" :disabled="store.busy || !round.ready || ties || store.disconnected" @click="store.action(`/rounds/${round.id}/finish-judging`)"><Trophy :size="18"/>{{ t('finishJudging') }}</button></div>
            </template>

            <template v-else>
              <div class="section-title"><div><h1>{{ t('results') }}</h1><p class="intro">{{ t('resultsText') }}</p></div><div class="small-letter">{{ round.letter }}</div></div>
              <div class="card result-card"><div v-for="a in round.answers" :key="a.id" class="result-row"><span class="avatar avatar-small">{{ initials(playerName(a.player_id)) }}</span><div><strong>{{ playerName(a.player_id) }}</strong><p>{{ a.answer || t('empty') }}</p></div><span :class="['points-award', { zero: a.points === 0 }]">+{{ a.points }}</span></div></div><p v-if="tiedLeaders" class="tiebreak-message"><Trophy :size="20"/>{{ t('tiebreak') }}</p><button v-if="store.isHost" class="primary full" :disabled="store.busy || onlineCount < 2" @click="start"><RotateCcw :size="18"/>{{ t('next') }}</button><p v-else class="wait-message">{{ t('waitingHost') }}</p>
            </template>
          </template>
        </section>

        <aside class="room-aside"><section class="card scoreboard"><div class="scoreboard-heading"><Trophy :size="20"/><h2>{{ t('ranking') }}</h2></div><p class="hint">{{ t('goal', { score: g.target_score }) }}</p><div v-for="(p, index) in ranking" :key="p.id" :class="['rank-row', { me: p.id === g.me_id }]"><span class="rank-number">{{ index + 1 }}</span><div class="rank-player"><strong>{{ p.nickname }} <small v-if="p.id === g.me_id">({{ t('you') }})</small></strong><div class="score-track"><span :style="{ width: `${Math.min(100, p.score / g.target_score * 100)}%` }"></span></div></div><strong class="rank-score">{{ p.score }}</strong></div></section><section class="room-settings"><div><Timer :size="18"/><span>{{ t('duration') }}</span><strong>{{ g.answer_duration }} s</strong></div><div><Trophy :size="18"/><span>{{ t('uniquePoints') }}</span><strong>+{{ g.unique_points }}</strong></div><div><Users :size="18"/><span>{{ t('duplicatePoints') }}</span><strong>+{{ g.duplicate_points }}</strong></div><div><ShieldCheck :size="18"/><span>{{ t('antiCheat') }}</span><strong>{{ t(g.anti_cheat_mode) }}</strong></div><p>{{ t(`${g.anti_cheat_mode}Help`) }}</p></section><button class="history-toggle" :aria-expanded="showHistory" @click="showHistory = !showHistory"><History :size="18"/>{{ t('history') }}<ChevronDown :size="16"/></button><div v-if="showHistory" class="history-list"><p v-if="!g.history.length" class="hint">{{ t('noHistory') }}</p><details v-for="h in g.history" :key="h.number"><summary>{{ h.number }} · {{ h.letter }} · {{ t(`category.${h.category}`) }}</summary><p v-for="a in h.answers" :key="a.player_id"><strong>{{ playerName(a.player_id) }}</strong> · {{ a.answer || '—' }} <span>+{{ a.points_awarded }}</span></p></details></div></aside>
      </main>
      <main v-else class="session-screen card"><div class="loader"></div><h2>{{ t('loading') }}</h2><button class="secondary" @click="store.resume">{{ t('retry') }}</button><button class="text-button" @click="store.forget(); router.push('/')">{{ t('backHome') }}</button></main>
    </template>

    <footer><span>BAKALOREA <span class="footer-dot">✳</span> {{ t('footer') }}</span><button class="text-button" @click="rulesDialog?.showModal()">{{ t('rules') }}</button></footer>
    <div v-if="soundBlocked" class="sound-notice" role="status">{{ t('soundBlocked') }}</div>
    <dialog ref="rulesDialog" class="modal"><div class="modal-heading"><h2>{{ t('rulesTitle') }}</h2><button class="icon-button" :aria-label="t('close')" @click="rulesDialog?.close()"><X :size="20"/></button></div><div v-for="n in 3" :key="n" class="modal-rule"><h3>{{ n }}. {{ t(`rule${n}Title`) }}</h3><p>{{ t(`rule${n}`) }}</p></div><p>{{ t('scoring') }}</p><p>{{ t('tieHelp') }}</p><button class="primary full" @click="rulesDialog?.close()">{{ t('close') }}</button></dialog>
    <dialog ref="leaveDialog" class="modal"><h2>{{ t('leaveTitle') }}</h2><p>{{ t('leaveText') }}</p><div class="dialog-actions"><button class="secondary" @click="leaveDialog?.close()">{{ t('cancel') }}</button><button class="primary" :disabled="store.busy" @click="leave">{{ t('leave') }}</button></div></dialog>
  </div>
</template>
