/**
 * Messaging Utilities for ArbeitszeitCheck App
 * Provides toast notifications and user feedback
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

const ArbeitszeitCheckMessaging = {
  announcePolite(message) {
    const region = document.getElementById('azc-live-region');
    if (region && message) {
      region.textContent = '';
      window.setTimeout(() => { region.textContent = String(message); }, 10);
    }
  },

  announceAssertive(message) {
    const region = document.getElementById('azc-alert-region');
    if (region && message) {
      region.textContent = '';
      window.setTimeout(() => { region.textContent = String(message); }, 10);
    }
  },

  mapApiError(json) {
    if (window.AzcApi && typeof window.AzcApi.mapApiError === 'function') {
      return window.AzcApi.mapApiError(json, json?.status || 500);
    }
    if (json && typeof json.error === 'string') {
      return json.error;
    }
    return window.t
      ? window.t('arbeitszeitcheck', 'An unexpected error occurred. Please try again.')
      : 'An unexpected error occurred. Please try again.';
  },

  /**
   * Show success message
   */
  showSuccess(message, title = null) {
    this.announcePolite(message);
    if (window.ArbeitszeitCheckComponents) {
      return window.ArbeitszeitCheckComponents.showToast({
        type: 'success',
        message: message,
        title: title,
        duration: 3000
      });
    } else if (window.OC && window.OC.Notification && window.OC.Notification.showTemporary) {
      window.OC.Notification.showTemporary(message);
    }
  },

  /**
   * Show error message
   */
  showError(message, title = null) {
    this.announceAssertive(message);
    if (window.ArbeitszeitCheckComponents) {
      return window.ArbeitszeitCheckComponents.showToast({
        type: 'error',
        message: message,
        title: title,
        duration: 5000
      });
    } else if (window.OC && window.OC.Notification && window.OC.Notification.showTemporary) {
      window.OC.Notification.showTemporary(message);
    }
  },

  /**
   * Show warning message
   */
  showWarning(message, title = null) {
    this.announcePolite(message);
    if (window.ArbeitszeitCheckComponents) {
      return window.ArbeitszeitCheckComponents.showToast({
        type: 'warning',
        message: message,
        title: title,
        duration: 4000
      });
    } else if (window.OC && window.OC.Notification && window.OC.Notification.showTemporary) {
      window.OC.Notification.showTemporary(message);
    }
  },

  /**
   * Show info message
   */
  showInfo(message, title = null) {
    this.announcePolite(message);
    if (window.ArbeitszeitCheckComponents) {
      return window.ArbeitszeitCheckComponents.showToast({
        type: 'info',
        message: message,
        title: title,
        duration: 3000
      });
    } else if (window.OC && window.OC.Notification && window.OC.Notification.showTemporary) {
      window.OC.Notification.showTemporary(message);
    }
  },

  /**
   * Show generic toast
   */
  show(type, message, title = null, duration = null) {
    if (type === 'error') {
      this.announceAssertive(message);
    } else {
      this.announcePolite(message);
    }
    if (window.ArbeitszeitCheckComponents) {
      return window.ArbeitszeitCheckComponents.showToast({
        type: type,
        message: message,
        title: title,
        duration: duration
      });
    } else if (window.OC && window.OC.Notification && window.OC.Notification.showTemporary) {
      window.OC.Notification.showTemporary(message);
    }
  }
};

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.ArbeitszeitCheckMessaging = ArbeitszeitCheckMessaging;
  window.AzcMessaging = ArbeitszeitCheckMessaging;
}
