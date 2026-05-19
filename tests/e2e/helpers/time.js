/**
 * Parse `HH:MM:SS` or `H:MM` duration strings rendered by the dashboard timer.
 *
 * @param {string} text
 * @returns {number|null} Total seconds, or null when the text is not a duration.
 */
export function parseDurationHms(text) {
  const raw = (text || '').trim()
  const long = raw.match(/^(\d+):(\d{2}):(\d{2})$/)
  if (long) {
    return parseInt(long[1], 10) * 3600 + parseInt(long[2], 10) * 60 + parseInt(long[3], 10)
  }
  const short = raw.match(/^(\d+):(\d{2})$/)
  if (short) {
    return parseInt(short[1], 10) * 3600 + parseInt(short[2], 10) * 60
  }
  return null
}

/**
 * Returns true when `value` looks like an ISO-8601 instant with explicit offset.
 *
 * @param {string} value
 * @returns {boolean}
 */
export function isIsoInstantWithOffset(value) {
  return typeof value === 'string'
    && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/.test(value)
}
