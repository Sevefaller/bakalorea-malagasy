import { test, expect } from '@playwright/test'

test('chat sends by button and Enter without crypto.randomUUID', async ({ page }) => {
  const comments: { id: number; player_id: number; client_id: string; body: string; created_at: string }[] = []
  let posts = 0
  await page.addInitScript(() => {
    localStorage.setItem('bakalorea.token', 'test-token')
    localStorage.setItem('bakalorea.code', 'TEST12')
    Object.defineProperty(window.crypto, 'randomUUID', { value: undefined })
  })
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (path === '/api/session/claim') return route.fulfill({ json: { ok: true } })
    if (path === '/api/config') return route.fulfill({ json: { realtime: false } })
    if (path === '/api/games/TEST12') return route.fulfill({ json: {
      id: 1, code: 'TEST12', name: 'Test', status: 'playing', host_id: 1, winner_id: null,
      me_id: 1, target_score: 200, answer_duration: 15, anti_cheat_mode: 'normal',
      unique_points: 10, duplicate_points: 5, letters: 'AB', no_repeat: true,
      server_now: Date.now(), players: [{ id: 1, nickname: 'Lova', score: 0, online: true, left: false }],
      history: [], round: { id: 7, number: 1, category: 'animal', letter: 'A', status: 'judging',
        started_at: Date.now() - 20000, answer_deadline: Date.now() - 5000, own_answer: '',
        own_revision: 0, participating: true, ready: false, answers: [], comments },
    } })
    if (path === '/api/rounds/7/comments') {
      posts++
      const body = route.request().postDataJSON() as { body: string; client_id: string }
      const comment = { id: posts, player_id: 1, client_id: body.client_id, body: body.body, created_at: new Date().toISOString() }
      comments.push(comment)
      return route.fulfill({ status: 201, json: { ok: true, id: comment.id } })
    }
    return route.fulfill({ status: 404, json: {} })
  })

  await page.goto('/')
  await expect(page.locator('#judging-comment')).toBeVisible()
  await page.locator('#judging-comment').fill('Salama')
  await page.locator('.chat-form button').click()
  await expect.poll(() => posts).toBe(1)
  await expect(page.locator('.chat-messages').getByText('Salama')).toBeVisible()
  await page.locator('#judging-comment').fill('Manao ahoana')
  await page.locator('#judging-comment').press('Enter')
  await expect.poll(() => posts).toBe(2)
  await expect(page.locator('.chat-messages').getByText('Manao ahoana')).toBeVisible()
})
