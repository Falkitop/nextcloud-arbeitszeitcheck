/**
 * Applies timezone bootstrap from Nextcloud InitialState.
 *
 * Loaded via Util::addInitScript() by {@see TimeClientBootstrap} so
 * `window.ArbeitszeitCheck.tz` and `serverNow` exist before `common/time.js`
 * on every page — including the global dashboard (widgets) that do not
 * render `templates/common/navigation.php`.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */
(function (root) {
	'use strict';

	function applyBootstrap(cfg) {
		if (!cfg || typeof cfg !== 'object') {
			return;
		}
		root.ArbeitszeitCheck = root.ArbeitszeitCheck || {};
		if (cfg.tz && typeof cfg.tz === 'object') {
			root.ArbeitszeitCheck.tz = Object.assign({}, root.ArbeitszeitCheck.tz || {}, cfg.tz);
		}
		if (typeof cfg.serverNow === 'string' && cfg.serverNow !== '') {
			root.ArbeitszeitCheck.serverNow = cfg.serverNow;
		}
		if (root.ArbeitszeitCheckTime
			&& typeof root.ArbeitszeitCheckTime.syncFromServer === 'function'
			&& root.ArbeitszeitCheck.serverNow) {
			root.ArbeitszeitCheckTime.syncFromServer(root.ArbeitszeitCheck.serverNow);
		}
	}

	try {
		if (root.OC
			&& root.OC.initialState
			&& typeof root.OC.initialState.loadState === 'function') {
			applyBootstrap(root.OC.initialState.loadState('arbeitszeitcheck', 'time'));
		}
	} catch (_) {
		// InitialState unavailable — time.js will fall back to browser local TZ.
	}
})(typeof window !== 'undefined' ? window : globalThis);
