(function () {
	'use strict';

	const Utils = window.ArbeitszeitCheckUtils || {};
	const Messaging = window.ArbeitszeitCheckMessaging || {};
	const state = {
		limit: 25,
		offset: 0,
		total: 0,
		lastFilters: null,
		dateLocale: window.ArbeitszeitCheck?.dateLocale || document.documentElement.lang || undefined,
	};

	function t(key, fallback) {
		const bundle = window.ArbeitszeitCheck?.l10n || {};
		const value = bundle[key];
		if (value !== undefined && value !== '') {
			return value;
		}
		return fallback || key;
	}

	function formatHours(value) {
		if (value === null || value === undefined || value === '') {
			return '-';
		}
		const num = Number(value);
		if (Number.isNaN(num)) {
			return '-';
		}
		return num.toFixed(2);
	}

	function formatBreaks(entry) {
		if (Array.isArray(entry.breaks) && entry.breaks.length > 0) {
			return entry.breaks.map((b) => {
				const s = formatDateTime(b.start || b.start_time, 'time');
				const e = formatDateTime(b.end || b.end_time, 'time');
				return s + '–' + e;
			}).join(', ');
		}
		if (entry.breakStartTime && entry.breakEndTime) {
			return formatDateTime(entry.breakStartTime, 'time') + '–' + formatDateTime(entry.breakEndTime, 'time');
		}
		return formatHours(entry.breakDurationHours) + ' h';
	}

	function formatDateTime(iso, mode) {
		if (!iso) {
			return '-';
		}
		const api = window.ArbeitszeitCheckTime;
		if (api) {
			if (mode === 'date') {
				const ymd = String(iso).slice(0, 10);
				if (/^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
					const parsed = api.parseYmd(ymd);
					return parsed ? api.formatDate(parsed) : '-';
				}
				return api.formatDate(iso) || '-';
			}
			return api.formatTime(iso) || '-';
		}
		if (Utils.formatTime && Utils.formatDate) {
			return mode === 'date'
				? (Utils.formatDate(iso, 'DD.MM.YYYY') || '-')
				: (Utils.formatTime(iso) || '-');
		}
		return '-';
	}

	function escapeHtml(value) {
		if (value === null || value === undefined) {
			return '';
		}
		const div = document.createElement('div');
		div.textContent = String(value);
		return div.innerHTML;
	}

	function setLoading(isLoading) {
		const countEl = document.getElementById('employee-time-entries-count');
		if (countEl) {
			countEl.textContent = isLoading ? t('Loading...', 'Loading...') : countEl.textContent;
		}
	}

	function setEmpty(message) {
		const emptyEl = document.getElementById('employee-time-entries-empty');
		const tableWrap = document.getElementById('employee-time-entries-table-wrap');
		if (!emptyEl || !tableWrap) {
			return;
		}
		emptyEl.classList.remove('visually-hidden');
		tableWrap.classList.add('visually-hidden');
		const desc = emptyEl.querySelector('.empty-state__description');
		if (desc) {
			desc.textContent = message;
		}
	}

	function renderEntries(entries) {
		const body = document.getElementById('employee-time-entries-body');
		const emptyEl = document.getElementById('employee-time-entries-empty');
		const tableWrap = document.getElementById('employee-time-entries-table-wrap');
		if (!body || !emptyEl || !tableWrap) {
			return;
		}

		if (!entries.length) {
			setEmpty(t('No entries found for the selected filters.', 'No entries found for the selected filters.'));
			body.innerHTML = '';
			return;
		}

		body.innerHTML = entries.map((entry) => {
			const canCorrect = entry.status === 'completed';
			const actionCell = canCorrect
				? `<button type="button" class="btn btn--sm btn--secondary btn-manager-correct" data-entry-id="${escapeHtml(String(entry.id))}" data-entry-updated="${escapeHtml(entry.updatedAt || '')}" data-entry-start="${escapeHtml(entry.startTime || '')}" data-entry-end="${escapeHtml(entry.endTime || '')}" aria-label="${escapeHtml(t('Correct time entry', 'Correct time entry'))}">${escapeHtml(t('Correct', 'Correct'))}</button>`
				: '<span class="text-muted">–</span>';
			return [
				'<tr>',
				`<td>${escapeHtml(entry.displayName || entry.userId || '-')}</td>`,
				`<td>${escapeHtml(formatDateTime(entry.startTime, 'date'))}</td>`,
				`<td>${escapeHtml(formatDateTime(entry.startTime, 'time'))}</td>`,
				`<td>${escapeHtml(formatDateTime(entry.endTime, 'time'))}</td>`,
				`<td>${escapeHtml(formatHours(entry.workingDurationHours))}</td>`,
				`<td>${escapeHtml(formatBreaks(entry))}</td>`,
				`<td><span class="badge badge--primary">${escapeHtml(entry.status || '-')}</span></td>`,
				`<td>${escapeHtml(entry.description || t('No description', 'No description'))}</td>`,
				`<td>${actionCell}</td>`,
				'</tr>',
			].join('');
		}).join('');
		bindManagerCorrectButtons();

		emptyEl.classList.add('visually-hidden');
		tableWrap.classList.remove('visually-hidden');
	}

	function updatePagination() {
		const prevBtn = document.getElementById('employee-time-entries-prev');
		const nextBtn = document.getElementById('employee-time-entries-next');
		const indicator = document.getElementById('employee-time-entries-page-indicator');
		const currentPage = Math.floor(state.offset / state.limit) + 1;
		const totalPages = Math.max(1, Math.ceil(state.total / state.limit));

		if (indicator) {
			indicator.textContent = t('Page {page} of {pages}', 'Page {page} of {pages}')
				.replace('{page}', String(currentPage))
				.replace('{pages}', String(totalPages));
		}
		if (prevBtn) {
			prevBtn.disabled = state.offset <= 0;
		}
		if (nextBtn) {
			nextBtn.disabled = state.offset + state.limit >= state.total;
		}
	}

	function updateCount() {
		const countEl = document.getElementById('employee-time-entries-count');
		if (!countEl) {
			return;
		}
		countEl.textContent = t('{count} entries', '{count} entries').replace('{count}', String(state.total));
	}

	function populateEmployees(employees) {
		const select = document.getElementById('employee-filter');
		if (!select) {
			return;
		}
		const current = select.value;
		const defaultOption = select.querySelector('option[value=""]');
		select.innerHTML = '';
		if (defaultOption) {
			select.appendChild(defaultOption);
		} else {
			const option = document.createElement('option');
			option.value = '';
			option.textContent = t('All in my scope', 'All in my scope');
			select.appendChild(option);
		}

		employees.forEach((employee) => {
			const option = document.createElement('option');
			option.value = employee.userId;
			option.textContent = employee.displayName || employee.userId;
			select.appendChild(option);
		});

		if (current) {
			select.value = current;
		}
	}

	function buildQuery(filters) {
		const dp = window.ArbeitszeitCheckDatepicker;
		const toISO = dp && typeof dp.convertEuropeanToISO === 'function'
			? dp.convertEuropeanToISO
			: (value) => value;
		const startISO = toISO(filters.startDate);
		const endISO = toISO(filters.endDate);
		const params = new URLSearchParams();
		params.set('startDate', startISO);
		params.set('endDate', endISO);
		params.set('limit', String(state.limit));
		params.set('offset', String(state.offset));
		if (filters.employeeId) {
			params.set('employeeId', filters.employeeId);
		}
		if (filters.status) {
			params.set('status', filters.status);
		}
		return params.toString();
	}

	function loadEntries() {
		const form = document.getElementById('employee-time-entries-filter-form');
		if (!form) {
			return;
		}

		const formData = new FormData(form);
		const filters = {
			employeeId: String(formData.get('employee_id') || ''),
			startDate: String(formData.get('start_date') || ''),
			endDate: String(formData.get('end_date') || ''),
			status: String(formData.get('status') || ''),
		};
		state.lastFilters = filters;

		if (!filters.startDate || !filters.endDate) {
			setEmpty(t('Please select start and end date.', 'Please select start and end date.'));
			updatePagination();
			return;
		}

		setLoading(true);
		const query = buildQuery(filters);
		Utils.ajax(`/apps/arbeitszeitcheck/api/manager/employee-time-entries?${query}`, {
			method: 'GET',
			onSuccess: (data) => {
				state.total = Number(data.total || 0);
				populateEmployees(Array.isArray(data.employees) ? data.employees : []);
				renderEntries(Array.isArray(data.entries) ? data.entries : []);
				updateCount();
				updatePagination();
			},
			onError: (error) => {
				Messaging.showError(error?.error || t('Could not load employee time entries.', 'Could not load employee time entries.'));
				setEmpty(t('Could not load employee time entries.', 'Could not load employee time entries.'));
			},
		});
	}

	function bindPagination() {
		const prevBtn = document.getElementById('employee-time-entries-prev');
		const nextBtn = document.getElementById('employee-time-entries-next');

		if (prevBtn) {
			prevBtn.addEventListener('click', () => {
				state.offset = Math.max(0, state.offset - state.limit);
				loadEntries();
			});
		}
		if (nextBtn) {
			nextBtn.addEventListener('click', () => {
				state.offset += state.limit;
				loadEntries();
			});
		}
	}

	function bindForm() {
		const form = document.getElementById('employee-time-entries-filter-form');
		const clearBtn = document.getElementById('employee-time-entries-clear');
		if (!form || !clearBtn) {
			return;
		}

		form.addEventListener('submit', (event) => {
			event.preventDefault();
			state.offset = 0;
			loadEntries();
		});

		clearBtn.addEventListener('click', () => {
			form.reset();
			state.offset = 0;
			state.total = 0;
			setDefaultDateRange();
			setEmpty(t('Choose a date range to load entries.', 'Choose a date range to load entries.'));
			updateCount();
			updatePagination();
		});
	}

	function toEuropeanDateString(date) {
		const day = String(date.getDate()).padStart(2, '0');
		const month = String(date.getMonth() + 1).padStart(2, '0');
		const year = date.getFullYear();
		return `${day}.${month}.${year}`;
	}

	function setDefaultDateRange() {
		const startInput = document.getElementById('start-date-filter');
		const endInput = document.getElementById('end-date-filter');
		if (!startInput || !endInput) {
			return;
		}

		// Only apply defaults when fields are empty to avoid overwriting user input.
		if (startInput.value || endInput.value) {
			return;
		}

		const api = window.ArbeitszeitCheckTime;
		if (api) {
			const endYmd = api.todayYmd();
			const endParsed = api.parseYmd(endYmd);
			if (endParsed) {
				const startParsed = new Date(endParsed);
				startParsed.setMonth(startParsed.getMonth() - 1);
				startInput.value = Utils.formatDate
					? Utils.formatDate(startParsed, 'DD.MM.YYYY')
					: toEuropeanDateString(startParsed);
				endInput.value = Utils.formatDate
					? Utils.formatDate(endParsed, 'DD.MM.YYYY')
					: toEuropeanDateString(endParsed);
				return;
			}
		}
		const today = new Date();
		const oneMonthAgo = new Date(today);
		oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
		startInput.value = toEuropeanDateString(oneMonthAgo);
		endInput.value = toEuropeanDateString(today);
	}

	function toDatetimeLocalValue(iso) {
		if (!iso) {
			return '';
		}
		const d = new Date(iso);
		if (Number.isNaN(d.getTime())) {
			return '';
		}
		const pad = (n) => String(n).padStart(2, '0');
		return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
			+ 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
	}

	function fromDatetimeLocalValue(value) {
		if (!value) {
			return null;
		}
		const d = new Date(value);
		return Number.isNaN(d.getTime()) ? null : d.toISOString();
	}


	function showManagerCorrectModal(entryId, updatedAt, startIso, endIso) {
		const Components = window.ArbeitszeitCheckComponents;
		if (!Components || !Components.createModal) {
			Messaging.showError(t('Could not open correction dialog.', 'Could not open correction dialog.'));
			return;
		}
		const modalId = 'manager-correct-entry-' + entryId;
		if (document.getElementById(modalId)) {
			document.getElementById(modalId).remove();
		}
		const content = [
			'<p class="form-help">' + escapeHtml(t('Changes are applied immediately and the employee is notified. A reason is required for the audit log.', 'Changes are applied immediately and the employee is notified. A reason is required for the audit log.')) + '</p>',
			'<div class="form-group"><label for="mgr-correct-start-' + entryId + '">' + escapeHtml(t('Start', 'Start')) + '</label>',
			'<input type="datetime-local" class="form-input" id="mgr-correct-start-' + entryId + '"></div>',
			'<div class="form-group"><label for="mgr-correct-end-' + entryId + '">' + escapeHtml(t('End', 'End')) + '</label>',
			'<input type="datetime-local" class="form-input" id="mgr-correct-end-' + entryId + '"></div>',
			'<div class="form-group"><label for="mgr-correct-reason-' + entryId + '">' + escapeHtml(t('Reason (min. 10 characters)', 'Reason (min. 10 characters)')) + '</label>',
			'<textarea class="form-textarea" id="mgr-correct-reason-' + entryId + '" rows="3" minlength="10" required></textarea></div>',
			'<div class="reject-modal-actions">',
			'<button type="button" class="btn btn--secondary btn-mgr-correct-cancel">' + escapeHtml(t('Cancel', 'Cancel')) + '</button>',
			'<button type="button" class="btn btn--primary btn-mgr-correct-save">' + escapeHtml(t('Apply correction', 'Apply correction')) + '</button>',
			'</div>',
		].join('');
		const modal = Components.createModal({
			id: modalId,
			title: t('Correct time entry', 'Correct time entry'),
			content: content,
			size: 'md',
		});
		const startInput = modal.querySelector('#mgr-correct-start-' + entryId);
		const endInput = modal.querySelector('#mgr-correct-end-' + entryId);
		if (startInput) {
			startInput.value = toDatetimeLocalValue(startIso);
		}
		if (endInput) {
			endInput.value = toDatetimeLocalValue(endIso);
		}
		const saveBtn = modal.querySelector('.btn-mgr-correct-save');
		const cancelBtn = modal.querySelector('.btn-mgr-correct-cancel');
		cancelBtn.addEventListener('click', () => Components.closeModal(modal));
		saveBtn.addEventListener('click', () => {
			const reason = (modal.querySelector('#mgr-correct-reason-' + entryId) || {}).value || '';
			const newStart = fromDatetimeLocalValue((modal.querySelector('#mgr-correct-start-' + entryId) || {}).value);
			const newEnd = fromDatetimeLocalValue((modal.querySelector('#mgr-correct-end-' + entryId) || {}).value);
			if (reason.trim().length < 10) {
				Messaging.showError(t('A reason of at least 10 characters is required.', 'A reason of at least 10 characters is required.'));
				return;
			}
			if (!newStart && !newEnd) {
				Messaging.showError(t('At least one field to correct is required.', 'At least one field to correct is required.'));
				return;
			}
			const payload = { reason: reason.trim(), expectedUpdatedAt: updatedAt || undefined };
			if (newStart) {
				payload.startTime = newStart;
			}
			if (newEnd) {
				payload.endTime = newEnd;
			}
			saveBtn.disabled = true;
			Utils.ajax('/apps/arbeitszeitcheck/api/manager/time-entries/' + entryId + '/correct', {
				method: 'POST',
				data: payload,
				onSuccess: (data) => {
					if (data.success) {
						Components.closeModal(modal);
						Messaging.showSuccess(data.message || t('Time entry corrected successfully.', 'Time entry corrected successfully.'));
						loadEntries();
					} else {
						Messaging.showError(data.error || t('Correction failed.', 'Correction failed.'));
						saveBtn.disabled = false;
					}
				},
				onError: (err) => {
					const code = err?.data?.error_code;
					if (code === 'entry_modified') {
						Messaging.showError(err.error || t('Entry was modified. Reloading…', 'Entry was modified. Reloading…'));
						Components.closeModal(modal);
						loadEntries();
					} else {
						Messaging.showError(err?.error || t('Correction failed.', 'Correction failed.'));
					}
					saveBtn.disabled = false;
				},
			});
		});
		Components.openModal(modalId);
	}


	function bindManagerCorrectButtons() {
		document.querySelectorAll('.btn-manager-correct').forEach((btn) => {
			btn.addEventListener('click', () => {
				const id = btn.getAttribute('data-entry-id');
				const updatedAt = btn.getAttribute('data-entry-updated') || '';
				const startIso = btn.getAttribute('data-entry-start') || '';
				const endIso = btn.getAttribute('data-entry-end') || '';
				if (id) {
					showManagerCorrectModal(id, updatedAt, startIso, endIso);
				}
			});
		});
	}

	function init() {
		setDefaultDateRange();
		bindForm();
		bindPagination();
		updatePagination();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
