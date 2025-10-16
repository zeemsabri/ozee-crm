<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { fetchCurrencyRates, displayCurrency } from '@/Utils/currency';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Filters from '@/Pages/Workspace/components/Filters.vue';
import ProjectCards from '@/Pages/Workspace/components/ProjectCards.vue';
import Sidebar from '@/Pages/Workspace/components/Sidebar.vue';
import KanbanBoard from '@/Components/KanbanBoard.vue';
import BlockReasonModal from '@/Components/BlockReasonModal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { usePermissions, usePermissionStore } from '@/Directives/permissions.js';
import * as taskState from '@/Utils/taskState.js';
import { openTaskDetailSidebar } from '@/Utils/sidebar';

// Mock data to simulate fetching from a backend
const projects = ref([
    {
        id: 1,
        name: 'Project Phoenix',
        role: 'Manager',
        health: 'at-risk',
        alert: {
            text: 'Client email from Project Phoenix requires a reply.',
            timer: '3h 25m',
            incentive: 'Reply in time to earn 50 points.',
        },
        overview: {
            milestone: '3 of 5 - Design Phase (60% Complete)',
            budget: '$15,000 / $25,000 Used',
            status: 'On Track',
        },
        tasks: {
            today: [
                { name: 'Create new ad copy', status: 'started', assignee: 'Alex Ray' },
                { name: 'Review wireframes', status: 'blocked', assignee: 'Sarah Chen' },
            ],
            tomorrow: [
                { name: 'Client feedback call', status: 'paused', assignee: 'Jane Doe' },
                { name: 'Design review meeting', status: 'complete', assignee: 'Team A' },
            ],
            completed: [
                { name: 'Initial Project Kickoff', status: 'complete' },
            ],
        },
        communication: {
            lastSent: 'Today at 9:15 AM',
            lastReceived: '3 days ago at 4:30 PM',
        }
    },
    {
        id: 2,
        name: 'Project Odyssey',
        role: 'Contributor',
        health: 'needs-attention',
        tasks: {
            today: [
                { name: 'Develop login API endpoint', status: 'blocked' },
                { name: 'Write unit tests for checkout process', status: 'started' },
            ],
            tomorrow: [
                { name: 'Refactor database schema', status: 'paused' },
            ],
            completed: [
                { name: 'Update documentation', status: 'complete' },
            ],
        },
        milestone: {
            name: 'Backend API Development',
            deadline: 'September 5, 2025',
            progress: 75,
            incentive: 'Complete on time to earn a 5% bonus and 200 points!',
        }
    },
    {
        id: 3,
        name: 'Project Gemini',
        role: 'Manager',
        health: 'on-track',
        alert: null,
        overview: {
            milestone: '2 of 4 - Testing Phase (95% Complete)',
            budget: '$8,000 / $10,000 Used',
            status: 'In Progress',
        },
        tasks: {
            today: [
                { name: 'User Acceptance Testing', status: 'complete' },
            ],
            tomorrow: [
                { name: 'Deploy to staging', status: 'started' },
            ],
            completed: [
                { name: 'Initial QA Review', status: 'complete' },
            ],
        },
        communication: {
            lastSent: 'Yesterday at 11:00 AM',
            lastReceived: 'Yesterday at 10:45 AM',
        }
    },
    {
        id: 4,
        name: 'Project Alpha',
        role: 'Contributor',
        health: 'on-track',
        tasks: {
            today: [],
            tomorrow: [],
            completed: [
                { name: 'Initial project setup', status: 'complete' },
                { name: 'Write documentation', status: 'complete' },
            ],
        },
        milestone: {
            name: 'Project Kickoff',
            deadline: 'September 20, 2025',
            progress: 100,
            incentive: 'Complete on time to earn a 5% bonus and 200 points!',
        }
    },
]);

// State for filtering projects
const activeFilter = ref('all');
const searchTerm = ref('');
const pendingFilter = ref('with'); // with | without

// Computed property to filter projects based on the active filter
const filteredProjects = computed(() => {
    if (activeFilter.value === 'all') {
        return projects.value;
    }
    if (activeFilter.value === 'manager') {
        return projects.value.filter(p => p.role === 'Manager');
    }
    if (activeFilter.value === 'contributor') {
        return projects.value.filter(p => p.role === 'Contributor');
    }
    // Return an empty array if filter is 'my' (this would be handled with a real user ID)
    return [];
});

