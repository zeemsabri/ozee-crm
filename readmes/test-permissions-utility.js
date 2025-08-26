// Test script for the permissions utility
// This script simulates the behavior of the permissions utility in different contexts

console.log("Testing permissions utility...");

// Mock user with global permissions
const mockUser = {
  id: 1,
  name: "Test User",
  role_data: {
    slug: "employee",
    name: "Employee"
  },
  global_permissions: [
    { id: 1, slug: "view_project_documents", name: "View Project Documents" },
    { id: 2, slug: "view_project_notes", name: "View Project Notes" }
  ]
};

// Mock super admin user
const mockSuperAdmin = {
  id: 2,
  name: "Super Admin",
  role_data: {
    slug: "super-admin",
    name: "Super Admin"
  },
  global_permissions: [
    { id: 1, slug: "view_project_documents", name: "View Project Documents" },
    { id: 2, slug: "view_project_notes", name: "View Project Notes" },
    { id: 3, slug: "manage_projects", name: "Manage Projects" },
    // ... many more permissions
  ]
};

// Mock project with the user having a project-specific role
const mockProject = {
  id: 1,
  name: "Test Project",
  users: [
    {
      id: 1,
      name: "Test User",
      pivot: {
        role_data: {
          id: 3,
          name: "Manager",
          slug: "manager",
          permissions: [
            { id: 3, slug: "manage_projects", name: "Manage Projects" },
            { id: 4, slug: "manage_project_users", name: "Manage Project Users" }
          ]
        }
      }
    }
  ]
};

// Mock the Vue reactive system
const reactive = (obj) => obj;
const ref = (value) => ({ value });
const computed = (fn) => ({ value: fn() });

// Mock the permissions utility
const useAuthUser = () => {
  return computed(() => mockUser);
};

const useProjectRole = (project) => {
  return computed(() => {
    if (!project.value || !project.value.users) return null;

    const userInProject = project.value.users.find(user => user.id === mockUser.id);
    if (!userInProject || !userInProject.pivot) return null;

    return userInProject.pivot.role_data || null;
  });
};

const hasPermission = (permissionSlug, projectRole = null) => {
  // First check project-specific permissions if available
  if (projectRole && projectRole.permissions) {
    const projectPermission = projectRole.permissions.find(p => p.slug === permissionSlug);
    if (projectPermission) return true;
  }

  // Fall back to global permissions if no project-specific permission found
  if (mockUser.global_permissions) {
    return mockUser.global_permissions.some(p => p.slug === permissionSlug);
  }

  return false;
};

const usePermissions = () => {
  const checkPermission = (permissionSlug, projectRole = null) => {
    return hasPermission(permissionSlug, projectRole);
  };

  const canDo = (permissionSlug, projectRole = null) => {
    return computed(() => {
      return checkPermission(permissionSlug, projectRole?.value || null);
    });
  };

  const canView = (resource, projectRole = null) => {
    return canDo(`view_${resource}`, projectRole);
  };

  const canManage = (resource, projectRole = null) => {
    return canDo(`manage_${resource}`, projectRole);
  };

  return {
    checkPermission,
    canDo,
    canView,
    canManage
  };
};

// Test cases
console.log("\nTest Case 1: Global permissions without project context");
console.log("-----------------------------------------------------");
const { canDo: globalCanDo, canView: globalCanView, canManage: globalCanManage } = usePermissions();

console.log("User has view_project_documents permission:", globalCanView("project_documents").value);
console.log("User has view_project_notes permission:", globalCanView("project_notes").value);
console.log("User has manage_projects permission:", globalCanManage("projects").value);
console.log("User has manage_project_users permission:", globalCanManage("project_users").value);

console.log("\nTest Case 2: Project-specific permissions with project context");
console.log("----------------------------------------------------------");
const project = ref(mockProject);
const userProjectRole = useProjectRole(project);
const { canDo: projectCanDo, canView: projectCanView, canManage: projectCanManage } = usePermissions();

console.log("User project role:", userProjectRole.value ? userProjectRole.value.name : "None");
console.log("User has view_project_documents permission:", projectCanView("project_documents", userProjectRole).value);
console.log("User has view_project_notes permission:", projectCanView("project_notes", userProjectRole).value);
console.log("User has manage_projects permission:", projectCanManage("projects", userProjectRole).value);
console.log("User has manage_project_users permission:", projectCanManage("project_users", userProjectRole).value);

console.log("\nTest Case 3: Super admin permissions");
console.log("----------------------------------");
// Temporarily replace mockUser with mockSuperAdmin
const originalMockUser = mockUser;
Object.assign(mockUser, mockSuperAdmin);

console.log("User role:", mockUser.role_data.name);
console.log("User has view_project_documents permission:", globalCanView("project_documents").value);
console.log("User has view_project_notes permission:", globalCanView("project_notes").value);
console.log("User has manage_projects permission:", globalCanManage("projects").value);
console.log("User has manage_project_users permission:", globalCanManage("project_users").value);

// Restore original mockUser
Object.assign(mockUser, originalMockUser);

console.log("\nTest Case 4: Permission override behavior");
console.log("--------------------------------------");
console.log("User global role:", mockUser.role_data.name);
console.log("User project role:", userProjectRole.value ? userProjectRole.value.name : "None");
console.log("User has manage_projects in global permissions:", mockUser.global_permissions.some(p => p.slug === "manage_projects"));
console.log("User has manage_projects in project permissions:", userProjectRole.value.permissions.some(p => p.slug === "manage_projects"));
console.log("Result of canManage('projects') with project context:", projectCanManage("projects", userProjectRole).value);
console.log("Result of canManage('projects') without project context:", globalCanManage("projects").value);

console.log("\nTest completed.");
