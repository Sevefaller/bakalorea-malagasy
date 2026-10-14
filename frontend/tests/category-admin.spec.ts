import { test, expect } from '@playwright/test'

test('admin edits difficulty, creates categories and previews imports on desktop and mobile', async ({ page }) => {
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  const rows = [
    { id: 1, code: 'fruit', difficulty: 1, names: { mg: 'Voankazo', fr: 'Fruit', en: 'Fruit' } },
    { id: 2, code: 'spice', difficulty: 2, names: { mg: 'Zava-manitra', fr: 'Épice', en: 'Spice' } },
    { id: 3, code: 'malagasy_monarch', difficulty: 3, names: { mg: 'Mpanjaka malagasy', fr: 'Roi ou reine de Madagascar', en: 'Malagasy monarch' } },
  ]
  const imported = { code: 'scientist', difficulty: 3, names: { mg: 'Mpahay siansa', fr: 'Scientifique', en: 'Scientist' } }
  let writes = 0
  await page.route('**/api/admin/**', async route => {
    const request = route.request(), path = new URL(request.url()).pathname
    let result: object = {}
    if (path.endsWith('/status')) result = { username: 'Admin démo', csrf: 'test-csrf' }
    else if (path.endsWith('/dashboard')) result = { totals: { games: 0, active_games: 0, finished_games: 0, players: 0, online_players: 0 }, games: [], pagination: { page: 1, pages: 1, total: 0 }, finished_retention_days: 30 }
    else if (path.endsWith('/categories/import')) {
      expect(request.headers()['x-admin-csrf']).toBe('test-csrf')
      if (request.postData()?.includes('name="preview"\r\n\r\n1')) result = { categories: [imported] }
      else { writes++; rows.push({ ...imported, id: 5 }); result = { created: 1 } }
    } else if (path.endsWith('/categories') && request.method() === 'POST') {
      expect(request.headers()['x-admin-csrf']).toBe('test-csrf')
      rows.push({ ...request.postDataJSON(), id: 4 }); writes++; result = { created: 1 }
    } else if (/\/categories\/\d+$/.test(path)) {
      expect(request.headers()['x-admin-csrf']).toBe('test-csrf')
      Object.assign(rows.find(row => row.id === Number(path.split('/').pop()))!, request.postDataJSON()); writes++
      result = { ok: true }
    } else if (path.endsWith('/categories')) result = { categories: rows }
    await route.fulfill({ json: result })
  })
  await page.goto('/admin')
  await expect(page.getByRole('heading', { name: 'Catégories et difficulté' })).toBeVisible()
  await page.getByRole('button', { name: 'Modifier Épice', exact: true }).click()
  await page.getByLabel('Difficulté', { exact: true }).selectOption('3')
  await page.getByLabel('Nom en français').fill('Épices du monde')
  await page.getByRole('button', { name: 'Enregistrer la catégorie', exact: true }).click()
  await expect(page.getByText('Épices du monde', { exact: true })).toBeVisible()
  expect(rows[1].difficulty).toBe(3)
  await page.getByRole('button', { name: 'Ajouter une catégorie', exact: true }).click()
  await page.getByLabel('Code unique').fill('musical_instrument')
  await page.getByLabel('Nom en malgache').fill('Zavamaneno')
  await page.getByLabel('Nom en français').fill('Instrument de musique')
  await page.getByLabel('Nom en anglais').fill('Musical instrument')
  await page.getByRole('button', { name: 'Enregistrer la catégorie', exact: true }).click()
  await expect(page.getByText('Instrument de musique', { exact: true })).toBeVisible()
  await page.getByText('Importer des catégories', { exact: true }).click()
  await expect(page.getByRole('link', { name: 'Modèle CSV' })).toHaveAttribute('href', '/api/admin/categories/template?format=csv')
  await page.getByLabel('Fichier CSV ou JSON').setInputFiles({ name: 'categories.json', mimeType: 'application/json', buffer: Buffer.from(JSON.stringify([imported])) })
  await page.getByRole('button', { name: 'Vérifier et afficher l’aperçu' }).click()
  await expect(page.getByRole('heading', { name: 'Aperçu : 1 catégories à ajouter' })).toBeVisible()
  expect(writes).toBe(2)
  await page.screenshot({ path: '../artifacts/category-admin-desktop.png', fullPage: true })
  await page.getByRole('button', { name: 'Importer 1 catégories', exact: true }).click()
  await expect(page.getByText('Scientifique', { exact: true })).toBeVisible()
  expect(writes).toBe(3)
  await page.getByRole('combobox', { name: 'Filtrer par difficulté' }).selectOption('3')
  await page.getByRole('textbox', { name: 'Rechercher une catégorie' }).fill('scientist')
  await expect(page.getByRole('button', { name: 'Modifier Scientifique' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Modifier Fruit', exact: true })).toHaveCount(0)
  await page.getByRole('textbox', { name: 'Rechercher une catégorie' }).fill('')
  await page.getByRole('combobox', { name: 'Filtrer par difficulté' }).selectOption('')
  await page.setViewportSize({ width: 390, height: 844 })
  await page.screenshot({ path: '../artifacts/category-admin-mobile.png', fullPage: true })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy()
  expect(errors).toEqual([])
})

test('custom catalogue names appear in all three game languages', async ({ page }) => {
  await page.route('**/api/categories', route => route.fulfill({ json: { categories: [
    { id: 1, code: 'male_name', difficulty: 1, names: { mg: 'Anaran-dehilahy', fr: 'Prénom masculin', en: 'Male name' } },
    { id: 2, code: 'musical_instrument', difficulty: 2, names: { mg: 'Zavamaneno', fr: 'Instrument de musique', en: 'Musical instrument' } },
  ] } }))
  await page.goto('/')
  await page.locator('.all-categories-button').click()
  await expect(page.locator('.category-item').filter({ hasText: 'Instrument de musique' })).toBeVisible()
  await page.getByRole('combobox', { name: 'Langue', exact: true }).selectOption('mg')
  await expect(page.locator('.category-item').filter({ hasText: 'Zavamaneno' })).toBeVisible()
  await page.getByRole('combobox', { name: 'Fiteny', exact: true }).selectOption('en')
  await expect(page.locator('.category-item').filter({ hasText: 'Musical instrument' })).toBeVisible()
})
