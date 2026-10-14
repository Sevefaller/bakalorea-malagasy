<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Download, Plus, RefreshCw, Search, Upload, X } from 'lucide-vue-next'

interface Category { id?: number; code: string; difficulty: number; names: { mg: string; fr: string; en: string } }
const props = defineProps<{ csrf: string }>()
const emit = defineEmits<{ expired: [] }>()
const categories = ref<Category[]>([]), loading = ref(false), busy = ref(false)
const error = ref(''), notice = ref(''), search = ref(''), level = ref(''), page = ref(1)
const editor = ref<Category | null>(null), importFile = ref<File | null>(null), preview = ref<Category[]>([])
const fileInput = ref<HTMLInputElement | null>(null)
const levelLabels = ['Tous les niveaux', '1 · Accessible', '2 · Intermédiaire', '3 · Avancé / varié']
const counts = computed(() => [1, 2, 3].map(value => categories.value.filter(c => c.difficulty === value).length))
const filtered = computed(() => categories.value.filter(c => (!level.value || c.difficulty === Number(level.value)) &&
  `${c.code} ${c.names.fr} ${c.names.mg} ${c.names.en}`.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())))
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / 20)))
const visible = computed(() => filtered.value.slice((page.value - 1) * 20, page.value * 20))
watch([search, level], () => { page.value = 1 })

