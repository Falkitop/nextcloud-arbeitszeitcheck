import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Regression coverage for the destructive-confirmation wrapper used by the
 * time-entry delete, paused-completion and absence-cancel flows.
 *
 * In Nextcloud 31+ OC.dialogs.confirmDestructive() returns a Promise whose
 * resolution value is ALWAYS `undefined` (its internal `.then(() => { ... })`
 * has no `return` statement) — the user's actual choice is delivered via the
 * callback parameter only. A previous version of `confirmDestructiveMain`
 * trusted the Promise value, which caused every confirmation to resolve as
 * "cancelled" and silently swallowed the destructive action (e.g. clicking
 * "Yes" in the delete-time-entry dialog did nothing). These tests pin the
 * correct behaviour so the bug cannot reappear.
 */

const snapshot = {}

beforeEach(async () => {
  // The IIFE in arbeitszeitcheck-main.js captures `window.OC` by reference at
  // import time, so we mutate that same object instead of replacing it.
  snapshot.OCDialogs = globalThis.OC ? globalThis.OC.dialogs : undefined
  snapshot.AzcComponents = globalThis.window.AzcComponents
  snapshot.ArbeitszeitCheckComponents = globalThis.window.ArbeitszeitCheckComponents
  snapshot.ArbeitszeitCheck = globalThis.window.ArbeitszeitCheck
  snapshot.ArbeitszeitCheckApp = globalThis.window.ArbeitszeitCheckApp
  vi.resetModules()

  // Load the IIFE module which attaches `window.ArbeitszeitCheckApp`.
  // Set `page: 'none'` so init() does not try to wire up real DOM listeners.
  globalThis.window.ArbeitszeitCheck = { page: 'none' }
  delete globalThis.window.AzcComponents
  delete globalThis.window.ArbeitszeitCheckComponents
  await import('./arbeitszeitcheck-main.js')
})

afterEach(() => {
  if (globalThis.OC) {
    if (snapshot.OCDialogs === undefined) {
      delete globalThis.OC.dialogs
    } else {
      globalThis.OC.dialogs = snapshot.OCDialogs
    }
  }
  globalThis.window.ArbeitszeitCheck = snapshot.ArbeitszeitCheck
  globalThis.window.AzcComponents = snapshot.AzcComponents
  globalThis.window.ArbeitszeitCheckComponents = snapshot.ArbeitszeitCheckComponents
  globalThis.window.ArbeitszeitCheckApp = snapshot.ArbeitszeitCheckApp
})

/**
 * Mimic Nextcloud 31/32/33 `OC.dialogs.confirmDestructive`:
 *  - the button callback delivers the real user choice
 *  - the returned Promise always resolves to `undefined`
 *  - in YES_NO mode the post-show fallback ALSO calls the callback with `false`
 *    (because NC sets `callback._clicked` but checks `callback.clicked`).
 *
 * `simulatedClick` is the choice the simulated user makes:
 *   true  → clicks the "Yes" button
 *   false → clicks the "No" button
 *   null  → closes via ESC / backdrop (no button)
 */
function mockNcConfirmDestructive(simulatedClick) {
  return vi.fn((text, title, buttons, callback) => {
    return Promise.resolve().then(() => {
      // Button click happens BEFORE the dialog-close promise resolves.
      if (simulatedClick === true) {
        callback._clicked = true
        callback(true)
      } else if (simulatedClick === false) {
        callback._clicked = true
        callback(false)
      }
      // Post-show fallback (NC's `.then`): looks at `callback.clicked` not
      // `_clicked`, so it ALWAYS fires `callback(false)` for legacy buttons.
      if (!callback.clicked) {
        callback(false)
      }
      // The returned Promise resolves to undefined regardless of choice.
      return undefined
    })
  })
}

describe('ArbeitszeitCheck.confirmDestructiveMain', () => {
  it('resolves to true when the user clicks "Yes" (NC 31+ promise+callback)', async () => {
    const cd = mockNcConfirmDestructive(true)
    globalThis.OC.dialogs = { confirmDestructive: cd, YES_NO_BUTTONS: 70 }

    const app = globalThis.window.ArbeitszeitCheckApp
    const result = await app.confirmDestructiveMain('Delete?', 'Confirm', {
      type: 70,
      modal: true,
    })

    expect(result).toBe(true)
    expect(cd).toHaveBeenCalledTimes(1)
  })

  it('resolves to false when the user clicks "No"', async () => {
    const cd = mockNcConfirmDestructive(false)
    globalThis.OC.dialogs = { confirmDestructive: cd, YES_NO_BUTTONS: 70 }

    const app = globalThis.window.ArbeitszeitCheckApp
    const result = await app.confirmDestructiveMain('Delete?', 'Confirm', {
      type: 70,
      modal: true,
    })

    expect(result).toBe(false)
  })

  it('resolves to false when the dialog is dismissed without a button click', async () => {
    const cd = mockNcConfirmDestructive(null)
    globalThis.OC.dialogs = { confirmDestructive: cd, YES_NO_BUTTONS: 70 }

    const app = globalThis.window.ArbeitszeitCheckApp
    const result = await app.confirmDestructiveMain('Delete?', 'Confirm', {
      type: 70,
      modal: true,
    })

    expect(result).toBe(false)
  })

  it('uses AzcComponents.confirmDialog when available', async () => {
    const confirmDialog = vi.fn().mockResolvedValue(true)
    globalThis.window.AzcComponents = { confirmDialog }
    delete globalThis.OC.dialogs

    const app = globalThis.window.ArbeitszeitCheckApp
    const result = await app.confirmDestructiveMain('Delete?', 'Confirm', {})

    expect(confirmDialog).toHaveBeenCalledTimes(1)
    expect(confirmDialog).toHaveBeenCalledWith(expect.objectContaining({
      title: 'Confirm',
      message: 'Delete?',
      variant: 'danger',
    }))
    expect(result).toBe(true)
  })

  it('resolves to false when confirmDialog is unavailable and OC.dialogs is missing', async () => {
    delete globalThis.window.AzcComponents
    delete globalThis.window.ArbeitszeitCheckComponents
    delete globalThis.OC.dialogs

    const app = globalThis.window.ArbeitszeitCheckApp
    const result = await app.confirmDestructiveMain('Delete?', 'Confirm', {})

    expect(result).toBe(false)
  })

  it('resolves to false when OC.dialogs.confirmDestructive throws', async () => {
    delete globalThis.window.AzcComponents
    delete globalThis.window.ArbeitszeitCheckComponents
    globalThis.OC.dialogs = {
      confirmDestructive: () => {
        throw new Error('boom')
      },
      YES_NO_BUTTONS: 70,
    }

    const app = globalThis.window.ArbeitszeitCheckApp
    const result = await app.confirmDestructiveMain('Delete?', 'Confirm', {
      type: 70,
      modal: true,
    })

    expect(result).toBe(false)
  })
})
