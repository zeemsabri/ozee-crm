// Test script to verify the bonus configuration API
const axios = require('axios');

// Function to test creating a bonus configuration
async function testCreateBonusConfiguration() {
  try {
    // Generate a UUID
    const uuid = crypto.randomUUID();

    // Create the payload with both id and uuid fields
    const payload = {
      id: uuid,
      uuid: uuid,
      name: "Daily Standup Bonus",
      type: "bonus",
      amountType: "percentage",
      value: 1,
      appliesTo: "standup",
      targetBonusTypeForRevocation: "",
      isActive: true
    };

    console.log('Sending payload:', payload);

    // Make the POST request
    const response = await axios.post('http://localhost:8000/api/bonus-configurations', payload, {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_AUTH_TOKEN' // Replace with a valid token
      }
    });

    console.log('Response status:', response.status);
    console.log('Response data:', response.data);

    return response.data;
  } catch (error) {
    console.error('Error creating bonus configuration:');
    if (error.response) {
      // The request was made and the server responded with a status code
      // that falls out of the range of 2xx
      console.error('Response status:', error.response.status);
      console.error('Response data:', error.response.data);
    } else if (error.request) {
      // The request was made but no response was received
      console.error('No response received:', error.request);
    } else {
      // Something happened in setting up the request that triggered an Error
      console.error('Error message:', error.message);
    }
    throw error;
  }
}

// Run the test
testCreateBonusConfiguration()
  .then(data => {
    console.log('Test completed successfully!');
  })
  .catch(error => {
    console.error('Test failed!');
  });
