<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Flag, RotateCcw } from 'lucide-vue-next'
import { useGame } from './store'

defineEmits<{ next: [] }>()
const store = useGame(), { t } = useI18n()
const round = computed(() => store.game?.round)
const own = computed(() => round.value?.answers?.find(a => a.player_id === store.game?.me_id))
const mine = computed(() => round.value?.contests?.find(c => c.player_id === store.game?.me_id))
const pending = computed(() => round.value?.contests?.filter(c => !c.acknowledged_at) || [])
const remaining = computed(() => store.game?.contest_remaining ?? 0)
const formOpen = ref(false), reason = ref(''), reasonInput = ref<HTMLTextAreaElement>()
const playerName = (id: number) => store.game?.players.find(p => p.id === id)?.nickname || '—'
async function openForm() { formOpen.value = true; await nextTick(); reasonInput.value?.focus() }
async function submit() {
  const id = round.value?.id
  if (!id || remaining.value === 0 || reason.value.trim().length < 3 || store.busy || store.disconnected) return
  if (await store.action(`/rounds/${id}/contest`, { reason: reason.value.trim() })) {
    formOpen.value = false; reason.value = ''
  }
}
watch(() => round.value?.id, () => { formOpen.value = false; reason.value = '' })
watch(mine, value => { if (value) formOpen.value = false })
watch(remaining, value => { if (value === 0) formOpen.value = false })
</script>

<template>
  <section v-if="round && store.game" class="round-contests" :aria-label="t('contest.title')">
    <div class="round-result-actions">
      <button v-if="own?.answer && !mine" type="button" class="secondary" :disabled="store.busy || store.disconnected || remaining === 0" :aria-expanded="formOpen" aria-controls="contest-form" aria-describedby="contest-quota" @click="openForm"><Flag :size="18"/>{{ t('contest.action') }}</button>
      <p v-if="mine" class="hint" role="status">{{ t(mine.acknowledged_at ? 'contest.acknowledged' : 'contest.sent') }}</p>
      <button v-if="store.isHost && store.game.status !== 'finished'" type="button" class="primary" :disabled="store.busy || store.disconnected || pending.length > 0 || store.game.players.filter(p => p.online).length < 2" @click="$emit('next')"><RotateCcw :size="18"/>{{ t('next') }}</button>
      <p v-else-if="!store.isHost && store.game.status !== 'finished'" class="wait-message">{{ t('waitingHost') }}</p>
    </div>
    <p v-if="own?.answer" id="contest-quota" class="hint" role="status">{{ t(remaining === 0 ? 'contest.exhausted' : 'contest.remaining', { count: remaining, limit: store.game.contest_limit }) }}</p>
    <form v-if="formOpen && !mine" id="contest-form" class="card contest-form" @submit.prevent="submit">
      <p class="hint" id="contest-help">{{ t('contest.help') }}</p>
      <label class="field" for="contest-reason">{{ t('contest.reason') }}<textarea id="contest-reason" ref="reasonInput" v-model="reason" required minlength="3" maxlength="300" rows="3" aria-describedby="contest-help" :disabled="store.busy" /></label>
      <div class="round-result-actions"><button type="button" class="secondary" :disabled="store.busy" @click="formOpen = false">{{ t('cancel') }}</button><button type="submit" class="primary" :disabled="store.busy || store.disconnected || reason.trim().length < 3">{{ t('contest.submit') }}</button></div>
    </form>
    <div v-if="pending.length" class="card contest-notice" role="status">
      <h2>{{ t('contest.pending') }}</h2>
      <p class="hint">{{ t('contest.wait') }}</p>
      <article v-for="contest in pending" :key="contest.id" class="contest-item">
        <strong>{{ t('contest.notice', { name: playerName(contest.player_id) }) }}</strong>
        <p>{{ contest.reason || round.comments?.find(c => c.id === contest.comment_id)?.body }}</p>
        <button v-if="store.isHost" type="button" class="secondary" :disabled="store.busy || store.disconnected" @click="store.action(`/rounds/${round.id}/contests/${contest.id}/acknowledge`)">{{ t('contest.acknowledge') }}</button>
      </article>
    </div>
  </section>
</template>

<style scoped>
.round-contests{margin:20px 0}.round-result-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px}.round-result-actions>button{flex:1}.contest-form,.contest-notice{margin-top:16px;padding:20px;min-width:0}.contest-form .field{margin:14px 0}.contest-form textarea{width:100%;box-sizing:border-box;resize:vertical;min-height:90px;font:inherit;border:1px solid var(--border);border-radius:10px;padding:12px;color:var(--ink);background:#fff}.contest-form textarea:focus{outline:2px solid var(--accent);outline-offset:2px}.contest-notice{border-color:#dba455;background:#fff8ed}.contest-notice h2{font-size:1rem;margin-bottom:8px}.contest-item{margin-top:14px;overflow-wrap:anywhere}.contest-item p{white-space:pre-wrap;margin:8px 0}.contest-item button{margin-top:4px}
</style>
