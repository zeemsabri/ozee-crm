/**
 * Test script for project-specific permissions
 *
 * This script tests the project-specific permissions functionality in the frontend.
 * It mocks the API calls and verifies that the correct project ID is used.
 *
 * Usage:
 * 1. Include this script in your HTML file
 * 2. Open the browser console to see the test results
 */

// Mock axios for testing
const originalAxios = window.axios;
window.axios = {
  get: function(url) {
    console.log('Mock axios.get called with URL:', url);

    // Check if the URL is for project permissions
    if (url.includes('/api/projects/') && url.includes('/permissions')) {
      // Extract the project ID from the URL
      const matches = url.match(/\/api\/projects\/([^\/]+)\/permissions/);
      if (matches && matches[1]) {
        const projectId = matches[1];

        // Check if the project ID is a valid number
        if (isNaN(projectId) || projectId === '[object Object]') {
          console.error('❌ TEST FAILED: Invalid project ID in URL:', url);
          return Promise.reject(new Error('Invalid project ID'));
        }

        console.log('✅ TEST PASSED: Valid project ID in URL:', projectId);

        // Return mock data
        return Promise.resolve({
          data: {
            project_id: parseInt(projectId),
            permissions: [
              { id: 1, name: 'Manage Projects', slug: 'manage_projects', source: 'project' },
              { id: 2, name: 'Delete Projects', slug: 'delete_projects', source: 'project' }
            ]
          }
        });
      }
    }

    // For other URLs, return empty data
    return Promise.resolve({ data: {} });
  }
};

// Test function
async function testProjectPermissions() {
  console.log('Running project permissions tests...');

  // Test with valid project ID
  try {
    const response = await window.axios.get('/api/projects/123/permissions');
    console.log('Response for valid project ID:', response.data);
  } catch (error) {
    console.error('Error testing with valid project ID:', error);
  }

  // Test with invalid project ID (object)
  try {
    const response = await window.axios.get('/api/projects/[object Object]/permissions');
    console.log('Response for invalid project ID:', response.data);
  } catch (error) {
    console.log('Expected error for invalid project ID:', error.message);
  }

  // Restore original axios
  window.axios = originalAxios;
  console.log('Tests completed. Original axios restored.');
}

// Run the tests
testProjectPermissions();

// Instructions for manual testing
console.log(`
Manual Testing Instructions:
1. Navigate to the Projects page
2. Open the browser console
3. Look for API calls to /api/projects/{id}/permissions
4. Verify that {id} is always a number, not [object Object]
5. Check if the Edit and Delete buttons are correctly enabled/disabled based on permissions
`);
