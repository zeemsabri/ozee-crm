import { defineStore } from 'pinia';
import { computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

/**
 * Permissions store using Pinia
 * This store manages application-wide and project-specific permissions
 */
export const usePermissionStore = defineStore('permissions', {
    state: () => ({
        // Global permissions
        globalPermissions: null,
        globalRole: null,
        loadingGlobal: false,
        globalError: null,

        // Project-specific permissions
        // Key is the project ID, value is the permissions data
        projectPermissions: {},
        loadingProject: {},
        projectErrors: {},
    }),

    getters: {
        /**
         * Get the authenticated user from Inertia shared props
         */
        authUser: () => {
            return usePage().props.auth.user;
        },

        /**
         * Check if the user has a specific permission
         * @param {string} permissionSlug - The permission slug to check
         * @param {number|null} projectId - The project ID (optional)
         * @returns {boolean} - Whether the user has the permission
         */
        hasPermission: (state) => (permissionSlug, projectId = null) => {
            // First check project-specific permissions if project ID is provided
            if (projectId && state.projectPermissions[projectId]) {
                const projectPermData = state.projectPermissions[projectId];

                // Check if permissions are in the project permissions store
                if (projectPermData && projectPermData.permissions) {
                    const hasPermission = projectPermData.permissions.some(p => p.slug === permissionSlug);
                    if (hasPermission) {
                        return true;
                    }
                }
            }

            // Check global permissions if no project-specific permission found
            if (state.globalPermissions && state.globalPermissions.permissions) {
                const hasPermission = state.globalPermissions.permissions.some(p => p.slug === permissionSlug);
                if (hasPermission) {
                    return true;
                }
            }

            // If user is a super admin, they have all permissions
            const user = usePage().props.auth.user;
            if (user && (
                (user.role_data && user.role_data.slug === 'super-admin') ||
                user.role === 'super_admin' ||
                user.role === 'super-admin'
            )) {
                return true;
            }

            return false;
        },

        /**
         * Check if the user can view a specific resource
         * @param {string} resource - The resource name (e.g., 'project_documents')
         * @param {number|null} projectId - The project ID (optional)
         * @returns {boolean} - Whether the user can view the resource
         */
        canView: (state, getters) => (resource, projectId = null) => {
            return getters.hasPermission(`view_${resource}`, projectId);
        },

        /**
         * Check if the user can manage a specific resource
         * @param {string} resource - The resource name (e.g., 'project_documents')
         * @param {number|null} projectId - The project ID (optional)
         * @returns {boolean} - Whether the user can manage the resource
         */
        canManage: (state, getters) => (resource, projectId = null) => {
            return getters.hasPermission(`manage_${resource}`, projectId);
        },

        /**
         * Check if the user can perform a specific action
         * @param {string} action - The action name (e.g., 'create_project')
         * @param {number|null} projectId - The project ID (optional)
         * @returns {boolean} - Whether the user can perform the action
         */
        canDo: (state, getters) => (action, projectId = null) => {
            return getters.hasPermission(action, projectId);
        },

        /**
         * Get the user's project-specific role for a given project
         * @param {number} projectId - The project ID
         * @returns {Object|null} - The user's project-specific role or null if not found
         */
        getProjectRole: (state) => (projectId) => {
            if (!projectId || !state.projectPermissions[projectId]) return null;

            return state.projectPermissions[projectId].project_role;
        }
    },

    actions: {
        /**
         * Fetch global permissions for the current user
         * This ensures we have the latest permissions even if they're not in the auth user object
         * Uses the project permissions endpoint with a special "global" identifier
         */
        async fetchGlobalPermissions() {
            if (this.globalPermissions) {
                return this.globalPermissions;
            }

            this.loadingGlobal = true;
            this.globalError = null;

            try {
                // Use the fetchProjectPermissions method with a special "global" identifier
                // This will use the project permissions endpoint which already includes global permissions
                const data = await this.fetchProjectPermissions('global');

                // Store the data in the globalPermissions state
                this.globalPermissions = data;
                this.globalRole = data.global_role;

                return data;
            } catch (error) {
                this.globalError = error;
                return null;
            } finally {
                this.loadingGlobal = false;
            }
        },

        /**
         * Fetch project-specific permissions for the current user
         * @param {number|string} projectId - The ID of the project or 'global' for global permissions
         * @returns {Object|null} - The permissions data or null if there was an error
         */
        async fetchProjectPermissions(projectId) {
            // Special case for 'global' identifier
            const isGlobalRequest = projectId === 'global';

            // For non-global requests, ensure projectId is a valid ID
            if (!isGlobalRequest && (!projectId || isNaN(Number(projectId)) || projectId === '[object Object]')) {
                return null;
            }

            // Convert to string for consistent key usage
            const projectIdStr = String(projectId);

            // If we already have permissions for this project and they're not being loaded, return them
            if (this.projectPermissions[projectIdStr] && !this.loadingProject[projectIdStr]) {
                return this.projectPermissions[projectIdStr];
            }

            // Set loading state for this project
            this.loadingProject = {
                ...this.loadingProject,
                [projectIdStr]: true
            };

            // Clear any previous errors
            this.projectErrors = {
                ...this.projectErrors,
                [projectIdStr]: null
            };

            try {
                // Use different endpoint based on whether this is a global request or project-specific
                const url = isGlobalRequest
                    ? '/api/user/permissions'  // Use the global permissions endpoint for 'global'
                    : `/api/projects/${projectIdStr}/permissions`;  // Use project-specific endpoint otherwise

                const response = await axios.get(url);

                // Store the permissions for this project
                this.projectPermissions = {
                    ...this.projectPermissions,
                    [projectIdStr]: response.data
                };

                return response.data;
            } catch (error) {
                // Store the error
                this.projectErrors = {
                    ...this.projectErrors,
                    [projectIdStr]: error
                };

                return null;
            } finally {
                // Clear loading state
                this.loadingProject = {
                    ...this.loadingProject,
                    [projectIdStr]: false
                };
            }
        },

        /**
         * Clear all permissions data
         * Useful when logging out or when permissions need to be refreshed
         */
        clearPermissions() {
            this.globalPermissions = null;
            this.globalRole = null;
            this.projectPermissions = {};
            this.loadingGlobal = false;
            this.loadingProject = {};
            this.globalError = null;
            this.projectErrors = {};
        }
    }
});

/**
 * Get the authenticated user from Inertia shared props
 */
export const useAuthUser = () => {
  return computed(() => usePage().props.auth.user);
};

/**
 * Fetch global permissions for the current user
 * This ensures we have the latest permissions even if they're not in the auth user object
 * Note: This function now uses fetchProjectPermissions internally to avoid redundant API calls
 */
export const fetchGlobalPermissions = async () => {
  const permissionStore = usePermissionStore();
  return await permissionStore.fetchGlobalPermissions();
};

/**
 * Fetch project-specific permissions for the current user
 * @param {number|string} projectId - The ID of the project or 'global' for global permissions
 * @returns {Object|null} - The permissions data or null if there was an error
 */
export const fetchProjectPermissions = async (projectId) => {
  const permissionStore = usePermissionStore();
  return await permissionStore.fetchProjectPermissions(projectId);
};

/**
 * Get global permissions for the current user
 * Returns an object with permissions data, loading state, and error state
 */
export const useGlobalPermissions = () => {
  const permissionStore = usePermissionStore();

  onMounted(() => {
    // Fetch permissions when component mounts if we don't have them yet
    if (!permissionStore.globalPermissions) {
      permissionStore.fetchGlobalPermissions();
    }
  });

  return {
    permissions: computed(() => permissionStore.globalPermissions),
    loading: computed(() => permissionStore.loadingGlobal),
    error: computed(() => permissionStore.globalError),
    refresh: fetchGlobalPermissions
  };
};

/**
 * Get project-specific permissions for the current user
 * @param {number|string} projectId - The ID of the project
 * @returns {Object} - Object containing permissions data, loading state, and error state
 */
export const useProjectPermissions = (projectId) => {
  const permissionStore = usePermissionStore();

  // Convert computed ref to primitive value if needed
  const getProjectId = () => {
    if (typeof projectId === 'function') {
      // Handle computed refs
      const value = projectId.value;
      return value && !isNaN(Number(value)) ? value : null;
    }
    return projectId && !isNaN(Number(projectId)) ? projectId : null;
  };

  onMounted(() => {
    // Fetch permissions when component mounts if we don't have them yet for this project
    const id = getProjectId();
    if (id && !permissionStore.projectPermissions[id]) {
      permissionStore.fetchProjectPermissions(id);
    }
  });

  return {
    permissions: computed(() => {
      const id = getProjectId();
      return id ? permissionStore.projectPermissions[id] || null : null;
    }),
    loading: computed(() => {
      const id = getProjectId();
      return id ? permissionStore.loadingProject[id] || false : false;
    }),
    error: computed(() => {
      const id = getProjectId();
      return id ? permissionStore.projectErrors[id] || null : null;
    }),
    refresh: () => {
      const id = getProjectId();
      return id ? fetchProjectPermissions(id) : Promise.resolve(null);
    }
  };
};

/**
 * Get the user's project-specific role for a given project
 * @param {Object} project - The project object
 * @returns {Object|null} - The user's project-specific role or null if not found
 */
export const useProjectRole = (project) => {
  const authUser = useAuthUser();
  console.log({'authUser': authUser.value});
  console.log(authUser.value);
  const permissionStore = usePermissionStore();

  return computed(() => {
    if (!authUser.value || !project.value) {
      return null;
    }

    // If the project has an ID, try to get the role from the store
    if (project.value.id) {
      const projectRole = permissionStore.getProjectRole(project.value.id);
      if (projectRole) {
        return projectRole;
      }
    }

    // Fall back to the old method if the store doesn't have the role
    if (!project.value.users) {
      return null;
    }

    const userInProject = project.value.users.find(user => user.id === authUser.value.id);
    if (!userInProject) return null;

    if (!userInProject.pivot) {
      return null;
    }

    // Check for role_data in the pivot
    if (userInProject.pivot.role_data) {
      return userInProject.pivot.role_data;
    }

    // If no role_data but we have a role_id, try to construct basic role data
    if (userInProject.pivot.role_id) {
      return {
        id: userInProject.pivot.role_id,
        name: userInProject.pivot.role || 'Unknown Role',
        // We don't have permissions here, but the hasPermission function will fall back to global permissions
      };
    }

    return null;
  });
};

/**
 * Check if the user has a specific permission
 * @param {string} permissionSlug - The permission slug to check
 * @param {Object|null} projectRole - The user's project-specific role (optional)
 * @param {number|string|null} projectId - The project ID (optional)
 * @returns {boolean} - Whether the user has the permission
 */
export const hasPermission = (permissionSlug, projectRole = null, projectId = null) => {
  const permissionStore = usePermissionStore();

  // If we have a project ID, use it directly
  if (projectId && !isNaN(Number(projectId))) {
    return permissionStore.hasPermission(permissionSlug, projectId);
  }

  // If we have a project role but no project ID, we need to handle it specially
  // This is for backward compatibility with existing code
  if (projectRole && projectRole.value) {
    // Check if permissions are directly in the role object
    if (projectRole.value.permissions) {
      const projectPermission = projectRole.value.permissions.find(p => p.slug === permissionSlug);
      if (projectPermission) {
        return true;
      }
    }
  }

  // Otherwise, check global permissions
  return permissionStore.hasPermission(permissionSlug);
};

/**
 * Create a composable function to check permissions in a component
 * @param {number|string|null} projectId - The ID of the project (optional)
 * @returns {Object} - Object containing permission checking functions
 */
export const usePermissions = (projectId = null) => {
  const permissionStore = usePermissionStore();

  // Check if projectId is a computed ref
  const isComputedRef = projectId && typeof projectId === 'object' && 'value' in projectId;

  // Create a computed ref for validProjectId that depends on projectId
  const validProjectId = isComputedRef
    ? computed(() => {
        const id = projectId.value;
        return id && !isNaN(Number(id)) ? id : null;
      })
    : projectId && !isNaN(Number(projectId)) ? projectId : null;

  // Debug function to log permission-related information in development mode
  const debugPermissions = (message, data = {}) => {
    if (process.env.NODE_ENV !== 'production') {
      console.debug(`[Permissions Debug] ${message}`, {
        projectId: isComputedRef ? projectId.value : projectId,
        validProjectId: isComputedRef ? validProjectId.value : validProjectId,
        ...data
      });
    }
  };

  // Log initial state
  debugPermissions('Initializing permissions');

  /**
   * Check if the user has a specific permission
   * @param {string} permissionSlug - The permission slug to check
   * @param {Object|null} projectRole - The user's project-specific role (optional)
   * @returns {boolean} - Whether the user has the permission
   */
  const checkPermission = (permissionSlug, projectRole = null) => {
    // Get the actual project ID value, handling both computed refs and primitive values
    const projectIdValue = isComputedRef ? validProjectId.value : validProjectId;
    return hasPermission(permissionSlug, projectRole, projectIdValue);
  };

  /**
   * Create a computed property that checks if the user has a specific permission
   * @param {string} permissionSlug - The permission slug to check
   * @param {Object} projectRole - The user's project-specific role (computed)
   * @returns {ComputedRef<boolean>} - Computed property that returns whether the user has the permission
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
            return false;
          } else if (process.env.NODE_ENV !== 'production') {
            // In non-production environments, log when project permissions are missing
            console.warn(`Project permissions not found for project ID ${projectIdValue} when checking permission: ${permissionSlug}`);
          }
        }

        // For backward compatibility, check if permissions are directly in the role object
        // Note: This is unlikely to be true as the API doesn't include permissions in the project_role object
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
        // This will check global permissions
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

  /**
   * Check if the user can view a specific resource
   * @param {string} resource - The resource name (e.g., 'project_documents')
   * @param {Object} projectRole - The user's project-specific role (computed)
   * @returns {ComputedRef<boolean>} - Computed property that returns whether the user can view the resource
   */
  const canView = (resource, projectRole = null) => {
    return canDo(`view_${resource}`, projectRole);
  };

  /**
   * Check if the user can manage a specific resource
   * @param {string} resource - The resource name (e.g., 'project_documents')
   * @param {Object} projectRole - The user's project-specific role (computed)
   * @returns {ComputedRef<boolean>} - Computed property that returns whether the user can manage the resource
   */
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

/**
 * Vue directive for permission-based rendering
 * Usage:
 * v-permission="'permission_slug'" - Renders element if user has the permission
 * v-permission:project="{ permission: 'permission_slug', projectId: 123 }" - Renders element if user has the project-specific permission
 * v-permission:not="'permission_slug'" - Renders element if user does NOT have the permission
 * v-permission:not:project="{ permission: 'permission_slug', projectId: 123 }" - Renders element if user does NOT have the project-specific permission
 */
export const vPermission = {
    /**
     * Called before the element is inserted into the document
     * @param {HTMLElement} el - The element the directive is bound to
     * @param {Object} binding - Directive binding object
     * @param {Object} vnode - Virtual node
     */
    beforeMount(el, binding, vnode) {
        const permissionStore = usePermissionStore();

        // Check if the directive is used with the 'not' modifier
        const isNegated = binding.modifiers.not;

        // Check if the directive is used with the 'project' modifier
        const isProjectSpecific = binding.modifiers.project;

        let hasPermission = false;

        if (isProjectSpecific) {
            // For project-specific permissions, binding.value should be an object with permission and projectId
            if (typeof binding.value === 'object' && binding.value.permission && binding.value.projectId) {
                hasPermission = permissionStore.hasPermission(binding.value.permission, binding.value.projectId);
            } else {
                hasPermission = false;
            }
        } else {
            // For global permissions, binding.value should be a string
            if (typeof binding.value === 'string') {
                hasPermission = permissionStore.hasPermission(binding.value);
            } else {
                hasPermission = false;
            }
        }

        // If the directive is negated, invert the result
        if (isNegated) {
            hasPermission = !hasPermission;
        }

        // If the user doesn't have the permission, hide the element
        if (!hasPermission) {
            // Store the original display value
            el._originalDisplay = el.style.display;

            // Hide the element
            el.style.display = 'none';
        }
    },

    /**
     * Called when the bound element's parent component is updated
     * @param {HTMLElement} el - The element the directive is bound to
     * @param {Object} binding - Directive binding object
     * @param {Object} vnode - Virtual node
     * @param {Object} prevVnode - Previous virtual node
     */
    updated(el, binding, vnode, prevVnode) {
        const permissionStore = usePermissionStore();

        // Check if the directive is used with the 'not' modifier
        const isNegated = binding.modifiers.not;

        // Check if the directive is used with the 'project' modifier
        const isProjectSpecific = binding.modifiers.project;

        let hasPermission = false;

        if (isProjectSpecific) {
            // For project-specific permissions, binding.value should be an object with permission and projectId
            if (typeof binding.value === 'object' && binding.value.permission && binding.value.projectId) {
                hasPermission = permissionStore.hasPermission(binding.value.permission, binding.value.projectId);
            } else {
                hasPermission = false;
            }
        } else {
            // For global permissions, binding.value should be a string
            if (typeof binding.value === 'string') {
                hasPermission = permissionStore.hasPermission(binding.value);
            } else {
                hasPermission = false;
            }
        }

        // If the directive is negated, invert the result
        if (isNegated) {
            hasPermission = !hasPermission;
        }

        // If the user doesn't have the permission, hide the element
        if (!hasPermission) {
            // Store the original display value if not already stored
            if (el._originalDisplay === undefined) {
                el._originalDisplay = el.style.display;
            }

            // Hide the element
            el.style.display = 'none';
        } else {
            // If the user has the permission, restore the original display value
            if (el._originalDisplay !== undefined) {
                el.style.display = el._originalDisplay === 'none' ? '' : el._originalDisplay;
            }
        }
    },

    /**
     * Called when the directive is unbound from the element
     * @param {HTMLElement} el - The element the directive is bound to
     */
    unmounted(el) {
        // Restore the original display value when the directive is unbound
        if (el._originalDisplay !== undefined) {
            el.style.display = el._originalDisplay;
            delete el._originalDisplay;
        }
    }
};

/**
 * Register the permission directive with Vue
 * @param {Object} app - Vue app instance
 */
export function registerPermissionDirective(app) {
    app.directive('permission', vPermission);
}
