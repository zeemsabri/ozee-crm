<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { openTaskDetailSidebar } from '@/Utils/sidebar';
import { formatCurrency, convertCurrency, displayCurrency } from '@/Utils/currency';
import { ChatBubbleOvalLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    project: Object,
});

// Local state for active tabs within the card
const activeMainTab = ref('overview');
const activeTaskTab = ref('today');

// Local state to manage the visibility of completed tasks
const showCompleted = ref(false);

// Completed tasks lazy-load state
const completedLoaded = ref(false);
const completedLoading = ref(false);
const completedError = ref(null);
const completedTasks = ref([]);

const completedList = computed(() => completedLoaded.value ? completedTasks.value : (props.project.tasks?.completed || []));
const completedCount = computed(() => Array.isArray(completedList.value) ? completedList.value.length : 0);

const correctChatUrl = computed(() => {
    // Guard against null or empty values
    if (!props.project || !props.project.google_chat_id) {
        return '#'; // Return a safe fallback
    }

    // Replace the incorrect "spaces/" with the correct "space/"
    const correctedPath = props.project.google_chat_id.replace('spaces/', 'space/');

    // Return the full, valid URL
    return `https://mail.google.com/chat/u/0/#chat/${correctedPath}`;
});

function statusChip(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'to do' || s === 'started') {
        return { label: 'To Do', classes: 'px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full' };
    }
    if (s === 'in progress' || s === 'started') {
        return { label: 'Started', classes: 'px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full' };
    }
    if (s === 'blocked') {
        return { label: 'Blocked', classes: 'px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full' };
    }
    if (s === 'paused') {
        return { label: 'Paused', classes: 'px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full' };
    }
    if (s === 'done' || s === 'complete' || s === 'completed') {
        return { label: 'Complete', classes: 'px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full' };
    }
    return null;
}

async function fetchCompleted() {
    if (!props.project || !props.project.id) return;
    completedLoading.value = true;
    completedError.value = null;
    try {
        const { data } = await window.axios.get(`/api/workspace/projects/${props.project.id}/completed-tasks`);
        completedTasks.value = Array.isArray(data) ? data : [];
        completedLoaded.value = true;
    } catch (e) {
        console.error(`Failed to load completed tasks for project ${props.project.id}`, e);
        completedError.value = e?.response?.data?.message || 'Failed to load completed tasks';
    } finally {
        completedLoading.value = false;
    }
}

function onViewCompletedClick() {
    if (!completedLoaded.value && !completedLoading.value) {
        fetchCompleted().then(() => { showCompleted.value = true; });
    } else {
        showCompleted.value = !showCompleted.value;
    }
}

// Expendable budget per-card state
const budget = ref(null);
const budgetLoading = ref(false);
const budgetError = ref(null);

const currentDisplayCurrency = displayCurrency;

const convertedBudget = computed(() => {
    if (!budget.value) return null;
    const fromCur = budget.value.currency || 'AUD';
    const toCur = currentDisplayCurrency.value || 'USD';
    return {
        total_budget: convertCurrency(budget.value.total_budget ?? 0, fromCur, toCur),
        total_assigned_milestone_amount: convertCurrency(budget.value.total_assigned_milestone_amount ?? 0, fromCur, toCur),
        total_pending_contract_amount: convertCurrency(budget.value.total_pending_contract_amount ?? 0, fromCur, toCur),
        total_approved_contract_amount: convertCurrency(budget.value.total_approved_contract_amount ?? 0, fromCur, toCur),
        total_expendable_amount: convertCurrency(budget.value.total_expendable_amount ?? 0, fromCur, toCur),
        available_for_new_milestones: convertCurrency(budget.value.available_for_new_milestones ?? 0, fromCur, toCur),
        currency: toCur,
    };
});

const budgetSummaryText = computed(() => {
    if (!convertedBudget.value) return null;
    // Show "Available" and "Total" in the user's display currency
    const available = formatCurrency(convertedBudget.value.available_for_new_milestones, convertedBudget.value.currency);
    const total = formatCurrency(convertedBudget.value.total_budget, convertedBudget.value.currency);
    return `${available} available of ${total}`;
});

async function loadBudget() {
    // Only fetch budget for Manager cards
    if (!props.project || !props.project.id || props.project.role !== 'Manager') {
        budget.value = null;
        budgetError.value = null;
        budgetLoading.value = false;
        return;
    }
    budgetLoading.value = true;
    budgetError.value = null;
    try {
        const { data } = await window.axios.get(`/api/projects/${props.project.id}/expendable-budget`);
        budget.value = data;
    } catch (e) {
        // Gracefully handle unauthorized or other errors
        console.error(`Failed to load expendable budget for project ${props.project.id}`, e);
        budgetError.value = e?.response?.data?.message || 'Unable to load budget';
        budget.value = null;
    } finally {
        budgetLoading.value = false;
    }
}

