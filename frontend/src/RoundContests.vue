<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Flag } from 'lucide-vue-next'
import { useGame } from './store'

withDefaults(defineProps<{ mode?: 'answer' | 'notice' }>(), { mode: 'notice' })
const store = useGame(), { t } = useI18n()
const round = computed(() => store.game?.round)
const own = computed(() => round.value?.answers?.find(a => a.player_id === store.game?.me_id))
const mine = computed(() => round.value?.contests?.find(c => c.player_id === store.game?.me_id))
const pending = computed(() => round.value?.contests?.filter(c => !c.acknowledged_at) || [])
const remaining = computed(() => store.game?.contest_remaining ?? 0)
const playerName = (id: number) => store.game?.players.find(p => p.id === id)?.nickname || '—'
async function submit() {
  const id = round.value?.id
  if (!id || mine.value || remaining.value === 0 || store.busy || store.disconnected) return
  await store.action(`/rounds/${id}/contest`)
}
</script>

<template>
  <section v-if="round && store.game && (mode === 'answer' || pending.length)" class="round-contests" :class="{ 'answer-contest': mode === 'answer' }" :aria-label="t('contest.title')">
    <template v-if="mode === 'answer' && round.status === 'judging'">
    <div class="round-result-actions">
      <button v-if="own?.answer && !mine" type="button" class="secondary" :disabled="store.busy || store.disconnected || remaining === 0" aria-describedby="contest-quota" @click="submit"><Flag :size="18"/>{{ t('contest.action') }}</button>
      <p v-if="mine" class="hint" role="status">{{ t(mine.acknowledged_at ? 'contest.acknowledged' : 'contest.sent') }}</p>
    </div>
    <p v-if="own?.answer" id="contest-quota" class="hint" role="status">{{ t(remaining === 0 ? 'contest.exhausted' : 'contest.remaining', { count: remaining, limit: store.game.contest_limit }) }}</p>
    </template>
    <div v-if="mode === 'notice' && pending.length" class="card contest-notice" role="status">
      <h2>{{ t('contest.pending') }}</h2>
      <p class="hint">{{ t('contest.wait') }}</p>
      <article v-for="contest in pending" :key="contest.id" class="contest-item">
        <strong>{{ t('contest.notice', { name: playerName(contest.player_id) }) }}</strong>
        <p v-if="contest.reason">{{ contest.reason }}</p>
        <button v-if="store.isHost" type="button" class="secondary" :disabled="store.busy || store.disconnected" @click="store.action(`/rounds/${round.id}/contests/${contest.id}/acknowledge`)">{{ t('contest.acknowledge') }}</button>
      </article>
    </div>
  </section>
</template>

<style scoped>
.round-contests.answer-contest{margin:10px 0}.answer-contest .round-result-actions>button{min-width:0}.answer-contest .hint{margin-top:6px;font-size:.72rem}
.round-contests{margin:20px 0}.round-result-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px}.round-result-actions>button{flex:1}.contest-notice{margin-top:16px;padding:20px;min-width:0;border-color:#dba455;background:#fff8ed}.contest-notice h2{font-size:1rem;margin-bottom:8px}.contest-item{margin-top:14px;overflow-wrap:anywhere}.contest-item p{white-space:pre-wrap;margin:8px 0}.contest-item button{margin-top:4px}
</style>
