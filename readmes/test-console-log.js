// Test script to check console logs for push notifications
console.log('Checking console logs for push notifications...');

// Add a global error handler to catch any errors
window.addEventListener('error', function(event) {
    console.error('Global error caught:', event.error);
});

// Mock the Echo object to simulate a notification
if (!window.Echo) {
    window.Echo = {
        private: function(channel) {
            console.log(`Mocking subscription to channel: ${channel}`);
            return {
                notification: function(callback) {
                    console.log('Setting up notification listener');

                    // Simulate a notification after 1 second
                    setTimeout(() => {
                        console.log('Simulating incoming notification');
                        const mockNotification = {
                            title: 'Test Notification',
                            message: 'This is a test notification',
                            task_id: 123,
                            project_name: 'Test Project',
                            due_date: '2025-08-10',
                            url: '/tasks/123'
                        };

                        try {
                            callback(mockNotification);
                            console.log('Notification callback executed successfully');
                        } catch (error) {
                            console.error('Error in notification callback:', error);
                        }
                    }, 1000);

                    return this;
                }
            };
        }
    };
}

console.log('Test setup complete. Check browser console for errors when notifications are received.');
