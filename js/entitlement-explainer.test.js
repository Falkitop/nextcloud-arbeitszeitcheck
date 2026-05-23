import { beforeEach, describe, expect, it, vi } from 'vitest'

const BOOTSTRAP = {
  traceUrl: '/index.php/apps/arbeitszeitcheck/api/absences/entitlement-trace',
  individualRule: 'Individual rule',
  teamPolicy: 'Team policy',
  workingTimeModel: 'Working time model',
  organisationDefault: 'Organisation default',
  defaultFallback: 'Default fallback',
  applied: 'Applied',
  skipped: 'Skipped',
  partialHistoryHint: 'partial hint',
  degradedBanner: 'degraded',
  clampedBanner: 'clamped',
  summaryTitle: 'Summary',
  daysPerYear: 'days per year',
  asOfDate: 'Calculated as of %s',
  chainTitle: 'Chain',
  layerDeterminedLead: 'Determined by:',
  hintContactHr: 'Contact HR',
  loading: 'Loading…',
  loadError: 'Load failed',
  closeLabel: 'Close'
}

function mountDialog() {
  document.body.innerHTML = `
    <div id="arbeitszeitcheck-app"></motion>
    <button type="button" id="entitlement-explain">Explain</button>
    <dialog id="entitlement-explain-dialog" class="entitlement-explain-dialog" aria-modal="true">
      <motion class="entitlement-explain-dialog__panel">
        <button type="button" id="entitlement-explain-dismiss" aria-label="Close">X</button>
        <motion id="entitlement-explain-body"></motion>
        <button type="button" id="entitlement-explain-retry" hidden>Retry</button>
        <form method="dialog">
          <button type="submit" id="entitlement-explain-close">Close</button>
        </form>
      </motion>
    </dialog>
    <script id="entitlement-explainer-bootstrap" type="application/json">${JSON.stringify(BOOTSTRAP)}</script>
  `.replace(/<\/motion>/g, '</div>').replace(/<motion/g, '<div')

  if (typeof HTMLDialogElement !== 'undefined' && typeof HTMLDialogElement.prototype.showModal !== 'function') {
    HTMLDialogElement.prototype.showModal = function showModal() {
      this.setAttribute('open', 'open')
      this.open = true
    }
    HTMLDialogElement.prototype.close = function close() {
      this.removeAttribute('open')
      this.open = false
      this.dispatchEvent(new Event('close'))
    }
  }
}

describe('entitlement-explainer', () => {
  beforeEach(async () => {
    mountDialog()
    vi.resetModules()
    window.ArbeitszeitCheck = {}
    window.ArbeitszeitCheckUtils = {
      ajax: vi.fn(),
      resolveUrl: (u) => u,
      toSameOriginPath: (u) => u,
      getRequestToken: () => 'token',
      escapeHtml: (s) => String(s).replace(/&/g, '&amp;')
    }
    await import('./entitlement-explainer.js')
  })

  it('loads trace and renders summary on trigger click', async () => {
    window.ArbeitszeitCheckUtils.ajax.mockResolvedValue({
      success: true,
      asOfDate: '2026-05-22',
      effectiveEntitlementDays: 28,
      matchedLayer: 'L2',
      trace: {
        layers_evaluated: [
          { layer: 'L3', matched: false },
          { layer: 'L2', matched: true }
        ]
      }
    })

    document.getElementById('entitlement-explain').click()
    await vi.waitFor(() => {
      const body = document.getElementById('entitlement-explain-body')
      expect(body.innerHTML).toContain('28')
      expect(body.innerHTML).toContain('Team policy')
      expect(body.querySelector('.entitlement-explain-dialog__step--applied')).toBeTruthy()
    })
    expect(window.ArbeitszeitCheckUtils.ajax).toHaveBeenCalled()
  })

  it('closes dialog when dismiss is clicked', async () => {
    const dlg = document.getElementById('entitlement-explain-dialog')
    dlg.showModal()
    document.getElementById('entitlement-explain-dismiss').click()
    expect(dlg.open).toBe(false)
  })

  it('shows error and retry when API fails', async () => {
    window.ArbeitszeitCheckUtils.ajax.mockRejectedValue(new Error('network'))
    document.getElementById('entitlement-explain').click()
    await vi.waitFor(() => {
      const body = document.getElementById('entitlement-explain-body')
      expect(body.querySelector('.entitlement-explain-dialog__error')).toBeTruthy()
    })
    const retry = document.getElementById('entitlement-explain-retry')
    expect(retry.hidden).toBe(false)
  })
})
