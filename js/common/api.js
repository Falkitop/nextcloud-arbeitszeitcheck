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

  mapApiError(data, status) {
    if (data && typeof data === 'object') {
      if (typeof data.error === 'string' && data.error !== '') {
        return data.error;
      }
      if (data.error && typeof data.error.message === 'string') {
        return data.error.message;
      }
      if (typeof data.message === 'string' && data.message !== '') {
        return data.message;
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
    return window.t
      ? window.t('arbeitszeitcheck', 'An unexpected error occurred. Please try again.')
      : 'An unexpected error occurred. Please try again.';
  },
};

if (typeof window !== 'undefined') {
  window.AzcApi = AzcApi;
}
