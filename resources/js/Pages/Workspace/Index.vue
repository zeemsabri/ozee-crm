<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { fetchCurrencyRates, displayCurrency } from '@/Utils/currency';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Filters from '@/Pages/Workspace/components/Filters.vue';
import ProjectCards from '@/Pages/Workspace/components/ProjectCards.vue';
import Sidebar from '@/Pages/Workspace/components/Sidebar.vue';
import KanbanBoard from '@/Components/KanbanBoard.vue';
import CreateTaskModal from '@/Components/ProjectTasks/CreateTaskModal.vue';
import WorkspaceBulkTaskModal from '@/Components/WorkspaceBulkTaskModal.vue';
import BlockReasonModal from '@/Components/BlockReasonModal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import DailyWorkLog from '@/Pages/Workspace/components/DailyWorkLog.vue';
import { usePermissions, usePermissionStore } from '@/Directives/permissions.js';
import * as taskState from '@/Utils/taskState.js';
import { openTaskDetailSidebar } from '@/Utils/sidebar';

// Active view: 'projects' | 'kanban' | 'daily'
const activeView = ref(localStorage.getItem('workspace_view') || 'projects');
watch(activeView, (v) => { try { localStorage.setItem('workspace_view', v); } catch (_) {} });
// Legacy kanbanView boolean – keep in sync so existing logic still works
const kanbanView = ref(activeView.value === 'kanban');
watch(kanbanView, (on) => {
    if (on) {
        activeView.value = 'kanban';
    } else if (activeView.value === 'kanban') {
        activeView.value = 'projects';
    }
});
watch(activeView, (v) => { kanbanView.value = v === 'kanban'; });

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

// (kanbanView is now derived from activeView above – legacy localStorage key kept for compat)
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
const activeTaskStatuses = ['To Do', 'In Progress', 'Paused', 'Blocked'];
const recentDoneFetchLimit = 25;

// Assigned tasks for Kanban
const assignedTasks = ref([]);
const recentDoneTasks = ref([]);
const tasksInDailyLog = ref(new Set());
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

const dueFilterOptions = [
    { value: 'all', label: 'All Due' },
    { value: 'today', label: 'Due Today' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'week', label: 'Due This Week' },
];

const completedFilterOptions = [
    { value: 'all', label: 'All Completed' },
    { value: 'completed_today', label: 'Completed Today' },
    { value: 'completed_yesterday', label: 'Completed Yesterday' },
    { value: 'completed_this_week', label: 'Completed This Week' },
    { value: 'completed_last_week', label: 'Completed Last Week' },
    { value: 'completed_this_month', label: 'Completed This Month' },
    { value: 'completed_last_month', label: 'Completed Last Month' },
    { value: 'completed_last_7', label: 'Completed in Last 7 Days' },
    { value: 'completed_last_30', label: 'Completed in Last 30 Days' },
];

const showAdvancedFilters = ref(false);

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

const combinedKanbanTasks = computed(() => {
    const merged = [...assignedTasks.value, ...recentDoneTasks.value];
    const seen = new Set();

    return merged.filter((task) => {
        if (seen.has(task.id)) return false;
        seen.add(task.id);

        return true;
    });
});

const activeFilterChips = computed(() => {
    const chips = [];
    const assigneeLabel = usersOptions.value.find(option => String(option.value) === String(assigneeId.value))?.label;
    const projectLabel = projectOptions.value.find(option => String(option.value) === String(projectId.value))?.label;
    const milestoneLabel = milestoneOptions.value.find(option => String(option.value) === String(milestoneId.value))?.label;

    if (searchText.value) chips.push({ key: 'search', label: `Search: ${searchText.value}`, clear: clearSearch });
    if (projectId.value && projectLabel) chips.push({ key: 'project', label: `Project: ${projectLabel}`, clear: clearProject });
    if (assigneeId.value && assigneeLabel) chips.push({ key: 'assignee', label: `Assignee: ${assigneeLabel}`, clear: clearAssignee });
    if (priority.value) chips.push({ key: 'priority', label: `Priority: ${priority.value}`, clear: clearPriority });
    if (milestoneId.value && milestoneLabel) chips.push({ key: 'milestone', label: `Milestone: ${milestoneLabel}`, clear: clearMilestone });
    if (dueFilter.value !== 'all') chips.push({ key: 'due', label: `Due: ${dueFilter.value}`, clear: clearDueFilter });
    if (completedFilter.value !== 'all') chips.push({ key: 'completed', label: `Completed: ${completedFilter.value}`, clear: clearCompletedFilter });

    return chips;
});

