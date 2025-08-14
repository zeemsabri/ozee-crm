import { ref } from 'vue';
import { markRaw } from 'vue';
import { success as showSuccessToast } from '@/Utils/notification';

// --- LocalStorage Helpers to persist push notifications across refresh ---
const PUSH_IDS_KEY = 'newPushNotificationIds';

const getNewPushIds = () => {
    try {
        const ids = localStorage.getItem(PUSH_IDS_KEY);
        return ids ? JSON.parse(ids) : [];
    } catch (e) {
        console.error('Failed to parse push notification IDs from localStorage', e);
        return [];
    }
};

const setNewPushIds = (ids) => {
    const uniqueIds = [...new Set(ids)];
    localStorage.setItem(PUSH_IDS_KEY, JSON.stringify(uniqueIds));
};

const removeIdFromLocalStorage = (viewId) => {
    let currentPushIds = getNewPushIds();
    if (currentPushIds.includes(viewId)) {
        setNewPushIds(currentPushIds.filter(id => id !== viewId));
    }
};
// --- End of LocalStorage Helpers ---


// A global state object for all notifications
export const notificationSidebarState = ref({
    show: false,
    notifications: [],
});

/**
 * Adds a new notification to the global state or uses it to clear an existing one.
 * @param {object} notification - The notification object to add.
 * @param {boolean} fromPush - Flag to indicate if the notification is from a live push event.
 */
export const addOrUpdateNotification = (notification, fromPush = false) => {

    if (notification.clears_notification_type && notification.correlation_id) {
        const notificationsToClear = notificationSidebarState.value.notifications.filter(
            n => n.correlation_id === notification.correlation_id && n.task_type === notification.clears_notification_type
        );

        if (notificationsToClear.length > 0) {
            const idsToClear = notificationsToClear.map(n => n.id);
            notificationsToClear.forEach(n => removeIdFromLocalStorage(n.view_id));
            notificationSidebarState.value.notifications = notificationSidebarState.value.notifications.filter(
                n => !idsToClear.includes(n.id)
            );
            showSuccessToast(notification.message, 4000);
        }

        const exists = notificationSidebarState.value.notifications.some(n => n.view_id === notification.view_id);
        if (!exists) {
            const newNotification = markRaw({
                ...notification,
                id: crypto.randomUUID(),
                isNewPush: false,
                isRead: false,
            });
            notificationSidebarState.value.notifications.unshift(newNotification);
        }
        return;
    }

    const exists = notificationSidebarState.value.notifications.some(
        (n) => n.view_id === notification.view_id
    );

    if (!exists) {
        if (fromPush) {
            const currentPushIds = getNewPushIds();
            setNewPushIds([...currentPushIds, notification.view_id]);
        }
        const newNotification = markRaw({
            ...notification,
            id: crypto.randomUUID(),
            isNewPush: fromPush,
            isRead: false,
        });
        if (fromPush) {
            notificationSidebarState.value.notifications.unshift(newNotification);
        } else {
            notificationSidebarState.value.notifications.push(newNotification);
        }
    }
};


/**
 * Fetches all notifications from the database and populates the global state.
 */
export const fetchNotificationsFromDatabase = async () => {
    try {
        const response = await window.axios.get('/api/notifications');
        let allDbNotifications = [];

        if (response.data && Array.isArray(response.data.data)) {
            allDbNotifications = response.data.data;
        } else {
            notificationSidebarState.value.notifications = [];
            return;
        }

        const approvedCorrelationIds = new Set(
            allDbNotifications
                .filter(n => n.clears_notification_type && n.correlation_id)
                .map(n => n.correlation_id)
        );

        const finalNotifications = allDbNotifications.filter(n => {
            if (n.task_type === 'email_approval' && n.correlation_id) {
                return !approvedCorrelationIds.has(n.correlation_id);
            }
            return true;
        });

        // --- UPDATED LOGIC TO SHOW MISSED NOTIFICATIONS ---
        const existingPushIds = getNewPushIds();
        // Find any unread notifications from the database fetch using the `isRead` flag.
        const unreadIdsFromDb = finalNotifications
            .filter(n => !n.isRead && !n.full_modal)
            .map(n => n.view_id);

        const combinedPushIds = [...new Set([...existingPushIds, ...unreadIdsFromDb])];
        setNewPushIds(combinedPushIds);
        // --- END OF UPDATED LOGIC ---

        notificationSidebarState.value.notifications = finalNotifications.map(dbNotification => {
            const isNew = combinedPushIds.includes(dbNotification.view_id);
            return markRaw({
                ...dbNotification,
                id: crypto.randomUUID(),
                isNewPush: isNew,
                isRead: dbNotification.isRead, // <-- CHANGED THIS LINE
            });
        });

    } catch (error) {
        console.error('Error fetching notifications from database:', error);
    }
};

/**
 * Opens the notification sidebar.
 */
export const openNotificationsSidebar = () => {
    notificationSidebarState.value.show = true;
    notificationSidebarState.value.notifications.forEach(n => {
        n.isNewPush = false;
    });
    localStorage.removeItem(PUSH_IDS_KEY);
    fetchNotificationsFromDatabase();
};

/**
 * Closes the notification sidebar.
 */
export const closeNotificationsSidebar = () => {
    notificationSidebarState.value.show = false;
};

/**
 * Marks a notification as read and re-fetches the list.
 */
export const markNotificationAndRefetch = async (viewId) => {
    try {
        await window.axios.post(`/api/notifications/${viewId}/read`);
        removeIdFromLocalStorage(viewId);
        await fetchNotificationsFromDatabase();
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
};

/**
 * Marks a toast as "seen" so it is removed from the push container.
 */
export const markToastAsSeen = (notificationId) => {
    const notification = notificationSidebarState.value.notifications.find(n => n.id === notificationId);
    if (notification) {
        notification.isNewPush = false;
        removeIdFromLocalStorage(notification.view_id);
    }
};
