<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import { Activity, ArrowLeft, Gamepad2, LockKeyhole, LogOut, RefreshCw, ShieldCheck, Trash2, Trophy, Users, X } from 'lucide-vue-next'

interface GameSummary {
  id: number; code: string; name: string; status: string; created_at: string; can_delete: boolean
  players_count: number; online_players_count: number; rounds_count: number
}
interface PlayerSummary { id: number; nickname: string; score: number; online: boolean; left: boolean }
interface Dashboard {
  totals: { games: number; active_games: number; finished_games: number; players: number; online_players: number }
  games: GameSummary[]
  pagination: { page: number; pages: number; total: number }
  finished_retention_days: number
}

const username = ref(''), password = ref(''), authenticated = ref(false)
const busy = ref(false), loading = ref(false), error = ref(''), dashboard = ref<Dashboard | null>(null)
type GameFilter = 'all' | 'finished' | 'lobby' | 'stale'
const csrf = ref(''), filter = ref<GameFilter>('all'), page = ref(1), deleting = ref(false), notice = ref('')
const retentionDays = ref(30), savingRetention = ref(false)
const selectedGame = ref<GameSummary | null>(null), players = ref<PlayerSummary[]>([]), playersLoading = ref(false), playersError = ref('')
const playersDialog = ref<HTMLDialogElement | null>(null)

async function api(path: string, method = 'GET', body?: object) {
  const response = await fetch(`/api/admin/${path}`, {
    method, credentials: 'same-origin', cache: 'no-store',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(['DELETE', 'PUT'].includes(method) ? { 'X-Admin-CSRF': csrf.value } : {}) },
    ...(body ? { body: JSON.stringify(body) } : {}),
  })
  if (!response.ok) throw new Error(String(response.status))
  return response.json()
}
async function refresh() {
  loading.value = true; error.value = ''
  try {
    let result: Dashboard = await api(`dashboard?filter=${filter.value}&page=${page.value}`)
    if (page.value > result.pagination.pages) {
      page.value = result.pagination.pages
      result = await api(`dashboard?filter=${filter.value}&page=${page.value}`)
    }
    dashboard.value = result
    retentionDays.value = result.finished_retention_days
  }
  catch (cause) {
    if (cause instanceof Error && cause.message === '401') authenticated.value = false
    else error.value = 'Impossible de charger les données. Réessayez.'
  } finally { loading.value = false }
}
async function login() {
  if (busy.value) return
  busy.value = true; error.value = ''
  try {
    const result = await api('login', 'POST', { username: username.value.trim(), password: password.value })
    username.value = result.username; csrf.value = result.csrf; password.value = ''; authenticated.value = true
    await refresh()
  } catch (cause) {
    error.value = cause instanceof Error && cause.message === '429' ? 'Trop de tentatives. Réessayez dans une minute.'
      : cause instanceof Error && cause.message === '401' ? 'Identifiant ou mot de passe incorrect.'
      : 'Connexion impossible. Réessayez.'
  } finally { busy.value = false }
}
async function changeFilter(value: GameFilter) {
  filter.value = value; page.value = 1
  await refresh()
}
async function changePage(value: number) {
  if (!dashboard.value || value < 1 || value > dashboard.value.pagination.pages || loading.value) return
  page.value = value
  await refresh()
}
async function saveRetention() {
  const days = Number(retentionDays.value)
  if (savingRetention.value || !Number.isInteger(days) || days < 0 || days > 3650) {
    error.value = 'Choisissez une durée entre 0 et 3650 jours.'
    return
  }
  if (days > 0 && !window.confirm(`Supprimer chaque jour les parties terminées depuis plus de ${days} jours ?`)) return
  savingRetention.value = true; error.value = ''; notice.value = ''
  try {
    const result = await api('retention', 'PUT', { days })
    retentionDays.value = result.finished_retention_days
    if (dashboard.value) dashboard.value.finished_retention_days = result.finished_retention_days
    notice.value = days === 0 ? 'Purge automatique désactivée.' : `Purge automatique réglée à ${days} jours.`
  } catch { error.value = 'Impossible d’enregistrer la durée de conservation.' }
  finally { savingRetention.value = false }
}
async function deleteGame(game: GameSummary) {
  if (deleting.value || !game.can_delete || !window.confirm(`Supprimer définitivement la partie « ${game.name} » et tout son historique ?`)) return
  deleting.value = true; error.value = ''; notice.value = ''
  try {
    await api(`games/${game.id}`, 'DELETE')
    if (selectedGame.value?.id === game.id) closePlayers()
    notice.value = 'Partie et historique supprimés.'
    await refresh()
  } catch { await refresh(); error.value = 'Suppression impossible. Actualisez la liste et réessayez.' }
  finally { deleting.value = false }
}
async function showPlayers(game: GameSummary) {
  selectedGame.value = game; players.value = []; playersError.value = ''; playersLoading.value = true
  await nextTick()
  playersDialog.value?.showModal()
  try {
    const result = await api(`games/${game.id}/players`)
    if (selectedGame.value?.id === game.id) players.value = result.players
  } catch { if (selectedGame.value?.id === game.id) playersError.value = 'Impossible de charger les joueurs.' }
  finally { if (selectedGame.value?.id === game.id) playersLoading.value = false }
}
function closePlayers() { playersDialog.value?.close() }
function clearPlayers() { selectedGame.value = null; players.value = []; playersError.value = '' }
function closeOnBackdrop(event: MouseEvent) { if (event.target === playersDialog.value) closePlayers() }
async function deleteAllFinished() {
  if (deleting.value || !dashboard.value?.totals.finished_games || !window.confirm(`Supprimer définitivement les ${dashboard.value.totals.finished_games} parties terminées et tout leur historique ?`)) return
  deleting.value = true; error.value = ''; notice.value = ''
  let deleted = 0
  try {
    let remaining: number
    do {
      const result = await api('games/finished', 'DELETE')
      deleted += result.deleted
      remaining = result.remaining
      if (remaining > 0 && result.deleted === 0) throw new Error('Purge incomplète')
    } while (remaining > 0)
    notice.value = `${deleted} partie${deleted > 1 ? 's' : ''} et leur historique supprimés.`
    await refresh()
  } catch {
    await refresh()
    error.value = `Purge interrompue après ${deleted} partie${deleted > 1 ? 's' : ''}. Actualisez et réessayez.`
  } finally { deleting.value = false }
}
async function logout() {
  try {
    await api('logout', 'POST')
    authenticated.value = false; dashboard.value = null; password.value = ''; csrf.value = ''; error.value = ''; notice.value = ''; closePlayers(); clearPlayers()
  } catch { error.value = 'Impossible de se déconnecter. Réessayez.' }
}
const dateLabel = (value: string) => new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
const statusLabel = (value: string) => ({ lobby: 'En attente', playing: 'En cours', finished: 'Terminée' } as Record<string, string>)[value] || value

