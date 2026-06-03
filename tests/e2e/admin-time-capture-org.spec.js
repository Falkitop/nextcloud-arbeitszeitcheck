import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { api, apiAllowFailure } from './helpers/api.js'
import { assertArbeitszeitcheckLoaded } from './helpers/app-config.js'

test.describe('Organisation time recording methods', () => {
	test.skip(!process.env.NC_ADMIN_USER || !process.env.NC_EMPLOYEE_USER, 'Requires NC_ADMIN_USER and NC_EMPLOYEE_USER')

	test('disabling org stamping hides punch clock and blocks clock-in API', async ({ browser }) => {
		const adminContext = await browser.newContext()
		const employeeContext = await browser.newContext()
		const adminPage = await adminContext.newPage()
		const employeePage = await employeeContext.newPage()

		try {
			await login(adminPage, credsFromEnv('ADMIN'))

			const before = await api(adminPage, 'GET', '/apps/arbeitszeitcheck/api/admin/settings')
			expect(before.success).toBe(true)
			const restoreClock = before.settings?.clockStampingEnabled !== false
			const restoreManual = before.settings?.manualTimeEntryEnabled !== false

			await api(adminPage, 'POST', '/apps/arbeitszeitcheck/api/admin/settings', {
				data: {
					clockStampingEnabled: false,
					manualTimeEntryEnabled: true,
				},
			})

			const after = await api(adminPage, 'GET', '/apps/arbeitszeitcheck/api/admin/settings')
			expect(after.settings?.clockStampingEnabled).toBe(false)
			expect(after.settings?.manualTimeEntryEnabled).toBe(true)

			await login(employeePage, credsFromEnv('EMPLOYEE'))

			const widget = await api(employeePage, 'GET', '/apps/arbeitszeitcheck/api/dashboard-widget/employee')
			expect(widget.success).toBe(true)
			expect(widget.data?.timeCapture?.clockStampingEnabled).toBe(false)
			expect(widget.data?.timeCapture?.manualTimeEntryEnabled).toBe(true)

			const clockIn = await apiAllowFailure(employeePage, 'POST', '/apps/arbeitszeitcheck/api/clock/in', {
				data: {},
			})
			expect(clockIn.ok).toBe(false)
			expect(clockIn.status).toBe(403)
			expect(clockIn.json?.error_code).toBe('clock_stamping_disabled')

			await employeePage.goto('/apps/arbeitszeitcheck/dashboard')
			await assertArbeitszeitcheckLoaded(employeePage)
			await expect(employeePage.locator('#btn-clock-in')).toHaveCount(0)

			await api(adminPage, 'POST', '/apps/arbeitszeitcheck/api/admin/settings', {
				data: {
					clockStampingEnabled: restoreClock,
					manualTimeEntryEnabled: restoreManual,
				},
			})
		} finally {
			await adminContext.close()
			await employeeContext.close()
		}
	})
})