// State for the checklist and notes, to be passed to the Sidebar
const checklistItems = ref([
    'Follow up with Alex on Project Phoenix',
    'Prepare for sprint planning',
]);
const notes = ref(localStorage.getItem('my_dashboard_notes') || '');

// Handler for when a new checklist item is added from the Sidebar
function handleAddChecklistItem(newItem) {
    if (newItem) {
        checklistItems.value.push(newItem);
    }
}

// Handler for when a checklist item is deleted from the Sidebar
function handleRemoveChecklistItem(index) {
    checklistItems.value.splice(index, 1);
}

// Handler for when a note is updated from the Sidebar
function handleUpdateNotes(newNotes) {
    notes.value = newNotes;
    localStorage.setItem('my_dashboard_notes', newNotes);
}

// Kanban toggle
const kanbanView = ref(localStorage.getItem('workspace_kanban_view') === '1');

watch(kanbanView, (v) => {
    try { localStorage.setItem('workspace_kanban_view', v ? '1' : '0'); } catch (e) {}
});

// Kanban columns and data scaffolding
const kanbanColumns = [
    { key: 'To Do', title: 'To Do' },
    { key: 'In Progress', title: 'In Progress' },
    { key: 'Paused', title: 'Paused' },
    { key: 'Blocked', title: 'Blocked' },
    { key: 'Done', title: 'Done' },
    { key: 'Archived', title: 'Archived' },
];

// User-selectable visible columns (Archived optional by default hidden)
const defaultVisibleColumns = ['To Do', 'In Progress', 'Paused', 'Blocked', 'Done'];
const getSavedColumns = () => {
    try {
        const saved = JSON.parse(localStorage.getItem('workspace_kanban_columns') || 'null');
        if (Array.isArray(saved) && saved.length) return saved;
    } catch (e) {}
    return defaultVisibleColumns;
};
const visibleColumns = ref(getSavedColumns());
const showColumnsMenu = ref(false);

watch(visibleColumns, (val) => {
    try { localStorage.setItem('workspace_kanban_columns', JSON.stringify(val)); } catch (e) {}
}, { deep: true });

const visibleKanbanColumns = computed(() => kanbanColumns.filter(c => visibleColumns.value.includes(c.key)));

// Assigned tasks for Kanban
const assignedTasks = ref([]);
const loadingAssignedTasks = ref(false);
const tasksError = ref('');

// Filters state
const searchText = ref('');
const dueFilter = ref('all'); // all | today | overdue | week
const completedFilter = ref('all'); // all | completed_today | completed_yesterday | completed_this_week | completed_last_week | completed_this_month | completed_last_month | completed_last_7 | completed_last_30
const projectId = ref(null);
const assigneeId = ref(null);
const priority = ref(''); // '', 'low','medium','high'
const milestoneId = ref(null);
const showTaskFilters = ref(false);

// Dropdown options loaded from API
const projectOptions = ref([]);
const usersOptions = ref([]);
const milestoneOptions = ref([]);

// Permission store and checker
const permissionStore = usePermissionStore();
const { canDo } = usePermissions();

const today = new Date();
const startOfWeek = new Date(today);
startOfWeek.setDate(today.getDate() - today.getDay()); // Sunday
startOfWeek.setHours(0,0,0,0);
const endOfWeek = new Date(startOfWeek);
endOfWeek.setDate(startOfWeek.getDate() + 6);
endOfWeek.setHours(23,59,59,999);

// Date helpers for Completed filters
const yesterday = new Date(today);
yesterday.setDate(today.getDate() - 1);
yesterday.setHours(0,0,0,0);
const yesterdayEnd = new Date(yesterday);
yesterdayEnd.setHours(23,59,59,999);

const lastWeekEnd = new Date(startOfWeek);
lastWeekEnd.setMilliseconds(-1);
const lastWeekStart = new Date(lastWeekEnd);
lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
lastWeekStart.setHours(0,0,0,0);

const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
endOfMonth.setHours(23,59,59,999);
const lastMonthEnd = new Date(startOfMonth);
lastMonthEnd.setMilliseconds(-1);
const lastMonthStart = new Date(lastMonthEnd.getFullYear(), lastMonthEnd.getMonth(), 1);
lastMonthStart.setHours(0,0,0,0);

const last7Start = new Date(today);
last7Start.setDate(today.getDate() - 6);
last7Start.setHours(0,0,0,0);
const last30Start = new Date(today);
last30Start.setDate(today.getDate() - 29);
last30Start.setHours(0,0,0,0);

