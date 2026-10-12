<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ShieldCheck } from 'lucide-vue-next'
import { useGame, type Round } from './store'

const props = defineProps<{ round: Round; now: number; ready: boolean }>()
const { t } = useI18n(), store = useGame()
const dialog = ref<HTMLDialogElement>(), input = ref<HTMLInputElement>()
const code = ref(''), confirmed = ref(false), sending = ref(false), error = ref('')
const remaining = computed(() => Math.max(0, Math.ceil(((props.round.presence?.ends_at || 0) - props.now) / 1000)))
const done = computed(() => confirmed.value || props.round.presence?.confirmed)
async function confirm() {
  if (sending.value || done.value || !props.ready || !remaining.value) return
  sending.value = true; error.value = ''
  try {
    await store.request(`/rounds/${props.round.id}/presence`, 'POST', { code: code.value }, true)
    confirmed.value = true
    await store.refresh()
  } catch (e) {
    error.value = e instanceof Error && e.message === 'presence_wrong' ? 'presence.wrong' : 'presence.retry'
  } finally { sending.value = false }
}
watch(() => props.ready, async value => { if (value) { await nextTick(); input.value?.focus() } })
onMounted(async () => { dialog.value?.showModal(); await store.refresh(); await nextTick(); input.value?.focus() })
</script>

<template>
  <dialog ref="dialog" class="modal presence-modal" aria-labelledby="presence-title" aria-describedby="presence-description" @cancel.prevent>
    <h2 id="presence-title"><ShieldCheck :size="22"/>{{ t('presence.title') }}</h2>
    <p id="presence-description">{{ t('presence.description') }}</p>
    <p class="presence-timer" role="timer">{{ t('presence.time', { seconds: remaining }) }}</p>
    <p v-if="!round.participating || done" role="status">{{ t(done ? 'presence.confirmed' : 'presence.wait') }}</p>
    <p v-else-if="!ready" role="status">{{ t('loading') }}</p>
    <form v-else @submit.prevent="confirm">
      <label v-if="round.presence?.code" class="field">{{ t('presence.copy') }}<strong class="presence-code">{{ round.presence.code }}</strong><input ref="input" v-model="code" maxlength="2" autocomplete="off" autocapitalize="characters" :spellcheck="false" :disabled="sending" :aria-label="t('presence.copy')"></label>
      <p v-if="error" class="danger" role="alert">{{ t(error) }}</p>
      <button type="submit" class="primary full" :disabled="sending || store.disconnected || (!!round.presence?.code && code.trim().length !== 2)">{{ t(round.presence?.code ? 'presence.validate' : 'presence.here') }}</button>
      <p>{{ t('presence.warning') }}</p>
    </form>
  </dialog>
</template>

<style scoped>
.presence-modal h2{display:flex;align-items:center;gap:10px}.presence-timer{font-weight:700;color:var(--accent)}.presence-code{font-size:2rem;letter-spacing:8px;text-align:center;margin:10px 0}.presence-modal input{text-transform:uppercase;text-align:center;font-size:1.4rem;letter-spacing:6px}
</style>
