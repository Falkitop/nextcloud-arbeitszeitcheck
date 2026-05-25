import { describe, expect, it, vi } from 'vitest'

// utils.js attaches itself to window.ArbeitszeitCheckUtils
import './utils.js'

describe('ArbeitszeitCheckUtils', () => {
  it('escapeHtml escapes unsafe characters', () => {
    const u = window.ArbeitszeitCheckUtils
    expect(u.escapeHtml('<script>alert("x")</script>')).toBe('&lt;script&gt;alert("x")&lt;/script&gt;')
  })

  it('encodeAttributeJson hex-escapes quotes for HTML attributes', () => {
    const u = window.ArbeitszeitCheckUtils
    const encoded = u.encodeAttributeJson({ startTime: '2026-05-20T08:00:00+02:00' })
    expect(encoded).not.toContain('"')
    expect(encoded).toContain('\\u0022startTime\\u0022')
    expect(encoded).toContain('2026-05-20T08:00:00+02:00')
  })

  it('createElement sets className and textContent and avoids implicit html', () => {
    const u = window.ArbeitszeitCheckUtils
    const el = u.createElement('div', { className: 'x', textContent: '<b>hi</b>' })
    expect(el.className).toBe('x')
    expect(el.textContent).toBe('<b>hi</b>')
    expect(el.innerHTML).toBe('&lt;b&gt;hi&lt;/b&gt;')
  })

  it('formatTime returns 24h time and handles invalid dates', () => {
    const u = window.ArbeitszeitCheckUtils
    expect(u.formatTime('invalid')).toBe('00:00')
    expect(u.formatTime('2024-01-01T09:05:07Z')).toMatch(/^\d{2}:\d{2}$/)
    expect(u.formatTime('2024-01-01T09:05:07Z', true)).toMatch(/^\d{2}:\d{2}:\d{2}$/)
  })

  it('debounce delays invocation until wait elapsed', async () => {
    vi.useFakeTimers()
    const u = window.ArbeitszeitCheckUtils
    const fn = vi.fn()
    const debounced = u.debounce(fn, 100)

    debounced(1)
    debounced(2)
    expect(fn).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(99)
    expect(fn).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1)
    expect(fn).toHaveBeenCalledTimes(1)
    expect(fn).toHaveBeenCalledWith(2)
    vi.useRealTimers()
  })

  it('resolveUrl normalizes app paths through OC.generateUrl', () => {
    const u = window.ArbeitszeitCheckUtils
    const originalGenerateUrl = window.OC.generateUrl
    const spy = vi.fn((path) => '/index.php' + path)
    window.OC.generateUrl = spy

    expect(u.resolveUrl('/apps/arbeitszeitcheck/api/admin/users')).toBe('/index.php/apps/arbeitszeitcheck/api/admin/users')
    expect(spy).toHaveBeenCalledWith('/apps/arbeitszeitcheck/api/admin/users')

    // Non-app absolute path must pass through unchanged.
    expect(u.resolveUrl('/ocs/v2.php/apps/notifications/api/v2/notifications')).toBe('/ocs/v2.php/apps/notifications/api/v2/notifications')

    window.OC.generateUrl = originalGenerateUrl
  })

  it('toSameOriginPath strips same-origin absolute URLs to root-relative paths', () => {
    const u = window.ArbeitszeitCheckUtils
    const origin = window.location.origin
    expect(u.toSameOriginPath(origin + '/index.php/apps/arbeitszeitcheck/api/x')).toBe('/index.php/apps/arbeitszeitcheck/api/x')
    expect(u.toSameOriginPath('/apps/arbeitszeitcheck/api/x')).toBe('/apps/arbeitszeitcheck/api/x')
    expect(u.toSameOriginPath('https://example.org/ping')).toBe('https://example.org/ping')
  })

  it('resolveUrl preserves already normalized /index.php app paths', () => {
    const u = window.ArbeitszeitCheckUtils
    expect(u.resolveUrl('/index.php/apps/arbeitszeitcheck/api/admin/teams')).toBe('/index.php/apps/arbeitszeitcheck/api/admin/teams')
  })

  it('resolveUrl falls back to /index.php prefix when OC is unavailable', () => {
    const u = window.ArbeitszeitCheckUtils
    const previousWindowOc = window.OC
    const previousGlobalOc = globalThis.OC

    // Simulate page context with /index.php routing and no OC helpers.
    Object.defineProperty(window, 'location', {
      value: { origin: 'https://example.test', protocol: 'https:', pathname: '/index.php/apps/arbeitszeitcheck/admin/teams' },
      configurable: true,
    })
    window.OC = undefined
    globalThis.OC = undefined

    expect(u.resolveUrl('/apps/arbeitszeitcheck/api/admin/teams')).toBe('/index.php/apps/arbeitszeitcheck/api/admin/teams')

    window.OC = previousWindowOc
    globalThis.OC = previousGlobalOc
  })

  it('isExternalUrl distinguishes same-origin from external origins', () => {
    const u = window.ArbeitszeitCheckUtils
    expect(u.isExternalUrl('/apps/arbeitszeitcheck/api/admin/users')).toBe(false)
    expect(u.isExternalUrl(window.location.origin + '/apps/arbeitszeitcheck/api/admin/users')).toBe(false)
    expect(u.isExternalUrl('https://example.org/apps/arbeitszeitcheck/api/admin/users')).toBe(true)
  })

  it('ajax blocks external URLs by default', async () => {
    const u = window.ArbeitszeitCheckUtils
    const fetchSpy = vi.spyOn(globalThis, 'fetch')

    await expect(u.ajax('https://example.org/ping')).rejects.toThrow('External URL blocked')
    expect(fetchSpy).not.toHaveBeenCalled()

    fetchSpy.mockRestore()
  })

  it('ajax allows external URLs when explicitly opted in', async () => {
    const u = window.ArbeitszeitCheckUtils
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: true,
      json: async () => ({ success: true })
    })

    const data = await u.ajax('https://example.org/ping', { allowExternal: true })
    expect(data).toEqual({ success: true })
    expect(fetchSpy).toHaveBeenCalledTimes(1)

    fetchSpy.mockRestore()
  })

  it('ajax surfaces session expiry on 412 with a stable message', async () => {
    const u = window.ArbeitszeitCheckUtils
    const showError = vi.fn()
    window.ArbeitszeitCheckMessaging = { showError }
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: false,
      status: 412,
      json: async () => ({ error: 'CSRF check failed' })
    })

    const onError = vi.fn()
    await u.ajax('/apps/arbeitszeitcheck/api/clock/status', { onError })
    expect(onError).toHaveBeenCalledTimes(1)
    expect(onError.mock.calls[0][0].status).toBe(412)
    expect(onError.mock.calls[0][0].error).toContain('session expired')
    expect(showError).toHaveBeenCalledTimes(1)

    delete window.ArbeitszeitCheckMessaging
    fetchSpy.mockRestore()
  })

  it('isConfirmAccepted accepts boolean true and confirmed objects', () => {
    const u = window.ArbeitszeitCheckUtils
    expect(u.isConfirmAccepted(true)).toBe(true)
    expect(u.isConfirmAccepted({ confirmed: true, reason: 'ok' })).toBe(true)
    expect(u.isConfirmAccepted(false)).toBe(false)
    expect(u.isConfirmAccepted({ confirmed: false })).toBe(false)
  })

  it('confirmDialogReason returns trimmed reason or fallback', () => {
    const u = window.ArbeitszeitCheckUtils
    expect(u.confirmDialogReason({ confirmed: true, reason: '  audit  ' })).toBe('audit')
    expect(u.confirmDialogReason(false, 'user_request')).toBe('user_request')
  })

  it('isApiSuccess respects HTTP ok and JSON success flag', async () => {
    await import('./api.js')
    const api = window.AzcApi
    expect(api.isApiSuccess({ ok: true, data: { success: true } })).toBe(true)
    expect(api.isApiSuccess({ ok: true, data: {} })).toBe(true)
    expect(api.isApiSuccess({ ok: false, data: { success: true } })).toBe(false)
    expect(api.isApiSuccess({ ok: true, data: { success: false } })).toBe(false)
    expect(api.isApiSuccess({ ok: true, data: { ok: false } })).toBe(false)
  })

  it('confirmDestructiveAction fails closed when dialog API is missing', async () => {
    const u = window.ArbeitszeitCheckUtils
    const showError = vi.fn()
    window.ArbeitszeitCheckMessaging = { showError, announceAssertive: vi.fn() }
    delete window.AzcComponents
    delete window.ArbeitszeitCheckComponents

    const result = await u.confirmDestructiveAction({ title: 'T', message: 'M' })
    expect(result).toBeNull()
    expect(showError).toHaveBeenCalledTimes(1)

    delete window.ArbeitszeitCheckMessaging
  })
})