const priorityOptions = [
    { value: '', label: 'All Priorities' },
    { value: 'high', label: 'High' },
    { value: 'medium', label: 'Medium' },
    { value: 'low', label: 'Low' },
];

// Clear helpers
const clearAllFilters = () => {
    searchText.value = '';
    projectId.value = null;
    assigneeId.value = null;
    priority.value = '';
    milestoneId.value = null;
    dueFilter.value = 'all';
    completedFilter.value = 'all';
};
const clearProject = () => { projectId.value = null; };
const clearAssignee = () => { assigneeId.value = null; };
const clearPriority = () => { priority.value = ''; };
const clearMilestone = () => { milestoneId.value = null; };
const clearDueFilter = () => { dueFilter.value = 'all'; };
const clearCompletedFilter = () => { completedFilter.value = 'all'; };
const clearSearch = () => { searchText.value = ''; };

// Caches
const projectUsersCache = new Map();

// Load projects via /api/projects-simplified
const loadProjects = async () => {
    try {
        const { data } = await window.axios.get('/api/projects-simplified');
        const opts = Array.isArray(data) ? data.map(p => ({ value: p.id, label: p.name })) : [];
        // Prepend explicit All Projects option (null means all)
        projectOptions.value = [{ value: null, label: 'All Projects' }, ...opts];
    } catch (e) {
        console.error('Failed to fetch projects-simplified', e);
        projectOptions.value = [{ value: null, label: 'All Projects' }];
    }
};

// Load all users if permitted
const loadAllUsers = async () => {
    try {
        const { data } = await window.axios.get('/api/users');
        const base = (Array.isArray(data) ? data : []).map(u => ({ value: u.id, label: u.name }));
        // Always show 'All Users' filter to everyone
        usersOptions.value = [{ value: '__all__', label: 'All Users' }, ...base];
    } catch (e) {
        console.error('Failed to fetch users', e);
        // Always show 'All Users' filter to everyone, even on error
        usersOptions.value = [{ value: '__all__', label: 'All Users' }];
    }
};

// Load users for a specific project
const loadProjectUsers = async (pid) => {
    if (!pid && pid !== 0) return [];
    if (projectUsersCache.has(pid)) return projectUsersCache.get(pid);
    try {
        const { data } = await window.axios.get(`/api/projects/${pid}/users`);
        const opts = (Array.isArray(data) ? data : []).map(u => ({ value: u.id, label: u.name }));
        projectUsersCache.set(pid, opts);
        return opts;
    } catch (e) {
        console.error('Failed to fetch project users', e);
        return [];
    }
};

// Load union of users across accessible projects
const loadUsersFromAccessibleProjects = async () => {
    const ids = projectOptions.value.map(p => p.value).filter(v => v !== null);
    const unique = new Map();
    await Promise.all(ids.map(async (pid) => {
        const list = await loadProjectUsers(pid);
        for (const u of list) {
            if (!unique.has(u.value)) unique.set(u.value, u);
        }
    }));
    const list = Array.from(unique.values()).sort((a,b)=> String(a.label).localeCompare(String(b.label)));
    // Always show 'All Users' filter to everyone
    usersOptions.value = [{ value: '__all__', label: 'All Users' }, ...list];
};

// Load milestones for a project
const loadProjectMilestones = async (pid) => {
    milestoneOptions.value = [];
    if (!pid) return;
    try {
        const { data } = await window.axios.get(`/api/projects/${pid}/milestones`);
        milestoneOptions.value = (Array.isArray(data) ? data : []).map(m => ({ value: m.id, label: m.name }));
    } catch (e) {
        console.warn('Failed to fetch project milestones; falling back to none', e);
        milestoneOptions.value = [];
    }
};

// Initialize permissions and dropdown data
onMounted(async () => {
    try { await permissionStore.fetchGlobalPermissions(); } catch (_) {}
    await loadProjects();
    // Users: Always use accessible projects approach to respect user project access
    // but ensure All Users filter is available to everyone
    if (canDo('view_all_user').value) {
        await loadAllUsers();
    } else {
        await loadUsersFromAccessibleProjects();
    }
});

    // React to project selection changes
