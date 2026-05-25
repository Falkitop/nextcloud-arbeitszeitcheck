import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { assertArbeitszeitcheckLoaded } from './helpers/app-config.js'

test('Employee can open dashboard and see clock widget', async ({ page }) => {
  await login(page, credsFromEnv('EMPLOYEE'))
  await page.goto('/apps/arbeitszeitcheck/dashboard')
  await assertArbeitszeitcheckLoaded(page)

  // Dashboard should have a page title/heading area and clock actions somewhere.
  await expect(page.locator('#app-content')).toBeVisible()
  await expect(page.locator('text=/Clock in|Clock out|Start break|End break/i').first()).toBeVisible()
})

test('Health endpoint returns JSON', async ({ request }) => {
  const res = await request.get('/apps/arbeitszeitcheck/health')
  expect(res.ok()).toBeTruthy()
  const json = await res.json()
  expect(json).toHaveProperty('status')
  expect(json).toHaveProperty('timestamp')
})

test('Dashboard exposes timezone bootstrap for client timer', async ({ page }) => {
  await login(page, credsFromEnv('EMPLOYEE'))
  await page.goto('/apps/arbeitszeitcheck/dashboard')
  await assertArbeitszeitcheckLoaded(page)

  const bootstrap = await page.evaluate(() => ({
    storageTz: window.ArbeitszeitCheck?.tz?.storage || '',
    displayTz: window.ArbeitszeitCheck?.tz?.display || '',
    serverNow: window.ArbeitszeitCheck?.serverNow || '',
    hasTimeApi: Boolean(window.ArbeitszeitCheckTime?.parseInstant),
  }))

  expect(bootstrap.storageTz).not.toBe('')
  expect(bootstrap.displayTz).not.toBe('')
  expect(bootstrap.serverNow.length).toBeGreaterThan(10)
  expect(bootstrap.hasTimeApi).toBe(true)
})

test('GDPR export returns downloadable response when logged in', async ({ page }) => {
  await login(page, credsFromEnv('EMPLOYEE'))
  const res = await page.request.get('/apps/arbeitszeitcheck/gdpr/export')
  expect(res.ok()).toBeTruthy()
  const disposition = res.headers()['content-disposition'] || ''
  expect(disposition).toMatch(/attachment/i)
})

test('Month closure feature endpoint returns JSON when logged in', async ({ page }) => {
  await login(page, credsFromEnv('EMPLOYEE'))
  const res = await page.request.get('/apps/arbeitszeitcheck/api/month-closure/feature')
  expect(res.ok()).toBeTruthy()
  const json = await res.json()
  expect(json).toHaveProperty('enabled')
  expect(typeof json.enabled).toBe('boolean')
})

