import { defineConfig } from '@playwright/test'
export default defineConfig({ testDir: './tests', timeout: 60000, expect: { timeout: 12000 }, workers: 1, use: { baseURL: process.env.GAME_URL || 'http://localhost:5173', headless: true, channel: 'msedge', screenshot: 'only-on-failure', trace: 'retain-on-failure' }, reporter: [['list']], outputDir: '../artifacts/browser-tests' })
