<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import {Head, Link} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePermissions } from '@/Directives/permissions';
import { success, error, confirmPrompt } from '@/Utils/notification';
import { formatCurrency, convertCurrency, conversionRatesToUSD, fetchCurrencyRates, displayCurrency } from '@/Utils/currency';
import MilestoneExpendableModal from "@/Components/ProjectExpendables/MilestoneExpendableModal.vue";
import ReasonModal from "@/Components/ProjectExpendables/ReasonModal.vue";
import UpdateMilestoneDueDateModal from "@/Components/ProjectExpendables/UpdateMilestoneDueDateModal.vue";
import MilestoneFormModal from '@/Components/ProjectTasks/MilestoneFormModal.vue';
import BulkTaskModal from '@/Components/ProjectTasks/BulkTaskModal.vue';
import MilestoneCompletionDateModal from '@/Components/ProjectTasks/MilestoneCompletionDateModal.vue';
import ProjectProgressTimeline from '@/Components/ProjectProgressTimeline.vue';
import Modal from '@/Components/Modal.vue';
import {
    Square2StackIcon,
    CheckCircleIcon,
    XCircleIcon,
    TrashIcon,
    ArrowPathIcon,
    PlusCircleIcon,
    WalletIcon,
    ChevronDownIcon,
    PencilSquareIcon,
    PlusIcon,
    ArrowPathIcon as RefreshIcon,
    InformationCircleIcon,
    MagnifyingGlassIcon
} from '@heroicons/vue/24/outline';

// -- State & Data --
const projects = ref([]);
const selectedProjectId = ref(null);
const loading = ref(false);
const activeTab = ref('active');
const milestones = ref([]);
const showExpendableModal = ref(false);
const showBudgetModal = ref(false);
const showReasonModal = ref(false);
const showReasonsListModal = ref(false);
const reasonsListLoading = ref(false);
const reasonsList = ref([]);
const showMilestoneFormModal = ref(false);
const showBulkTaskModal = ref(false);
const activeContractId = ref(null);
const activeMilestone = ref(null);
const showCompletionDateModal = ref(false);
const pendingMilestoneForDate = ref(null);
const pendingContractId = ref(null);
const activeExpendable = ref(null);
const showUpdateDueDateModal = ref(false);
const milestoneForDueDateUpdate = ref(null);

const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', 'label': 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

const { canDo } = usePermissions();
const canManageFinancial = canDo('manage_project_financial');
const canApproveMilestoneExpendables = canDo('approve_milestone_expendables');
const canApproveExpendables = canDo('approve_expendables');
const canApproveMilestones = canDo('approve_milestones');
const canViewMonthlyBudgets = canDo('view_monthly_budgets');
const canUpdateMilestoneDueDate = canDo('update_milestone_due_date');

const currentDisplayCurrency = displayCurrency;
const projectBudgetAmount = ref(null);
const projectBudgetCurrency = ref('PKR');
const expendableBudget = ref({
    total_budget: 0,
    total_assigned_milestone_amount: 0,
    total_pending_contract_amount: 0,
    total_approved_contract_amount: 0,
    total_expendable_amount: 0,
    available_for_new_milestones: 0,
    currency: 'AUD',
});
const users = ref([]);

const tabs = [
    { id: 'active', label: 'Active Milestones' },
    { id: 'completed', label: 'Completed' },
    { id: 'approved', label: 'Approved' },
];

// -- Computed Properties --
// Milestone ordering and counts
const nonNullMilestones = computed(() => {
    return (milestones.value || []).filter(m => !!m.completion_date);
});
const orderedNonNullMilestones = computed(() => {
    // Backend already orders with NULLs last, but ensure ordering on the client too
    return [...nonNullMilestones.value].sort((a, b) => {
        const ad = new Date(a.completion_date);
        const bd = new Date(b.completion_date);
        return ad - bd;
    });
});
const totalMilestonesCount = computed(() => (milestones.value || []).length);
const completedMilestonesCount = computed(() => (milestones.value || []).filter(m => {
    const s = (m.status || '').toLowerCase();
    return s === 'completed' || s === 'approved';
}).length);
const milestoneProgressPct = computed(() => {
    if (!totalMilestonesCount.value) return 0;
    return Math.round((completedMilestonesCount.value / totalMilestonesCount.value) * 100);
});

// Timeline based on earliest and latest non-null completion_date
const timelineStartDate = computed(() => orderedNonNullMilestones.value.length ? new Date(orderedNonNullMilestones.value[0].completion_date) : null);
const timelineEndDate = computed(() => orderedNonNullMilestones.value.length ? new Date(orderedNonNullMilestones.value[orderedNonNullMilestones.value.length - 1].completion_date) : null);
const timelinePercent = computed(() => {
    if (!timelineStartDate.value || !timelineEndDate.value) return 0;
    // Normalize to noon to reduce DST issues
    const toNoon = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
    const start = toNoon(timelineStartDate.value);
    const end = toNoon(timelineEndDate.value);
    const todayD = toNoon(new Date());
    const total = end - start;
    if (total <= 0) return 100;
    let pct = ((todayD - start) / total) * 100;
    if (pct < 0) pct = 0;
    if (pct > 100) pct = 100;
    return Math.round(pct);
});

