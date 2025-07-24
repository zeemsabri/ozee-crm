/**
 * Test script to verify the batch submission functionality for availability records
 *
 * This script can be run in the browser console to test the batch submission
 * of availability records.
 */

// Function to test batch submission
function testBatchSubmission() {
  console.log('=== Testing Batch Submission of Availability Records ===');

  // Check if auth token exists in localStorage
  const token = localStorage.getItem('authToken');
  console.log('Auth token exists in localStorage:', !!token);

  // Ensure auth headers are set
  if (token && !axios.defaults.headers.common['Authorization']) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    console.log('Auth headers set in test script');
  }

  // Create test data for batch submission
  const today = new Date();
  const testData = [];

  // Create 3 test records for consecutive days
  for (let i = 1; i <= 3; i++) {
    const date = new Date(today);
    date.setDate(today.getDate() + i);
    const dateString = date.toISOString().split('T')[0];

    // Alternate between available and not available
    const isAvailable = i % 2 === 1;

    testData.push({
      date: dateString,
      is_available: isAvailable,
      reason: isAvailable ? null : `Test reason for day ${i}`,
      time_slots: isAvailable ? [
        { start_time: '09:00', end_time: '12:00' },
        { start_time: '13:00', end_time: '17:00' }
      ] : null
    });
  }

  console.log('Test data for batch submission:', testData);

  // Make a test request to the batch API endpoint
  console.log('Making test request to /api/availabilities/batch...');
  axios.post('/api/availabilities/batch', {
    availabilities: testData
  })
    .then(response => {
      console.log('Batch submission successful:', response.status);
      console.log('Response data:', response.data);

      // Verify the saved records by fetching them
      const startDate = testData[0].date;
      const endDate = testData[testData.length - 1].date;

      console.log(`Fetching saved records from ${startDate} to ${endDate}...`);
      return axios.get(`/api/availabilities?start_date=${startDate}&end_date=${endDate}`);
    })
    .then(response => {
      console.log('Fetch successful:', response.status);
      console.log('Fetched availabilities:', response.data.availabilities);

      // Check if all test records were saved
      const savedDates = response.data.availabilities.map(a => a.date);
      const testDates = testData.map(a => a.date);

      const allSaved = testDates.every(date => savedDates.includes(date));
      console.log('All test records saved:', allSaved);
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
=== Availability Batch Submission Test ===

This script tests the batch submission functionality for availability records.

To use this script:
1. Navigate to any page in the application
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Run this script by pasting it in the console
4. Check the console output for the test results

Expected behavior:
- The script should successfully submit multiple availability records in a single request
- The script should then fetch the saved records to verify they were saved correctly
- All test records should be present in the fetched data
`);

// Run the test
testBatchSubmission();
