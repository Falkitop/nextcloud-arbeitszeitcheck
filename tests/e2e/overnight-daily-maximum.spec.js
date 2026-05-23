import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { api } from './helpers/api.js'

/**
 * ArbZG §3 calendar-day contract: status API must expose server-authoritative
 * fields so the frontend never auto-clocks-out on full overnight session length.
 */
test('Clock status exposes calendar-day ArbZG fields when clocked in', async ({ page }) => {
  await login(page, credsFromEnv('EMPLOYEE'))

  const pre = await api(page, 'GET', '/apps/arbeitszeitcheck/api/clock/status')
  expect(pre.success).toBe(true)

  if (pre.status?.status === 'active' || pre.status?.status === 'break') {
    assertCalendarDayStatusFields(pre.status)
    return
  }

  const clockIn = await api(page, 'POST', '/apps/arbeitszeitcheck/api/clock/in')
  if (!clockIn.success) {
    test.skip(true, clockIn.error || 'Clock-in blocked (rest period / daily max)')
    return
  }

  const status = await api(page, 'GET', '/apps/arbeitszeitcheck/api/clock/status')
  expect(status.success).toBe(true)
  assertCalendarDayStatusFields(status.status)

  await api(page, 'POST', '/apps/arbeitszeitcheck/api/clock/out')
})

function assertCalendarDayStatusFields(status) {
  expect(status).toBeTruthy()
  expect(typeof status.server_now).toBe('string')
  expect(status.server_now.length).toBeGreaterThan(10)
  expect(typeof status.server_timezone).toBe('string')
  expect(status.server_timezone.length).toBeGreaterThan(2)
  expect(typeof status.working_today_hours).toBe('number')
  expect(typeof status.at_daily_maximum).toBe('boolean')
  expect(typeof status.session_hours_on_calendar_today).toBe('number')
  expect(status.session_hours_on_calendar_today).toBeLessThanOrEqual(status.working_today_hours + 0.01)
  if (status.at_daily_maximum === false) {
    expect(status.working_today_hours).toBeLessThan(10.01)
  }
}
