(function () {
	'use strict';

	function loadDeskletInitialState() {
		try {
			if (window.OCP?.InitialState && typeof window.OCP.InitialState.loadState === 'function') {
				return window.OCP.InitialState.loadState('arbeitszeitcheck', 'desklet');
			}
			if (window.OC?.initialState && typeof window.OC.initialState.loadState === 'function') {
				return window.OC.initialState.loadState('arbeitszeitcheck', 'desklet');
			}
		} catch (_e) {
			// Missing state on non-dashboard pages.
		}
		return null;
	}

	function findDeskletMountPoint() {
		const root = document.getElementById('app-dashboard');
		if (!root) {
			return null;
		}
		const panels = root.querySelectorAll('.panel');
		for (let i = 0; i < panels.length; i++) {
			const panel = panels[i];
			if (panel.querySelector('a[href*="/apps/arbeitszeitcheck/"]')) {
				return panel.querySelector('.panel--content') || panel;
			}
		}
		return null;
	}

	function mountDeskletWorkspaceFromInitialState() {
		const state = loadDeskletInitialState();
		if (!state || typeof state !== 'object') {
			return null;
		}
		const html = typeof state.workspaceHtml === 'string' ? state.workspaceHtml.trim() : '';
		if (!html) {
			return null;
		}
		const mount = findDeskletMountPoint();
		if (!mount || mount.querySelector('[data-arbeitszeitcheck-desklet]')) {
			return null;
		}
		const wrap = document.createElement('div');
		wrap.className = 'dz-mount';
		wrap.setAttribute('data-arbeitszeitcheck-desklet-mount', '1');
		wrap.innerHTML = html;
		mount.appendChild(wrap);
		return document.getElementById('dz-config');
	}

	function resolveConfigElement() {
		let el = document.getElementById('dz-config');
		if (!el) {
			el = mountDeskletWorkspaceFromInitialState();
		}
		return el;
	}

	function bootDesklet(configEl) {
		let config;
		try {
			config = JSON.parse(configEl.textContent);
		} catch (_e) {
			return;
		}

		if (!config || typeof config !== 'object') {
			return;
		}

		runDesklet(config);
	}

	function scheduleDeskletBoot() {
		const configEl = resolveConfigElement();
		if (configEl) {
			bootDesklet(configEl);
			return;
		}
		const state = loadDeskletInitialState();
		if (!state?.workspaceHtml) {
			return;
		}
		let attempts = 0;
		const timer = window.setInterval(() => {
			attempts++;
			const el = resolveConfigElement();
			if (el) {
				window.clearInterval(timer);
				bootDesklet(el);
			} else if (attempts >= 40) {
				window.clearInterval(timer);
			}
		}, 250);
	}

	function runDesklet(config) {
	// l10n is guaranteed to be an object after the guard above
	const l10n = (config.l10n && typeof config.l10n === 'object') ? config.l10n : {};

	// ── CSRF token ─────────────────────────────────────────────────────────────
	const requestToken = (() => {
		const meta = document.head.querySelector('meta[name="requesttoken"]');
		return meta ? meta.getAttribute('content') : '';
	})();

	// ── Fetch helper ───────────────────────────────────────────────────────────
	const api = (url, method = 'GET') => fetch(url, {
		method,
		headers: {
			'Accept': 'application/json',
			'Content-Type': 'application/json',
			'requesttoken': requestToken,
		},
		credentials: 'same-origin',
	});

	// ── Element references ─────────────────────────────────────────────────────
	const statusSectionEl = document.getElementById('dz-status-section');
	const statusCardEl    = document.getElementById('dz-status-card');
	const statusBadgeEl   = document.getElementById('dz-status-badge');
	const statusTextEl    = document.getElementById('dz-status-text');
	const statusIconEl    = document.getElementById('dz-status-icon');
	const workedTodayEl   = document.getElementById('dz-worked-today');
	const sessionEl       = document.getElementById('dz-session-duration');
	const feedbackEl      = document.getElementById('dz-feedback');
	const lastUpdatedEl   = document.getElementById('dz-last-updated');
	const liveStatusEl    = document.getElementById('dz-live-status');
	const managerListEl   = document.getElementById('dz-manager-list');
	const adminListEl     = document.getElementById('dz-admin-list');
	const errorEl         = document.getElementById('dz-error');
	const actionButtons   = ['dz-clock-in', 'dz-start-break', 'dz-end-break', 'dz-clock-out']
		.map((id) => document.getElementById(id))
		.filter(Boolean);

	// ── Status tracking ────────────────────────────────────────────────────────
	let lastKnown = {
		status: 'clocked_out',
		workingTodayHours: 0,
		currentSessionDuration: 0,
		clockStampingEnabled: true,
		manualTimeEntryEnabled: true,
	};
	let mutationInFlight = false;

	// ── Helpers ────────────────────────────────────────────────────────────────
	const statusLabel = (status) => {
		switch (status) {
			case 'active': return l10n.working    || 'Working';
			case 'break':  return l10n.onBreak    || 'On Break';
			case 'paused': return l10n.paused     || 'Paused';
			case 'completed': return l10n.clockedOut || 'Clocked Out';
			default:       return l10n.clockedOut || 'Clocked Out';
		}
	};

	const timeApi = () => window.ArbeitszeitCheckTime || null;

	const formatDuration = (seconds) => {
		const api = timeApi();
		if (api) {
			const formatted = api.formatDuration(seconds);
			// Widget shows HH:MM (drop seconds for compact display).
			return formatted.length >= 8 ? formatted.slice(0, 5) : formatted;
		}
		const s = Math.max(0, Math.floor(Number(seconds) || 0));
		const h = Math.floor(s / 3600);
		const m = Math.floor((s % 3600) / 60);
		return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
	};

	const formatHours = (hours) => Number.isFinite(hours) ? hours.toFixed(2) : '0.00';

	const statusIcon = (status) => {
		const map = {
			active: 'clock',
			break: 'coffee',
			paused: 'pause',
		};
		const name = map[status] || 'circle';
		if (typeof window.AzcCatalog !== 'undefined' && typeof window.AzcCatalog.render === 'function') {
			return window.AzcCatalog.render(name, 'dashboard-widget-status-icon');
		}
		return '';
	};

	const formatTime = (value) => {
		const api = timeApi();
		if (api) {
			return api.formatTime(value, { withSeconds: true }) || '';
		}
		const date = value instanceof Date ? value : new Date(value);
		return new Intl.DateTimeFormat(undefined, {
			hour: '2-digit',
			minute: '2-digit',
			second: '2-digit',
		}).format(date);
	};

	const showFeedback = (message) => {
		if (!feedbackEl) {
			return;
		}
		feedbackEl.textContent = message;
		feedbackEl.removeAttribute('hidden');
	};

	const hideFeedback = () => {
		if (!feedbackEl) {
			return;
		}
		feedbackEl.setAttribute('hidden', '');
	};

	const announce = (message) => {
		if (!liveStatusEl || !message) {
			return;
		}
		liveStatusEl.textContent = '';
		window.setTimeout(() => {
			liveStatusEl.textContent = message;
		}, 10);
	};

	const updateLastRefreshed = () => {
		if (!lastUpdatedEl) {
			return;
		}
		const template = l10n.lastUpdated || 'Last updated: %1$s';
		const now = timeApi() ? timeApi().serverNow() : new Date();
		lastUpdatedEl.textContent = template.replace('%1$s', formatTime(now));
	};

	// Normalise status data regardless of whether it comes from the employee
	// widget API (camelCase) or a raw clock-action response (snake_case).
	const normaliseStatus = (raw) => {
		if (!raw || typeof raw !== 'object') {
			return {
				status: 'clocked_out',
				workingTodayHours: 0,
				currentSessionDuration: 0,
				clockStampingEnabled: true,
				manualTimeEntryEnabled: true,
			};
		}
		const capture = (raw.timeCapture && typeof raw.timeCapture === 'object')
			? raw.timeCapture
			: {};
		return {
			status: String(raw.status ?? 'clocked_out'),
			workingTodayHours: parseFloat(
				raw.workingTodayHours ?? raw.working_today_hours ?? 0
			),
			currentSessionDuration: parseInt(
				raw.currentSessionDuration ?? raw.current_session_duration ?? 0,
				10
			),
			clockStampingEnabled: capture.clockStampingEnabled !== false,
			manualTimeEntryEnabled: capture.manualTimeEntryEnabled !== false,
		};
	};

	const getEffectiveButtonStates = (status, clockStampingEnabled) => {
		const states = { ...(BUTTON_STATES[status] ?? BUTTON_STATES.clocked_out) };
		if (!clockStampingEnabled) {
			states['dz-clock-in'] = false;
		}
		return states;
	};

	// ── Loading state ──────────────────────────────────────────────────────────
	const setLoading = (loading) => {
		if (statusSectionEl) {
			statusSectionEl.setAttribute('aria-busy', loading ? 'true' : 'false');
		}
	};

	// ── Error display ──────────────────────────────────────────────────────────
	const showError = (message) => {
		if (!errorEl) {
			return;
		}
		errorEl.textContent = message;
		errorEl.removeAttribute('hidden');
	};

	const hideError = () => {
		if (!errorEl) {
			return;
		}
		errorEl.setAttribute('hidden', '');
	};

	const setButtonsLocked = (locked) => {
		actionButtons.forEach((btn) => {
			btn.disabled = locked;
			btn.setAttribute('aria-disabled', locked ? 'true' : 'false');
			btn.classList.toggle('btn--loading', locked);
		});
	};

	const BUTTON_STATES = {
		clocked_out: { 'dz-clock-in': true,  'dz-start-break': false, 'dz-end-break': false, 'dz-clock-out': false },
		active:      { 'dz-clock-in': false, 'dz-start-break': true,  'dz-end-break': false, 'dz-clock-out': true  },
		break:       { 'dz-clock-in': false, 'dz-start-break': false, 'dz-end-break': true,  'dz-clock-out': true },
		paused:      { 'dz-clock-in': true,  'dz-start-break': false, 'dz-end-break': false, 'dz-clock-out': true  },
		completed:   { 'dz-clock-in': true,  'dz-start-break': false, 'dz-end-break': false, 'dz-clock-out': false },
	};

	const captureNoticeEl = document.getElementById('dz-capture-notice');

	const updateCaptureNotice = (data) => {
		if (!captureNoticeEl) {
			return;
		}
		const stampingOff = data.clockStampingEnabled === false;
		const showNotice = stampingOff
			&& (data.status === 'clocked_out' || data.status === 'paused');
		if (!showNotice) {
			captureNoticeEl.setAttribute('hidden', '');
			captureNoticeEl.textContent = '';
			return;
		}
		const title = l10n.stampingDisabledTitle || 'Clock in/out is turned off';
		const body = data.status === 'paused'
			? (l10n.stampingDisabledPausedBody
				|| 'Finish the paused session on the dashboard, or contact your administrator.')
			: (data.manualTimeEntryEnabled
				? (l10n.stampingDisabledBodyManual
					|| 'Add your hours under Time entries in the app.')
				: (l10n.stampingDisabledBody
					|| 'Contact HR if you need to record time.'));
		captureNoticeEl.removeAttribute('hidden');
		captureNoticeEl.innerHTML = '';
		const titleEl = document.createElement('p');
		titleEl.className = 'dz-capture-notice__title';
		titleEl.textContent = title;
		const textEl = document.createElement('p');
		textEl.className = 'dz-capture-notice__text';
		textEl.textContent = body;
		captureNoticeEl.appendChild(titleEl);
		captureNoticeEl.appendChild(textEl);
		captureNoticeEl.setAttribute('role', 'status');
	};

	const updateButtonStates = (status, clockStampingEnabled = true) => {
		if (mutationInFlight) {
			setButtonsLocked(true);
			return;
		}
		const states = getEffectiveButtonStates(status, clockStampingEnabled);
		Object.entries(states).forEach(([id, enabled]) => {
			const btn = document.getElementById(id);
			if (!btn) {
				return;
			}
			const visible = enabled || (id !== 'dz-clock-in');
			btn.hidden = !visible;
			btn.disabled = !enabled;
			btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
		});
		// Hide clock-in completely when stamping is off (not merely disabled).
		const clockInBtn = document.getElementById('dz-clock-in');
		if (clockInBtn && !clockStampingEnabled) {
			clockInBtn.hidden = true;
			clockInBtn.disabled = true;
			clockInBtn.setAttribute('aria-disabled', 'true');
		}
	};

	// ── Render functions ───────────────────────────────────────────────────────
	const renderEmployee = (rawData) => {
		const data = normaliseStatus(rawData);
		const {
			status,
			workingTodayHours,
			currentSessionDuration,
			clockStampingEnabled,
		} = data;
		lastKnown = data;

		// Status badge: visual indicator with data-status for CSS colour coding
		if (statusBadgeEl) {
			statusBadgeEl.dataset.status = status;
			statusBadgeEl.textContent    = statusLabel(status);
		}

		if (statusTextEl) {
			const template = l10n.statusLine || 'Status: %1$s';
			statusTextEl.textContent = template
				.replace('%1$s', statusLabel(status));
		}

		if (statusIconEl) {
			statusIconEl.innerHTML = statusIcon(status);
			statusIconEl.setAttribute('aria-hidden', 'true');
		}

		if (workedTodayEl) {
			workedTodayEl.textContent = `${formatHours(workingTodayHours)} h`;
		}

		if (sessionEl) {
			sessionEl.textContent = formatDuration(currentSessionDuration);
		}

		if (statusCardEl) {
			statusCardEl.dataset.status = status;
		}

		updateButtonStates(status, clockStampingEnabled);
		updateCaptureNotice(data);
	};

	// Clears and re-renders a people list (team or company overview).
	const renderPeopleList = (target, rows, emptyMsg) => {
		if (!target) {
			return;
		}

		// Clear previous content safely (no innerHTML)
		while (target.firstChild) {
			target.removeChild(target.firstChild);
		}

		if (!Array.isArray(rows) || rows.length === 0) {
			const msg = document.createElement('p');
			msg.className   = 'dz-empty';
			msg.textContent = emptyMsg || l10n.noEntriesFound || 'No entries found.';
			target.appendChild(msg);
			return;
		}

		const list = document.createElement('ul');
		list.className = 'dz-list';
		list.setAttribute('role', 'list');

		rows.forEach((row) => {
			const item = document.createElement('li');
			item.className    = 'dz-list-item';
			item.dataset.status = String(row.status || 'clocked_out');

			// Badge
			const badge = document.createElement('span');
			badge.className       = 'dz-badge';
			badge.dataset.status  = item.dataset.status;
			badge.setAttribute('aria-hidden', 'true');
			badge.textContent     = statusLabel(item.dataset.status);

			// Text: "Alice: Working (3.25 h)"
			const text = document.createElement('span');
			text.className = 'dz-list-item__text';
			const template = l10n.peopleRow || '%1$s: %2$s (%3$s h)';
			text.textContent = template
				.replace('%1$s', String(row.displayName || ''))
				.replace('%2$s', statusLabel(item.dataset.status))
				.replace('%3$s', parseFloat(row.workingTodayHours || 0).toFixed(2));

			// Accessible label for screen readers (badge text is hidden)
			item.setAttribute('aria-label',
				String(row.displayName || '') + ': ' + statusLabel(item.dataset.status));

			item.appendChild(badge);
			item.appendChild(text);
			list.appendChild(item);
		});

		target.appendChild(list);
	};

	// ── Data loading ───────────────────────────────────────────────────────────
	const loadData = async () => {
		setLoading(true);
		if (!mutationInFlight) {
			hideError();
		}

		// Employee status (always loaded)
		try {
			const resp = await api(config.employeeDataUrl);
			if (resp.status === 401) {
				showError(l10n.sessionExpired ||
					'Your session has expired. Please refresh the page and try again.');
				setLoading(false);
				return;
			}
			if (resp.ok) {
				const json = await resp.json();
				if (json.success && json.data) {
					renderEmployee(json.data);
					updateLastRefreshed();
				}
			}
		} catch (_e) {
			if (!mutationInFlight) {
				showError(l10n.networkError ||
					'Could not load status. Please check your connection.');
			}
		}

		// Team overview (manager)
		if (config.isManager && managerListEl) {
			try {
				const resp = await api(config.managerDataUrl);
				if (resp.ok) {
					const json = await resp.json();
					if (json.success && json.data) {
						renderPeopleList(
							managerListEl,
							json.data.members || [],
							l10n.noTeamMembers || 'No team members found.'
						);
					}
				}
			} catch (_e) {
				// Non-critical: silently leave section empty
			}
		}

		// Company overview (admin)
		if (config.isAdmin && adminListEl) {
			try {
				const resp = await api(config.adminDataUrl);
				if (resp.ok) {
					const json = await resp.json();
					if (json.success && json.data) {
						renderPeopleList(
							adminListEl,
							json.data.users || [],
							l10n.noUsersFound || 'No users found.'
						);
					}
				}
			} catch (_e) {
				// Non-critical: silently leave section empty
			}
		}

		setLoading(false);
	};

	// ── Action wiring ──────────────────────────────────────────────────────────
	const wireAction = (id, url) => {
		const btn = document.getElementById(id);
		if (!btn) {
			return;
		}

		btn.addEventListener('click', async () => {
			if (btn.disabled || mutationInFlight) {
				return;
			}

			mutationInFlight = true;
			setButtonsLocked(true);
			hideError();
			hideFeedback();

			try {
				const resp = await api(url, 'POST');
				const json = await resp.json();

				if (!resp.ok || !json.success) {
					const errMsg = json.error || l10n.actionFailed || 'Action failed';
					if (resp.status === 403 && json.error_code === 'clock_stamping_disabled') {
						await loadData();
					}
					showError(errMsg);
					if (window.AzcMessaging?.announceAssertive) {
						window.AzcMessaging.announceAssertive(errMsg);
					} else {
						announce(errMsg);
					}
					updateButtonStates(lastKnown.status, lastKnown.clockStampingEnabled);
					return;
				}

				if (json.status && typeof json.status === 'object') {
					renderEmployee(json.status);
					updateLastRefreshed();
				} else {
					await loadData();
				}

				const actionLabel = btn.textContent ? btn.textContent.trim() : (l10n.actionDone || 'Action');
				const doneTemplate = l10n.actionDone || '%1$s successful';
				const successMsg = doneTemplate.replace('%1$s', actionLabel);
				showFeedback(successMsg);
				announce(successMsg);
			} catch (_e) {
				showError(l10n.networkError ||
					'Could not load status. Please check your connection.');
				announce(l10n.networkError || 'Could not load status. Please check your connection.');
				updateButtonStates(lastKnown.status, lastKnown.clockStampingEnabled);
			} finally {
				mutationInFlight = false;
				updateButtonStates(lastKnown.status, lastKnown.clockStampingEnabled);
			}
		});
	};

	wireAction('dz-clock-in',    config.clockInUrl);
	wireAction('dz-start-break', config.startBreakUrl);
	wireAction('dz-end-break',   config.endBreakUrl);
	wireAction('dz-clock-out',   config.clockOutUrl);

	// Initial load + periodic refresh
	loadData();
	const refreshTimer = setInterval(loadData, 30000);

	// Clean up interval on page unload to prevent orphaned timers
	window.addEventListener('beforeunload', () => {
		clearInterval(refreshTimer);
	});
	}

	scheduleDeskletBoot();
})();
