// Test script to debug push notifications
console.log('Testing push notification flow...');

// Import the notification utilities
import {
    setPushNotificationContainer,
    pushSuccess
} from './resources/js/Utils/notification.js';

// Create a mock container with the same interface as PushNotificationContainer
const mockContainer = {
    addNotification: (payload) => {
        console.log('Mock addNotification called with payload:', payload);
        return 'mock-id-123';
    }
};

// Set the mock container
console.log('Setting mock push notification container...');
setPushNotificationContainer(mockContainer);

// Test the pushSuccess function
console.log('Testing pushSuccess function...');
const notificationId = pushSuccess({
    title: 'Test Notification',
    message: 'This is a test notification',
    task_id: 123,
    project_name: 'Test Project',
    due_date: '2025-08-10',
    url: '/tasks/123'
});

console.log('Notification ID returned:', notificationId);

// If we got here without errors and received an ID, the function is working
if (notificationId) {
    console.log('✅ pushSuccess function is working correctly!');
} else {
    console.error('❌ pushSuccess function failed!');
}

// Test complete
console.log('Test completed.');
