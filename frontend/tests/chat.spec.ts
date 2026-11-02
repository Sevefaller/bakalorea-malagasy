import { test, expect } from '@playwright/test'

test('chat sends by button and Enter without crypto.randomUUID', async ({ page }) => {
  const comments: { id: number; player_id: number; client_id: string; body: string; created_at: string; has_image?: boolean }[] = []
  let posts = 0
  let playerCount = 2
  let roundId = 7
  await page.addInitScript(() => {
    localStorage.setItem('bakalorea.token', 'test-token')
    localStorage.setItem('bakalorea.code', 'TEST12')
    localStorage.setItem('bakalorea.updates.seen', '2026-11-02-random-referee')
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
      server_now: Date.now(), players: Array.from({ length: playerCount }, (_, index) => ({
        id: index + 1, nickname: index === 0 ? 'Lova' : index === 1 ? 'Narindra' : `Joueur ${index + 1}`,
        score: 0, online: true, left: false,
      })),
      history: [], round: { id: roundId, number: roundId === 7 ? 1 : 2, category: 'animal', letter: 'A', status: 'judging',
        started_at: Date.now() - 20000, answer_deadline: Date.now() - 5000, own_answer: '',
        own_revision: 0, participating: true, ready: false, answers: Array.from({ length: playerCount }, (_, index) => ({
          id: index + 1, player_id: index + 1, answer: index === 0 ? 'Antilope' : index === 1 ? 'Aigle' : `Animal ${index + 1}`,
          flagged: false, invalid_reason: null, verdict: null, points: 0, tie_decision: null, referee_id: null,
          votes: { valid: 0, invalid: 0, uncertain: 0, count: 0, tied: false }, my_vote: null,
        })), comments },
    } })
    if (path === '/api/rounds/7/comments') {
      posts++
      const body = route.request().postDataJSON() as { body: string; client_id: string }
      const comment = { id: Math.max(0, ...comments.map(item => item.id)) + 1, player_id: 1, client_id: body.client_id, body: body.body, created_at: new Date().toISOString() }
      comments.push(comment)
      return route.fulfill({ status: 201, json: { ok: true, id: comment.id } })
    }
    return route.fulfill({ status: 404, json: {} })
  })

  await page.goto('/')
  await expect(page.locator('#judging-comment')).toBeVisible()
  await expect(page.locator('.help-launcher')).toHaveCount(0)
  await expect(page.locator('.mobile-chat-launcher')).toBeHidden()
  const answers = await page.locator('.answer-cards').boundingBox()
  const ranking = await page.locator('.judging-room .scoreboard').boundingBox()
  const chat = await page.locator('.judging-chat').boundingBox()
  const regularCard = await page.locator('.judgment-card').nth(1).boundingBox()
  expect(answers && ranking && chat && answers.x + answers.width < ranking.x).toBeTruthy()
  expect(ranking && chat && ranking.y + ranking.height < chat.y).toBeTruthy()
  await page.locator('#judging-comment').fill('Salama')
  await page.locator('.chat-form button[type="submit"]').click()
  await expect.poll(() => posts).toBe(1)
  await expect(page.locator('.chat-messages').getByText('Salama')).toBeVisible()
  await page.locator('#judging-comment').fill('Manao ahoana')
  await page.locator('#judging-comment').press('Enter')
  await expect.poll(() => posts).toBe(2)
  await expect(page.locator('.chat-messages').getByText('Manao ahoana')).toBeVisible()
  await page.locator('.chat-form input[type="file"]').setInputFiles({
    name: 'photo.png', mimeType: 'image/png',
    buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', 'base64'),
  })
  await expect(page.locator('.chat-photo-preview')).toBeVisible()
  const sendButton = page.locator('.chat-form button[type="submit"]')
  const sendFitsInChat = async () => {
    const button = await sendButton.boundingBox()
    const chatBox = await page.locator('.judging-chat').boundingBox()
    return !!button && !!chatBox && button.y >= chatBox.y && button.y + button.height <= chatBox.y + chatBox.height
  }
  await expect.poll(sendFitsInChat).toBe(true)
  await page.setViewportSize({ width: 390, height: 844 })
  const launcher = page.locator('.mobile-chat-launcher')
  await expect(launcher).toBeVisible()
  await expect(launcher).toHaveAttribute('aria-expanded', 'false')
  await expect(page.locator('.judging-chat')).toBeHidden()
  comments.push({ id: 3, player_id: 2, client_id: 'incoming-3', body: 'Message pour le mobile', created_at: new Date().toISOString() })
  comments.push({ id: 4, player_id: 2, client_id: 'incoming-4', body: 'Un autre message', created_at: new Date().toISOString() })
  await expect(launcher).toHaveClass(/unread/)
  await expect(launcher.locator('.chat-unread-badge')).toHaveText('2')
  await expect(launcher).toBeInViewport()
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await expect(launcher).toHaveCSS('animation-name', 'none')
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.screenshot({ path: '../artifacts/chat-mobile-unread.png', fullPage: false })
  await launcher.click()
  await expect(page.locator('.judging-chat')).toBeVisible()
  await expect(launcher).toHaveAttribute('aria-expanded', 'true')
  await expect(launcher).not.toHaveClass(/unread/)
  await expect(page.locator('.chat-photo-preview')).toBeVisible()
  const panel = await page.locator('.judging-chat').boundingBox()
  expect(panel && panel.x >= 0 && panel.y >= 0 && panel.x + panel.width <= 390 && panel.y + panel.height <= 844).toBeTruthy()
  comments.push({ id: 5, player_id: 2, client_id: 'incoming-5', body: 'Chat déjà ouvert', created_at: new Date().toISOString() })
  await expect(page.locator('.chat-messages').getByText('Chat déjà ouvert')).toBeVisible()
  await expect(launcher).not.toHaveClass(/unread/)
  await expect.poll(sendFitsInChat).toBe(true)
  await page.screenshot({ path: '../artifacts/chat-mobile-open.png', fullPage: false })
  await page.locator('.chat-photo-preview button').click()
  await page.keyboard.press('Escape')
  await expect(page.locator('.judging-chat')).toBeHidden()
  await expect(launcher).toBeFocused()
  comments.push({ id: 6, player_id: 1, client_id: 'own-6', body: 'Mon propre message', created_at: new Date().toISOString() })
  await expect(page.locator('.chat-messages').getByText('Mon propre message')).toBeAttached()
  await expect(launcher).not.toHaveClass(/unread/)
  comments.push({ id: 7, player_id: 2, client_id: 'photo-7', body: '', has_image: true, created_at: new Date().toISOString() })
  await expect(launcher.locator('.chat-unread-badge')).toHaveText('1')
  await launcher.click()
  await page.locator('#judging-comment').fill('Envoyé depuis le mobile')
  await sendButton.click()
  await expect.poll(() => posts).toBe(3)
  await expect(page.locator('.chat-messages').getByText('Envoyé depuis le mobile')).toBeVisible()
  await expect(launcher).not.toHaveClass(/unread/)
  await page.locator('.mobile-chat-close').click()
  const mobileAnswers = await page.locator('.answer-cards').boundingBox()
  const mobileRanking = await page.locator('.judging-room .scoreboard').boundingBox()
  expect(mobileAnswers && mobileRanking && mobileAnswers.y + mobileAnswers.height < mobileRanking.y).toBeTruthy()

  playerCount = 4
  await page.setViewportSize({ width: 1280, height: 720 })
  await page.reload()
  await expect(page.locator('.judging-room.compact-judging .judgment-card')).toHaveCount(4)
  const compactCard = await page.locator('.judgment-card').nth(1).boundingBox()
  expect(compactCard!.height).toBeLessThan(regularCard!.height)
  const firstCard = await page.locator('.judgment-card').first().boundingBox()
  expect(compactCard!.y).toBe(firstCard!.y)
  expect(compactCard!.x).toBeGreaterThan(firstCard!.x)

  playerCount = 12
  await page.reload()
  await expect(page.locator('.judging-room.dense-judging .judgment-card')).toHaveCount(12)
  const denseCard = await page.locator('.judgment-card').nth(1).boundingBox()
  expect(denseCard!.height).toBeLessThan(compactCard!.height)
  const scrollArea = page.locator('.answer-cards')
  expect(await scrollArea.evaluate(element => element.scrollHeight > element.clientHeight)).toBeTruthy()
  await page.locator('.judgment-card').last().scrollIntoViewIfNeeded()
  await expect(page.locator('.judgment-card').last()).toBeInViewport()

  await page.setViewportSize({ width: 390, height: 844 })
  expect(await scrollArea.evaluate(element => getComputedStyle(element).overflowY)).toBe('visible')
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy()
  await launcher.click()
  await expect(launcher).toHaveAttribute('aria-expanded', 'true')
  roundId = 8
  comments.splice(0)
  await expect(launcher).toHaveAttribute('aria-expanded', 'false')
  await expect(launcher).not.toHaveClass(/unread/)
  await expect(page.locator('.judging-chat')).toBeHidden()
})