const filteredMilestones = computed(() => {
    if (!milestones.value) return [];
    if (activeTab.value === 'active') return milestones.value.filter(m => m.status.toLowerCase() === 'in progress' || m.status.toLowerCase() === 'not started' || m.status.toLowerCase() === 'pending');
    if (activeTab.value === 'completed') return milestones.value.filter(m => m.status.toLowerCase() === 'completed');
    if (activeTab.value === 'approved') return milestones.value.filter(m => m.status.toLowerCase() === 'approved');
    return milestones.value;
});

const approvedTotal = computed(() => {
    // Use backend-provided totals; values are in expendableBudget.currency
    const amt = Number(expendableBudget.value.total_approved_contract_amount || 0);
    return convertCurrency(amt, expendableBudget.value.currency || projectBudgetCurrency.value || 'AUD', currentDisplayCurrency.value);
});

const pendingTotal = computed(() => {
    const amt = Number(expendableBudget.value.total_pending_contract_amount || 0);
    return convertCurrency(amt, expendableBudget.value.currency || projectBudgetCurrency.value || 'AUD', currentDisplayCurrency.value);
});

const remainingBudget = computed(() => {
    // Show available for new milestones as the remaining budget for new expenditure
    const amt = Number(expendableBudget.value.available_for_new_milestones ?? expendableBudget.value.total_expendable_amount ?? 0);
    const inDisplay = convertCurrency(amt, expendableBudget.value.currency || projectBudgetCurrency.value || 'AUD', currentDisplayCurrency.value);
    return formatCurrency(inDisplay, currentDisplayCurrency.value);
});

const fmtBudget = (val) => {
    const amt = Number(val || 0);
    const inDisplay = convertCurrency(amt, expendableBudget.value.currency || projectBudgetCurrency.value || 'AUD', currentDisplayCurrency.value);
    return formatCurrency(inDisplay, currentDisplayCurrency.value);
};

const userOptions = computed(() => {
    const list = Array.isArray(users.value?.users) ? users.value.users : users.value;
    return (list || []).map(u => ({ value: u.id, label: u.name }));
});

const milestoneStats = (m) => {
    if (!m) {
        return {};
    }
    const budgetAmt = m.budget ? convertCurrency(Number(m.budget.amount || 0), m.budget.currency, currentDisplayCurrency.value) : 0;
    const approvedAmt = (m.expendable || [])
        .filter(x => x.status === 'Accepted')
        .reduce((sum, x) => sum + convertCurrency(Number(x.amount || 0), x.currency, currentDisplayCurrency.value), 0);
    return { budgetAmt, approvedAmt, remaining: budgetAmt - approvedAmt };
};

const hasMilestoneBudget = (m) => {
    if (!m) {
        return null;
    }
    return m.budget !== null;
};

// -- Methods --
const onAddTasksClick = (m, e) => {
    if (!m || !e) return;
    if (!m.completion_date) {
        pendingMilestoneForDate.value = m;
        pendingContractId.value = e.id;
        showCompletionDateModal.value = true;
        return;
    }
    activeMilestone.value = m;
    activeContractId.value = e.id;
    showBulkTaskModal.value = true;
};

const onCompletionDateUpdated = (updatedMilestone) => {
    try {
        if (updatedMilestone && updatedMilestone.id) {
            const idx = (milestones.value || []).findIndex(x => x.id === updatedMilestone.id);
            if (idx !== -1) {
                // preserve existing nested arrays like expendable/budget if not returned
                milestones.value[idx] = { ...milestones.value[idx], ...updatedMilestone };
            }
        }
    } catch (err) {
        console.error('Failed to merge updated milestone', err);
    }
    const m = (milestones.value || []).find(x => x.id === (updatedMilestone?.id || pendingMilestoneForDate.value?.id));
    activeMilestone.value = m || updatedMilestone || pendingMilestoneForDate.value;
    activeContractId.value = pendingContractId.value;
    showCompletionDateModal.value = false;
    showBulkTaskModal.value = true;
    pendingMilestoneForDate.value = null;
    pendingContractId.value = null;
};
const loadProjects = async () => {
    try {
        const { data } = await window.axios.get('/api/projects-simplified');
        projects.value = (data || []).map(p => ({ value: p.id, label: p.name }));
    } catch (e) {
        console.error(e);
        error('Failed to load projects');
    }
};

const loadMilestones = async () => {
    if (!selectedProjectId.value) return;
    loading.value = true;
    try {
        const { data } = await window.axios.get(`/api/projects/${selectedProjectId.value}/milestones-with-expendables`);
        milestones.value = (data || []).map(m => ({ ...m, _collapsed: true }));
    } catch (e) {
        console.error(e);
        error('Failed to load milestones');
    } finally {
        loading.value = false;
    }
};

