import { test, expect } from '@playwright/test'
import fs from 'node:fs'

const examples = JSON.parse(fs.readFileSync('../backend/resources/data/category_examples.json', 'utf8'))
const definitions = JSON.parse(fs.readFileSync('../backend/resources/data/everyday_categories_2026_11_24.json', 'utf8')) as [string, number, string, string, string][]
const knowledge = JSON.parse(fs.readFileSync('../backend/resources/data/knowledge_categories_2026_11_24.json', 'utf8')) as [string, number, string, string, string, string, string, string][]
const extra = JSON.parse(fs.readFileSync('../backend/resources/data/knowledge_categories_extra_2026_11_24.json', 'utf8')) as [string, number, string, string, string, string, string, string][]

for (const mobile of [false, true]) {
  test(`catalogue shows examples in three languages ${mobile ? 'on mobile' : 'on desktop'}`, async ({ page }) => {
    if (mobile) await page.setViewportSize({ width: 390, height: 844 })
    await page.addInitScript(() => {
      localStorage.setItem('bakalorea.locale', 'fr')
      localStorage.setItem('bakalorea.updates.seen', '2026-11-24-more-knowledge')
    })
    await page.route('**/api/categories', route => route.fulfill({ json: { categories: [
      { id: 1, code: 'fruit', difficulty: 1, names: { fr: 'Fruit', mg: 'Voankazo', en: 'Fruit' }, examples: examples.fruit },
      ...[...definitions, ...knowledge, ...extra].map(([code, difficulty, fr, mg, en], index) => ({ id: index + 2, code, difficulty, names: { fr, mg, en }, examples: examples[code] })),
    ] } }))
    await page.goto('/')
    await page.locator('.all-categories-button').click()
    await expect(page.locator('.category-item')).toHaveCount(57)
    await expect(page.locator('.category-example')).toHaveCount(57)
    await expect(page.locator('.category-example').getByText('Exemple : Banane', { exact: true })).toBeVisible()
    await expect(page.locator('.category-item').filter({ hasText: 'Objet avec une poignée' })).toContainText('Exemple : Valise')
    await page.getByRole('button', { name: 'MG', exact: true }).click()
    await expect(page.getByText('Ohatra: Akondro', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'EN', exact: true }).click()
    await expect(page.getByText('Example: Banana', { exact: true })).toBeVisible()
    await expect(page.locator('.category-item').filter({ hasText: 'Object with a handle' })).toContainText('Example: Suitcase')
    await page.screenshot({ path: `../artifacts/category-examples-${mobile ? 'mobile' : 'desktop'}.png`, fullPage: true })
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy()
    await page.getByRole('button', { name: 'FR', exact: true }).click()
    await page.getByRole('button', { name: 'Lieux', exact: true }).click()
    await expect(page.locator('.category-item').filter({ hasText: 'Pays d’Afrique' })).toContainText('Exemple : Sénégal')
    await expect(page.locator('.category-item').filter({ hasText: 'Ville de Madagascar' })).toContainText('Exemple : Mahajanga')
    await expect(page.locator('.category-item').filter({ hasText: 'Pays d’Europe' })).toContainText('Exemple : Portugal')
    await page.getByRole('button', { name: 'Nature', exact: true }).click()
    await expect(page.locator('.category-item').filter({ hasText: 'Animal qui rampe' })).toContainText('Exemple : Serpent')
    await expect(page.locator('.category-item').filter({ hasText: 'Animal carnivore' })).toContainText('Exemple : Lion')
    await expect(page.locator('.category-item').filter({ hasText: 'Animal migrateur' })).toContainText('Exemple : Hirondelle')
    await page.getByRole('button', { name: 'Cuisine', exact: true }).click()
    await expect(page.locator('.category-item').filter({ hasText: 'Légumineuse' })).toContainText('Exemple : Haricot')
    await page.getByRole('button', { name: 'Culture', exact: true }).click()
    await expect(page.locator('.category-item').filter({ hasText: 'Organisation internationale' })).toContainText('Exemple : UNESCO')
  })
}
