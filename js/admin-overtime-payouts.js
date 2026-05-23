/**
 * Admin overtime payout (Auszahlung) UI — audit-focused payroll workflow
 *
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const cfg = window.ARBEITSZEITCHECK_OT_PAYOUT || {};
	const i18n = cfg.i18n || {};
	const Utils = window.ArbeitszeitCheck?.Utils;

	function $(sel) {
		return document.querySelector(sel);
	}

	function escapeHtml(text) {
		if (Utils && typeof Utils.escapeHtml === 'function') {
			return Utils.escapeHtml(text);
		}
		const d = document.createElement('div');
		d.textContent = String(text);
		return d.innerHTML;
	}

	function setLive(msg, isError) {
		const el = $('#ot-payout-live');
		if (!el) {
			return;
		}
		el.textContent = msg || '';
		el.classList.toggle('admin-overtime-payouts-live--error', !!isError);
	}

	function getYearMonth() {
		return {
			year: parseInt($('#ot-payout-year')?.value || '0', 10),
			month: parseInt($('#ot-payout-month')?.value || '0', 10),
		};
	}

	async function apiGet(url, params) {
		const qs = new URLSearchParams(params).toString();
		const res = await fetch(url + (qs ? '?' + qs : ''), {
			headers: { requesttoken: OC.requestToken },
			credentials: 'same-origin',
		});
		return res.json();
	}

	async function apiPost(url, body) {
		const res = await fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				requesttoken: OC.requestToken,
			},
			credentials: 'same-origin',
			body: JSON.stringify(body),
		});
		return res.json();
	}

	function renderSummary(summary, meta) {
		const el = $('#ot-payout-summary');
		if (!el || !summary) {
			return;
		}
		let text = (i18n.summary || '%1$s pending (%2$s h), %3$s already paid.')
			.replace('%1$s', String(summary.pending_count ?? 0))
			.replace('%2$s', String(summary.pending_hours ?? 0))
			.replace('%3$s', String(summary.paid_count ?? 0));
		if (meta && meta.truncated) {
			text += ' ' + (i18n.truncatedWarning || 'Warning: employee list was truncated; contact support.');
		} else if (meta && meta.total_users_in_scope != null) {
			const scanned = meta.users_scanned ?? meta.total_users_in_scope;
			text += ' ' + (i18n.scopeCount || '(%1$s employees in scope)')
				.replace('%1$s', String(scanned));
		}
		el.textContent = text;
		el.classList.toggle('admin-overtime-payouts-live--error', !!(meta && meta.truncated));
		el.hidden = false;
	}

	function statusBadge(status) {
		if (status === 'paid') {
			return '<span class="badge badge--success">' + escapeHtml(i18n.paid || 'Paid') + '</span>';
		}
		if (status === 'pending') {
			return '<span class="badge badge--warning">' + escapeHtml(i18n.pending || 'Pending') + '</span>';
		}
		return '<span class="badge">' + escapeHtml(i18n.none || '—') + '</span>';
	}

	function renderTable(items) {
		const tbody = $('#ot-payout-tbody');
		if (!tbody) {
			return;
		}
		const pending = (items || []).filter((r) => r.status === 'pending');
		const paid = (items || []).filter((r) => r.status === 'paid');
		const ordered = pending.concat(paid).concat((items || []).filter((r) => r.status === 'none'));

		if (!ordered.length) {
			tbody.innerHTML = '<tr><td colspan="5">' + escapeHtml(i18n.empty || '') + '</td></tr>';
			return;
		}

		tbody.innerHTML = ordered.map((row) => {
			const status = row.status || 'none';
			let eligible = '—';
			let paidH = '—';
			let action = '';

			if (status === 'pending') {
				eligible = Number(row.payout_eligible_hours || 0).toFixed(2);
				const name = escapeHtml(row.display_name || row.user_id);
				const hours = eligible;
				action = '<button type="button" class="btn btn--primary btn--small ot-payout-one" data-user-id="'
					+ escapeHtml(row.user_id) + '" data-name="' + name + '" data-hours="' + escapeHtml(hours) + '">'
					+ escapeHtml(i18n.confirmBtn || 'Confirm') + '</button>';
			} else if (status === 'paid') {
				paidH = Number(row.hours_paid || 0).toFixed(2);
			}

			return '<tr>'
				+ '<th scope="row">' + escapeHtml(row.display_name || row.user_id) + '</th>'
				+ '<td>' + statusBadge(status) + '</td>'
				+ '<td>' + escapeHtml(eligible) + '</td>'
				+ '<td>' + escapeHtml(paidH) + '</td>'
				+ '<td>' + action + '</td>'
				+ '</tr>';
		}).join('');

		tbody.querySelectorAll('.ot-payout-one').forEach((btn) => {
			btn.addEventListener('click', () => {
				const name = btn.getAttribute('data-name') || '';
				const hours = btn.getAttribute('data-hours') || '';
				const msg = (i18n.confirmOne || 'Record payout for %s?')
					.replace('%s', name + ' (' + hours + ' h)');
				if (window.confirm(msg)) {
					processOne(btn.getAttribute('data-user-id'));
				}
			});
		});
	}

	async function loadList() {
		if (!cfg.bankEnabled) {
			return;
		}
		const { year, month } = getYearMonth();
		setLive(i18n.loading || 'Loading…', false);
		try {
			const json = await apiGet(cfg.apiList, { year, month });
			if (!json.success) {
				setLive(json.error || i18n.error, true);
				return;
			}
			renderSummary(json.data?.summary, json.data?.meta);
			renderTable(json.data?.items);
			setLive('', false);
		} catch (e) {
			setLive(i18n.error, true);
		}
	}

	async function processOne(userId) {
		const { year, month } = getYearMonth();
		if (!userId) {
			return;
		}
		setLive(i18n.loading || 'Loading…', false);
		try {
			const json = await apiPost(cfg.apiProcess, { userId, year, month });
			if (!json.success) {
				setLive(json.error || i18n.error, true);
				return;
			}
			await loadList();
			setLive(i18n.paid || 'Paid', false);
		} catch (e) {
			setLive(i18n.error, true);
		}
	}

	async function processBulk() {
		if (!window.confirm(i18n.confirmBulk || 'Confirm bulk payout?')) {
			return;
		}
		const { year, month } = getYearMonth();
		setLive(i18n.loading || 'Loading…', false);
		try {
			const json = await apiPost(cfg.apiBulk, { year, month });
			if (!json.success) {
				setLive(json.error || i18n.error, true);
				return;
			}
			const r = json.result || {};
			const text = (i18n.done || 'Done.')
				.replace('%1$s', String(r.processed ?? 0))
				.replace('%2$s', String(r.skipped ?? 0));
			setLive(text, false);
			await loadList();
		} catch (e) {
			setLive(i18n.error, true);
		}
	}

	function exportCsv() {
		const { year, month } = getYearMonth();
		const url = cfg.apiExport + '?year=' + encodeURIComponent(year) + '&month=' + encodeURIComponent(month);
		window.location.href = url;
	}

	function applyQueryParams() {
		try {
			const params = new URLSearchParams(window.location.search);
			const year = parseInt(params.get('year') || '', 10);
			const month = parseInt(params.get('month') || '', 10);
			const yearEl = $('#ot-payout-year');
			const monthEl = $('#ot-payout-month');
			if (yearEl && Number.isFinite(year) && year >= 2000 && year <= 2100) {
				yearEl.value = String(year);
			}
			if (monthEl && Number.isFinite(month) && month >= 1 && month <= 12) {
				monthEl.value = String(month);
			}
		} catch (e) {
			// ignore malformed query string
		}
	}

	function init() {
		applyQueryParams();
		$('#ot-payout-refresh')?.addEventListener('click', loadList);
		$('#ot-payout-bulk')?.addEventListener('click', processBulk);
		$('#ot-payout-export')?.addEventListener('click', exportCsv);
		if (cfg.bankEnabled) {
			loadList();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