const openReasonsList = async (m) => {
    if (!m || !m.id) {
        error('Invalid milestone selected.');
        return;
    }
    activeMilestone.value = m;
    reasonsListLoading.value = true;
    showReasonsListModal.value = true;
    try {
        const { data } = await window.axios.get(`/api/milestones/${m.id}/reasons`);
        reasonsList.value = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error(e);
        error('Failed to load reasons');
    } finally {
        reasonsListLoading.value = false;
    }
};

const loadUsers = async () => {
    if (!selectedProjectId.value) return;
    try {
        const { data } = await window.axios.get(`/api/projects/${selectedProjectId.value}/sections/users?type=users`);
        users.value = data || [];
    } catch (e) {
        console.error(e);
        error('Failed to load users');
    }
};

const loadProjectBudget = async () => {
    if (!selectedProjectId.value) return;
    try {
        const { data } = await window.axios.get(`/api/projects/${selectedProjectId.value}/expendable-budget`);
        expendableBudget.value = {
            total_budget: Number(data.total_budget || 0),
            total_assigned_milestone_amount: Number(data.total_assigned_milestone_amount || 0),
            total_pending_contract_amount: Number(data.total_pending_contract_amount || 0),
            total_approved_contract_amount: Number(data.total_approved_contract_amount || 0),
            total_expendable_amount: Number(data.total_expendable_amount || 0),
            available_for_new_milestones: Number(data.available_for_new_milestones || 0),
            currency: data.currency || 'AUD',
        };
        // Keep these for backward compatibility where used elsewhere
        projectBudgetAmount.value = Number(data.total_expendable_amount || 0);
        projectBudgetCurrency.value = data.currency || 'AUD';
    } catch (e) {
        console.error(e);
    }
};

const onProjectChange = async () => {
    if (!selectedProjectId.value) {
        milestones.value = [];
        projectBudgetAmount.value = 0;
        projectBudgetCurrency.value = 'PKR';
        expendableBudget.value = {
            total_budget: 0,
            total_assigned_milestone_amount: 0,
            total_pending_contract_amount: 0,
            total_approved_contract_amount: 0,
            total_expendable_amount: 0,
            available_for_new_milestones: 0,
            currency: 'AUD',
        };
        return;
    }
    await Promise.all([loadMilestones(), loadUsers(), loadProjectBudget()]);
};

const onModalSubmitted = async () => {
    success('Operation successful!');
    showReasonModal.value = false;
    activeMilestone.value = null;
    activeExpendable.value = null;
    // Refresh milestones and financial stats after any submission (including budget updates)
    if (selectedProjectId.value) {
        await Promise.all([loadMilestones(), loadProjectBudget()]);
    } else {
        await loadMilestones();
    }
};

const openExpendableModal = (milestone) => {
    if (!milestone || !milestone.id) {
        error('Invalid milestone selected.');
        return;
    }
    activeMilestone.value = milestone;
    showExpendableModal.value = true;
};

const openBudgetModal = (milestone) => {
    if (!milestone || !milestone.id) {
        error('Invalid milestone selected.');
        return;
    }
    activeMilestone.value = milestone;
    showBudgetModal.value = true;
};

const onReasonModalClose = () => {
    showReasonModal.value = false;
    activeMilestone.value = null;
    activeExpendable.value = null;
};

const openUpdateDueDateModal = (milestone) => {
    if (!milestone || !milestone.id) {
        error('Invalid milestone selected.');
        return;
    }
    milestoneForDueDateUpdate.value = milestone;
    showUpdateDueDateModal.value = true;
};

const onDueDateUpdated = async () => {
    showUpdateDueDateModal.value = false;
    milestoneForDueDateUpdate.value = null;
    // Refresh milestones to reflect the updated due date and notes
    if (selectedProjectId.value) {
        await loadMilestones();
    }
};

const markComplete = async (m) => {
    if (!m || !m.id) {
        error('Invalid milestone.');
        return;
    }
    const confirmed = await confirmPrompt('Mark this milestone as complete?');
    if (!confirmed) return;
    activeMilestone.value = { ...m, action: 'complete', apiEndpoint: `/api/milestones/${m.id}/complete`, httpMethod: 'post' };
    showReasonModal.value = true;
};

const approve = async (m) => {
    if (!m || !m.id) {
        error('Invalid milestone.');
        return;
    }
    const confirmed = await confirmPrompt('Approve this completed milestone?');
    if (!confirmed) return;
    activeMilestone.value = { ...m, action: 'approve', apiEndpoint: `/api/milestones/${m.id}/approve`, httpMethod: 'post' };
    showReasonModal.value = true;
};

