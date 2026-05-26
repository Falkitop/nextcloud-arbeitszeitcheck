/**
 * Manager direct time-entry correction dialog (date + HH:mm matrix).
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
			intro: t('managerCorrectionIntro', 'Changes are applied immediately and the employee is notified. A reason is required for the audit log.'),
			workingDayLegend: t('correctionWorkingDayLegend', 'Corrected working day'),
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
			nightShiftHint: t('correctionNightShiftHint', 'Night shift: if end is earlier than start (e.g. 22:00–06:00), end counts as the next day.'),
			breaksOptional: t('correctionBreaksOptional', 'Breaks (optional)'),
			breaksHelp: t('managerCorrectionBreaksHelp', 'Adjust breaks if needed. Each break must be at least 15 minutes and within working hours.'),
			breaksEmpty: t('correctionBreaksEmpty', 'No breaks added.'),
			actions: t('Actions', 'Actions'),
			addBreak: t('correctionAddBreak', 'Add break'),
			reason: t('Reason (min. 10 characters)', 'Reason (min. 10 characters)'),
			reasonHelp: t('correctionReasonHelp', 'Required for the audit trail (at least 10 characters).'),
		};
	}

	function parseEntrySummary(raw) {
		if (!raw) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function open(entryId, updatedAt, summary) {
		if (!ClockForm || !Components.createModal) {
			Messaging.showError(t('Could not open correction dialog.', 'Could not open correction dialog.'));
			return;
		}

		const modalId = 'manager-correct-entry-' + entryId;
		const existing = document.getElementById(modalId);
		if (existing) {
			existing.remove();
		}

		const idPrefix = 'mgr-correct-' + entryId;
		const formHtml = ClockForm.buildFormHtml(idPrefix, labels());
		const footerHtml = [
			'<div class="reject-modal-actions modal-footer">',
			'<button type="button" class="btn btn--secondary btn-mgr-correct-cancel">' + t('Cancel', 'Cancel') + '</button>',
			'<button type="button" class="btn btn--primary btn-mgr-correct-save">' + t('Apply correction', 'Apply correction') + '</button>',
			'</div>',
		].join('');

		const modal = Components.createModal({
			id: modalId,
			title: t('Correct time entry', 'Correct time entry'),
			content: '<div class="manager-create-dialog__meta manager-correct-dialog__meta"></div>' + formHtml + footerHtml,
			size: 'xl',
		});

		const meta = modal.querySelector('.manager-correct-dialog__meta');
		let projectSelect = null;
		if (window.ArbeitszeitCheck?.projectCheckEnabled && summary?.userId) {
			const wrap = document.createElement('div');
			wrap.className = 'azc-filter-field';
			const pid = 'mgr-correct-project-' + entryId;
			wrap.innerHTML = [
				`<label for="${pid}" class="azc-filter-field__label">${t('Project (optional)', 'Project (optional)')}</label>`,
				'<div class="azc-filter-field__control">',
				`<select id="${pid}" class="form-select" disabled></select>`,
				'</div>',
			].join('');
			projectSelect = wrap.querySelector('select');
			meta.appendChild(wrap);
			const url = OC.generateUrl(
				'/apps/arbeitszeitcheck/api/manager/employees/{employeeId}/projectcheck-assignable-projects',
				{ employeeId: summary.userId }
			);
			fetch(url, { headers: { requesttoken: OC.requestToken } })
				.then((r) => r.json())
				.then((data) => {
					if (!projectSelect) {
						return;
					}
					projectSelect.innerHTML = '';
					const none = document.createElement('option');
					none.value = '';
					none.textContent = t('No project link', 'No project link');
					projectSelect.appendChild(none);
					if (data.success && Array.isArray(data.projects)) {
						data.projects.forEach((p) => {
							const opt = document.createElement('option');
							opt.value = String(p.id);
							opt.textContent = p.displayName || p.name || String(p.id);
							if (String(summary.projectCheckProjectId || '') === opt.value) {
								opt.selected = true;
							}
							projectSelect.appendChild(opt);
						});
					}
				})
				.finally(() => {
					if (projectSelect) {
						projectSelect.disabled = false;
					}
				});
		}

		const initial = {
			startTime: summary?.startTime || null,
			endTime: summary?.endTime || null,
			breaks: summary?.breaks || [],
		};
		const formApi = ClockForm.bindForm(modal, idPrefix, initial, t);

		const saveBtn = modal.querySelector('.btn-mgr-correct-save');
		const cancelBtn = modal.querySelector('.btn-mgr-correct-cancel');

		cancelBtn?.addEventListener('click', () => Components.closeModal(modal));

		saveBtn?.addEventListener('click', () => {
			const result = formApi.validateAndCollect();
			if (!result.ok) {
				formApi.setStatus(result.error, true);
				return;
			}
			formApi.setStatus('', false);

			const payload = {
				reason: result.payload.reason,
				expectedUpdatedAt: updatedAt || undefined,
				date: result.payload.date,
				startTime: result.payload.startTime,
				endTime: result.payload.endTime,
			};
			if (result.payload.breaks && result.payload.breaks.length > 0) {
				payload.breaks = result.payload.breaks;
			}
			if (projectSelect) {
				payload.projectCheckProjectId = projectSelect.value || '';
			}

			saveBtn.disabled = true;
			saveBtn.setAttribute('aria-busy', 'true');

			Utils.ajax('/apps/arbeitszeitcheck/api/manager/time-entries/' + entryId + '/correct', {
				method: 'POST',
				data: payload,
				onSuccess: (data) => {
					if (data.success) {
						Components.closeModal(modal);
						Messaging.showSuccess(data.message || t('Time entry corrected successfully.', 'Time entry corrected successfully.'));
						document.dispatchEvent(new CustomEvent('arbeitszeitcheck:manager-entry-corrected'));
					} else {
						Messaging.showError(data.error || t('Correction failed.', 'Correction failed.'));
						saveBtn.disabled = false;
						saveBtn.removeAttribute('aria-busy');
					}
				},
				onError: (err) => {
					const code = err?.data?.error_code;
					if (code === 'entry_modified') {
						Messaging.showError(err.error || t('Entry was modified. Reloading…', 'Entry was modified. Reloading…'));
						Components.closeModal(modal);
						document.dispatchEvent(new CustomEvent('arbeitszeitcheck:manager-entry-corrected'));
					} else {
						Messaging.showError(err?.error || t('Correction failed.', 'Correction failed.'));
					}
					saveBtn.disabled = false;
					saveBtn.removeAttribute('aria-busy');
				},
			});
		});

		Components.openModal(modalId);
	}

	window.ArbeitszeitCheckManagerCorrection = {
		open,
		parseEntrySummary,
	};
})();