const advancedFilterCount = computed(() => {
    let count = 0;
    if (priority.value) count++;
    if (milestoneId.value) count++;
    if (dueFilter.value !== 'all') count++;
    if (completedFilter.value !== 'all') count++;

    return count;
});

const hasActiveFilters = computed(() => activeFilterChips.value.length > 0);

const visibleActiveTaskCount = computed(() => activeTaskStatuses.reduce((count, status) => count + (itemsByColumn.value[status]?.length ?? 0), 0));
const visibleDoneTaskCount = computed(() => itemsByColumn.value.Done?.length ?? 0);
const recentDoneSummary = computed(() => {
    if (!recentDoneTasks.value.length) return 'No recent completed tasks';
    if (recentDoneTasks.value.length > 10) return `Showing latest 10 of ${recentDoneTasks.value.length} recent completed tasks`;

    return `Showing ${recentDoneTasks.value.length} recent completed task${recentDoneTasks.value.length === 1 ? '' : 's'}`;
});

const activePreset = ref('my-active');

const setKanbanPreset = (preset) => {
    activePreset.value = preset;

    if (preset === 'my-active') {
        assigneeId.value = null;
        dueFilter.value = 'all';
        completedFilter.value = 'all';
        return;
    }

    if (preset === 'team-active') {
        assigneeId.value = '__all__';
        dueFilter.value = 'all';
        completedFilter.value = 'all';
        return;
    }

    if (preset === 'overdue') {
        dueFilter.value = 'overdue';
        completedFilter.value = 'all';
        return;
    }

    if (preset === 'recent-done') {
        dueFilter.value = 'all';
        completedFilter.value = 'completed_last_7';
    }
};

const buildTaskScopeParams = (statuses, extra = {}) => {
    const params = {
        ...extra,
        statuses: statuses.join(','),
    };

    if (projectId.value) params.project_id = projectId.value;
    if (searchText.value && !params.search) params.search = searchText.value;

    const currentUserId = usePage().props.auth?.user?.id;

    if (assigneeId.value === '__all__') {
        if (!canDo('view_all_user').value && !projectId.value) {
            const accessibleProjectIds = projectOptions.value
                .map(option => option.value)
                .filter(value => value !== null);

            if (accessibleProjectIds.length > 0) {
                params.project_ids = accessibleProjectIds.join(',');
            }
        }

        return params;
    }

    if (assigneeId.value) {
        params.assigned_to_user_id = assigneeId.value;
        return params;
    }

    if (currentUserId) {
        params.assigned_to_user_id = currentUserId;
    }

    return params;
};

