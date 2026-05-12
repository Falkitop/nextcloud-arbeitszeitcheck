/**
 * Admin · Vacation entitlement layers (L0 / L1 / L2 / simulator).
 *
 * UX:
 *  - Pure vanilla JS, mirrors the patterns used by admin-teams.js so QA &
 *    accessibility behaviour stay consistent across the admin area.
 *  - All form fields are labelled (htmlFor) and feedback uses an aria-live
 *    region (`#vacation-layers-status`).
 *  - The dialog is a native <dialog> with backdrop; focus is trapped by the
 *    browser; ESC closes; SUBMIT triggers save.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

(function () {
  'use strict';

  const Utils = window.ArbeitszeitCheckUtils || {};
  const Messaging = window.ArbeitszeitCheckMessaging || {};
  const baseUrl = '/apps/arbeitszeitcheck';

  // -----------------------------------------------------------------------
  // Bootstrap (URLs etc.) shipped from the server-rendered template.
  // -----------------------------------------------------------------------
  const bootstrapEl = document.getElementById('vacation-layers-bootstrap');
  if (!bootstrapEl) {
    return;
  }
  let bootstrap;
  try {
    bootstrap = JSON.parse(bootstrapEl.textContent || '{}');
  } catch (e) {
    bootstrap = {};
  }
  const URLS = bootstrap.urls || {};

  // -----------------------------------------------------------------------
  // i18n helper
  // -----------------------------------------------------------------------
  function t(key, fallback) {
    if (typeof window.t === 'function') {
      return window.t('arbeitszeitcheck', key);
    }
    return fallback || key;
  }

  function announce(msg) {
    const node = document.getElementById('vacation-layers-status');
    if (node) {
      node.textContent = '';
      window.setTimeout(() => { node.textContent = msg; }, 30);
    }
  }

  function notifyError(message) {
    if (Messaging && typeof Messaging.showError === 'function') {
      Messaging.showError(message);
    }
    announce(message);
  }

  function notifySuccess(message) {
    if (Messaging && typeof Messaging.showSuccess === 'function') {
      Messaging.showSuccess(message);
    }
    announce(message);
  }

  // -----------------------------------------------------------------------
  // State
  // -----------------------------------------------------------------------
  const state = {
    org: { active: null, history: [] },
    model: { defaults: [], availableModels: [] },
    team: { policies: [], availableTeams: [] },
    ruleSets: [],
  };

  // -----------------------------------------------------------------------
  // Formatting helpers
  // -----------------------------------------------------------------------
  function fmtDate(iso) {
    if (!iso) return '—';
    try {
      const d = new Date(iso + (iso.length === 10 ? 'T00:00:00' : ''));
      if (Number.isNaN(d.getTime())) return iso;
      return d.toLocaleDateString();
    } catch (e) {
      return iso;
    }
  }

  function fmtDays(value) {
    if (value === null || value === undefined || value === '') return '—';
    const num = Number(value);
    if (!Number.isFinite(num)) return '—';
    return num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function fmtMode(mode) {
    const map = {
      manual_fixed: t('Manual (fixed days)', 'Manual (fixed days)'),
      model_based_simple: t('Working time model (30 × work-days/5)', 'Working time model (30 × work-days/5)'),
      tariff_rule_based: t('Tariff / collective agreement', 'Tariff / collective agreement'),
      manual_exception: t('Manual exception (L3)', 'Manual exception (L3)'),
      inherit: t('Inherit lower layers (L3)', 'Inherit lower layers (L3)'),
    };
    return map[mode] || mode || '—';
  }

  function fmtRuleSet(id) {
    if (!id) return '—';
    const rs = state.ruleSets.find((r) => Number(r.id) === Number(id));
    if (!rs) return `#${id}`;
    return `${rs.tariffCode || rs.id} (v${rs.version})`;
  }

  function fmtEffective(from, to) {
    const f = fmtDate(from);
    const tEnd = to ? fmtDate(to) : t('today', 'today');
    return `${f} → ${tEnd}`;
  }

  function escape(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
  }

  function setHtml(el, html) {
    if (el) el.innerHTML = html;
  }

  function getTeamLabel(id) {
    const team = state.team.availableTeams.find((tm) => Number(tm.id) === Number(id));
    return team ? team.name : `#${id}`;
  }

  function getModelLabel(id) {
    const model = state.model.availableModels.find((m) => Number(m.id) === Number(id));
    return model ? model.name : `#${id}`;
  }

  // -----------------------------------------------------------------------
  // Data load
  // -----------------------------------------------------------------------
  function loadOverview() {
    Utils.ajax(URLS.overview, {
      method: 'GET',
      onSuccess: (data) => {
        if (!data || data.success !== true) {
          notifyError(t('Could not load vacation entitlement layers', 'Could not load vacation entitlement layers'));
          return;
        }
        state.org = data.org || { active: null, history: [] };
        state.model = data.model || { defaults: [], availableModels: [] };
        state.team = data.team || { policies: [], availableTeams: [] };
        state.ruleSets = Array.isArray(data.ruleSets) ? data.ruleSets : [];
        renderAll();
      },
      onError: (err) => {
        notifyError((err && err.error) || t('Could not load vacation entitlement layers', 'Could not load vacation entitlement layers'));
      },
    });
  }

  // -----------------------------------------------------------------------
  // Renderers
  // -----------------------------------------------------------------------
  function renderAll() {
    renderL0();
    renderL1();
    renderL2();
  }

  function renderL0() {
    const active = state.org.active;
    const activeEl = document.getElementById('l0-active');
    if (activeEl) {
      if (!active) {
        activeEl.classList.add('layer-card__active--empty');
        setHtml(activeEl, `<p>${escape(t('No organisation default configured. The legacy fallback of 25 days/year applies until you add a default.', 'No organisation default configured. The legacy fallback of 25 days/year applies until you add a default.'))}</p>`);
      } else {
        activeEl.classList.remove('layer-card__active--empty');
        setHtml(activeEl, renderActiveRow(active, [
          { label: t('Mode', 'Mode'), value: fmtMode(active.vacationMode) },
          { label: t('Days', 'Days'), value: active.manualDays != null ? fmtDays(active.manualDays) : '—' },
          { label: t('Tariff rule set', 'Tariff rule set'), value: fmtRuleSet(active.tariffRuleSetId) },
          { label: t('Effective', 'Effective'), value: fmtEffective(active.effectiveFrom, active.effectiveTo) },
        ]));
      }
    }
    const tbody = document.getElementById('l0-history-rows');
    if (!tbody) return;
    if (state.org.history.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="layer-card__placeholder">${escape(t('No entries yet.', 'No entries yet.'))}</td></tr>`;
      return;
    }
    tbody.innerHTML = state.org.history.map((row) => `
      <tr>
        <td data-label="${escape(t('Effective', 'Effective'))}">${escape(fmtEffective(row.effectiveFrom, row.effectiveTo))}</td>
        <td data-label="${escape(t('Mode', 'Mode'))}">${escape(fmtMode(row.vacationMode))}</td>
        <td data-label="${escape(t('Days', 'Days'))}">${escape(fmtDays(row.manualDays))}</td>
        <td data-label="${escape(t('Tariff rule set', 'Tariff rule set'))}">${escape(fmtRuleSet(row.tariffRuleSetId))}</td>
        <td data-label="${escape(t('Description', 'Description'))}">${escape(row.description || '')}</td>
        <td data-label="${escape(t('Actions', 'Actions'))}" class="layer-card__history-actions">
          <button type="button" class="btn btn--small btn--danger" data-delete-org="${row.id}" aria-label="${escape(t('Delete organisation default', 'Delete organisation default'))} #${row.id}">${escape(t('Delete', 'Delete'))}</button>
        </td>
      </tr>
    `).join('');
  }

  function renderL1() {
    const tbody = document.getElementById('l1-rows');
    if (!tbody) return;
    if (state.model.defaults.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" class="layer-card__placeholder">${escape(t('No model defaults yet — employees fall through to L0.', 'No model defaults yet — employees fall through to L0.'))}</td></tr>`;
      return;
    }
    tbody.innerHTML = state.model.defaults.map((row) => `
      <tr>
        <td data-label="${escape(t('Model', 'Model'))}">${escape(getModelLabel(row.workingTimeModelId))}</td>
        <td data-label="${escape(t('Effective', 'Effective'))}">${escape(fmtEffective(row.effectiveFrom, row.effectiveTo))}</td>
        <td data-label="${escape(t('Mode', 'Mode'))}">${escape(fmtMode(row.vacationMode))}</td>
        <td data-label="${escape(t('Days', 'Days'))}">${escape(fmtDays(row.manualDays))}</td>
        <td data-label="${escape(t('Tariff rule set', 'Tariff rule set'))}">${escape(fmtRuleSet(row.tariffRuleSetId))}</td>
        <td data-label="${escape(t('Description', 'Description'))}">${escape(row.description || '')}</td>
        <td data-label="${escape(t('Actions', 'Actions'))}" class="layer-card__history-actions">
          <button type="button" class="btn btn--small btn--danger" data-delete-model="${row.id}" aria-label="${escape(t('Delete model default', 'Delete model default'))} #${row.id}">${escape(t('Delete', 'Delete'))}</button>
        </td>
      </tr>
    `).join('');
  }

  function renderL2() {
    const tbody = document.getElementById('l2-rows');
    if (!tbody) return;
    if (state.team.policies.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" class="layer-card__placeholder">${escape(t('No team policies yet — teams inherit from L1 or L0.', 'No team policies yet — teams inherit from L1 or L0.'))}</td></tr>`;
      return;
    }
    tbody.innerHTML = state.team.policies.map((row) => `
      <tr>
        <td data-label="${escape(t('Team', 'Team'))}">${escape(getTeamLabel(row.teamId))}</td>
        <td data-label="${escape(t('Effective', 'Effective'))}">${escape(fmtEffective(row.effectiveFrom, row.effectiveTo))}</td>
        <td data-label="${escape(t('Mode', 'Mode'))}">${escape(fmtMode(row.vacationMode))}</td>
        <td data-label="${escape(t('Days', 'Days'))}">${escape(fmtDays(row.manualDays))}</td>
        <td data-label="${escape(t('Priority', 'Priority'))}">${escape(String(row.priority ?? 0))}</td>
        <td data-label="${escape(t('Description', 'Description'))}">${escape(row.description || '')}</td>
        <td data-label="${escape(t('Actions', 'Actions'))}" class="layer-card__history-actions">
          <button type="button" class="btn btn--small btn--danger" data-delete-team="${row.id}" aria-label="${escape(t('Delete team policy', 'Delete team policy'))} #${row.id}">${escape(t('Delete', 'Delete'))}</button>
        </td>
      </tr>
    `).join('');
  }

  function renderActiveRow(row, fields) {
    const parts = fields.map((f) => `
      <div>
        <dt>${escape(f.label)}</dt>
        <dd>${escape(f.value)}</dd>
      </div>
    `).join('');
    const desc = row && row.description ? `<p class="form-help">${escape(row.description)}</p>` : '';
    return `<dl class="layer-card__active-row">${parts}</dl>${desc}`;
  }

  // -----------------------------------------------------------------------
  // Dialog (form drawer)
  // -----------------------------------------------------------------------
  const dialog = document.getElementById('layer-dialog');
  const dialogTitle = document.getElementById('layer-dialog-title');
  const dialogIntro = document.getElementById('layer-dialog-intro');
  const dialogBody = document.getElementById('layer-dialog-body');
  const dialogFeedback = document.getElementById('layer-dialog-feedback');
  const dialogForm = document.getElementById('layer-dialog-form');
  let dialogContext = null; // { layer: 'org'|'model'|'team' }

  function openDialog(context) {
    dialogContext = context;
    dialogTitle.textContent = context.title;
    dialogIntro.textContent = context.intro || '';
    dialogBody.innerHTML = context.body;
    dialogFeedback.textContent = '';
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      dialog.setAttribute('open', 'open');
    }
    const first = dialog.querySelector('input, select, textarea');
    if (first) first.focus();
  }

  function closeDialog() {
    if (typeof dialog.close === 'function') {
      try { dialog.close(); } catch (e) { /* noop */ }
    }
    dialog.removeAttribute('open');
    dialogContext = null;
  }

  document.getElementById('layer-dialog-cancel').addEventListener('click', closeDialog);

  // Shared form fragments
  function modeFieldHtml(allowInherit) {
    return `
      <div class="layer-form__row">
        <label for="dlg-mode" class="form-label">${escape(t('Mode', 'Mode'))}</label>
        <select id="dlg-mode" name="vacationMode" class="form-select" required>
          <option value="manual_fixed">${escape(fmtMode('manual_fixed'))}</option>
          <option value="model_based_simple">${escape(fmtMode('model_based_simple'))}</option>
          <option value="tariff_rule_based">${escape(fmtMode('tariff_rule_based'))}</option>
          ${allowInherit ? `<option value="inherit">${escape(fmtMode('inherit'))}</option>` : ''}
        </select>
      </div>
    `;
  }

  function daysFieldHtml() {
    return `
      <div class="layer-form__row">
        <label for="dlg-days" class="form-label">${escape(t('Manual days (per year)', 'Manual days (per year)'))}</label>
        <input type="number" id="dlg-days" name="manualDays" class="form-input"
               min="0" max="366" step="0.5"
               aria-describedby="dlg-days-help">
        <p id="dlg-days-help" class="form-help">${escape(t('Required when mode is "Manual (fixed days)". Allowed range: 0–366, in steps of 0.5.', 'Required when mode is "Manual (fixed days)". Allowed range: 0–366, in steps of 0.5.'))}</p>
      </div>
    `;
  }

  function ruleSetFieldHtml() {
    const opts = ['<option value="">— ' + escape(t('None', 'None')) + ' —</option>']
      .concat(state.ruleSets.map((r) => `<option value="${r.id}">${escape(r.tariffCode || r.id)} (v${escape(r.version)} · ${escape(r.status)})</option>`))
      .join('');
    return `
      <div class="layer-form__row">
        <label for="dlg-ruleset" class="form-label">${escape(t('Tariff rule set', 'Tariff rule set'))}</label>
        <select id="dlg-ruleset" name="tariffRuleSetId" class="form-select" aria-describedby="dlg-ruleset-help">${opts}</select>
        <p id="dlg-ruleset-help" class="form-help">${escape(t('Required when mode is "Tariff / collective agreement".', 'Required when mode is "Tariff / collective agreement".'))}</p>
      </div>
    `;
  }

  function descriptionFieldHtml() {
    return `
      <div class="layer-form__row layer-form__row--wide">
        <label for="dlg-desc" class="form-label">${escape(t('Description', 'Description'))} <span class="form-help">(${escape(t('optional', 'optional'))})</span></label>
        <textarea id="dlg-desc" name="description" class="form-textarea" rows="2"
                  maxlength="1000" aria-describedby="dlg-desc-help"></textarea>
        <p id="dlg-desc-help" class="form-help">${escape(t('Visible to administrators only.', 'Visible to administrators only.'))}</p>
      </div>
    `;
  }

  function effectiveFieldsHtml() {
    const today = new Date().toISOString().slice(0, 10);
    return `
      <div class="layer-form__row">
        <label for="dlg-from" class="form-label">${escape(t('Effective from', 'Effective from'))}</label>
        <input type="date" id="dlg-from" name="effectiveFrom" class="form-input" value="${today}" required>
      </div>
      <div class="layer-form__row">
        <label for="dlg-to" class="form-label">${escape(t('Effective to', 'Effective to'))} <span class="form-help">(${escape(t('optional', 'optional'))})</span></label>
        <input type="date" id="dlg-to" name="effectiveTo" class="form-input">
        <p class="form-help">${escape(t('Leave empty for "until further notice". You can always supersede a row by adding a newer one.', 'Leave empty for "until further notice". You can always supersede a row by adding a newer one.'))}</p>
      </div>
    `;
  }

  function openOrgDialog() {
    openDialog({
      layer: 'org',
      title: t('Add organisation default', 'Add organisation default'),
      intro: t('This default applies to every employee who has no individual rule, no team policy, and no model default for the chosen date range.', 'This default applies to every employee who has no individual rule, no team policy, and no model default for the chosen date range.'),
      body: `
        ${modeFieldHtml(false)}
        ${daysFieldHtml()}
        ${ruleSetFieldHtml()}
        ${effectiveFieldsHtml()}
        ${descriptionFieldHtml()}
      `,
    });
  }

  function openModelDialog() {
    const opts = state.model.availableModels.map((m) => `<option value="${m.id}">${escape(m.name)}</option>`).join('');
    openDialog({
      layer: 'model',
      title: t('Add working time model default', 'Add working time model default'),
      intro: t('Attach a default entitlement to a working time model. Applies to every employee currently assigned to that model who has neither an individual rule nor a team policy.', 'Attach a default entitlement to a working time model. Applies to every employee currently assigned to that model who has neither an individual rule nor a team policy.'),
      body: `
        <div class="layer-form__row layer-form__row--wide">
          <label for="dlg-model" class="form-label">${escape(t('Working time model', 'Working time model'))}</label>
          <select id="dlg-model" name="workingTimeModelId" class="form-select" required>${opts}</select>
        </div>
        ${modeFieldHtml(false)}
        ${daysFieldHtml()}
        ${ruleSetFieldHtml()}
        ${effectiveFieldsHtml()}
        ${descriptionFieldHtml()}
      `,
    });
  }

  function openTeamDialog() {
    const opts = state.team.availableTeams.map((tm) => `<option value="${tm.id}">${escape(tm.name)}</option>`).join('');
    openDialog({
      layer: 'team',
      title: t('Add team / cohort policy', 'Add team / cohort policy'),
      intro: t('Attach a policy to a team. When an employee belongs to several teams, the deepest team wins; ties are broken by the higher priority, then by the smallest team ID.', 'Attach a policy to a team. When an employee belongs to several teams, the deepest team wins; ties are broken by the higher priority, then by the smallest team ID.'),
      body: `
        <div class="layer-form__row layer-form__row--wide">
          <label for="dlg-team" class="form-label">${escape(t('Team', 'Team'))}</label>
          <select id="dlg-team" name="teamId" class="form-select" required>${opts}</select>
        </div>
        ${modeFieldHtml(false)}
        ${daysFieldHtml()}
        ${ruleSetFieldHtml()}
        <div class="layer-form__row">
          <label for="dlg-priority" class="form-label">${escape(t('Priority', 'Priority'))}</label>
          <input type="number" id="dlg-priority" name="priority" class="form-input" value="0" min="-1000" max="1000" step="1" aria-describedby="dlg-priority-help">
          <p id="dlg-priority-help" class="form-help">${escape(t('Higher priority wins inside the same team depth. Use small integers (e.g. 0–100).', 'Higher priority wins inside the same team depth. Use small integers (e.g. 0–100).'))}</p>
        </div>
        ${effectiveFieldsHtml()}
        ${descriptionFieldHtml()}
      `,
    });
  }

  // -----------------------------------------------------------------------
  // Save handlers
  // -----------------------------------------------------------------------
  function readForm() {
    const formData = new FormData(dialogForm);
    const obj = {};
    formData.forEach((value, key) => {
      const trimmed = typeof value === 'string' ? value.trim() : value;
      obj[key] = trimmed === '' ? null : trimmed;
    });
    if (obj.manualDays != null) obj.manualDays = String(obj.manualDays).replace(',', '.');
    if (obj.priority != null) obj.priority = parseInt(String(obj.priority), 10);
    return obj;
  }

  function showFieldErrors(errors) {
    if (!errors) return;
    Object.keys(errors).forEach((field) => {
      const map = {
        vacationMode: 'dlg-mode',
        manualDays: 'dlg-days',
        tariffRuleSetId: 'dlg-ruleset',
        effectiveFrom: 'dlg-from',
        effectiveTo: 'dlg-to',
        workingTimeModelId: 'dlg-model',
        teamId: 'dlg-team',
        priority: 'dlg-priority',
      };
      const id = map[field];
      if (!id) return;
      const el = document.getElementById(id);
      if (el) {
        el.setAttribute('aria-invalid', 'true');
      }
    });
  }

  function clearFieldErrors() {
    dialogBody.querySelectorAll('[aria-invalid="true"]').forEach((el) => el.removeAttribute('aria-invalid'));
  }

  dialogForm.addEventListener('submit', (ev) => {
    ev.preventDefault();
    if (!dialogContext) return;
    const payload = readForm();
    clearFieldErrors();
    dialogFeedback.textContent = '';
    let endpoint;
    if (dialogContext.layer === 'org') endpoint = URLS.org;
    else if (dialogContext.layer === 'model') endpoint = URLS.model;
    else if (dialogContext.layer === 'team') endpoint = URLS.team;
    else return;

    Utils.ajax(endpoint, {
      method: 'POST',
      data: payload,
      onSuccess: () => {
        notifySuccess(t('Saved.', 'Saved.'));
        closeDialog();
        loadOverview();
      },
      onError: (err) => {
        const msg = (err && err.error) || t('Could not save', 'Could not save');
        dialogFeedback.textContent = msg;
        if (err && err.data && err.data.errors) {
          showFieldErrors(err.data.errors);
        }
      },
    });
  });

  // -----------------------------------------------------------------------
  // Action wiring
  // -----------------------------------------------------------------------
  document.addEventListener('click', (ev) => {
    const target = ev.target;
    if (!(target instanceof HTMLElement)) return;
    const action = target.getAttribute('data-action');
    if (action === 'add-org') { ev.preventDefault(); openOrgDialog(); return; }
    if (action === 'add-model') { ev.preventDefault(); openModelDialog(); return; }
    if (action === 'add-team') { ev.preventDefault(); openTeamDialog(); return; }
    const delOrg = target.getAttribute('data-delete-org');
    if (delOrg) { ev.preventDefault(); confirmDelete(t('Delete organisation default?', 'Delete organisation default?'), () => doDelete(URLS.orgDelete, delOrg)); return; }
    const delModel = target.getAttribute('data-delete-model');
    if (delModel) { ev.preventDefault(); confirmDelete(t('Delete model default?', 'Delete model default?'), () => doDelete(URLS.modelDelete, delModel)); return; }
    const delTeam = target.getAttribute('data-delete-team');
    if (delTeam) { ev.preventDefault(); confirmDelete(t('Delete team policy?', 'Delete team policy?'), () => doDelete(URLS.teamDelete, delTeam)); return; }
  });

  function confirmDelete(message, onConfirm) {
    if (typeof OC !== 'undefined' && OC.dialogs && typeof OC.dialogs.confirmDestructive === 'function') {
      let result;
      try {
        result = OC.dialogs.confirmDestructive(message, t('Confirm deletion', 'Confirm deletion'), {
          type: OC.dialogs.YES_NO_BUTTONS,
          confirm: t('Delete', 'Delete'),
          cancel: t('Cancel', 'Cancel'),
        }, (confirmed) => { if (confirmed) onConfirm(); });
      } catch (e) {
        result = undefined;
      }
      if (result && typeof result.then === 'function') {
        result.then((confirmed) => { if (confirmed) onConfirm(); });
      }
    } else if (window.confirm(message)) {
      onConfirm();
    }
  }

  function doDelete(template, id) {
    const url = String(template).replace(/\/0(\?|$)/, `/${encodeURIComponent(String(id))}$1`);
    Utils.ajax(url, {
      method: 'DELETE',
      onSuccess: () => {
        notifySuccess(t('Deleted.', 'Deleted.'));
        loadOverview();
      },
      onError: (err) => {
        notifyError((err && err.error) || t('Could not delete', 'Could not delete'));
      },
    });
  }

  // -----------------------------------------------------------------------
  // Simulator
  // -----------------------------------------------------------------------
  const simForm = document.getElementById('sim-form');
  const simUser = document.getElementById('sim-user');
  const simSuggest = document.getElementById('sim-user-suggest');
  const simResult = document.getElementById('sim-result');
  let simSuggestTimer = null;
  let simSelectedUserId = null;

  if (simUser && simSuggest) {
    simUser.addEventListener('input', () => {
      window.clearTimeout(simSuggestTimer);
      const term = simUser.value.trim();
      simSelectedUserId = null;
      if (term.length < 2) {
        simSuggest.hidden = true;
        simSuggest.innerHTML = '';
        return;
      }
      simSuggestTimer = window.setTimeout(() => fetchSuggestions(term), 200);
    });
    simUser.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape') {
        simSuggest.hidden = true;
        simSuggest.innerHTML = '';
      }
    });
    simSuggest.addEventListener('click', (ev) => {
      const li = ev.target.closest('li[data-uid]');
      if (!li) return;
      simSelectedUserId = li.getAttribute('data-uid');
      simUser.value = li.getAttribute('data-display') || simSelectedUserId;
      simSuggest.hidden = true;
      simSuggest.innerHTML = '';
    });
  }

  function fetchSuggestions(term) {
    const url = URLS.userSearch + '?search=' + encodeURIComponent(term) + '&limit=10';
    Utils.ajax(url, {
      method: 'GET',
      onSuccess: (data) => {
        const list = (data && (data.users || data.data || [])).slice(0, 10);
        if (!Array.isArray(list) || list.length === 0) {
          simSuggest.hidden = true;
          simSuggest.innerHTML = '';
          return;
        }
        simSuggest.innerHTML = list.map((u) => {
          const uid = u.userId || u.uid || u.id || '';
          const display = u.displayName || u.display_name || uid;
          return `<li role="option" data-uid="${escape(uid)}" data-display="${escape(display)}">${escape(display)} <span class="form-help">${escape(uid)}</span></li>`;
        }).join('');
        simSuggest.hidden = false;
      },
      onError: () => {
        simSuggest.hidden = true;
        simSuggest.innerHTML = '';
      },
    });
  }

  if (simForm) {
    simForm.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const userId = simSelectedUserId || (simUser ? simUser.value.trim() : '');
      const asOfDate = document.getElementById('sim-date').value || new Date().toISOString().slice(0, 10);
      if (!userId) {
        notifyError(t('Please pick an employee first.', 'Please pick an employee first.'));
        return;
      }
      Utils.ajax(URLS.simulate, {
        method: 'POST',
        data: { userId, asOfDate },
        onSuccess: (data) => renderSimResult(data),
        onError: (err) => {
          simResult.innerHTML = `<p class="layer-card__placeholder">${escape((err && err.error) || t('Could not run simulation', 'Could not run simulation'))}</p>`;
        },
      });
    });
  }

  function renderSimResult(data) {
    if (!data || data.success !== true) {
      simResult.innerHTML = `<p class="layer-card__placeholder">${escape(t('Could not run simulation', 'Could not run simulation'))}</p>`;
      return;
    }
    const trace = data.calculationTrace || {};
    const layers = Array.isArray(trace.layers_evaluated) ? trace.layers_evaluated : [];
    const winnerLabel = (trace.winner && trace.winner.matched_layer) || data.source || '—';
    const rows = layers.map((layer) => {
      const matched = layer.matched === true;
      return `
        <tr data-matched="${matched ? 'true' : 'false'}">
          <td>${escape(layer.layer || '')}</td>
          <td>${matched ? escape(t('Match', 'Match')) : escape(t('Skipped', 'Skipped'))}</td>
          <td>${escape(layer.reason || layer.mode || '')}</td>
          <td>${escape(layer.days != null ? fmtDays(layer.days) : '—')}</td>
        </tr>
      `;
    }).join('');
    simResult.innerHTML = `
      <div class="layer-sim__card">
        <h3 class="layer-sim__headline">${escape(t('Result', 'Result'))}</h3>
        <p class="layer-sim__sub">${escape(t('On', 'On'))} ${escape(fmtDate(data.asOfDate))} — ${escape(t('layer', 'layer'))}: <strong>${escape(winnerLabel)}</strong></p>
        <p><span class="layer-sim__days">${escape(fmtDays(data.effectiveEntitlementDays))}</span> <span aria-hidden="true">${escape(t('days', 'days'))}</span><span class="visually-hidden">${escape(t('vacation days per year', 'vacation days per year'))}</span></p>
        <table class="trace-table">
          <thead>
            <tr>
              <th scope="col">${escape(t('Layer', 'Layer'))}</th>
              <th scope="col">${escape(t('Outcome', 'Outcome'))}</th>
              <th scope="col">${escape(t('Reason / Mode', 'Reason / Mode'))}</th>
              <th scope="col">${escape(t('Days', 'Days'))}</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    `;
    announce(t('Simulation finished.', 'Simulation finished.'));
  }

  // -----------------------------------------------------------------------
  // Init
  // -----------------------------------------------------------------------
  loadOverview();
})();