watch(projectId, async (pid) => {
    // Reset milestone state
    milestoneId.value = null;
    await loadProjectMilestones(pid);

    if (canDo('view_all_user').value) {
        // If can view all users, but a project is selected we still filter to project users per requirement
        if (pid) {
            const list = await loadProjectUsers(pid);
            usersOptions.value = [{ value: '__all__', label: 'All Users' }, ...list];
        } else {
            await loadAllUsers();
        }
    } else {
        if (pid) {
            const list = await loadProjectUsers(pid);
            // Always show 'All Users' filter to everyone
            usersOptions.value = [{ value: '__all__', label: 'All Users' }, ...list];
        } else {
            await loadUsersFromAccessibleProjects();
        }
    }

// If currently viewing All Users, re-fetch tasks with new project scope to avoid massive payloads
    if (kanbanView.value && assigneeId.value === '__all__') {
        const baseParams = {};
        if (pid) baseParams.project_id = pid;
        await fetchAllUsersScopedTasks(baseParams);
    }
});

const fetchAssignedTasksAll = async () => {
    const userId = usePage().props.auth?.user?.id;
    if (!userId) return;
    loadingAssignedTasks.value = true;
    tasksError.value = '';
    try {
        const { data } = await window.axios.get('/api/tasks', { params: { assigned_to_user_id: userId } });
        assignedTasks.value = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error('Failed to load assigned tasks for kanban', e);
        tasksError.value = 'Failed to load tasks';
        assignedTasks.value = [];
    } finally {
        loadingAssignedTasks.value = false;
    }
};

const fetchTasksWithParams = async (params) => {
    loadingAssignedTasks.value = true;
    tasksError.value = '';
    try {
        const { data } = await window.axios.get('/api/tasks', { params });
        assignedTasks.value = Array.isArray(data) ? data : (Array.isArray(data?.data) ? data.data : []);
    } catch (e) {
        console.error('Failed to load tasks', e);
        tasksError.value = 'Failed to load tasks';
        assignedTasks.value = [];
    } finally {
        loadingAssignedTasks.value = false;
    }
};

// Fetch only due (including overdue) tasks and today's completed tasks for All Users
const fetchAllUsersScopedTasks = async (baseParams = {}) => {
    loadingAssignedTasks.value = true;
    tasksError.value = '';
    try {
        const todayStr = new Date().toISOString().slice(0,10);
        let params = { ...baseParams, per_page: 500, statuses: 'To Do,In Progress,Paused,Blocked,Done', due_until: todayStr };
        
        // If user doesn't have view_all_user permission and no specific project is selected,
        // we need to limit to their accessible projects
        if (!canDo('view_all_user').value && !baseParams.project_id) {
            const accessibleProjectIds = projectOptions.value
                .map(p => p.value)
                .filter(v => v !== null);
            if (accessibleProjectIds.length > 0) {
                params.project_ids = accessibleProjectIds.join(',');
            }
        }
        
        const res = await window.axios.get('/api/tasks', { params });
        const list = Array.isArray(res.data) ? res.data : (Array.isArray(res.data?.data) ? res.data.data : []);
        assignedTasks.value = list;
    } catch (e) {
        console.error('Failed to load scoped All Users tasks', e);
        tasksError.value = 'Failed to load tasks';
        assignedTasks.value = [];
    } finally {
        loadingAssignedTasks.value = false;
    }
};

watch(kanbanView, (on) => {
    setTimeout(() => {
        if (on && assignedTasks.value.length === 0 && !loadingAssignedTasks.value) {
            fetchAssignedTasksAll();
        }
    }, 500)

}, { immediate: true });

// Re-fetch tasks when assignee selection changes
watch(assigneeId, async (v) => {
    if (!kanbanView.value) return;
    const currentUserId = usePage().props.auth?.user?.id;
    // Build scoped params
    const baseParams = {};
    if (projectId.value) baseParams.project_id = projectId.value;
    
    if (v === '__all__') {
        // All users can now use 'All Users' filter, but tasks are scoped to their accessible projects
        await fetchAllUsersScopedTasks(baseParams);
    } else if (v) {
        await fetchTasksWithParams({ ...baseParams, assigned_to_user_id: v, per_page: 500 });
    } else {
        if (currentUserId) await fetchTasksWithParams({ ...baseParams, assigned_to_user_id: currentUserId, per_page: 500 });
    }
});

