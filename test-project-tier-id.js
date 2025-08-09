// Test script to verify project_tier_id is included in the form submission
console.log('Testing project_tier_id inclusion in form submission');

// DETAILED TEST INSTRUCTIONS:

// 1. Open a project edit page in your browser
//    - Navigate to a project's edit page where ProjectEditBasicInfo.vue is used

// 2. Open browser developer tools
//    - Press F12 or right-click and select "Inspect"
//    - Go to the Console tab

// 3. Select a project tier from the dropdown
//    - This should update the localProjectForm.project_tier_id value

// 4. Click "Update Basic Information" button
//    - This will trigger the submitBasicInfo function

// 5. Check the browser console for these specific log messages:
//    - "Added project_tier_id: [value] type: string" - Confirms explicit addition with type conversion
//    - "Form data contents:" followed by entries including "project_tier_id: [value]"

// 6. Check the Network tab:
//    - Find the POST request to "/api/projects/{id}/sections/basic"
//    - Inspect the Form Data in the request payload
//    - Verify "project_tier_id" is included with the correct value

// TROUBLESHOOTING:
// If project_tier_id is still not in the payload:
// - Check if the SelectDropdown is emitting the correct value (add a watch on localProjectForm.project_tier_id)
// - Verify the project_tier_id value is not being reset before submission
// - Check if there are any server-side validation issues rejecting the field
