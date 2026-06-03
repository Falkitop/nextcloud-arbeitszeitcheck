import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'

test.describe('Admin pages load datepicker script', () => {
	test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS')

	const pages = [
		{ name: 'employees', path: '/index.php/apps/arbeitszeitcheck/admin/users' },
		{ name: 'holidays', path: '/index.php/apps/arbeitszeitcheck/admin/holidays' },
		{ name: 'tariff-rules', path: '/index.php/apps/arbeitszeitcheck/admin/tariff-rules' },
		{ name: 'audit-log', path: '/index.php/apps/arbeitszeitcheck/admin/audit-log' },
	]

	for (const { name, path } of pages) {
		test(`${name} exposes ArbeitszeitCheckDatepicker`, async ({ page }) => {
			await login(page, credsFromEnv('ADMIN'))
			const scriptLoaded = page.waitForResponse(
				(res) => res.url().includes('/js/common/datepicker.js') && res.status() === 200,
				{ timeout: 15000 }
			)
			await page.goto(path)
			await scriptLoaded
			const hasModule = await page.evaluate(() => {
				return !!(window.ArbeitszeitCheckDatepicker && typeof window.ArbeitszeitCheckDatepicker.convertEuropeanToISO === 'function')
			})
			expect(hasModule).toBe(true)
		})
	}
})