const rejectMilestone = async (m) => {
    if (!m || !m.id) {
        error('Invalid milestone.');
        return;
    }
    const confirmed = await confirmPrompt('Reject this completed milestone?');
    if (!confirmed) return;
    activeMilestone.value = { ...m, action: 'reject', apiEndpoint: `/api/milestones/${m.id}/reject`, httpMethod: 'post' };
    showReasonModal.value = true;
};

const approveExpendable = async (e) => {
    if (!e || !e.id) {
        error('Invalid contract.');
        return;
    }
    activeExpendable.value = { ...e, action: 'accept', apiEndpoint: `/api/projects/${selectedProjectId.value}/expendables/${e.id}/accept`, httpMethod: 'post' };
    showReasonModal.value = true;
};

const rejectExpendable = async (e) => {
    if (!e || !e.id) {
        error('Invalid contract.');
        return;
    }
    activeExpendable.value = { ...e, action: 'reject', apiEndpoint: `/api/projects/${selectedProjectId.value}/expendables/${e.id}/reject`, httpMethod: 'post' };
    showReasonModal.value = true;
};

const deleteExpendable = async (e) => {
    if (!e || !e.id) {
        error('Invalid contract.');
        return;
    }
    activeExpendable.value = { ...e, action: 'delete', apiEndpoint: `/api/projects/${selectedProjectId.value}/expendables/${e.id}`, httpMethod: 'delete' };
    showReasonModal.value = true;
};

const reopen = async (m) => {
    if (!m || !m.id) {
        error('Invalid milestone.');
        return;
    }
    const confirmed = await confirmPrompt('Reopen this milestone to move it back to active?');
    if (!confirmed) return;
    activeMilestone.value = { ...m, action: 'reopen', apiEndpoint: `/api/milestones/${m.id}/reopen`, httpMethod: 'post' };
    showReasonModal.value = true;
};

const toggle = (m) => { m._collapsed = !m._collapsed; };

const hasPendingContracts = (m) => {
    if (!m || !Array.isArray(m.expendable)) return false;
    return m.expendable.some(e => (e?.status || 'Pending Approval') === 'Pending Approval');
};

const hasAnyContracts = (m) => {
    return !!(m && Array.isArray(m.expendable) && m.expendable.length > 0);
};

// Deadline helpers
const daysUntil = (dateStr) => {
    try {
        if (!dateStr) return null;
        const d = new Date(dateStr);
        const today = new Date();
        // Normalize to noon to avoid DST/timezone edge cases
        const dt = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
        const tt = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 12, 0, 0);
        const msPerDay = 24 * 60 * 60 * 1000;
        return Math.round((dt - tt) / msPerDay);
    } catch (e) {
        return null;
    }
};

const daysRemainingText = (dateStr) => {
    const diff = daysUntil(dateStr);
    if (diff === null) return '';
    if (diff < 0) return `Overdue by ${Math.abs(diff)} day${Math.abs(diff) === 1 ? '' : 's'}`;
    if (diff === 0) return 'Due today';
    return `${diff} day${diff === 1 ? '' : 's'} remaining`;
};

// -- Lifecycle Hooks --
onMounted(async () => {
    const storedCurrency = localStorage.getItem('displayCurrency');
    if (storedCurrency) currentDisplayCurrency.value = storedCurrency;
    await fetchCurrencyRates();
    await loadProjects();
    if (selectedProjectId.value) {
        await onProjectChange();
    }
});

