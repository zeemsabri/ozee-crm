// Test script to verify the notification import fix
console.log('Testing notification import fix...');

// Import the notification utilities to verify they load correctly
import {
    setStandardNotificationContainer,
    setPushNotificationContainer,
    success,
    error,
    pushSuccess
} from './resources/js/Utils/notification.js';

console.log('Imports loaded successfully!');
console.log('Available exports:', {
    setStandardNotificationContainer: typeof setStandardNotificationContainer,
    setPushNotificationContainer: typeof setPushNotificationContainer,
    success: typeof success,
    error: typeof error,
    pushSuccess: typeof pushSuccess
});

// This script will fail if any of the imports don't exist
console.log('Test completed successfully - all imports are available.');
