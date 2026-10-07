<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Activity, ArrowLeft, Gamepad2, LockKeyhole, LogOut, RefreshCw, ShieldCheck, Trophy, Users } from 'lucide-vue-next'

interface GameSummary {
  id: number; code: string; name: string; status: string; created_at: string
  players_count: number; online_players_count: number; rounds_count: number
}
interface Dashboard {
  totals: { games: number; active_games: number; finished_games: number; players: number; online_players: number }
  games: GameSummary[]
}

const username = ref(''), password = ref(''), authenticated = ref(false)
const busy = ref(false), loading = ref(false), error = ref(''), dashboard = ref<Dashboard | null>(null)

async function api(path: string, method = 'GET', body?: object) {
  const response = await fetch(`/api/admin/${path}`, {
    method, credentials: 'same-origin', cache: 'no-store',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    ...(body ? { body: JSON.stringify(body) } : {}),
  })
  if (!response.ok) throw new Error(String(response.status))
  return response.json()
}
async function refresh() {
  loading.value = true; error.value = ''
  try { dashboard.value = await api('dashboard') }
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
    username.value = result.username; password.value = ''; authenticated.value = true
    await refresh()
  } catch (cause) {
    error.value = cause instanceof Error && cause.message === '429' ? 'Trop de tentatives. Réessayez dans une minute.'
      : cause instanceof Error && cause.message === '401' ? 'Identifiant ou mot de passe incorrect.'
      : 'Connexion impossible. Réessayez.'
  } finally { busy.value = false }
}
async function logout() {
  try {
    await api('logout', 'POST')
    authenticated.value = false; dashboard.value = null; password.value = ''; error.value = ''
  } catch { error.value = 'Impossible de se déconnecter. Réessayez.' }
}
const dateLabel = (value: string) => new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
const statusLabel = (value: string) => ({ lobby: 'En attente', playing: 'En cours', finished: 'Terminée' } as Record<string, string>)[value] || value

onMounted(async () => {
  try { const result = await api('status'); username.value = result.username; authenticated.value = true; await refresh() }
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
      <div v-if="dashboard" class="admin-stats">
        <div class="admin-stat card"><Gamepad2 :size="22"/><span>Parties</span><strong>{{ dashboard.totals.games }}</strong></div>
        <div class="admin-stat card"><Activity :size="22"/><span>En cours</span><strong>{{ dashboard.totals.active_games }}</strong></div>
        <div class="admin-stat card"><Users :size="22"/><span>Joueurs connectés</span><strong>{{ dashboard.totals.online_players }}</strong></div>
        <div class="admin-stat card"><Trophy :size="22"/><span>Terminées</span><strong>{{ dashboard.totals.finished_games }}</strong></div>
      </div>
      <section class="admin-games card"><div class="admin-games-heading"><h2>Dernières parties</h2><small>20 plus récentes</small></div><p v-if="loading && !dashboard" class="admin-empty">Chargement…</p><p v-else-if="dashboard && !dashboard.games.length" class="admin-empty">Aucune partie créée pour le moment.</p><div v-else-if="dashboard" class="admin-table-wrap"><table><thead><tr><th>Partie</th><th>État</th><th>Joueurs</th><th>Manches</th><th>Créée le</th></tr></thead><tbody><tr v-for="game in dashboard.games" :key="game.id"><td><strong>{{ game.name }}</strong><small>{{ game.code }}</small></td><td><span :class="['admin-status', game.status]">{{ statusLabel(game.status) }}</span></td><td>{{ game.online_players_count }} / {{ game.players_count }}</td><td>{{ game.rounds_count }}</td><td>{{ dateLabel(game.created_at) }}</td></tr></tbody></table></div></section>
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
.admin-games{padding:23px}.admin-games-heading{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}.admin-games-heading h2{font-size:1.05rem}.admin-games-heading small,.admin-empty{color:#77848a;font-size:.78rem}.admin-table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;text-align:left;font-size:.8rem}th{color:#77848a;font-size:.72rem;font-weight:600;padding:12px 10px;border-bottom:1px solid #e1e5e9;white-space:nowrap}td{padding:13px 10px;border-bottom:1px solid #edf0f1;white-space:nowrap}tr:last-child td{border-bottom:0}td:first-child strong,td:first-child small{display:block}td:first-child small{color:#82919a;margin-top:3px;font-size:.72rem}.admin-status{display:inline-block;padding:5px 8px;border-radius:6px;background:#edf2f2;color:#627276;font-size:.72rem}.admin-status.playing{background:#e6f4ec;color:#287c58}.admin-status.finished{background:#fff2dd;color:#9a7130}
@media(max-width:800px){.admin-page{padding:0 22px 45px}.admin-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-heading{align-items:flex-start;flex-direction:column}}
@media(max-width:460px){.admin-page{padding:0 16px 35px}.admin-topbar{height:75px}.admin-login{margin:35px auto;padding:23px}.admin-stats{gap:9px}.admin-stat{padding:14px}.admin-stat strong{font-size:1.4rem}.admin-actions{flex-wrap:wrap}}
</style>
