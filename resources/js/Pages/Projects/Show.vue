<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ProjectForm from '@/Components/ProjectForm.vue';
import Modal from '@/Components/Modal.vue';
import NotesModal from '@/Components/NotesModal.vue';
import { useAuthUser, useProjectRole, usePermissions, useGlobalPermissions, fetchGlobalPermissions, useProjectPermissions, fetchProjectPermissions } from '@/Directives/permissions';

// Use the permission utilities
const authUser = useAuthUser();

// Get project ID from Inertia page props
const projectId = usePage().props.id;

// Use global permissions
const { permissions: globalPermissions, loading: permissionsLoading, error: permissionsError } = useGlobalPermissions();

// Use project-specific permissions
const { permissions: projectPermissions, loading: projectPermissionsLoading, error: projectPermissionsError } = useProjectPermissions(projectId);

// Project data
const project = ref({});
const clients = ref([]);
const contractors = ref([]);
const loading = ref(true);
const generalError = ref('');

// Modal state
const showEditModal = ref(false);
const showAddNoteModal = ref(false);
const showReplyModal = ref(false);
const selectedNote = ref(null);
const replyContent = ref('');
const replyError = ref('');
const noteReplies = ref([]);
const loadingReplies = ref(false);

// Function to open the add note modal
const openAddNoteModal = () => {
    showAddNoteModal.value = true;
};

// Function to open the reply modal and fetch replies
const replyToNote = async (note) => {
    selectedNote.value = note;
    replyContent.value = '';
    replyError.value = '';
    noteReplies.value = [];
    loadingReplies.value = true;
    showReplyModal.value = true;

    try {
        // Fetch replies for this note
        const response = await window.axios.get(
            `/api/projects/${project.value.id}/notes/${note.id}/replies`
        );

        if (response.data.success) {
            noteReplies.value = response.data.replies;
        } else {
            console.error('Failed to fetch replies:', response.data.message);
        }
    } catch (error) {
        console.error('Error fetching replies:', error);
    } finally {
        loadingReplies.value = false;
    }
};

// Function to submit a reply to a note
const submitReply = async () => {
    if (!selectedNote.value || !replyContent.value.trim()) {
        replyError.value = 'Reply content is required';
        return;
    }

    replyError.value = '';

    try {
        const response = await window.axios.post(
            `/api/projects/${project.value.id}/notes/${selectedNote.value.id}/reply`,
            { content: replyContent.value }
        );

        if (response.data.success) {
            // Clear the reply content
            replyContent.value = '';

            // Fetch the updated replies for this note
            try {
                loadingReplies.value = true;
                const repliesResponse = await window.axios.get(
                    `/api/projects/${project.value.id}/notes/${selectedNote.value.id}/replies`
                );

                if (repliesResponse.data.success) {
                    noteReplies.value = repliesResponse.data.replies;
                }
            } catch (repliesError) {
                console.error('Error fetching updated replies:', repliesError);
            } finally {
                loadingReplies.value = false;
            }

            // Refresh project data to update the reply count
            await fetchProjectData();
        } else {
            replyError.value = response.data.message || 'Failed to send reply';
        }
    } catch (error) {
        console.error('Error sending reply:', error);
        replyError.value = error.response?.data?.message || 'An error occurred while sending the reply';
    }
};

// Options (reused from Index.vue)
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'on_hold', label: 'On Hold' },
    { value: 'archived', label: 'Archived' },
];
const departmentOptions = [
    { value: 'Website Designing', label: 'Website Designing' },
    { value: 'SEO', label: 'SEO' },
    { value: 'Social Media', label: 'Social Media' },
    { value: 'Content Writing', label: 'Content Writing' },
    { value: 'Graphic Design', label: 'Graphic Design' },
];
const sourceOptions = [
    { value: 'UpWork', label: 'UpWork' },
    { value: 'Direct', label: 'Direct Client' },
    { value: 'Wix Marketplace', label: 'Wix Marketplace' },
    { value: 'Referral', label: 'Referral' },
];
const clientRoleOptions = [
    { value: 'Primary', label: 'Primary' },
    { value: 'Accountant', label: 'Accountant' },
    { value: 'Other', label: 'Other' },
];
const userRoleOptions = [
    { value: 'Manager', label: 'Manager' },
    { value: 'Developer', label: 'Developer' },
    { value: 'QA', label: 'QA' },
    { value: 'Other', label: 'Other' },
];
const paymentTypeOptions = [
    { value: 'one_off', label: 'One-Off' },
    { value: 'monthly', label: 'Monthly' },
];

// Get the user's project-specific role
const userProjectRole = useProjectRole(project);

// Check if user has a specific project role
const hasProjectRole = computed(() => {
    return !!userProjectRole.value;
});

// Check if user is a project manager in this specific project
const isProjectManager = computed(() => {
    if (!userProjectRole.value) return false;

    // Check if the project-specific role is a manager role
    const roleName = userProjectRole.value.name;
    const roleSlug = userProjectRole.value.slug;

    return roleName === 'Manager' ||
           roleName === 'Project Manager' ||
           roleSlug === 'manager' ||
           roleSlug === 'project-manager';
});

// Set up permission checking functions with project ID
const { canDo, canView, canManage } = usePermissions(projectId);

// Legacy role-based checks (kept for backward compatibility)
const isSuperAdmin = computed(() => {
    if (!authUser.value) return false;
    return (authUser.value.role_data && authUser.value.role_data.slug === 'super-admin') ||
           authUser.value.role === 'super_admin' ||
           authUser.value.role === 'super-admin';
});

const isManager = computed(() => {
    if (!authUser.value) return false;
    // Check application-wide role first
    const hasManagerRole = (authUser.value.role_data && authUser.value.role_data.slug === 'manager') ||
           authUser.value.role === 'manager' ||
           authUser.value.role === 'manager-role' ||
           authUser.value.role === 'manager_role';

    // If user is not a manager application-wide, check if they're a project manager for this project
    return hasManagerRole || isProjectManager.value;
});

const isEmployee = computed(() => {
    if (!authUser.value) return false;
    // If user has a project-specific role, don't consider them an employee for this project
    if (hasProjectRole.value && !isProjectManager.value) return false;

    return (authUser.value.role_data && authUser.value.role_data.slug === 'employee') ||
           authUser.value.role === 'employee' ||
           authUser.value.role === 'employee-role';
});

const isContractor = computed(() => {
    if (!authUser.value) return false;
    // Only consider application-wide role if user doesn't have a project-specific role
    if (hasProjectRole.value) return false;

    return (authUser.value.role_data && authUser.value.role_data.slug === 'contractor') ||
           authUser.value.role === 'contractor' ||
           authUser.value.role === 'contractor-role';
});

