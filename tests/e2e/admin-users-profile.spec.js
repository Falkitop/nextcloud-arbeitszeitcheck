import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { api } from './helpers/api.js'

test.describe('Admin employees — atomic profile save', () => {
	test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS')

	test('PUT profile saves all sections without partial failure', async ({ page }) => {
		await login(page, credsFromEnv('ADMIN'))

		const userId = 'e2e_employee'
		const user = await api(page, 'GET', `/apps/arbeitszeitcheck/api/admin/users/${userId}`)
		expect(user.success).toBe(true)

		const startDate = user.workingTimeModelStartDate || user.userWorkingTimeModel?.startDate || '2025-03-27'
		const policy = user.vacationPolicy || {}
		const vacationMode = policy.vacationMode || 'inherit'
		const inheritLowerLayers = vacationMode === 'inherit' || !!policy.inheritLowerLayers
		const manualDays = inheritLowerLayers ? null : (policy.manualDays ?? 30)
		const policyId = policy.id ?? null
		const policyEffectiveFrom = policy.effectiveFrom || startDate
		const loadedStart = startDate
		const effectiveFrom =
			policyId && policyEffectiveFrom && loadedStart && startDate === loadedStart
				? policyEffectiveFrom
				: startDate

		const payload = {
			workingTimeModel: {
				workingTimeModelId: user.workingTimeModelId ?? user.userWorkingTimeModel?.workingTimeModelId ?? 1,
				vacationDaysPerYear: user.vacationDaysPerYear ?? 28,
				vacationCarryoverDays: user.vacationCarryoverDays ?? 0,
				vacationCarryoverYear: user.vacationCarryoverYear ?? new Date().getFullYear(),
				startDate,
				endDate: user.workingTimeModelEndDate || user.userWorkingTimeModel?.endDate || null,
				germanState: user.germanState || 'NW',
			},
			vacationPolicy: {
				policyId,
				vacationMode,
				inheritLowerLayers,
				manualDays,
				tariffRuleSetId: policy.tariffRuleSetId ?? null,
				overrideReason: policy.overrideReason ?? '',
				effectiveFrom,
				effectiveTo: user.workingTimeModelEndDate || user.userWorkingTimeModel?.endDate || null,
			},
			timeCapture: (() => {
				const preferences = user.timeCapture?.preferences || user.timeCapture || {}
				return {
					clockStampingEnabled: preferences.clockStampingEnabled !== false,
					manualTimeEntryEnabled: preferences.manualTimeEntryEnabled !== false,
				}
			})(),
			overtime: {
				trackingFrom: user.overtimeTrackingFrom || null,
				openingBalance: {
					year: user.overtimeOpeningBalanceYear ?? new Date().getFullYear(),
					hours: String(user.overtimeOpeningBalanceHours ?? 0),
				},
			},
		}

		const result = await api(page, 'PUT', `/apps/arbeitszeitcheck/api/admin/users/${userId}/profile`, {
			data: payload,
		})
		expect(result.success).toBe(true)
		expect(result.userWorkingTimeModel !== undefined || result.policyId !== undefined).toBeTruthy()
	})
})
