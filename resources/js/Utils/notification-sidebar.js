import { ref } from 'vue';
import { markRaw } from 'vue';

// A global state object for the notification sidebar
export const notificationSidebarState = ref({
    show: false,
    notifications: [],
});

/**
 * Fetches all notifications from the database and populates the global state.
 */
export const fetchNotificationsFromDatabase = async () => {
    try {
        const response = await window.axios.get('/api/notifications'); // Assuming this is your API endpoint
        // Clear existing notifications to prevent duplicates on subsequent fetches
        notificationSidebarState.value.notifications = [];

        // The new API response format has a data key
        if (response.data && Array.isArray(response.data.data)) {
            response.data.data.forEach(dbNotification => {
                // The API now returns a flat object, so we don't need to flatten it
                const newNotification = markRaw({
                    ...dbNotification,
                    // We now use the isRead flag directly from the response
                });

                // Ensure unique notifications based on the new 'view_id'
                const exists = notificationSidebarState.value.notifications.some(
                    (n) => n.view_id === newNotification.view_id
                );
                if (!exists) {
                    notificationSidebarState.value.notifications.unshift(newNotification);
                }
            });
        }
    } catch (error) {
        console.error('Error fetching notifications from database:', error);
    }
};

/**
 * Opens the notification sidebar.
 */
export const openNotificationsSidebar = () => {
    notificationSidebarState.value.show = true;
    fetchNotificationsFromDatabase(); // Fetch notifications whenever the sidebar is opened
};

/**
 * Closes the notification sidebar.
 */
export const closeNotificationsSidebar = () => {
    notificationSidebarState.value.show = false;
};

/**
 * Marks a notification as read in the database and then re-fetches the notifications.
 * @param {string} viewId - The unique ID of the notification from the payload.
 */
export const markNotificationAndRefetch = async (viewId) => {
    try {
        await window.axios.post(`/api/notifications/${viewId}/read`);
        // Re-fetch the notifications to get the updated state
        await fetchNotificationsFromDatabase();
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
};
