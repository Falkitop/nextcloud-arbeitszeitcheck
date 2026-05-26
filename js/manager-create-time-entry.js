/**
 * Manager: create a completed time entry for a managed employee.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const Utils = window.ArbeitszeitCheckUtils || {};
	const Messaging = window.ArbeitszeitCheckMessaging || {};
	const Components = window.ArbeitszeitCheckComponents || {};
	const ClockForm = window.ArbeitszeitCheckClockForm;

	function t(key, fallback) {
		const bundle = window.ArbeitszeitCheck?.l10n || {};
		const value = bundle[key];
		return value !== undefined && value !== '' ? value : (fallback || key);
	}

	function labels() {
		return {
			intro: t(
				'managerCreateIntro',
				'The entry is saved as completed and counts toward the employee\'s hours. A reason is required for the audit log.'
			),
			workingDayLegend: t('correctionWorkingDayLegend', 'Working day'),
			date: t('Date', 'Date'),
			required: t('required', 'required'),
			datePlaceholder: t('dd.mm.yyyy', 'dd.mm.yyyy'),
			today: t('Today', 'Today'),
			dateHelp: t('correctionDateHelp', 'Format: dd.mm.yyyy'),
			workingHours: t('Working Hours', 'Working Hours'),
			startTime: t('Start Time', 'Start Time'),
			endTime: t('End Time', 'End Time'),
			start: t('Start', 'Start'),
			end: t('End', 'End'),
			nightShiftHint: t(
				'correctionNightShiftHint',
				'Night shift: if end is earlier than start (e.g. 22:00–06:00), end counts as the next day.'
			),
			breaksOptional: t('correctionBreaksOptional', 'Breaks (optional)'),
			breaksHelp: t('managerCorrectionBreaksHelp', 'Each break must be at least 15 minutes and within working hours.'),
			breaksEmpty: t('correctionBreaksEmpty', 'No breaks added.'),
			actions: t('Actions', 'Actions'),
			addBreak: t('correctionAddBreak', 'Add break'),
			reason: t('Reason (min. 10 characters)', 'Reason (min. 10 characters)'),
			reasonHelp: t('correctionReasonHelp', 'Required for the audit trail (at least 10 characters).'),
		};
	}

	function fetchEmployees() {
		const select = document.getElementById('employee-filter');
		if (!select || select.options.length > 1) {
			return Promise.resolve();
		}
		const end = document.getElementById('end-date-filter');
		const start = document.getElementById('start-date-filter');
		if (!end || !start || !end.value || !start.value) {
			return Promise.resolve();
		}
		const dp = window.ArbeitszeitCheckDatepicker;
		const startISO = dp && dp.convertEuropeanToISO ? dp.convertEuropeanToISO(start.value) : '';
		const endISO = dp && dp.convertEuropeanToISO ? dp.convertEuropeanToISO(end.value) : '';
		if (!startISO || !endISO) {
			return Promise.resolve();
		}
		const params = new URLSearchParams({ startDate: startISO, endDate: endISO, limit: '200', offset: '0' });
		return Utils.ajax(`/apps/arbeitszeitcheck/api/manager/employee-time-entries?${params}`, {
			method: 'GET',
		}).then((data) => {
			if (!data || !Array.isArray(data.employees)) {
				return;
			}
			const current = select.value;
			select.innerHTML = '';
			const def = document.createElement('option');
			def.value = '';
			def.textContent = t('All in my scope', 'All in my scope');
			select.appendChild(def);
			data.employees.forEach((emp) => {
				const opt = document.createElement('option');
				opt.value = emp.userId;
				opt.textContent = emp.displayName || emp.userId;
				select.appendChild(opt);
			});
			if (current) {
				select.value = current;
			}
		}).catch(() => {});
	}

	function loadProjectOptions(selectEl, employeeId) {
		if (!selectEl) {
			return Promise.resolve();
		}
		selectEl.innerHTML = '';
		const none = document.createElement('option');
		none.value = '';
		none.textContent = t('No project link', 'No project link');
		selectEl.appendChild(none);
		if (!employeeId || !window.ArbeitszeitCheck?.projectCheckEnabled) {
			selectEl.disabled = true;
			return Promise.resolve();
		}
		selectEl.disabled = true;
		const url = OC.generateUrl(
			'/apps/arbeitszeitcheck/api/manager/employees/{employeeId}/projectcheck-assignable-projects',
			{ employeeId: employeeId }
		);
		return fetch(url, { headers: { requesttoken: OC.requestToken } })
			.then((r) => r.json())
			.then((data) => {
				if (!data.success || !Array.isArray(data.projects)) {
					return;
				}
				data.projects.forEach((p) => {
					const opt = document.createElement('option');
					opt.value = String(p.id);
					opt.textContent = p.displayName || p.name || String(p.id);
					selectEl.appendChild(opt);
				});
			})
			.catch(() => {
				Messaging.showError(t('Could not load projects.', 'Could not load projects.'));
			})
			.finally(() => {
				selectEl.disabled = false;
			});
	}

	function buildEmployeeField(employees) {
		const wrap = document.createElement('div');
		wrap.className = 'azc-filter-field manager-create-dialog__employee';
		const id = 'mgr-create-employee';
		wrap.innerHTML = [
			`<label for="${id}" class="azc-filter-field__label">${t('Select employee', 'Select employee')} <span class="required-star" aria-hidden="true">*</span></label>`,
			'<div class="azc-filter-field__control">',
			`<select id="${id}" class="form-select" required aria-required="true"></select>`,
			'</div>',
		].join('');
		const sel = wrap.querySelector('select');
		const filterSel = document.getElementById('employee-filter');
		const source = filterSel && filterSel.options.length > 1
			? Array.from(filterSel.options).filter((o) => o.value !== '')
			: (employees || []);
		source.forEach((item) => {
			const opt = document.createElement('option');
			opt.value = item.value !== undefined ? item.value : item.userId;
			opt.textContent = item.textContent !== undefined ? item.textContent : (item.displayName || item.userId);
			sel.appendChild(opt);
		});
		return { wrap, sel };
	}

	function buildProjectField() {
		const wrap = document.createElement('div');
		wrap.className = 'azc-filter-field manager-create-dialog__project';
		const id = 'mgr-create-project';
		wrap.innerHTML = [
			`<label for="${id}" class="azc-filter-field__label">${t('Project (optional)', 'Project (optional)')}</label>`,
			'<div class="azc-filter-field__control">',
			`<select id="${id}" class="form-select" disabled></select>`,
			'</div>',
		].join('');
		return { wrap, sel: wrap.querySelector('select') };
	}

	function open() {
		if (!ClockForm || !Components.createModal) {
			Messaging.showError(t('Could not open form.', 'Could not open form.'));
			return;
		}

		fetchEmployees().finally(() => {
			const modalId = 'manager-create-time-entry-modal';
			const existing = document.getElementById(modalId);
			if (existing) {
				existing.remove();
			}

			const idPrefix = 'mgr-create';
			const { wrap: empWrap, sel: empSel } = buildEmployeeField();
			const { wrap: projWrap, sel: projSel } = buildProjectField();
			const formHtml = ClockForm.buildFormHtml(idPrefix, labels());
			const footerHtml = [
				'<div class="reject-modal-actions modal-footer">',
				`<button type="button" class="btn btn--secondary btn-mgr-create-cancel">${t('Cancel', 'Cancel')}</button>`,
				`<button type="button" class="btn btn--primary btn-mgr-create-save">${t('Save time entry', 'Save time entry')}</button>`,
				'</div>',
			].join('');

			const modal = Components.createModal({
				id: modalId,
				title: t('Record time for an employee', 'Record time for an employee'),
				content: '<div class="manager-create-dialog__meta"></div>' + formHtml + footerHtml,
				size: 'xl',
			});

			const meta = modal.querySelector('.manager-create-dialog__meta');
			meta.appendChild(empWrap);
			if (window.ArbeitszeitCheck?.projectCheckEnabled) {
				meta.appendChild(projWrap);
			}

			const formApi = ClockForm.bindForm(modal, idPrefix, {}, t);
			const preselected = document.getElementById('employee-filter')?.value || '';
			if (preselected && empSel) {
				empSel.value = preselected;
			}
			loadProjectOptions(projSel, empSel?.value || '');

			empSel?.addEventListener('change', () => {
				loadProjectOptions(projSel, empSel.value);
			});

			const saveBtn = modal.querySelector('.btn-mgr-create-save');
			const cancelBtn = modal.querySelector('.btn-mgr-create-cancel');
			cancelBtn?.addEventListener('click', () => Components.closeModal(modal));

			saveBtn?.addEventListener('click', () => {
				const result = formApi.validateAndCollect();
				if (!result.ok) {
					formApi.setStatus(result.error, true);
					return;
				}
				if (!empSel?.value) {
					formApi.setStatus(t('Select employee', 'Select employee'), true);
					empSel?.focus();
					return;
				}
				formApi.setStatus('', false);

				const payload = {
					userId: empSel.value,
					reason: result.payload.reason,
					date: result.payload.date,
					startTime: result.payload.startTime,
					endTime: result.payload.endTime,
				};
				if (result.payload.breaks?.length) {
					payload.breaks = result.payload.breaks;
				}
				if (projSel?.value) {
					payload.projectCheckProjectId = projSel.value;
				}

				saveBtn.disabled = true;
				saveBtn.setAttribute('aria-busy', 'true');

				Utils.ajax('/apps/arbeitszeitcheck/api/manager/employee-time-entries', {
					method: 'POST',
					data: payload,
					onSuccess: (data) => {
						Messaging.showSuccess(data.message || t('Time entry recorded for the employee.', 'Time entry recorded for the employee.'));
						if (data.warning) {
							Messaging.showWarning(data.warning);
						}
						Components.closeModal(modal);
						document.dispatchEvent(new CustomEvent('arbeitszeitcheck:manager-entry-created'));
					},
					onError: (err) => {
						Messaging.showError(err?.error || t('Could not save time entry.', 'Could not save time entry.'));
					},
				}).finally(() => {
					saveBtn.disabled = false;
					saveBtn.removeAttribute('aria-busy');
				});
			});

			Components.openModal(modal);
		});
	}

	function init() {
		const btn = document.getElementById('manager-open-create-time-entry');
		btn?.addEventListener('click', open);
		document.addEventListener('arbeitszeitcheck:manager-entry-created', () => {
			document.dispatchEvent(new CustomEvent('arbeitszeitcheck:manager-entry-corrected'));
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.ArbeitszeitCheckManagerCreate = { open };
})();