// Permission-based checks using the permission utilities
const canManageProjects = computed(() => {
    console.log('userProjectRole in canManageProjects:', userProjectRole.value);

    // Check if we have project-specific permissions
    const hasProjectPermission = userProjectRole.value &&
                               userProjectRole.value.permissions &&
                               userProjectRole.value.permissions.some(p => p.slug === 'manage_projects');

    console.log('Has project-specific manage_projects permission:', hasProjectPermission);

    // Check global permissions
    const hasGlobalPermission = authUser.value &&
                              authUser.value.global_permissions &&
                              authUser.value.global_permissions.some(p => p.slug === 'manage_projects');

    console.log('Has global manage_projects permission:', hasGlobalPermission);

    // Check role permissions
    const hasRolePermission = authUser.value &&
                            authUser.value.role &&
                            authUser.value.role.permissions &&
                            authUser.value.role.permissions.some(p => p.slug === 'manage_projects');

    console.log('Has role manage_projects permission:', hasRolePermission);

    // Use the permission utility
    const canDoResult = canDo('manage_projects', userProjectRole).value;
    console.log('canDo result for manage_projects:', canDoResult);

    const result = canDoResult || isSuperAdmin.value;
    console.log('Final canManageProjects result:', result);

    return result;
});

const canViewProjectFinancial = computed(() => {
    return canView('project_financial', userProjectRole).value || isSuperAdmin.value;
});

const canViewProjectTransactions = computed(() => {
    return canView('project_transactions', userProjectRole).value || isSuperAdmin.value;
});

const canViewClientContacts = computed(() => {
    return canView('client_contacts', userProjectRole).value || isSuperAdmin.value;
});

const canViewClientFinancial = computed(() => {
    return canView('client_financial', userProjectRole).value || isSuperAdmin.value;
});

const canViewUsers = computed(() => {
    return canView('users', userProjectRole).value || isSuperAdmin.value;
});

// Email permissions
const canViewEmails = computed(() => {
    return canView('emails', userProjectRole).value || false; // Default to false for backward compatibility
});

const canComposeEmails = computed(() => {
    return canDo('compose_emails', userProjectRole).value || false; // Default to false for backward compatibility
});

// Fetch project data
const fetchProjectData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const projectId = usePage().props.id; // Get project ID from Inertia props
        console.log('Fetching project data for ID:', projectId);

        const response = await window.axios.get(`/api/projects/${projectId}`);
        console.log('Project data received:', response.data);

        // Check if users are included and have permissions
        if (response.data.users) {
            console.log('Project users:', response.data.users.length);

            // Check the first user to see the structure
            if (response.data.users.length > 0) {
                const firstUser = response.data.users[0];
                console.log('First user structure:', {
                    id: firstUser.id,
                    name: firstUser.name,
                    hasPivot: !!firstUser.pivot,
                    pivotData: firstUser.pivot ? {
                        hasRoleId: !!firstUser.pivot.role_id,
                        hasRoleData: !!firstUser.pivot.role_data,
                        roleDataStructure: firstUser.pivot.role_data
                    } : 'No pivot data'
                });

                // Check if current user is in the project
                const currentUser = response.data.users.find(u => u.id === authUser.value.id);
                console.log('Current user in project:', currentUser ? {
                    name: currentUser.name,
                    hasPivot: !!currentUser.pivot,
                    pivotData: currentUser.pivot ? {
                        hasRoleId: !!currentUser.pivot.role_id,
                        hasRoleData: !!currentUser.pivot.role_data,
                        roleDataStructure: currentUser.pivot.role_data
                    } : 'No pivot data'
                } : 'Not found');
            }
        } else {
            console.log('No users array in project data');
        }

        project.value = response.data;
        console.log('Project value after assignment:', project.value);

        // Log the userProjectRole after project is set
        console.log('userProjectRole after project set:', userProjectRole.value);

        // Fetch additional data if user can manage projects
        if (canManageProjects.value) {
            console.log('User can manage projects, fetching additional data');
            const clientsResponse = await window.axios.get('/api/clients');
            clients.value = clientsResponse.data;
            const usersResponse = await window.axios.get('/api/users');
            contractors.value = usersResponse.data.filter(user => user.role === 'contractor');
        } else {
            console.log('User cannot manage projects, skipping additional data fetch');
        }
    } catch (error) {
        generalError.value = 'Failed to load project data.';
        console.error('Error fetching project data:', error);
        if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            generalError.value = 'You are not authorized to view this project or your session expired. Please log in.';
        }
    } finally {
        loading.value = false;
    }
};

// Edit project
const openEditModal = () => {
    showEditModal.value = true;
};

const handleProjectSubmit = (updatedProject) => {
    // First update the project with the returned data
    project.value = updatedProject;
    // Close the modal
    showEditModal.value = false;
    // Show success message
    alert('Project updated successfully!');
    // Fetch the complete project data to ensure we have all necessary information
    fetchProjectData();
};

// Tasks data
const tasks = ref([]);
const loadingTasks = ref(false);
const tasksError = ref('');
const showTaskModal = ref(false);
const selectedTask = ref(null);
const taskFormData = ref({
    name: '',
    description: '',
    assigned_to_user_id: null,
    due_date: null,
    status: 'To Do',
    task_type_id: null,
    milestone_id: null,
    tags: []
});
const taskFormErrors = ref({});
const taskTypes = ref([]);
const milestones = ref([]);
const loadingTaskTypes = ref(false);
const loadingMilestones = ref(false);

// Task filters
const taskFilters = ref({
    status: '',
    assigned_to_user_id: '',
    milestone_id: '',
    due_date_range: ''
});

// Due date filter options
const dueDateOptions = [
    { value: '', label: 'All Dates' },
    { value: 'today', label: 'Due Today' },
    { value: 'this_week', label: 'Due This Week' },
    { value: 'next_week', label: 'Due Next Week' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'no_date', label: 'No Due Date' }
];

// Computed property for sorted milestones
const sortedMilestones = computed(() => {
    if (!milestones.value || !milestones.value.length) return [];

    // Sort milestones by completion_date
    return [...milestones.value].sort((a, b) => {
        // Handle null completion dates (put them at the end)
        if (!a.completion_date) return 1;
        if (!b.completion_date) return -1;

        // Sort by completion_date (ascending)
        return new Date(a.completion_date) - new Date(b.completion_date);
    });
});

