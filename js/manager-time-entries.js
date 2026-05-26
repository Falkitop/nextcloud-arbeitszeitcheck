(function () {
	'use strict';

	const Utils = window.ArbeitszeitCheckUtils || {};
	const Messaging = window.ArbeitszeitCheckMessaging || {};
	const state = {
		limit: 25,
		offset: 0,
		total: 0,
		lastFilters: null,
		loading: false,
		countBeforeLoad: '',
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
		state.loading = isLoading;
		const results = document.querySelector('.manager-scope-page__results');
		if (results) {
			results.setAttribute('aria-busy', isLoading ? 'true' : 'false');
		}
		const submitBtn = document.getElementById('employee-time-entries-submit');
		const clearBtn = document.getElementById('employee-time-entries-clear');
		const prevBtn = document.getElementById('employee-time-entries-prev');
		const nextBtn = document.getElementById('employee-time-entries-next');
		if (submitBtn) {
			submitBtn.disabled = isLoading;
			submitBtn.setAttribute('aria-disabled', isLoading ? 'true' : 'false');
		}
		if (clearBtn) {
			clearBtn.disabled = isLoading;
		}
		if (prevBtn) {
			prevBtn.disabled = isLoading || state.offset <= 0;
		}
		if (nextBtn) {
			nextBtn.disabled = isLoading || state.offset + state.limit >= state.total;
		}
		const countEl = document.getElementById('employee-time-entries-count');
		if (!countEl) {
			return;
		}
		if (isLoading) {
			state.countBeforeLoad = countEl.textContent;
			countEl.textContent = t('Loading...', 'Loading...');
			return;
		}
		countEl.textContent = state.countBeforeLoad;
	}

	function clearFilterFieldErrors() {
		const form = document.getElementById('employee-time-entries-filter-form');
		if (!form) {
			return;
		}
		form.querySelectorAll('[aria-invalid="true"]').forEach((el) => {
			el.removeAttribute('aria-invalid');
		});
		const errorEl = document.getElementById('employee-time-entries-filter-error');
		setFilterErrorText(errorEl, '');
	}

	function setFilterErrorText(errorEl, message) {
		if (!errorEl) {
			return;
		}
		const inner = errorEl.querySelector('.azc-callout__text');
		if (inner) {
			inner.textContent = message;
		} else {
			errorEl.textContent = message;
		}
		errorEl.hidden = !message;
	}

	function setFilterError(message, focusId) {
		const errorEl = document.getElementById('employee-time-entries-filter-error');
		setFilterErrorText(errorEl, message);
		if (message && focusId) {
			const focusEl = document.getElementById(focusId);
			if (focusEl) {
				focusEl.setAttribute('aria-invalid', 'true');
				focusEl.focus();
			}
		}
	}

	function europeanToYmd(value) {
		if (!value || !/^\d{2}\.\d{2}\.\d{4}$/.test(value)) {
			return '';
		}
		const parts = String(value).split('.');
		return parts[2] + '-' + parts[1] + '-' + parts[0];
	}

	function parseEuropeanDate(value) {
		if (!value || !/^\d{2}\.\d{2}\.\d{4}$/.test(value)) {
			return null;
		}
		const parts = String(value).split('.');
		const date = new Date(
			parseInt(parts[2], 10),
			parseInt(parts[1], 10) - 1,
			parseInt(parts[0], 10)
		);
		if (
			date.getFullYear() !== parseInt(parts[2], 10)
			|| date.getMonth() !== parseInt(parts[1], 10) - 1
			|| date.getDate() !== parseInt(parts[0], 10)
		) {
			return null;
		}
		return date;
	}

	function validateFilters(filters) {
		clearFilterFieldErrors();
		if (!filters.startDate || !filters.endDate) {
			const message = t('Please select start and end date.', 'Please select start and end date.');
			setFilterError(message, !filters.startDate ? 'start-date-filter' : 'end-date-filter');
			return { valid: false };
		}
		const startParsed = parseEuropeanDate(filters.startDate);
		const endParsed = parseEuropeanDate(filters.endDate);
		if (!startParsed || !endParsed) {
			const message = t(
				'Invalid date format. Please use dd.mm.yyyy (e.g., 15.01.2024).',
				'Invalid date format. Please use dd.mm.yyyy (e.g., 15.01.2024).'
			);
			setFilterError(message, !startParsed ? 'start-date-filter' : 'end-date-filter');
			return { valid: false };
		}
		if (startParsed > endParsed) {
			const message = t(
				'Invalid date range. The start date must be before the end date.',
				'Invalid date range. The start date must be before the end date.'
			);
			setFilterError(message, 'start-date-filter');
			return { valid: false };
		}
		const dp = window.ArbeitszeitCheckDatepicker;
		const toISO = dp && typeof dp.convertEuropeanToISO === 'function'
			? dp.convertEuropeanToISO
			: europeanToYmd;
		const startISO = toISO(filters.startDate);
		const endISO = toISO(filters.endDate);
		if (!/^\d{4}-\d{2}-\d{2}$/.test(startISO) || !/^\d{4}-\d{2}-\d{2}$/.test(endISO)) {
			const message = t(
				'Invalid date range. Please use valid dates in YYYY-MM-DD format.',
				'Invalid date range. Please use valid dates in YYYY-MM-DD format.'
			);
			setFilterError(message, 'start-date-filter');
			return { valid: false };
		}
		const maxDays = Number(window.ArbeitszeitCheck?.maxManagerListDateRangeDays) || 365;
		const spanDays = Math.round((endParsed.getTime() - startParsed.getTime()) / 86400000) + 1;
		if (spanDays > maxDays) {
			const message = t('dateRangeTooLong', 'Date range must not exceed %d days. Please narrow the range.')
				.replace('%d', String(maxDays));
			setFilterError(message, 'start-date-filter');
			return { valid: false };
		}
		return { valid: true, startISO, endISO };
	}

	function setEmpty(message) {
		const emptyEl = document.getElementById('employee-time-entries-empty');
		const tableWrap = document.getElementById('employee-time-entries-table-wrap');
		if (!emptyEl || !tableWrap) {
			return;
		}
		emptyEl.classList.remove('visually-hidden');
		tableWrap.classList.add('visually-hidden');
		const desc = emptyEl.querySelector('.azc-empty-state__text')
			|| emptyEl.querySelector('.empty-state__description');
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
			const summaryPayload = {
				userId: entry.userId || null,
				projectCheckProjectId: entry.projectCheckProjectId || null,
				startTime: entry.displayStartTime || entry.startTime || null,
				endTime: entry.displayEndTime || entry.endTime || null,
				breaks: entry.displayBreaks || entry.breaks || null,
			};
			const summaryJson = Utils.encodeAttributeJson
				? Utils.encodeAttributeJson(summaryPayload)
				: escapeHtml(JSON.stringify(summaryPayload));
			const actionCell = canCorrect
				? `<button type="button" class="azc-btn azc-btn--secondary azc-btn--sm btn-manager-correct" data-entry-id="${escapeHtml(String(entry.id))}" data-entry-updated="${escapeHtml(entry.updatedAt || '')}" data-entry-summary="${summaryJson}" aria-label="${escapeHtml(t('Correct time entry', 'Correct time entry'))}">${escapeHtml(t('Correct', 'Correct'))}</button>`
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
				`<td class="azc-table-actions-col"><div class="azc-table-actions" role="group">${actionCell}</div></td>`,
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
		if (state.total <= 0 && !state.lastFilters) {
			countEl.textContent = '';
			state.countBeforeLoad = '';
			return;
		}
		const text = t('{count} entries', '{count} entries').replace('{count}', String(state.total));
		countEl.textContent = text;
		state.countBeforeLoad = text;
	}

	function populateEmployees(employees) {
		const select = document.getElementById('employee-filter');
		if (!select) {
			return;
		}
		const current = select.value;
		select.innerHTML = '';
		const defaultOption = document.createElement('option');
		defaultOption.value = '';
		defaultOption.textContent = t('All in my scope', 'All in my scope');
		select.appendChild(defaultOption);

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

	function buildQuery(filters, isoDates) {
		const startISO = isoDates?.startISO || europeanToYmd(filters.startDate);
		const endISO = isoDates?.endISO || europeanToYmd(filters.endDate);
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
		const validation = validateFilters(filters);
		if (!validation.valid) {
			setEmpty(t('Choose a date range to load entries.', 'Choose a date range to load entries.'));
			updateCount();
			updatePagination();
			return;
		}

		if (state.loading) {
			return;
		}

		state.lastFilters = filters;
		setLoading(true);
		const query = buildQuery(filters, validation);
		Utils.ajax(`/apps/arbeitszeitcheck/api/manager/employee-time-entries?${query}`, {
			method: 'GET',
			onSuccess: (data) => {
				clearFilterFieldErrors();
				state.total = Number(data.total || 0);
				populateEmployees(Array.isArray(data.employees) ? data.employees : []);
				renderEntries(Array.isArray(data.entries) ? data.entries : []);
				updateCount();
				state.countBeforeLoad = document.getElementById('employee-time-entries-count')?.textContent || '';
				updatePagination();
			},
			onError: (error) => {
				const message = error?.error || t('Could not load employee time entries.', 'Could not load employee time entries.');
				setFilterError(message);
				Messaging.showError(message);
				setEmpty(message);
				updateCount();
				updatePagination();
			},
		}).finally(() => {
			setLoading(false);
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
			if (state.loading) {
				return;
			}
			form.reset();
			clearFilterFieldErrors();
			state.offset = 0;
			state.total = 0;
			state.lastFilters = null;
			state.countBeforeLoad = '';
			setDefaultDateRange();
			setEmpty(t('Choose a date range to load entries.', 'Choose a date range to load entries.'));
			const countEl = document.getElementById('employee-time-entries-count');
			if (countEl) {
				countEl.textContent = '';
			}
			updatePagination();
			const employeeSelect = document.getElementById('employee-filter');
			if (employeeSelect && employeeSelect.options.length <= 1) {
				employeeSelect.innerHTML = '';
				const option = document.createElement('option');
				option.value = '';
				option.textContent = t('All in my scope', 'All in my scope');
				employeeSelect.appendChild(option);
			}
		});

		form.querySelectorAll('#start-date-filter, #end-date-filter').forEach((input) => {
			input.addEventListener('change', clearFilterFieldErrors);
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


	function bindManagerCorrectButtons() {
		const MgrCorrection = window.ArbeitszeitCheckManagerCorrection;
		document.querySelectorAll('.btn-manager-correct').forEach((btn) => {
			btn.addEventListener('click', () => {
				const id = btn.getAttribute('data-entry-id');
				const updatedAt = btn.getAttribute('data-entry-updated') || '';
				const summary = MgrCorrection
					? MgrCorrection.parseEntrySummary(btn.getAttribute('data-entry-summary'))
					: null;
				if (id && MgrCorrection) {
					MgrCorrection.open(id, updatedAt, summary || {});
				}
			});
		});
	}


	function init() {
		setDefaultDateRange();
		bindForm();
		bindPagination();
		updatePagination();
		document.addEventListener('arbeitszeitcheck:manager-entry-corrected', loadEntries);
		document.addEventListener('arbeitszeitcheck:manager-entry-created', loadEntries);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
