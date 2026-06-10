/**
 * Admin kiosk — terminals, credentials, enrollment.
 *
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const page = document.getElementById('azc-kiosk-page');
	if (!page) {
		return;
	}

	const token = page.dataset.requesttoken || '';
	const live = document.getElementById('azc-kiosk-live');
	const alertEl = document.getElementById('azc-kiosk-alert');
	let i18n = {};
	try {
		i18n = JSON.parse(page.dataset.i18n || '{}');
	} catch {
		i18n = {};
	}

	function t(key, fallback) {
		return i18n[key] || fallback || key;
	}

	function announce(msg) {
		if (live) {
			live.textContent = msg;
		}
	}

	function alertAnnounce(msg) {
		if (alertEl) {
			alertEl.textContent = msg;
		}
	}

	function headers() {
		return {
			'Content-Type': 'application/json',
			requesttoken: token,
			'X-Requested-With': 'XMLHttpRequest',
		};
	}

	function apiUrl(pattern, id) {
		return String(pattern || '').replace('__ID__', encodeURIComponent(id));
	}

	async function api(url, options) {
		const res = await fetch(url, options);
		const data = await res.json().catch(() => ({}));
		if (!res.ok) {
			throw new Error(data.error || data.message || t('requestFailed', 'Request failed'));
		}
		return data;
	}

	function escapeHtml(s) {
		return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	let modalReturnFocus = null;

	function openModal(modal, backdrop, returnFocus) {
		if (!modal) {
			return;
		}
		modalReturnFocus = returnFocus || null;
		modal.hidden = false;
		if (backdrop) {
			backdrop.hidden = false;
		}
		document.body.classList.add('azc-kiosk-modal-open');
		const focusTarget = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
		if (focusTarget instanceof HTMLElement) {
			focusTarget.focus();
		}
	}

	function closeModal(modal, backdrop) {
		if (!modal) {
			return;
		}
		modal.hidden = true;
		if (backdrop) {
			backdrop.hidden = true;
		}
		document.body.classList.remove('azc-kiosk-modal-open');
		if (modalReturnFocus instanceof HTMLElement) {
			modalReturnFocus.focus();
		}
		modalReturnFocus = null;
	}

	function bindModal(modal, backdrop, closeBtn) {
		if (!modal) {
			return;
		}
		const close = () => closeModal(modal, backdrop);
		if (closeBtn) {
			closeBtn.addEventListener('click', close);
		}
		if (backdrop) {
			backdrop.addEventListener('click', close);
		}
		modal.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				close();
			}
		});
	}

	const pairingModal = document.getElementById('azc-kiosk-pairing-modal');
	const pairingBackdrop = document.getElementById('azc-kiosk-pairing-backdrop');
	const pairingClose = document.getElementById('azc-kiosk-pairing-close');
	bindModal(pairingModal, pairingBackdrop, pairingClose);

	const pinModal = document.getElementById('azc-kiosk-pin-modal');
	const pinBackdrop = document.getElementById('azc-kiosk-pin-backdrop');
	const pinClose = document.getElementById('azc-kiosk-pin-close');
	bindModal(pinModal, pinBackdrop, pinClose);

	async function loadCredentials() {
		const tbody = document.getElementById('azc-kiosk-creds-body');
		if (!tbody) {
			return;
		}
		const data = await api(page.dataset.apiCredentials || '', { headers: headers() });
		const creds = (data.data && data.data.credentials) || [];
		const allowedLabel = t('kioskAllowedLabel', 'Allow kiosk access');
		tbody.innerHTML = creds.map((c) => {
			const checked = c.kioskAllowed ? ' checked' : '';
			const toggleId = 'azc-kiosk-allowed-' + escapeHtml(c.userId);
			return '<tr data-user-id="' + escapeHtml(c.userId) + '">'
				+ '<td>' + escapeHtml(c.displayName) + ' <small>(' + escapeHtml(c.userId) + ')</small></td>'
				+ '<td>' + escapeHtml(c.type) + '</td>'
				+ '<td>'
				+ '<label class="azc-kiosk-allowed-toggle" for="' + toggleId + '">'
				+ '<input type="checkbox" class="azc-kiosk-allowed-input" id="' + toggleId + '"'
				+ ' data-user-id="' + escapeHtml(c.userId) + '"' + checked + '>'
				+ '<span class="azc-sr-only">' + escapeHtml(allowedLabel) + ' — ' + escapeHtml(c.displayName) + '</span>'
				+ '<span class="azc-kiosk-allowed-visual" aria-hidden="true">' + (c.kioskAllowed ? '✓' : '—') + '</span>'
				+ '</label>'
				+ '</td>'
				+ '<td><button type="button" class="azc-btn azc-btn--small azc-kiosk-delete-cred" data-id="' + c.id + '">'
				+ escapeHtml(t('delete', 'Delete')) + '</button></td></tr>';
		}).join('');

		tbody.querySelectorAll('.azc-kiosk-delete-cred').forEach((btn) => {
			btn.addEventListener('click', async () => {
				const id = btn.getAttribute('data-id');
				await api((page.dataset.apiCredentials || '') + '/' + id, { method: 'DELETE', headers: headers() });
				announce(t('credentialRemoved', 'Credential removed'));
				loadCredentials();
			});
		});

		tbody.querySelectorAll('.azc-kiosk-allowed-input').forEach((input) => {
			input.addEventListener('change', async () => {
				const userId = input.getAttribute('data-user-id') || '';
				const allowed = input.checked;
				const url = apiUrl(page.dataset.apiUserAllowed || '', userId);
				await api(url, {
					method: 'PUT',
					headers: headers(),
					body: JSON.stringify({ kioskAllowed: allowed }),
				});
				announce(allowed ? t('kioskAllowedOn', 'Kiosk access enabled') : t('kioskAllowedOff', 'Kiosk access disabled'));
				loadCredentials();
			});
		});
	}

	function bindRevokeButtons(scope) {
		const root = scope || document;
		root.querySelectorAll('.azc-kiosk-revoke-terminal').forEach((btn) => {
			if (btn.dataset.bound === '1') {
				return;
			}
			btn.dataset.bound = '1';
			btn.addEventListener('click', async () => {
				const terminalId = btn.getAttribute('data-terminal-id') || '';
				if (!terminalId) {
					return;
				}
				if (!window.confirm(t('confirmRevoke', 'Revoke this terminal?'))) {
					return;
				}
				const url = apiUrl(page.dataset.apiTerminalRevoke || '', terminalId);
				await api(url, { method: 'POST', headers: headers() });
				announce(t('terminalRevoked', 'Terminal revoked'));
				window.location.reload();
			});
		});
	}

	const enabledToggle = document.getElementById('azc-kiosk-enabled');
	if (enabledToggle) {
		enabledToggle.addEventListener('change', async () => {
			await api(page.dataset.apiEnabled || '', {
				method: 'POST',
				headers: headers(),
				body: JSON.stringify({ enabled: enabledToggle.checked }),
			});
			announce(enabledToggle.checked ? t('kioskEnabled', 'Kiosk enabled') : t('kioskDisabled', 'Kiosk disabled'));
		});
	}

	const createBtn = document.getElementById('azc-kiosk-create-terminal');
	if (createBtn) {
		createBtn.addEventListener('click', async () => {
			const labelEl = document.getElementById('azc-kiosk-terminal-label');
			const label = labelEl ? labelEl.value.trim() : '';
			if (!label) {
				alertAnnounce(t('labelRequired', 'Enter a terminal label'));
				if (labelEl) {
					labelEl.focus();
				}
				return;
			}
			try {
				const data = await api(page.dataset.apiTerminals || '', {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ label }),
				});
				const codeEl = document.getElementById('azc-kiosk-pairing-code');
				if (codeEl && data.data) {
					codeEl.textContent = data.data.pairingCode || '';
				}
				openModal(pairingModal, pairingBackdrop, createBtn);
				announce(t('terminalCreated', 'Terminal created — save the pairing code'));
			} catch (e) {
				alertAnnounce(e instanceof Error ? e.message : t('requestFailed', 'Request failed'));
			}
		});
	}

	if (pairingClose) {
		pairingClose.addEventListener('click', () => {
			closeModal(pairingModal, pairingBackdrop);
			window.location.reload();
		});
	}

	const userSearch = document.getElementById('azc-kiosk-user-search');
	const userResults = document.getElementById('azc-kiosk-user-results');
	const selectedUser = document.getElementById('azc-kiosk-selected-user');
	if (userSearch && userResults) {
		let timer = null;
		userSearch.addEventListener('input', () => {
			clearTimeout(timer);
			timer = setTimeout(async () => {
				const q = userSearch.value.trim();
				if (q.length < 2) {
					userResults.hidden = true;
					return;
				}
				try {
					const data = await api((page.dataset.apiSearchUsers || '') + '?q=' + encodeURIComponent(q), { headers: headers() });
					const users = data.users || [];
					userResults.innerHTML = users.map((u) =>
						'<li role="option"><button type="button" class="azc-kiosk-user-pick" data-id="' + escapeHtml(u.userId) + '">'
						+ escapeHtml(u.displayName) + '</button></li>'
					).join('');
					userResults.hidden = users.length === 0;
					userResults.querySelectorAll('.azc-kiosk-user-pick').forEach((btn) => {
						btn.addEventListener('click', () => {
							if (selectedUser) {
								selectedUser.value = btn.getAttribute('data-id') || '';
							}
							userSearch.value = btn.textContent || '';
							userResults.hidden = true;
						});
					});
				} catch (e) {
					alertAnnounce(e instanceof Error ? e.message : t('requestFailed', 'Request failed'));
				}
			}, 250);
		});
	}

	const enrollBtn = document.getElementById('azc-kiosk-start-enrollment');
	if (enrollBtn) {
		enrollBtn.addEventListener('click', async () => {
			const userId = selectedUser ? selectedUser.value : '';
			const terminalSelect = document.getElementById('azc-kiosk-enroll-terminal');
			const terminalId = terminalSelect ? terminalSelect.value : '';
			if (!userId || !terminalId) {
				alertAnnounce(t('selectEmployeeTerminal', 'Select employee and terminal'));
				return;
			}
			try {
				await api(page.dataset.apiEnrollmentStart || '', {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ userId, terminalId }),
				});
				const statusEl = document.getElementById('azc-kiosk-enrollment-status');
				if (statusEl) {
					statusEl.textContent = t('enrollmentWaiting', 'Waiting for badge scan…');
				}
				pollEnrollment(terminalId);
			} catch (e) {
				alertAnnounce(e instanceof Error ? e.message : t('requestFailed', 'Request failed'));
			}
		});
	}

	async function pollEnrollment(terminalId) {
		const statusEl = document.getElementById('azc-kiosk-enrollment-status');
		for (let i = 0; i < 60; i++) {
			await new Promise((r) => setTimeout(r, 3000));
			try {
				const data = await api((page.dataset.apiEnrollmentStatus || '') + '?terminalId=' + encodeURIComponent(terminalId), { headers: headers() });
				const st = (data.data && data.data.status) || '';
				if (st === 'completed') {
					if (statusEl) {
						statusEl.textContent = t('enrollmentDone', 'Badge assigned successfully');
					}
					loadCredentials();
					return;
				}
				if (st === 'expired') {
					if (statusEl) {
						statusEl.textContent = t('enrollmentExpired', 'Enrollment expired');
					}
					return;
				}
			} catch {
				// keep polling unless terminal errors persist
			}
		}
	}

	const pinBtn = document.getElementById('azc-kiosk-generate-pin');
	if (pinBtn) {
		pinBtn.addEventListener('click', async () => {
			const userId = selectedUser ? selectedUser.value : '';
			if (!userId) {
				alertAnnounce(t('selectEmployee', 'Select an employee first'));
				if (userSearch) {
					userSearch.focus();
				}
				return;
			}
			try {
				const data = await api(page.dataset.apiPin || '', {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ userId }),
				});
				const pin = data.data && data.data.pin;
				const codeEl = document.getElementById('azc-kiosk-pin-code');
				if (codeEl) {
					codeEl.textContent = pin ? String(pin) : '';
				}
				openModal(pinModal, pinBackdrop, pinBtn);
				loadCredentials();
			} catch (e) {
				alertAnnounce(e instanceof Error ? e.message : t('requestFailed', 'Request failed'));
			}
		});
	}

	bindRevokeButtons();
	loadCredentials().catch(() => {});
})();