// Computed property for filtered tasks
const filteredTasks = computed(() => {
    if (!tasks.value.length) return [];

    return tasks.value.filter(task => {
        // Status filter
        if (taskFilters.value.status && task.status !== taskFilters.value.status) {
            return false;
        }

        // Assigned user filter
        if (taskFilters.value.assigned_to_user_id) {
            const assignedUserId = parseInt(taskFilters.value.assigned_to_user_id);
            // Handle the special case for unassigned tasks
            if (assignedUserId === -1) {
                if (task.assigned_to !== 'Unassigned') {
                    return false;
                }
            } else {
                // The task.assigned_to_id might not be directly available in the API response
                // We need to check if the assigned_to matches the user name or if we have the ID
                const assignedToUser = project.value.users?.find(u => u.id === assignedUserId);
                if (assignedToUser && task.assigned_to !== assignedToUser.name) {
                    return false;
                }
            }
        }

        // Milestone filter
        if (taskFilters.value.milestone_id) {
            const milestoneId = parseInt(taskFilters.value.milestone_id);
            // Handle the special case for tasks without milestones
            if (milestoneId === -1) {
                if (task.milestone) {
                    return false;
                }
            } else {
                // Check if the milestone name matches or if we have the ID
                const milestone = milestones.value.find(m => m.id === milestoneId);
                if (milestone && task.milestone !== milestone.name && task.milestone_id !== milestoneId) {
                    return false;
                }
            }
        }

        // Due date filter
        if (taskFilters.value.due_date_range) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const taskDueDate = task.due_date ? new Date(task.due_date) : null;

            switch (taskFilters.value.due_date_range) {
                case 'today':
                    if (!taskDueDate || taskDueDate.toDateString() !== today.toDateString()) {
                        return false;
                    }
                    break;

                case 'this_week': {
                    if (!taskDueDate) return false;

                    const endOfWeek = new Date(today);
                    endOfWeek.setDate(today.getDate() + (6 - today.getDay())); // Sunday is 0, Saturday is 6

                    if (taskDueDate < today || taskDueDate > endOfWeek) {
                        return false;
                    }
                    break;
                }

                case 'next_week': {
                    if (!taskDueDate) return false;

                    const startOfNextWeek = new Date(today);
                    startOfNextWeek.setDate(today.getDate() + (7 - today.getDay()));

                    const endOfNextWeek = new Date(startOfNextWeek);
                    endOfNextWeek.setDate(startOfNextWeek.getDate() + 6);

                    if (taskDueDate < startOfNextWeek || taskDueDate > endOfNextWeek) {
                        return false;
                    }
                    break;
                }

                case 'overdue': {
                    if (!taskDueDate || taskDueDate >= today) {
                        return false;
                    }
                    break;
                }

                case 'no_date': {
                    if (taskDueDate) {
                        return false;
                    }
                    break;
                }
            }
        }

        return true;
    });
});

// Reset all filters
const resetFilters = () => {
    taskFilters.value = {
        status: '',
        assigned_to_user_id: '',
        milestone_id: '',
        due_date_range: ''
    };
};

// Task note data
const showTaskNoteModal = ref(false);
const taskForNote = ref(null);
const taskNoteContent = ref('');
const taskNoteError = ref('');
const addingTaskNote = ref(false);

// Milestone creation modal state
const showMilestoneModal = ref(false);
const milestoneFormData = ref({
    name: '',
    description: '',
    completion_date: null,
    status: 'Not Started',
    project_id: usePage().props.id
});
const milestoneFormErrors = ref({});
const creatingMilestone = ref(false);

// Fetch tasks for the project
const fetchProjectTasks = async () => {
    loadingTasks.value = true;
    tasksError.value = '';
    try {
        const projectId = usePage().props.id;
        const response = await window.axios.get(`/api/projects/${projectId}/tasks`);
        tasks.value = response.data;
        console.log('Tasks fetched:', tasks.value);
    } catch (error) {
        tasksError.value = 'Failed to load tasks data.';
        console.error('Error fetching project tasks:', error);

        // If there's a specific message from the server, use it
        if (error.response && error.response.data && error.response.data.message) {
            tasksError.value = error.response.data.message;
        }
    } finally {
        loadingTasks.value = false;
    }
};

// Fetch task types
const fetchTaskTypes = async () => {
    loadingTaskTypes.value = true;
    try {
        const response = await window.axios.get('/api/task-types');
        taskTypes.value = response.data;
    } catch (error) {
        console.error('Error fetching task types:', error);
    } finally {
        loadingTaskTypes.value = false;
    }
};

// Fetch milestones for the project
const fetchMilestones = async () => {
    loadingMilestones.value = true;
    try {
        const projectId = usePage().props.id;
        const response = await window.axios.get(`/api/projects/${projectId}/milestones`);
        milestones.value = response.data;
    } catch (error) {
        console.error('Error fetching milestones:', error);
    } finally {
        loadingMilestones.value = false;
    }
};

// Open modal for adding a new milestone
const openAddMilestoneModal = () => {
    // Reset form data
    milestoneFormData.value = {
        name: '',
        description: '',
        completion_date: null,
        status: 'Not Started',
        project_id: usePage().props.id
    };
    milestoneFormErrors.value = {};
    showMilestoneModal.value = true;
};

// Submit the milestone form
const submitMilestoneForm = async () => {
    milestoneFormErrors.value = {};
    creatingMilestone.value = true;

    try {
        const response = await window.axios.post('/api/milestones', milestoneFormData.value);

        // Add the new milestone to the milestones array
        milestones.value.push(response.data);

        // Select the new milestone in the task form
        taskFormData.value.milestone_id = response.data.id;

        // Close the milestone modal
        showMilestoneModal.value = false;

        // Show success message
        alert('Milestone created successfully!');
    } catch (error) {
        console.error('Error creating milestone:', error);

        if (error.response && error.response.data && error.response.data.errors) {
            milestoneFormErrors.value = error.response.data.errors;
        } else {
            alert('Failed to create milestone. Please try again.');
        }
    } finally {
        creatingMilestone.value = false;
    }
};

// Open modal for adding a new task
const openAddTaskModal = async () => {
    selectedTask.value = null;
    taskFormData.value = {
        name: '',
        description: '',
        assigned_to_user_id: null,
        due_date: null,
        status: 'To Do',
        task_type_id: null,
        milestone_id: null,
        tags: []
    };
    taskFormErrors.value = {};

    // Fetch task types and milestones if needed
    if (taskTypes.value.length === 0) {
        await fetchTaskTypes();
    }
    if (milestones.value.length === 0) {
        await fetchMilestones();
    }

    showTaskModal.value = true;
};

// Open modal for editing an existing task
const editTask = async (task) => {
    selectedTask.value = task;

    // Fetch task types and milestones if needed
    if (taskTypes.value.length === 0) {
        await fetchTaskTypes();
    }
    if (milestones.value.length === 0) {
        await fetchMilestones();
    }

    // Find milestone ID based on milestone name
    let milestoneId = null;
    if (task.milestone) {
        const foundMilestone = milestones.value.find(m => m.name === task.milestone);
        if (foundMilestone) {
            milestoneId = foundMilestone.id;
        }
    }

    // Find task type ID based on task type name
    let taskTypeId = null;
    if (task.task_type) {
        const foundTaskType = taskTypes.value.find(t => t.name === task.task_type);
        if (foundTaskType) {
            taskTypeId = foundTaskType.id;
        }
    }

    // Find user ID based on assigned_to name
    let assignedToUserId = null;
    if (task.assigned_to && task.assigned_to !== 'Unassigned') {
        const foundUser = project.value.users?.find(u => u.name === task.assigned_to);
        if (foundUser) {
            assignedToUserId = foundUser.id;
        }
    }

    taskFormData.value = {
        name: task.title,
        description: task.description || '',
        assigned_to_user_id: assignedToUserId || task.assigned_to_id || null,
        due_date: task.due_date || null,
        status: task.status,
        task_type_id: taskTypeId || task.task_type_id || null,
        milestone_id: milestoneId,
        tags: task.tags || []
    };
    taskFormErrors.value = {};

    showTaskModal.value = true;
};

// Mark a task as completed
const markTaskAsCompleted = async (task) => {
    try {
        await window.axios.post(`/api/tasks/${task.id}/complete`);
        // Refresh tasks after marking as completed
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error marking task as completed:', error);
        alert('Failed to mark task as completed. Please try again.');
    }
};

