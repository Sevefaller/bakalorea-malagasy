<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Sparkles, X } from 'lucide-vue-next'

const { t, locale } = useI18n()
const props = withDefaults(defineProps<{ autoOpen?: boolean }>(), { autoOpen: true })
// Add future updates first, with a unique ID, an ISO date and translated content.
const releases = [
  { id: '2026-10-12-categories', date: '2026-10-12', key: 'expansion', features: ['categories'] },
  { id: '2026-10-12-optional-presence', date: '2026-10-12', key: 'strict', features: ['flow'] },
  { id: '2026-10-11', date: '2026-10-11', key: 'launch', features: ['categories', 'reactions', 'chat', 'guide'] }
]
const storageKey = 'bakalorea.updates.seen'
const dialog = ref<HTMLDialogElement>()
const seen = ref('')
try { seen.value = localStorage.getItem(storageKey) || '' } catch { /* Reading updates still works without storage. */ }
const unread = computed(() => seen.value !== releases[0]?.id)
function open() {
  if (!dialog.value || dialog.value.open) return
  dialog.value.showModal()
  seen.value = releases[0]?.id || ''
  try { localStorage.setItem(storageKey, seen.value) } catch { /* Keep the read state for this visit. */ }
}
function openUnread() {
  if (props.autoOpen && unread.value) open()
}
onMounted(openUnread)
watch(() => props.autoOpen, openUnread)
function formatDate(date: string) {
  return new Intl.DateTimeFormat(locale.value, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`))
}
</script>

<template>
  <button type="button" class="text-button updates-button" aria-haspopup="dialog" :aria-label="t(unread ? 'updates.unreadLabel' : 'updates.title')" @click="open">
    <Sparkles :size="18"/><span class="updates-label">{{ t('updates.title') }}</span><span v-if="unread" class="updates-dot" aria-hidden="true"></span>
  </button>
  <dialog ref="dialog" class="modal updates-modal" aria-labelledby="updates-title">
    <div class="modal-heading"><h2 id="updates-title">{{ t('updates.title') }}</h2><button type="button" class="icon-button" :aria-label="t('close')" @click="dialog?.close()"><X :size="20"/></button></div>
    <p>{{ t('updates.intro') }}</p>
    <article v-for="release in releases" :key="release.id" class="update-release">
      <time :datetime="release.date">{{ formatDate(release.date) }}</time>
      <h3>{{ t(`updates.${release.key}.title`) }}</h3>
      <ul><li v-for="feature in release.features" :key="feature"><strong>{{ t(`updates.${release.key}.${feature}.title`) }}</strong><p>{{ t(`updates.${release.key}.${feature}.description`) }}</p></li></ul>
    </article>
    <button type="button" class="primary full" @click="dialog?.close()">{{ t('close') }}</button>
  </dialog>
</template>

<style scoped>
.updates-button{position:relative;gap:7px;white-space:nowrap}.updates-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);flex-shrink:0}.update-release{border-top:1px solid var(--border);padding:20px 0}.update-release time{font-size:.78rem;color:var(--muted)}.update-release h3{margin:8px 0 16px}.update-release ul{padding-left:20px;margin:0}.update-release li{padding-left:4px;margin-bottom:14px}.update-release li::marker{color:var(--accent)}.update-release li p{margin:5px 0;font-size:.85rem;line-height:1.6}
@media(max-width:800px){.updates-label{display:none}.updates-button{min-width:30px;min-height:34px}.updates-dot{position:absolute;top:2px;right:0}}
</style>
