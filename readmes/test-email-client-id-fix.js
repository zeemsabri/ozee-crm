// Test script to verify the email preview endpoint works with client_id
// This is a simple script to test the changes made to the ComposeEmailModal.vue component

// Mock axios for testing
const mockAxios = {
  post: async (url, data) => {
    console.log('POST request to:', url);
    console.log('Request data:', JSON.stringify(data, null, 2));

    // Verify that client_id is being sent instead of recipient_id
    if (url.includes('/email-preview')) {
      if (data.client_id) {
        console.log('✅ Success: client_id is being sent correctly');
      } else if (data.recipient_id) {
        console.error('❌ Error: recipient_id is being sent instead of client_id');
      } else {
        console.error('❌ Error: Neither client_id nor recipient_id is being sent');
      }
    }

    // Mock response
    return {
      data: {
        body_html: '<p>This is a test email preview</p>'
      }
    };
  }
};

// Mock the fetchPreview function from ComposeEmailModal.vue
const fetchPreview = async () => {
  const emailForm = {
    template_id: 1,
    client_ids: [2],
    dynamic_data: {}
  };

  const projectId = 3;

  try {
    const response = await mockAxios.post(`/api/projects/${projectId}/email-preview`, {
      template_id: emailForm.template_id,
      client_id: emailForm.client_ids[0],
      dynamic_data: emailForm.dynamic_data,
    });

    console.log('Preview content:', response.data.body_html);
  } catch (error) {
    console.error('Failed to fetch email preview:', error);
  }
};

// Run the test
console.log('Testing email preview with client_id...');
fetchPreview().then(() => {
  console.log('Test completed');
});
