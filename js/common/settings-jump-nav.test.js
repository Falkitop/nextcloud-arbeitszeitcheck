/**
 * @vitest-environment jsdom
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

function stageSettingsPage() {
	document.body.innerHTML = `
		<nav class="azc-jump-nav" aria-label="Jump">
			<h2 class="azc-jump-nav__title">Quick navigation</h2>
			<ol class="azc-jump-nav__list">
				<li class="azc-jump-nav__item"><a class="azc-jump-nav__link" href="#section-a-heading">Section A</a></li>
				<li class="azc-jump-nav__item"><a class="azc-jump-nav__link" href="#section-b-heading">Section B</a></li>
			</ol>
		</nav>
		<form>
			<section class="admin-settings-section" aria-labelledby="section-a-heading">
				<h3 id="section-a-heading">Section A</h3>
			</section>
			<section class="admin-settings-section" aria-labelledby="section-b-heading">
				<h3 id="section-b-heading">Section B</h3>
			</section>
		</form>
	`;
}

describe('settings-jump-nav', () => {
	beforeEach(async () => {
		vi.resetModules();
		stageSettingsPage();
		await import('./settings-jump-nav.js');
		await Promise.resolve();
	});

	it('marks the clicked section link with aria-current', () => {
		const links = document.querySelectorAll('.azc-jump-nav__link');
		links[1].click();
		expect(links[1].getAttribute('aria-current')).toBe('location');
		expect(links[0].hasAttribute('aria-current')).toBe(false);
	});

	it('activates the link matching the location hash on load', () => {
		window.location.hash = '#section-b-heading';
		vi.resetModules();
		stageSettingsPage();
		return import('./settings-jump-nav.js').then(() => {
			const link = document.querySelector('.azc-jump-nav__link[href="#section-b-heading"]');
			expect(link.getAttribute('aria-current')).toBe('location');
			window.location.hash = '';
		});
	});
});
