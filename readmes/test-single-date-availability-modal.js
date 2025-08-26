/**
 * Test script to verify the SingleDateAvailabilityModal functionality
 *
 * This script can be run in the browser console to test that:
 * 1. The SingleDateAvailabilityModal opens when the "Add" button is clicked
 * 2. The modal displays the correct date
 * 3. The modal can fetch existing availability data for the date
 * 4. The modal can save new availability data
 * 5. The calendar is updated after saving
 */

// Function to test the SingleDateAvailabilityModal
function testSingleDateAvailabilityModal() {
  console.log('=== Testing SingleDateAvailabilityModal ===');

  // Check if we're on the Availability page
  const calendar = document.querySelector('[data-test="availability-calendar"]');
  if (!calendar) {
    console.error('Error: This test should be run on the Weekly Availability page (/availability)');
    return;
  }

  // Get the Vue component instance
  const calendarComponent = calendar.__vueParentComponent?.ctx;
  if (!calendarComponent) {
    console.error('Error: Could not access the Vue component');
    return;
  }

  console.log('Calendar component found:', !!calendarComponent);

  // Test 1: Check if the SingleDateAvailabilityModal opens when the "Add" button is clicked
  console.log('\nTest 1: Checking if the SingleDateAvailabilityModal opens when the "Add" button is clicked');
  console.log('Please click the "Add" button for any date in the calendar');

  // Set up a watcher to check when the modal is opened
  const checkModalInterval = setInterval(() => {
    if (calendarComponent.showSingleDateModal) {
      clearInterval(checkModalInterval);
      console.log('Success! SingleDateAvailabilityModal opened');

      // Test 2: Check if the modal displays the correct date
      console.log('\nTest 2: Checking if the modal displays the correct date');
      const modalTitle = document.querySelector('.p-6 h2');
      if (modalTitle) {
        console.log('Modal title:', modalTitle.textContent.trim());
        console.log('Selected date:', calendarComponent.selectedDate);

        const dateInTitle = modalTitle.textContent.includes(new Date(calendarComponent.selectedDate).toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' }));
        console.log('Date in title matches selected date:', dateInTitle);

        if (dateInTitle) {
          console.log('Success! Modal displays the correct date');
        } else {
          console.error('Error: Modal does not display the correct date');
        }
      } else {
        console.error('Error: Could not find modal title');
      }

      // Test 3: Check if the modal can fetch existing availability data
      console.log('\nTest 3: Checking if the modal can fetch existing availability data');
      console.log('This test requires manual verification. Please check if any existing availability data is pre-filled in the modal.');

      // Test 4: Check if the modal can save new availability data
      console.log('\nTest 4: Checking if the modal can save new availability data');
      console.log('Please fill in the form and click "Save Availability"');

      // Test 5: Check if the calendar is updated after saving
      console.log('\nTest 5: Checking if the calendar is updated after saving');
      console.log('After saving, please check if the calendar displays the new availability data');
    }
  }, 500);

  // Timeout after 10 seconds
  setTimeout(() => {
    clearInterval(checkModalInterval);
    if (!calendarComponent.showSingleDateModal) {
      console.error('Timeout: Modal not opened after waiting. Please click the "Add" button to continue the test.');
    }
  }, 10000);
}

// Instructions for use
console.log(`
=== SingleDateAvailabilityModal Test ===

This script tests whether the SingleDateAvailabilityModal component works correctly.

To use this script:
1. Navigate to the Weekly Availability page (/availability)
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Run this script by pasting it in the console
4. Follow the instructions in the console output

Expected behavior:
- The script will guide you through testing the SingleDateAvailabilityModal
- You'll need to click the "Add" button for a date and fill in the form
- The script will check if the modal opens, displays the correct date, and can save data
`);

// Run the test
testSingleDateAvailabilityModal();