const applyCompletedWindowToParams = (params) => {
    switch (completedFilter.value) {
        case 'completed_today':
            params.completed_on = new Date().toISOString().slice(0, 10);
            break;
        case 'completed_yesterday':
            params.completed_since = yesterday.toISOString().slice(0, 10);
            params.completed_until = yesterdayEnd.toISOString().slice(0, 10);
            break;
        case 'completed_this_week':
            params.completed_since = startOfWeek.toISOString().slice(0, 10);
            params.completed_until = endOfWeek.toISOString().slice(0, 10);
            break;
        case 'completed_last_week':
            params.completed_since = lastWeekStart.toISOString().slice(0, 10);
            params.completed_until = lastWeekEnd.toISOString().slice(0, 10);
            break;
        case 'completed_this_month':
            params.completed_since = startOfMonth.toISOString().slice(0, 10);
            params.completed_until = endOfMonth.toISOString().slice(0, 10);
            break;
        case 'completed_last_month':
            params.completed_since = lastMonthStart.toISOString().slice(0, 10);
            params.completed_until = lastMonthEnd.toISOString().slice(0, 10);
            break;
        case 'completed_last_7':
            params.completed_since = last7Start.toISOString().slice(0, 10);
            break;
        case 'completed_last_30':
            params.completed_since = last30Start.toISOString().slice(0, 10);
            break;
        default:
            params.completed_since = last30Start.toISOString().slice(0, 10);
            break;
    }

    return params;
};

const fetchTaskPage = async (params) => {
    const { data } = await window.axios.get('/api/tasks', { params });
    const list = Array.isArray(data) ? data : (Array.isArray(data?.data) ? data.data : []);

    return {
        list,
        currentPage: Array.isArray(data) ? 1 : (data.current_page || 1),
        lastPage: Array.isArray(data) ? 1 : (data.last_page || 1),
    };
};

const mergeTasksById = (existingTasks, incomingTasks) => {
    const merged = [...existingTasks];
    const existingIds = new Set(existingTasks.map(task => String(task.id)));

    for (const task of incomingTasks) {
        if (!existingIds.has(String(task.id))) {
            merged.push(task);
        }
    }

    return merged;
};

const matchesSearchQuery = (task) => {
    const q = (searchText.value || '').toLowerCase().trim();
    if (!q) return true;

    return String(task.name || task.title || '').toLowerCase().includes(q)
        || String(task.milestone?.name || '').toLowerCase().includes(q)
        || String(task.project?.name || task.milestone?.project?.name || '').toLowerCase().includes(q)
        || String(task.description || '').toLowerCase().includes(q);
};

const matchesBoardScope = (task, statusGroup = 'active') => {
    const status = String(task.status || '');
    if (statusGroup === 'active' && !activeTaskStatuses.includes(status)) return false;
    if (statusGroup === 'done' && status !== 'Done') return false;
    if (!matchesSearchQuery(task)) return false;

    const pid = task.project?.id ?? task.milestone?.project_id ?? task.milestone?.project?.id ?? null;
    if (projectId.value && String(pid) !== String(projectId.value)) return false;

    const uid = task.assigned_to_user_id ?? task.assigned_to?.id ?? task.assigned_to_id ?? null;
    const currentUserId = usePage().props.auth?.user?.id;

    if (assigneeId.value === '__all__') {
        if (!canDo('view_all_user').value && !projectId.value) {
            const accessibleProjectIds = projectOptions.value
                .map(option => option.value)
                .filter(value => value !== null)
                .map(String);

            if (pid && !accessibleProjectIds.includes(String(pid))) return false;
        }

        return true;
    }

    if (assigneeId.value) return String(uid) === String(assigneeId.value);
    if (currentUserId) return String(uid) === String(currentUserId);

    return true;
};

const reconcileTaskInBoard = (updatedTask) => {
    if (!updatedTask?.id) return;

    assignedTasks.value = assignedTasks.value.filter(task => String(task.id) !== String(updatedTask.id));
    recentDoneTasks.value = recentDoneTasks.value.filter(task => String(task.id) !== String(updatedTask.id));

    if (matchesBoardScope(updatedTask, 'done')) {
        recentDoneTasks.value = [updatedTask, ...recentDoneTasks.value].slice(0, recentDoneFetchLimit);
        return;
    }

    if (matchesBoardScope(updatedTask, 'active')) {
        assignedTasks.value = [updatedTask, ...assignedTasks.value];
    }
};

