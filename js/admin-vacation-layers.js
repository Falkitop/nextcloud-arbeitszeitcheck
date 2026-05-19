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
  const Components = window.ArbeitszeitCheckComponents || {};
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
  function timeApi() {
    return window.ArbeitszeitCheckTime || null;
  }

  function todayYmd() {
    const api = timeApi();
    return api ? api.todayYmd() : new Date().toISOString().slice(0, 10);
  }

  function fmtDate(iso) {
    if (!iso) return '—';
    const api = timeApi();
    if (api) {
      if (String(iso).length === 10) {
        const parsed = api.parseYmd(String(iso).slice(0, 10));
        return parsed ? api.formatDate(parsed) : iso;
      }
      return api.formatDate(iso) || iso;
    }
    return String(iso);
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

  /** Localised tariff rule set lifecycle label (API may send statusLabel; else map known codes). */
  function fmtTariffStatusCode(status) {
    const s = String(status || '').toLowerCase();
    const map = {
      draft: t('Draft', 'Draft'),
      active: t('Active', 'Active'),
      retired: t('Retired', 'Retired'),
    };
    if (map[s]) return map[s];
    const raw = String(status || '').trim();
    return raw || '—';
  }

  function fmtTariffRuleSetStatus(rs) {
    if (!rs) return '—';
    if (rs.statusLabel) return String(rs.statusLabel);
    return fmtTariffStatusCode(rs.status);
  }

  function fmtRuleSet(id) {
    if (!id) return '—';
    const rs = state.ruleSets.find((r) => Number(r.id) === Number(id));
    if (!rs) return `#${id}`;
    const statusTxt = fmtTariffRuleSetStatus(rs);
    return `${rs.tariffCode || rs.id} (v${rs.version} · ${statusTxt})`;
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
    renderHypotheticalTeamPicker();
  }

  /**
   * REQ-ENT-10: detect locally whether multiple L0 rows are active on
   * "today" using the history we already loaded, so we can surface a
   * warning banner in the admin UI before someone clicks Save. The engine
   * still fails closed when this slips through, but the admin should not
   * need to read the engine logs to discover the conflict.
   */
  function detectOrgCollision() {
    const history = (state.org && Array.isArray(state.org.history)) ? state.org.history : [];
    if (history.length < 2) return false;
    const today = todayYmd();
    let actives = 0;
    history.forEach((row) => {
      const from = row.effectiveFrom || '';
      const to = row.effectiveTo || null;
      if (from && from <= today && (to === null || to >= today)) actives += 1;
    });
    return actives > 1;
  }

  function renderHypotheticalTeamPicker() {
    const select = document.getElementById('sim-hypothetical-teams');
    if (!select) return;
    const teams = (state.team && Array.isArray(state.team.availableTeams)) ? state.team.availableTeams : [];
    const opts = teams
      .slice()
      .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')))
      .map((tm) => `<option value="${escape(tm.id)}">${escape(tm.name)}</option>`)
      .join('');
    select.innerHTML = opts;
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
        const collisionBanner = detectOrgCollision()
          ? `<p class="layer-card__warning" role="alert">${escape(t('Warning: more than one organisation default is active on the same date. Resolution falls back to the latest row and emits a degraded flag. Please remove the conflicting row.', 'Warning: more than one organisation default is active on the same date. Resolution falls back to the latest row and emits a degraded flag. Please remove the conflicting row.'))}</p>`
          : '';
        setHtml(activeEl, collisionBanner + renderActiveRow(active, [
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
  // WCAG 2.4.3 — remember the trigger so we can return focus when the
  // dialog closes (otherwise focus drops to <body> and keyboard users have
  // to tab back from the very top).
  let dialogReturnFocus = null;

  function openDialog(context) {
    dialogReturnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    dialogContext = context;
    dialogTitle.textContent = context.title;
    dialogIntro.textContent = context.intro || '';
    dialogBody.innerHTML = context.body;
    dialogFeedback.textContent = '';
    resetImpactPreview();
    syncLayerDialogModeFields();
    if (typeof dialog.showModal === 'function') {
      try { dialog.showModal(); } catch (e) { dialog.setAttribute('open', 'open'); }
    } else {
      dialog.setAttribute('open', 'open');
    }
    const first = dialog.querySelector('input, select, textarea');
    if (first) {
      try { first.focus(); } catch (e) { /* noop */ }
    }
    // Initial preview — for `org` no target is needed; for `model` / `team`
    // we wait for the user to pick a target before firing.
    schedulePreview();
  }

  function closeDialog() {
    if (typeof dialog.close === 'function') {
      try { dialog.close(); } catch (e) { /* noop */ }
    }
    dialog.removeAttribute('open');
    dialogContext = null;
    if (dialogReturnFocus && typeof dialogReturnFocus.focus === 'function') {
      try { dialogReturnFocus.focus(); } catch (e) { /* noop */ }
    }
    dialogReturnFocus = null;
  }

  // Native <dialog>'s ESC closes via "cancel" — wire focus return there too.
  if (dialog) {
    dialog.addEventListener('cancel', () => {
      // The browser will close the dialog automatically; we only need to
      // restore focus and clear our state.
      dialogContext = null;
      if (dialogReturnFocus && typeof dialogReturnFocus.focus === 'function') {
        try { dialogReturnFocus.focus(); } catch (e) { /* noop */ }
      }
      dialogReturnFocus = null;
    });
  }

  document.getElementById('layer-dialog-cancel').addEventListener('click', closeDialog);

  // --------------------------------------------------------------------
  // Impact preview (REQ-UX-03): live "this change will affect ~N users"
  // hint that fires when the admin picks a target. Read-only endpoint, no
  // write lock, debounced to 250ms.
  // --------------------------------------------------------------------
  const impactBox = document.getElementById('layer-dialog-impact');
  const impactText = document.getElementById('layer-dialog-impact-text');
  let impactTimer = null;
  let impactReqId = 0;

  function resetImpactPreview() {
    if (impactBox) {
      impactBox.hidden = true;
      impactBox.removeAttribute('data-state');
    }
    if (impactText) {
      impactText.textContent = '';
    }
  }

  function setImpactState(state, message) {
    if (!impactBox || !impactText) return;
    impactBox.hidden = false;
    impactBox.setAttribute('data-state', state);
    impactText.textContent = message;
  }

  function schedulePreview() {
    window.clearTimeout(impactTimer);
    impactTimer = window.setTimeout(runPreview, 250);
  }

  function runPreview() {
    if (!dialogContext || !URLS.impact) return;
    const layer = dialogContext.layer;
    let targetId = null;
    if (layer === 'model') {
      const sel = document.getElementById('dlg-model');
      targetId = sel ? sel.value : null;
    } else if (layer === 'team') {
      const sel = document.getElementById('dlg-team');
      targetId = sel ? sel.value : null;
    }
    if ((layer === 'model' || layer === 'team') && (!targetId || targetId === '')) {
      // No target picked yet → friendly placeholder.
      const msg = layer === 'model'
        ? t('Pick a working time model to see who would be affected.', 'Pick a working time model to see who would be affected.')
        : t('Pick a team to see who would be affected.', 'Pick a team to see who would be affected.');
      setImpactState('idle', msg);
      return;
    }
    setImpactState('loading', t('Estimating impact…', 'Estimating impact…'));
    const reqId = ++impactReqId;
    const params = new URLSearchParams({ scope: layer });
    if (targetId !== null && targetId !== '') {
      params.set('targetId', String(targetId));
    }
    Utils.ajax(URLS.impact + '?' + params.toString(), {
      method: 'GET',
      onSuccess: (data) => {
        if (reqId !== impactReqId) return; // stale response
        const payload = (data && data.data) || {};
        const count = Number(payload.affected_user_count || 0);
        const note = String(payload.note || '');
        let msg;
        if (count === 0) {
          msg = t('No employees would be directly affected by this change.', 'No employees would be directly affected by this change.');
        } else if (count === 1) {
          msg = t('Up to 1 employee may be re-resolved by this change.', 'Up to 1 employee may be re-resolved by this change.');
        } else {
          msg = (t('Up to {n} employees may be re-resolved by this change.', 'Up to {n} employees may be re-resolved by this change.') || '').replace('{n}', String(count));
        }
        setImpactState(count > 0 ? 'warn' : 'ok', msg + (note ? '  ' + note : ''));
      },
      onError: () => {
        if (reqId !== impactReqId) return;
        setImpactState('error', t('Impact preview unavailable.', 'Impact preview unavailable.'));
      },
    });
  }

  // Live updates as the admin edits the form (target id changes only —
  // the underlying count is identical regardless of mode/days, so we don't
  // re-fire on every keystroke).
  document.addEventListener('change', (ev) => {
    if (!dialogContext) return;
    if (!dialog.open && !dialog.hasAttribute('open')) return;
    const target = ev.target;
    if (!(target instanceof HTMLElement)) return;
    const id = target.id || '';
    if (id === 'dlg-mode') {
      syncLayerDialogModeFields();
    }
    if (id === 'dlg-model' || id === 'dlg-team') {
      schedulePreview();
    }
  });

  // Clear the inline manual-days error as soon as the user starts fixing
  // the value. Without this the admin could correct the typo and the
  // red error message would stick until the next submit attempt, which
  // is confusing and would fail WCAG 3.3.1 "Error identification" when
  // the field no longer carries an error state.
  document.addEventListener('input', (ev) => {
    if (!dialogContext) return;
    if (!dialog.open && !dialog.hasAttribute('open')) return;
    const target = ev.target;
    if (!(target instanceof HTMLElement)) return;
    if (target.id === 'dlg-days') {
      clearManualDaysFieldError();
    }
  });

  // Shared form fragments (fieldsets + mode-conditional rows for WCAG / clarity)
  function fieldsetSection(fieldsetId, legendKey, legendFb, innerHtml) {
    return `<fieldset class="layer-form__fieldset" id="${escape(fieldsetId)}"><legend class="layer-form__legend">${escape(t(legendKey, legendFb))}</legend>${innerHtml}</fieldset>`;
  }

  function syncLayerDialogModeFields() {
    const modeEl = document.getElementById('dlg-mode');
    if (!modeEl) return;
    let mode = modeEl.value;
    const tariffOpt = modeEl.querySelector('option[value="tariff_rule_based"]');
    if (mode === 'tariff_rule_based' && tariffOpt && tariffOpt.disabled) {
      mode = 'manual_fixed';
      modeEl.value = 'manual_fixed';
    }
    const rowManual = document.getElementById('dlg-row-manual');
    const rowTariff = document.getElementById('dlg-row-tariff');
    const inpDays = document.getElementById('dlg-days');
    const selRuleset = document.getElementById('dlg-ruleset');
    const showManual = mode === 'manual_fixed';
    const showTariff = mode === 'tariff_rule_based';

    if (rowManual) {
      rowManual.hidden = !showManual;
      rowManual.setAttribute('aria-hidden', showManual ? 'false' : 'true');
    }
    if (inpDays) {
      inpDays.disabled = !showManual;
      inpDays.required = showManual;
      if (!showManual) {
        inpDays.value = '';
        // Clear the inline error helper too, otherwise switching from
        // manual_fixed → model_based_simple after a failed save would
        // leave a stale error visible against a now-irrelevant field.
        clearManualDaysFieldError();
      }
    }
    if (rowTariff) {
      rowTariff.hidden = !showTariff;
      rowTariff.setAttribute('aria-hidden', showTariff ? 'false' : 'true');
    }
    if (selRuleset) {
      selRuleset.disabled = !showTariff;
      selRuleset.required = showTariff;
      if (!showTariff) {
        selRuleset.value = '';
        selRuleset.removeAttribute('aria-invalid');
      }
    }
  }

  function modeFieldHtml(allowInherit, tariffAvailable) {
    const tariffOptDisabled = !tariffAvailable;
    return `
      <div class="layer-form__row">
        <label for="dlg-mode" class="form-label">${escape(t('Mode', 'Mode'))}</label>
        <select id="dlg-mode" name="vacationMode" class="form-select" required>
          <option value="manual_fixed">${escape(fmtMode('manual_fixed'))}</option>
          <option value="model_based_simple">${escape(fmtMode('model_based_simple'))}</option>
          <option value="tariff_rule_based"${tariffOptDisabled ? ' disabled' : ''}>${escape(fmtMode('tariff_rule_based'))}</option>
          ${allowInherit ? `<option value="inherit">${escape(fmtMode('inherit'))}</option>` : ''}
        </select>
        ${!tariffAvailable ? `<p class="layer-form__hint layer-form__hint--info" id="dlg-tariff-unavailable" role="status">${escape(t('No active tariff rule sets exist yet. Create and activate a rule set first, or choose another mode.', 'No active tariff rule sets exist yet. Create and activate a rule set first, or choose another mode.'))}</p>` : ''}
      </div>
    `;
  }

  function daysFieldHtml() {
    // Numeric invariant per spec: 0 ≤ days ≤ 366, rounded to two decimal
    // places (REQ-ENT-07). We deliberately use `type="text"` + `pattern`
    // instead of `type="number" step="0.5"` so:
    //  1. Part-time / tariff prorations such as 31.2 days are not rejected
    //     by the browser's numeric stepper (the previous step="0.5" UI
    //     blocked legitimate values).
    //  2. German-locale users can type a decimal comma (`31,2`) natively
    //     — the same pattern is used by the L3 manual-days editor in
    //     `admin-users.js`, so all four layers (L0/L1/L2/L3) accept the
    //     identical input shape.
    //  3. The DB column `manual_days FLOAT(6, 2)` and engine
    //     `roundDays()` (half-up, 2 dp) already constrain precision on
    //     the write path; the UI now matches that contract exactly.
    return `
      <div class="layer-form__row layer-form__row--mode-conditional" id="dlg-row-manual" hidden>
        <label for="dlg-days" class="form-label">${escape(t('Manual days (per year)', 'Manual days (per year)'))} <span class="form-required" aria-label="${escape(t('required', 'required'))}">*</span></label>
        <input type="text" id="dlg-days" name="manualDays" class="form-input"
               inputmode="decimal"
               pattern="^[0-9]+([\\.,][0-9]{1,2})?$"
               autocomplete="off"
               aria-describedby="dlg-days-help dlg-days-error">
        <p id="dlg-days-help" class="form-help">${escape(t('Annual vacation days (0–366). Up to two decimal places are allowed, e.g. 25, 25.5, 27.75, or 31.2 — comma or dot both work.', 'Annual vacation days (0–366). Up to two decimal places are allowed, e.g. 25, 25.5, 27.75, or 31.2 — comma or dot both work.'))}</p>
        <p id="dlg-days-error" class="form-error form-error--inline" role="alert" aria-live="polite" hidden></p>
      </div>
    `;
  }

  function ruleSetFieldHtml() {
    const opts = ['<option value="">— ' + escape(t('None', 'None')) + ' —</option>']
      .concat(state.ruleSets.map((r) => {
        const statusTxt = fmtTariffRuleSetStatus(r);
        return `<option value="${r.id}">${escape(r.tariffCode || r.id)} (v${escape(r.version)} · ${escape(statusTxt)})</option>`;
      }))
      .join('');
    return `
      <div class="layer-form__row layer-form__row--mode-conditional" id="dlg-row-tariff" hidden>
        <label for="dlg-ruleset" class="form-label">${escape(t('Tariff rule set', 'Tariff rule set'))}</label>
        <select id="dlg-ruleset" name="tariffRuleSetId" class="form-select" aria-describedby="dlg-ruleset-help">${opts}</select>
        <p id="dlg-ruleset-help" class="form-help">${escape(t('Pick the active rule set that defines this entitlement.', 'Pick the active rule set that defines this entitlement.'))}</p>
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
    const today = todayYmd();
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

  function teamPriorityHtml() {
    return `
      <div class="layer-form__row" id="dlg-row-priority">
        <label for="dlg-priority" class="form-label">${escape(t('Priority', 'Priority'))}</label>
        <input type="number" id="dlg-priority" name="priority" class="form-input" value="0" min="-1000" max="1000" step="1" aria-describedby="dlg-priority-help">
        <p id="dlg-priority-help" class="form-help">${escape(t('Higher priority wins inside the same team depth. Use small integers (e.g. 0–100).', 'Higher priority wins inside the same team depth. Use small integers (e.g. 0–100).'))}</p>
      </div>
    `;
  }

  function openOrgDialog() {
    const tariffAvailable = Array.isArray(state.ruleSets) && state.ruleSets.length > 0;
    openDialog({
      layer: 'org',
      title: t('Add organisation default', 'Add organisation default'),
      intro: t('This default applies to every employee who has no individual rule, no team policy, and no model default for the chosen date range.', 'This default applies to every employee who has no individual rule, no team policy, and no model default for the chosen date range.'),
      body: [
        fieldsetSection('dlg-fs-calc', 'How vacation days are calculated', 'How vacation days are calculated',
          modeFieldHtml(false, tariffAvailable) + daysFieldHtml() + ruleSetFieldHtml()),
        fieldsetSection('dlg-fs-dates', 'When this rule applies', 'When this rule applies', effectiveFieldsHtml()),
        fieldsetSection('dlg-fs-note', 'Optional description', 'Optional description', descriptionFieldHtml()),
      ].join(''),
    });
  }

  function openModelDialog() {
    const tariffAvailable = Array.isArray(state.ruleSets) && state.ruleSets.length > 0;
    const opts = state.model.availableModels.map((m) => `<option value="${m.id}">${escape(m.name)}</option>`).join('');
    const modelRow = `
      <div class="layer-form__row layer-form__row--wide">
        <label for="dlg-model" class="form-label">${escape(t('Working time model', 'Working time model'))}</label>
        <select id="dlg-model" name="workingTimeModelId" class="form-select" required>${opts}</select>
      </div>`;
    openDialog({
      layer: 'model',
      title: t('Add working time model default', 'Add working time model default'),
      intro: t('Attach a default entitlement to a working time model. Applies to every employee currently assigned to that model who has neither an individual rule nor a team policy.', 'Attach a default entitlement to a working time model. Applies to every employee currently assigned to that model who has neither an individual rule nor a team policy.'),
      body: [
        fieldsetSection('dlg-fs-target', 'Working time model', 'Working time model', modelRow),
        fieldsetSection('dlg-fs-calc', 'How vacation days are calculated', 'How vacation days are calculated',
          modeFieldHtml(false, tariffAvailable) + daysFieldHtml() + ruleSetFieldHtml()),
        fieldsetSection('dlg-fs-dates', 'When this rule applies', 'When this rule applies', effectiveFieldsHtml()),
        fieldsetSection('dlg-fs-note', 'Optional description', 'Optional description', descriptionFieldHtml()),
      ].join(''),
    });
  }

  function openTeamDialog() {
    const tariffAvailable = Array.isArray(state.ruleSets) && state.ruleSets.length > 0;
    const opts = state.team.availableTeams.map((tm) => `<option value="${tm.id}">${escape(tm.name)}</option>`).join('');
    const teamRow = `
      <div class="layer-form__row layer-form__row--wide">
        <label for="dlg-team" class="form-label">${escape(t('Team', 'Team'))}</label>
        <select id="dlg-team" name="teamId" class="form-select" required>${opts}</select>
      </div>`;
    openDialog({
      layer: 'team',
      title: t('Add team / cohort policy', 'Add team / cohort policy'),
      intro: t('Attach a policy to a team. When an employee belongs to several teams, the deepest team wins; ties are broken by the higher priority, then by the smallest team ID.', 'Attach a policy to a team. When an employee belongs to several teams, the deepest team wins; ties are broken by the higher priority, then by the smallest team ID.'),
      body: [
        fieldsetSection('dlg-fs-target', 'Team', 'Team', teamRow),
        fieldsetSection('dlg-fs-calc', 'How vacation days are calculated', 'How vacation days are calculated',
          modeFieldHtml(false, tariffAvailable) + daysFieldHtml() + ruleSetFieldHtml()),
        fieldsetSection('dlg-fs-tiebreak', 'Tie-breaking when teams overlap', 'Tie-breaking when teams overlap', teamPriorityHtml()),
        fieldsetSection('dlg-fs-dates', 'When this rule applies', 'When this rule applies', effectiveFieldsHtml()),
        fieldsetSection('dlg-fs-note', 'Optional description', 'Optional description', descriptionFieldHtml()),
      ].join(''),
    });
  }

  // -----------------------------------------------------------------------
  // Decimal helpers — single canonical parser used by all entry points so
  // backend (PHP `LayeredVacationDefaultsService::parseDecimal`) and frontend
  // share semantics: accept comma OR dot, reject anything that is not a
  // plain non-negative decimal with up to two fraction digits, normalise
  // to a dot-decimal string the controller can ingest verbatim.
  //
  // Why we duplicate the regex on the client side instead of just
  // shipping the raw string and waiting for the 400:
  //  - Native `<input pattern>` validation is silent on submit when the
  //    form is submitted programmatically — we still need to flag the
  //    field as invalid for AT users.
  //  - Round-tripping to the server for a typo is poor UX, especially on
  //    slow networks.
  //  - We must still defer the *authoritative* validation to the engine
  //    so this is defence-in-depth, not a replacement.
  // -----------------------------------------------------------------------
  const MANUAL_DAYS_MAX = 366; // mirror engine `roundDays` clamp
  const MANUAL_DAYS_MIN = 0;

  function parseManualDaysInput(raw) {
    if (raw === null || raw === undefined) {
      return { ok: false, reason: 'empty' };
    }
    const trimmed = String(raw).replace(/\s+/g, '').trim();
    if (trimmed === '') {
      return { ok: false, reason: 'empty' };
    }
    // Reject sign / scientific notation / multiple separators up front so
    // a malicious paste like "1e308" or "-5" cannot smuggle in NaN / inf
    // / negative manual days. The strict pattern is the same one used in
    // the form attribute so client and HTML semantics never drift.
    if (!/^[0-9]+([.,][0-9]{1,2})?$/.test(trimmed)) {
      return { ok: false, reason: 'format' };
    }
    const dotted = trimmed.replace(',', '.');
    const value = Number(dotted);
    if (!Number.isFinite(value)) {
      return { ok: false, reason: 'not-finite' };
    }
    if (value < MANUAL_DAYS_MIN || value > MANUAL_DAYS_MAX) {
      return { ok: false, reason: 'range' };
    }
    return { ok: true, value, normalized: dotted };
  }

  function setManualDaysFieldError(message) {
    const input = document.getElementById('dlg-days');
    const errorEl = document.getElementById('dlg-days-error');
    if (input) {
      input.setAttribute('aria-invalid', 'true');
    }
    if (errorEl) {
      errorEl.textContent = message;
      errorEl.hidden = false;
    }
  }

  function clearManualDaysFieldError() {
    const input = document.getElementById('dlg-days');
    const errorEl = document.getElementById('dlg-days-error');
    if (input) {
      input.removeAttribute('aria-invalid');
    }
    if (errorEl) {
      errorEl.textContent = '';
      errorEl.hidden = true;
    }
  }

  function manualDaysErrorMessage(reason) {
    if (reason === 'empty') {
      return t('Please enter the number of vacation days per year.', 'Please enter the number of vacation days per year.');
    }
    if (reason === 'format') {
      return t('Enter a number between 0 and 366 with up to two decimal places (for example 25, 25.5, 27.75, or 31.2). Comma or dot both work.', 'Enter a number between 0 and 366 with up to two decimal places (for example 25, 25.5, 27.75, or 31.2). Comma or dot both work.');
    }
    if (reason === 'range') {
      return t('Enter a value between 0 and 366 days.', 'Enter a value between 0 and 366 days.');
    }
    return t('Enter a valid number of vacation days.', 'Enter a valid number of vacation days.');
  }

  // -----------------------------------------------------------------------
  // Save handlers
  // -----------------------------------------------------------------------
  function readForm() {
    const modeEl = document.getElementById('dlg-mode');
    const mode = modeEl ? modeEl.value : 'manual_fixed';
    const fromEl = document.getElementById('dlg-from');
    const toEl = document.getElementById('dlg-to');
    const descEl = document.getElementById('dlg-desc');

    const payload = {
      vacationMode: mode,
      effectiveFrom: fromEl && fromEl.value ? String(fromEl.value).trim() : null,
      effectiveTo: toEl && toEl.value ? String(toEl.value).trim() : null,
      description: descEl && String(descEl.value).trim() !== '' ? String(descEl.value).trim() : null,
    };

    if (dialogContext.layer === 'model') {
      const m = document.getElementById('dlg-model');
      payload.workingTimeModelId = m && m.value ? parseInt(String(m.value), 10) : 0;
    }
    if (dialogContext.layer === 'team') {
      const team = document.getElementById('dlg-team');
      const pr = document.getElementById('dlg-priority');
      payload.teamId = team && team.value ? parseInt(String(team.value), 10) : 0;
      const rawP = pr && pr.value !== '' ? parseInt(String(pr.value), 10) : 0;
      payload.priority = Number.isFinite(rawP) ? rawP : 0;
    }

    if (mode === 'manual_fixed') {
      const d = document.getElementById('dlg-days');
      if (d && !d.disabled && d.value !== '') {
        // We already validated `d.value` in the submit handler before
        // calling readForm(); re-run the parser here defensively so a
        // refactor that bypasses the handler (e.g. a test that calls
        // readForm directly) still receives a normalised dot-decimal
        // string instead of a German "31,2" the backend would parse but
        // some intermediate JSON consumers might not.
        const parsed = parseManualDaysInput(d.value);
        if (parsed.ok) {
          payload.manualDays = parsed.normalized;
        } else {
          // Preserve raw (with comma→dot swap) so the server's own
          // validator can return the canonical error; client-side error
          // was already surfaced by the submit handler if we got here.
          payload.manualDays = String(d.value).replace(',', '.');
        }
      }
    } else if (mode === 'tariff_rule_based') {
      const r = document.getElementById('dlg-ruleset');
      if (r && !r.disabled && r.value !== '') {
        payload.tariffRuleSetId = parseInt(String(r.value), 10);
      }
    }
    return payload;
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
    clearFieldErrors();
    clearManualDaysFieldError();
    syncLayerDialogModeFields();
    dialogFeedback.textContent = '';

    // Client-side guard on the manual-days input. We still defer the
    // authoritative check to the engine — this only catches the obvious
    // typos and keeps the round-trip cost down for HR.
    const modeEl = document.getElementById('dlg-mode');
    const mode = modeEl ? modeEl.value : 'manual_fixed';
    if (mode === 'manual_fixed') {
      const d = document.getElementById('dlg-days');
      if (d && !d.disabled) {
        const parsed = parseManualDaysInput(d.value);
        if (!parsed.ok) {
          const msg = manualDaysErrorMessage(parsed.reason);
          setManualDaysFieldError(msg);
          dialogFeedback.textContent = msg;
          try { d.focus(); } catch (e) { /* noop */ }
          return;
        }
      }
    }

    const payload = readForm();
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
        const status = err && err.status;
        let msg = (err && err.error) || t('Could not save', 'Could not save');
        if (status === 409) {
          // EC-07: another admin is editing the same layer — give a
          // specific, actionable hint so the user doesn't just retry blindly.
          msg = (err && err.error)
            || t('Another administrator is currently editing this layer. Refresh and try again.', 'Another administrator is currently editing this layer. Refresh and try again.');
        }
        dialogFeedback.textContent = msg;
        if (err && err.data && err.data.errors) {
          syncLayerDialogModeFields();
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
    if (delOrg) {
      ev.preventDefault();
      void confirmDelete(t('Delete organisation default?', 'Delete organisation default?'), () => doDelete(URLS.orgDelete, delOrg));
      return;
    }
    const delModel = target.getAttribute('data-delete-model');
    if (delModel) {
      ev.preventDefault();
      void confirmDelete(t('Delete model default?', 'Delete model default?'), () => doDelete(URLS.modelDelete, delModel));
      return;
    }
    const delTeam = target.getAttribute('data-delete-team');
    if (delTeam) {
      ev.preventDefault();
      void confirmDelete(t('Delete team policy?', 'Delete team policy?'), () => doDelete(URLS.teamDelete, delTeam));
      return;
    }
  });

  /**
   * Prefer the app confirm dialog (arbeitszeitcheck strings + centered modal).
   * OC.dialogs uses core copy/styling and is not guaranteed to match the user locale.
   */
  async function confirmDelete(message, onConfirm) {
    if (typeof Components.showConfirmDialog === 'function') {
      try {
        const confirmed = await Components.showConfirmDialog({
          title: t('Confirm deletion', 'Confirm deletion'),
          message,
          variant: 'danger',
          confirmLabel: t('Delete', 'Delete'),
          cancelLabel: t('Cancel', 'Cancel'),
        });
        if (confirmed) {
          onConfirm();
        }
      } catch (e) {
        /* noop */
      }
      return;
    }
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
      return;
    }
    if (window.confirm(message)) {
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
      const asOfDate = document.getElementById('sim-date').value || todayYmd();
      if (!userId) {
        notifyError(t('Please pick an employee first.', 'Please pick an employee first.'));
        if (simUser) simUser.focus();
        return;
      }
      const hypothetical = document.getElementById('sim-hypothetical-teams');
      const hypotheticalTeamIds = hypothetical
        ? Array.from(hypothetical.selectedOptions).map((o) => parseInt(o.value, 10)).filter((n) => Number.isInteger(n) && n > 0)
        : [];
      const payload = { userId, asOfDate };
      if (hypotheticalTeamIds.length > 0) {
        payload.hypotheticalTeamIds = hypotheticalTeamIds;
      }
      Utils.ajax(URLS.simulate, {
        method: 'POST',
        data: payload,
        onSuccess: (data) => renderSimResult(data),
        onError: (err) => {
          const errMsg = (err && err.error) || t('Could not run simulation', 'Could not run simulation');
          simResult.innerHTML = `<p class="layer-card__placeholder" role="alert">${escape(errMsg)}</p>`;
          announce(errMsg);
        },
      });
    });

    const simReset = document.getElementById('sim-reset');
    if (simReset) {
      simReset.addEventListener('click', () => {
        simSelectedUserId = null;
        if (simUser) simUser.value = '';
        const simDate = document.getElementById('sim-date');
        if (simDate) simDate.value = todayYmd();
        const hypothetical = document.getElementById('sim-hypothetical-teams');
        if (hypothetical) {
          Array.from(hypothetical.options).forEach((o) => { o.selected = false; });
        }
        if (simResult) simResult.innerHTML = '';
      });
    }
  }

  function renderLayerFlags(layer) {
    // Surface degraded-state markers as visible chips so an admin can spot
    // misconfigurations in the simulator output without having to crack
    // open the raw trace JSON. Each flag is also conveyed by its label —
    // we never rely on colour alone (WCAG 1.4.1).
    const flags = [];
    if (layer.degraded_org_default_collision) {
      flags.push({ kind: 'warn', label: t('Multiple active L0 rules', 'Multiple active L0 rules') });
    }
    if (layer.partial_history) {
      flags.push({ kind: 'info', label: t('Partial team history', 'Partial team history') });
    }
    if (layer.hypothetical) {
      flags.push({ kind: 'info', label: t('Hypothetical team', 'Hypothetical team') });
    }
    if (layer.degraded) {
      flags.push({ kind: 'warn', label: t('Degraded fallback', 'Degraded fallback') });
    }
    if (!flags.length) return '';
    return flags.map((f) => `<span class="trace-flag trace-flag--${escape(f.kind)}">${escape(f.label)}</span>`).join(' ');
  }

  function humanLayerLabel(layer) {
    const map = {
      L3: t('Individual policy', 'Individual policy'),
      L2: t('Team / cohort policy', 'Team / cohort policy'),
      L1: t('Working time model default', 'Working time model default'),
      L0: t('Organisation default', 'Organisation default'),
      legacy: t('Legacy fallback (25 d.)', 'Legacy fallback (25 d.)'),
    };
    return map[layer] || layer || '—';
  }

  function renderCandidatesList(candidates) {
    if (!Array.isArray(candidates) || candidates.length < 2) return '';
    const items = candidates.map((c, idx) => {
      const tn = getTeamLabel(c.team_id);
      const winner = idx === 0 ? ` <span class="trace-flag trace-flag--info">${escape(t('Winner', 'Winner'))}</span>` : '';
      return `<li><strong>${escape(tn)}</strong> ` +
        `<span class="form-help">(${escape(t('depth', 'depth'))} ${escape(String(c.team_depth))}, ` +
        `${escape(t('priority', 'priority'))} ${escape(String(c.priority))}, ` +
        `${escape(t('policy', 'policy'))} #${escape(String(c.policy_id))})</span>${winner}</li>`;
    }).join('');
    return `<details class="layer-sim__details"><summary>${escape(t('Tie-break details (candidate teams)', 'Tie-break details (candidate teams)'))}</summary><ol>${items}</ol></details>`;
  }

  function renderInnerFlags(inner) {
    if (!inner) return '';
    const chips = [];
    if (inner.clamped) {
      const raw = inner.raw_manual_days != null ? inner.raw_manual_days : inner.raw_computed_days;
      const detail = raw != null ? ` (${fmtDays(raw)} → ${t('clamped to 0–366', 'clamped to 0–366')})` : '';
      chips.push(`<span class="trace-flag trace-flag--warn">${escape(t('Clamped', 'Clamped'))}${escape(detail)}</span>`);
    }
    if (inner.degraded === 'model_lookup_failed') {
      chips.push(`<span class="trace-flag trace-flag--warn">${escape(t('Working time model missing', 'Working time model missing'))}</span>`);
    }
    if (inner.rule_set_status_warning) {
        chips.push(`<span class="trace-flag trace-flag--warn">${escape(t('Tariff rule set not active', 'Tariff rule set not active'))} (${escape(fmtTariffStatusCode(inner.rule_set_status_warning))})</span>`);
    }
    return chips.join(' ');
  }

  function renderSimResult(data) {
    if (!data || data.success !== true) {
      simResult.innerHTML = `<p class="layer-card__placeholder" role="alert">${escape(t('Could not run simulation', 'Could not run simulation'))}</p>`;
      return;
    }
    const trace = data.calculationTrace || {};
    const layers = Array.isArray(trace.layers_evaluated) ? trace.layers_evaluated : [];
    const matchedLayer = (trace.matched_layer || data.matchedLayer) || '—';
    const rows = layers.map((layer) => {
      const matched = layer.matched === true;
      const flagsHtml = renderLayerFlags(layer);
      const candidatesHtml = (layer.layer === 'L2' && matched && Array.isArray(layer.candidates))
        ? renderCandidatesList(layer.candidates)
        : '';
      const teamLabel = (layer.layer === 'L2' && matched && layer.team_id)
        ? `<br><span class="form-help">${escape(t('Team', 'Team'))}: ${escape(getTeamLabel(layer.team_id))}</span>`
        : '';
      const modelLabel = (layer.layer === 'L1' && matched && layer.working_time_model_id)
        ? `<br><span class="form-help">${escape(t('Model', 'Model'))}: ${escape(getModelLabel(layer.working_time_model_id))}</span>`
        : '';
      return `
        <tr data-matched="${matched ? 'true' : 'false'}">
          <td><strong>${escape(layer.layer || '')}</strong><br><span class="form-help">${escape(humanLayerLabel(layer.layer))}</span></td>
          <td>${matched ? escape(t('Match', 'Match')) : escape(t('Skipped', 'Skipped'))}</td>
          <td>${escape(fmtMode(layer.mode || layer.reason || ''))}${flagsHtml ? '<br>' + flagsHtml : ''}${teamLabel}${modelLabel}${candidatesHtml}</td>
          <td>${escape(layer.days != null ? fmtDays(layer.days) : '—')}</td>
        </tr>
      `;
    }).join('');
    const innerFlags = renderInnerFlags(trace.inner);
    const banners = [];
    if (trace.degraded) {
      banners.push(`<p class="layer-sim__banner layer-sim__banner--warn" role="alert">${escape(t('Heads up: this resolution ran in a degraded state. See the flags below for details.', 'Heads up: this resolution ran in a degraded state. See the flags below for details.'))}</p>`);
    }
    if (Array.isArray(data.hypotheticalTeamIds) && data.hypotheticalTeamIds.length > 0) {
      const names = data.hypotheticalTeamIds.map(getTeamLabel).join(', ');
      banners.push(`<p class="layer-sim__banner layer-sim__banner--info" role="status">${escape(t('What-if mode: hypothetical team membership applied —', 'What-if mode: hypothetical team membership applied —'))} ${escape(names)}.</p>`);
    }
    const summaryDays = fmtDays(data.effectiveEntitlementDays);
    const summarySentence = data.hypotheticalTeamIds && data.hypotheticalTeamIds.length > 0
      ? t('In this what-if scenario, the employee would receive {days} vacation days per year, determined by the {layer}.', 'In this what-if scenario, the employee would receive {days} vacation days per year, determined by the {layer}.')
      : t('On {date}, the employee receives {days} vacation days per year, determined by the {layer}.', 'On {date}, the employee receives {days} vacation days per year, determined by the {layer}.');
    const summaryText = summarySentence
      .replace('{date}', fmtDate(data.asOfDate))
      .replace('{days}', summaryDays)
      .replace('{layer}', humanLayerLabel(matchedLayer));
    simResult.innerHTML = `
      <div class="layer-sim__card">
        ${banners.join('')}
        <h3 class="layer-sim__headline">${escape(t('Result', 'Result'))}</h3>
        <p class="layer-sim__summary"><span class="layer-sim__days" aria-hidden="true">${escape(summaryDays)}</span>
          <span class="visually-hidden">${escape(summaryDays)} ${escape(t('days per year', 'days per year'))}</span>
          <span class="layer-sim__summary-text">${escape(summaryText)}</span></p>
        ${innerFlags ? `<p class="layer-sim__flags">${innerFlags}</p>` : ''}
        <details class="layer-sim__trace-details">
          <summary>${escape(t('Show full resolution trace', 'Show full resolution trace'))}</summary>
          <table class="trace-table" aria-label="${escape(t('Resolution trace', 'Resolution trace'))}">
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
          <p class="form-help">${escape(t('Trace v', 'Trace v'))}${escape(String(trace.algorithm_version || 1))} · ${escape(t('as-of', 'as-of'))} ${escape(trace.as_of_date || data.asOfDate || '')}</p>
        </details>
      </div>
    `;
    announce(t('Simulation finished.', 'Simulation finished.'));
    // Focus management: move keyboard focus to the result region so screen
    // readers / keyboard users can read the answer without hunting.
    if (typeof simResult.focus === 'function') {
      try { simResult.setAttribute('tabindex', '-1'); simResult.focus({ preventScroll: false }); } catch (e) { /* noop */ }
    }
  }

  // -----------------------------------------------------------------------
  // Init
  // -----------------------------------------------------------------------
  loadOverview();

  // Expose pure helpers for unit tests (vitest). We deliberately do NOT
  // expose mutating helpers, just the parser, so a hostile page script
  // cannot use this hook to mutate dialog state. Production users do not
  // depend on this object; if it disappears in a future minor release
  // that is intentional.
  if (typeof window !== 'undefined') {
    window.__ArbeitszeitCheckVacationLayersTestables = {
      parseManualDaysInput,
      manualDaysErrorMessage,
      MANUAL_DAYS_MIN,
      MANUAL_DAYS_MAX,
    };
  }
})();