const filteredAssignedTasks = computed(() => {
    const q = (searchText.value || '').toLowerCase().trim();
    const result = (assignedTasks.value || []).filter(t => {
        // Search by name/milestone/project
        const matchesSearch = !q ||
            String(t.name || t.title || '').toLowerCase().includes(q) ||
            String(t.milestone?.name || '').toLowerCase().includes(q) ||
            String(t.project?.name || '').toLowerCase().includes(q);

        // Project filter
        const pid = t.project?.id ?? t.milestone?.project_id ?? null;
        const matchesProject = !projectId.value || (pid && String(pid) === String(projectId.value));

        // Assignee filter
        const uid = t.assigned_to_user_id ?? t.assigned_to?.id ?? t.assigned_to_id ?? null;
        const matchesAssignee = !assigneeId.value || assigneeId.value === '__all__' || (uid && String(uid) === String(assigneeId.value));

        // Priority filter
        const matchesPriority = !priority.value || String(t.priority || '').toLowerCase() === String(priority.value);

        // Milestone filter
        const mid = t.milestone?.id ?? null;
        const matchesMilestone = !milestoneId.value || (mid && String(mid) === String(milestoneId.value));

        // Due filter
        let matchesDue = true;
        if (dueFilter.value && dueFilter.value !== 'all') {
            const due = t.due_date ? new Date(t.due_date) : null;
            if (!due) {
                // If no due date, exclude for due-based filters
                matchesDue = false;
            } else {
                const dueDay = new Date(due); dueDay.setHours(0,0,0,0);
                const todayDay = new Date(); todayDay.setHours(0,0,0,0);
                if (dueFilter.value === 'today') {
                    matchesDue = (dueDay.getTime() === todayDay.getTime());
                } else if (dueFilter.value === 'overdue') {
                    matchesDue = (dueDay.getTime() < todayDay.getTime());
                } else if (dueFilter.value === 'week') {
                    matchesDue = (due >= startOfWeek && due <= endOfWeek);
                }
            }
        }

        // Completed filter (filters by completion timestamp; only includes Done tasks when active)
        let matchesCompleted = true;
        if (completedFilter.value && completedFilter.value !== 'all') {
            const completedAt = t.completed_at || (t.status === 'Done' ? t.updated_at : null);
            if (!completedAt) {
                matchesCompleted = false;
            } else {
                const c = new Date(completedAt);
                const cDay = new Date(c); cDay.setHours(0,0,0,0);
                const todayDay = new Date(); todayDay.setHours(0,0,0,0);
                switch (completedFilter.value) {
                    case 'completed_today':
                        matchesCompleted = (cDay.getTime() === todayDay.getTime());
                        break;
                    case 'completed_yesterday':
                        matchesCompleted = (c >= yesterday && c <= yesterdayEnd);
                        break;
                    case 'completed_this_week':
                        matchesCompleted = (c >= startOfWeek && c <= endOfWeek);
                        break;
                    case 'completed_last_week':
                        matchesCompleted = (c >= lastWeekStart && c <= lastWeekEnd);
                        break;
                    case 'completed_this_month':
                        matchesCompleted = (c >= startOfMonth && c <= endOfMonth);
                        break;
                    case 'completed_last_month':
                        matchesCompleted = (c >= lastMonthStart && c <= lastMonthEnd);
                        break;
                    case 'completed_last_7':
                        matchesCompleted = (c >= last7Start && c <= endOfWeek /* use now end of day */);
                        break;
                    case 'completed_last_30':
                        matchesCompleted = (c >= last30Start && c <= endOfWeek /* use now end of day */);
                        break;
                    default:
                        matchesCompleted = true;
                }
                // Ensure only Done tasks are included when completed filter is active
                if (matchesCompleted) {
                    matchesCompleted = (t.status === 'Done');
                }
            }
        }

        return matchesSearch && matchesProject && matchesAssignee && matchesPriority && matchesMilestone && matchesDue && matchesCompleted;
    });
    return result;
});

const itemsByColumn = computed(() => {
    const init = {
        'To Do': [],
        'In Progress': [],
        'Paused': [],
        'Blocked': [],
        'Done': [],
        'Archived': [],
    };
    for (const t of filteredAssignedTasks.value) {
        const key = t.status && init[t.status] !== undefined ? t.status : 'To Do';
        init[key].push(t);
    }
    // Limit Done to latest 10 by updated_at desc
    if (init['Done'] && init['Done'].length > 0) {
        init['Done'].sort((a,b) => new Date(b.updated_at || b.completed_at || 0) - new Date(a.updated_at || a.completed_at || 0));
        init['Done'] = init['Done'].slice(0, 10);
    }
    return init;
});

// Block reason modal state for workspace-level actions
const showBlockReason = ref(false);
const taskPendingBlock = ref(null);

