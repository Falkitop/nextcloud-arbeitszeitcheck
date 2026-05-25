import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

describe('ArbeitszeitCheckComponents confirmDialog', () => {
  beforeEach(async () => {
    document.body.innerHTML = '<div id="app-content" data-azc-html-lang="en"></div>'
    vi.resetModules()
    await import('./components.js')
  })

  afterEach(() => {
    document.body.innerHTML = ''
    document.body.style.overflow = ''
    if (window.ArbeitszeitCheckComponents) {
      window.ArbeitszeitCheckComponents._modalLockDepth = 0
    }
  })

  it('resolves false when cancel is clicked', async () => {
    const Components = window.ArbeitszeitCheckComponents
    const promise = Components.confirmDialog({
      title: 'Delete entry',
      message: 'This cannot be undone.',
      variant: 'danger',
    })

    const cancel = document.querySelector('.confirm-dialog__cancel')
    expect(cancel).toBeTruthy()
    cancel.click()

    await expect(promise).resolves.toBe(false)
    expect(document.getElementById('app-content').getAttribute('aria-hidden')).toBeNull()
  })

  it('resolves with confirmed payload when confirm is clicked', async () => {
    const Components = window.ArbeitszeitCheckComponents
    const promise = Components.confirmDialog({
      title: 'Mark as fixed',
      message: 'Confirm resolution.',
      variant: 'primary',
      requireReason: true,
    })

    const reason = document.querySelector('.confirm-dialog__reason')
    const confirm = document.querySelector('.confirm-dialog__confirm')
    expect(reason).toBeTruthy()
    expect(confirm).toBeTruthy()

    reason.value = 'Reviewed with employee'
    reason.dispatchEvent(new Event('input', { bubbles: true }))
    confirm.click()

    await expect(promise).resolves.toEqual({
      confirmed: true,
      reason: 'Reviewed with employee',
    })
  })

  it('shows the requested typed-confirm phrase in the label', async () => {
    const Components = window.ArbeitszeitCheckComponents
    Components.confirmDialog({
      title: 'Remove team',
      message: 'Permanent removal.',
      variant: 'danger',
      requireTypedConfirm: true,
      typedConfirmPhrase: 'REMOVE',
    })

    const label = document.querySelector('label[for$="-typed"]')
    expect(label).toBeTruthy()
    expect(label.textContent).toContain('REMOVE')
    expect(label.textContent).not.toMatch(/Type DELETE to confirm/i)

    document.querySelector('.confirm-dialog__cancel')?.click()
  })

  it('sets aria-hidden on app content while open', async () => {
    const Components = window.ArbeitszeitCheckComponents
    const promise = Components.confirmDialog({
      title: 'Finalize month',
      message: 'Lock this month.',
      variant: 'danger',
      requireTypedConfirm: true,
      typedConfirmPhrase: 'DELETE',
    })

    expect(document.getElementById('app-content').getAttribute('aria-hidden')).toBe('true')

    document.querySelector('.confirm-dialog__cancel')?.click()
    await promise
  })

  it('openModal locks scroll, traps focus, and closeModal restores the page', async () => {
    const Components = window.ArbeitszeitCheckComponents
    const modal = Components.createModal({
      id: 'test-open-modal',
      title: 'Test modal',
      content: '<button type="button" id="test-modal-first">First</button><button type="button" id="test-modal-last">Last</button>',
      size: 'sm',
    })

    Components.openModal(modal)
    expect(document.body.style.overflow).toBe('hidden')
    expect(document.getElementById('app-content').getAttribute('aria-hidden')).toBe('true')

    await new Promise((r) => setTimeout(r, 60))
    expect(modal.contains(document.activeElement)).toBe(true)

    Components.closeModal(modal)
    await new Promise((r) => setTimeout(r, 350))

    expect(document.getElementById('test-open-modal')).toBeNull()
    expect(document.body.style.overflow).toBe('')
    expect(document.getElementById('app-content').getAttribute('aria-hidden')).toBeNull()
    expect(Components._modalLockDepth).toBe(0)
  })
})
