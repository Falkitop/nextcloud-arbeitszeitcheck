import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { api, apiAllowFailure } from './helpers/api.js'

/**
 * ArbZG §3 calendar-day contract: status API must expose server-authoritative
 * fields on every response (including clocked out). When clock-in is allowed,
 * also validates live-session clipping fields.
 */
test('Clock status exposes calendar-day ArbZG fields', async ({ page }) => {
  await login(page, credsFromEnv('EMPLOYEE'))

  let statusRes = await api(page, 'GET', '/apps/arbeitszeitcheck/api/clock/status')
  expect(statusRes.success).toBe(true)
  assertCalendarDayStatusFields(statusRes.status)

  for (let attempt = 0; attempt < 3; attempt += 1) {
    if (statusRes.status?.status !== 'active' && statusRes.status?.status !== 'break') {
      break
    }
    await apiAllowFailure(page, 'POST', '/apps/arbeitszeitcheck/api/clock/out')
    statusRes = await api(page, 'GET', '/apps/arbeitszeitcheck/api/clock/status')
    expect(statusRes.success).toBe(true)
  }

  if (statusRes.status?.status === 'active' || statusRes.status?.status === 'break') {
    assertCalendarDayStatusFields(statusRes.status)
    return
  }

  const clockInRes = await apiAllowFailure(page, 'POST', '/apps/arbeitszeitcheck/api/clock/in')
  if (!clockInRes.ok || !clockInRes.json?.success) {
    const blocked = await api(page, 'GET', '/apps/arbeitszeitcheck/api/clock/status')
    expect(blocked.success).toBe(true)
    assertCalendarDayStatusFields(blocked.status, {
      expectAtDailyMaximum: blocked.status?.at_daily_maximum === true,
    })
    if (blocked.status?.status !== 'active' && blocked.status?.status !== 'break') {
      expect(blocked.status?.session_hours_on_calendar_today).toBe(0)
    }
    return
  }

  statusRes = await api(page, 'GET', '/apps/arbeitszeitcheck/api/clock/status')
  expect(statusRes.success).toBe(true)
  assertCalendarDayStatusFields(statusRes.status)

  await api(page, 'POST', '/apps/arbeitszeitcheck/api/clock/out')
})

function assertCalendarDayStatusFields(status, options = {}) {
  const { expectAtDailyMaximum = false } = options
  expect(status).toBeTruthy()
  expect(typeof status.server_now).toBe('string')
  expect(status.server_now.length).toBeGreaterThan(10)
  expect(typeof status.server_timezone).toBe('string')
  expect(status.server_timezone.length).toBeGreaterThan(2)
  expect(typeof status.working_today_hours).toBe('number')
  expect(typeof status.at_daily_maximum).toBe('boolean')
  expect(typeof status.session_hours_on_calendar_today).toBe('number')
  if (status.status === 'active' || status.status === 'break') {
    expect(status.session_hours_on_calendar_today).toBeLessThanOrEqual(status.working_today_hours + 0.01)
  } else {
    expect(status.session_hours_on_calendar_today).toBe(0)
  }
  if (expectAtDailyMaximum || status.at_daily_maximum === true) {
    expect(status.at_daily_maximum).toBe(true)
    expect(status.working_today_hours).toBeGreaterThanOrEqual(9.99)
  } else if (status.status === 'active' || status.status === 'break') {
    expect(status.working_today_hours).toBeLessThan(10.01)
  }
}
