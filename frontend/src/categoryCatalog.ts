import { i18n } from './i18n'
import { ref } from 'vue'

export const categoryExamples = ref<Record<string, Record<string, string | null>>>({})

export function applyCategoryNames(names: Record<string, Record<string, string>>) {
  for (const locale of ['mg', 'fr', 'en'] as const) {
    const category: Record<string, string> = {}
    for (const [code, translations] of Object.entries(names)) {
      if (translations[locale]) category[code] = translations[locale]
    }
    i18n.global.mergeLocaleMessage(locale, { category })
  }
}

export async function loadCategoryCatalog(): Promise<string[]> {
  const response = await fetch('/api/categories', { cache: 'no-store', headers: { Accept: 'application/json' } })
  if (!response.ok) throw new Error('catalog_unavailable')
  const data: { categories: { code: string; names: Record<string, string>; examples?: Record<string, string | null> }[] } = await response.json()
  applyCategoryNames(Object.fromEntries(data.categories.map(category => [category.code, category.names])))
  categoryExamples.value = Object.fromEntries(data.categories.map(category => [category.code, category.examples || {}]))
  return data.categories.map(category => category.code)
}
