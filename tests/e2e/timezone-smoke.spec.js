import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { assertArbeitszeitcheckLoaded } from './helpers/app-config.js'
import { api, apiAllowFailure } from './helpers/api.js'
import { isIsoInstantWithOffset, parseDurationHms } from './helpers/time.js'

/**
 * Guards the original production bug: after clock-in the session timer jumped
 * to ~02:00:00 because the client compared UTC `Date.now()` against a shifted
 * interpretation of the stored start time.
 */
test('Timezone: bootstrap, API instants, and live timer stay aligned after clock-in', async ({ page }) => {
  await login(page, credsFromEnv('EMPLOYEE'))
  await page.goto('/apps/arbeitszeitcheck/dashboard')
  await assertArbeitszeitcheckLoaded(page)

  const bootstrap = await page.evaluate(() => ({
    storageTz: window.ArbeitszeitCheck?.tz?.storage || '',
    displayTz: window.ArbeitszeitCheck?.tz?.display || '',
    serverNow: window.ArbeitszeitCheck?.serverNow || '',
    hasTimeApi: Boolean(window.ArbeitszeitCheckTime?.parseInstant && window.ArbeitszeitCheckTime?.syncFromServer),
  }))

  expect(bootstrap.storageTz).not.toBe('')
  expect(bootstrap.displayTz).not.toBe('')
  expect(isIsoInstantWithOffset(bootstrap.serverNow)).toBe(true)
  expect(bootstrap.hasTimeApi).toBe(true)

  const preStatus = await api(page, 'GET', '/apps/arbeitszeitcheck/api/clock/status')
  if (preStatus?.status?.status === 'active' || preStatus?.status?.status === 'break') {
    await api(page, 'POST', '/apps/arbeitszeitcheck/api/clock/out')
  }

  const clockIn = await apiAllowFailure(page, 'POST', '/apps/arbeitszeitcheck/api/clock/in')
  if (!clockIn.ok) {
    expect(clockIn.status).toBeGreaterThanOrEqual(400)
    expect(clockIn.json?.success).toBe(false)
    expect(clockIn.json?.error || '').toMatch(/rest period|Maximum daily working hours|not authenticated/i)
    return
  }
  expect(clockIn.json?.success).toBe(true)

  const status = await api(page, 'GET', '/apps/arbeitszeitcheck/api/clock/status')
  expect(status.success).toBe(true)
  expect(status.status?.status).toMatch(/active|break/)
  expect(isIsoInstantWithOffset(status.status?.server_now || '')).toBe(true)
  expect(isIsoInstantWithOffset(status.status?.current_entry?.startTime || '')).toBe(true)

  const apiDuration = Number(status.status?.current_session_duration ?? 0)
  expect(apiDuration).toBeGreaterThanOrEqual(0)
  expect(apiDuration).toBeLessThan(120)

  await page.reload()
  await assertArbeitszeitcheckLoaded(page)
  await page.waitForSelector('#session-timer-value', { timeout: 15_000 })

  // Let initTimer + first tick run.
  await page.waitForTimeout(1500)

  const timerText = await page.locator('#session-timer-value').first().textContent()
  const timerSecs = parseDurationHms(timerText)
  expect(timerSecs).not.toBeNull()

  // Must not show the classic UTC-offset jump (~7200 s) right after clock-in.
  expect(timerSecs).toBeLessThan(300)
  expect(Math.abs(timerSecs - apiDuration)).toBeLessThan(25)

  await api(page, 'POST', '/apps/arbeitszeitcheck/api/clock/out')
})
