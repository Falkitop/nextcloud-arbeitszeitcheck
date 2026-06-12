/**
 * Fetch wrapper with CSRF, JSON error mapping, and session-expiry handling.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

const AzcApi = {
  getRequestToken() {
    if (window.OC?.requestToken) {
      return window.OC.requestToken;
    }
    const meta = document.querySelector('head meta[name="requesttoken"]');
    return meta ? meta.getAttribute('content') : '';
  },

  /**
   * @param {string} url
   * @param {RequestInit & {json?: object}} options
   * @returns {Promise<{ok: boolean, status: number, data: object|null, error: string|null}>}
   */
  async fetch(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = new Headers(options.headers || {});
    const token = this.getRequestToken();

    if (method !== 'GET' && method !== 'HEAD' && token) {
      headers.set('requesttoken', token);
    }
    if (options.json !== undefined) {
      headers.set('Content-Type', 'application/json');
      headers.set('Accept', 'application/json');
    }
    if (!headers.has('Accept')) {
      headers.set('Accept', 'application/json');
    }

    const init = {
      ...options,
      method,
      headers,
      credentials: options.credentials || 'same-origin',
      body: options.json !== undefined ? JSON.stringify(options.json) : options.body,
    };
    delete init.json;

    let response;
    try {
      response = await fetch(url, init);
    } catch (e) {
      const msg = window.t
        ? window.t('arbeitszeitcheck', 'Network error. Check your connection and try again.')
        : 'Network error. Check your connection and try again.';
      window.ArbeitszeitCheckMessaging?.showError?.(msg);
      return { ok: false, status: 0, data: null, error: msg };
    }

    if (response.status === 412 || response.status === 419) {
      const expired = window.t
        ? window.t('arbeitszeitcheck', 'Your session expired — please refresh the page and try again.')
        : 'Your session expired — please refresh the page and try again.';
      window.ArbeitszeitCheckMessaging?.showError?.(expired);
      return { ok: false, status: response.status, data: null, error: expired };
    }

    let data = null;
    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      try {
        data = await response.json();
      } catch (e) {
        data = null;
      }
    }

    if (!response.ok) {
      const error = this.mapApiError(data, response.status);
      return { ok: false, status: response.status, data, error };
    }

    return { ok: true, status: response.status, data, error: null };
  },

  /**
   * True when HTTP succeeded and JSON body indicates success (if present).
   *
   * @param {{ok?: boolean, data?: object|null}} result
   * @returns {boolean}
   */
  isApiSuccess(result) {
    if (!result || result.ok !== true) {
      return false;
    }
    const data = result.data;
    if (data && typeof data === 'object') {
      if (data.ok === false) {
        return false;
      }
      if (typeof data.success === 'boolean') {
        return data.success;
      }
    }
    return true;
  },

  /**
   * True when a string looks like an API/machine code rather than user-facing text.
   * @param {string} value
   * @returns {boolean}
   */
  isMachineErrorCode(value) {
    if (typeof value !== 'string' || value === '') {
      return false;
    }
    if (/\s/.test(value)) {
      return false;
    }
    return /^[A-Z][A-Z0-9_]*$/.test(value) || /^[a-z][a-z0-9_]+$/.test(value);
  },

  /**
   * @param {string} msgid
   * @returns {string}
   */
  translate(msgid) {
    return window.t ? window.t('arbeitszeitcheck', msgid) : msgid;
  },

  mapApiError(data, status) {
    if (data && typeof data === 'object') {
      if (typeof data.error === 'string' && data.error !== '' && !this.isMachineErrorCode(data.error)) {
        return data.error;
      }
      if (data.error && typeof data.error.message === 'string') {
        return data.error.message;
      }
      if (typeof data.message === 'string' && data.message !== '' && !this.isMachineErrorCode(data.message)) {
        return data.message;
      }
      const code = (typeof data.code === 'string' && data.code)
        || (typeof data.error === 'string' && data.error)
        || (typeof data.message === 'string' && data.message);
      if (code === 'app_access_denied' || code === 'access_denied') {
        return this.translate(
          'You do not have access to ArbeitszeitCheck. Your account is not among the users or groups allowed to use this app.',
        );
      }
      if (code === 'LICENSE_REQUIRED') {
        return this.translate('ArbeitszeitCheck Mobile is not licensed for this user.');
      }
      if (code === 'TERMINAL_LICENSE_REQUIRED') {
        return this.translate('ArbeitszeitCheck Terminal is not licensed for this organisation.');
      }
      if (code === 'KIOSK_DISABLED') {
        return this.translate('Kiosk mode is not enabled on this server.');
      }
      if (code === 'KIOSK_TERMINAL_UNAUTHORIZED') {
        return this.translate('Terminal token invalid or revoked.');
      }
    }
    if (status === 403) {
      return window.t
        ? window.t('arbeitszeitcheck', 'Not found or no access.')
        : 'Not found or no access.';
    }
    if (status === 404) {
      return window.t
        ? window.t('arbeitszeitcheck', 'Not found or no access.')
        : 'Not found or no access.';
    }
    return this.translate(
      'An unexpected error occurred. Please try again. If the problem continues, contact your administrator.',
    );
  },
};

if (typeof window !== 'undefined') {
  window.AzcApi = AzcApi;
}
