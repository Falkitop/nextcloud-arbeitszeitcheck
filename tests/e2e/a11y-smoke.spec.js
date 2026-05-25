// @ts-check
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const a11yRoutes = [
	'/apps/arbeitszeitcheck/dashboard',
	'/apps/arbeitszeitcheck/settings',
];

for (const path of a11yRoutes) {
	test(`a11y smoke: ${path}`, async ({ page }) => {
		await page.goto(path);
		await page.waitForSelector('#azc-main-content', { timeout: 30000 });
		const results = await new AxeBuilder({ page })
			.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
			.analyze();
		expect(results.violations).toEqual([]);
	});
}
