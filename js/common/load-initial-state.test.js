import { describe, expect, it, beforeEach, afterEach } from 'vitest';
import { loadAppInitialState } from './load-initial-state.js';

describe('loadAppInitialState', () => {
	const snapshot = {};

	beforeEach(() => {
		snapshot.OCP = globalThis.OCP;
		snapshot.OC = globalThis.OC;
		delete globalThis.OCP;
		delete globalThis.OC;
	});

	afterEach(() => {
		globalThis.OCP = snapshot.OCP;
		globalThis.OC = snapshot.OC;
	});

	it('reads from OCP.InitialState when available', () => {
		globalThis.OCP = {
			InitialState: {
				loadState: (appId, key) => {
					expect(appId).toBe('arbeitszeitcheck');
					expect(key).toBe('time');
					return { tz: { storage: 'Europe/Berlin', display: 'Europe/Berlin' }, serverNow: '2026-05-25T10:00:00+02:00' };
				},
			},
		};

		const cfg = loadAppInitialState('arbeitszeitcheck', 'time');
		expect(cfg?.tz?.storage).toBe('Europe/Berlin');
	});

	it('falls back to OC.initialState on older Nextcloud builds', () => {
		globalThis.OC = {
			initialState: {
				loadState: () => ({ tz: { storage: 'UTC', display: 'UTC' } }),
			},
		};

		const cfg = loadAppInitialState('arbeitszeitcheck', 'time');
		expect(cfg?.tz?.storage).toBe('UTC');
	});

	it('returns null when no InitialState API exists', () => {
		expect(loadAppInitialState('arbeitszeitcheck', 'time')).toBeNull();
	});
});
