/**
 * Notification utility for managing application-wide notifications
 *
 * This utility provides a global reference to the notification container
 * and methods to show different types of notifications.
 */

// Reference to the notification container component
let notificationContainer = null;

/**
 * Set the notification container reference
 * @param {Object} container - Reference to the notification container component
 */
export const setNotificationContainer = (container) => {
    notificationContainer = container;
};

/**
 * Show a notification
 * @param {string} message - The notification message
 * @param {string} type - The notification type (success, error, info, warning)
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const notify = (message, type = 'info', duration = 5000) => {
    if (!notificationContainer) {
        console.warn('Notification container not set. Call setNotificationContainer first.');
        return null;
    }
    return notificationContainer.addNotification(message, type, duration);
};

/**
 * Show a success notification
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const success = (message, duration = 5000) => {
    return notify(message, 'success', duration);
};

/**
 * Show an error notification
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const error = (message, duration = 5000) => {
    return notify(message, 'error', duration);
};

/**
 * Show an info notification
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const info = (message, duration = 5000) => {
    return notify(message, 'info', duration);
};

/**
 * Show a warning notification
 * @param {string} message - The notification message
 * @param {number} duration - How long the notification should be displayed (in ms)
 * @returns {string|null} The notification ID or null if the container is not set
 */
export const warning = (message, duration = 5000) => {
    return notify(message, 'warning', duration);
};