onMounted(async () => {
  try { const result = await api('status'); username.value = result.username; csrf.value = result.csrf; authenticated.value = true; await refresh() }
  catch { authenticated.value = false }
})
</script>

<template>
  <main class="admin-page">
    <header class="admin-topbar"><a href="/" class="admin-home"><ArrowLeft :size="17"/> Retour au jeu</a><span>BAKALOREA <span class="admin-mark">✳</span> ADMIN</span></header>

    <section v-if="!authenticated" class="admin-login card">
      <span class="admin-symbol"><ShieldCheck :size="28"/></span>
      <p class="admin-eyebrow">Accès privé</p>
      <h1>Espace admin</h1>
      <p class="admin-subtitle">Connectez-vous pour suivre l’activité des parties.</p>
      <form @submit.prevent="login">
        <label>Utilisateur<input v-model="username" required autocomplete="username" placeholder="Utilisateur"></label>
        <label>Mot de passe<input v-model="password" required type="password" autocomplete="current-password" placeholder="Mot de passe"></label>
        <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
        <button type="submit" class="primary full" :disabled="busy"><LockKeyhole :size="17"/>{{ busy ? 'Connexion…' : 'Se connecter' }}</button>
      </form>
    </section>

    <section v-else class="admin-content">
      <div class="admin-heading"><div><p class="admin-eyebrow">Tableau de bord</p><h1>Bonjour, {{ username }}</h1><p class="admin-subtitle">Vue d’ensemble des parties Bakalorea.</p></div><div class="admin-actions"><button class="secondary" :disabled="loading" @click="refresh"><RefreshCw :size="16"/>Actualiser</button><button class="admin-logout" @click="logout"><LogOut :size="16"/>Déconnexion</button></div></div>
      <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
      <p v-if="notice" class="admin-notice" role="status">{{ notice }}</p>
      <div v-if="dashboard" class="admin-stats">
        <div class="admin-stat card"><Gamepad2 :size="22"/><span>Parties</span><strong>{{ dashboard.totals.games }}</strong></div>
        <div class="admin-stat card"><Activity :size="22"/><span>En cours</span><strong>{{ dashboard.totals.active_games }}</strong></div>
        <div class="admin-stat card"><Users :size="22"/><span>Joueurs connectés</span><strong>{{ dashboard.totals.online_players }}</strong></div>
        <div class="admin-stat card"><Trophy :size="22"/><span>Terminées</span><strong>{{ dashboard.totals.finished_games }}</strong></div>
      </div>
      <section v-if="dashboard" class="admin-retention card"><div><h2>Conservation des parties terminées</h2><p>La purge s’exécute chaque jour. Saisissez 0 pour la désactiver.</p></div><form @submit.prevent="saveRetention"><label for="retention-days">Durée</label><input id="retention-days" v-model.number="retentionDays" type="number" min="0" max="3650" step="1" required><span>jours</span><button class="secondary" type="submit" :disabled="savingRetention || loading">{{ savingRetention ? 'Enregistrement…' : 'Enregistrer' }}</button></form></section>
      <section class="admin-games card">
        <div class="admin-games-heading"><div><h2>Historique des parties</h2><small>20 parties par page · suppression possible en attente, terminée ou en cours depuis plus de 3 h</small><small v-if="dashboard?.finished_retention_days">Les parties terminées sont supprimées après {{ dashboard.finished_retention_days }} jours.</small></div><button class="admin-danger" :disabled="deleting || !dashboard?.totals.finished_games" @click="deleteAllFinished"><Trash2 :size="15"/> Supprimer toutes les parties terminées</button></div>
        <div class="admin-filters"><button :class="{ selected: filter === 'all' }" :disabled="loading || deleting" @click="changeFilter('all')">Toutes</button><button :class="{ selected: filter === 'lobby' }" :disabled="loading || deleting" @click="changeFilter('lobby')">En attente</button><button :class="{ selected: filter === 'stale' }" :disabled="loading || deleting" @click="changeFilter('stale')">En cours &gt; 3 h</button><button :class="{ selected: filter === 'finished' }" :disabled="loading || deleting" @click="changeFilter('finished')">Terminées</button></div>
        <p v-if="loading && !dashboard" class="admin-empty">Chargement…</p><p v-else-if="dashboard && !dashboard.games.length" class="admin-empty">Aucune partie dans cette vue.</p>
        <div v-else-if="dashboard" class="admin-table-wrap"><table><thead><tr><th>Partie</th><th>État</th><th>Joueurs</th><th>Manches</th><th>Créée le</th><th>Action</th></tr></thead><tbody><tr v-for="game in dashboard.games" :key="game.id"><td><strong>{{ game.name }}</strong><small>{{ game.code }}</small></td><td><span :class="['admin-status', game.status]">{{ statusLabel(game.status) }}</span></td><td><button class="admin-players-link" :aria-label="`Afficher les joueurs de ${game.name}`" @click="showPlayers(game)"><Users :size="14"/> {{ game.online_players_count }} / {{ game.players_count }}</button></td><td>{{ game.rounds_count }}</td><td>{{ dateLabel(game.created_at) }}</td><td><button v-if="game.can_delete" class="admin-delete" :disabled="deleting" :aria-label="`Supprimer ${game.name}`" @click="deleteGame(game)"><Trash2 :size="15"/> Supprimer</button><span v-else class="admin-muted" :title="game.status === 'playing' ? 'Disponible après 3 heures de jeu' : ''">{{ game.status === 'playing' ? 'Avant 3 h' : '—' }}</span></td></tr></tbody></table></div>
        <nav v-if="dashboard && dashboard.pagination.pages > 1" class="admin-pagination" aria-label="Pages de l’historique"><button :disabled="loading || page === 1" @click="changePage(page - 1)">Précédente</button><span>Page {{ dashboard.pagination.page }} / {{ dashboard.pagination.pages }} · {{ dashboard.pagination.total }} parties</span><button :disabled="loading || page === dashboard.pagination.pages" @click="changePage(page + 1)">Suivante</button></nav>
      </section>
      <dialog ref="playersDialog" class="admin-modal card" aria-labelledby="admin-players-title" @close="clearPlayers" @click="closeOnBackdrop">
        <template v-if="selectedGame">
          <div class="admin-modal-heading"><div><p class="admin-eyebrow">Joueurs</p><h2 id="admin-players-title">{{ selectedGame.name }}</h2><small>{{ selectedGame.code }} · {{ selectedGame.players_count }} joueur{{ selectedGame.players_count > 1 ? 's' : '' }}</small></div><button class="admin-modal-close" aria-label="Fermer" @click="closePlayers"><X :size="20"/></button></div>
          <p v-if="playersLoading" class="admin-empty">Chargement…</p><p v-else-if="playersError" class="admin-error" role="alert">{{ playersError }}</p><p v-else-if="!players.length" class="admin-empty">Aucun joueur dans cette partie.</p>
          <ul v-else class="admin-player-list"><li v-for="player in players" :key="player.id"><span class="admin-player-avatar">{{ player.nickname.slice(0, 1).toUpperCase() }}</span><strong>{{ player.nickname }}</strong><span :class="['admin-player-presence', { online: player.online }]">{{ player.left ? 'Parti' : player.online ? 'En ligne' : 'Hors ligne' }}</span><span class="admin-player-score">{{ player.score }} isa</span></li></ul>
        </template>
      </dialog>
    </section>
  </main>
