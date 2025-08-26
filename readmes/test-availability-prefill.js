/**
 * Test script to verify the pre-filling functionality in the AvailabilityModal component
 *
 * This script can be run in the browser console to test that:
 * 1. Existing availability data is properly fetched and pre-filled when the modal is opened
 * 2. The UI correctly indicates which dates already have entries
 * 3. Users can update existing entries and add new ones
 * 4. The batch submission works correctly for both new and updated entries
 */

// Function to test the pre-filling functionality
function testAvailabilityPrefill() {
  console.log('=== Testing Availability Pre-filling ===');

  // Step 1: Create a test availability entry
  console.log('Step 1: Creating a test availability entry...');

  // Get tomorrow's date
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const tomorrowString = tomorrow.toISOString().split('T')[0];

  // Ensure auth headers are set
  const token = localStorage.getItem('authToken');
  if (token && !axios.defaults.headers.common['Authorization']) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    console.log('Auth headers set in test script');
  }

  // Create a test availability entry
  axios.post('/api/availabilities', {
    date: tomorrowString,
    is_available: true,
    time_slots: [
      { start_time: '09:00', end_time: '12:00' }
    ]
  })
  .then(response => {
    console.log('Test availability entry created:', response.data);

    // Step 2: Open the availability modal
    console.log('Step 2: Open the availability modal and check if the entry is pre-filled');
    console.log('Please open the availability modal now by clicking the "Submit Availability" button');
    console.log('Then check if the entry for tomorrow is pre-filled and has a "Saved" badge');

    // Step 3: Instructions for manual testing
    console.log('Step 3: Manual testing instructions:');
    console.log('1. Verify that the entry for tomorrow is pre-filled and has a "Saved" badge');
    console.log('2. Update the existing entry (e.g., change the time slot)');
    console.log('3. Add a new entry for another day');
    console.log('4. Submit the form');
    console.log('5. Open the modal again and verify that both entries are pre-filled');

    // Step 4: Verify the updated entries
    console.log('Step 4: After completing the manual testing steps, run the following code to verify the updated entries:');
    console.log('axios.get("/api/availabilities").then(response => console.log(response.data))');
  })
  .catch(error => {
    // If the entry already exists, that's fine
    if (error.response && error.response.status === 409) {
      console.log('Test availability entry already exists, proceeding with testing');

      // Step 2: Open the availability modal
      console.log('Step 2: Open the availability modal and check if the entry is pre-filled');
      console.log('Please open the availability modal now by clicking the "Submit Availability" button');
      console.log('Then check if the entry for tomorrow is pre-filled and has a "Saved" badge');

      // Step 3: Instructions for manual testing
      console.log('Step 3: Manual testing instructions:');
      console.log('1. Verify that the entry for tomorrow is pre-filled and has a "Saved" badge');
      console.log('2. Update the existing entry (e.g., change the time slot)');
      console.log('3. Add a new entry for another day');
      console.log('4. Submit the form');
      console.log('5. Open the modal again and verify that both entries are pre-filled');

      // Step 4: Verify the updated entries
      console.log('Step 4: After completing the manual testing steps, run the following code to verify the updated entries:');
      console.log('axios.get("/api/availabilities").then(response => console.log(response.data))');
    } else {
      console.error('Error creating test availability entry:', error);
    }
  });
}

// Instructions for use
console.log(`
=== Availability Pre-filling Test ===

This script tests whether the AvailabilityModal component correctly pre-fills existing availability data.

To use this script:
1. Navigate to the Dashboard page (/)
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Run this script by pasting it in the console
4. Follow the instructions in the console output

Expected behavior:
- The script will create a test availability entry for tomorrow
- When you open the availability modal, the entry for tomorrow should be pre-filled
- The entry should have a "Saved" badge
- You should be able to update the existing entry and add new ones
- When you submit the form and open the modal again, all entries should be pre-filled
`);

// Run the test
testAvailabilityPrefill();
