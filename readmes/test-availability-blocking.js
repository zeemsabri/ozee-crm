/**
 * Test script to verify the availability blocking functionality
 *
 * This script tests:
 * 1. The updated shouldShowPrompt API endpoint
 * 2. The AvailabilityPrompt component's behavior
 * 3. The AvailabilityBlocker component's behavior
 *
 * Run this script in the browser console to test the functionality.
 */

// Function to test the shouldShowPrompt API endpoint
async function testShouldShowPromptAPI() {
  console.log('=== Testing shouldShowPrompt API Endpoint ===');

  // Ensure auth headers are set
  const token = localStorage.getItem('authToken');
  if (token && !axios.defaults.headers.common['Authorization']) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    console.log('Auth headers set');
  }

  try {
    const response = await axios.get('/api/availability-prompt');
    console.log('API Response:', response.data);

    // Verify that the response contains the expected fields
    const expectedFields = [
      'should_show_prompt',
      'should_block_user',
      'next_week_start',
      'next_week_end',
      'weekdays_covered',
      'all_weekdays_covered',
      'current_day',
      'is_thursday_to_saturday'
    ];

    const missingFields = expectedFields.filter(field => !(field in response.data));
    if (missingFields.length > 0) {
      console.error('Missing fields in API response:', missingFields);
    } else {
      console.log('All expected fields are present in the API response');
    }

    // Log the current state
    console.log('Current day:', response.data.current_day);
    console.log('Is Thursday to Saturday:', response.data.is_thursday_to_saturday);
    console.log('Should show prompt:', response.data.should_show_prompt);
    console.log('Should block user:', response.data.should_block_user);
    console.log('All weekdays covered:', response.data.all_weekdays_covered);
    console.log('Weekdays covered:', response.data.weekdays_covered);

    return response.data;
  } catch (error) {
    console.error('Error testing shouldShowPrompt API:', error);
    return null;
  }
}

// Function to test the AvailabilityPrompt component
function testAvailabilityPrompt() {
  console.log('=== Testing AvailabilityPrompt Component ===');

  // Find the AvailabilityPrompt component in the DOM
  const promptElement = document.querySelector('.border-l-4.p-4.mb-6.rounded-md.shadow-sm');
  if (!promptElement) {
    console.log('AvailabilityPrompt component not found in the DOM. It might not be visible if shouldShowPrompt is false.');
    return;
  }

  console.log('AvailabilityPrompt component found in the DOM');

  // Check if the component has the correct styling based on shouldBlockUser
  const shouldBlockUser = localStorage.getItem('shouldBlockUser') === 'true';
  const hasRedStyling = promptElement.classList.contains('bg-red-50') && promptElement.classList.contains('border-red-500');
  const hasBlueStyling = promptElement.classList.contains('bg-indigo-50') && promptElement.classList.contains('border-indigo-500');

  console.log('Should block user:', shouldBlockUser);
  console.log('Has red styling:', hasRedStyling);
  console.log('Has blue styling:', hasBlueStyling);

  if (shouldBlockUser && hasRedStyling) {
    console.log('AvailabilityPrompt has correct red styling for blocking state');
  } else if (!shouldBlockUser && hasBlueStyling) {
    console.log('AvailabilityPrompt has correct blue styling for non-blocking state');
  } else {
    console.error('AvailabilityPrompt has incorrect styling');
  }

  // Check if the component has the correct message based on the current day and submission status
  const messageElement = promptElement.querySelector('p.text-sm');
  if (messageElement) {
    console.log('Message text:', messageElement.textContent.trim());
  } else {
    console.error('Message element not found in AvailabilityPrompt');
  }

  // Check if the button has the correct text and styling
  const buttonElement = promptElement.querySelector('button');
  if (buttonElement) {
    console.log('Button text:', buttonElement.textContent.trim());
    console.log('Button has red styling:', buttonElement.classList.contains('bg-red-600'));
  } else {
    console.error('Button element not found in AvailabilityPrompt');
  }
}