const openWorkspaceBlockModal = (task) => {
    taskPendingBlock.value = task;
    showBlockReason.value = true;
};

const confirmWorkspaceBlock = async (reason) => {
    if (!taskPendingBlock.value) return;
    try {
        const updated = await taskState.blockTask(taskPendingBlock.value, reason);
        updateTaskInList(updated);
    } catch (e) {
        console.error('Failed to block task from workspace:', e);
    } finally {
        showBlockReason.value = false;
        taskPendingBlock.value = null;
    }
};

// Helpers for transitions
const getTransition = (from, to) => {
    if (from === to) return null;
    // Main allowed paths
    if (from === 'To Do' && to === 'In Progress') return 'start';
    if (from === 'In Progress' && to === 'Paused') return 'pause';
    if (from === 'Paused' && to === 'In Progress') return 'resume';
    if (from === 'In Progress' && to === 'Done') return 'complete';
    if ((from === 'To Do' || from === 'In Progress' || from === 'Paused') && to === 'Blocked') return 'block';
    if (from === 'Blocked' && to === 'In Progress') return 'unblock_to_inprogress';
    if (from === 'Done' && to === 'To Do') return 'revise';
    // Disallow direct moves to Archived or other unsupported jumps
    return null;
};

const updateTaskInList = (updated) => {
    if (!updated) return;
    const idx = assignedTasks.value.findIndex(t => String(t.id) === String(updated.id));
    if (idx >= 0) {
        assignedTasks.value[idx] = { ...assignedTasks.value[idx], ...updated };
    } else {
        assignedTasks.value.push(updated);
    }
};

const handleKanbanDrop = async ({ data, to }) => {
    if (!data || !to) return;

    const from = data.status;
    const action = getTransition(from, to);
    if (!action) return; // silently ignore disallowed moves

    // Optimistic move
    const prev = { ...data };
    data.status = to;
    updateTaskInList(data);

    try {
        let updated;
        switch (action) {
            case 'start':
                updated = await taskState.startTask(prev);
                break;
            case 'pause':
                updated = await taskState.pauseTask(prev);
                break;
            case 'resume':
                updated = await taskState.resumeTask(prev);
                break;
            case 'complete':
                updated = await taskState.completeTask(prev);
                break;
            case 'block':
                // For block we collect reason via modal and return without API here
                // Revert optimistic change for now; modal flow will update
                data.status = prev.status;
                updateTaskInList(prev);
                return openWorkspaceBlockModal(prev);
            case 'unblock_to_inprogress':
                // Unblock first; server restores previous status (or To Do)
                updated = await taskState.unblockTask(prev);
                // If not in progress yet, try to transition forward to reach In Progress
                if (updated.status === 'To Do') {
                    updated = await taskState.startTask(updated);
                }
                break;
            case 'revise':
                updated = await taskState.reviseTask(prev);
                break;
            default:
                throw new Error('Unsupported transition');
        }
        updateTaskInList(updated);
    } catch (e) {
        // Roll back
        updateTaskInList(prev);
    }
};

// Initialize currency conversion similar to Admin/ProjectExpendables
onMounted(async () => {
    try {
        const stored = localStorage.getItem('displayCurrency');
        if (stored) displayCurrency.value = stored;
        await fetchCurrencyRates();
    } catch (e) {
        console.warn('Currency initialization failed in Workspace/Index.vue:', e);
    }
});

</script>

<template>
    <Head title="My Workspace" />

    <AuthenticatedLayout>
<template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-md border text-sm transition
                               border-gray-300 text-gray-700 hover:bg-gray-50
                               data-[active=true]:bg-indigo-600 data-[active=true]:text-white data-[active=true]:border-indigo-600"
                        :data-active="kanbanView ? 'true' : 'false'"
                        @click="kanbanView = !kanbanView"
                    >
                        Kanban
                    </button>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Workspace</h2>
                </div>
            </div>
        </template>

