/**
 * Test script to verify the fix for the "Unauthenticated" error when refreshing the Dashboard page
 *
 * This script can be run in the browser console to test the authentication behavior
 * when refreshing the page.
 */

// Function to test authentication behavior
function testAuthenticationOnRefresh() {
  console.log('=== Testing Authentication on Dashboard Refresh ===');

  // Check if auth token exists in localStorage
  const token = localStorage.getItem('authToken');
  console.log('Auth token exists in localStorage:', !!token);

  // Check if Authorization header is set in axios
  const authHeader = axios.defaults.headers.common['Authorization'];
  console.log('Authorization header in axios:', authHeader);

  // Test if ensureAuthHeaders function exists in AvailabilityPrompt component
  const availabilityPrompt = document.querySelector('.bg-indigo-50.border-l-4.border-indigo-500');
  if (availabilityPrompt && availabilityPrompt.__vueParentComponent) {
    const component = availabilityPrompt.__vueParentComponent.ctx;
    console.log('AvailabilityPrompt component found:', !!component);
    console.log('ensureAuthHeaders function exists:', typeof component.ensureAuthHeaders === 'function');
  } else {
    console.log('AvailabilityPrompt component not found or not visible');
  }

  // Make a test request to the API
  console.log('Making test request to /api/availability-prompt...');
  axios.get('/api/availability-prompt')
    .then(response => {
      console.log('API request successful:', response.status);
      console.log('Received data:', response.data);
    })
    .catch(error => {
      console.error('API request failed:', error.response ? error.response.status : error.message);
      if (error.response && error.response.data) {
        console.error('Error data:', error.response.data);
      }
    });
}

// Instructions for use
console.log(`
=== Dashboard Authentication Refresh Test ===

This script tests the authentication behavior when refreshing the Dashboard page.

To use this script:
1. Navigate to the Dashboard page (/)
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Run this script by pasting it in the console
4. Check the console output for authentication status
5. Refresh the page
6. Run the script again to verify authentication persists after refresh

Expected behavior:
- Before refresh: Authentication should be working
- After refresh: Authentication should still be working, no "Unauthenticated" errors
`);

// Run the test
testAuthenticationOnRefresh();
