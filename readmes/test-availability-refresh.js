/**
 * Test script to verify the fix for the "Unauthenticated" error when refreshing the AvailabilityCalendar page
 *
 * This script can be run in the browser console to test the authentication behavior
 * when refreshing the page.
 */

// Function to test authentication behavior
function testAuthenticationOnRefresh() {
  console.log('=== Testing Authentication on Refresh ===');

  // Check if auth token exists in localStorage
  const token = localStorage.getItem('authToken');
  console.log('Auth token exists in localStorage:', !!token);

  // Check if Authorization header is set in axios
  const authHeader = axios.defaults.headers.common['Authorization'];
  console.log('Authorization header in axios:', authHeader);

  // Test if ensureAuthHeaders function exists in AvailabilityCalendar component
  const availabilityCalendar = document.querySelector('[data-test="availability-calendar"]');
  if (availabilityCalendar && availabilityCalendar.__vueParentComponent) {
    const component = availabilityCalendar.__vueParentComponent.ctx;
    console.log('AvailabilityCalendar component found:', !!component);
    console.log('ensureAuthHeaders function exists:', typeof component.ensureAuthHeaders === 'function');
  } else {
    console.log('AvailabilityCalendar component not found');
  }

  // Make a test request to the API
  console.log('Making test request to /api/availabilities...');
  axios.get('/api/availabilities')
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
=== Authentication Refresh Test ===

This script tests the authentication behavior when refreshing the AvailabilityCalendar page.

To use this script:
1. Navigate to the Weekly Availability page (/availability)
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