// Start a task (change status to In Progress)
const startTask = async (task) => {
    try {
        await window.axios.post(`/api/tasks/${task.id}/start`);
        // Refresh tasks after starting
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error starting task:', error);
        alert('Failed to start task. Please try again.');
    }
};

// Open modal for adding a note to a task
const openAddTaskNoteModal = (task) => {
    taskForNote.value = task;
    taskNoteContent.value = '';
    taskNoteError.value = '';
    showTaskNoteModal.value = true;
};

// Add a note to a task
const addTaskNote = async () => {
    if (!taskNoteContent.value.trim()) {
        taskNoteError.value = 'Note content is required';
        return;
    }

    taskNoteError.value = '';
    addingTaskNote.value = true;

    try {
        await window.axios.post(`/api/tasks/${taskForNote.value.id}/notes`, {
            note: taskNoteContent.value
        });

        // Close the modal and reset form
        showTaskNoteModal.value = false;
        taskNoteContent.value = '';
        taskForNote.value = null;

        // Refresh tasks to show any status changes
        await fetchProjectTasks();

        // Show success message
        alert('Note added successfully!');
    } catch (error) {
        console.error('Error adding note to task:', error);

        if (error.response && error.response.data && error.response.data.message) {
            taskNoteError.value = error.response.data.message;
        } else {
            taskNoteError.value = 'Failed to add note. Please try again.';
        }
    } finally {
        addingTaskNote.value = false;
    }
};

// Delete a task
const deleteTask = async (task) => {
    if (!confirm(`Are you sure you want to delete the task "${task.title}"?`)) {
        return;
    }

    try {
        await window.axios.delete(`/api/tasks/${task.id}`);
        // Refresh tasks after deletion
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error deleting task:', error);
        alert('Failed to delete task. Please try again.');
    }
};

// Submit the task form (create or update)
const submitTaskForm = async () => {
    taskFormErrors.value = {};

    try {
        let response;

        if (selectedTask.value) {
            // Update existing task
            response = await window.axios.put(`/api/tasks/${selectedTask.value.id}`, taskFormData.value);
        } else {
            // Create new task
            response = await window.axios.post('/api/tasks', {
                ...taskFormData.value,
                milestone_id: taskFormData.value.milestone_id || null
            });
        }

        // Close the modal and refresh tasks
        showTaskModal.value = false;
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error submitting task form:', error);

        if (error.response && error.response.data && error.response.data.errors) {
            taskFormErrors.value = error.response.data.errors;
        } else {
            alert('Failed to save task. Please try again.');
        }
    }
};

// Email data
const emails = ref([]);
const loadingEmails = ref(false);
const emailError = ref('');
const selectedEmail = ref(null);
const showEmailModal = ref(false);

// Fetch emails for the project
const fetchProjectEmails = async () => {
    loadingEmails.value = true;
    emailError.value = '';
    try {
        const projectId = usePage().props.id;
        const response = await window.axios.get(`/api/projects/${projectId}/emails`);
        emails.value = response.data;
    } catch (error) {
        emailError.value = 'Failed to load email data.';
        console.error('Error fetching project emails:', error);
    } finally {
        loadingEmails.value = false;
    }
};

// View email details
const viewEmail = (email) => {
    selectedEmail.value = email;
    showEmailModal.value = true;
};

onMounted(async () => {
    console.log('Component mounted, fetching data...');

    // Fetch project-specific permissions
    // This will also include global permissions, so we don't need a separate call
    try {
        const projectId = usePage().props.id;
        const permissions = await fetchProjectPermissions(projectId);
        console.log('Project permissions fetched (includes global):', permissions);
    } catch (error) {
        console.error(`Error fetching permissions for project ${projectId}:`, error);
    }

    // Then fetch project data
    await fetchProjectData();
    await fetchProjectEmails();
    await fetchProjectTasks();

    // Fetch task types and milestones for task management
    try {
        await fetchTaskTypes();
        await fetchMilestones();
        console.log('Task types and milestones fetched successfully');
    } catch (error) {
        console.error('Error fetching task types or milestones:', error);
    }

    // Log permission status after all data is loaded
    console.log('All data loaded, permission status:');
    console.log('- Global permissions:', globalPermissions.value);
    console.log('- Global permissions loading:', permissionsLoading.value);
    console.log('- Global permissions error:', permissionsError.value);
    console.log('- Project permissions:', projectPermissions.value);
    console.log('- Project permissions loading:', projectPermissionsLoading.value);
    console.log('- Project permissions error:', projectPermissionsError.value);
    console.log('- User project role:', userProjectRole.value);
    console.log('- Can manage projects:', canManageProjects.value);
});
</script>

<template>
    <Head :title="project.name || 'Project Details'" />

    <AuthenticatedLayout>

        <div class="py-8 max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Loading and Error States -->
            <div v-if="loading" class="text-center text-gray-600 text-lg animate-pulse">
                Loading project details...
            </div>
            <div v-else-if="generalError" class="text-center text-red-600 text-lg font-medium">
                {{ generalError }}
            </div>
            <div v-else class="space-y-8">
                <!-- General Information Card with Two Columns -->
                <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-lg font-semibold text-gray-900">General Information</h4>
                        <div v-if="canManageProjects || isSuperAdmin" class="flex gap-3">
                            <PrimaryButton
                                class="bg-indigo-600 hover:bg-indigo-700 transition-colors"
                                @click="openEditModal"
                            >
                                Edit Project
                            </PrimaryButton>
                            <!-- Debug info -->
<!--                            <div class="text-xs text-gray-500 mt-2 p-2 bg-gray-100 rounded">-->
<!--                                <div class="font-bold">Permission Debug Info:</div>-->
<!--                                <div>{{ userProjectRole ? 'Project Role: ' + (userProjectRole.value?.name || 'None') : 'No Project Role' }}</div>-->

<!--                                &lt;!&ndash; Global Permissions &ndash;&gt;-->
<!--                                <div class="mt-1">-->
<!--                                    <div class="font-semibold">Global Permissions:</div>-->
<!--                                    <div v-if="permissionsLoading">Loading global permissions...</div>-->
<!--                                    <div v-else-if="permissionsError">Error loading global permissions</div>-->
<!--                                    <div v-else-if="globalPermissions">-->
<!--                                        Count: {{ globalPermissions.permissions ? globalPermissions.permissions.length : 0 }}-->
<!--                                    </div>-->
<!--                                </div>-->