</template>

<style scoped>
.admin-page{min-height:100vh;max-width:1250px;margin:auto;padding:0 42px 60px;color:#131720}
.admin-topbar{height:96px;border-bottom:1px solid #e1e5e9;display:flex;align-items:center;justify-content:space-between;color:#557c6d;font-size:.76rem;font-weight:700;letter-spacing:.8px}
.admin-home{display:inline-flex;align-items:center;gap:8px;color:#68767b;letter-spacing:0;font-size:.85rem}.admin-home:hover{color:#2e8a7d}.admin-mark{color:#2e8a7d;margin:0 6px}
.admin-login{max-width:430px;margin:70px auto;padding:35px}.admin-symbol{width:50px;height:50px;display:grid;place-items:center;background:#e8f4ee;color:#2e8a7d;border-radius:13px;margin-bottom:20px}
.admin-eyebrow{color:#2e8a7d;text-transform:uppercase;letter-spacing:1.4px;font-size:.72rem;font-weight:800}.admin-login h1,.admin-heading h1{font-size:clamp(1.8rem,3vw,2.4rem);margin:7px 0}.admin-subtitle{color:#68767b;font-size:.9rem}
.admin-login form{display:grid;gap:17px;margin-top:27px}.admin-login label{display:grid;gap:8px;font-size:.82rem;font-weight:700}.admin-login input{width:100%;height:48px;border:1px solid #dce2e7;border-radius:9px;padding:0 13px;outline:0}.admin-login input:focus{border-color:#2e8a7d;box-shadow:0 0 0 3px #e7f2ef}.admin-error{color:#b54848;background:#fff0ee;border-radius:8px;padding:10px 12px;font-size:.82rem}
.admin-content{padding-top:42px}.admin-heading{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:30px}.admin-actions{display:flex;gap:10px;align-items:center}.admin-actions button{display:inline-flex;align-items:center;gap:7px}.admin-logout{border:0;background:none;color:#66747d;padding:9px;font-size:.82rem}.admin-logout:hover{color:#2e8a7d}
.admin-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:24px 0}.admin-stat{display:flex;flex-direction:column;gap:10px;padding:20px;color:#2e8a7d}.admin-stat span{color:#66747d;font-size:.8rem}.admin-stat strong{color:#131720;font-size:1.7rem;font-weight:800}
.admin-retention{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:19px 23px;margin-bottom:16px}.admin-retention h2{font-size:.98rem}.admin-retention p{color:#68767b;font-size:.78rem;margin-top:5px}.admin-retention form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.78rem;font-weight:700}.admin-retention input{width:75px;border:1px solid #dce2e7;border-radius:8px;padding:8px;font:inherit}.admin-retention .secondary{min-height:36px;font-size:.78rem;padding:7px 11px}
.admin-games{padding:23px}.admin-games-heading{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}.admin-games-heading h2{font-size:1.05rem}.admin-games-heading small,.admin-empty{color:#77848a;font-size:.78rem}.admin-table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;text-align:left;font-size:.8rem}th{color:#77848a;font-size:.72rem;font-weight:600;padding:12px 10px;border-bottom:1px solid #e1e5e9;white-space:nowrap}td{padding:13px 10px;border-bottom:1px solid #edf0f1;white-space:nowrap}tr:last-child td{border-bottom:0}td:first-child strong,td:first-child small{display:block}td:first-child small{color:#82919a;margin-top:3px;font-size:.72rem}.admin-status{display:inline-block;padding:5px 8px;border-radius:6px;background:#edf2f2;color:#627276;font-size:.72rem}.admin-status.playing{background:#e6f4ec;color:#287c58}.admin-status.finished{background:#fff2dd;color:#9a7130}
.admin-notice{color:#24674e;background:#e8f4ee;border-radius:8px;padding:10px 12px;font-size:.82rem}.admin-games-heading{gap:16px}.admin-games-heading small{display:block;margin-top:4px}.admin-danger,.admin-delete{display:inline-flex;align-items:center;gap:6px;border:1px solid #f0c9c6;border-radius:8px;background:#fff7f6;color:#a43f39;padding:9px 11px;font-size:.76rem;font-weight:700;cursor:pointer}.admin-danger:hover,.admin-delete:hover{background:#ffeae8}.admin-danger:disabled,.admin-delete:disabled{opacity:.5;cursor:not-allowed}.admin-delete{padding:6px 8px}.admin-muted{color:#9aa5aa}.admin-filters{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 10px}.admin-filters button{border:1px solid #dce5e2;background:#fff;color:#627276;border-radius:7px;padding:7px 12px;font-size:.76rem;cursor:pointer}.admin-filters button.selected{color:#247c62;background:#e8f4ee;border-color:#b8ded0;font-weight:700}
.admin-players-link{display:inline-flex;align-items:center;gap:5px;border:0;background:transparent;color:#287c67;font:inherit;font-weight:700;cursor:pointer;padding:5px 3px;text-decoration:underline;text-underline-offset:3px}.admin-players-link:hover{color:#155a49}.admin-modal{width:min(500px,calc(100% - 32px));max-height:min(75vh,650px);display:flex;flex-direction:column;padding:25px;box-shadow:0 24px 65px rgba(0,0,0,.2);margin:auto}.admin-modal:not([open]){display:none}.admin-modal::backdrop{background:rgba(12,25,30,.5)}.admin-modal-heading{display:flex;justify-content:space-between;gap:15px;align-items:start}.admin-modal-heading h2{margin:3px 0;font-size:1.35rem}.admin-modal-heading small{color:#77848a}.admin-modal-close{border:0;background:#f0f3f3;color:#52646a;width:34px;height:34px;border-radius:8px;display:grid;place-items:center;cursor:pointer}.admin-player-list{list-style:none;padding:0;margin:18px 0 0;overflow:auto}.admin-player-list li{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #edf0f1;font-size:.84rem}.admin-player-list li:last-child{border:0}.admin-player-avatar{width:32px;height:32px;flex:none;display:grid;place-items:center;border-radius:50%;background:#e8f4ee;color:#287c67;font-weight:800}.admin-player-presence{margin-left:auto;color:#82919a;font-size:.75rem}.admin-player-presence.online{color:#287c58}.admin-player-score{min-width:55px;text-align:right;color:#56666c;font-weight:700}.admin-pagination{display:flex;justify-content:center;align-items:center;gap:13px;flex-wrap:wrap;margin-top:17px;color:#68767b;font-size:.78rem}.admin-pagination button{border:1px solid #dce5e2;border-radius:7px;background:#fff;color:#287c67;padding:7px 10px;font-size:.78rem}.admin-pagination button:disabled{opacity:.45}
@media(max-width:800px){.admin-page{padding:0 22px 45px}.admin-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-heading,.admin-games-heading,.admin-retention{align-items:flex-start;flex-direction:column}}
@media(max-width:460px){.admin-page{padding:0 16px 35px}.admin-topbar{height:75px}.admin-login{margin:35px auto;padding:23px}.admin-stats{gap:9px}.admin-stat{padding:14px}.admin-stat strong{font-size:1.4rem}.admin-actions{flex-wrap:wrap}}
</style>
