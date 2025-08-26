/**
 * Test script for ProjectForm permissions
 *
 * This script tests the fix for the issue where validProjectId is null when opening ProjectForm.
 * It simulates the ProjectForm component's use of permissions and tests with both null and valid project IDs.
 *
 * Usage:
 * 1. Include this script in your HTML file
 * 2. Open the browser console to see the test results
 */

// Mock Vue's ref and computed functions
const ref = (initialValue) => {
  const r = {
    value: initialValue,
    _isRef: true
  };
  return r;
};

const computed = (getter) => {
  const c = {
    value: getter(),
    _isComputed: true,
    _getter: getter,
    // Add a method to update the computed value
    update() {
      this.value = this._getter();
      return this.value;
    }
  };
  return c;
};

// Mock the permission store
const mockPermissionStore = {
  projectPermissions: {
    '123': {
      project_id: 123,
      permissions: [
        { id: 1, name: 'Manage Projects', slug: 'manage_projects', source: 'project' },
        { id: 2, name: 'Upload Project Documents', slug: 'upload_project_documents', source: 'project' }
      ]
    }
  },
  hasPermission: (permissionSlug, projectId) => {
    console.log(`[Mock] Checking global permission: ${permissionSlug}, projectId: ${projectId}`);
    if (projectId && mockPermissionStore.projectPermissions[projectId]) {
      const projectPermissions = mockPermissionStore.projectPermissions[projectId];
      if (projectPermissions.permissions) {
        return projectPermissions.permissions.some(p => p.slug === permissionSlug);
      }
    }
    return false;
  }
};

// Mock the usePermissionStore function
const usePermissionStore = () => mockPermissionStore;

// Import the permissions module
// Note: In a real test, you would import the module directly
// Here we're copying the relevant parts of the code
const usePermissions = (projectId = null) => {
  const permissionStore = usePermissionStore();

  // Check if projectId is a computed ref
  const isComputedRef = typeof projectId === 'object' && 'value' in projectId;

  // Create a computed ref for validProjectId that depends on projectId
  const validProjectId = isComputedRef
    ? computed(() => {
        const id = projectId.value;
        return id && !isNaN(Number(id)) ? id : null;
      })
    : projectId && !isNaN(Number(projectId)) ? projectId : null;

  // Debug function to log permission-related information in development mode
  const debugPermissions = (message, data = {}) => {
    console.debug(`[Permissions Debug] ${message}`, {
      projectId: isComputedRef ? projectId.value : projectId,
      validProjectId: isComputedRef ? validProjectId.value : validProjectId,
      ...data
    });
  };

  // Log initial state
  debugPermissions('Initializing permissions');

  /**
   * Create a computed property that checks if the user has a specific permission
   */
  const canDo = (permissionSlug, projectRole = null) => {
    return computed(() => {
      try {
        // Get the actual project ID value, handling both computed refs and primitive values
        const projectIdValue = isComputedRef ? validProjectId.value : validProjectId;

        // Log permission check
        debugPermissions(`Checking permission: ${permissionSlug}`, {
          projectIdValue,
          hasProjectRole: !!projectRole?.value,
          projectRoleName: projectRole?.value?.name || 'None'
        });

        // If we have a valid project ID, check the project permissions directly
        if (projectIdValue) {
          const projectPermissions = permissionStore.projectPermissions[projectIdValue];
          if (projectPermissions && projectPermissions.permissions) {
            const hasPermission = projectPermissions.permissions.some(p => p.slug === permissionSlug);
            if (hasPermission) {
              debugPermissions(`Permission ${permissionSlug} found in project permissions`, { result: true });
              return true;
            }
          }
        }

        // For backward compatibility, check if permissions are directly in the role object
        if (projectRole && projectRole.value) {
          if (projectRole.value.permissions) {
            const projectPermission = projectRole.value.permissions.find(p => p.slug === permissionSlug);
            if (projectPermission) {
              debugPermissions(`Permission ${permissionSlug} found in project role permissions`, { result: true });
              return true;
            }
          }
        }

        // If no permission found in project permissions or project role, use the store's hasPermission getter
        const hasGlobalPermission = permissionStore.hasPermission(permissionSlug, projectIdValue);
        debugPermissions(`Permission ${permissionSlug} check result from global permissions`, { result: hasGlobalPermission });
        return hasGlobalPermission;
      } catch (error) {
        // Log any errors that occur during permission checking
        debugPermissions(`Error checking permission ${permissionSlug}`, { error: error.message, stack: error.stack });
        console.error(`Error checking permission ${permissionSlug}:`, error);

        // Default to false for safety in case of errors
        return false;
      }
    });
  };

  return {
    canDo
  };
};

// Test cases
function runTests() {
  console.log('Running ProjectForm permissions tests...');

  // Test case 1: Initial state with null project ID
  console.log('\nTest case 1: Initial state with null project ID');
  const project = ref({});
  const projectId = computed(() => project.value?.id || null);
  const { canDo } = usePermissions(projectId);
  const canUploadProjectDocuments = canDo('upload_project_documents');

  console.log('Initial state:');
  console.log('- project.value:', project.value);
  console.log('- projectId.value:', projectId.value);
  console.log('- canUploadProjectDocuments.value:', canUploadProjectDocuments.value);

  // Test case 2: Update project with valid ID
  console.log('\nTest case 2: Update project with valid ID');
  project.value = { id: 123, name: 'Test Project' };
  projectId.update();
  canUploadProjectDocuments.update();

  console.log('After update:');
  console.log('- project.value:', project.value);
  console.log('- projectId.value:', projectId.value);
  console.log('- canUploadProjectDocuments.value:', canUploadProjectDocuments.value);

  // Test case 3: Update project with invalid ID
  console.log('\nTest case 3: Update project with invalid ID');
  project.value = { id: 'invalid', name: 'Invalid Project' };
  projectId.update();
  canUploadProjectDocuments.update();

  console.log('After update with invalid ID:');
  console.log('- project.value:', project.value);
  console.log('- projectId.value:', projectId.value);
  console.log('- canUploadProjectDocuments.value:', canUploadProjectDocuments.value);

  // Test case 4: Update project with valid ID again
  console.log('\nTest case 4: Update project with valid ID again');
  project.value = { id: 123, name: 'Test Project Again' };
  projectId.update();
  canUploadProjectDocuments.update();

  console.log('After update with valid ID again:');
  console.log('- project.value:', project.value);
  console.log('- projectId.value:', projectId.value);
  console.log('- canUploadProjectDocuments.value:', canUploadProjectDocuments.value);
}

// Run the tests
runTests();

// Instructions for manual testing
console.log(`
Manual Testing Instructions:
1. Open ProjectForm in the application
2. Open the browser console
3. Look for [Permissions Debug] messages
4. Verify that permissions work correctly when ProjectForm is first opened (with null project ID)
5. Verify that permissions update correctly when project ID becomes available
6. Check if the document upload section is visible when you have the 'upload_project_documents' permission
`);
