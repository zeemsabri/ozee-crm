/**
 * Test script to verify the fix for availability display in the Calendar view
 *
 * This script can be run in the browser console to test that availabilities
 * are properly displayed in the Calendar view after the date format fix.
 */

// Function to test availability display
function testAvailabilityDisplay() {
  console.log('=== Testing Availability Display in Calendar ===');

  // Check if we're on the Availability page
  const calendar = document.querySelector('[data-test="availability-calendar"]');
  if (!calendar) {
    console.error('Error: This test should be run on the Weekly Availability page (/availability)');
    return;
  }

  // Get the Vue component instance
  const component = calendar.__vueParentComponent?.ctx;
  if (!component) {
    console.error('Error: Could not access the Vue component');
    return;
  }

  console.log('Component found:', !!component);

  // Check if availabilities are loaded
  if (!component.availabilities || component.availabilities.length === 0) {
    console.log('No availabilities loaded yet. Will wait for data to load...');

    // Set up a watcher to check when availabilities are loaded
    const checkInterval = setInterval(() => {
      if (component.availabilities && component.availabilities.length > 0) {
        clearInterval(checkInterval);
        analyzeAvailabilities(component);
      }
    }, 500);

    // Timeout after 10 seconds
    setTimeout(() => {
      clearInterval(checkInterval);
      if (!component.availabilities || component.availabilities.length === 0) {
        console.log('Timeout: No availabilities loaded after waiting. Try refreshing the page or creating some availability records.');
      }
    }, 10000);
  } else {
    analyzeAvailabilities(component);
  }
}

// Function to analyze availabilities and their display
function analyzeAvailabilities(component) {
  const availabilities = component.availabilities;
  const availabilitiesByDate = component.availabilitiesByDate;

  console.log('Availabilities from API:', availabilities);
  console.log('Grouped availabilities by date:', availabilitiesByDate);

  // Check if any availabilities are displayed
  let totalDisplayed = 0;
  for (const date in availabilitiesByDate) {
    totalDisplayed += availabilitiesByDate[date].length;
  }

  console.log(`Total availabilities from API: ${availabilities.length}`);
  console.log(`Total availabilities displayed: ${totalDisplayed}`);

  if (totalDisplayed === 0 && availabilities.length > 0) {
    console.error('Error: Availabilities are loaded from API but none are displayed in the calendar');

    // Debug the date formats
    console.log('Date formats analysis:');
    availabilities.forEach(a => {
      const apiDate = a.date;
      const extractedDate = apiDate.split('T')[0];
      const matchingDay = component.weekDays.find(day => day.date === extractedDate);

      console.log({
        apiDate,
        extractedDate,
        matchingDayFound: !!matchingDay,
        matchingDayDate: matchingDay?.date
      });
    });
  } else if (totalDisplayed < availabilities.length) {
    console.warn('Warning: Some availabilities are not displayed in the calendar');

    // Debug which ones are missing
    const displayedIds = [];
    for (const date in availabilitiesByDate) {
      availabilitiesByDate[date].forEach(a => displayedIds.push(a.id));
    }

    const missingAvailabilities = availabilities.filter(a => !displayedIds.includes(a.id));
    console.log('Missing availabilities:', missingAvailabilities);
  } else {
    console.log('Success! All availabilities are properly displayed in the calendar');
  }

  // Check the DOM to see if availabilities are actually rendered
  const availabilityElements = document.querySelectorAll('.bg-green-50, .bg-red-50');
  console.log(`Availability elements in DOM: ${availabilityElements.length}`);

  if (availabilityElements.length === 0 && totalDisplayed > 0) {
    console.warn('Warning: Availabilities are in the component data but not rendered in the DOM');
  } else if (availabilityElements.length > 0) {
    console.log('Success! Availabilities are rendered in the DOM');
  }
}

// Instructions for use
console.log(`
=== Availability Calendar Display Test ===

This script tests whether availabilities are properly displayed in the Calendar view.

To use this script:
1. Navigate to the Weekly Availability page (/availability)
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Run this script by pasting it in the console
4. Check the console output for the test results

Expected behavior:
- The script should show that availabilities from the API are properly displayed in the calendar
- If there are any issues, the script will provide debugging information
`);

// Run the test
testAvailabilityDisplay();
