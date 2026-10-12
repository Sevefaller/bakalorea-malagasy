<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGame } from './store'
const props = defineProps<{ commentId: number }>()
const store = useGame(), { t } = useI18n()
const url = ref(''), failed = ref(false)
let version = 0
async function load() {
  const current = ++version
  failed.value = false
  try {
    const blob = await store.request(`/comments/${props.commentId}/image`, 'GET', undefined, true, true)
    if (current !== version) return
    if (url.value) URL.revokeObjectURL(url.value)
    url.value = URL.createObjectURL(blob)
  } catch { if (current === version) failed.value = true }
}
watch(() => props.commentId, load, { immediate: true })
onUnmounted(() => { version++; if (url.value) URL.revokeObjectURL(url.value) })
</script>
<template>
  <a v-if="url" :href="url" target="_blank" rel="noopener" :aria-label="t('chatPhoto.open')"><img :src="url" :alt="t('chatPhoto.photo')" loading="lazy"></a>
  <button v-else-if="failed" type="button" class="text-button" @click="load">{{ t('chatPhoto.retry') }}</button>
  <p v-else role="status">{{ t('loading') }}</p>
</template>
<style scoped>img{display:block;max-width:100%;max-height:260px;object-fit:contain;border-radius:10px;margin-top:8px}</style>