// Function to test the AvailabilityBlocker component
function testAvailabilityBlocker() {
  console.log('=== Testing AvailabilityBlocker Component ===');

  // Find the AvailabilityBlocker component in the DOM
  const blockerElement = document.querySelector('.fixed.inset-0.bg-black.bg-opacity-50.z-50');
  const shouldBlockUser = localStorage.getItem('shouldBlockUser') === 'true';
  const allWeekdaysCovered = localStorage.getItem('allWeekdaysCovered') === 'true';

  console.log('Should block user:', shouldBlockUser);
  console.log('All weekdays covered:', allWeekdaysCovered);

  if (shouldBlockUser && !allWeekdaysCovered) {
    if (blockerElement) {
      console.log('AvailabilityBlocker is correctly displayed when user should be blocked');

      // Check if the blocker has the correct content
      const titleElement = blockerElement.querySelector('h2');
      if (titleElement) {
        console.log('Blocker title:', titleElement.textContent.trim());
      } else {
        console.error('Title element not found in AvailabilityBlocker');
      }

      const messageElement = blockerElement.querySelector('p');
      if (messageElement) {
        console.log('Blocker message:', messageElement.textContent.trim());
      } else {
        console.error('Message element not found in AvailabilityBlocker');
      }

      const buttonElement = blockerElement.querySelector('button');
      if (buttonElement) {
        console.log('Blocker button text:', buttonElement.textContent.trim());
      } else {
        console.error('Button element not found in AvailabilityBlocker');
      }
    } else {
      console.error('AvailabilityBlocker is not displayed when it should be');
    }
  } else {
    if (blockerElement) {
      console.error('AvailabilityBlocker is displayed when it should not be');
    } else {
      console.log('AvailabilityBlocker is correctly not displayed when user should not be blocked');
    }
  }
}

// Function to simulate different days and blocking conditions
async function simulateDifferentConditions() {
  console.log('=== Simulating Different Conditions ===');
  console.log('Note: This is a simulation and does not actually change the server state.');

  // Simulate Thursday with no availability submitted
  console.log('\nSimulating Thursday with no availability submitted:');
  localStorage.setItem('shouldBlockUser', 'false');
  localStorage.setItem('allWeekdaysCovered', 'false');
  window.dispatchEvent(new CustomEvent('availability-status-updated', {
    detail: {
      shouldBlockUser: false,
      allWeekdaysCovered: false
    }
  }));
  console.log('Refresh the page to see the changes');

  // Simulate Thursday with all availability submitted
  console.log('\nSimulating Thursday with all availability submitted:');
  localStorage.setItem('shouldBlockUser', 'false');
  localStorage.setItem('allWeekdaysCovered', 'true');
  window.dispatchEvent(new CustomEvent('availability-status-updated', {
    detail: {
      shouldBlockUser: false,
      allWeekdaysCovered: true
    }
  }));
  console.log('Refresh the page to see the changes');

  // Simulate Friday with no availability submitted (blocking)
  console.log('\nSimulating Friday with no availability submitted (blocking):');
  localStorage.setItem('shouldBlockUser', 'true');
  localStorage.setItem('allWeekdaysCovered', 'false');
  window.dispatchEvent(new CustomEvent('availability-status-updated', {
    detail: {
      shouldBlockUser: true,
      allWeekdaysCovered: false
    }
  }));
  console.log('Refresh the page to see the changes');

  // Simulate Friday with all availability submitted
  console.log('\nSimulating Friday with all availability submitted:');
  localStorage.setItem('shouldBlockUser', 'false');
  localStorage.setItem('allWeekdaysCovered', 'true');
  window.dispatchEvent(new CustomEvent('availability-status-updated', {
    detail: {
      shouldBlockUser: false,
      allWeekdaysCovered: true
    }
  }));
  console.log('Refresh the page to see the changes');
}

// Main test function
async function runTests() {
  console.log('=== Starting Availability Blocking Tests ===');

  // Test the API endpoint
  const apiData = await testShouldShowPromptAPI();

  if (apiData) {
    // Test the components
    testAvailabilityPrompt();
    testAvailabilityBlocker();

    // Provide instructions for simulating different conditions
    console.log('\n=== Simulation Instructions ===');
    console.log('To simulate different conditions, run:');
    console.log('simulateDifferentConditions()');
  }

  console.log('\n=== Tests Completed ===');
}

// Instructions for use
console.log(`
=== Availability Blocking Test Script ===

This script tests the availability blocking functionality.

To run the tests:
1. Navigate to the Dashboard page (/)
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Run this script by pasting it in the console
4. Call the runTests() function to start the tests
5. To simulate different conditions, call the simulateDifferentConditions() function

Expected behavior:
- The API endpoint should return the expected fields
- The AvailabilityPrompt component should display the correct message and styling
- The AvailabilityBlocker component should be displayed only when the user should be blocked
`);

// Export the test functions
window.testAvailabilityBlocking = {
  runTests,
  testShouldShowPromptAPI,
  testAvailabilityPrompt,
  testAvailabilityBlocker,
  simulateDifferentConditions
};

console.log('Test functions are available under window.testAvailabilityBlocking');
console.log('Run window.testAvailabilityBlocking.runTests() to start the tests');
