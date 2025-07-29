import { ref } from 'vue';
import axios from 'axios';
import { error } from '@/Utils/notification'; // Assuming a notification utility exists

/**
 * Composable for fetching project-related data from the API.
 * This centralizes data fetching logic, making components cleaner.
 */

// Define reactive refs to hold fetched data, shared across calls if needed
const dbClientRoles = ref([]);
const dbUserRoles = ref([]);
const clients = ref([]);
const users = ref([]);

/**
 * Fetches roles from the API based on type (client or project).
 * @param {string} type - The type of roles to fetch ('client' or 'project').
 * @returns {Array} - An array of mapped role objects ({ value: id, label: name }).
 */
const fetchRoles = async (type) => {
    try {
        const response = await axios.get(`/api/roles?type=${type}`);
        const roles = response.data;
        const mappedRoles = roles.map(role => ({ value: role.id, label: role.name }));

        if (type === 'client') {
            dbClientRoles.value = mappedRoles;
        } else if (type === 'project') {
            dbUserRoles.value = mappedRoles;
        }
        return mappedRoles;
    } catch (err) {
        console.error(`Error fetching ${type} roles:`, err);
        error(`Failed to load ${type} roles. Please refresh the page.`);
        return [];
    }
};

/**
 * Fetches clients from the API.
 * Prioritizes project-specific clients if a projectId is provided and permissions allow,
 * otherwise fetches all clients for global selection.
 * @param {boolean} canCreateClientsPermission - Whether the user has global 'create_clients' permission.
 * @param {number|null} projectId - The ID of the current project, if editing an existing one.
 * @returns {Array} - An array of client objects.
 */
const fetchClients = async (canCreateClientsPermission, projectId) => {
    try {
        let response;
        if (projectId && !canCreateClientsPermission) {
            // If project ID exists, try to fetch clients associated with THIS project
            response = await window.axios.get(`/api/projects/${projectId}/clients`);
            clients.value = response.data; // Assuming this endpoint returns the direct clients list
        } else if (canCreateClientsPermission) {
            // If creating a new project and user can create clients, fetch all clients
            response = await window.axios.get('/api/clients');
            clients.value = response.data.data || response.data; // Adjust based on API response structure
        } else {
            // Fallback for new projects without specific client access (empty list or limited access)
            clients.value = []; // Default to all clients if no specific endpoint
        }
        return clients.value;
    } catch (err) {
        console.error('Error fetching clients:', err);
        error('Failed to load clients. Please try again.');
        return [];
    }
};

/**
 * Fetches users from the API.
 * Prioritizes project-specific users if a projectId is provided and permissions allow,
 * otherwise fetches all users for global assignment.
 * @param {boolean} canCreateProjectsPermission - Whether the user has global 'create_projects' permission.
 * @param {number|null} projectId - The ID of the current project, if editing an existing one.
 * @returns {Array} - An array of user objects.
 */
const fetchUsers = async (canCreateProjectsPermission, projectId) => {
    try {
        let response;
        if (projectId && !canCreateProjectsPermission) {
            // If project ID exists, try to fetch users associated with THIS project
            response = await window.axios.get(`/api/projects/${projectId}/users`);
            users.value = response.data; // Assuming this endpoint returns the direct users list
        } else if (canCreateProjectsPermission) {
            // If creating a new project and user can create projects, fetch all users
            response = await window.axios.get('/api/users');
            users.value = response.data; // Assuming this returns a direct array of users
        } else {
            // Fallback for new projects without specific user access (empty list or limited access)
            response = await window.axios.get('/api/users');
            users.value = response.data; // Default to all users if no specific endpoint
        }
        return users.value;
    } catch (err) {
        console.error('Error fetching users:', err);
        error('Failed to load users. Please try again.');
        return [];
    }
};

/**
 * Fetches data for a specific project section based on the active tab and user permissions.
 * @param {number} projectId - The ID of the project.
 * @param {string} tabName - The name of the active tab ('basic', 'client', 'services', 'documents', 'notes').
 * @param {object} permissions - An object containing permission flags for each section.
 * @returns {object|Array|null} - The fetched data for the section, or null if no data fetched.
 */
const fetchProjectSectionData = async (projectId, tabName, permissions) => {
    let url = '';
    let fetchedData = null;

    try {
        switch (tabName) {
            case 'basic':
                url = `/api/projects/${projectId}/sections/basic`;
                break;
            case 'client':
                // Check multiple permissions for clients and users tab
                if (permissions.canViewProjectClients || permissions.canManageProjectClients ||
                    permissions.canViewProjectUsers || permissions.canManageProjectUsers) {
                    url = `/api/projects/${projectId}/sections/clients-users`;
                }
                break;
            case 'services':
                if (permissions.canViewProjectServicesAndPayments || permissions.canManageProjectServicesAndPayments) {
                    url = `/api/projects/${projectId}/sections/services-payment`;
                }
                break;
            case 'documents':
                if (permissions.canViewProjectDocuments) {
                    url = `/api/projects/${projectId}/sections/documents`;
                }
                break;
            case 'notes':
                if (permissions.canViewProjectNotes || permissions.canAddProjectNotes) {
                    url = `/api/projects/${projectId}/sections/notes?type=private`;
                }
                break;
            case 'transactions':
                // Transactions data is usually fetched by the ProjectTransactions component itself,
                // so no explicit fetch is needed here for the main form's data state.
                return null;
            default:
                console.warn(`Attempted to fetch data for unknown tab: ${tabName}`);
                return null;
        }

        if (url) {
            const response = await window.axios.get(url);
            fetchedData = response.data;
        }
        return fetchedData;
    } catch (err) {
        // Log the error and notify the user using the notification utility
        console.error(`Error fetching data for tab '${tabName}':`, err);
        error(`Failed to load data for ${tabName} section. Please try again.`);
        throw err; // Re-throw to be caught by the calling component (e.g., ProjectForm.vue)
    }
};

export {
    fetchRoles,
    fetchClients,
    fetchUsers,
    fetchProjectSectionData,
};
