// Test script for email client_ids formatting
// This script tests that client_ids are formatted correctly when submitting the form
// Run this in the browser console when on the Email Composer page

console.log('Testing Email Client IDs Formatting');
console.log('----------------------------------');

// Mock client data for testing
const mockClients = [
    { id: 1, name: 'Test Client 1' },
    { id: 2, name: 'Test Client 2' },
    { id: 3, name: 'Test Client 3' }
];

// Test 1: Test client_ids formatting with simple ID values
function testSimpleIdValues() {
    console.log('\nTest 1: Format client_ids with simple ID values');
    console.log('-------------------------------------------');

    // Simple array of IDs
    const simpleIds = [1, 2, 3];

    // Format client_ids as array of objects with id property
    const formattedIds = simpleIds.map(clientId => {
        // Check if clientId is already an object with an id property
        if (typeof clientId === 'object' && clientId !== null) {
            return { id: clientId.id };
        }
        // Otherwise, assume it's a simple ID value
        return { id: clientId };
    });

    console.log('Input:', JSON.stringify(simpleIds));
    console.log('Output:', JSON.stringify(formattedIds));

    // Verify that each item in formattedIds is an object with an id property
    const isFormatted = formattedIds.every(item =>
        typeof item === 'object' &&
        item !== null &&
        typeof item.id !== 'undefined' &&
        typeof item.id !== 'object'
    );

    console.log('Correctly formatted:', isFormatted ? 'PASS' : 'FAIL');
}

// Test 2: Test client_ids formatting with object values
function testObjectValues() {
    console.log('\nTest 2: Format client_ids with object values');
    console.log('------------------------------------------');

    // Array of client objects
    const objectIds = mockClients;

    // Format client_ids as array of objects with id property
    const formattedIds = objectIds.map(clientId => {
        // Check if clientId is already an object with an id property
        if (typeof clientId === 'object' && clientId !== null) {
            return { id: clientId.id };
        }
        // Otherwise, assume it's a simple ID value
        return { id: clientId };
    });

    console.log('Input:', JSON.stringify(objectIds));
    console.log('Output:', JSON.stringify(formattedIds));

    // Verify that each item in formattedIds is an object with an id property
    const isFormatted = formattedIds.every(item =>
        typeof item === 'object' &&
        item !== null &&
        typeof item.id !== 'undefined' &&
        typeof item.id !== 'object'
    );

    console.log('Correctly formatted:', isFormatted ? 'PASS' : 'FAIL');
}

// Test 3: Test client_ids formatting with mixed values
function testMixedValues() {
    console.log('\nTest 3: Format client_ids with mixed values');
    console.log('-----------------------------------------');

    // Mixed array of IDs and objects
    const mixedIds = [1, mockClients[1], 3];

    // Format client_ids as array of objects with id property
    const formattedIds = mixedIds.map(clientId => {
        // Check if clientId is already an object with an id property
        if (typeof clientId === 'object' && clientId !== null) {
            return { id: clientId.id };
        }
        // Otherwise, assume it's a simple ID value
        return { id: clientId };
    });

    console.log('Input:', JSON.stringify(mixedIds));
    console.log('Output:', JSON.stringify(formattedIds));

    // Verify that each item in formattedIds is an object with an id property
    const isFormatted = formattedIds.every(item =>
        typeof item === 'object' &&
        item !== null &&
        typeof item.id !== 'undefined' &&
        typeof item.id !== 'object'
    );

    console.log('Correctly formatted:', isFormatted ? 'PASS' : 'FAIL');
}

// Test 4: Test with the actual Composer component
function testWithComposerComponent() {
    console.log('\nTest 4: Test with actual Composer component');
    console.log('----------------------------------------');

    // Get the Vue component instance
    const app = document.querySelector('#app').__vue_app__;
    const composerComponent = Array.from(app._instance.subTree.children)
        .find(child => child.component && child.component.type.__name === 'Composer');

    if (!composerComponent) {
        console.error('Could not find Composer component');
        return false;
    }

    const vm = composerComponent.component.ctx;

    // Mock axios.post to capture the payload
    const originalPost = window.axios.post;
    let capturedPayload = null;

    window.axios.post = function(url, payload) {
        if (url === '/api/emails') {
            capturedPayload = payload;
            console.log('Captured payload:', payload);

            // Restore the original method
            window.axios.post = originalPost;

            // Return a mock promise
            return Promise.resolve({ data: { id: 1 } });
        }
        return originalPost.apply(this, arguments);
    };

    // Set up test data
    vm.emailForm.project_id = 1; // Assuming project with ID 1 exists
    vm.emailForm.subject = 'Test Subject';
    vm.emailForm.body = 'Test Body';

    // Test with client objects
    if (vm.filteredClients.length > 0) {
        console.log('\nTesting with client objects:');
        vm.emailForm.client_ids = [vm.filteredClients[0]];

        // Call the submit method
        vm.submitEmailForApproval();

        // Wait for the mock axios.post to be called
        setTimeout(() => {
            console.log('Payload with client objects:', capturedPayload);

            if (capturedPayload) {
                const clientIdsFormatted = Array.isArray(capturedPayload.client_ids) &&
                    capturedPayload.client_ids.every(client =>
                        typeof client === 'object' &&
                        client !== null &&
                        typeof client.id !== 'undefined' &&
                        typeof client.id !== 'object'
                    );

                console.log('client_ids correctly formatted:', clientIdsFormatted ? 'PASS' : 'FAIL');
            } else {
                console.log('Failed to capture payload');
            }

            console.log('\nAll tests completed.');
        }, 100);
    } else {
        console.log('No clients available for testing with the component');
        console.log('\nAll tests completed.');
    }
}

// Run the tests
testSimpleIdValues();
testObjectValues();
testMixedValues();
testWithComposerComponent();
