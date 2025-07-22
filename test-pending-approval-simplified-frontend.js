// Test script for the simplified PendingApprovals.vue component
// Run this in the browser console when on the Pending Approvals page

console.log('Testing Simplified PendingApprovals.vue Component');
console.log('----------------------------------------------');

// Function to test the PendingApprovals.vue component
function testPendingApprovalsComponent() {
    // Get the Vue component instance
    const app = document.querySelector('#app').__vue_app__;
    const pendingApprovalsComponent = Array.from(app._instance.subTree.children)
        .find(child => child.component && child.component.type.__file &&
              child.component.type.__file.includes('PendingApprovals.vue'));

    if (!pendingApprovalsComponent) {
        console.error('Could not find PendingApprovals component');
        return;
    }

    const vm = pendingApprovalsComponent.component.ctx;

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
        if (url === '/api/emails/pending-approval-simplified') {
            return Promise.resolve({
                data: [
                    {
                        id: 1,
                        project: { id: 1, name: 'Test Project' },
                        client: { id: 1, name: 'Test Client' },
                        subject: 'Test Subject',
                        sender: { id: 1, name: 'Test Sender' },
                        created_at: new Date().toISOString(),
                        body: 'Test Body'
                    }
                ]
            });
        } else if (url === '/api/projects') {
            return Promise.resolve({
                data: [
                    { id: 1, name: 'Test Project' }
                ]
            });
        }

        // Default response
        return Promise.resolve({ data: [] });
    };

    // Call fetchInitialData
    vm.fetchInitialData();

    // Check if the correct URL was used
    console.log('Expected URL: /api/emails/pending-approval-simplified');
    console.log('Actual URL:', capturedUrl);
    console.log('Result:', capturedUrl === '/api/emails/pending-approval-simplified' ? 'PASS' : 'FAIL');

    // Test 2: Verify that the table displays the correct fields
    console.log('\nTest 2: Verify that the table displays the correct fields');
    console.log('------------------------------------------------------');

    // Wait for the data to be loaded
    setTimeout(() => {
        // Get the table headers
        const tableHeaders = Array.from(document.querySelectorAll('th')).map(th => th.textContent.trim());
        console.log('Table headers:', tableHeaders);

        // Check if the table headers match the expected headers
        const expectedHeaders = ['Project', 'Client', 'Subject', 'Sender', 'Submitted On', 'Actions'];
        const missingHeaders = expectedHeaders.filter(header => !tableHeaders.includes(header));
        const extraHeaders = tableHeaders.filter(header => !expectedHeaders.includes(header));

        console.log('Missing headers:', missingHeaders.length > 0 ? missingHeaders : 'None');
        console.log('Extra headers:', extraHeaders.length > 0 ? extraHeaders : 'None');
        console.log('Result:', missingHeaders.length === 0 && extraHeaders.length === 0 ? 'PASS' : 'FAIL');

        // Test 3: Verify that the edit modal shows only Project, client name(s), subject and body
        console.log('\nTest 3: Verify that the edit modal shows only Project, client name(s), subject and body');
        console.log('-------------------------------------------------------------------------');

        // Mock the openEditModal function to use our test data
        const originalOpenEditModal = vm.openEditModal;
        vm.openEditModal = function(email) {
            console.log('Opening edit modal with email:', email);
            originalOpenEditModal(email);
        };

        // Call openEditModal with our test data
        vm.openEditModal({
            id: 1,
            project: { id: 1, name: 'Test Project' },
            client: { id: 1, name: 'Test Client' },
            subject: 'Test Subject',
            sender: { id: 1, name: 'Test Sender' },
            created_at: new Date().toISOString(),
            body: 'Test Body'
        });

        // Wait for the modal to render
        setTimeout(() => {
            // Check if the modal shows the correct fields
            const projectSelect = document.querySelector('#project_id');
            const clientName = document.querySelector('#client_name');
            const subject = document.querySelector('#subject');
            const body = document.querySelector('.editor-content');

            console.log('Project select element present:', projectSelect !== null);
            console.log('Client name element present:', clientName !== null);
            console.log('Subject input element present:', subject !== null);
            console.log('Body editor element present:', body !== null);

            console.log('Result:',
                projectSelect !== null &&
                clientName !== null &&
                subject !== null &&
                body !== null ? 'PASS' : 'FAIL');

            // Test 4: Verify that the saveAndApproveEmail function sends the correct payload
            console.log('\nTest 4: Verify that the saveAndApproveEmail function sends the correct payload');
            console.log('--------------------------------------------------------------------');

            // Mock axios.post to capture the payload
            const originalPost = window.axios.post;
            let capturedPayload = null;

            window.axios.post = function(url, payload) {
                capturedPayload = payload;
                console.log('Captured URL:', url);
                console.log('Captured payload:', payload);

                // Restore the original method
                window.axios.post = originalPost;

                // Return a mock promise
                return Promise.resolve({ data: { id: 1 } });
            };

            // Set up the form data
            vm.editForm.project_id = 1;
            vm.editForm.subject = 'Updated Subject';
            vm.editForm.body = 'Updated Body';
            vm.currentEmail = { id: 1 };

            // Call saveAndApproveEmail
            vm.saveAndApproveEmail();

            // Wait for the mock axios.post to be called
            setTimeout(() => {
                // Check if the payload contains the correct fields
                console.log('Payload contains only project_id, subject, and body:',
                    capturedPayload &&
                    Object.keys(capturedPayload).length === 3 &&
                    'project_id' in capturedPayload &&
                    'subject' in capturedPayload &&
                    'body' in capturedPayload);

                console.log('Result:',
                    capturedPayload &&
                    Object.keys(capturedPayload).length === 3 &&
                    'project_id' in capturedPayload &&
                    'subject' in capturedPayload &&
                    'body' in capturedPayload ? 'PASS' : 'FAIL');

                // Close the modal
                vm.showEditModal = false;

                console.log('\nAll tests completed.');
            }, 100);
        }, 100);
    }, 100);
}

// Run the test
testPendingApprovalsComponent();