<div class="py-10">
            <div :class="kanbanView ? 'max-w-none w-full px-4 sm:px-6 lg:px-8' : 'max-w-7xl mx-auto sm:px-6 lg:px-8'">
                <div class="mb-6">
                    <!-- Filters component emits 'update:filter' to change the active filter -->
                    <Filters v-if="!kanbanView" @update:filter="activeFilter = $event" :active-filter="activeFilter" :search="searchTerm" @update:search="searchTerm = $event" :pending-filter="pendingFilter" @update:pending="pendingFilter = $event" />
                </div>

                <div v-if="!kanbanView" class="flex flex-col lg:flex-row gap-8">
                    <div class="lg:w-2/3">
                        <!-- ProjectCards fetches and paginates from API; pass search and filter -->
                        <ProjectCards :search="searchTerm" :active-filter="activeFilter" :pending-filter="pendingFilter" />
                    </div>
                    <div class="lg:w-1/3">
                        <!-- Sidebar component receives and emits events for its data -->
                        <Sidebar
                            :checklist-items="checklistItems"
                            :notes="notes"
                            @add-checklist-item="handleAddChecklistItem"
                            @remove-checklist-item="handleRemoveChecklistItem"
                            @update-notes="handleUpdateNotes"
                        />
                    </div>
                </div>

                <!-- Kanban view -->
                <div v-else class="mt-4">
<div class="mb-3 flex flex-col gap-3">
    <div class="flex items-center justify-between gap-4">
        <h3 class="text-lg font-semibold text-gray-800">My Tasks (Kanban)</h3>
        <div class="flex items-center gap-2">
            <span v-if="loadingAssignedTasks" class="text-xs text-gray-500">Loading...</span>
            <span v-else-if="tasksError" class="text-xs text-red-600">{{ tasksError }}</span>
<div class="relative">
                <!-- Columns selector dropdown -->
                <button type="button" class="px-3 py-1.5 text-sm border rounded-md bg-white hover:bg-gray-50" @click="showColumnsMenu = !showColumnsMenu">
                    Columns
                </button>
                <div v-if="showColumnsMenu" class="absolute right-0 mt-2 w-56 bg-white border rounded-md shadow-lg z-10 p-3">
                    <div class="space-y-2">
                        <label v-for="col in kanbanColumns" :key="col.key" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="rounded" :value="col.key" v-model="visibleColumns" />
                            <span>{{ col.title }}</span>
                        </label>
                        <div class="text-xs text-gray-500 pt-1">Tip: Archived is optional</div>
                    </div>
                </div>
            </div>
            <button type="button" class="px-3 py-1.5 text-sm border rounded-md bg-white hover:bg-gray-50" @click="showTaskFilters = !showTaskFilters">
                {{ showTaskFilters ? 'Hide Filters' : 'Show Filters' }}
            </button>
            <button
                v-if="showTaskFilters"
                type="button"
                class="px-3 py-1.5 text-sm border rounded-md bg-white hover:bg-gray-50"
                @click="clearAllFilters"
                title="Clear all filters"
            >
                Clear All
            </button>
        </div>
    </div>

    <!-- Filters row -->
    <div v-if="showTaskFilters" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-7 gap-3">
        <!-- Search -->
        <div class="relative">
            <input
                type="text"
                v-model="searchText"
                placeholder="Search tasks..."
                class="w-full pr-16 px-3 py-2 rounded-md border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <div class="absolute inset-y-0 right-2 flex items-center gap-1">
                <button type="button" class="text-xs text-gray-600 hover:text-gray-900" @click="clearSearch" v-if="searchText">Clear</button>
            </div>
        </div>
        <!-- Project -->
        <div class="flex items-center gap-2">
            <div class="flex-1">
                <SelectDropdown
                    v-model="projectId"
                    :options="projectOptions"
                    placeholder="Filter by project"
                />
            </div>
            <button type="button" class="text-xs text-gray-600 hover:text-gray-900" @click="clearProject" v-if="projectId">Clear</button>
        </div>
        <!-- Assignee -->
        <div class="flex items-center gap-2">
            <div class="flex-1">
                <SelectDropdown
                    v-model="assigneeId"
                    :options="usersOptions"
                    placeholder="Filter by assignee"
                />
            </div>
            <button type="button" class="text-xs text-gray-600 hover:text-gray-900" @click="clearAssignee" v-if="assigneeId">Clear</button>
        </div>
        <!-- Priority -->
        <div class="flex items-center gap-2">
            <div class="flex-1">
                <SelectDropdown
                    v-model="priority"
                    :options="priorityOptions"
                    placeholder="Priority"
                />
            </div>
            <button type="button" class="text-xs text-gray-600 hover:text-gray-900" @click="clearPriority" v-if="priority">Clear</button>
        </div>
        <!-- Milestone (only when project selected) -->
        <div v-if="projectId" class="flex items-center gap-2">
            <div class="flex-1">
                <SelectDropdown
                    v-model="milestoneId"
                    :options="milestoneOptions"
                    placeholder="Filter by milestone"
                />
            </div>
            <button type="button" class="text-xs text-gray-600 hover:text-gray-900" @click="clearMilestone" v-if="milestoneId">Clear</button>
        </div>
        <!-- Due filter -->
        <div class="flex items-center gap-2">
            <div class="flex-1">
                <SelectDropdown
                    v-model="dueFilter"
                    :options="[
                        { value: 'all', label: 'All Due' },
                        { value: 'today', label: 'Due Today' },
                        { value: 'overdue', label: 'Overdue' },
                        { value: 'week', label: 'Due This Week' },
                    ]"
                    placeholder="Due filter"
                />
            </div>
            <button type="button" class="text-xs text-gray-600 hover:text-gray-900" @click="clearDueFilter" v-if="dueFilter !== 'all'">Clear</button>
        </div>

        <!-- Completed filter -->
        <div class="flex items-center gap-2">
            <div class="flex-1">
                <SelectDropdown
                    v-model="completedFilter"
                    :options="[
                        { value: 'all', label: 'All Completed' },
                        { value: 'completed_today', label: 'Completed Today' },
                        { value: 'completed_yesterday', label: 'Completed Yesterday' },
                        { value: 'completed_this_week', label: 'Completed This Week' },
                        { value: 'completed_last_week', label: 'Completed Last Week' },
                        { value: 'completed_this_month', label: 'Completed This Month' },
                        { value: 'completed_last_month', label: 'Completed Last Month' },
                        { value: 'completed_last_7', label: 'Completed in Last 7 Days' },
                        { value: 'completed_last_30', label: 'Completed in Last 30 Days' },
                    ]"
                    placeholder="Completed filter"
                />
            </div>
            <button type="button" class="text-xs text-gray-600 hover:text-gray-900" @click="clearCompletedFilter" v-if="completedFilter !== 'all'">Clear</button>
        </div>
    </div>
