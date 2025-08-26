// Test script for the simplified Rejected.vue component
// Run this in the browser console when on the Rejected Emails page

console.log('Testing Simplified Rejected.vue Component');
console.log('---------------------------------------');

// Function to test the Rejected.vue component
function testRejectedComponent() {
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

    // Test 1: Verify that the component is using the simplified API endpoint
    console.log('\nTest 1: Verify that the component is using the simplified API endpoint');
    console.log('-------------------------------------------------------------------');

    // Mock axios.get to capture the URL
    const originalGet = window.axios.get;
    let capturedUrl = null;

    window.axios.get = function(url) {
        capturedUrl = url;
        console.log('Captured URL:', url);

        // Restore the original method
        window.axios.get = originalGet;

        // Return a mock promise with simplified data
        return Promise.resolve({
            data: [
                {
                    id: 1,
                    subject: 'Test Subject',
                    body: 'Test Body',
                    rejection_reason: 'Test Rejection Reason',
                    created_at: new Date().toISOString()
                }
            ]
        });
    };

    // Call fetchInitialData
    vm.fetchInitialData();

    // Check if the correct URL was used
    console.log('Expected URL: /api/emails/rejected-simplified');
    console.log('Actual URL:', capturedUrl);
    console.log('Result:', capturedUrl === '/api/emails/rejected-simplified' ? 'PASS' : 'FAIL');

    // Test 2: Verify that the table only displays the required fields
    console.log('\nTest 2: Verify that the table only displays the required fields');
    console.log('----------------------------------------------------------');

    // Get the table headers
    const tableHeaders = Array.from(document.querySelectorAll('th')).map(th => th.textContent.trim());
    console.log('Table headers:', tableHeaders);

    // Check if the table headers match the expected headers
    const expectedHeaders = ['Subject', 'Rejection Reason', 'Submitted On', 'Actions'];
    const missingHeaders = expectedHeaders.filter(header => !tableHeaders.includes(header));
    const extraHeaders = tableHeaders.filter(header => !expectedHeaders.includes(header));

    console.log('Missing headers:', missingHeaders.length > 0 ? missingHeaders : 'None');
    console.log('Extra headers:', extraHeaders.length > 0 ? extraHeaders : 'None');
    console.log('Result:', missingHeaders.length === 0 && !extraHeaders.includes('Project') && !extraHeaders.includes('Client') ? 'PASS' : 'FAIL');

    // Test 3: Verify that the edit modal doesn't have project and client selection fields
    console.log('\nTest 3: Verify that the edit modal doesn\'t have project and client selection fields');
    console.log('-------------------------------------------------------------------------');

    // Set up test data
    vm.currentEmail = {
        id: 1,
        subject: 'Test Subject',
        body: 'Test Body',
        rejection_reason: 'Test Rejection Reason',
        created_at: new Date().toISOString()
    };

    // Open the edit modal
    vm.showEditModal = true;

    // Wait for the modal to render
    setTimeout(() => {
        // Check if the project and client selection fields are not present
        const projectSelect = document.querySelector('#project_id');
        const clientSelect = document.querySelector('#client_ids');

        console.log('Project select element present:', projectSelect !== null);
        console.log('Client select element present:', clientSelect !== null);
        console.log('Result:', projectSelect === null && clientSelect === null ? 'PASS' : 'FAIL');

        // Test 4: Verify that the saveEditedEmail function only sends subject and body
        console.log('\nTest 4: Verify that the saveEditedEmail function only sends subject and body');
        console.log('--------------------------------------------------------------------');

        // Mock axios.put to capture the payload
        const originalPut = window.axios.put;
        let capturedPayload = null;

        window.axios.put = function(url, payload) {
            capturedPayload = payload;
            console.log('Captured payload:', payload);

            // Restore the original method
            window.axios.put = originalPut;

            // Return a mock promise
            return Promise.resolve({ data: { id: 1 } });
        };

        // Set up the form data
        vm.editForm.subject = 'Updated Subject';
        vm.editForm.body = 'Updated Body';

        // Call saveEditedEmail
        vm.saveEditedEmail();

        // Wait for the mock axios.put to be called
        setTimeout(() => {
            // Check if the payload only contains subject and body
            console.log('Payload contains only subject and body:',
                capturedPayload &&
                Object.keys(capturedPayload).length === 2 &&
                'subject' in capturedPayload &&
                'body' in capturedPayload);

            console.log('Result:',
                capturedPayload &&
                Object.keys(capturedPayload).length === 2 &&
                'subject' in capturedPayload &&
                'body' in capturedPayload ? 'PASS' : 'FAIL');

            // Close the modal
            vm.showEditModal = false;

            console.log('\nAll tests completed.');
        }, 100);
    }, 100);
}

// Run the test
testRejectedComponent();
