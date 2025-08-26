// Test script for email composer client filtering
// This script tests that clients are filtered based on the selected project
// Run this in the browser console when on the Email Composer page

console.log('Testing Email Composer client filtering');
console.log('--------------------------------------');

// Test 1: Verify that filteredClients computed property works correctly
function testFilteredClients() {
    console.log('\nTest 1: Verify filteredClients computed property');
    console.log('-------------------------------------------');

    // Get the Vue component instance
    const app = document.querySelector('#app').__vue_app__;
    const composerComponent = Array.from(app._instance.subTree.children)
        .find(child => child.component && child.component.type.__name === 'Composer');

    if (!composerComponent) {
        console.error('Could not find Composer component');
        return false;
    }

    const vm = composerComponent.component.ctx;

    // Log initial state
    console.log('Initial state:');
    console.log('- Projects:', vm.projects.length);
    console.log('- Clients:', vm.clients.length);
    console.log('- Selected project:', vm.emailForm.project_id);
    console.log('- Selected clients:', vm.emailForm.client_ids.length);
    console.log('- Filtered clients:', vm.filteredClients.length);

    // If no project is selected, filteredClients should be empty
    if (!vm.emailForm.project_id) {
        console.log('\nNo project selected:');
        console.log('- Filtered clients:', vm.filteredClients.length);
        console.log('- Expected:', 0);
        console.log('- Result:', vm.filteredClients.length === 0 ? 'PASS' : 'FAIL');
    }

    // Select the first project
    if (vm.projects.length > 0) {
        const firstProject = vm.projects[0];
        vm.emailForm.project_id = firstProject.id;

        // Wait for Vue to update
        setTimeout(() => {
            console.log('\nSelected project:', firstProject.name);
            console.log('- Project clients:', firstProject.clients.length);
            console.log('- Filtered clients:', vm.filteredClients.length);
            console.log('- Expected:', firstProject.clients.length);
            console.log('- Result:', vm.filteredClients.length === firstProject.clients.length ? 'PASS' : 'FAIL');

            // Verify that all filtered clients are related to the selected project
            const projectClientIds = firstProject.clients.map(c => c.id);
            const allClientsRelated = vm.filteredClients.every(client =>
                projectClientIds.includes(client.id)
            );
            console.log('\nAll filtered clients are related to the selected project:');
            console.log('- Result:', allClientsRelated ? 'PASS' : 'FAIL');

            // If there's a second project, test switching projects
            if (vm.projects.length > 1) {
                testSwitchingProjects(vm);
            } else {
                testClientIdsFormatting(vm);
            }
        }, 100);
    } else {
        console.log('No projects available for testing');
        return false;
    }

    return true;
}

// Test 2: Verify that switching projects clears selected clients
function testSwitchingProjects(vm) {
    console.log('\nTest 2: Verify switching projects clears selected clients');
    console.log('---------------------------------------------------');

    const secondProject = vm.projects[1];

    // Select a client from the first project
    if (vm.filteredClients.length > 0) {
        vm.emailForm.client_ids = [vm.filteredClients[0].id];

        console.log('Selected client:', vm.filteredClients[0].name);
        console.log('- Selected clients:', vm.emailForm.client_ids.length);
        console.log('- Expected:', 1);
        console.log('- Result:', vm.emailForm.client_ids.length === 1 ? 'PASS' : 'FAIL');

        // Switch to the second project
        vm.emailForm.project_id = secondProject.id;

        // Wait for Vue to update
        setTimeout(() => {
            console.log('\nSwitched to project:', secondProject.name);
            console.log('- Selected clients:', vm.emailForm.client_ids.length);
            console.log('- Expected:', 0);
            console.log('- Result:', vm.emailForm.client_ids.length === 0 ? 'PASS' : 'FAIL');

            // Verify that filtered clients are updated
            console.log('\nFiltered clients updated:');
            console.log('- Project clients:', secondProject.clients.length);
            console.log('- Filtered clients:', vm.filteredClients.length);
            console.log('- Expected:', secondProject.clients.length);
            console.log('- Result:', vm.filteredClients.length === secondProject.clients.length ? 'PASS' : 'FAIL');

            testClientIdsFormatting(vm);
        }, 100);
    } else {
        console.log('No clients available for testing');
        testClientIdsFormatting(vm);
    }
}

// Test 3: Verify client_ids formatting for submission
function testClientIdsFormatting(vm) {
    console.log('\nTest 3: Verify client_ids formatting for submission');
    console.log('----------------------------------------------');

    // Mock the axios.post method to capture the payload
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

    // Select a project and client
    if (vm.projects.length > 0 && vm.filteredClients.length > 0) {
        vm.emailForm.project_id = vm.projects[0].id;

        // Wait for Vue to update
        setTimeout(() => {
            vm.emailForm.client_ids = [vm.filteredClients[0].id];
            vm.emailForm.subject = 'Test Subject';
            vm.emailForm.body = 'Test Body';

            // Call the submit method
            vm.submitEmailForApproval();

            // Wait for the mock axios.post to be called
            setTimeout(() => {
                console.log('\nVerify client_ids format:');
                if (capturedPayload) {
                    const clientIdsFormatted = Array.isArray(capturedPayload.client_ids) &&
                        capturedPayload.client_ids.every(client =>
                            typeof client === 'object' && 'id' in client
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
        }, 100);
    } else {
        console.log('No projects or clients available for testing');
        console.log('\nAll tests completed.');
    }
}

// Start the tests
testFilteredClients();
