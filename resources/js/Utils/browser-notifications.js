const DESKTOP_NOTIFICATION_PERMISSION_KEY = 'desktop-notification-permission-requested';

const canUseDesktopNotifications = () => {
    return typeof window !== 'undefined' && 'Notification' in window;
};

export const getDesktopNotificationPermission = () => {
    if (!canUseDesktopNotifications()) {
        return 'unsupported';
    }

    return window.Notification.permission;
};

export const requestDesktopNotificationPermission = async () => {
    if (!canUseDesktopNotifications()) {
        return 'unsupported';
    }

    if (window.Notification.permission !== 'default') {
        return window.Notification.permission;
    }

    const permission = await window.Notification.requestPermission();

    try {
        sessionStorage.setItem(DESKTOP_NOTIFICATION_PERMISSION_KEY, '1');
    } catch (error) {
        console.warn('Failed to persist desktop notification permission request state:', error);
    }

    return permission;
};

const shouldShowDesktopNotification = () => {
    if (!canUseDesktopNotifications() || window.Notification.permission !== 'granted') {
        return false;
    }

    return document.visibilityState !== 'visible' || !document.hasFocus();
};

export const maybeShowDesktopNotification = ({ title, body, tag, onClick } = {}) => {
    if (!shouldShowDesktopNotification()) {
        return null;
    }

    const notification = new window.Notification(title || 'New notification', {
        body: body || '',
        tag,
        renotify: true,
    });

    notification.onclick = (event) => {
        event.preventDefault();
        window.focus();
        if (typeof onClick === 'function') {
            onClick();
        }
        notification.close();
    };

    window.setTimeout(() => notification.close(), 8000);

    return notification;
};
