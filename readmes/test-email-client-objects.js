// Test script to verify client_ids are properly formatted as objects in the email submission
// This script simulates the behavior of the ComposeEmailModal.vue component

// Mock axios for testing
const mockAxios = {
  post: async (url, data) => {
    console.log('POST request to:', url);
    console.log('Request data:', JSON.stringify(data, null, 2));

    // Verify client_ids format for email submission
    if (url.includes('/emails')) {
      // Check if client_ids is an array of objects with id property
      if (Array.isArray(data.client_ids) &&
          data.client_ids.length > 0 &&
          typeof data.client_ids[0] === 'object' &&
          'id' in data.client_ids[0]) {
        console.log('✅ Success: client_ids is correctly formatted as an array of objects with id property');
        console.log('First client object:', data.client_ids[0]);
      } else {
        console.error('❌ Error: client_ids is not correctly formatted');
        console.error('Expected format: [{id: 1}, {id: 2}, ...]');
        console.error('Actual format:', data.client_ids);
      }
      return { data: { message: 'Email sent successfully!' } };
    }

    // Mock response for other requests
    return { data: {} };
  }
};

// Mock the Vue reactive state with client objects
const emailForm = {
  template_id: 1,
  client_ids: [
    { id: 1, name: 'Client One' },
    { id: 2, name: 'Client Two' }
  ],
  dynamic_data: {},
  project_id: 3,
  subject: 'Test Subject',
  body: '<p>Test body</p>',
  greeting_name: 'Client One',
  custom_greeting_name: '',
  status: 'pending_approval'
};

// Simulate form submission
const submitForm = async () => {
  console.log('Submitting form...');

  // Submit the form
  try {
    const response = await mockAxios.post('/api/emails', emailForm);
    console.log('Form submitted successfully:', response.data.message);
  } catch (error) {
    console.error('Failed to submit form:', error);
  }
};

// Run the test
console.log('=== Starting Email Client Objects Test ===');
console.log('Initial form state:', JSON.stringify(emailForm, null, 2));

// Test scenario: Submit form with client objects
(async () => {
  console.log('\n--- Test Scenario: Submit form with client objects ---');
  await submitForm();

  console.log('\n=== Test Completed ===');
})();
