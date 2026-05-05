const DESKTOP_NOTIFICATION_PERMISSION_KEY = 'desktop-notification-permission-requested';
const DESKTOP_NOTIFICATION_BOOTSTRAP_KEY = 'desktop-notification-bootstrap-attached';

const canUseDesktopNotifications = () => {
    return typeof window !== 'undefined' && 'Notification' in window;
};

const markPermissionRequested = () => {
    try {
        sessionStorage.setItem(DESKTOP_NOTIFICATION_PERMISSION_KEY, '1');
    } catch (error) {
        console.warn('Failed to persist desktop notification permission request state:', error);
    }
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
    markPermissionRequested();

    return permission;
};

export const setupDesktopNotificationPermissionBootstrap = () => {
    if (!canUseDesktopNotifications()) {
        return () => {};
    }

    if (window.Notification.permission !== 'default') {
        return () => {};
    }

    if (window[DESKTOP_NOTIFICATION_BOOTSTRAP_KEY]) {
        return window[DESKTOP_NOTIFICATION_BOOTSTRAP_KEY];
    }

    const events = ['pointerdown', 'keydown', 'touchstart'];

    const requestFromInteraction = async () => {
        teardown();
        await requestDesktopNotificationPermission();
    };

    const teardown = () => {
        events.forEach((eventName) => {
            window.removeEventListener(eventName, requestFromInteraction);
        });
        delete window[DESKTOP_NOTIFICATION_BOOTSTRAP_KEY];
    };

    events.forEach((eventName) => {
        window.addEventListener(eventName, requestFromInteraction, { once: true });
    });

    window[DESKTOP_NOTIFICATION_BOOTSTRAP_KEY] = teardown;
    return teardown;
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
