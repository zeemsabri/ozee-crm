/**
 * Notification utility for managing application-wide notifications
 *
 * This utility provides a global reference to the notification container
 * and methods to show different types of notifications.
 */

// Reference to the standard notification container component (for toasts)
let standardNotificationContainer = null;
// Reference to the push notification container component (for persistent messages)
let pushNotificationContainer = null;

/**
 * Set the standard notification container reference
 * @param {Object} container - Reference to the standard notification container component
 */
export const setStandardNotificationContainer = (container) => {
    standardNotificationContainer = container;
};

/**
 * Set the push notification container reference
 * @param {Object} container - Reference to the push notification container component
 */
export const setPushNotificationContainer = (container) => {
    pushNotificationContainer = container;
};

/**
 * Show a standard notification
 * @param {string} message - The notification message
 * @param {string} type - The notification type (success, error, info, warning)
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
const showStandardNotification = (message, type = 'info', duration = 5000) => {
    if (!standardNotificationContainer) {
        console.warn('Standard notification container not set. Call setStandardNotificationContainer first.');
        return null;
    }
    return standardNotificationContainer.addNotification(message, type, duration);
};

/**
 * Show a push notification
 * This function is specifically for Reverb notifications.
 * @param {object} payload - The rich object payload from the broadcast event
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const pushSuccess = (payload) => {
    if (!pushNotificationContainer) {
        console.warn('Push notification container not set. Call setPushNotificationContainer first.');
        return null;
    }
    return pushNotificationContainer.addNotification(payload);
};

/**
 * Show a success notification
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const success = (message, duration = 5000) => {
    return showStandardNotification(message, 'success', duration);
};

/**
 * Show an error notification
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const error = (message, duration = 5000) => {
    return showStandardNotification(message, 'error', duration);
};

/**
 * Show an info notification
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const info = (message, duration = 5000) => {
    return showStandardNotification(message, 'info', duration);
};

/**
 * Show a warning notification
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const warning = (message, duration = 5000) => {
    return showStandardNotification(message, 'warning', duration);
};
