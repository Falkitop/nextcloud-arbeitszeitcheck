/**
 * Audit Log Viewer JavaScript for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function() {
    'use strict';

    const Utils = window.ArbeitszeitCheckUtils || {};
    const Messaging = window.ArbeitszeitCheckMessaging || {};

    function alT(msg) {
        const map = window.ArbeitszeitCheck && window.ArbeitszeitCheck.auditLogViewerL10n;
        if (map && Object.prototype.hasOwnProperty.call(map, msg) && map[msg] !== undefined && map[msg] !== '') {
            return map[msg];
        }
        return (typeof window.t === 'function' ? window.t('arbeitszeitcheck', msg) : msg);
    }

    /**
     * Initialize audit log viewer
     */
    function applyUrlParams() {
        const params = new URLSearchParams(window.location.search);
        const userId = params.get('user_id') || params.get('userId') || '';
        const action = params.get('action') || '';
        const userEl = Utils.$('#user-filter');
        const actionEl = Utils.$('#action-filter');
        if (userId && userEl) {
            userEl.value = userId;
        }
        if (action && actionEl) {
            let found = false;
            for (let i = 0; i < actionEl.options.length; i++) {
                if (actionEl.options[i].value === action) {
                    found = true;
                    break;
                }
            }
            if (!found) {
                const opt = document.createElement('option');
                opt.value = action;
                opt.textContent = action;
                actionEl.appendChild(opt);
            }
            actionEl.value = action;
        }
        if (userId || action) {
            loadAuditLogs();
        }
    }

    function init() {
        bindEvents();
        applyUrlParams();
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        const applyBtn = Utils.$('#apply-filters');
        if (applyBtn) {
            Utils.on(applyBtn, 'click', loadAuditLogs);
        }

        const exportBtn = Utils.$('#export-logs');
        if (exportBtn) {
            Utils.on(exportBtn, 'click', exportLogs);
        }
    }

    /**
     * Load audit logs with filters
     */
    function loadAuditLogs() {
        const dp = window.ArbeitszeitCheckDatepicker;
        const toISO = dp ? dp.convertEuropeanToISO : function (s) { return s; };
        const startDate = toISO(Utils.$('#start-date')?.value || '');
        const endDate = toISO(Utils.$('#end-date')?.value || '');
        const userId = Utils.$('#user-filter')?.value || '';
        const action = Utils.$('#action-filter')?.value || '';

        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (userId) params.append('user_id', userId);
        if (action) params.append('action', action);

        const tbody = Utils.$('#audit-log-tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">' + alT('Loading…') + '</td></tr>';
        }

        Utils.ajax('/apps/arbeitszeitcheck/api/admin/audit-logs?' + params.toString(), {
            method: 'GET',
            onSuccess: function(data) {
                if (data.success && data.logs) {
                    renderAuditLogs(data.logs);
                } else {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center">' + alT('Error loading audit logs') + '</td></tr>';
                }
            },
            onError: function(_error) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center">' + alT('Error loading audit logs') + '</td></tr>';
                if (Messaging && Messaging.showError) {
                    Messaging.showError(alT('Failed to load audit logs. Please try again.'));
                }
            }
        });
    }

    /**
     * Render audit logs table
     */
    function renderAuditLogs(logs) {
        const tbody = Utils.$('#audit-log-tbody');
        if (!tbody) return;

        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">' + alT('No audit log entries found') + '</td></tr>';
            return;
        }

        tbody.innerHTML = logs.map(log => {
            const createdRaw = log.created_at || log.createdAt;
            const created = createdRaw
                ? (window.ArbeitszeitCheckTime
                    ? (window.ArbeitszeitCheckTime.formatDateTime(createdRaw) || '-')
                    : (Utils.formatDate
                        ? Utils.formatDate(createdRaw, 'DD.MM.YYYY HH:mm')
                        : String(createdRaw)))
                : '-';
            const user = log.user_display_name || log.userDisplayName || log.user_id || log.userId;
            const performed = log.performed_by_display_name || log.performedByDisplayName || log.performed_by || log.performedBy || '-';
            const entity = log.entity_type || log.entityType;
            return `<tr>
                <td>${Utils.escapeHtml(String(created))}</td>
                <td>${Utils.escapeHtml(String(user))}</td>
                <td>${Utils.escapeHtml(String(log.action))}</td>
                <td>${Utils.escapeHtml(String(entity))}</td>
                <td>${Utils.escapeHtml(String(performed))}</td>
            </tr>`;
        }).join('');
    }

    /**
     * Export audit logs
     */
    function exportLogs() {
        const dp = window.ArbeitszeitCheckDatepicker;
        const toISO = dp ? dp.convertEuropeanToISO : function (s) { return s; };
        const startDate = toISO(Utils.$('#start-date')?.value || '');
        const endDate = toISO(Utils.$('#end-date')?.value || '');
        const userId = Utils.$('#user-filter')?.value || '';
        const action = Utils.$('#action-filter')?.value || '';

        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (userId) params.append('user_id', userId);
        if (action) params.append('action', action);

        // Redirect to export endpoint
        params.append('format', 'csv');
        window.location.href = OC.generateUrl('/apps/arbeitszeitcheck/api/admin/audit-logs/export?' + params.toString());
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
