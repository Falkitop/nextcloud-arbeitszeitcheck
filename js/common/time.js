/**
 * Single source of truth for date/time handling on the ArbeitszeitCheck client.
 *
 * Exposed as `window.ArbeitszeitCheckTime`. Every JS file that parses or
 * renders a datetime must go through this module.
 *
 * Mental model (same as the PHP `TimeZoneService`):
 *
 *   - **API instants** travel as ISO-8601 with explicit offset
 *     (`2026-05-19T11:45:57+02:00`). Parse with {@link parseInstant} into a
 *     native `Date`. Never use `new Date(string)` on hand-built JSON — it has
 *     subtle UTC interpretation pitfalls (see `2026-05-19` shifting to the
 *     previous day in negative-UTC zones).
 *   - **Calendar dates** travel as `YYYY-MM-DD`. Parse with {@link parseYmd}
 *     so the resulting `Date` is anchored at local noon (DST-safe) instead
 *     of UTC midnight (which can shift the calendar day in non-UTC zones).
 *   - **Display** always happens in the user's display timezone (configured
 *     server-side from their Nextcloud personal setting and emitted into the
 *     page via `window.ArbeitszeitCheck.tz`). When that bootstrap is not
 *     available the browser local zone is used as a safe fallback.
 *   - **Live timer** uses the server clock as anchor (`serverNow`) plus a
 *     monotonic delta from `performance.now()`. This eliminates the entire
 *     class of "client clock drifted by X seconds" timer bugs.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

(function (root) {
  'use strict';

  if (root.ArbeitszeitCheckTime) {
    return; // idempotent
  }

  /* --------------------------------------------------------------- *
   * Internal state                                                  *
   * --------------------------------------------------------------- */

  // Monotonic anchor used by the drift-safe timer. `serverNowMs` is the UTC
  // milliseconds the server reported via `server_now`; `monotonicAnchorMs` is
  // the `performance.now()` value captured at the same moment. We extrapolate
  // server time as `serverNowMs + (performance.now() - monotonicAnchorMs)`,
  // which is immune to:
  //   - client wall-clock skew (the user's PC clock being off),
  //   - the user changing the system clock during the session,
  //   - the browser throttling `setInterval` callbacks while the tab is in
  //     the background (the next tick simply observes the bigger delta).
  let serverNowMs = null;
  let monotonicAnchorMs = null;

  /* --------------------------------------------------------------- *
   * Configuration                                                   *
   * --------------------------------------------------------------- */

  /**
   * Read the server-injected timezone configuration. The PHP template
   * exposes `window.ArbeitszeitCheck.tz = { storage: '...', display: '...' }`
   * once `templates/common/time-bootstrap.php` is included.
   */
  function readConfig() {
    const ns = root.ArbeitszeitCheck || {};
    const tz = ns.tz || {};
    return {
      storage: typeof tz.storage === 'string' && tz.storage ? tz.storage : null,
      display: typeof tz.display === 'string' && tz.display ? tz.display : null,
    };
  }

  function safeIntl(timeZone, options) {
    try {
      const opts = Object.assign({ timeZone }, options);
      return new Intl.DateTimeFormat('en-GB', opts);
    } catch (_) {
      // Fallback: drop timeZone if invalid (e.g. typo in admin setting).
      return new Intl.DateTimeFormat('en-GB', options);
    }
  }

  /* --------------------------------------------------------------- *
   * Parsing                                                         *
   * --------------------------------------------------------------- */

  /**
   * Parse an ISO-8601 datetime with explicit offset (the canonical API
   * format produced by PHP `DateTime::format('c')`). Accepts an existing
   * `Date` instance for convenience.
   *
   * @param {string|number|Date|null|undefined} value
   * @returns {Date|null}
   */
  function parseInstant(value) {
    if (value == null || value === '') return null;
    if (value instanceof Date) {
      return Number.isNaN(value.getTime()) ? null : value;
    }
    if (typeof value === 'number') {
      const d = new Date(value);
      return Number.isNaN(d.getTime()) ? null : d;
    }
    const s = String(value).trim();
    if (!s) return null;
    const ms = Date.parse(s);
    if (!Number.isFinite(ms)) return null;
    return new Date(ms);
  }

  /**
   * Parse a strict `YYYY-MM-DD` calendar date. The returned `Date` is
   * anchored at **local noon** so DST transitions (which only ever happen
   * around 02:00 / 03:00) cannot shift the calendar day.
   *
   * @param {string} value
   * @returns {Date|null}
   */
  function parseYmd(value) {
    if (typeof value !== 'string') return null;
    const m = value.trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return null;
    const y = parseInt(m[1], 10);
    const mo = parseInt(m[2], 10);
    const d = parseInt(m[3], 10);
    const date = new Date(y, mo - 1, d, 12, 0, 0, 0);
    if (date.getFullYear() !== y || date.getMonth() !== mo - 1 || date.getDate() !== d) {
      return null;
    }
    return date;
  }

  /* --------------------------------------------------------------- *
   * Formatting (all output respects user display TZ)                *
   * --------------------------------------------------------------- */

  function pickDisplayTz(override) {
    if (override) return override;
    const cfg = readConfig();
    return cfg.display || cfg.storage || undefined;
  }

  /**
   * `HH:mm` of the given instant in the display TZ.
   *
   * @param {Date|string|number|null|undefined} value
   * @param {{ withSeconds?: boolean, timeZone?: string }} [options]
   */
  function formatTime(value, options) {
    const d = parseInstant(value);
    if (!d) return '';
    const opts = options || {};
    const fmt = safeIntl(pickDisplayTz(opts.timeZone), {
      hour: '2-digit',
      minute: '2-digit',
      second: opts.withSeconds ? '2-digit' : undefined,
      hour12: false,
    });
    return fmt.format(d);
  }

  /**
   * `dd.MM.yyyy` of the given instant in the display TZ. Accepts both ISO
   * instants and `YYYY-MM-DD` calendar strings.
   */
  function formatDate(value, options) {
    let d;
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value.trim())) {
      d = parseYmd(value);
    } else {
      d = parseInstant(value);
    }
    if (!d) return '';
    const opts = options || {};
    const fmt = safeIntl(pickDisplayTz(opts.timeZone), {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    });
    // `en-GB` returns `19/05/2026`; we want `19.05.2026` to match the rest
    // of the German-leaning UI without introducing a hard locale dep.
    return fmt.format(d).replace(/\//g, '.');
  }

  /**
   * `dd.MM.yyyy HH:mm` of the given instant in the display TZ.
   */
  function formatDateTime(value, options) {
    const d = parseInstant(value);
    if (!d) return '';
    const opts = options || {};
    const date = formatDate(d, { timeZone: opts.timeZone });
    const time = formatTime(d, { timeZone: opts.timeZone, withSeconds: !!opts.withSeconds });
    return date && time ? (date + ' ' + time) : (date || time || '');
  }

  /**
   * `HH:mm:ss` of a non-negative duration in seconds.
   */
  function formatDuration(seconds) {
    const total = Math.max(0, Math.floor(Number(seconds) || 0));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const pad = (n) => (n < 10 ? '0' + n : String(n));
    return pad(h) + ':' + pad(m) + ':' + pad(s);
  }

  /**
   * Today's `YYYY-MM-DD` in the display TZ. Use this — never
   * `new Date().toISOString().slice(0, 10)` — when emitting date filters or
   * comparing to stored calendar dates, because `toISOString()` shifts to
   * UTC and silently rolls the calendar day for users west of UTC.
   */
  function todayYmd(options) {
    const tz = pickDisplayTz(options && options.timeZone);
    const fmt = safeIntl(tz, { year: 'numeric', month: '2-digit', day: '2-digit' });
    const parts = fmt.formatToParts(new Date());
    let y = '', m = '', d = '';
    parts.forEach((p) => {
      if (p.type === 'year') y = p.value;
      else if (p.type === 'month') m = p.value;
      else if (p.type === 'day') d = p.value;
    });
    if (y && m && d) return y + '-' + m + '-' + d;
    // Defensive fallback if Intl returned an unexpected shape.
    const now = new Date();
    return (
      now.getFullYear() +
      '-' +
      String(now.getMonth() + 1).padStart(2, '0') +
      '-' +
      String(now.getDate()).padStart(2, '0')
    );
  }

  /**
   * `YYYY-MM-DD` of the given instant/date in the display TZ.
   */
  function ymd(value, options) {
    const d = value instanceof Date ? value : parseInstant(value) || parseYmd(value);
    if (!d) return '';
    const tz = pickDisplayTz(options && options.timeZone);
    const fmt = safeIntl(tz, { year: 'numeric', month: '2-digit', day: '2-digit' });
    const parts = fmt.formatToParts(d);
    let y = '', m = '', day = '';
    parts.forEach((p) => {
      if (p.type === 'year') y = p.value;
      else if (p.type === 'month') m = p.value;
      else if (p.type === 'day') day = p.value;
    });
    if (y && m && day) return y + '-' + m + '-' + day;
    return (
      d.getFullYear() +
      '-' +
      String(d.getMonth() + 1).padStart(2, '0') +
      '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }

  /* --------------------------------------------------------------- *
   * Drift-safe "server now"                                         *
   * --------------------------------------------------------------- */

  /**
   * Anchor the client to the server clock using the `server_now` field
   * returned by the status API.
   *
   * The anchor uses `performance.now()` for the monotonic component, so:
   *
   *  - The user's local clock being wrong by minutes/hours has no effect on
   *    any duration we compute against the server anchor.
   *  - A user travelling between timezones (or DST flipping mid-session) is
   *    handled by re-anchoring on the next status response.
   *  - Background-tab throttling, which causes `setInterval` to fire late,
   *    naturally heals because the next tick observes the correct delta.
   *
   * @param {string|Date|null|undefined} serverNowIso
   */
  function syncFromServer(serverNowIso) {
    const parsed = parseInstant(serverNowIso);
    if (!parsed) return;
    serverNowMs = parsed.getTime();
    monotonicAnchorMs = (typeof performance !== 'undefined' && performance.now)
      ? performance.now()
      : Date.now();
  }

  /**
   * Whether {@link syncFromServer} has been called at least once.
   */
  function hasServerAnchor() {
    return serverNowMs !== null && monotonicAnchorMs !== null;
  }

  /**
   * Current UTC milliseconds according to the server, or a best-effort
   * fallback to the local clock when no anchor has been established yet.
   */
  function serverNowMillis() {
    if (serverNowMs === null || monotonicAnchorMs === null) {
      return Date.now();
    }
    const monotonic = (typeof performance !== 'undefined' && performance.now)
      ? performance.now()
      : Date.now();
    return serverNowMs + (monotonic - monotonicAnchorMs);
  }

  /**
   * Current "server now" as a native `Date`.
   */
  function serverNow() {
    return new Date(serverNowMillis());
  }

  /**
   * Elapsed seconds between an absolute instant and the (drift-safe)
   * current server time. Returns `0` if the inputs are missing or the
   * instant is in the future (clock skew safety net).
   */
  function secondsSince(instantValue) {
    const d = parseInstant(instantValue);
    if (!d) return 0;
    const diff = Math.floor((serverNowMillis() - d.getTime()) / 1000);
    return diff > 0 ? diff : 0;
  }

  /**
   * Format using mask tokens in the user's display TZ.
   *
   * Supported tokens: `YYYY`, `MM`, `DD`, `HH`, `mm`, `ss`.
   * This is the backing implementation for legacy
   * `ArbeitszeitCheckUtils.formatDate(mask)` call sites.
   *
   * @param {Date|string|number|null|undefined} value
   * @param {string} mask
   * @param {{ timeZone?: string }} [options]
   */
  function formatWithMask(value, mask, options) {
    const d = parseInstant(value) || parseYmd(typeof value === 'string' ? value : '');
    if (!d || !mask) {
      return '';
    }
    const opts = options || {};
    const fmt = safeIntl(pickDisplayTz(opts.timeZone), {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    });
    const parts = fmt.formatToParts(d);
    const map = { year: '', month: '', day: '', hour: '00', minute: '00', second: '00' };
    parts.forEach((p) => {
      if (p.type === 'year') map.year = p.value;
      else if (p.type === 'month') map.month = p.value;
      else if (p.type === 'day') map.day = p.value;
      else if (p.type === 'hour') map.hour = p.value;
      else if (p.type === 'minute') map.minute = p.value;
      else if (p.type === 'second') map.second = p.value;
    });
    return String(mask)
      .replace(/YYYY/g, map.year)
      .replace(/MM/g, map.month)
      .replace(/DD/g, map.day)
      .replace(/HH/g, map.hour)
      .replace(/mm/g, map.minute)
      .replace(/ss/g, map.second);
  }

  /**
   * Human-readable relative time (e.g. "2 hours ago"). Uses the drift-safe
   * server clock when anchored so "now" matches the backend, not a skewed
   * client wall clock.
   *
   * @param {Date|string|number|null|undefined} value
   * @param {{ t?: function(string, Object=): string }} [options]
   */
  function relativeTime(value, options) {
    const d = parseInstant(value);
    if (!d) {
      return '';
    }
    const opts = options || {};
    const tFn = typeof opts.t === 'function'
      ? opts.t
      : (typeof root.t === 'function' ? (s, vars) => root.t('arbeitszeitcheck', s, vars || {}) : null);

    const replaceVars = (template, vars) =>
      String(template).replace(/\{(\w+)\}/g, (_, key) => (vars && key in vars ? vars[key] : `{${key}}`));

    const nowMs = serverNowMillis();
    const diff = nowMs - d.getTime();
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (tFn) {
      if (days > 0) return tFn('{n} days ago', { n: days });
      if (hours > 0) return tFn('{n} hours ago', { n: hours });
      if (minutes > 0) return tFn('{n} minutes ago', { n: minutes });
      return tFn('just now');
    }

    if (days > 0) return replaceVars('{n} days ago', { n: days });
    if (hours > 0) return replaceVars('{n} hours ago', { n: hours });
    if (minutes > 0) return replaceVars('{n} minutes ago', { n: minutes });
    return 'just now';
  }

  /** Alias for {@link parseInstant} — documents the PHP API contract (`format('c')`). */
  const parseApiInstant = parseInstant;

  /* --------------------------------------------------------------- *
   * Export                                                          *
   * --------------------------------------------------------------- */

  const api = {
    // Parsing
    parseInstant,
    parseApiInstant,
    parseYmd,
    // Formatting (display TZ)
    formatTime,
    formatDate,
    formatDateTime,
    formatWithMask,
    formatDuration,
    todayYmd,
    ymd,
    relativeTime,
    // Drift-safe server clock
    syncFromServer,
    hasServerAnchor,
    serverNow,
    serverNowMillis,
    secondsSince,
    // Configuration accessor (read-only snapshot)
    config: readConfig,
  };

  root.ArbeitszeitCheckTime = api;

  /**
   * Apply bootstrap from InitialState when the init script has not run yet
   * (e.g. script load order edge cases).
   */
  function applyBootstrapFromInitialState() {
    try {
      if (!root.OC || !root.OC.initialState || typeof root.OC.initialState.loadState !== 'function') {
        return;
      }
      const cfg = root.OC.initialState.loadState('arbeitszeitcheck', 'time');
      if (!cfg || typeof cfg !== 'object') {
        return;
      }
      root.ArbeitszeitCheck = root.ArbeitszeitCheck || {};
      if (cfg.tz && typeof cfg.tz === 'object') {
        root.ArbeitszeitCheck.tz = Object.assign({}, root.ArbeitszeitCheck.tz || {}, cfg.tz);
      }
      if (typeof cfg.serverNow === 'string' && cfg.serverNow !== '') {
        root.ArbeitszeitCheck.serverNow = cfg.serverNow;
      }
    } catch (_) {
      // Non-fatal — display falls back to browser local TZ until status poll.
    }
  }

  // Auto-sync from InitialState or inline bootstrap (`time-init.js` /
  // legacy template). Ensures the timer never starts against a skewed client clock.
  try {
    if (!root.ArbeitszeitCheck || !root.ArbeitszeitCheck.serverNow) {
      applyBootstrapFromInitialState();
    }
    const ns = root.ArbeitszeitCheck;
    if (ns && ns.serverNow) {
      syncFromServer(ns.serverNow);
    }
  } catch (_) {
    // Bootstrap missing — page either does not need a timer or will
    // anchor explicitly on its first status poll. Nothing to do here.
  }
})(typeof window !== 'undefined' ? window : globalThis);
