// Test script to verify the fix for empty body in email submission
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
      // Verify that body is included in the submission and not empty
      if (data.body && data.body.length > 0) {
        console.log('✅ Success: body is not empty in the email submission');
        console.log('Body content:', data.body);
      } else {
        console.error('❌ Error: body is empty in the email submission');
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

// Simulate the prepareFormData function with the fix
const prepareFormData = async () => {
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

  // Get the preview content for the body if available
  if (previewContent &&
      previewContent !== '<p class="text-gray-500 italic">Select a template and at least one recipient to see a preview.</p>' &&
      previewContent !== '<p class="text-red-500 italic">Error loading preview.</p>') {
    emailForm.body = previewContent;
    console.log('Body set from existing preview content:', emailForm.body);
  } else {
    // Fetch preview one last time if possible
    if (emailForm.template_id && emailForm.client_ids.length > 0) {
      console.log('No valid preview content available, fetching preview...');
      // Use await to ensure the preview is fetched before continuing
      await fetchPreview();
      emailForm.body = previewContent;
      console.log('Body set from newly fetched preview content:', emailForm.body);
    }
  }

  console.log('Form data prepared:', JSON.stringify(emailForm, null, 2));
  return true;
};

// Simulate form submission
const submitForm = async () => {
  console.log('Submitting form...');

  // Prepare the form data with the async function
  await prepareFormData();

  // Submit the form
  try {
    const response = await mockAxios.post('/api/emails', emailForm);
    console.log('Form submitted successfully:', response.data.message);
  } catch (error) {
    console.error('Failed to submit form:', error);
  }
};

// Run the test
console.log('=== Starting Email Body Fix Test ===');
console.log('Initial form state:', JSON.stringify(emailForm, null, 2));

// Test scenario: Submit without preview first
(async () => {
  console.log('\n--- Test Scenario: Submit without preview first ---');
  await submitForm();

  console.log('\n=== Test Completed ===');
})();