<!--                                &lt;!&ndash; Project Permissions &ndash;&gt;-->
<!--                                <div class="mt-1">-->
<!--                                    <div class="font-semibold">Project Permissions:</div>-->
<!--                                    <div v-if="projectPermissionsLoading">Loading project permissions...</div>-->
<!--                                    <div v-else-if="projectPermissionsError">Error loading project permissions</div>-->
<!--                                    <div v-else-if="projectPermissions">-->
<!--                                        Count: {{ projectPermissions.permissions ? projectPermissions.permissions.length : 0 }}-->
<!--                                        <div v-if="projectPermissions.permissions && projectPermissions.permissions.length > 0">-->
<!--                                            <div class="font-semibold">Permissions:</div>-->
<!--                                            <ul class="list-disc ml-4">-->
<!--                                                <li v-for="perm in projectPermissions.permissions.slice(0, 5)" :key="perm.slug">-->
<!--                                                    {{ perm.name }} ({{ perm.source }})-->
<!--                                                </li>-->
<!--                                                <li v-if="projectPermissions.permissions.length > 5">-->
<!--                                                    ... and {{ projectPermissions.permissions.length - 5 }} more-->
<!--                                                </li>-->
<!--                                            </ul>-->
<!--                                        </div>-->
<!--                                    </div>-->
<!--                                </div>-->

