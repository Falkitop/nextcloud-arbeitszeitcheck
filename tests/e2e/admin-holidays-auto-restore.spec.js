import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { api } from './helpers/api.js'

test.describe('Admin holidays auto-restore (API)', () => {
	test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS')

	test('deleted statutory stays removed when auto-restore is disabled', async ({ page }) => {
		await login(page, credsFromEnv('ADMIN'))

		const year = 2098
		const state = 'NW'

		await api(page, 'POST', '/apps/arbeitszeitcheck/api/admin/settings', {
			data: { statutoryAutoReseed: false },
		})

		const list1 = await api(page, 'GET', `/apps/arbeitszeitcheck/api/admin/state-holidays?state=${state}&year=${year}`)
		expect(list1.success).toBe(true)
		expect(list1.statutoryAutoReseed).toBe(false)

		const labour = (list1.holidays || []).find(
			(h) => h.scope === 'statutory' && h.date === `${year}-05-01` && h.id
		)
		test.skip(!labour, 'No seeded Labour Day row for test year')

		await api(page, 'DELETE', `/apps/arbeitszeitcheck/api/admin/state-holidays/${labour.id}`)

		const list2 = await api(page, 'GET', `/apps/arbeitszeitcheck/api/admin/state-holidays?state=${state}&year=${year}`)
		const dates = (list2.holidays || []).map((h) => h.date)
		expect(dates).not.toContain(`${year}-05-01`)

		const cal = await api(page, 'GET', `/apps/arbeitszeitcheck/api/holidays?start=${year}-05-01&end=${year}-05-07`)
		const calDates = (cal.holidays || []).map((h) => h.date)
		expect(calDates).not.toContain(`${year}-05-01`)

		await api(page, 'POST', '/apps/arbeitszeitcheck/api/admin/settings', {
			data: { statutoryAutoReseed: true },
		})
	})
})
