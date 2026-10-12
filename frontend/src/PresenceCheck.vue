<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGame, type Round } from './store'
const props = defineProps<{ round: Round; now: number }>()
const { t } = useI18n(), store = useGame()
const dialog = ref<HTMLDialogElement>(), confirmed = ref(false), busy = ref(false), failed = ref(false)
const remaining = computed(() => Math.max(0, Math.ceil(((props.round.presence?.ends_at || 0) - props.now) / 1000)))
const done = computed(() => confirmed.value || props.round.presence?.confirmed)
async function confirm() {
  if (busy.value || done.value || !remaining.value) return
  busy.value = true; failed.value = false
  try { await store.request(`/rounds/${props.round.id}/presence`, 'POST', {}, true); confirmed.value = true; await store.refresh() }
  catch { failed.value = true }
  finally { busy.value = false }
}
onMounted(() => dialog.value?.showModal())
</script>
<template>
  <dialog ref="dialog" class="modal" aria-labelledby="presence-title" aria-describedby="presence-description" @cancel.prevent>
    <h2 id="presence-title">{{ t('presence.title') }}</h2>
    <p id="presence-description">{{ t('presence.description') }}</p>
    <p role="timer">{{ t('presence.time', { seconds: remaining }) }}</p>
    <p v-if="done || !round.participating" role="status">{{ t(done ? 'presence.confirmed' : 'presence.wait') }}</p>
    <template v-else>
      <button type="button" class="primary full" :disabled="busy || store.disconnected" @click="confirm">{{ t('presence.here') }}</button>
      <p v-if="failed" role="alert">{{ t('presence.retry') }}</p>
      <p>{{ t('presence.warning') }}</p>
    </template>
  </dialog>
</template>
