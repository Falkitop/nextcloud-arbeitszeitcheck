import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { api, apiAllowFailure } from './helpers/api.js'

/**
 * Next Saturday (local date Y-m-d) at least `minDaysAhead` calendar days from today.
 */
function nextSaturdayIso(minDaysAhead = 120) {
	const base = new Date()
	base.setHours(0, 0, 0, 0)
	base.setDate(base.getDate() + minDaysAhead)
	while (base.getDay() !== 6) {
		base.setDate(base.getDate() + 1)
	}
	const y = base.getFullYear()
	const m = String(base.getMonth() + 1).padStart(2, '0')
	const d = String(base.getDate()).padStart(2, '0')
	return `${y}-${m}-${d}`
}

test.describe('Absence planning vs working days / holidays (API)', () => {
	test('vacation spanning only Sat–Sun is rejected (zero working days)', async ({ page }) => {
		await login(page, credsFromEnv('EMPLOYEE'))
		await page.goto('/apps/arbeitszeitcheck/absences')

		const sat = nextSaturdayIso(120)
		const d = new Date(`${sat}T12:00:00`)
		d.setDate(d.getDate() + 1)
		const sun = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

		const res = await apiAllowFailure(page, 'POST', '/apps/arbeitszeitcheck/api/absences', {
			data: {
				type: 'vacation',
				start_date: sat,
				end_date: sun,
				reason: 'E2E weekend-only vacation (must fail)',
			},
		})

		expect(res.ok).toBe(false)
		expect(res.json?.success).toBe(false)
		const err = String(res.json?.error || '').toLowerCase()
		expect(err).toMatch(/working day|weekend|public holiday|feiertag|urlaub/i)
	})

	test('vacation stats API returns a coherent shape for the current year', async ({ page }) => {
		await login(page, credsFromEnv('EMPLOYEE'))
		await page.goto('/apps/arbeitszeitcheck/absences')

		const year = new Date().getFullYear()
		const body = await api(page, 'GET', `/apps/arbeitszeitcheck/api/absences/stats?year=${year}`)

		expect(body.success).toBe(true)
		expect(body.vacationStats).toBeTruthy()
		expect(typeof body.vacationStats.used === 'number' || typeof body.vacationStats.used === 'string').toBe(true)
		expect(typeof body.vacationStats.entitlement === 'number' || typeof body.vacationStats.entitlement === 'string').toBe(true)
		expect(
			typeof body.vacationStats.remaining === 'number' || typeof body.vacationStats.remaining === 'string'
		).toBe(true)
		expect(body.sickLeaveStats).toBeTruthy()
		expect(typeof body.sickLeaveStats.days === 'number' || typeof body.sickLeaveStats.days === 'string').toBe(true)
	})
})
