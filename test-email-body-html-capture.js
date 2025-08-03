// Test script to verify the email body_html is properly captured from the preview response
// This script simulates the behavior of the ComposeEmailModal.vue component

// Mock axios for testing
const mockAxios = {
  post: async (url, data) => {
    console.log('POST request to:', url);
    console.log('Request data:', JSON.stringify(data, null, 2));

    // Mock response for email preview
    if (url.includes('/email-preview')) {
      console.log('Returning mock preview response with subject and body_html');
      return {
        data: {
          subject: "Project Update",
          body_html: "<p>This is the formatted HTML body from the preview response</p>"
        }
      };
    }

    // Mock response for email submission
    if (url.includes('/emails')) {
      console.log('Email submission data:', JSON.stringify(data, null, 2));
      // Verify that body is included in the submission and matches the body_html from preview
      if (data.body === "<p>This is the formatted HTML body from the preview response</p>") {
        console.log('✅ Success: body_html is correctly included as body in the email submission');
      } else {
        console.error(`❌ Error: Expected body to be the body_html from preview but got "${data.body}"`);
      }
      return { data: { message: 'Email sent successfully!' } };
    }

    return { data: {} };
  }
};

// Mock the Vue reactive state
const emailForm = {
  template_id: 1,
  client_ids: [2],
  dynamic_data: {},
  project_id: 3,
  subject: '',
  body: '',
  greeting_name: '',
  custom_greeting_name: '',
  status: 'pending_approval'
};

// Mock the previewContent ref
let previewContent = '';

// Simulate the fetchPreview function
const fetchPreview = async () => {
  console.log('Fetching preview...');

  try {
    const response = await mockAxios.post(`/api/projects/${emailForm.project_id}/email-preview`, {
      template_id: emailForm.template_id,
      client_id: emailForm.client_ids[0],
      dynamic_data: emailForm.dynamic_data,
    });

    // Store the body_html in previewContent
    previewContent = response.data.body_html;
    console.log('Preview content set to:', previewContent);

    // Capture the subject from the preview response
    if (response.data.subject) {
      emailForm.subject = response.data.subject;
      console.log('Subject captured from preview:', emailForm.subject);
    }
  } catch (error) {
    console.error('Failed to fetch email preview:', error);
  }
};

// Simulate the prepareFormData function
const prepareFormData = () => {
  console.log('Preparing form data...');

  // Format client_ids to be an array of objects with id property
  emailForm.client_ids = emailForm.client_ids.map(clientId => ({ id: clientId }));

  // Set subject from template only if it hasn't been set by the preview
  if (!emailForm.subject) {
    emailForm.subject = 'Default Subject'; // Simulating template subject
    console.log('Subject set from template:', emailForm.subject);
  } else {
    console.log('Using subject from preview:', emailForm.subject);
  }

  // Set body from preview content
  if (previewContent &&
      previewContent !== '<p class="text-gray-500 italic">Select a template and at least one recipient to see a preview.</p>' &&
      previewContent !== '<p class="text-red-500 italic">Error loading preview.</p>') {
    emailForm.body = previewContent;
    console.log('Body set from preview content:', emailForm.body);
  } else {
    console.log('No valid preview content available for body');
  }

  console.log('Form data prepared:', JSON.stringify(emailForm, null, 2));
  return true;
};

// Simulate form submission
const submitForm = async () => {
  console.log('Submitting form...');

  // Prepare the form data
  prepareFormData();

  // Submit the form
  try {
    const response = await mockAxios.post('/api/emails', emailForm);
    console.log('Form submitted successfully:', response.data.message);
  } catch (error) {
    console.error('Failed to submit form:', error);
  }
};

// Run the test
console.log('=== Starting Email Body HTML Capture Test ===');
console.log('Initial form state:', JSON.stringify(emailForm, null, 2));

// Test scenario: Preview then submit
(async () => {
  console.log('\n--- Test Scenario: Preview then Submit ---');
  await fetchPreview();
  await submitForm();

  console.log('\n=== Test Completed ===');
})();
