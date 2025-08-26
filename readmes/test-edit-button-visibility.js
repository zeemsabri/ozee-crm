// This is a simple test script to verify that the Edit Project button remains visible after saving a project
// You can run this in your browser's console when viewing a project page

// Step 1: Check if the Edit Project button is visible initially
function checkEditButtonVisibility() {
    const editButton = document.querySelector('button:contains("Edit Project")');
    console.log('Edit Project button is ' + (editButton ? 'visible' : 'not visible'));
    return !!editButton;
}

// Step 2: Simulate clicking the Edit Project button to open the modal
function openEditModal() {
    const editButton = document.querySelector('button:contains("Edit Project")');
    if (editButton) {
        console.log('Clicking Edit Project button...');
        editButton.click();
        return true;
    } else {
        console.log('Edit Project button not found');
        return false;
    }
}

// Step 3: Simulate saving the project
function saveProject() {
    // In a real test, you would fill in the form fields and click the save button
    // For this test, we'll just call the handleProjectSubmit function directly
    console.log('Simulating project save...');

    // Get the Vue component instance
    // Note: This is a simplified approach and may not work in all cases
    // In a real test, you would use proper Vue testing utilities
    const app = document.querySelector('#app').__vue_app__;
    const component = app._instance.proxy;

    // Call the handleProjectSubmit function with a mock project
    // This should trigger the fetchProjectData call
    component.handleProjectSubmit({
        ...component.project,
        name: component.project.name + ' (Updated)'
    });

    return true;
}

// Step 4: Check if the Edit Project button is still visible after saving
function testEditButtonVisibilityAfterSave() {
    console.log('Starting test...');

    // Check initial visibility
    const initiallyVisible = checkEditButtonVisibility();
    if (!initiallyVisible) {
        console.log('Test failed: Edit Project button is not visible initially');
        return;
    }

    // Open the edit modal
    if (!openEditModal()) {
        console.log('Test failed: Could not open edit modal');
        return;
    }

    // Wait for the modal to open
    setTimeout(() => {
        // Save the project
        if (!saveProject()) {
            console.log('Test failed: Could not save project');
            return;
        }

        // Wait for the save to complete and the modal to close
        setTimeout(() => {
            // Check if the button is still visible
            const stillVisible = checkEditButtonVisibility();
            if (stillVisible) {
                console.log('Test passed: Edit Project button is still visible after saving');
            } else {
                console.log('Test failed: Edit Project button disappeared after saving');
            }
        }, 1000);
    }, 1000);
}

// Run the test
testEditButtonVisibilityAfterSave();