watch(() => [props.project?.id, props.project?.role], () => {
    loadBudget();
}, { immediate: true });

function toggleCompleted() {
    showCompleted.value = !showCompleted.value;
}

function viewTask(task) {
    if (!task || !task.id) return;
    openTaskDetailSidebar(task.id, props.project.id);
}

const cardRef = ref(null);

function handleDelegatedClick(e) {
    const link = e.target.closest('a');
    if (!link) return;
    const label = link.getAttribute('aria-label') || '';
    if (!label.startsWith('View details for task:')) return;
    e.preventDefault();
    const name = label.replace('View details for task: ', '').trim();
    const all = [...(props.project.tasks?.today || []), ...(props.project.tasks?.tomorrow || []), ...((completedList && completedList.value) ? completedList.value : (props.project.tasks?.completed || []))];
    const task = all.find(t => t && t.name === name);
    if (task && task.id) {
        openTaskDetailSidebar(task.id, props.project.id);
    }
}

onMounted(() => {
    if (cardRef.value) cardRef.value.addEventListener('click', handleDelegatedClick);
});

onBeforeUnmount(() => {
    if (cardRef.value) cardRef.value.removeEventListener('click', handleDelegatedClick);
});
function daysUntil(dateStr) {
    try {
        if (!dateStr) return null;
        const d = new Date(dateStr);
        const today = new Date();
        const dt = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
        const tt = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 12, 0, 0);
        const msPerDay = 24 * 60 * 60 * 1000;
        return Math.round((dt - tt) / msPerDay);
    } catch (e) { return null; }
}
function daysRemainingText(dateStr) {
    const diff = daysUntil(dateStr);
    if (diff === null) return '';
    if (diff < 0) return `Overdue by ${Math.abs(diff)} day${Math.abs(diff) === 1 ? '' : 's'}`;
    if (diff === 0) return 'Due today';
    return `${diff} day${diff === 1 ? '' : 's'} remaining`;
}
function urgencyColor(dateStr) {
    const diff = daysUntil(dateStr);
    if (diff === null) return 'text-gray-500';
    if (diff < 0) return 'text-red-600';
    if (diff === 0) return 'text-orange-600';
    if (diff <= 3) return 'text-yellow-700';
    return 'text-green-600';
}
</script>

