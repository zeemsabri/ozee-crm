/**
 * Notification utility for managing application-wide notifications
 */

import { addOrUpdateNotification } from '@/Utils/notification-sidebar';

// Reference to the standard notification container component (for toasts)
let standardNotificationContainer = null;
// Push notification container is no longer needed here, as it will read from global state.

/**
 * Set the standard notification container reference
 * @param {Object} container - Reference to the standard notification container component
 */
export const setStandardNotificationContainer = (container) => {
    standardNotificationContainer = container;
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
