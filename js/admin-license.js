/**
 * Admin license tab — paste AZC2 key, assign mobile seats.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const page = document.getElementById('azc-license-page');
	if (!page) {
		return;
	}

	const apiLicense = page.dataset.apiLicense || '';
	const apiClearLicense = page.dataset.apiClearLicense || '';
	const apiSeats = page.dataset.apiSeats || '';
	const apiRemoveSeat = page.dataset.apiRemoveSeat || '';
	const apiSearchUsers = page.dataset.apiSearchUsers || '';
	const requestToken = page.dataset.requesttoken || '';
	let i18n = {};
	try {
		i18n = JSON.parse(page.dataset.i18n || '{}');
	} catch {
		i18n = {};
	}

	function t(key, fallback) {
		return i18n[key] || fallback || key;
	}

	const liveRegion = document.getElementById('azc-license-live');
	const alertRegion = document.getElementById('azc-license-alert');
	const keyInput = document.getElementById('azc-license-key-input');
	const saveBtn = document.getElementById('azc-license-save');
	const clearBtn = document.getElementById('azc-license-clear');
	const statusPanel = document.getElementById('azc-license-status');
	const seatListBody = document.getElementById('azc-seat-list-body');
	const seatEmpty = document.getElementById('azc-seat-empty');
	const seatCount = document.getElementById('azc-seat-count');
	const userSearch = document.getElementById('azc-seat-user-search');
	const searchResults = document.getElementById('azc-seat-search-results');

	function announce(el, message) {
		if (el) {
			el.textContent = message;
		}
	}

	function headers() {
		return {
			'Content-Type': 'application/json',
			requesttoken: requestToken,
			'X-Requested-With': 'XMLHttpRequest',
		};
	}

	function updateStatus(data) {
		if (!data || !statusPanel) {
			return;
		}
		statusPanel.hidden = false;
		const lic = data.license || data;
		const set = (id, val) => {
			const el = document.getElementById(id);
			if (el) {
				el.textContent = val;
			}
		};
		set('azc-license-customer', lic.customerId || '');
		set('azc-license-valid-until', lic.validUntil || '');
		set('azc-license-mobile-used', String(data.mobileSeatsUsed ?? 0));
		set('azc-license-mobile-limit', String(data.mobileSeatsLimit ?? lic.mobileSeats ?? 0));
		set('azc-license-terminal-used', String(data.terminalDevicesUsed ?? 0));
		set('azc-license-terminal-limit', String(data.terminalDevicesLimit ?? lic.terminalDevices ?? 0));
		const badge = document.getElementById('azc-license-active-badge');
		if (badge) {
			const active = !!lic.active;
			badge.textContent = active
				? (badge.dataset.activeLabel || t('activeLabel', 'Active'))
				: (badge.dataset.inactiveLabel || t('inactiveLabel', 'Expired or invalid'));
			badge.classList.toggle('azc-badge--success', active);
			badge.classList.toggle('azc-badge--warning', !active);
		}
		if (seatCount && data.mobileSeatsLimit != null) {
			seatCount.textContent = (data.mobileSeatsUsed ?? 0) + ' / ' + data.mobileSeatsLimit;
		}
	}

	function renderSeatRows(seats) {
		if (!seatListBody) {
			return;
		}
		seatListBody.innerHTML = '';
		if (!seats || seats.length === 0) {
			if (seatEmpty) {
				seatEmpty.hidden = false;
			}
			return;
		}
		if (seatEmpty) {
			seatEmpty.hidden = true;
		}
		seats.forEach((seat) => {
			const tr = document.createElement('tr');
			tr.dataset.userId = seat.userId;
			tr.innerHTML =
				'<td>' + escapeHtml(seat.displayName) + '</td>' +
				'<td><code>' + escapeHtml(seat.userId) + '</code></td>' +
				'<td>' + escapeHtml(seat.assignedAt) + '</td>' +
				'<td><button type="button" class="azc-btn azc-btn--secondary azc-seat-remove" data-user-id="' + escapeAttr(seat.userId) + '">Remove</button></td>';
			seatListBody.appendChild(tr);
		});
	}

	function escapeHtml(s) {
		const d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	function escapeAttr(s) {
		return String(s).replace(/"/g, '&quot;');
	}

	if (saveBtn && keyInput) {
		saveBtn.addEventListener('click', async () => {
			const key = keyInput.value.trim();
			if (!key) {
				announce(alertRegion, t('emptyKey', 'Please paste a license key.'));
				keyInput.focus();
				return;
			}
			saveBtn.disabled = true;
			try {
				const res = await fetch(apiLicense, {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ licenseKey: key }),
				});
				const data = await res.json();
				if (data.ok) {
					updateStatus(data);
					announce(liveRegion, t('saveSuccess', 'License saved successfully.'));
					window.location.reload();
				} else {
					announce(alertRegion, data.message || t('saveFailed', 'Could not save license.'));
				}
			} catch (e) {
				announce(alertRegion, t('networkError', 'Network error. Please try again.'));
			} finally {
				saveBtn.disabled = false;
			}
		});
	}

	if (clearBtn) {
		clearBtn.addEventListener('click', async () => {
			if (!window.confirm(t('clearConfirm', 'Remove the organisation license?'))) {
				return;
			}
			clearBtn.disabled = true;
			try {
				const res = await fetch(apiClearLicense, {
					method: 'DELETE',
					headers: headers(),
				});
				const data = await res.json();
				if (data.ok) {
					announce(liveRegion, t('clearSuccess', 'License removed.'));
					window.location.reload();
				} else {
					announce(alertRegion, data.message || t('clearFailed', 'Could not remove license.'));
				}
			} catch (e) {
				announce(alertRegion, t('networkError', 'Network error. Please try again.'));
			} finally {
				clearBtn.disabled = false;
			}
		});
	}

	if (seatListBody) {
		seatListBody.addEventListener('click', async (ev) => {
			const btn = ev.target.closest('.azc-seat-remove');
			if (!btn) {
				return;
			}
			const userId = btn.dataset.userId;
			if (!userId) {
				return;
			}
			btn.disabled = true;
			try {
				const res = await fetch(apiRemoveSeat, {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ userId }),
				});
				const data = await res.json();
				if (data.ok) {
					renderSeatRows(data.seats);
					updateStatus(data);
					announce(liveRegion, t('seatRemoved', 'Seat removed.'));
				}
			} finally {
				btn.disabled = false;
			}
		});
	}

	let searchTimer = null;
	if (userSearch && searchResults) {
		userSearch.addEventListener('input', () => {
			clearTimeout(searchTimer);
			const q = userSearch.value.trim();
			if (q.length < 2) {
				searchResults.hidden = true;
				searchResults.innerHTML = '';
				userSearch.setAttribute('aria-expanded', 'false');
				return;
			}
			searchTimer = setTimeout(async () => {
				try {
					const url = apiSearchUsers + '?q=' + encodeURIComponent(q);
					const res = await fetch(url, { headers: { requesttoken: requestToken } });
					const data = await res.json();
					searchResults.innerHTML = '';
					if (!data.ok || !data.users || data.users.length === 0) {
						searchResults.hidden = true;
						userSearch.setAttribute('aria-expanded', 'false');
						return;
					}
					data.users.forEach((u) => {
						if (u.hasSeat) {
							return;
						}
						const li = document.createElement('li');
						li.role = 'option';
						li.tabIndex = 0;
						li.textContent = u.displayName + ' (' + u.id + ')';
						li.dataset.userId = u.id;
						li.addEventListener('click', () => assignSeat(u.id));
						li.addEventListener('keydown', (e) => {
							if (e.key === 'Enter' || e.key === ' ') {
								e.preventDefault();
								assignSeat(u.id);
							}
						});
						searchResults.appendChild(li);
					});
					searchResults.hidden = searchResults.children.length === 0;
					userSearch.setAttribute('aria-expanded', searchResults.hidden ? 'false' : 'true');
				} catch (e) {
					searchResults.hidden = true;
				}
			}, 250);
		});
	}

	async function assignSeat(userId) {
		try {
			const res = await fetch(apiSeats, {
				method: 'POST',
				headers: headers(),
				body: JSON.stringify({ userId }),
			});
			const data = await res.json();
			if (data.ok) {
				renderSeatRows(data.seats);
				updateStatus(data);
				if (userSearch) {
					userSearch.value = '';
				}
				if (searchResults) {
					searchResults.hidden = true;
					searchResults.innerHTML = '';
				}
				announce(liveRegion, t('seatAssigned', 'Seat assigned.'));
			} else {
				announce(alertRegion, data.message || t('assignFailed', 'Could not assign seat.'));
			}
		} catch (e) {
			announce(alertRegion, t('networkError', 'Network error.'));
		}
	}
})();
