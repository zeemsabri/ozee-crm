// Test script for the View/Edit button in Rejected.vue
// Run this in the browser console when on the Rejected Emails page

console.log('Testing View/Edit button in Rejected.vue');
console.log('---------------------------------------');

// Function to test clicking the View/Edit button
function testViewEditButton() {
    // Get all View/Edit buttons on the page
    const viewEditButtons = Array.from(document.querySelectorAll('button')).filter(
        button => button.textContent.trim() === 'View/Edit'
    );

    console.log(`Found ${viewEditButtons.length} View/Edit buttons on the page`);

    if (viewEditButtons.length === 0) {
        console.log('No View/Edit buttons found. Make sure you are on the Rejected Emails page and there are rejected emails listed.');
        return;
    }

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
    console.log('- clients:', vm.clients);
    console.log('- projects:', vm.projects);
    console.log('- rejectedEmails:', vm.rejectedEmails);

    // Test clicking the first View/Edit button
    console.log('\nClicking the first View/Edit button...');

    try {
        // Simulate clicking the button by calling openEditModal directly
        const firstEmail = vm.rejectedEmails[0];
        vm.openEditModal(firstEmail);

        console.log('openEditModal called successfully');
        console.log('- currentEmail:', vm.currentEmail);
        console.log('- editForm:', vm.editForm);
        console.log('- showEditModal:', vm.showEditModal);

        // Check if selectedProjectClient computed property works
        console.log('\nTesting selectedProjectClient computed property:');
        console.log('- selectedProjectClient:', vm.selectedProjectClient);
        console.log('- Result:', vm.selectedProjectClient !== undefined ? 'PASS' : 'FAIL');

        // Close the modal
        vm.showEditModal = false;
        console.log('\nModal closed');

        console.log('\nTest completed successfully!');
    } catch (error) {
        console.error('Error during test:', error);
    }
}

// Run the test
testViewEditButton();