// -- Watchers --
watch(currentDisplayCurrency, async (newCurrency) => {
    if (newCurrency) {
        localStorage.setItem('displayCurrency', newCurrency);
        if (selectedProjectId.value) {
            await Promise.all([loadProjectBudget(), loadMilestones()]);
        }
    }
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Project Financials Dashboard" />

        <!-- Header with Project Selector and Currency -->
        <template #header>
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <h2 class="font-semibold text-2xl text-gray-800">Project Financials</h2>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <button @click="onProjectChange" class="p-2 rounded-full text-gray-500 hover:bg-gray-100 transition-colors" title="Refresh">
                        <RefreshIcon class="h-5 w-5" />
                    </button>
                    <div class="flex-grow md:flex-grow-0 w-full md:w-64">
                        <SelectDropdown
                            id="project-select"
                            v-model="selectedProjectId"
                            :options="projects"
                            placeholder="Select a project"
                            @update:modelValue="onProjectChange"
                        />
                    </div>
                    <div class="w-24 md:w-32 flex-shrink-0">
                        <SelectDropdown
                            id="expendables-display-currency"
                            v-model="currentDisplayCurrency"
                            :options="currencyOptions"
                            placeholder="Currency"
                        />
                    </div>
                    <Link :href="route('admin.pm-payout-calculator.index')" class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring active:bg-indigo-700 transition ease-in-out duration-150">
                        Payout Calculator
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Financial Summary Dashboard -->
                <section v-if="selectedProjectId">
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Financial Summary</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-indigo-50 rounded-lg p-5 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="text-sm font-medium text-indigo-700 mb-1">Total Project Budget</div>
                                    <div class="text-3xl font-bold text-indigo-900">
                                        {{ fmtBudget(expendableBudget.total_budget) }}
                                    </div>
                                </div>
                                <p class="text-xs text-indigo-600 mt-2">Overall budget allocated to the project.</p>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-5 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="text-sm font-medium text-purple-700 mb-1">Assigned to Milestones</div>
                                    <div class="text-3xl font-bold text-purple-900">
                                        {{ fmtBudget(expendableBudget.total_assigned_milestone_amount) }}
                                    </div>
                                </div>
                                <p class="text-xs text-purple-600 mt-2">Amount already assigned to milestones.</p>
                            </div>
                            <div class="bg-green-50 rounded-lg p-5 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="text-sm font-medium text-green-700 mb-1">Approved Contracts</div>
                                    <div class="text-3xl font-bold text-green-900">
                                        {{ formatCurrency(approvedTotal, currentDisplayCurrency) }}
                                    </div>
                                </div>
                                <p class="text-xs text-green-600 mt-2">Total value of all accepted contracts.</p>
                            </div>
                            <div class="bg-yellow-50 rounded-lg p-5 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="text-sm font-medium text-yellow-700 mb-1">Pending Approval</div>
                                    <div class="text-3xl font-bold text-yellow-900">
                                        {{ formatCurrency(pendingTotal, currentDisplayCurrency) }}
                                    </div>
                                </div>
                                <p class="text-xs text-yellow-600 mt-2">Contracts awaiting review and approval.</p>
                            </div>
                            <div class="bg-blue-50 rounded-lg p-5 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="text-sm font-medium text-blue-700 mb-1">Expendable Remaining</div>
                                    <div class="text-3xl font-bold text-blue-900">
                                        {{ fmtBudget(expendableBudget.total_expendable_amount) }}
                                    </div>
                                </div>
                                <p class="text-xs text-blue-600 mt-2">Remaining after approved contracts.</p>
                            </div>
                            <div class="bg-teal-50 rounded-lg p-5 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="text-sm font-medium text-teal-700 mb-1">Available for New Milestones</div>
                                    <div class="text-3xl font-bold text-teal-900">
                                        {{ fmtBudget(expendableBudget.available_for_new_milestones) }}
                                    </div>
                                </div>
                                <p class="text-xs text-teal-600 mt-2">Budget still available to allocate to new milestones.</p>
                            </div>
                        </div>

                        <!-- Project Progress & Timeline extracted into a reusable component -->
                        <ProjectProgressTimeline :milestones="milestones" />
                    </div>
                </section>

                <!-- Milestone Section with Tabs -->
                <section class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4 mb-4">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xl font-semibold text-gray-900">Project Milestones</h3>
                            <button
                                v-if="selectedProjectId"
                                @click="showMilestoneFormModal = true"
                                class="p-1.5 rounded-full text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                title="Add Milestone"
                            >
                                <PlusIcon class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="flex space-x-2 mt-2 sm:mt-0">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="activeTab = tab.id"
                                :class="[
                                    'py-2 px-4 rounded-full text-sm font-medium transition',
                                    activeTab === tab.id ? 'bg-indigo-600 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                ]"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <div v-if="!selectedProjectId" class="text-center text-gray-500 py-12">
                        <MagnifyingGlassIcon class="h-12 w-12 mx-auto mb-2 text-gray-400" />
                        <p class="text-lg">Please select a project to view its milestones.</p>
                    </div>
                    <div v-else>
                        <div v-if="loading" class="text-center text-gray-500 py-12">
                            <ArrowPathIcon class="animate-spin h-8 w-8 mx-auto mb-2 text-indigo-500" />
                            <p>Loading milestones...</p>
                        </div>
                        <div v-else-if="!filteredMilestones.length" class="text-center text-gray-500 py-12">
                            <p>No milestones found for this project in the selected tab.</p>
                        </div>
                        <div v-else class="space-y-4">
                            <!-- Milestone Cards -->
                            <div v-for="m in filteredMilestones" :key="m.id" class="border border-gray-200 rounded-xl bg-gray-50 shadow-sm transition-all duration-300 hover:shadow-lg">
                                <div class="p-5 flex items-center justify-between cursor-pointer" @click="toggle(m)">
                                    <div class="flex-grow">
                                        <div class="font-bold text-lg text-gray-900 mb-1">{{ m.name }}</div>
                                        <div class="text-sm text-gray-600" v-if="m.description">{{ m.description }}</div>
                                        <!-- Completion date and days remaining -->
                                        <div class="mt-1 text-xs text-gray-500 flex items-center gap-2">
                                            <span v-if="m.completion_date">
                                                Due by:
                                                <span
                                                    v-if="canUpdateMilestoneDueDate && activeTab === 'active'"
                                                    @click.stop="openUpdateDueDateModal(m)"
                                                    class="font-medium text-gray-700 cursor-pointer hover:text-indigo-600 hover:underline transition-colors"
                                                    title="Click to update due date"
                                                >
                                                    {{ new Date(m.completion_date).toLocaleDateString() }}
                                                </span>
                                                <span v-else class="font-medium text-gray-700">
                                                    {{ new Date(m.completion_date).toLocaleDateString() }}
                                                </span>
                                            </span>
                                            <span v-else>No completion date</span>
                                            <span v-if="m.completion_date" :class="[
                                                daysUntil(m.completion_date) < 0 ? 'text-red-600' : (daysUntil(m.completion_date) === 0 ? 'text-orange-600' : 'text-green-600')
                                            ]">
                                                • {{ daysRemainingText(m.completion_date) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Milestone Summary & Toggle -->
                                    <div class="flex items-center gap-4 text-sm text-gray-700">
                                        <!-- Budget Summary -->
                                        <div class="hidden sm:flex items-center gap-4">
                                            <span class="inline-flex items-center gap-1 text-gray-600">
                                                <WalletIcon class="h-4 w-4 text-gray-500" />
                                                Budget: <span class="font-semibold text-gray-800">{{ formatCurrency(milestoneStats(m).budgetAmt, currentDisplayCurrency) }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 text-gray-600">
                                                <CheckCircleIcon class="h-4 w-4 text-green-600" />
                                                Approved: <span class="font-semibold text-green-700">{{ formatCurrency(milestoneStats(m).approvedAmt, currentDisplayCurrency) }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 text-gray-600">
                                                <ArrowPathIcon class="h-4 w-4 text-blue-600" />
                                                Remaining: <span class="font-semibold text-blue-700">{{ formatCurrency(milestoneStats(m).remaining, currentDisplayCurrency) }}</span>
                                            </span>
                                        </div>

                                        <!-- Budget Edit Icon (Moved here) -->
                                        <button @click.stop="openBudgetModal(m)" class="p-2 rounded-full text-gray-500 hover:text-blue-500 hover:bg-gray-100 transition-colors" v-if="activeTab === 'active'">
                                            <PencilSquareIcon v-if="hasMilestoneBudget(m)" class="h-5 w-5" />
                                            <PlusCircleIcon v-else class="h-5 w-5" />
                                        </button>

                                        <!-- Other Actions & Toggle Button Group -->
                                        <div class="flex items-center gap-2">
                                            <button @click.stop="openReasonsList(m)" class="p-2 rounded-full text-blue-600 hover:bg-blue-100 transition-colors" title="View reasons">
                                                <InformationCircleIcon class="h-5 w-5" />
                                            </button>
                                            <button @click.stop="toggle(m)" class="p-2 transition-transform duration-300">
                                                <ChevronDownIcon :class="['h-6 w-6 text-gray-500', m._collapsed ? '' : 'rotate-180']" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Collapsible Content -->
                                <div v-show="!m._collapsed" class="border-t border-gray-200 p-5 bg-white rounded-b-xl">
                                    <!-- Task Stats -->
                                    <div class="mb-5">
                                        <h5 class="text-sm font-semibold text-gray-800 mb-2">Task Stats</h5>
                                        <div class="flex flex-wrap gap-2 text-xs">
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                                Total <span class="font-semibold">{{ m.tasks_total_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-slate-100 text-slate-700">
                                                To Do <span class="font-semibold">{{ m.tasks_todo_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-blue-100 text-blue-700">
                                                In Progress <span class="font-semibold">{{ m.tasks_in_progress_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-yellow-100 text-yellow-700">
                                                Paused <span class="font-semibold">{{ m.tasks_paused_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-orange-100 text-orange-700">
                                                Blocked <span class="font-semibold">{{ m.tasks_blocked_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-100 text-green-700">
                                                Done <span class="font-semibold">{{ m.tasks_done_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-red-100 text-red-700">
                                                Archived <span class="font-semibold">{{ m.tasks_archived_count || 0 }}</span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="text-lg font-semibold text-gray-800">Contracts</h4>
                                        <div class="flex items-center gap-2">
                                            <PrimaryButton @click.stop="openExpendableModal(m)" class="flex items-center gap-1" v-if="activeTab === 'active'">
                                                <PlusIcon class="h-4 w-4" /> Add Contract
                                            </PrimaryButton>
                                        </div>
                                    </div>
                                    <div v-if="!m.expendable || !m.expendable.length" class="text-center text-gray-500 text-sm py-4">No contracts found.</div>
                                    <ul v-else class="space-y-2">
                                        <li v-for="e in (m.expendable || [])" :key="e.id" class="flex flex-col sm:flex-row items-start sm:items-center justify-between text-sm gap-2 p-3 rounded-lg bg-gray-100 shadow-sm">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium"
                                                      :class="{
                                                          'bg-gray-200 text-gray-700': e.status === 'Pending Approval',
                                                          'bg-green-200 text-green-700': e.status === 'Accepted',
                                                          'bg-red-200 text-red-700': e.status === 'Rejected'
                                                      }">
                                                    {{ e.status || 'Pending Approval' }}
                                                </span>
                                                <span v-if="e.user && e.user.name" class="text-gray-600 text-xs sm:text-sm">{{ e.user.name }}</span>
                                                <span class="font-medium">{{ e.name }}</span>
                                            </div>
                                            <div class="flex items-center gap-4 mt-2 sm:mt-0">
                                                <div class="text-right">
                                                    <span class="font-semibold">
                                                        {{ formatCurrency(convertCurrency(parseFloat(e.amount ?? 0), e.currency || currentDisplayCurrency, currentDisplayCurrency), currentDisplayCurrency) }}
                                                    </span>
                                                    <span v-if="e.currency && e.currency?.toUpperCase() !== currentDisplayCurrency?.toUpperCase()" class="text-gray-500 block text-xs">
                                                        ({{ formatCurrency(parseFloat(e.amount ?? 0), e.currency) }})
                                                    </span>
                                                </div>
                                                <div class="flex gap-1.5">
                                                    <!-- Add Tasks: Only for Approved contracts -->
                                                    <button v-if="e.status === 'Accepted'" @click.stop="onAddTasksClick(m, e)" class="p-1 rounded-full text-indigo-600 hover:bg-indigo-200 transition-colors" title="Add tasks">
                                                        <PlusIcon class="h-5 w-5" />
                                                    </button>
                                                    <!-- Contract Action Buttons -->
                                                    <template v-if="e.status === 'Pending Approval' && activeTab !== 'approved'">
                                                        <button v-if="canApproveMilestoneExpendables || canApproveExpendables" @click.stop="approveExpendable(e)" class="p-1 rounded-full text-green-600 hover:bg-green-200 transition-colors" title="Approve">
                                                            <CheckCircleIcon class="h-5 w-5" />
                                                        </button>
                                                        <button v-if="canApproveMilestoneExpendables || canApproveExpendables" @click.stop="rejectExpendable(e)" class="p-1 rounded-full text-red-600 hover:bg-red-200 transition-colors" title="Reject">
                                                            <XCircleIcon class="h-5 w-5" />
                                                        </button>
                                                    </template>
                                                    <button v-if="e.status === 'Rejected' && activeTab !== 'approved'" @click.stop="deleteExpendable(e)" class="p-1 rounded-full text-red-600 hover:bg-red-200 transition-colors" title="Delete">
                                                        <TrashIcon class="h-5 w-5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>

                                    <!-- Milestone-level Action Buttons -->
                                    <div class="mt-6 pt-4 border-t border-gray-200 flex flex-col items-start gap-2">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <PrimaryButton
                                                v-if="m.status.toLowerCase() !== 'completed' && activeTab === 'active'"
                                                @click.stop="markComplete(m)"
                                                :disabled="hasPendingContracts(m) && hasAnyContracts(m)"
                                                :title="(hasPendingContracts(m) && hasAnyContracts(m)) ? 'Approve or reject all contracts before completing the milestone.' : ''"
                                            >
                                                Mark Complete
                                            </PrimaryButton>
                                            <PrimaryButton v-else-if="m.status.toLowerCase() === 'completed' && activeTab === 'completed' && canApproveMilestones" @click.stop="approve(m)" class="bg-green-600 hover:bg-green-700">Approve Milestone</PrimaryButton>
                                            <PrimaryButton v-if="m.status.toLowerCase() === 'completed' && activeTab === 'completed' && canApproveMilestones" @click.stop="rejectMilestone(m)" class="bg-red-600 hover:bg-red-700">Reject Milestone</PrimaryButton>
                                            <SecondaryButton v-else-if="m.status.toLowerCase() === 'approved' && activeTab === 'approved' && canApproveMilestones" @click.stop="reopen(m)">Reopen Milestone</SecondaryButton>
                                        </div>
                                        <p v-if="m.status.toLowerCase() !== 'completed' && activeTab === 'active' && hasPendingContracts(m) && hasAnyContracts(m)" class="text-sm text-red-600">
                                            You have pending contracts. Approve or reject each contract before marking this milestone complete.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Modals - Kept as-is but with the assumption they exist elsewhere -->
        <MilestoneExpendableModal
            :show="showExpendableModal"
            title="Add New Contract"
            :milestone="activeMilestone"
            :users="users"
            :currency-options="currencyOptions"
            :is-user-selection-required="true"
            @close="showExpendableModal = false"
            @submitted="onModalSubmitted"
            :project-total-budget="projectBudgetAmount"
            :project-budget-currency="projectBudgetCurrency"
            :milestone-stats="milestoneStats(activeMilestone)"
            :available-for-new-milestones="expendableBudget.available_for_new_milestones"
            :budget-base-currency="expendableBudget.currency"
        />

        <MilestoneExpendableModal
            :show="showBudgetModal"
            :title="hasMilestoneBudget(activeMilestone) ? 'Update Milestone Budget' : 'Add Milestone Budget'"
            :milestone="activeMilestone"
            :users="users"
            :currency-options="currencyOptions"
            :is-budget-form="true"
            :is-user-selection-required="false"
            @close="showBudgetModal = false"
            @submitted="onModalSubmitted"
            :project-total-budget="projectBudgetAmount"
            :project-budget-currency="projectBudgetCurrency"
            :milestone-stats="milestoneStats(activeMilestone)"
            :available-for-new-milestones="expendableBudget.available_for_new_milestones"
            :budget-base-currency="expendableBudget.currency"
        />

        <ReasonModal
            :show="showReasonModal"
            :title="activeMilestone?.action === 'approve' ? 'Approve Milestone' : activeMilestone?.action === 'reject' ? 'Reject Milestone' : activeMilestone?.action === 'reopen' ? 'Reopen Milestone' : (activeMilestone ? 'Provide Milestone Review' : (activeExpendable?.action === 'accept' ? 'Approve Contract' : (activeExpendable?.action === 'reject' ? 'Reject Contract' : 'Delete Contract')))"
            :message="activeMilestone?.action === 'approve' ? 'Please provide a reason for approving this milestone (required):' : activeMilestone?.action === 'reject' ? 'Please provide a reason for rejecting this milestone (required):' : activeMilestone?.action === 'reopen' ? 'Please provide a reason for reopening this milestone (required):' : (activeMilestone ? 'Please provide a review explaining why this milestone is complete (required):' : (activeExpendable?.action === 'accept' ? 'Please provide a reason for approval (required):' : (activeExpendable?.action === 'reject' ? 'Please provide a reason for rejection (required):' : 'Provide a reason for deleting this rejected expendable (required):')))"
            :type="activeMilestone?.action === 'reject' || activeMilestone?.action === 'reopen' ? 'warning' : 'info'"
            :api-endpoint="activeMilestone ? activeMilestone.apiEndpoint : activeExpendable?.apiEndpoint"
            :http-method="activeMilestone ? activeMilestone.httpMethod : activeExpendable?.httpMethod"
            @close="onReasonModalClose"
            @submitted="onModalSubmitted"
        />

        <!-- Create Milestone Modal -->
        <MilestoneFormModal
            :show="showMilestoneFormModal"
            :project-id="Number(selectedProjectId)"
            @close="showMilestoneFormModal = false"
            @saved="() => { showMilestoneFormModal = false; loadMilestones(); }"
            :contract-id="activeContractId"
        />

        <Modal :show="showReasonsListModal" @close="() => { showReasonsListModal = false; activeMilestone = null; reasonsList = []; }">
            <div class="p-6 bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Milestone Reasons</h3>
                    <button class="text-gray-500 hover:text-gray-700" @click="() => { showReasonsListModal = false; activeMilestone = null; reasonsList = []; }">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div v-if="reasonsListLoading" class="text-gray-500 text-center py-4">Loading...</div>
                <div v-else>
                    <div v-if="!reasonsList.length" class="text-gray-500 text-center py-4">No reasons found.</div>
                    <ul v-else class="space-y-3 max-h-96 overflow-y-auto pr-2">
                        <li v-for="r in reasonsList" :key="r.id" class="p-4 rounded-lg bg-gray-50 shadow-sm border border-gray-200">
                            <div class="text-sm text-gray-600 flex justify-between items-center mb-1">
                                <span class="font-medium">{{ r.creator_name || 'Unknown' }}</span>
                                <span class="text-xs text-gray-400">{{ new Date(r.created_at).toLocaleString() }}</span>
                            </div>
                            <div class="mt-1 text-gray-800 text-sm whitespace-pre-line">{{ r.content }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </Modal>

        <!-- Bulk Task Modal for Contract -->
        <BulkTaskModal
            :show="showBulkTaskModal"
            :contract-id="Number(activeContractId)"
            :completion-date="activeMilestone?.completion_date ? String(activeMilestone.completion_date).split('T')[0] : null"
            @close="() => { showBulkTaskModal = false; activeContractId = null; }"
            @tasks-submitted="() => { showBulkTaskModal = false; activeContractId = null; }"
        />

        <!-- Set Milestone Completion Date Modal -->
        <MilestoneCompletionDateModal
            :show="showCompletionDateModal"
            :milestone-id="Number(pendingMilestoneForDate?.id || activeMilestone?.id)"
            :initial-completion-date="pendingMilestoneForDate?.completion_date ? String(pendingMilestoneForDate.completion_date).split('T')[0] : null"
            @close="() => { showCompletionDateModal = false; pendingMilestoneForDate = null; }"
            @updated="onCompletionDateUpdated"
        />

        <!-- Update Milestone Due Date Modal -->
        <UpdateMilestoneDueDateModal
            :show="showUpdateDueDateModal"
            :milestone="milestoneForDueDateUpdate"
            @close="() => { showUpdateDueDateModal = false; milestoneForDueDateUpdate = null; }"
            @submitted="onDueDateUpdated"
        />
    </AuthenticatedLayout>
</template>