<template>
    <div class="bg-white rounded-xl shadow-md p-6" ref="cardRef">
        <!-- Card Header with Project Health Indicator -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-semibold text-gray-900">{{ project.name }}</h2>
                <!-- Role Tag -->
                <span v-if="project.role === 'Manager'" class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full">Manager</span>
                <span v-else class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Contributor</span>
                <!-- Project Health Indicator -->
                <div
                    :class="{
                        'bg-red-500': project.health === 'at-risk',
                        'bg-yellow-500': project.health === 'needs-attention',
                        'bg-green-500': project.health === 'on-track',
                    }"
                    class="w-3 h-3 rounded-full"
                    :title="`Project health status: ${project.health.replace('-', ' ')}`"
                    :aria-label="`Project health status: ${project.health.replace('-', ' ')}`">
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a
                    v-if="project.google_chat_id"
                    :href="correctChatUrl"  target="_blank"
                    rel="noopener"
                    class="text-gray-500 hover:text-indigo-600"
                    title="Open Google Chat"
                    aria-label="Open Google Chat"
                >
                    <ChatBubbleOvalLeftIcon class="h-5 w-5" />
                </a>
                <Link :href="route('projects.show', project.id)" class="text-indigo-600 text-sm font-medium hover:underline">View Project</Link>
            </div>
        </div>

        <!-- Priority Alert Section -->
        <div v-if="project.alert" class="flex items-start gap-4 p-4 mb-6 border-l-4 border-red-500 bg-red-50 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/><path d="M11 12H9V8h2v4zM11 16H9v-2h2v2z"/>
            </svg>
            <div class="flex-grow">
                <p class="font-medium text-sm text-red-800">{{ project.alert.text }}</p>
                <div class="flex justify-between items-center mt-2">
                    <span class="text-xs font-semibold text-red-600">{{ project.alert.timer }} remaining</span>
                    <span v-if="project.alert.incentive" class="text-xs text-red-600">{{ project.alert.incentive }}</span>
                </div>
            </div>
        </div>

        <!-- Manager View (with Tabs) -->
        <div v-if="project.role === 'Manager'" class="tabs">
            <div class="flex border-b border-gray-200">
                <button
                    :class="{'active text-gray-900 border-b-2 border-indigo-600': activeMainTab === 'overview', 'text-gray-500 hover:text-gray-900': activeMainTab !== 'overview'}"
                    class="tab-button flex-1 py-2 text-sm font-medium focus:outline-none transition-all-colors"
                    @click="activeMainTab = 'overview'">
                    Overview
                </button>
                <button
                    :class="{'active text-gray-900 border-b-2 border-indigo-600': activeMainTab === 'tasks', 'text-gray-500 hover:text-gray-900': activeMainTab !== 'tasks'}"
                    class="tab-button flex-1 py-2 text-sm font-medium focus:outline-none transition-all-colors"
                    @click="activeMainTab = 'tasks'">
                    Team's Tasks
                </button>
                <button
                    :class="{'active text-gray-900 border-b-2 border-indigo-600': activeMainTab === 'communication', 'text-gray-500 hover:text-gray-900': activeMainTab !== 'communication'}"
                    class="tab-button flex-1 py-2 text-sm font-medium focus:outline-none transition-all-colors"
                    @click="activeMainTab = 'communication'">
                    Communication
                </button>
            </div>
            <!-- Overview Tab Content -->
            <div v-if="activeMainTab === 'overview'" class="tab-content pt-4">
                <div class="space-y-3 text-sm text-gray-700">
                    <p class="flex justify-between items-center">
                        <span class="font-medium">Milestone:</span>
                        <span>{{ project.overview.milestone }}</span>
                    </p>
                    <p class="flex justify-between items-center">
                        <span class="font-medium">Budget:</span>
                        <span>
                            <span v-if="budgetLoading" class="text-gray-500">Loading...</span>
                            <span v-else-if="budgetSummaryText">{{ budgetSummaryText }}</span>
                            <span v-else>{{ project.overview.budget }}</span>
                        </span>
                    </p>
                    <p class="flex justify-between items-center">
                        <span class="font-medium">Project Status:</span>
                        <span class="px-2 py-0.5 text-xs font-medium text-green-800 bg-green-200 rounded-full">{{ project.overview.status }}</span>
                    </p>
                    <div class="mt-3">
                        <h5 class="text-sm font-semibold text-gray-600 mb-1">Latest Context</h5>
                        <p class="text-sm text-gray-700" v-if="project.overview.latestContext">{{ project.overview.latestContext }}</p>
                        <p class="text-sm text-gray-400" v-else>—</p>
                    </div>
                </div>
            </div>
            <!-- Team's Tasks Tab Content -->
            <div v-else-if="activeMainTab === 'tasks'" class="tab-content pt-4">
                <div>
                    <h4 class="text-base font-semibold text-gray-900 mb-2">Team Tasks</h4>
                    <div class="flex space-x-2 border-b border-gray-200 mb-3">
                        <button class="task-tab-button flex-1 text-sm font-medium py-1 transition-all-colors"
                                :class="{'text-gray-900 border-b-2 border-indigo-600': activeTaskTab === 'today', 'text-gray-500 hover:text-gray-900': activeTaskTab !== 'today'}"
                                @click="activeTaskTab = 'today'">
                            Today <span class="ml-1 px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ project.tasks.today.length }}</span>
                        </button>
                        <button class="task-tab-button flex-1 text-sm font-medium py-1 transition-all-colors"
                                :class="{'text-gray-900 border-b-2 border-indigo-600': activeTaskTab === 'tomorrow', 'text-gray-500 hover:text-gray-900': activeTaskTab !== 'tomorrow'}"
                                @click="activeTaskTab = 'tomorrow'">
                            Tomorrow <span class="ml-1 px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ project.tasks.tomorrow.length }}</span>
                        </button>
                    </div>
                    <!-- Today's Tasks Content -->
                    <div v-if="activeTaskTab === 'today'">
                        <div v-if="project.tasks.today.length" class="space-y-2 text-sm text-gray-700">
                            <div v-for="task in project.tasks.today" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                                <span>{{ task.name }}</span>
                                <div class="flex items-center space-x-2">
                                    <span v-if="statusChip(task.status)" :class="statusChip(task.status).classes">{{ statusChip(task.status).label }}</span>
                                    <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-4 text-center text-sm text-gray-500">
                            <p>No tasks due today. Nice work!</p>
                        </div>
                    </div>
                    <!-- Tomorrow's Tasks Content -->
                    <div v-else>
                        <div v-if="project.tasks.tomorrow.length" class="space-y-2 text-sm text-gray-700">
                            <div v-for="task in project.tasks.tomorrow" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                                <span>{{ task.name }}</span>
                                <div class="flex items-center space-x-2">
                                    <span v-if="statusChip(task.status)" :class="statusChip(task.status).classes">{{ statusChip(task.status).label }}</span>
                                    <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-4 text-center text-sm text-gray-500">
                            <p>No tasks due tomorrow. Enjoy your day!</p>
                        </div>
                    </div>

                    <!-- Completed Tasks Section (lazy-loaded) -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h5 class="text-sm font-semibold text-gray-500 mb-2">Completed Tasks</h5>

                        <div v-if="completedLoading" class="text-sm text-gray-500">Loading completed tasks...</div>
                        <div v-else-if="completedError" class="text-sm text-red-600">{{ completedError }}</div>

                        <div v-else-if="completedLoaded && completedCount === 0" class="text-sm text-gray-500">
                            No completed tasks yet.
                        </div>

                        <div v-else :class="{'hidden': !showCompleted}" class="space-y-2 text-sm text-gray-400">
                            <div v-for="task in completedList" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                                <span class="line-through">{{ task.name }}</span>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Complete</span>
                                    <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                                </div>
                            </div>
                        </div>

                        <button v-if="!completedLoaded" @click="onViewCompletedClick" class="mt-4 text-indigo-600 text-sm font-medium hover:underline transition-all-colors">
                            View Completed Tasks
                        </button>
                        <button v-else @click="onViewCompletedClick" class="mt-4 text-indigo-600 text-sm font-medium hover:underline transition-all-colors" :aria-expanded="showCompleted">
                            <span v-if="!showCompleted">Show {{ completedCount }} Completed Task{{ completedCount > 1 ? 's' : '' }}</span>
                            <span v-else>Hide Completed ({{ completedCount }})</span>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Communication Tab Content -->
            <div v-else class="tab-content pt-4">
                <div class="space-y-3 text-sm text-gray-700">
                    <p>Last Email Sent: <span class="font-semibold">{{ project.communication.lastSent }}</span></p>
                    <p>Last Email Received: <span class="font-semibold text-yellow-700">{{ project.communication.lastReceived }}</span></p>
                    <div class="mt-4">
                        <h5 class="text-sm font-semibold text-gray-600 mb-2">Latest Contexts</h5>
                        <div v-if="project.communication.contexts && project.communication.contexts.length" class="space-y-2">
                            <div v-for="(c, idx) in project.communication.contexts.slice(0,5)" :key="idx" class="p-2 rounded-lg bg-gray-50">
                                <div class="text-sm text-gray-800">{{ c.summary }}</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <span v-if="c.user">{{ c.user }}</span>
                                    <span v-if="c.source_type" class="ml-1">• {{ c.source_type }}</span>
                                    <span v-if="c.created_at" class="ml-1">• {{ c.created_at }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400">No recent contexts.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contributor View (No Main Tabs) -->
        <div v-else>
            <!-- My Tasks Section -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">My Tasks</h3>
                <!-- Sub-tabs for Today and Tomorrow tasks -->
                <div class="flex space-x-2 border-b border-gray-200 mb-3">
                    <button class="task-tab-button flex-1 text-sm font-medium py-1 transition-all-colors"
                            :class="{'text-gray-900 border-b-2 border-indigo-600': activeTaskTab === 'today', 'text-gray-500 hover:text-gray-900': activeTaskTab !== 'today'}"
                            @click="activeTaskTab = 'today'">
                        Today <span class="ml-1 px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ project.tasks.today.length }}</span>
                    </button>
                    <button class="task-tab-button flex-1 text-sm font-medium py-1 transition-all-colors"
                            :class="{'text-gray-900 border-b-2 border-indigo-600': activeTaskTab === 'tomorrow', 'text-gray-500 hover:text-gray-900': activeTaskTab !== 'tomorrow'}"
                            @click="activeTaskTab = 'tomorrow'">
                        Tomorrow <span class="ml-1 px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ project.tasks.tomorrow.length }}</span>
                    </button>
                </div>
                <!-- Today's Tasks Content -->
                <div v-if="activeTaskTab === 'today'">
                    <div v-if="project.tasks.today.length" class="space-y-2 text-sm text-gray-700">
                        <div v-for="task in project.tasks.today" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                            <span class="max-w-[80%]">{{ task.name }}</span>
                            <div class="flex items-center space-x-2">
                                <span v-if="statusChip(task.status)" :class="statusChip(task.status).classes">{{ statusChip(task.status).label }}</span>
                                <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-4 text-center text-sm text-gray-500">
                        <p>No tasks due today. Well done!</p>
                    </div>
                </div>
                <!-- Tomorrow's Tasks Content -->
                <div v-else>
                    <div v-if="project.tasks.tomorrow.length" class="space-y-2 text-sm text-gray-700">
                        <div v-for="task in project.tasks.tomorrow" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                            <span>{{ task.name }}</span>
                            <div class="flex items-center space-x-2">
                                <span v-if="statusChip(task.status)" :class="statusChip(task.status).classes">{{ statusChip(task.status).label }}</span>
                                <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-4 text-center text-sm text-gray-500">
                        <p>No tasks due tomorrow. Enjoy your day!</p>
                    </div>
                </div>

                <!-- Communication section for Contributors -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <h5 class="text-sm font-semibold text-gray-500 mb-2">Communication</h5>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p>Last Email Sent: <span class="font-semibold">{{ project.communication?.lastSent || '—' }}</span></p>
                        <p>Last Email Received: <span class="font-semibold text-yellow-700">{{ project.communication?.lastReceived || '—' }}</span></p>
                        <div class="mt-2">
                            <h6 class="text-xs font-semibold text-gray-600 mb-1">Latest Contexts</h6>
                            <div v-if="project.communication?.contexts && project.communication.contexts.length" class="space-y-2">
                                <div v-for="(c, idx) in project.communication.contexts.slice(0,5)" :key="idx" class="p-2 rounded-lg bg-gray-50">
                                    <div class="text-sm text-gray-800">{{ c.summary }}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <span v-if="c.user">{{ c.user }}</span>
                                        <span v-if="c.source_type" class="ml-1">• {{ c.source_type }}</span>
                                        <span v-if="c.created_at" class="ml-1">• {{ c.created_at }}</span>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-400">No recent contexts.</p>
                        </div>
                    </div>
                </div>

                <!-- Completed Tasks Section (lazy-loaded) -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <h5 class="text-sm font-semibold text-gray-500 mb-2">Completed Tasks</h5>

                    <div v-if="completedLoading" class="text-sm text-gray-500">Loading completed tasks...</div>
                    <div v-else-if="completedError" class="text-sm text-red-600">{{ completedError }}</div>

                    <div v-else-if="completedLoaded && completedCount === 0" class="text-sm text-gray-500">
                        No completed tasks yet.
                    </div>

                    <div v-else :class="{'hidden': !showCompleted}" class="space-y-2 text-sm text-gray-400">
                        <div v-for="task in completedList" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                            <span class="line-through">{{ task.name }}</span>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Complete</span>
                                <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                            </div>
                        </div>
                    </div>

                    <button v-if="!completedLoaded" @click="onViewCompletedClick" class="mt-4 text-indigo-600 text-sm font-medium hover:underline transition-all-colors">
                        View Completed Tasks
                    </button>
                    <button v-else @click="onViewCompletedClick" class="mt-4 text-indigo-600 text-sm font-medium hover:underline transition-all-colors" :aria-expanded="showCompleted">
                        <span v-if="!showCompleted">Show {{ completedCount }} Completed Task{{ completedCount > 1 ? 's' : '' }}</span>
                        <span v-else>Hide Completed ({{ completedCount }})</span>
                    </button>
                </div>
            </div>
            <!-- Current Milestone Section -->
            <div class="p-4 bg-indigo-50 rounded-lg mt-6">
                <h3 class="text-base font-semibold text-indigo-900 mb-2">Current Milestone: {{ project.milestone.name }}</h3>
                <p class="text-sm text-indigo-700 mb-1">
                    <span v-if="project.milestone.completed != null && project.milestone.left != null">
                        {{ project.milestone.completed }} completed • {{ project.milestone.left }} left
                    </span>
                    <span v-else>Progress overview</span>
                </p>
                <!-- Progress bar component -->
                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                    <div class="bg-indigo-600 h-2.5 rounded-full" :style="`width: ${project.milestone.progress}%`"></div>
                </div>
                <p class="text-right text-xs font-medium text-indigo-600">{{ project.milestone.progress }}% Complete</p>
                <p class="text-xs text-indigo-800 italic mt-3">{{ project.milestone.incentive }}</p>
            </div>
        </div>
    </div>
</template>