<!--                                <div class="mt-1">Can manage projects: {{ canManageProjects ? 'Yes' : 'No' }}</div>-->
<!--                            </div>-->
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 tracking-tight mb-2">{{ project.name }}</h3>
                            <p class="text-gray-600 text-base">{{ project.description || 'No description provided' }}</p>
                        </div>
                        <div class="space-y-3 text-sm text-gray-700">
                            <p><strong class="text-gray-900">Status:</strong> {{ project.status.replace('_', ' ').toUpperCase() }}</p>
                            <p><strong class="text-gray-900">Project Type:</strong> {{ project.project_type || 'N/A' }}</p>
                            <p><strong class="text-gray-900">Source:</strong> {{ project.source || 'N/A' }}</p>
                            <div class="flex space-x-4 mt-2">
                                <a v-if="project.website" :href="project.website" target="_blank"
                                   class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors"
                                   title="Visit Website">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                    </svg>
                                </a>
                                <a v-if="project.social_media_link" :href="project.social_media_link" target="_blank"
                                   class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors"
                                   title="Social Media">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                    </svg>
                                </a>
                                <a v-if="project.google_drive_link" :href="project.google_drive_link" target="_blank"
                                   class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors"
                                   title="Google Drive">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Stats Section -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                        <h4 class="text-sm font-semibold text-gray-500 mb-1">Pending Tasks</h4>
                        <p class="text-2xl font-bold text-indigo-600">{{ tasks.filter(t => t.status !== 'Completed').length }}</p>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                        <h4 class="text-sm font-semibold text-gray-500 mb-1">Unread Emails</h4>
                        <p class="text-2xl font-bold text-indigo-600">{{ emails.filter(e => e.status === 'Received').length }}</p>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                        <h4 class="text-sm font-semibold text-gray-500 mb-1">Last Email Received</h4>
                        <p class="text-2xl font-bold text-indigo-600">{{ emails.filter(e => e.status === 'Received').length ? emails.filter(e => e.status === 'Received')[0].date : 'N/A' }}</p>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                        <h4 class="text-sm font-semibold text-gray-500 mb-1">Next Task Deadline</h4>
                        <p class="text-2xl font-bold text-indigo-600">{{ tasks.filter(t => t.status !== 'Completed').length ? tasks.filter(t => t.status !== 'Completed').sort((a, b) => new Date(a.due_date) - new Date(b.due_date))[0].due_date : 'N/A' }}</p>
                    </div>
                </div>

                <!-- Three Column Section: Financial, Clients, Team -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <!-- Financial Information Card -->
                    <div v-if="canViewProjectFinancial" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Financial Information</h4>
                        <div class="space-y-3 text-sm text-gray-700">
                            <p><strong class="text-gray-900">Total Amount:</strong> ${{ project.total_amount || '0.00' }}</p>
                            <p><strong class="text-gray-900">Payment Type:</strong> {{ project.payment_type.replace('_', ' ').toUpperCase() }}</p>
                            <div v-if="project.service_details && project.service_details.length">
                                <strong class="text-gray-900">Service Details:</strong>
                                <ul class="list-disc ml-5 mt-2 space-y-2">
                                    <li v-for="service in project.service_details" :key="service.service_id">
                                        {{ service.service_id }}: ${{ service.amount }} ({{ service.frequency }}) - Starts {{ service.start_date || 'N/A' }}
                                        <div v-if="service.payment_breakdown" class="ml-4 mt-1">
                                            <p class="text-xs font-medium text-gray-600">Payment Breakdown:</p>
                                            <ul class="list-circle ml-5 text-xs text-gray-600">
                                                <li>First: {{ service.payment_breakdown.first }}%</li>
                                                <li>Second: {{ service.payment_breakdown.second }}%</li>
                                                <li>Third: {{ service.payment_breakdown.third }}%</li>
                                            </ul>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <p v-else class="text-gray-400">No service details available.</p>
                        </div>
                    </div>

                    <!-- Clients Card -->
                    <div v-if="canViewClientContacts" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Clients</h4>
                        <div v-if="project.clients && project.clients.length" class="space-y-3 text-sm text-gray-700">
                            <div v-for="client in project.clients" :key="client.id" class="border-l-4 border-indigo-500 pl-3">
                                <p><strong class="text-gray-900">{{ client.name }}</strong></p>
                                <p>Email: <a :href="`mailto:${client.email}`" class="text-indigo-600 hover:underline">{{ client.email }}</a></p>
                                <p>Phone: {{ client.phone || 'N/A' }}</p>
                                <p>Address: {{ client.address || 'N/A' }}</p>
                            </div>
                        </div>
                        <p v-else class="text-gray-400 text-sm">No clients assigned.</p>
                    </div>

                    <!-- Assigned Team Card -->
                    <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Assigned Team</h4>
                        <div v-if="project.users && project.users.length" class="space-y-3 text-sm text-gray-700">
                            <div v-for="user in project.users" :key="user.id" class="border-l-4 border-blue-500 pl-3">
                                <p><strong class="text-gray-900">{{ user.name }}</strong> ({{ user.pivot.role }})</p>
                                <p>Email: <a :href="`mailto:${user.email}`" class="text-indigo-600 hover:underline">{{ user.email }}</a></p>
                            </div>
                        </div>
                        <p v-else class="text-gray-400 text-sm">No team members assigned.</p>
                    </div>
                </div>

                <!-- Tasks Section -->
                <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-lg font-semibold text-gray-900">Project Tasks</h4>
                        <div class="flex gap-2">
                            <SecondaryButton
                                @click="fetchProjectTasks"
                                :disabled="loadingTasks"
                                class="text-indigo-600 hover:text-indigo-800"
                            >
                                <span v-if="!loadingTasks">Refresh</span>
                                <span v-else>Loading...</span>
                            </SecondaryButton>
                            <PrimaryButton
                                v-if="canManageProjects"
                                class="bg-indigo-600 hover:bg-indigo-700 transition-colors"
                                @click="openAddTaskModal"
                            >
                                Add Task
                            </PrimaryButton>
                        </div>
                    </div>

                    <!-- Milestone Timeline -->
                    <div class="mb-6 overflow-x-auto">
                        <div v-if="loadingMilestones" class="text-center text-gray-600 text-sm animate-pulse py-4">
                            Loading milestones...
                        </div>
                        <div v-else-if="!sortedMilestones.length" class="text-center py-4">
                            <p class="text-gray-400 text-sm">No milestones found for this project.</p>
                        </div>
                        <div v-else class="relative py-8">
                            <!-- Timeline Line -->
                            <div class="absolute h-1 bg-gray-200 top-1/2 left-0 right-0 transform -translate-y-1/2"></div>

                            <!-- Milestone Markers -->
                            <div class="relative flex justify-between">
                                <div
                                    v-for="(milestone, index) in sortedMilestones"
                                    :key="milestone.id"
                                    class="flex flex-col items-center relative z-10"
                                    :class="{'ml-4': index === 0, 'mr-4': index === sortedMilestones.length - 1}"
                                >
                                    <!-- Milestone Marker -->
                                    <div
                                        class="w-6 h-6 rounded-full shadow-lg flex items-center justify-center"
                                        :class="{
                                            'bg-gray-300': milestone.status === 'Not Started',
                                            'bg-blue-500': milestone.status === 'In Progress',
                                            'bg-green-500': milestone.status === 'Completed',
                                            'bg-red-500': milestone.status === 'Overdue'
                                        }"
                                    >
                                        <svg v-if="milestone.status === 'Completed'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <svg v-else-if="milestone.status === 'In Progress'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        <svg v-else-if="milestone.status === 'Overdue'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span v-else class="w-2 h-2 bg-white rounded-full"></span>
                                    </div>

                                    <!-- Milestone Name (above) -->
                                    <div class="absolute -top-8 transform -translate-x-1/2 left-1/2 w-32">
                                        <p class="text-xs font-medium text-gray-700 text-center truncate" :title="milestone.name">
                                            {{ milestone.name }}
                                        </p>
                                    </div>

                                    <!-- Milestone Date (below) -->
                                    <div class="absolute top-8 transform -translate-x-1/2 left-1/2">
                                        <p class="text-xs text-gray-500 whitespace-nowrap">
                                            {{ milestone.completion_date ? new Date(milestone.completion_date).toLocaleDateString() : 'No date' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Task Filters -->
                    <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex-1 min-w-[200px]">
                                <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select
                                    id="status-filter"
                                    v-model="taskFilters.status"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="To Do">To Do</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Done">Done</option>
                                    <option value="Blocked">Blocked</option>
                                    <option value="Archived">Archived</option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-[200px]">
                                <label for="assigned-filter" class="block text-sm font-medium text-gray-700 mb-1">Assigned To</label>
                                <select
                                    id="assigned-filter"
                                    v-model="taskFilters.assigned_to_user_id"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">All Users</option>
                                    <option value="-1">Unassigned</option>
                                    <option v-for="user in project.users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-[200px]">
                                <label for="milestone-filter" class="block text-sm font-medium text-gray-700 mb-1">Milestone</label>
                                <select
                                    id="milestone-filter"
                                    v-model="taskFilters.milestone_id"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">All Milestones</option>
                                    <option value="-1">No Milestone</option>
                                    <option v-for="milestone in milestones" :key="milestone.id" :value="milestone.id">
                                        {{ milestone.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-[200px]">
                                <label for="due-date-filter" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                                <select
                                    id="due-date-filter"
                                    v-model="taskFilters.due_date_range"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option v-for="option in dueDateOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex items-end">
                                <button
                                    @click="resetFilters"
                                    class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
                                >
                                    Clear Filters
                                </button>
                            </div>
                        </div>

                        <!-- Filter summary -->
                        <div v-if="Object.values(taskFilters).some(v => v !== '')" class="mt-3 text-sm text-gray-600">
                            <p>
                                Showing {{ filteredTasks.length }} of {{ tasks.length }} tasks
                                <span v-if="filteredTasks.length === 0" class="text-red-600 font-medium">
                                    (No tasks match the current filters)
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div v-if="loadingTasks" class="text-center text-gray-600 text-sm animate-pulse py-4">
                        Loading tasks...
                    </div>

                    <!-- Error State -->
                    <div v-else-if="tasksError" class="text-center py-4">
                        <p class="text-red-600 text-sm font-medium">{{ tasksError }}</p>
                    </div>

                    <!-- Tasks Table -->
                    <div v-else-if="tasks.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Milestone</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="task in filteredTasks" :key="task.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ task.title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <span
                                        :class="{
                                            'px-2 py-1 rounded-full text-xs font-medium': true,
                                            'bg-yellow-100 text-yellow-800': task.status === 'To Do',
                                            'bg-blue-100 text-blue-800': task.status === 'In Progress',
                                            'bg-green-100 text-green-800': task.status === 'Done',
                                            'bg-red-100 text-red-800': task.status === 'Blocked',
                                            'bg-gray-100 text-gray-800': task.status === 'Archived'
                                        }"
                                    >
                                        {{ task.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ task.assigned_to }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ task.due_date ? task.due_date : 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ task.milestone || 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end space-x-2">
                                        <button
                                            @click="editTask(task)"
                                            class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="openAddTaskNoteModal(task)"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                        >
                                            Add Note
                                        </button>
                                        <button
                                            v-if="task.status === 'To Do'"
                                            @click="startTask(task)"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                        >
                                            Start
                                        </button>
                                        <button
                                            v-if="task.status !== 'Done'"
                                            @click="markTaskAsCompleted(task)"
                                            class="text-green-600 hover:text-green-800 text-sm font-medium"
                                        >
                                            Complete
                                        </button>
<!--                                        <button-->
<!--                                            v-if="canManageProjects"-->
<!--                                            @click="deleteTask(task)"-->
<!--                                            class="text-red-600 hover:text-red-800 text-sm font-medium"-->
<!--                                        >-->
<!--                                            Delete-->
<!--                                        </button>-->
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-4">
                        <p class="text-gray-400 text-sm">No tasks found for this project.</p>
                        <p class="text-gray-500 text-sm mt-2">
                            Click the "Add Task" button to create a new task.
                        </p>
                    </div>
                </div>

                <!-- Email Communication Section -->
                <div v-if="canViewEmails" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-lg font-semibold text-gray-900">Email Communication</h4>
                        <div v-if="canComposeEmails" class="flex gap-3">
                            <PrimaryButton
                                class="bg-indigo-600 hover:bg-indigo-700 transition-colors"
                                @click="router.visit('/emails/compose')"
                            >
                                Compose Email
                            </PrimaryButton>
                        </div>
                    </div>

                    <!-- Loading and Error States -->
                    <div v-if="loadingEmails" class="text-center text-gray-600 text-sm animate-pulse py-4">
                        Loading email data...
                    </div>
                    <div v-else-if="emailError" class="text-center text-red-600 text-sm font-medium py-4">
                        {{ emailError }}
                    </div>
                    <div v-else-if="emails.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="email in emails" :key="email.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ email.subject }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ email.sender?.name || 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ Array.isArray(email.to) ? email.to.join(', ') : email.to }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ new Date(email.created_at).toLocaleDateString() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <span
                                        :class="{
                                            'px-2 py-1 rounded-full text-xs font-medium': true,
                                            'bg-green-100 text-green-800': email.status === 'sent',
                                            'bg-yellow-100 text-yellow-800': email.status === 'pending_approval',
                                            'bg-red-100 text-red-800': email.status === 'rejected',
                                            'bg-gray-100 text-gray-800': email.status === 'draft'
                                        }"
                                    >
                                        {{ email.status.replace('_', ' ').toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <SecondaryButton
                                        class="text-indigo-600 hover:text-indigo-800"
                                        @click="viewEmail(email)"
                                    >
                                        View
                                    </SecondaryButton>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-gray-400 text-sm">No email communication found.</p>
                </div>

                <!-- Notes Section -->
                <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-lg font-semibold text-gray-900">Notes</h4>
                        <div v-if="canDo('add_project_notes').value">
                            <PrimaryButton
                                class="bg-indigo-600 hover:bg-indigo-700 transition-colors"
                                @click="openAddNoteModal"
                            >
                                Add Note
                            </PrimaryButton>
                        </div>
                    </div>
                    <div v-if="project.notes && project.notes.length" class="space-y-4">
                        <div v-for="note in project.notes" :key="note.id" class="p-4 bg-gray-50 rounded-md shadow-sm hover:bg-gray-100 transition-colors">
                            <div class="flex justify-between">
                                <div class="flex-grow">
                                    <p class="text-sm" :class="{'text-gray-700': note.content !== '[Encrypted content could not be decrypted]', 'text-red-500 italic': note.content === '[Encrypted content could not be decrypted]'}">
                                        {{ note.content }}
                                        <span v-if="note.content === '[Encrypted content could not be decrypted]'" class="text-xs text-red-400 block mt-1">
                                            (There was an issue decrypting this note. Please contact an administrator.)
                                        </span>
                                    </p>
                                    <div class="flex items-center mt-1">
                                        <p class="text-xs text-gray-500">Added by {{ note.user?.name || 'Unknown' }} on {{ new Date(note.created_at).toLocaleDateString() }}</p>
                                        <span v-if="note.reply_count > 0" class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-800 text-xs rounded-full">
                                            {{ note.reply_count }} {{ note.reply_count === 1 ? 'reply' : 'replies' }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="canDo('add_project_notes').value && note.chat_message_id && project.google_chat_id">
                                    <SecondaryButton
                                        class="text-sm text-indigo-600 hover:text-indigo-800"
                                        @click="replyToNote(note)"
                                    >
                                        Reply
                                    </SecondaryButton>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-gray-400 text-sm">No notes available.</p>
                </div>
            </div>
        </div>

        <!-- Edit Project Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <ProjectForm
                :show="showEditModal"
                :project="project"
                :statusOptions="statusOptions"
                :departmentOptions="departmentOptions"
                :sourceOptions="sourceOptions"
                :clientRoleOptions="clientRoleOptions"
                :userRoleOptions="userRoleOptions"
                :paymentTypeOptions="paymentTypeOptions"
                @close="showEditModal = false"
                @submit="handleProjectSubmit"
            />
        </Modal>

        <!-- Email View Modal -->
        <Modal :show="showEmailModal" @close="showEmailModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Email Details</h3>
                    <button @click="showEmailModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="selectedEmail" class="space-y-4">
                    <!-- Email Header -->
                    <div class="border-b pb-4">
                        <h4 class="text-xl font-medium text-gray-900 mb-2">{{ selectedEmail.subject }}</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-600">From: <span class="text-gray-900">{{ selectedEmail.sender?.name || 'N/A' }}</span></p>
                                <p class="text-gray-600">To: <span class="text-gray-900">{{ Array.isArray(selectedEmail.to) ? selectedEmail.to.join(', ') : selectedEmail.to }}</span></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Date: <span class="text-gray-900">{{ new Date(selectedEmail.created_at).toLocaleString() }}</span></p>
                                <p class="text-gray-600">Status:
                                    <span
                                        :class="{
                                            'px-2 py-1 rounded-full text-xs font-medium': true,
                                            'bg-green-100 text-green-800': selectedEmail.status === 'sent',
                                            'bg-yellow-100 text-yellow-800': selectedEmail.status === 'pending_approval',
                                            'bg-red-100 text-red-800': selectedEmail.status === 'rejected',
                                            'bg-gray-100 text-gray-800': selectedEmail.status === 'draft'
                                        }"
                                    >
                                        {{ selectedEmail.status.replace('_', ' ').toUpperCase() }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Email Body -->
                    <div class="prose max-w-none">
                        <div v-html="selectedEmail.body"></div>
                    </div>

                    <!-- Additional Information -->
                    <div v-if="selectedEmail.rejection_reason" class="mt-4 p-4 bg-red-50 rounded-md">
                        <h5 class="font-medium text-red-800">Rejection Reason:</h5>
                        <p class="text-red-700">{{ selectedEmail.rejection_reason }}</p>
                    </div>

                    <div v-if="selectedEmail.approver" class="mt-4 text-sm text-gray-600">
                        <p>Approved/Rejected by: {{ selectedEmail.approver.name }}</p>
                        <p v-if="selectedEmail.sent_at">Sent at: {{ new Date(selectedEmail.sent_at).toLocaleString() }}</p>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Add Note Modal -->
        <NotesModal
            :show="showAddNoteModal"
            :project-id="project.id"
            @close="showAddNoteModal = false"
            @note-added="fetchProjectData"
        />

        <!-- Reply to Note Modal -->
        <Modal :show="showReplyModal" @close="showReplyModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Reply to Note</h3>
                    <button @click="showReplyModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="selectedNote" class="mb-4 p-3 bg-gray-100 rounded-md">
                    <p class="text-sm text-gray-700">{{ selectedNote.content }}</p>
                    <p class="text-xs text-gray-500 mt-1">Added by {{ selectedNote.user?.name || 'Unknown' }} on {{ new Date(selectedNote.created_at).toLocaleDateString() }}</p>
                </div>

                <!-- Replies Section -->
                <div v-if="selectedNote" class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Replies</h4>

                    <!-- Loading State -->
                    <div v-if="loadingReplies" class="text-center py-4">
                        <p class="text-gray-500 text-sm">Loading replies...</p>
                    </div>

                    <!-- Replies List -->
                    <div v-else-if="noteReplies.length" class="space-y-3 max-h-60 overflow-y-auto">
                        <div v-for="reply in noteReplies" :key="reply.id" class="p-2 bg-gray-50 rounded border-l-2 border-indigo-300">
                            <p class="text-sm text-gray-700">{{ reply.content }}</p>
                            <p class="text-xs text-gray-500 mt-1">Replied by {{ reply.user?.name || 'Unknown' }} on {{ new Date(reply.created_at).toLocaleDateString() }}</p>
                        </div>
                    </div>

                    <!-- No Replies State -->
                    <div v-else class="text-center py-3">
                        <p class="text-gray-500 text-sm">No replies yet. Be the first to reply!</p>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="reply-content" class="block text-sm font-medium text-gray-700 mb-1">Your Reply</label>
                    <textarea
                        id="reply-content"
                        v-model="replyContent"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full h-32"
                        placeholder="Enter your reply..."
                    ></textarea>
                    <p v-if="replyError" class="mt-2 text-sm text-red-600">{{ replyError }}</p>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showReplyModal = false" class="mr-2">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitReply">Send Reply</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Task Modal -->
        <Modal :show="showTaskModal" @close="showTaskModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ selectedTask ? 'Edit Task' : 'Add New Task' }}
                    </h3>
                    <button @click="showTaskModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitTaskForm" class="space-y-4">
                    <!-- Task Name -->
                    <div>
                        <label for="task-name" class="block text-sm font-medium text-gray-700">Task Name</label>
                        <input
                            id="task-name"
                            v-model="taskFormData.name"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Enter task name"
                        />
                        <p v-if="taskFormErrors.name" class="mt-1 text-sm text-red-600">{{ taskFormErrors.name[0] }}</p>
                    </div>

                    <!-- Task Description -->
                    <div>
                        <label for="task-description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea
                            id="task-description"
                            v-model="taskFormData.description"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows="3"
                            placeholder="Enter task description"
                        ></textarea>
                        <p v-if="taskFormErrors.description" class="mt-1 text-sm text-red-600">{{ taskFormErrors.description[0] }}</p>
                    </div>

                    <!-- Task Status -->
                    <div>
                        <label for="task-status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select
                            id="task-status"
                            v-model="taskFormData.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="To Do">To Do</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Done">Done</option>
                            <option value="Blocked">Blocked</option>
                            <option value="Archived">Archived</option>
                        </select>
                        <p v-if="taskFormErrors.status" class="mt-1 text-sm text-red-600">{{ taskFormErrors.status[0] }}</p>
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label for="task-due-date" class="block text-sm font-medium text-gray-700">Due Date</label>
                        <input
                            id="task-due-date"
                            v-model="taskFormData.due_date"
                            type="date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="taskFormErrors.due_date" class="mt-1 text-sm text-red-600">{{ taskFormErrors.due_date[0] }}</p>
                    </div>

                    <!-- Assigned To -->
                    <div>
                        <label for="task-assigned-to" class="block text-sm font-medium text-gray-700">Assigned To</label>
                        <select
                            id="task-assigned-to"
                            v-model="taskFormData.assigned_to_user_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="null">Unassigned</option>
                            <option v-for="user in project.users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                        <p v-if="taskFormErrors.assigned_to_user_id" class="mt-1 text-sm text-red-600">{{ taskFormErrors.assigned_to_user_id[0] }}</p>
                    </div>

                    <!-- Task Type -->
                    <div>
                        <label for="task-type" class="block text-sm font-medium text-gray-700">Task Type</label>
                        <div v-if="loadingTaskTypes" class="text-sm text-gray-500">Loading task types...</div>
                        <select
                            v-else
                            id="task-type"
                            v-model="taskFormData.task_type_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="null">Select a task type</option>
                            <option v-for="type in taskTypes" :key="type.id" :value="type.id">
                                {{ type.name }}
                            </option>
                        </select>
                        <p v-if="taskFormErrors.task_type_id" class="mt-1 text-sm text-red-600">{{ taskFormErrors.task_type_id[0] }}</p>
                    </div>

                    <!-- Milestone -->
                    <div>
                        <div class="flex justify-between items-center">
                            <label for="task-milestone" class="block text-sm font-medium text-gray-700">Milestone</label>
                            <button
                                type="button"
                                @click="openAddMilestoneModal"
                                class="text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                + Create New Milestone
                            </button>
                        </div>
                        <div v-if="loadingMilestones" class="text-sm text-gray-500">Loading milestones...</div>
                        <select
                            v-else
                            id="task-milestone"
                            v-model="taskFormData.milestone_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="null">No milestone</option>
                            <option v-for="milestone in milestones" :key="milestone.id" :value="milestone.id">
                                {{ milestone.name }}
                            </option>
                        </select>
                        <p v-if="taskFormErrors.milestone_id" class="mt-1 text-sm text-red-600">{{ taskFormErrors.milestone_id[0] }}</p>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="showTaskModal = false">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit" :disabled="loadingTaskTypes || loadingMilestones">
                            {{ selectedTask ? 'Update Task' : 'Create Task' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Milestone Modal -->
        <Modal :show="showMilestoneModal" @close="showMilestoneModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add New Milestone</h3>
                    <button @click="showMilestoneModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitMilestoneForm" class="space-y-4">
                    <!-- Milestone Name -->
                    <div>
                        <label for="milestone-name" class="block text-sm font-medium text-gray-700">Milestone Name</label>
                        <input
                            id="milestone-name"
                            v-model="milestoneFormData.name"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Enter milestone name"
                        />
                        <p v-if="milestoneFormErrors.name" class="mt-1 text-sm text-red-600">{{ milestoneFormErrors.name[0] }}</p>
                    </div>

                    <!-- Milestone Description -->
                    <div>
                        <label for="milestone-description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea
                            id="milestone-description"
                            v-model="milestoneFormData.description"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows="3"
                            placeholder="Enter milestone description"
                        ></textarea>
                        <p v-if="milestoneFormErrors.description" class="mt-1 text-sm text-red-600">{{ milestoneFormErrors.description[0] }}</p>
                    </div>

                    <!-- Completion Date -->
                    <div>
                        <label for="milestone-completion-date" class="block text-sm font-medium text-gray-700">Completion Date</label>
                        <input
                            id="milestone-completion-date"
                            v-model="milestoneFormData.completion_date"
                            type="date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="milestoneFormErrors.completion_date" class="mt-1 text-sm text-red-600">{{ milestoneFormErrors.completion_date[0] }}</p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="milestone-status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select
                            id="milestone-status"
                            v-model="milestoneFormData.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Overdue">Overdue</option>
                        </select>
                        <p v-if="milestoneFormErrors.status" class="mt-1 text-sm text-red-600">{{ milestoneFormErrors.status[0] }}</p>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="showMilestoneModal = false" type="button">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit" :disabled="creatingMilestone">
                            {{ creatingMilestone ? 'Creating...' : 'Create Milestone' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Task Note Modal -->
        <Modal :show="showTaskNoteModal" @close="showTaskNoteModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add Note to Task</h3>
                    <button @click="showTaskNoteModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="taskForNote" class="mb-4 p-3 bg-gray-100 rounded-md">
                    <p class="text-sm font-medium text-gray-900">{{ taskForNote.title }}</p>
                    <p class="text-xs text-gray-500 mt-1">Status: {{ taskForNote.status }}</p>
                </div>

                <div class="mb-4">
                    <label for="task-note-content" class="block text-sm font-medium text-gray-700 mb-1">Note Content</label>
                    <textarea
                        id="task-note-content"
                        v-model="taskNoteContent"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full h-32"
                        placeholder="Enter your note..."
                    ></textarea>
                    <p v-if="taskNoteError" class="mt-2 text-sm text-red-600">{{ taskNoteError }}</p>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showTaskNoteModal = false" class="mr-2">Cancel</SecondaryButton>
                    <PrimaryButton @click="addTaskNote" :disabled="addingTaskNote">
                        {{ addingTaskNote ? 'Adding...' : 'Add Note' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom styles for subtle enhancements */
th, td {
    min-width: 120px;
}
</style>