</div>

<KanbanBoard
    :columns="visibleKanbanColumns"
    :items-by-column="itemsByColumn"
    :loading="loadingAssignedTasks"
    @drop="handleKanbanDrop"
>
    <template #item="{ item, columnKey }">
        <div
            class="relative p-3 rounded-md bg-white border border-gray-200 shadow-sm cursor-move border-l-4"
            :class="[
                (String(item.priority || '').toLowerCase() === 'high') ? 'border-l-red-500' : (String(item.priority || '').toLowerCase() === 'medium') ? 'border-l-yellow-500' : (String(item.priority || '').toLowerCase() === 'low') ? 'border-l-green-500' : 'border-l-gray-200',
                (item.due_date && new Date(item.due_date) < new Date(new Date().setHours(0,0,0,0)) && item.status !== 'Done') ? 'ring-1 ring-red-300' : ''
            ]"
            draggable="true"
            @dragstart="(e) => e.dataTransfer.setData('text/plain', JSON.stringify(item))"
            @click.stop="openTaskDetailSidebar(item.id, item.milestone?.project_id)"
            :title="item.milestone?.name ? `${item.name} — ${item.milestone.name}` : item.name"
        >
            <!-- Overdue hazard icon -->
            <div v-if="item.due_date && new Date(item.due_date) < new Date(new Date().setHours(0,0,0,0)) && item.status !== 'Done'" class="absolute top-1 right-1 text-red-500" title="Overdue">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.487 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11.75 13a.75.75 0 01-1.5 0v-2.25a.75.75 0 011.5 0V13zm-1.5-7.5a.75.75 0 011.5 0v1.5a.75.75 0 01-1.5 0V5.5z" clip-rule="evenodd"/></svg>
            </div>

            <div class="text-sm font-medium text-gray-800 truncate">{{ item.name || item.title || 'Task' }}</div>
            <div class="text-xs text-gray-500 truncate" v-if="item.milestone?.name">{{ item.milestone.name }}</div>
            <div class="text-xs text-gray-500 truncate">{{ item.project?.name || item.milestone?.project?.name }}</div>
            <div class="text-xs text-gray-500 truncate" v-if="!assigneeId || assigneeId === '__all__'">{{ item.assigned_to?.name }}</div>
        </div>
    </template>
</KanbanBoard>

<!-- Block reason modal for workspace-level blocking -->
<BlockReasonModal
    :show="showBlockReason"
    title="Block Task"
    confirm-text="Block Task"
    placeholder="Enter reason for blocking..."
    @close="showBlockReason = false"
    @confirm="confirmWorkspaceBlock"
/>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
