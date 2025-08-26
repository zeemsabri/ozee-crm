// Test script for the RichTextEditor in Rejected.vue
// Run this in the browser console when on the Rejected Emails page

console.log('Testing RichTextEditor in Rejected.vue');
console.log('-------------------------------------');

// Function to test the RichTextEditor
function testRichTextEditor() {
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

    // Test 1: Verify that RichTextEditor is being used
    console.log('\nTest 1: Verify that RichTextEditor is being used');
    console.log('-------------------------------------------');

    // Find a rejected email to test with
    if (vm.rejectedEmails.length > 0) {
        const testEmail = vm.rejectedEmails[0];

        // Open the edit modal
        vm.openEditModal(testEmail);

        // Wait for the modal to render
        setTimeout(() => {
            // Check if the RichTextEditor is present
            const richTextEditor = document.querySelector('.rich-text-editor');
            const toolbar = document.querySelector('.toolbar');

            console.log('RichTextEditor present:', richTextEditor !== null);
            console.log('Toolbar present:', toolbar !== null);
            console.log('Result:', richTextEditor !== null && toolbar !== null ? 'PASS' : 'FAIL');

            // Test 2: Verify that formatting works
            console.log('\nTest 2: Verify that formatting works');
            console.log('-----------------------------------');

            // Set some formatted content
            const formattedContent = '<p>This is <strong>bold</strong> and <em>italic</em> text.</p>';
            vm.editForm.body = formattedContent;

            // Wait for the content to be updated
            setTimeout(() => {
                // Get the editor content
                const editorContent = document.querySelector('.editor-content');

                console.log('Editor content updated:', editorContent.innerHTML.includes('<strong>bold</strong>'));
                console.log('Formatting preserved:',
                    editorContent.innerHTML.includes('<strong>bold</strong>') &&
                    editorContent.innerHTML.includes('<em>italic</em>'));
                console.log('Result:',
                    editorContent.innerHTML.includes('<strong>bold</strong>') &&
                    editorContent.innerHTML.includes('<em>italic</em>') ? 'PASS' : 'FAIL');

                // Test 3: Verify that toolbar buttons work
                console.log('\nTest 3: Verify that toolbar buttons work');
                console.log('--------------------------------------');

                // Get the toolbar buttons
                const boldButton = Array.from(document.querySelectorAll('.toolbar-button'))
                    .find(button => button.textContent.trim() === 'B');

                if (boldButton) {
                    // Set up a test to check if the button works
                    console.log('Bold button found');
                    console.log('Note: Manual testing required for toolbar buttons');
                    console.log('To test: Select text in the editor and click the bold button');
                } else {
                    console.log('Bold button not found');
                    console.log('Result: FAIL');
                }

                // Test 4: Verify that the content is saved correctly
                console.log('\nTest 4: Verify that the content is saved correctly');
                console.log('----------------------------------------------');

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

                // Call saveEditedEmail
                vm.saveEditedEmail();

                // Wait for the mock axios.put to be called
                setTimeout(() => {
                    console.log('Payload body contains HTML formatting:',
                        capturedPayload &&
                        capturedPayload.body &&
                        capturedPayload.body.includes('<strong>bold</strong>') &&
                        capturedPayload.body.includes('<em>italic</em>'));

                    console.log('Result:',
                        capturedPayload &&
                        capturedPayload.body &&
                        capturedPayload.body.includes('<strong>bold</strong>') &&
                        capturedPayload.body.includes('<em>italic</em>') ? 'PASS' : 'FAIL');

                    // Close the modal
                    vm.showEditModal = false;

                    console.log('\nAll tests completed.');
                }, 100);
            }, 100);
        }, 100);
    } else {
        console.log('No rejected emails available for testing');
    }
}

// Run the test
testRichTextEditor();
