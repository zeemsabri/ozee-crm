// Test script for the multi-select dropdown in Rejected.vue
// Run this in the browser console when on the Rejected Emails page

console.log('Testing multi-select dropdown in Rejected.vue');
console.log('-------------------------------------------');

// Function to test the multi-select dropdown
function testMultiSelectDropdown() {
    // Get the Vue component instance
    const app = document.querySelector('#app').__vue_app__;
    const rejectedComponent = Array.from(app._instance.subTree.children)
        .find(child => child.component && child.component.type.__file &&
              child.component.type.__file.includes('Rejected.vue'));

    if (!rejectedComponent) {
        console.error('Could not find Rejected component');
        return;
    }

    const vm = rejectedComponent.component.ctx;

    // Log initial state
    console.log('Initial state:');
    console.log('- clients:', vm.clients.length);
    console.log('- projects:', vm.projects.length);
    console.log('- rejectedEmails:', vm.rejectedEmails.length);
    console.log('- filteredClients:', vm.filteredClients.length);

    // Test 1: Verify that filteredClients computed property works correctly
    console.log('\nTest 1: Verify filteredClients computed property');
    console.log('-------------------------------------------');

    // If no project is selected, filteredClients should be empty
    if (!vm.editForm.project_id) {
        console.log('No project selected:');
        console.log('- Filtered clients:', vm.filteredClients.length);
        console.log('- Expected:', 0);
        console.log('- Result:', vm.filteredClients.length === 0 ? 'PASS' : 'FAIL');
    }

    // Test 2: Verify that openEditModal sets client_ids correctly
    console.log('\nTest 2: Verify openEditModal sets client_ids correctly');
    console.log('-------------------------------------------');

    // Find a rejected email to test with
    if (vm.rejectedEmails.length > 0) {
        const testEmail = vm.rejectedEmails[0];

        // Mock the getRecipientEmails function to return a known value
        const originalGetRecipientEmails = vm.getRecipientEmails;
        vm.getRecipientEmails = function() {
            return ['test@example.com'];
        };

        // Mock the findClientsByEmails function to return a known value
        const originalFindClientsByEmails = vm.findClientsByEmails;
        vm.findClientsByEmails = function() {
            return [{ id: 1, name: 'Test Client' }];
        };

        // Call openEditModal
        vm.openEditModal(testEmail);

        console.log('openEditModal called:');
        console.log('- editForm.project_id:', vm.editForm.project_id);
        console.log('- editForm.client_ids:', vm.editForm.client_ids);
        console.log('- Expected client_ids length:', 1);
        console.log('- Result:', vm.editForm.client_ids.length === 1 ? 'PASS' : 'FAIL');

        // Restore original functions
        vm.getRecipientEmails = originalGetRecipientEmails;
        vm.findClientsByEmails = originalFindClientsByEmails;
    } else {
        console.log('No rejected emails available for testing');
    }

    // Test 3: Verify that saveEditedEmail formats client_ids correctly
    console.log('\nTest 3: Verify saveEditedEmail formats client_ids correctly');
    console.log('-------------------------------------------');

    // Set up test data
    vm.editForm.project_id = vm.projects.length > 0 ? vm.projects[0].id : 1;
    vm.editForm.client_ids = [{ id: 1, name: 'Test Client' }];
    vm.editForm.subject = 'Test Subject';
    vm.editForm.body = 'Test Body';
    vm.currentEmail = { id: 1 };

    // Mock axios.put to capture the payload
    const originalPut = window.axios.put;
    let capturedPayload = null;

    window.axios.put = function(url, payload) {
        if (url.includes('/api/emails/')) {
            capturedPayload = payload;
            console.log('Captured payload:', payload);

            // Restore the original method
            window.axios.put = originalPut;

            // Return a mock promise
            return Promise.resolve({ data: { id: 1 } });
        }
        return originalPut.apply(this, arguments);
    };

    // Call saveEditedEmail
    vm.saveEditedEmail();

    // Wait for the mock axios.put to be called
    setTimeout(() => {
        console.log('\nVerify client_ids format:');
        if (capturedPayload) {
            const clientIdsFormatted = Array.isArray(capturedPayload.client_ids) &&
                capturedPayload.client_ids.every(client =>
                    typeof client === 'object' &&
                    client !== null &&
                    typeof client.id !== 'undefined' &&
                    typeof client.id !== 'object'
                );

            console.log('- client_ids is array of objects with id property:', clientIdsFormatted ? 'PASS' : 'FAIL');

            if (clientIdsFormatted) {
                console.log('- Example client_ids format:', JSON.stringify(capturedPayload.client_ids));
            }
        } else {
            console.log('- Failed to capture payload');
        }

        console.log('\nAll tests completed.');
    }, 100);
}

// Run the test
testMultiSelectDropdown();