const removeTaskFromBoard = (taskId) => {
    assignedTasks.value = assignedTasks.value.filter(task => String(task.id) !== String(taskId));
    recentDoneTasks.value = recentDoneTasks.value.filter(task => String(task.id) !== String(taskId));
};

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
    await fetchTodayDailyLogTasks();
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

// Re-fetch the board when project scope changes
    if (kanbanView.value) {
        kanbanPage.value = 1;
        await fetchKanbanWithCurrentFilters();
    }
});

const fetchTodayDailyLogTasks = async () => {
    try {
        // Remove explicit date so server defaults to user's timezone 'today'
        const { data } = await window.axios.get('/api/daily-tasks');
        tasksInDailyLog.value = new Set(data.map(d => d.task_id));
    } catch (e) {
        console.error('Failed to fetch today daily log tasks', e);
    }
};

const addTaskToDailyLog = async (task) => {
    try {
        // Remove explicit date so server defaults to user's timezone 'today'
        await window.axios.post('/api/daily-tasks', { task_ids: [task.id] });
        tasksInDailyLog.value.add(task.id);
        // Toast notification if available, or just visual update is enough per plan
    } catch (e) {
        console.error('Failed to add task to daily log', e);
    }
};

const kanbanPage = ref(1);
const kanbanLastPage = ref(1);

const fetchActiveTasks = async (append = false) => {
    const params = buildTaskScopeParams(activeTaskStatuses, {
        per_page: 100,
        page: kanbanPage.value,
    });

    const result = await fetchTaskPage(params);

    assignedTasks.value = append
        ? mergeTasksById(assignedTasks.value, result.list)
        : result.list;

    kanbanPage.value = result.currentPage;
    kanbanLastPage.value = result.lastPage;
};

const fetchRecentDoneTasks = async () => {
    const params = applyCompletedWindowToParams(buildTaskScopeParams(['Done'], {
        per_page: recentDoneFetchLimit,
        page: 1,
    }));

    const result = await fetchTaskPage(params);
    recentDoneTasks.value = result.list;
};

watch(kanbanView, (on) => {
    setTimeout(() => {
        if (on && assignedTasks.value.length === 0 && !loadingAssignedTasks.value) {
            fetchKanbanWithCurrentFilters();
        }
    }, 500)

}, { immediate: true });

// Trigger backend re-fetch if certain filters change
const fetchKanbanWithCurrentFilters = async (append = false) => {
    if (!kanbanView.value) return;
    loadingAssignedTasks.value = true;
    tasksError.value = '';

    try {
        await fetchActiveTasks(append);

        if (!append) {
            await fetchRecentDoneTasks();
        }
    } catch (e) {
        console.error('Failed to load tasks', e);
        tasksError.value = 'Failed to load tasks';

        if (!append) {
            assignedTasks.value = [];
            recentDoneTasks.value = [];
        }
    } finally {
        loadingAssignedTasks.value = false;
    }
};

// Re-fetch tasks when assignee selection changes
watch(assigneeId, async () => {
    kanbanPage.value = 1;
    await fetchKanbanWithCurrentFilters();
});

watch(completedFilter, async () => {
    if (!kanbanView.value) return;
    kanbanPage.value = 1;
    await fetchKanbanWithCurrentFilters();
});

// Load more
const loadMoreKanbanTasks = async () => {
    if (kanbanPage.value >= kanbanLastPage.value) return;
    kanbanPage.value++;
    await fetchKanbanWithCurrentFilters(true);
};

let searchTimeout = null;
watch(searchText, (v) => {
    if (!kanbanView.value) return;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        kanbanPage.value = 1;
        await fetchKanbanWithCurrentFilters();
    }, 400); // 400ms debounce
});