async function api(path = '', method = 'GET', body?: object | FormData) {
  const response = await fetch(`/api/admin/categories${path}`, {
    method, credentials: 'same-origin', cache: 'no-store',
    headers: { Accept: 'application/json', ...(body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...(method === 'GET' ? {} : { 'X-Admin-CSRF': props.csrf }) },
    ...(body ? { body: body instanceof FormData ? body : JSON.stringify(body) } : {}),
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) {
    if (response.status === 401) emit('expired')
    const messages = data.errors ? Object.values(data.errors).flat().join('\n') : ''
    throw new Error(messages || (response.status === 401 ? 'Session expirée. Reconnectez-vous.' : 'Opération impossible. Réessayez.'))
  }
  return data
}
async function refresh() {
  loading.value = true; error.value = ''
  try { categories.value = (await api()).categories; page.value = Math.min(page.value, pages.value) }
  catch (cause) { error.value = (cause as Error).message }
  finally { loading.value = false }
}
function edit(category?: Category) {
  editor.value = category ? { ...category, names: { ...category.names } } : { code: '', difficulty: 2, names: { mg: '', fr: '', en: '' } }
  error.value = ''; notice.value = ''
}
async function save() {
  if (!editor.value || busy.value) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const category = editor.value
    await api(category.id ? `/${category.id}` : '', category.id ? 'PUT' : 'POST', category)
    editor.value = null
    notice.value = category.id ? 'Catégorie enregistrée. Le niveau s’applique aux prochains tirages.' : 'Catégorie créée et ajoutée aux salles ouvertes.'
    await refresh()
  } catch (cause) { error.value = (cause as Error).message }
  finally { busy.value = false }
}
function selectFile(event: Event) {
  importFile.value = (event.target as HTMLInputElement).files?.[0] || null
  preview.value = []; error.value = ''; notice.value = ''
}
async function importCategories(check: boolean) {
  if (!importFile.value || busy.value) return
  busy.value = true; error.value = ''; notice.value = ''
  if (check) preview.value = []
  try {
    const body = new FormData()
    body.append('file', importFile.value); body.append('preview', check ? '1' : '0')
    const result = await api('/import', 'POST', body)
    if (check) preview.value = result.categories
    else {
      notice.value = `${result.created} catégories importées et ajoutées aux salles ouvertes.`
      preview.value = []; importFile.value = null
      if (fileInput.value) fileInput.value.value = ''
      await refresh()
    }
  } catch (cause) { error.value = (cause as Error).message; preview.value = [] }
  finally { busy.value = false }
}
onMounted(refresh)
</script>

<template>
  <section class="category-manager card" aria-labelledby="category-manager-title">
    <div class="section-heading"><div><h2 id="category-manager-title">Catégories et difficulté</h2><p>Avant 50 % du score cible : tous les niveaux. Dès 50 % : niveaux 2 et 3. Dès 80 % : niveau 3, puis le plus élevé restant.</p></div><button type="button" class="secondary" :disabled="loading || busy" @click="refresh"><RefreshCw :size="16"/> Actualiser</button></div>
    <div class="level-counts"><span v-for="(count, index) in counts" :key="index">Niveau {{ index + 1 }} <strong>{{ count }}</strong></span></div>
    <p v-if="error" class="feedback failure" role="alert">{{ error }}</p>
    <p v-if="notice" class="feedback success" role="status">{{ notice }}</p>
    <div class="catalog-tools"><label class="search"><Search :size="16"/><input v-model="search" aria-label="Rechercher une catégorie" placeholder="Rechercher un nom ou un code"></label><select v-model="level" aria-label="Filtrer par difficulté"><option value="">Tous les niveaux</option><option v-for="value in [1, 2, 3]" :key="value" :value="String(value)">{{ levelLabels[value] }}</option></select><button type="button" class="primary" :disabled="busy" @click="edit()"><Plus :size="16"/> Ajouter une catégorie</button></div>

    <form v-if="editor" class="category-editor" @submit.prevent="save">
      <div class="section-heading"><h3>{{ editor.id ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}</h3><button type="button" class="close-editor" aria-label="Fermer le formulaire" :disabled="busy" @click="editor = null"><X :size="19"/></button></div>
      <div class="form-grid"><label>Code unique<input v-model="editor.code" required maxlength="80" pattern="[a-z][a-z0-9_]*" :readonly="!!editor.id" :disabled="busy" placeholder="ex. musical_instrument"><small>Minuscules, chiffres et _. Le code reste fixe après création.</small></label><label>Difficulté<select v-model.number="editor.difficulty" :disabled="busy"><option v-for="value in [1, 2, 3]" :key="value" :value="value">{{ levelLabels[value] }}</option></select></label><label>Nom en malgache<input v-model="editor.names.mg" required maxlength="255" :disabled="busy"></label><label>Nom en français<input v-model="editor.names.fr" required maxlength="255" :disabled="busy"></label><label>Nom en anglais<input v-model="editor.names.en" required maxlength="255" :disabled="busy"></label></div>
      <button type="submit" class="primary" :disabled="busy">{{ busy ? 'Enregistrement…' : 'Enregistrer la catégorie' }}</button>
    </form>

    <p v-if="loading" class="muted" role="status">Chargement des catégories…</p>
    <div v-else class="table-wrap"><table><thead><tr><th>Catégorie</th><th>Code</th><th>Difficulté</th><th>Action</th></tr></thead><tbody><tr v-for="category in visible" :key="category.id"><td><strong>{{ category.names.fr }}</strong><small>MG : {{ category.names.mg }} · EN : {{ category.names.en }}</small></td><td><code>{{ category.code }}</code></td><td><span class="level-badge">{{ levelLabels[category.difficulty] }}</span></td><td><button type="button" class="secondary compact" :disabled="busy" :aria-label="`Modifier ${category.names.fr}`" @click="edit(category)">Modifier</button></td></tr><tr v-if="!visible.length"><td colspan="4" class="muted">Aucune catégorie trouvée.</td></tr></tbody></table></div>
    <nav v-if="pages > 1" class="pagination" aria-label="Pages des catégories"><button type="button" class="secondary compact" :disabled="page === 1" @click="page--">Précédente</button><span>{{ filtered.length }} catégories · Page {{ page }} / {{ pages }}</span><button type="button" class="secondary compact" :disabled="page === pages" @click="page++">Suivante</button></nav>

    <details class="import-panel"><summary><Upload :size="18"/> Importer des catégories</summary><p>Téléchargez un modèle intégré et adaptez ses exemples. Chaque catégorie nécessite un code unique, un niveau de 1 à 3 et les trois noms. L’import ajoute de nouvelles catégories ; pour changer une catégorie existante, utilisez « Modifier ».</p><div class="template-links"><a class="secondary compact" href="/api/admin/categories/template?format=csv" download><Download :size="16"/> Modèle CSV</a><a class="secondary compact" href="/api/admin/categories/template?format=json" download><Download :size="16"/> Modèle JSON</a></div><label class="import-label">Fichier CSV ou JSON<input ref="fileInput" type="file" accept=".csv,.json" :disabled="busy" @change="selectFile"></label><small class="muted">UTF-8 · CSV avec virgules ou points-virgules · maximum 200 catégories et 512 Ko. Aucun ajout si une ligne est invalide.</small><button type="button" class="secondary" :disabled="busy || !importFile" @click="importCategories(true)">{{ busy ? 'Traitement…' : 'Vérifier et afficher l’aperçu' }}</button>
      <div v-if="preview.length" class="import-preview"><h3>Aperçu : {{ preview.length }} catégories à ajouter</h3><div class="table-wrap"><table><thead><tr><th>Code</th><th>Niveau</th><th>MG</th><th>FR</th><th>EN</th></tr></thead><tbody><tr v-for="category in preview" :key="category.code"><td>{{ category.code }}</td><td>{{ category.difficulty }}</td><td>{{ category.names.mg }}</td><td>{{ category.names.fr }}</td><td>{{ category.names.en }}</td></tr></tbody></table></div><button type="button" class="primary" :disabled="busy" @click="importCategories(false)">Importer {{ preview.length }} catégories</button></div>
    </details>
  </section>
</template>

<style scoped>
.category-manager{padding:24px;margin:20px 0}.section-heading{display:flex;justify-content:space-between;align-items:start;gap:20px}.section-heading h2{font-size:1.05rem}.section-heading p,.import-panel p{font-size:.82rem;line-height:1.6;color:#68767b;margin-top:8px}.section-heading button{flex-shrink:0}.category-manager button,.template-links a{display:inline-flex;align-items:center;justify-content:center;gap:7px}.level-counts{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0}.level-counts span{padding:8px 13px;border-radius:8px;background:#edf6f1;font-size:.8rem;color:#267562}.level-counts strong{margin-left:9px}.catalog-tools{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:18px 0}.search{display:flex;align-items:center;gap:8px;flex:1;min-width:180px;border:1px solid #dce2e7;padding:0 10px;border-radius:8px}.search input{border:0;min-width:0;width:100%;padding:11px 0}.category-manager input,.category-manager select{font:inherit;font-size:.82rem;color:#223c37}.category-manager select,.form-grid input{border:1px solid #dce2e7;border-radius:8px;padding:10px;background:#fff}.category-manager input:focus-visible,.category-manager select:focus-visible{outline:2px solid #2e8a7d;outline-offset:2px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;text-align:left;font-size:.8rem}th,td{padding:12px 9px;border-bottom:1px solid #edf0f1}th{font-size:.73rem;color:#68767b}td:first-child{min-width:200px}td small{display:block;color:#68767b;margin-top:5px;line-height:1.5}code{font-size:.72rem;overflow-wrap:anywhere}.level-badge{display:inline-block;padding:5px 8px;background:#edf6f1;color:#267562;border-radius:6px;white-space:nowrap}.compact{min-height:34px;padding:7px 11px;font-size:.78rem}.pagination{display:flex;justify-content:center;align-items:center;gap:13px;margin-top:17px;font-size:.78rem;color:#68767b}.category-editor{padding:20px;background:#f6faf8;border:1px solid #cfe5da;border-radius:12px;margin-bottom:18px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin:18px 0}.form-grid label{display:grid;align-content:start;gap:8px;font-size:.8rem;font-weight:700}.form-grid input{width:100%;min-width:0}.form-grid small{font-weight:400;color:#68767b;font-size:.72rem}.form-grid input[readonly]{background:#edf0ee}.close-editor{background:transparent;border:0;color:#68767b;padding:5px;cursor:pointer}.feedback{padding:12px;border-radius:8px;font-size:.82rem;white-space:pre-line;margin:12px 0}.failure{background:#fff0ee;color:#b54848}.success{background:#e8f4ee;color:#24674e}.muted{font-size:.78rem;color:#68767b}.import-panel{border-top:1px solid #e1e5e9;margin-top:24px;padding-top:20px}.import-panel summary{display:flex;align-items:center;gap:9px;cursor:pointer;font-size:.9rem;font-weight:700;color:#267562}.template-links{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}.import-label{display:grid;gap:10px;font-size:.82rem;font-weight:700;margin:16px 0}.import-panel>small{display:block;margin-bottom:15px}.import-preview{margin-top:20px}.import-preview h3{font-size:.9rem;margin-bottom:12px}.import-preview .table-wrap{max-height:340px;margin-bottom:16px}
@media(max-width:700px){.category-manager{padding:18px}.section-heading{flex-wrap:wrap}.form-grid{grid-template-columns:1fr}.catalog-tools>select,.catalog-tools>button{flex:1}.pagination{flex-wrap:wrap}.search{flex-basis:100%}}
</style>
