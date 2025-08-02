// Test script for notification system
// Run this in the browser console after loading the application

console.log('Testing notification system...');

// Test if notification utilities are available
if (typeof success === 'function' && typeof error === 'function') {
    console.log('✅ Notification utilities are available in global scope');
} else {
    console.error('❌ Notification utilities are not available in global scope');
    console.log('Import them manually for testing:');
    console.log('import { success, error } from "@/Utils/notification"');
}

// Test creating notifications
try {
    // Try to create a success notification
    console.log('Creating a success notification...');
    success('This is a test success notification');

    // Try to create an error notification
    console.log('Creating an error notification...');
    error('This is a test error notification');

    console.log('✅ Notifications created successfully');
} catch (e) {
    console.error('❌ Error creating notifications:', e);
}

// Instructions for testing real-time notifications
console.log('\nTo test real-time notifications with Laravel Echo:');
console.log('1. Make sure you are logged in');
console.log('2. Make sure the Reverb server is running (php artisan reverb:start)');
console.log('3. Create a new task and assign it to yourself or another user');
console.log('4. Check if the notification appears in real-time');
