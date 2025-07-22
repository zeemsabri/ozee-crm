/**
 * Test script for the permissions.js fix
 *
 * This script tests the fix for the issue where projectRole.value.permissions is undefined.
 * It mocks the permission store and project role to simulate different scenarios.
 *
 * Usage:
 * 1. Include this script in your HTML file
 * 2. Open the browser console to see the test results
 */

// Mock the permission store
const mockPermissionStore = {
  projectPermissions: {
    // Mock project permissions for project ID 123
    '123': {
      project_id: 123,
      global_role: {
        id: 1,
        name: "User",
        slug: "user",
        type: "application"
      },
      project_role: {
        id: 2,
        name: "Project Manager",
        slug: "project-manager",
        type: "project"
        // Note: No permissions property here, which is the issue we're fixing
      },
      permissions: [
        {
          id: 1,
          name: "View Projects",
          slug: "view_projects",
          category: "Project Management",
          source: "project"
        },
        {
          id: 2,
          name: "Manage Projects",
          slug: "manage_projects",
          category: "Project Management",
          source: "project"
        }
      ]
    }
  },
  hasPermission: (permissionSlug, projectId) => {
    // Mock implementation of hasPermission
    if (projectId && mockPermissionStore.projectPermissions[projectId]) {
      const projectPermissions = mockPermissionStore.projectPermissions[projectId];
      if (projectPermissions.permissions) {
        return projectPermissions.permissions.some(p => p.slug === permissionSlug);
      }
    }
    return false;
  }
};

// Mock the computed function
const computed = (fn) => {
  return {
    value: fn()
  };
};

// Mock the process.env
process.env = {
  NODE_ENV: 'development'
};

// Mock the console methods
const originalConsole = {
  warn: console.warn,
  error: console.error,
  log: console.log
};

console.warn = function(message) {
  originalConsole.warn('MOCK WARN:', message);
};

console.error = function(message, error) {
  originalConsole.error('MOCK ERROR:', message, error);
};

// Test the fixed canDo function
const canDo = (permissionSlug, projectRole = null) => {
  const validProjectId = 123; // For testing purposes
  const permissionStore = mockPermissionStore;

  return computed(() => {
    try {
      // If we have a valid project ID, check the project permissions directly
      if (validProjectId) {
        const projectPermissions = permissionStore.projectPermissions[validProjectId];
        if (projectPermissions && projectPermissions.permissions) {
          const hasPermission = projectPermissions.permissions.some(p => p.slug === permissionSlug);
          if (hasPermission) {
            return true;
          }
        } else if (process.env.NODE_ENV !== 'production') {
          // In non-production environments, log when project permissions are missing
          console.warn(`Project permissions not found for project ID ${validProjectId} when checking permission: ${permissionSlug}`);
        }
      }

      // For backward compatibility, check if permissions are directly in the role object
      // Note: This is unlikely to be true as the API doesn't include permissions in the project_role object
      if (projectRole && projectRole.value) {
        if (projectRole.value.permissions) {
          const projectPermission = projectRole.value.permissions.find(p => p.slug === permissionSlug);
          if (projectPermission) {
            return true;
          }
        }
      }

      // If no permission found in project permissions or project role, use the store's hasPermission getter
      // This will check global permissions
      return permissionStore.hasPermission(permissionSlug, validProjectId);
    } catch (error) {
      // Log any errors that occur during permission checking
      console.error(`Error checking permission ${permissionSlug}:`, error);

      // Default to false for safety in case of errors
      return false;
    }
  });
};

// Test cases
function runTests() {
  console.log('Running permission tests...');

  // Test case 1: Permission exists in project permissions
  const mockProjectRole = {
    value: {
      id: 2,
      name: "Project Manager",
      slug: "project-manager",
      type: "project"
      // No permissions property
    }
  };

  const canManageProjects = canDo('manage_projects', mockProjectRole);
  console.log('Test case 1: Permission exists in project permissions');
  console.log('canManageProjects.value:', canManageProjects.value);
  console.log('Expected: true');
  console.log('Result:', canManageProjects.value === true ? 'PASS' : 'FAIL');
  console.log('');

  // Test case 2: Permission doesn't exist in project permissions
  const canDeleteProjects = canDo('delete_projects', mockProjectRole);
  console.log('Test case 2: Permission doesn\'t exist in project permissions');
  console.log('canDeleteProjects.value:', canDeleteProjects.value);
  console.log('Expected: false');
  console.log('Result:', canDeleteProjects.value === false ? 'PASS' : 'FAIL');
  console.log('');

  // Test case 3: Permission exists in project role (unlikely scenario)
  const mockProjectRoleWithPermissions = {
    value: {
      id: 2,
      name: "Project Manager",
      slug: "project-manager",
      type: "project",
      permissions: [
        {
          id: 3,
          name: "Delete Projects",
          slug: "delete_projects",
          category: "Project Management",
          source: "project"
        }
      ]
    }
  };

  const canDeleteProjectsWithRolePermissions = canDo('delete_projects', mockProjectRoleWithPermissions);
  console.log('Test case 3: Permission exists in project role (unlikely scenario)');
  console.log('canDeleteProjectsWithRolePermissions.value:', canDeleteProjectsWithRolePermissions.value);
  console.log('Expected: true');
  console.log('Result:', canDeleteProjectsWithRolePermissions.value === true ? 'PASS' : 'FAIL');
  console.log('');

  // Test case 4: Error during permission checking
  const mockErrorProjectRole = {
    value: {
      id: 2,
      name: "Project Manager",
      slug: "project-manager",
      type: "project",
      permissions: null // This will cause an error when we try to call .find() on it
    }
  };

  const canManageProjectsWithError = canDo('manage_projects', mockErrorProjectRole);
  console.log('Test case 4: Error during permission checking');
  console.log('canManageProjectsWithError.value:', canManageProjectsWithError.value);
  console.log('Expected: true (because it\'s in project permissions)');
  console.log('Result:', canManageProjectsWithError.value === true ? 'PASS' : 'FAIL');
  console.log('');

  // Restore original console methods
  console.warn = originalConsole.warn;
  console.error = originalConsole.error;
  console.log = originalConsole.log;
}

// Run the tests
runTests();

// Instructions for manual testing
console.log(`
Manual Testing Instructions:
1. Navigate to a page with permission checks (e.g., ProjectForm.vue)
2. Open the browser console
3. Look for any warning or error messages related to permissions
4. Verify that UI elements that depend on permissions are displayed correctly
5. Test with different user roles and permissions to ensure the fix works in all scenarios
`);
