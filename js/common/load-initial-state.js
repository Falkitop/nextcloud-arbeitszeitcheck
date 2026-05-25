/**
 * Read ArbeitszeitCheck InitialState across Nextcloud API generations.
 *
 * NC 28+ exposes `OCP.InitialState`; older builds used `OC.initialState`.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

/**
 * @param {string} appId
 * @param {string} key
 * @returns {object|null}
 */
export function loadAppInitialState(appId, key) {
	const root = typeof window !== 'undefined' ? window : globalThis;
	try {
		if (root.OCP?.InitialState && typeof root.OCP.InitialState.loadState === 'function') {
			return root.OCP.InitialState.loadState(appId, key);
		}
		if (root.OC?.initialState && typeof root.OC.initialState.loadState === 'function') {
			return root.OC.initialState.loadState(appId, key);
		}
	} catch (_) {
		// Missing or invalid state — caller falls back to inline bootstrap.
	}
	return null;
}
