/**
 * Notification utility for managing application-wide notifications
 */

import { addOrUpdateNotification } from '@/Utils/notification-sidebar';

// Reference to the standard notification container component (for toasts)
let standardNotificationContainer = null;
// Optional callback to trigger fetching unread notices (e.g., to open full-screen modal)
let noticeFetcher = null;
// Push notification container is no longer needed here, as it will read from global state.

/**
 * Set the standard notification container reference
 * @param {Object} container - Reference to the standard notification container component
 */
export const setStandardNotificationContainer = (container) => {
    standardNotificationContainer = container;
};

/**
 * Set or clear the notice fetcher callback used to refresh notices (full modal use-case)
 * @param {Function|null} fn - function to call to fetch unread notices, or null to clear
 */
export const setNoticeFetcher = (fn) => {
    noticeFetcher = typeof fn === 'function' ? fn : null;
};

/**
 * Show a standard notification
 * @param {string} message - The notification message
 * @param {string} type - The notification type (success, error, info, warning)
 * @param {number} duration - How long the notification should be displayed (in ms)
 */
const showStandardNotification = (message, type = 'info', duration = 5000) => {
    if (!standardNotificationContainer) {
        console.warn('Standard notification container not set. Call setStandardNotificationContainer first.');
        return;
    }
    standardNotificationContainer.addNotification(message, type, duration);
};

/**
 * Handles a received push notification from Reverb.
 * It adds the notification to the central state and flags it as a new push.
 * @param {object} payload - The rich object payload from the broadcast event
 */
export const pushSuccess = (payload) => {
    console.log('Push received, adding to central store:', payload);

    // Check if the payload has the full_modal property set to true
    if (payload.full_modal) {
        console.log('Notification has full_modal set to true, triggering notice fetch instead of showing push.');
        try {
            if (noticeFetcher) {
                noticeFetcher();
            }
        } catch (e) {
            console.warn('Notice fetcher threw an error:', e);
        }
        return;
    }

    // The second argument `true` flags it as a new push notification
    addOrUpdateNotification(payload, true);
};

// --- Standard Notification Helpers ---

export const success = (message, duration = 5000) => {
    showStandardNotification(message, 'success', duration);
};

export const error = (message, duration = 5000) => {
    showStandardNotification(message, 'error', duration);
};

export const info = (message, duration = 5000) => {
    showStandardNotification(message, 'info', duration);
};

export const warning = (message, duration = 5000) => {
    showStandardNotification(message, 'warning', duration);
};

/**
 * Shows a confirmation prompt using the standard notification container.
 * Falls back to window.confirm if the container is not set.
 * @param {string} message - Prompt message.
 * @param {{confirmText?: string, cancelText?: string, type?: string}} options
 * @returns {Promise<boolean>} resolves true if confirmed, false if canceled
 */
export const confirmPrompt = async (message, options = {}) => {
    const { confirmText = 'Proceed', cancelText = 'Cancel', type = 'warning' } = options;

    if (!standardNotificationContainer) {
        // Fallback to browser confirm if container is not ready
        return Promise.resolve(window.confirm(message));
    }

    return new Promise((resolve) => {
        standardNotificationContainer.addNotification({
            message,
            type,
            sticky: true,
            confirm: {
                confirmText,
                cancelText,
                onConfirm: () => resolve(true),
                onCancel: () => resolve(false),
            },
        });
    });
};

/**
 * Utility function to format dates.
 * @param {string} dateString - The date string to format.
 * @returns {string} The formatted date string.
 */
export const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (date.toDateString() === now.toDateString()) {
        return 'Today';
    } else if (date.toDateString() === tomorrow.toDateString()) {
        return 'Tomorrow';
    } else {
        return date.toLocaleDateString('en-GB'); // dd/mm/yyyy format
    }
};

/**
 * Alias for error notification (for backward compatibility)
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const showErrorNotification = (message, duration = 5000) => {
    return error(message, duration);
};


/**
 * Alias for success notification (for backward compatibility)
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const showSuccessNotification = (message, duration = 5000) => {
    return success(message, duration);
};
