import { describe, expect, it, vi } from 'vitest'

// utils.js attaches itself to window.ArbeitszeitCheckUtils
import './utils.js'

describe('ArbeitszeitCheckUtils', () => {
  it('escapeHtml escapes unsafe characters', () => {
    const u = window.ArbeitszeitCheckUtils
    expect(u.escapeHtml('<script>alert("x")</script>')).toBe('&lt;script&gt;alert("x")&lt;/script&gt;')
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
})

