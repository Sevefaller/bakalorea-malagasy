import { test, expect } from '@playwright/test'

for (const mobile of [false, true]) {
  test(`answer contest is shared and acknowledged ${mobile ? 'on mobile' : 'on desktop'}`, async ({ browser }) => {
    const contexts = await Promise.all([browser.newContext({ viewport: mobile ? { width: 390, height: 844 } : { width: 1280, height: 800 } }), browser.newContext()])
    const comments: Record<string, unknown>[] = [], contests: Record<string, unknown>[] = []
    let posts = 0, failOnce = true, guestRemaining = 5, roundId = 7
    let phase = 'judging'
    const pages = await Promise.all(contexts.map(async (context, index) => {
      const me = index === 0 ? 2 : 1
      await context.addInitScript(() => {
        localStorage.setItem('bakalorea.token', 'test-token')
        localStorage.setItem('bakalorea.code', 'TEST12')
        localStorage.setItem('bakalorea.locale', 'fr')
        localStorage.setItem('bakalorea.updates.seen', '2026-11-24-category-examples')
      })
      await context.route('**/api/**', async route => {
        const path = new URL(route.request().url()).pathname
        if (path === '/api/session/claim') return route.fulfill({ json: { ok: true } })
        if (path === '/api/config') return route.fulfill({ json: { realtime: false } })
        if (path === '/api/games/TEST12') return route.fulfill({ json: {
          id: 1, code: 'TEST12', name: 'Test', status: 'playing', host_id: 1, me_id: me,
          contest_limit: 5, contest_remaining: me === 2 ? guestRemaining : 5,
          target_score: 200, answer_duration: 15, anti_cheat_mode: 'normal', unique_points: 10, duplicate_points: 5,
          letters: 'AB', no_repeat: true, server_now: Date.now(), history: [],
          players: [{ id: 1, nickname: 'Lova', score: 10, online: true }, { id: 2, nickname: 'Mialy', score: 0, online: true }],
          round: { id: roundId, number: roundId - 6, category: 'animal', letter: 'A', status: phase, started_at: Date.now() - 25000,
            answer_deadline: Date.now() - 10000, own_answer: 'Antilope', own_revision: 1, participating: true, ready: true,
            comments, contests, answers: [1, 2].map(id => ({ id, player_id: id, answer: id === 1 ? 'Aigle' : 'Antilope',
              flagged: false, invalid_reason: null, verdict: id === 1, points: id === 1 ? 10 : 0, tie_decision: null,
              referee_id: null, reactions: {}, votes: { valid: id === 1 ? 1 : 0, invalid: id === 2 ? 1 : 0, count: 1, tied: false }, my_vote: null })) },
        } })
        if (path === '/api/rounds/7/contest') {
          posts++
          if (failOnce) { failOnce = false; return route.fulfill({ status: 500, json: {} }) }
          guestRemaining--
          comments.push({ id: 1, player_id: 2, body: '', created_at: new Date().toISOString() })
          contests.push({ id: 1, player_id: 2, comment_id: 1, acknowledged_at: null, acknowledged_by: null })
          return route.fulfill({ json: { ok: true } })
        }
        if (path === '/api/rounds/7/contests/1/acknowledge') {
          contests[0].acknowledged_at = new Date().toISOString(); contests[0].acknowledged_by = 1
          return route.fulfill({ json: { ok: true } })
        }
        if (path === '/api/rounds/7/finish-judging') {
          phase = 'finished'
          return route.fulfill({ json: { ok: true } })
        }
        return route.fulfill({ status: 404, json: {} })
      })
      const page = await context.newPage()
      await page.goto('/')
      return page
    }))
    const [guest, host] = pages
    const ownCard = guest.locator('.judgment-card').filter({ has: guest.getByRole('heading', { name: 'Antilope', exact: true }) })
    await expect(ownCard.getByRole('button', { name: 'Contester ma réponse' })).toBeVisible()
    await expect(guest.locator('.judgment-card').filter({ has: guest.getByRole('heading', { name: 'Aigle', exact: true }) }).getByRole('button', { name: 'Contester ma réponse' })).toHaveCount(0)
    await expect(guest.getByText('Contestations restantes dans cette partie : 5/5.')).toBeVisible()
    await guest.getByRole('button', { name: 'Contester ma réponse' }).click()
    await expect.poll(() => posts).toBe(1)
    await expect(guest.locator('textarea')).toHaveCount(0)
    await expect(guest.getByRole('button', { name: 'Contester ma réponse' })).toBeEnabled()
    await expect(guest.getByText('Contestations restantes dans cette partie : 5/5.')).toBeVisible()
    await guest.getByRole('button', { name: 'Contester ma réponse' }).click()
    await expect(guest.getByText('Contestation envoyée au maître du jeu.', { exact: true })).toBeVisible()
    await expect(guest.getByText('Contestations restantes dans cette partie : 4/5.')).toBeVisible()
    await expect(guest.getByRole('button', { name: 'Contester ma réponse' })).toHaveCount(0)
    if (mobile) await guest.locator('.mobile-chat-launcher').click()
    await expect(guest.locator('.chat-messages').getByText('Mialy a contesté sa réponse et demande une vérification. · Manche 1')).toBeVisible()
    await expect(host.locator('.contest-notice').getByText('Mialy a contesté sa réponse et demande une vérification.', { exact: true })).toBeVisible()
    await expect(host.getByRole('button', { name: 'Afficher les résultats', exact: true })).toBeDisabled()
    await guest.reload()
    await expect(guest.getByRole('button', { name: 'Contester ma réponse' })).toHaveCount(0)
    if (mobile) await guest.locator('.mobile-chat-launcher').click()
    await expect(guest.locator('.chat-messages').getByText('Mialy a contesté sa réponse et demande une vérification. · Manche 1')).toBeVisible()
    await expect(guest.getByText('Contestations restantes dans cette partie : 4/5.')).toBeVisible()
    await guest.screenshot({ path: `../artifacts/contest-${mobile ? 'mobile' : 'desktop'}.png`, fullPage: true })
    await host.getByRole('button', { name: 'J’ai pris connaissance' }).click()
    await expect(host.getByRole('button', { name: 'Afficher les résultats', exact: true })).toBeEnabled()
    await expect(guest.getByText('Le maître du jeu a pris connaissance de ta contestation.')).toBeVisible()
    await expect(guest.locator('.chat-messages').getByText('Mialy a contesté sa réponse et demande une vérification. · Manche 1')).toBeVisible()
    await expect.poll(() => posts).toBe(2)
    await host.getByRole('button', { name: 'Afficher les résultats', exact: true }).click()
    await expect(guest.locator('.result-card')).toBeVisible()
    await expect(guest.getByRole('button', { name: 'Contester ma réponse' })).toHaveCount(0)
    await expect(guest.locator('#contest-quota')).toHaveCount(0)
    await expect(host.getByRole('button', { name: 'Manche suivante' })).toBeEnabled()
    // On a later round, the exhausted quota survives a reload and prevents another appeal.
    guestRemaining = 0; roundId = 8; phase = 'judging'; contests.splice(0); comments.splice(0)
    await guest.reload()
    await expect(guest.getByRole('button', { name: 'Contester ma réponse' })).toBeDisabled()
    await expect(guest.getByText('Tu as utilisé tes 5 contestations pour cette partie.')).toBeVisible()
    await expect(guest.locator('#contest-form')).toHaveCount(0)
    await expect(host.getByText('Contestations restantes dans cette partie : 5/5.')).toBeVisible()
    await Promise.all(contexts.map(context => context.close()))
  })
}
