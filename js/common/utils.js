/**
 * General Utility Functions for ArbeitszeitCheck App
 * Provides common utility functions used throughout the application
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

const ArbeitszeitCheckUtils = {
  // ===== API DATE/TIME (contract with PHP `format('c')`) =====

  /**
   * Parse an absolute instant from the PHP JSON API (RFC 3339 / ISO-8601 with offset from DateTime::format('c')).
   * Use for session start, entry start/end, and any server field serialized with an explicit zone.
   *
   * @param {string|number|Date|null|undefined} value
   * @returns {Date|null}
   */
  parseApiInstant(value) {
    const time = (typeof window !== 'undefined') ? window.ArbeitszeitCheckTime : null;
    if (time && typeof time.parseApiInstant === 'function') {
      return time.parseApiInstant(value);
    }
    if (value == null || value === '') {
      return null;
    }
    if (value instanceof Date) {
      return Number.isNaN(value.getTime()) ? null : value;
    }
    const s = String(value).trim();
    if (!s) {
      return null;
    }
    const ms = Date.parse(s);
    if (!Number.isFinite(ms)) {
      return null;
    }
    return new Date(ms);
  },

  // ===== DOM UTILITIES =====

  /**
   * Get element by selector
   */
  $(selector, context = document) {
    return context.querySelector(selector);
  },

  /**
   * Get all elements by selector
   */
  $$(selector, context = document) {
    return context.querySelectorAll(selector);
  },

  /**
   * Create element with attributes.
   * SECURITY: When using innerHTML, only pass pre-escaped or trusted markup.
   * For user/API data use textContent or escapeHtml() first.
   */
  createElement(tag, attributes = {}, content = '') {
    const element = document.createElement(tag);
    
    Object.entries(attributes).forEach(([key, value]) => {
      if (key === 'className') {
        element.className = value;
      } else if (key === 'textContent') {
        element.textContent = value;
      } else if (key === 'innerHTML') {
        element.innerHTML = value;
      } else {
        element.setAttribute(key, value);
      }
    });
    
    if (content) {
      element.textContent = content;
    }
    
    return element;
  },

  /**
   * Add event listener with options
   */
  on(element, event, handler, options = {}) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.addEventListener(event, handler, options);
    }
  },

  /**
   * Remove event listener
   */
  off(element, event, handler, options = {}) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.removeEventListener(event, handler, options);
    }
  },

  /**
   * Toggle element visibility
   */
  toggle(element, show = null) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (!element) return;
    
    if (show === null) {
      show = element.style.display === 'none';
    }
    
    element.style.display = show ? '' : 'none';
  },

  /**
   * Show element
   */
  show(element) {
    this.toggle(element, true);
  },

  /**
   * Hide element
   */
  hide(element) {
    this.toggle(element, false);
  },

  /**
   * Add class to element
   */
  addClass(element, className) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.classList.add(className);
    }
  },

  /**
   * Remove class from element
   */
  removeClass(element, className) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.classList.remove(className);
    }
  },

  /**
   * Toggle class on element
   */
  toggleClass(element, className, force = null) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.classList.toggle(className, force);
    }
  },

  /**
   * Check if element has class
   */
  hasClass(element, className) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    return element ? element.classList.contains(className) : false;
  },

  // ===== AJAX UTILITIES =====

  /**
   * Get Nextcloud OC object in a safe way.
   */
  getOc() {
    if (typeof window !== 'undefined' && window.OC) {
      return window.OC;
    }
    if (typeof OC !== 'undefined') {
      return OC;
    }
    return null;
  },

  /**
   * Read request token from OC or <head> fallback.
   */
  getRequestToken() {
    const oc = this.getOc();
    if (oc && oc.requestToken) {
      return oc.requestToken;
    }
    const head = document.querySelector('head');
    return head ? (head.getAttribute('data-requesttoken') || '') : '';
  },

  /**
   * Strip absolute same-app URLs to a root-relative path so origin checks
   * and fetch always target the current host (avoids localhost vs 127.0.0.1 mismatches).
   */
  toSameOriginPath(url) {
    if (typeof url !== 'string' || !url) {
      return url;
    }
    try {
      if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//')) {
        const absolute = url.startsWith('//') ? (window.location.protocol + url) : url;
        const parsed = new URL(absolute);
        if (typeof window !== 'undefined' && window.location && parsed.origin === window.location.origin) {
          return parsed.pathname + parsed.search + parsed.hash;
        }
        return url;
      }
    } catch (e) {
      return url;
    }
    return url;
  },

  /**
   * Resolve app and API URLs in one place.
   * - Keeps absolute URLs unchanged.
   * - Normalizes /apps/arbeitszeitcheck/... through OC.generateUrl when available.
   * - Keeps other root-relative paths unchanged.
   * - For relative paths, applies OC.generateUrl when available.
   */
  resolveUrl(url) {
    const oc = this.getOc();
    if (typeof url !== 'string') {
      return url;
    }

    url = this.toSameOriginPath(url);

    if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//')) {
      return url;
    }

    if (url.startsWith('/')) {
      // Already normalized app path.
      if (url.startsWith('/index.php/apps/arbeitszeitcheck/')) {
        return url;
      }
      if (oc && typeof oc.generateUrl === 'function' && url.startsWith('/apps/arbeitszeitcheck/')) {
        return oc.generateUrl(url);
      }
      // Fallback for deployments requiring /index.php where OC is unavailable or incomplete.
      if (url.startsWith('/apps/arbeitszeitcheck/') &&
        typeof window !== 'undefined' &&
        window.location &&
        typeof window.location.pathname === 'string' &&
        window.location.pathname.includes('/index.php/')) {
        return '/index.php' + url;
      }
      return url;
    }

    if (oc && typeof oc.generateUrl === 'function') {
      return oc.generateUrl(url);
    }

    return url;
  },

  /**
   * Check whether a URL targets an external origin.
   */
  isExternalUrl(url) {
    if (typeof url !== 'string') {
      return false;
    }
    if (!(url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//'))) {
      return false;
    }
    if (typeof window === 'undefined' || !window.location || !window.location.origin) {
      return true;
    }
    try {
      const absoluteUrl = url.startsWith('//') ? (window.location.protocol + url) : url;
      const parsed = new URL(absoluteUrl);
      return parsed.origin !== window.location.origin;
    } catch (e) {
      return true;
    }
  },

  /**
   * Localized message when Nextcloud rejects the request token (session expired).
   */
  sessionExpiredMessage() {
    return window.t
      ? window.t('arbeitszeitcheck', 'Your session expired — please refresh the page and try again.')
      : 'Your session expired — please refresh the page and try again.';
  },

  /**
   * Make AJAX request using Nextcloud's OC.generateUrl
   */
  ajax(url, options = {}) {
    const {
      method = 'GET',
      data = null,
      headers = {},
      onSuccess = null,
      onError = null,
      allowExternal = false,
      signal = null
    } = options;

    const requestToken = this.getRequestToken();
    const methodUpper = String(method).toUpperCase();
    const defaultHeaders = {
      'Accept': 'application/json',
      'requesttoken': requestToken
    };
    // Avoid sending Content-Type on GET/HEAD: some stacks and intermediaries mishandle it; body is absent anyway.
    if (methodUpper !== 'GET' && methodUpper !== 'HEAD') {
      defaultHeaders['Content-Type'] = 'application/json';
    }

    const config = {
      method: method,
      headers: { ...defaultHeaders, ...headers },
      credentials: 'same-origin'
    };

    if (signal) {
      config.signal = signal;
    }

    if (data && methodUpper !== 'GET' && methodUpper !== 'HEAD') {
      if (config.headers['Content-Type'] === 'application/json') {
        config.body = JSON.stringify(data);
      } else {
        config.body = data;
      }
    }

    const resolvedUrl = this.resolveUrl(url);
    if (!allowExternal && this.isExternalUrl(resolvedUrl)) {
      const error = new Error('External URL blocked by ArbeitszeitCheckUtils.ajax');
      error.error = error.message;
      error.status = 0;
      error.data = null;
      if (onError) {
        onError(error);
        return Promise.resolve(null);
      }
      return Promise.reject(error);
    }

    return fetch(resolvedUrl, config)
      .then(async response => {
        const data = await response.json().catch(() => null);
        if (response.status === 412 || response.status === 419) {
          const expired = this.sessionExpiredMessage();
          window.ArbeitszeitCheckMessaging?.showError?.(expired);
          const err = new Error(expired);
          err.error = expired;
          err.status = response.status;
          err.data = data;
          throw err;
        }
        if (!response.ok) {
          const err = new Error(data?.error || `HTTP error! status: ${response.status}`);
          err.error = data?.error || err.message;
          err.status = response.status;
          err.data = data;
          throw err;
        }
        return data;
      })
      .then(data => {
        if (onSuccess) {
          onSuccess(data);
        }
        return data;
      })
      .catch(error => {
        if (onError) {
          onError(error);
          // Most call sites rely on callbacks and do not attach .catch().
          // Avoid unhandled promise rejections once the error callback ran.
          return null;
        }
        throw error;
      });
  },

  /**
   * Serialize form data
   */
  serializeForm(form) {
    if (typeof form === 'string') {
      form = this.$(form);
    }
    
    if (!form || form.tagName !== 'FORM') {
      return {};
    }

    const formData = new FormData(form);
    const data = {};
    
    for (const [key, value] of formData.entries()) {
      if (data[key]) {
        // Handle multiple values (e.g., checkboxes)
        if (Array.isArray(data[key])) {
          data[key].push(value);
        } else {
          data[key] = [data[key], value];
        }
      } else {
        data[key] = value;
      }
    }
    
    return data;
  },

  // ===== DATE UTILITIES =====

  /**
   * Format time in 24-hour format (HH:MM or HH:MM:SS)
   * Always returns 24-hour format regardless of locale settings
   * 
   * @param {Date|string} date - Date object or date string
   * @param {boolean} includeSeconds - Whether to include seconds in output (default: false)
   * @returns {string} Time in 24-hour format (HH:MM or HH:MM:SS)
   */
  formatTime(date, includeSeconds = false) {
    const time = (typeof window !== 'undefined') ? window.ArbeitszeitCheckTime : null;
    if (time && typeof time.formatTime === 'function') {
      return time.formatTime(date, includeSeconds ? { withSeconds: true } : undefined);
    }
    const d = new Date(date);
    if (isNaN(d.getTime())) {
      return '00:00' + (includeSeconds ? ':00' : '');
    }
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    if (includeSeconds) {
      const seconds = String(d.getSeconds()).padStart(2, '0');
      return `${hours}:${minutes}:${seconds}`;
    }
    return `${hours}:${minutes}`;
  },

  /**
   * Format date
   * Default format is DD.MM.YYYY for European users
   * Time is always formatted in 24-hour format (HH:mm)
   */
  formatDate(date, format = 'DD.MM.YYYY') {
    const time = (typeof window !== 'undefined') ? window.ArbeitszeitCheckTime : null;
    if (time && typeof time.formatWithMask === 'function') {
      return time.formatWithMask(date, format);
    }
    const d = new Date(date);
    if (isNaN(d.getTime())) {
      return '';
    }
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');
    return format
      .replace('YYYY', year)
      .replace('MM', month)
      .replace('DD', day)
      .replace('HH', hours)
      .replace('mm', minutes)
      .replace('ss', seconds);
  },

  /**
   * Format time duration (seconds to HH:MM:SS)
   */
  formatDuration(seconds) {
    const time = (typeof window !== 'undefined') ? window.ArbeitszeitCheckTime : null;
    if (time && typeof time.formatDuration === 'function') {
      return time.formatDuration(seconds);
    }
    const total = Math.max(0, Math.floor(Number(seconds) || 0));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  },

  /**
   * Format hours (decimal to HH:MM)
   */
  formatHours(hours) {
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return `${h}:${String(m).padStart(2, '0')}`;
  },

  /**
   * Get relative time (e.g., "2 hours ago")
   */
  relativeTime(date) {
    const time = (typeof window !== 'undefined') ? window.ArbeitszeitCheckTime : null;
    if (time && typeof time.relativeTime === 'function') {
      const tFn = (typeof window !== 'undefined' && typeof window.t === 'function')
        ? (s, vars) => window.t('arbeitszeitcheck', s, vars || {})
        : undefined;
      return time.relativeTime(date, tFn ? { t: tFn } : undefined);
    }

    const now = new Date();
    const diff = now - new Date(date);
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    const replaceVars = (template, vars = {}) =>
      template.replace(/\{(\w+)\}/g, (_, key) => (vars && key in vars ? vars[key] : `{${key}}`));

    const tFn = (typeof window !== 'undefined' && typeof window.t === 'function')
      ? (s, vars) => window.t('arbeitszeitcheck', s, vars || {})
      : (s, vars) => replaceVars(s, vars);

    const nFn = (typeof window !== 'undefined' && typeof window.n === 'function')
      ? (singular, plural, count, vars) => window.n('arbeitszeitcheck', singular, plural, count, vars || {})
      : (singular, plural, count, vars) => {
        const template = count === 1 ? singular : plural;
        const allVars = { count, ...(vars || {}) };
        return replaceVars(template, allVars);
      };

    if (days > 0) {
      return nFn('{count} day ago', '{count} days ago', days, { count: days });
    }
    if (hours > 0) {
      return nFn('{count} hour ago', '{count} hours ago', hours, { count: hours });
    }
    if (minutes > 0) {
      return nFn('{count} minute ago', '{count} minutes ago', minutes, { count: minutes });
    }
    return tFn('Just now');
  },

  // ===== STRING UTILITIES =====

  /**
   * Escape HTML
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },

  /**
   * Encode JSON for safe embedding in HTML double-quoted attributes.
   * Mirrors PHP `json_encode($x, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`.
   *
   * @param {unknown} value
   * @returns {string}
   */
  encodeAttributeJson(value) {
    return JSON.stringify(value)
      .replace(/</g, '\\u003C')
      .replace(/>/g, '\\u003E')
      .replace(/&/g, '\\u0026')
      .replace(/'/g, '\\u0027')
      .replace(/"/g, '\\u0022');
  },

  /**
   * Truncate string
   */
  truncate(str, length = 50, suffix = '...') {
    if (str.length <= length) return str;
    return str.substring(0, length) + suffix;
  },

  // ===== NUMBER UTILITIES =====

  /**
   * Format number with decimals
   */
  formatNumber(num, decimals = 2) {
    return Number(num).toFixed(decimals);
  },

  /**
   * Round to decimal places
   */
  round(value, decimals = 2) {
    const factor = Math.pow(10, decimals);
    return Math.round(value * factor) / factor;
  },

  // ===== FUNCTION UTILITIES =====

  /**
   * Debounce function
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  /**
   * Throttle function
   */
  throttle(func, limit) {
    let inThrottle;
    return function() {
      const args = arguments;
      const context = this;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  },

  /**
   * Whether a confirmDialog / showConfirmDialog result counts as accepted.
   *
   * @param {boolean|{confirmed?: boolean, reason?: string}|null|undefined} result
   * @returns {boolean}
   */
  isConfirmAccepted(result) {
    if (result === true) {
      return true;
    }
    return !!(result && typeof result === 'object' && result.confirmed);
  },

  /**
   * Reason text from a confirmDialog result, if any.
   *
   * @param {boolean|{confirmed?: boolean, reason?: string}|null|undefined} result
   * @returns {string}
   */
  confirmDialogReason(result, fallback = '') {
    if (result && typeof result === 'object' && typeof result.reason === 'string') {
      return result.reason.trim();
    }
    return fallback;
  },

  /**
   * Run confirmDialog; abort (null) when unavailable or cancelled.
   * Fail-closed: never treat a missing dialog as consent (audit-critical).
   *
   * @param {object} options confirmDialog options
   * @param {string} [unavailableMessage] shown when dialog API is missing
   * @returns {Promise<null|{confirmed: boolean, reason?: string}>}
   */
  async confirmDestructiveAction(options, unavailableMessage) {
    const confirmFn = (typeof window !== 'undefined')
      && (window.AzcComponents?.confirmDialog || window.ArbeitszeitCheckComponents?.confirmDialog);
    const fallbackMsg = (typeof window !== 'undefined' && window.t)
      ? window.t('arbeitszeitcheck', 'Confirmation dialog is not available. Please refresh the page and try again.')
      : 'Confirmation dialog is not available. Please refresh the page and try again.';
    const msg = unavailableMessage || fallbackMsg;

    if (!confirmFn) {
      if (typeof window !== 'undefined' && window.ArbeitszeitCheckMessaging) {
        window.ArbeitszeitCheckMessaging.showError?.(msg);
        window.ArbeitszeitCheckMessaging.announceAssertive?.(msg);
      }
      return null;
    }

    const result = await confirmFn(options);
    if (!this.isConfirmAccepted(result)) {
      return null;
    }
    if (result === true) {
      return { confirmed: true, reason: '' };
    }
    return result;
  },

  // ===== VALIDATION UTILITIES =====

  /**
   * Check if value is empty
   */
  isEmpty(value) {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    if (typeof value === 'object') return Object.keys(value).length === 0;
    return false;
  },

  /**
   * Check if value is email
   */
  isEmail(value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(value);
  },

  /**
   * Check if value is numeric
   */
  isNumeric(value) {
    return !isNaN(parseFloat(value)) && isFinite(value);
  },

  /**
   * Measured or themed offset from viewport top to below Nextcloud #header.
   * @returns {number} pixels
   */
  getHeaderOffsetPx() {
    const header = document.getElementById('header');
    if (header) {
      const rect = header.getBoundingClientRect();
      if (rect.height > 0) {
        return Math.ceil(rect.bottom);
      }
    }
    const raw = getComputedStyle(document.body).getPropertyValue('--header-height').trim();
    const parsed = parseFloat(raw);
    if (Number.isFinite(parsed) && parsed > 0) {
      return parsed;
    }
    return 50;
  },

  /**
   * Keep fixed overlays (calendar day panel, mobile nav) below the NC header.
   * Uses live #header measurement so the close control never sits under the profile menu.
   * @returns {number} applied top offset in pixels
   */
  syncAzcOverlayMetrics() {
    const top = this.getHeaderOffsetPx();
    document.body.style.setProperty('--azc-overlay-top', `${top}px`);
    document.body.style.setProperty('--azc-overlay-height', `calc(100dvh - ${top}px)`);
    return top;
  }
};

/**
 * Initialize common l10n strings for vanilla JS pages.
 *
 * We prefer Nextcloud's client-side translation function `t()` when available,
 * but keep safe English fallbacks. Templates can still override/extend
 * `window.ArbeitszeitCheck.l10n` with page-specific strings.
 */
(function initArbeitszeitCheckL10n() {
  if (typeof window === 'undefined') return;

  window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
  window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};

  const tt = (typeof window.t === 'function')
    ? (s) => window.t('arbeitszeitcheck', s)
    : (s) => s;

  const l10n = window.ArbeitszeitCheck.l10n;

  // Generic strings used across multiple pages / components
  l10n.loading = l10n.loading || tt('Loading...');
  l10n.error = l10n.error || tt('An error occurred');
  l10n.confirmDelete = l10n.confirmDelete || tt('Are you sure you want to delete this item?');

  // Timeline / calendar shared strings
  l10n.loadingTimeline = l10n.loadingTimeline || tt('Loading timeline...');
  l10n.noTimelineData = l10n.noTimelineData || tt('No timeline data available');
  l10n.selectAtLeastOneFilter = l10n.selectAtLeastOneFilter || tt('Select at least one type to display in the timeline.');
  l10n.loadingCalendar = l10n.loadingCalendar || tt('Loading calendar...');
  l10n.noEntries = l10n.noEntries || tt('No entries for this day');

  // Calendar labels (used for period header)
  l10n.months = l10n.months || [
    tt('January'), tt('February'), tt('March'), tt('April'),
    tt('May'), tt('June'), tt('July'), tt('August'),
    tt('September'), tt('October'), tt('November'), tt('December'),
  ];
  l10n.weekdays = l10n.weekdays || [
    tt('Sunday'), tt('Monday'), tt('Tuesday'), tt('Wednesday'),
    tt('Thursday'), tt('Friday'), tt('Saturday'),
  ];
  l10n.weekdaysShort = l10n.weekdaysShort || [
    tt('Sun'), tt('Mon'), tt('Tue'), tt('Wed'),
    tt('Thu'), tt('Fri'), tt('Sat'),
  ];
  l10n.openCalendar = l10n.openCalendar || tt('Open calendar');
  l10n.previousMonth = l10n.previousMonth || tt('Previous month');
  l10n.nextMonth = l10n.nextMonth || tt('Next month');
})();

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.ArbeitszeitCheckUtils = ArbeitszeitCheckUtils;
}
