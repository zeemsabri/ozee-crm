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

// Assigned tasks for Kanban
const assignedTasks = ref([]);
const loadingAssignedTasks = ref(false);
const tasksError = ref('');

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

watch(kanbanView, (on) => {
    setTimeout(() => {
        if (on && assignedTasks.value.length === 0 && !loadingAssignedTasks.value) {
            fetchAssignedTasksAll();
        }
    }, 500)

}, { immediate: true });

const itemsByColumn = computed(() => {
    const init = {
        'To Do': [],
        'In Progress': [],
        'Paused': [],
        'Blocked': [],
        'Done': [],
        'Archived': [],
    };
    for (const t of assignedTasks.value) {
        const key = t.status && init[t.status] !== undefined ? t.status : 'To Do';
        init[key].push(t);
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
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">My Tasks (Kanban)</h3>
                        <span v-if="loadingAssignedTasks" class="text-xs text-gray-500">Loading...</span>
                        <span v-else-if="tasksError" class="text-xs text-red-600">{{ tasksError }}</span>
                    </div>
                    <KanbanBoard
                        :columns="kanbanColumns"
                        :items-by-column="itemsByColumn"
                        :loading="loadingAssignedTasks"
@drop="handleKanbanDrop"
                    >
                        <template #item="{ item, columnKey }">
                            <div
                                class="p-3 rounded-md bg-white border border-gray-200 shadow-sm cursor-move"
                                draggable="true"
                                @dragstart="(e) => e.dataTransfer.setData('text/plain', JSON.stringify(item))"
                                @click.stop="openTaskDetailSidebar(item.id, item.milestone?.project_id)"
                                :title="item.milestone?.name ? `${item.name} — ${item.milestone.name}` : item.name"
                            >
                                <div class="text-sm font-medium text-gray-800 truncate">{{ item.name || item.title || 'Task' }}</div>
                                <div class="text-xs text-gray-500 truncate" v-if="item.milestone?.name">{{ item.milestone.name }}</div>
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