const filteredAssignedTasks = computed(() => {
    // Only apply text search frontend filtering if not using backend search
    // Since we now use backend search, just rely on it primarily.
    // We can keep the local filter for items already fetched to make it responsive immediately.
    const q = (searchText.value || '').toLowerCase().trim();
    const result = combinedKanbanTasks.value.filter(t => {
        // Search by name/milestone/project
        // local fallback just in case
        const matchesSearch = !q ||
            String(t.name || t.title || '').toLowerCase().includes(q) ||
            String(t.milestone?.name || '').toLowerCase().includes(q) ||
            String(t.project?.name || '').toLowerCase().includes(q) ||
            String(t.description || '').toLowerCase().includes(q);

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

// Task creation state
const showCreateTaskModal = ref(false);
const showBulkTaskModal = ref(false);

const openCreateTaskModal = () => {
    showCreateTaskModal.value = true;
};

const openBulkTaskModal = () => {
    showBulkTaskModal.value = true;
    showCreateTaskModal.value = false;
};

const handleSwitchToBulk = () => {
    showCreateTaskModal.value = false;
    showBulkTaskModal.value = true;
};

const handleTaskSaved = () => {
    kanbanPage.value = 1;
    fetchKanbanWithCurrentFilters();
};

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
        reconcileTaskInBoard(updated);
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

const handleExternalTaskUpdated = (event) => {
    reconcileTaskInBoard(event.detail);
};

const handleExternalTaskDeleted = (event) => {
    removeTaskFromBoard(event.detail?.taskId);
};

onMounted(() => {
    window.addEventListener('workspace-task-updated', handleExternalTaskUpdated);
    window.addEventListener('workspace-task-deleted', handleExternalTaskDeleted);
});

onUnmounted(() => {
    window.removeEventListener('workspace-task-updated', handleExternalTaskUpdated);
    window.removeEventListener('workspace-task-deleted', handleExternalTaskDeleted);
});

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
                 // Default to simple update if no specific transition action defined
                 updated = (await window.axios.put(`/api/tasks/${data.id}`, { status: to })).data;
        }
        reconcileTaskInBoard(updated);
    } catch (e) {
        console.error('Failed to update task status', e);
        // Roll back
        updateTaskInList(prev);
    }
};

const handleKanbanAddTask = async ({ columnKey, taskName }) => {
    try {
        const response = await window.axios.post('/api/tasks/quick', {
            name: taskName,
            status: columnKey,
            project_id: projectId.value
        });
        
        if (response.data && response.data.id) {
            reconcileTaskInBoard(response.data);
        }
    } catch (e) {
        console.error('Failed to quick add task', e);
        if (e.response && e.response.data && e.response.data.message) {
            alert(`Error: ${e.response.data.message}`);
        } else {
            alert('Failed to quickly add task.');
        }
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
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Workspace</h2>
                    <!-- view switcher -->
                    <div class="flex items-center rounded-lg border border-gray-200 overflow-hidden shadow-sm">
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm transition font-medium"
                            :class="activeView === 'projects' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            @click="activeView = 'projects'"
                        >Projects</button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm transition font-medium border-l border-r border-gray-200"
                            :class="activeView === 'kanban' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            @click="activeView = 'kanban'"
                        >Kanban</button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm transition font-medium"
                            :class="activeView === 'daily' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            @click="activeView = 'daily'"
                        >📋 Daily Log</button>
                    </div>
                </div>
            </div>
        </template>

<div class="py-10">
            <div :class="activeView === 'kanban' ? 'max-w-none w-full px-4 sm:px-6 lg:px-8' : 'max-w-7xl mx-auto sm:px-6 lg:px-8'">

                <!-- ── Daily Work Log view ── -->
                <div v-if="activeView === 'daily'" class="max-w-3xl mx-auto">
                    <DailyWorkLog />
                </div>

                <template v-else>
                <div class="mb-6">
                    <!-- Filters component emits 'update:filter' to change the active filter -->
                    <Filters v-if="activeView === 'projects'" @update:filter="activeFilter = $event" :active-filter="activeFilter" :search="searchTerm" @update:search="searchTerm = $event" :pending-filter="pendingFilter" @update:pending="pendingFilter = $event" />
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
<div class="mb-4 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M7 12h10M10 18h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold tracking-tight text-slate-900">Task Board</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ visibleActiveTaskCount }} active tasks
                            <span class="mx-2 text-slate-300">•</span>
                            {{ recentDoneSummary }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                <span v-if="loadingAssignedTasks" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500">Syncing board...</span>
                <span v-else-if="tasksError" class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-600">{{ tasksError }}</span>

                <div class="relative">
                    <button type="button" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50" @click="showColumnsMenu = !showColumnsMenu">
                        Columns
                    </button>
                    <div v-if="showColumnsMenu" class="absolute right-0 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl z-10">
                        <div class="space-y-2">
                            <label v-for="col in kanbanColumns" :key="col.key" class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" class="rounded border-slate-300" :value="col.key" v-model="visibleColumns" />
                                <span>{{ col.title }}</span>
                            </label>
                            <div class="pt-1 text-xs text-slate-400">Archived stays optional by default.</div>
                        </div>
                    </div>
                </div>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-100"
                    @click="clearAllFilters"
                    title="Clear all filters"
                >
                    Reset
                </button>

                <button type="button" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700" @click="openCreateTaskModal">
                    Add Task
                </button>
                <button type="button" class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100" @click="openBulkTaskModal">
                    Bulk Tasks
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="inline-flex w-full flex-wrap items-center gap-1 rounded-2xl bg-slate-100 p-1 xl:w-auto">
                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-sm font-medium transition"
                    :class="activePreset === 'my-active' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    @click="setKanbanPreset('my-active')"
                >My Active Work</button>
                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-sm font-medium transition"
                    :class="activePreset === 'team-active' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    @click="setKanbanPreset('team-active')"
                >Team Board</button>
                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-sm font-medium transition"
                    :class="activePreset === 'overdue' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    @click="setKanbanPreset('overdue')"
                >Overdue</button>
                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-sm font-medium transition"
                    :class="activePreset === 'recent-done' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    @click="setKanbanPreset('recent-done')"
                >Recent Done</button>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                <span class="rounded-full bg-slate-100 px-3 py-1.5">{{ visibleKanbanColumns.length }} columns visible</span>
                <span class="rounded-full bg-slate-100 px-3 py-1.5">{{ visibleDoneTaskCount }} done shown</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(260px,1.8fr)_minmax(220px,1fr)_minmax(220px,1fr)_auto]">
            <div class="relative">
                <input
                    type="text"
                    v-model="searchText"
                    placeholder="Search tasks, milestones, or projects"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 pr-16 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-50"
                />
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-500 hover:text-slate-700" @click="clearSearch" v-if="searchText">Clear</button>
            </div>

            <div>
                <SelectDropdown
                    v-model="projectId"
                    :options="projectOptions"
                    placeholder="Project"
                />
            </div>

            <div>
                <SelectDropdown
                    v-model="assigneeId"
                    :options="usersOptions"
                    placeholder="Assignee"
                />
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                @click="showAdvancedFilters = !showAdvancedFilters"
            >
                <span>Advanced Filters</span>
                <span v-if="advancedFilterCount" class="rounded-full bg-slate-900 px-2 py-0.5 text-[11px] font-semibold text-white">{{ advancedFilterCount }}</span>
            </button>
        </div>

        <div v-if="showAdvancedFilters" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <div class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Priority</div>
                    <SelectDropdown
                        v-model="priority"
                        :options="priorityOptions"
                        placeholder="Priority"
                    />
                </div>

                <div v-if="projectId">
                    <div class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Milestone</div>
                    <SelectDropdown
                        v-model="milestoneId"
                        :options="milestoneOptions"
                        placeholder="Milestone"
                    />
                </div>

                <div>
                    <div class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Due Window</div>
                    <SelectDropdown
                        v-model="dueFilter"
                        :options="dueFilterOptions"
                        placeholder="Due filter"
                    />
                </div>

                <div>
                    <div class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Completed</div>
                    <SelectDropdown
                        v-model="completedFilter"
                        :options="completedFilterOptions"
                        placeholder="Completed filter"
                    />
                </div>
            </div>
        </div>

        <div v-if="activeFilterChips.length" class="flex flex-wrap gap-2">
            <button
                v-for="chip in activeFilterChips"
                :key="chip.key"
                type="button"
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:bg-white"
                @click="chip.clear()"
            >
                <span>{{ chip.label }}</span>
                <span class="text-slate-400">×</span>
            </button>
        </div>
    </div>
</div>

<KanbanBoard
    :columns="visibleKanbanColumns"
    :items-by-column="itemsByColumn"
    :loading="loadingAssignedTasks"
    :is-add-task-disabled="!projectId"
    add-task-disabled-tooltip="Action Required: Please select a Project from the filters above before adding a card."
    @drop="handleKanbanDrop"
    @add-task="handleKanbanAddTask"
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

            <div class="flex items-start gap-1">
                <div class="text-sm font-medium text-gray-800 truncate flex-1">
                    <span class="text-indigo-600 font-bold mr-1">#{{ item.task_number }}</span>
                    {{ item.name || item.title || 'Task' }}
                </div>
                <div v-if="item.source && item.source !== 'local'" class="text-indigo-500 mt-0.5 flex-shrink-0" :title="`External Task: ${item.source}`">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </div>
            </div>
            <div class="text-xs text-gray-500 truncate" v-if="item.milestone?.name">{{ item.milestone.name }}</div>
            <div class="text-xs text-gray-500 truncate">{{ item.project?.name || item.milestone?.project?.name }}</div>
            <div class="text-xs text-gray-500 truncate" v-if="!assigneeId || assigneeId === '__all__'">{{ item.assigned_to?.name }}</div>

            <!-- Add to Daily Log Action -->
            <div class="mt-2 pt-2 border-t border-gray-100 flex justify-end">
                <button
                    v-if="!tasksInDailyLog.has(item.id) && item.status !== 'Done' && item.status !== 'Archived'"
                    type="button"
                    @click.stop="addTaskToDailyLog(item)"
                    class="flex items-center gap-1 text-[10px] font-medium text-indigo-600 hover:text-indigo-800 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Daily Log
                </button>
                <div v-else-if="tasksInDailyLog.has(item.id)" class="flex items-center gap-1 text-[10px] font-medium text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    In Log
                </div>
            </div>
        </div>
    </template>
</KanbanBoard>

<CreateTaskModal
    :show="showCreateTaskModal"
    :project-id="projectId"
    @close="showCreateTaskModal = false"
    @saved="handleTaskSaved"
    @switch-to-bulk="handleSwitchToBulk"
/>

<WorkspaceBulkTaskModal
    :show="showBulkTaskModal"
    :project-id="projectId"
    @close="showBulkTaskModal = false"
    @tasks-submitted="handleTaskSaved"
/>

<!-- Block reason modal for workspace-level blocking -->
<BlockReasonModal
    :show="showBlockReason"
    title="Block Task"
    confirm-text="Block Task"
    placeholder="Enter reason for blocking..."
    @close="showBlockReason = false"
    @confirm="confirmWorkspaceBlock"
/>

<div v-if="kanbanPage < kanbanLastPage" class="mt-6 flex justify-center">
    <button 
        @click="loadMoreKanbanTasks" 
        :disabled="loadingAssignedTasks"
        class="px-5 py-2.5 rounded-md border border-gray-300 bg-white text-gray-700 font-medium hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-all"
    >
        {{ loadingAssignedTasks ? 'Loading...' : 'Load More Tasks' }}
    </button>
</div>
                </div>
                </template><!-- end v-else (not daily) -->
            </div>
        </div>
    </AuthenticatedLayout>
</template>
