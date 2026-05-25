/**
 * Admin tariff rule sets CRUD page for the ArbeitszeitCheck app.
 *
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const Utils = window.ArbeitszeitCheckUtils || {};
	const Messaging = window.ArbeitszeitCheckMessaging || {};
	const Components = window.AzcComponents || window.ArbeitszeitCheckComponents || {};

	const API_LIST = '/apps/arbeitszeitcheck/api/admin/tariff-rule-sets';

	const MODULE_DEFINITIONS = [
		{
			type: 'base_formula',
			labelKey: 'moduleBaseFormula',
			fields: [
				{ name: 'reference_days', labelKey: 'referenceDays', type: 'number', step: '0.5', min: 0, max: 366, defaultValue: 30 },
				{ name: 'reference_week_days', labelKey: 'referenceWeekDays', type: 'number', step: '0.5', min: 1, max: 7, defaultValue: 5 },
				{ name: 'work_days_per_week', labelKey: 'workDaysPerWeek', type: 'number', step: '0.5', min: 1, max: 7, defaultValue: 5 },
			],
		},
		{
			type: 'additional_entitlements',
			labelKey: 'moduleAdditional',
			fields: [
				{ name: 'days', labelKey: 'days', type: 'number', step: '0.5', min: 0, max: 366, defaultValue: 0 },
			],
		},
		{
			type: 'deductions',
			labelKey: 'moduleDeductions',
			fields: [
				{ name: 'days', labelKey: 'days', type: 'number', step: '0.5', min: 0, max: 366, defaultValue: 0 },
			],
		},
		{
			type: 'rounding_rule',
			labelKey: 'moduleRounding',
			fields: [
				{
					name: 'mode',
					labelKey: 'roundingMode',
					type: 'select',
					defaultValue: 'commercial',
					options: [
						{ value: 'commercial', labelKey: 'roundingCommercial' },
						{ value: 'ceil', labelKey: 'roundingCeil' },
						{ value: 'floor', labelKey: 'roundingFloor' },
					],
				},
			],
		},
		{
			type: 'pro_rata_rule',
			labelKey: 'moduleProRata',
			fields: [
				{
					name: 'mode',
					labelKey: 'proRataMode',
					type: 'select',
					defaultValue: 'none',
					options: [
						{ value: 'none', labelKey: 'proRataNone' },
						{ value: 'month', labelKey: 'proRataMonth' },
						{ value: 'day', labelKey: 'proRataDay' },
					],
				},
			],
		},
	];

	function l10n(key) {
		const map = window.ArbeitszeitCheck && window.ArbeitszeitCheck.tariffRulesL10n;
		if (map && Object.prototype.hasOwnProperty.call(map, key)) {
			return String(map[key]);
		}
		return key;
	}

	function escapeHtml(value) {
		if (Utils.escapeHtml) {
			return Utils.escapeHtml(value == null ? '' : String(value));
		}
		const div = document.createElement('div');
		div.textContent = value == null ? '' : String(value);
		return div.innerHTML;
	}

	function generateUrl(path) {
		if (Utils.resolveUrl) {
			return Utils.resolveUrl(path);
		}
		if (window.OC && typeof window.OC.generateUrl === 'function') {
			return window.OC.generateUrl(path);
		}
		return path;
	}

	function requestToken() {
		if (Utils.getRequestToken) {
			return Utils.getRequestToken();
		}
		return (typeof OC !== 'undefined' && OC.requestToken) ? OC.requestToken : '';
	}

	function announce(message) {
		const region = document.getElementById('admin-tariff-rules-feedback');
		if (region) {
			region.textContent = String(message || '');
		}
	}

	async function apiFetch(url, options) {
		const opts = Object.assign({ method: 'GET', headers: {}, credentials: 'same-origin' }, options || {});
		opts.headers = Object.assign({}, opts.headers, {
			Accept: 'application/json',
			requesttoken: requestToken(),
			'OCS-APIRequest': 'true',
		});
		if (opts.json !== undefined) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(opts.json);
			delete opts.json;
		}
		const response = await fetch(generateUrl(url), opts);
		let payload = null;
		try {
			payload = await response.json();
		} catch (_) {
			payload = null;
		}
		return { ok: response.ok, status: response.status, payload };
	}

	function statusLabel(status) {
		switch (status) {
			case 'draft': return l10n('statusDraft');
			case 'active': return l10n('statusActive');
			case 'retired': return l10n('statusRetired');
			default: return status;
		}
	}

	function statusBadgeClass(status) {
		switch (status) {
			case 'active': return 'badge badge--success';
			case 'draft': return 'badge badge--warning';
			case 'retired': return 'badge';
			default: return 'badge';
		}
	}

	function renderTable(ruleSets) {
		const tbody = document.getElementById('tariff-rules-tbody');
		if (!tbody) {
			return;
		}

		if (!ruleSets.length) {
			tbody.innerHTML = `
				<tr>
					<td colspan="8">
						<div class="azc-empty-state admin-tariff-rules__empty">
							<p class="azc-empty-state__title">${escapeHtml(l10n('noRuleSets'))}</p>
							<p class="azc-empty-state__lead">${escapeHtml(l10n('noRuleSetsHelp'))}</p>
						</div>
					</td>
				</tr>`;
			return;
		}

		tbody.innerHTML = ruleSets.map((rs) => {
			const id = String(rs.id);
			const actions = [];
			if (rs.status === 'draft') {
				actions.push(`<button type="button" class="azc-btn azc-btn--secondary azc-btn--sm" data-action="edit" data-id="${escapeHtml(id)}">${escapeHtml(l10n('edit'))}</button>`);
				actions.push(`<button type="button" class="azc-btn azc-btn--primary azc-btn--sm" data-action="activate" data-id="${escapeHtml(id)}">${escapeHtml(l10n('activate'))}</button>`);
				actions.push(`<button type="button" class="azc-btn azc-btn--danger azc-btn--sm" data-action="delete" data-id="${escapeHtml(id)}">${escapeHtml(l10n('delete'))}</button>`);
			} else if (rs.status === 'active') {
				actions.push(`<button type="button" class="azc-btn azc-btn--secondary azc-btn--sm" data-action="view" data-id="${escapeHtml(id)}">${escapeHtml(l10n('view'))}</button>`);
				actions.push(`<button type="button" class="azc-btn azc-btn--danger azc-btn--sm" data-action="retire" data-id="${escapeHtml(id)}">${escapeHtml(l10n('retire'))}</button>`);
			} else {
				actions.push(`<button type="button" class="azc-btn azc-btn--secondary azc-btn--sm" data-action="view" data-id="${escapeHtml(id)}">${escapeHtml(l10n('view'))}</button>`);
			}
			return `
				<tr data-rule-set-id="${escapeHtml(id)}">
					<th scope="row">${escapeHtml(rs.tariffCode || '')}</th>
					<td>${escapeHtml(rs.version || '')}</td>
					<td>${escapeHtml(rs.jurisdiction || '—')}</td>
					<td><span class="${statusBadgeClass(rs.status)}">${escapeHtml(statusLabel(rs.status))}</span></td>
					<td>${escapeHtml(rs.validFrom || '—')}</td>
					<td>${escapeHtml(rs.validTo || '—')}</td>
					<td>${escapeHtml(typeof rs.modulesCount === 'number' ? String(rs.modulesCount) : '—')}</td>
					<td class="admin-tariff-rules__row-actions">${actions.join(' ')}</td>
				</tr>`;
		}).join('');

		tbody.querySelectorAll('button[data-action]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const action = btn.getAttribute('data-action');
				const id = parseInt(btn.getAttribute('data-id') || '0', 10);
				handleRowAction(action, id);
			});
		});
	}

	async function loadRuleSets() {
		const tbody = document.getElementById('tariff-rules-tbody');
		if (tbody) {
			tbody.innerHTML = `<tr><td colspan="8" class="admin-tariff-rules__empty">${escapeHtml(l10n('loading'))}</td></tr>`;
		}
		const { ok, payload } = await apiFetch(API_LIST, { method: 'GET' });
		if (!ok || !payload || !payload.success) {
			const msg = (payload && payload.error) || l10n('loadingError');
			if (tbody) {
				tbody.innerHTML = `<tr><td colspan="8" class="admin-tariff-rules__empty">${escapeHtml(msg)}</td></tr>`;
			}
			if (Messaging.showError) {
				Messaging.showError(msg);
			}
			return;
		}
		renderTable(payload.ruleSets || []);
	}

	async function handleRowAction(action, id) {
		if (!id) {
			return;
		}
		if (action === 'edit') {
			openEditModal(id);
		} else if (action === 'view') {
			openViewModal(id);
		} else if (action === 'delete') {
			await confirmAndCall(
				l10n('confirmDeleteTitle'),
				l10n('confirmDeleteMessage'),
				l10n('delete'),
				'danger',
				async () => {
					const r = await apiFetch(`${API_LIST}/${encodeURIComponent(id)}`, { method: 'DELETE' });
					if (r.ok && r.payload && r.payload.success) {
						Messaging.showSuccess && Messaging.showSuccess(l10n('deleted'));
						announce(l10n('deleted'));
						loadRuleSets();
					} else {
						Messaging.showError && Messaging.showError((r.payload && r.payload.error) || l10n('deleteError'));
					}
				},
			);
		} else if (action === 'activate') {
			await confirmAndCall(
				l10n('confirmActivateTitle'),
				l10n('confirmActivateMessage'),
				l10n('activate'),
				'warning',
				async () => {
					const r = await apiFetch(`${API_LIST}/${encodeURIComponent(id)}/activate`, { method: 'POST', json: {} });
					if (r.ok && r.payload && r.payload.success) {
						Messaging.showSuccess && Messaging.showSuccess(l10n('activated'));
						announce(l10n('activated'));
						loadRuleSets();
					} else {
						Messaging.showError && Messaging.showError((r.payload && r.payload.error) || l10n('activateError'));
					}
				},
			);
		} else if (action === 'retire') {
			await confirmAndCall(
				l10n('confirmRetireTitle'),
				l10n('confirmRetireMessage'),
				l10n('retire'),
				'danger',
				async () => {
					const r = await apiFetch(`${API_LIST}/${encodeURIComponent(id)}/retire`, { method: 'POST', json: {} });
					if (r.ok && r.payload && r.payload.success) {
						Messaging.showSuccess && Messaging.showSuccess(l10n('retired'));
						announce(l10n('retired'));
						loadRuleSets();
					} else {
						Messaging.showError && Messaging.showError((r.payload && r.payload.error) || l10n('retireError'));
					}
				},
			);
		}
	}

	async function confirmAndCall(title, message, confirmLabel, variant, fn) {
		const confirmed = Utils.confirmDestructiveAction
			? await Utils.confirmDestructiveAction({ title, message, confirmLabel, variant })
			: null;
		if (!confirmed) {
			return;
		}
		try {
			await fn();
		} catch (_) {
			Messaging.showError && Messaging.showError(l10n('updateError'));
		}
	}

	function findModuleDefinition(type) {
		return MODULE_DEFINITIONS.find((d) => d.type === type) || null;
	}

	function moduleTypeOptionsHtml(selectedType) {
		return MODULE_DEFINITIONS.map((d) => {
			const sel = d.type === selectedType ? ' selected' : '';
			return `<option value="${escapeHtml(d.type)}"${sel}>${escapeHtml(l10n(d.labelKey))}</option>`;
		}).join('');
	}

	function moduleFieldsHtml(moduleIndex, def, config) {
		return def.fields.map((field) => {
			const id = `module-${moduleIndex}-${field.name}`;
			const current = (config && Object.prototype.hasOwnProperty.call(config, field.name))
				? config[field.name]
				: field.defaultValue;
			if (field.type === 'select') {
				const optsHtml = field.options.map((opt) => {
					const sel = String(current) === String(opt.value) ? ' selected' : '';
					return `<option value="${escapeHtml(opt.value)}"${sel}>${escapeHtml(l10n(opt.labelKey))}</option>`;
				}).join('');
				return `
					<div class="form-group admin-tariff-rules__module-field">
						<label for="${id}" class="form-label">${escapeHtml(l10n(field.labelKey))}</label>
						<select id="${id}" class="form-input" data-field-name="${escapeHtml(field.name)}">${optsHtml}</select>
					</div>`;
			}
			const minAttr = field.min !== undefined ? ` min="${escapeHtml(field.min)}"` : '';
			const maxAttr = field.max !== undefined ? ` max="${escapeHtml(field.max)}"` : '';
			const stepAttr = field.step !== undefined ? ` step="${escapeHtml(field.step)}"` : '';
			return `
				<div class="form-group admin-tariff-rules__module-field">
					<label for="${id}" class="form-label">${escapeHtml(l10n(field.labelKey))}</label>
					<input type="number" id="${id}" class="form-input" value="${escapeHtml(current ?? '')}"
						data-field-name="${escapeHtml(field.name)}"${minAttr}${maxAttr}${stepAttr}>
				</div>`;
		}).join('');
	}

	function moduleRowHtml(moduleIndex, type, config, readOnly) {
		const def = findModuleDefinition(type) || MODULE_DEFINITIONS[0];
		const removeBtn = readOnly ? '' : `<button type="button" class="azc-btn azc-btn--danger azc-btn--sm admin-tariff-rules__remove-module">${escapeHtml(l10n('remove'))}</button>`;
		const typeDisabled = readOnly ? ' disabled' : '';
		return `
			<fieldset class="admin-tariff-rules__module" data-module-index="${moduleIndex}">
				<legend>${escapeHtml(l10n('moduleType'))}</legend>
				<div class="form-group">
					<label class="azc-sr-only" for="module-type-${moduleIndex}">${escapeHtml(l10n('moduleType'))}</label>
					<select id="module-type-${moduleIndex}" class="form-input admin-tariff-rules__module-type"${typeDisabled}>
						${moduleTypeOptionsHtml(def.type)}
					</select>
				</div>
				<div class="admin-tariff-rules__module-fields">
					${moduleFieldsHtml(moduleIndex, def, config || {})}
				</div>
				${removeBtn}
			</fieldset>`;
	}

	function reindexModules(modulesHost) {
		modulesHost.querySelectorAll('.admin-tariff-rules__module').forEach((fs, idx) => {
			fs.setAttribute('data-module-index', String(idx));
			const typeSelect = fs.querySelector('.admin-tariff-rules__module-type');
			if (typeSelect) {
				typeSelect.id = `module-type-${idx}`;
			}
			fs.querySelectorAll('[data-field-name]').forEach((el) => {
				const name = el.getAttribute('data-field-name');
				if (name) {
					el.id = `module-${idx}-${name}`;
				}
			});
		});
	}

	function buildModalHtml(ruleSet, isEdit, readOnly) {
		const titleText = readOnly
			? l10n('viewTitle')
			: (isEdit ? l10n('editTitle') : l10n('createTitle'));
		const code = ruleSet ? ruleSet.tariffCode || '' : '';
		const version = ruleSet ? ruleSet.version || '' : '';
		const jurisdiction = ruleSet ? ruleSet.jurisdiction || '' : '';
		const validFrom = ruleSet ? ruleSet.validFrom || new Date().toISOString().slice(0, 10) : new Date().toISOString().slice(0, 10);
		const validTo = ruleSet ? ruleSet.validTo || '' : '';
		const activationMode = ruleSet ? ruleSet.activationMode || 'immediate' : 'immediate';
		const modules = (ruleSet && Array.isArray(ruleSet.modules) && ruleSet.modules.length)
			? ruleSet.modules
			: [{ moduleType: 'base_formula', config: {} }];
		const lockAttr = (isEdit || readOnly) ? ' disabled' : '';
		const moduleRowsHtml = modules.map((m, idx) => moduleRowHtml(idx, m.moduleType, m.config || {}, readOnly)).join('');
		const readOnlyAttr = readOnly ? ' aria-readonly="true"' : '';
		const readOnlyNote = readOnly
			? `<aside class="azc-callout azc-callout--info" role="note"><p class="azc-callout__text">${escapeHtml(l10n('readOnlyHelp'))}</p></aside>`
			: '';

		return `
			<div class="modal modal--lg admin-tariff-rules__modal" id="tariff-rules-modal" role="dialog" aria-modal="true" aria-labelledby="tariff-rules-modal-title"${readOnlyAttr}>
				<div class="modal-header">
					<h2 class="modal-title" id="tariff-rules-modal-title">${escapeHtml(titleText)}</h2>
					<button type="button" class="modal-close azc-btn azc-btn--ghost" data-dismiss="modal" aria-label="${escapeHtml(readOnly ? l10n('close') : l10n('cancel'))}">×</button>
				</div>
				<form id="tariff-rules-form" class="modal-body" novalidate>
					${readOnlyNote}
					<div class="form-grid admin-tariff-rules__form-grid">
						<div class="form-group">
							<label for="tariff-rules-code" class="form-label">${escapeHtml(l10n('tariffCode'))}</label>
							<input type="text" id="tariff-rules-code" class="form-input" required maxlength="64" value="${escapeHtml(code)}"${lockAttr} aria-describedby="tariff-rules-code-help">
							<p id="tariff-rules-code-help" class="form-help">${escapeHtml(l10n('tariffCodeHelp'))}</p>
						</div>
						<div class="form-group">
							<label for="tariff-rules-version" class="form-label">${escapeHtml(l10n('version'))}</label>
							<input type="text" id="tariff-rules-version" class="form-input" required maxlength="32" value="${escapeHtml(version)}"${lockAttr} aria-describedby="tariff-rules-version-help">
							<p id="tariff-rules-version-help" class="form-help">${escapeHtml(l10n('versionHelp'))}</p>
						</div>
						<div class="form-group">
							<label for="tariff-rules-jurisdiction" class="form-label">${escapeHtml(l10n('jurisdiction'))}</label>
							<input type="text" id="tariff-rules-jurisdiction" class="form-input" maxlength="64" value="${escapeHtml(jurisdiction)}"${readOnly ? ' disabled' : ''} aria-describedby="tariff-rules-jurisdiction-help">
							<p id="tariff-rules-jurisdiction-help" class="form-help">${escapeHtml(l10n('jurisdictionHelp'))}</p>
						</div>
						<div class="form-group">
							<label for="tariff-rules-valid-from" class="form-label">${escapeHtml(l10n('validFrom'))}</label>
							<input type="date" id="tariff-rules-valid-from" class="form-input" required value="${escapeHtml(validFrom)}"${readOnly ? ' disabled' : ''}>
						</div>
						<div class="form-group">
							<label for="tariff-rules-valid-to" class="form-label">${escapeHtml(l10n('validTo'))}</label>
							<input type="date" id="tariff-rules-valid-to" class="form-input" value="${escapeHtml(validTo)}"${readOnly ? ' disabled' : ''} aria-describedby="tariff-rules-valid-to-help">
							<p id="tariff-rules-valid-to-help" class="form-help">${escapeHtml(l10n('validToHelp'))}</p>
						</div>
						<div class="form-group">
							<label for="tariff-rules-activation" class="form-label">${escapeHtml(l10n('activationMode'))}</label>
							<select id="tariff-rules-activation" class="form-input"${readOnly ? ' disabled' : ''} aria-describedby="tariff-rules-activation-help">
								<option value="immediate"${activationMode === 'immediate' ? ' selected' : ''}>${escapeHtml(l10n('activationImmediate'))}</option>
								<option value="next_month"${activationMode === 'next_month' ? ' selected' : ''}>${escapeHtml(l10n('activationNextMonth'))}</option>
								<option value="next_year"${activationMode === 'next_year' ? ' selected' : ''}>${escapeHtml(l10n('activationNextYear'))}</option>
							</select>
							<p id="tariff-rules-activation-help" class="form-help">${escapeHtml(l10n('activationModeHelp'))}</p>
						</div>
					</div>
					<h3 class="admin-tariff-rules__section-title">${escapeHtml(l10n('modules'))}</h3>
					<p class="form-help">${escapeHtml(l10n('modulesHelp'))}</p>
					<div id="tariff-rules-modules">${moduleRowsHtml}</div>
					${readOnly ? '' : `<button type="button" id="tariff-rules-add-module" class="azc-btn azc-btn--secondary azc-btn--sm">${escapeHtml(l10n('addModule'))}</button>`}
					<div id="tariff-rules-form-error" class="azc-callout azc-callout--error admin-tariff-rules__form-error" role="alert" hidden></div>
				</form>
				<div class="modal-footer">
					<button type="button" class="azc-btn azc-btn--secondary" data-dismiss="modal">${escapeHtml(readOnly ? l10n('close') : l10n('cancel'))}</button>
					${readOnly ? '' : `<button type="button" class="azc-btn azc-btn--primary" id="tariff-rules-submit">${escapeHtml(l10n('save'))}</button>`}
				</div>
			</div>`;
	}

	function bindModuleHandlers(container, readOnly) {
		if (readOnly) {
			return;
		}
		const modulesHost = container.querySelector('#tariff-rules-modules') || document.getElementById('tariff-rules-modules');

		container.querySelectorAll('.admin-tariff-rules__module-type').forEach((select) => {
			select.addEventListener('change', (e) => {
				const newType = e.target.value;
				const fieldset = e.target.closest('.admin-tariff-rules__module');
				const idx = fieldset.getAttribute('data-module-index');
				const def = findModuleDefinition(newType);
				if (!def) {
					return;
				}
				const fieldsHost = fieldset.querySelector('.admin-tariff-rules__module-fields');
				if (fieldsHost) {
					fieldsHost.innerHTML = moduleFieldsHtml(idx, def, {});
				}
			});
		});

		container.querySelectorAll('.admin-tariff-rules__remove-module').forEach((btn) => {
			btn.addEventListener('click', (e) => {
				const fieldset = e.target.closest('.admin-tariff-rules__module');
				const type = fieldset.querySelector('.admin-tariff-rules__module-type')?.value;
				if (type === 'base_formula') {
					const baseCount = modulesHost.querySelectorAll('.admin-tariff-rules__module-type').length
						? [...modulesHost.querySelectorAll('.admin-tariff-rules__module-type')].filter((s) => s.value === 'base_formula').length
						: 0;
					if (baseCount <= 1) {
						showFormError(l10n('cannotRemoveBaseModule'));
						return;
					}
				}
				fieldset.remove();
				reindexModules(modulesHost);
				showFormError(null);
			});
		});
	}

	function readModuleValues(modulesHost) {
		const result = [];
		modulesHost.querySelectorAll('.admin-tariff-rules__module').forEach((fs) => {
			const typeSelect = fs.querySelector('.admin-tariff-rules__module-type');
			if (!typeSelect) {
				return;
			}
			const moduleType = typeSelect.value;
			const def = findModuleDefinition(moduleType);
			if (!def) {
				return;
			}
			const config = {};
			def.fields.forEach((field) => {
				const el = fs.querySelector(`[data-field-name="${field.name}"]`);
				if (!el) {
					return;
				}
				if (field.type === 'number') {
					const v = parseFloat(el.value);
					config[field.name] = Number.isFinite(v) ? v : field.defaultValue;
				} else {
					config[field.name] = String(el.value || field.defaultValue);
				}
			});
			result.push({ moduleType, config });
		});
		return result;
	}

	function showFormError(message) {
		const errBox = document.getElementById('tariff-rules-form-error');
		if (!errBox) {
			return;
		}
		if (!message) {
			errBox.textContent = '';
			errBox.hidden = true;
		} else {
			errBox.textContent = message;
			errBox.hidden = false;
			errBox.focus && errBox.focus();
		}
	}

	function validateForm(values) {
		if (!values.tariffCode) {
			return l10n('tariffCodeRequired');
		}
		if (!values.version) {
			return l10n('versionRequired');
		}
		if (!values.validFrom) {
			return l10n('validFromRequired');
		}
		if (values.validTo && values.validTo < values.validFrom) {
			return l10n('validityInvalid');
		}
		const modules = values.modules || [];
		if (!modules.length) {
			return l10n('modulesRequired');
		}
		const base = modules.find((m) => m.moduleType === 'base_formula');
		if (!base) {
			return l10n('baseFormulaRequired');
		}
		const c = base.config || {};
		if (!Number.isFinite(c.reference_days) || !Number.isFinite(c.reference_week_days)) {
			return l10n('baseFormulaRequired');
		}
		return null;
	}

	function collectFormValues(modal, isEdit, existing) {
		const tariffCode = isEdit ? existing.tariffCode : modal.querySelector('#tariff-rules-code').value.trim();
		const version = isEdit ? existing.version : modal.querySelector('#tariff-rules-version').value.trim();
		const jurisdiction = modal.querySelector('#tariff-rules-jurisdiction').value.trim();
		const validFrom = modal.querySelector('#tariff-rules-valid-from').value;
		const validTo = modal.querySelector('#tariff-rules-valid-to').value;
		const activationMode = modal.querySelector('#tariff-rules-activation').value;
		const modules = readModuleValues(modal.querySelector('#tariff-rules-modules'));
		return { tariffCode, version, jurisdiction, validFrom, validTo, activationMode, modules };
	}

	function openModal(ruleSet, isEdit, readOnly) {
		const backdrop = document.createElement('div');
		backdrop.className = 'modal-backdrop azc-modal-backdrop';
		backdrop.setAttribute('role', 'presentation');
		backdrop.innerHTML = buildModalHtml(ruleSet, isEdit, readOnly);
		document.body.appendChild(backdrop);

		const modal = backdrop.querySelector('#tariff-rules-modal');
		if (Components.openModal) {
			Components.openModal(modal);
		} else {
			backdrop.style.display = 'flex';
			modal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
		}

		const closeModal = () => {
			if (Components.closeModal) {
				Components.closeModal(modal);
			} else {
				backdrop.remove();
				document.body.style.overflow = '';
			}
		};

		modal.querySelectorAll('[data-dismiss="modal"]').forEach((btn) => {
			btn.addEventListener('click', closeModal);
		});
		backdrop.addEventListener('click', (e) => {
			if (e.target === backdrop) {
				closeModal();
			}
		});
		const escListener = (e) => {
			if (e.key === 'Escape') {
				document.removeEventListener('keydown', escListener);
				closeModal();
			}
		};
		document.addEventListener('keydown', escListener);

		const modulesHost = modal.querySelector('#tariff-rules-modules');
		bindModuleHandlers(modal, readOnly);

		if (!readOnly) {
			const addBtn = modal.querySelector('#tariff-rules-add-module');
			if (addBtn) {
				addBtn.addEventListener('click', () => {
					const nextIndex = modulesHost.children.length;
					const wrapper = document.createElement('div');
					wrapper.innerHTML = moduleRowHtml(nextIndex, 'additional_entitlements', {}, false);
					const fieldset = wrapper.firstElementChild;
					modulesHost.appendChild(fieldset);
					bindModuleHandlers(fieldset, false);
				});
			}

			const submitBtn = modal.querySelector('#tariff-rules-submit');
			if (submitBtn) {
				submitBtn.addEventListener('click', async () => {
					showFormError(null);
					const values = collectFormValues(modal, isEdit, ruleSet || {});
					const err = validateForm(values);
					if (err) {
						showFormError(err);
						return;
					}
					submitBtn.setAttribute('aria-busy', 'true');
					submitBtn.disabled = true;
					try {
						let r;
						if (isEdit) {
							r = await apiFetch(`${API_LIST}/${encodeURIComponent(ruleSet.id)}`, { method: 'PUT', json: values });
						} else {
							r = await apiFetch(API_LIST, { method: 'POST', json: values });
						}
						if (r.ok && r.payload && r.payload.success) {
							const msg = isEdit ? l10n('updated') : l10n('created');
							Messaging.showSuccess && Messaging.showSuccess(msg);
							announce(msg);
							closeModal();
							loadRuleSets();
						} else {
							let errMsg = (r.payload && r.payload.error) || (isEdit ? l10n('updateError') : l10n('createError'));
							if (r.payload && r.payload.errors && typeof r.payload.errors === 'object') {
								const parts = Object.values(r.payload.errors).filter(Boolean);
								if (parts.length) {
									errMsg = parts.join(' ');
								}
							}
							showFormError(errMsg);
						}
					} catch (_) {
						showFormError(isEdit ? l10n('updateError') : l10n('createError'));
					} finally {
						submitBtn.removeAttribute('aria-busy');
						submitBtn.disabled = false;
					}
				});
			}
		}

		setTimeout(() => {
			const first = modal.querySelector('input:not([disabled]), select:not([disabled]), button[data-dismiss="modal"]');
			if (first) {
				first.focus();
			}
		}, 50);
	}

	async function openEditModal(id) {
		const { ok, payload } = await apiFetch(`${API_LIST}/${encodeURIComponent(id)}`, { method: 'GET' });
		if (!ok || !payload || !payload.success) {
			Messaging.showError && Messaging.showError((payload && payload.error) || l10n('loadingError'));
			return;
		}
		if (payload.ruleSet && payload.ruleSet.status !== 'draft') {
			openModal(payload.ruleSet, false, true);
			return;
		}
		openModal(payload.ruleSet, true, false);
	}

	async function openViewModal(id) {
		const { ok, payload } = await apiFetch(`${API_LIST}/${encodeURIComponent(id)}`, { method: 'GET' });
		if (!ok || !payload || !payload.success) {
			Messaging.showError && Messaging.showError((payload && payload.error) || l10n('loadingError'));
			return;
		}
		openModal(payload.ruleSet, false, true);
	}

	function init() {
		const createBtn = document.getElementById('tariff-rules-create');
		if (createBtn) {
			createBtn.addEventListener('click', () => openModal(null, false, false));
		}
		const refreshBtn = document.getElementById('tariff-rules-refresh');
		if (refreshBtn) {
			refreshBtn.addEventListener('click', () => loadRuleSets());
		}
		if (Components.relocatePageActions) {
			Components.relocatePageActions();
		}
		loadRuleSets();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
