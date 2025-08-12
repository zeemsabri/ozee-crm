<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePermissions } from '@/Directives/permissions';
import { success, error, confirmPrompt } from '@/Utils/notification';
import { formatCurrency, convertCurrency, conversionRatesToUSD, fetchCurrencyRates, displayCurrency } from '@/Utils/currency';
import MilestoneExpendableModal from "@/Components/ProjectExpendables/MilestoneExpendableModal.vue";
import ReasonModal from "@/Components/ProjectExpendables/ReasonModal.vue";
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
    ArrowPathIcon as RefreshIcon
} from '@heroicons/vue/24/outline';

const projects = ref([]);
const selectedProjectId = ref(null);
const loading = ref(false);
const activeTab = ref('active');
const milestones = ref([]);

const showExpendableModal = ref(false);
const showBudgetModal = ref(false);
const showReasonModal = ref(false);

const activeMilestone = ref(null);
const activeExpendable = ref(null);

const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

const { canDo } = usePermissions();
const canManageFinancial = canDo('manage_project_financial');
const canApproveMilestoneExpendables = canDo('approve_milestone_expendables');
const canApproveExpendables = canDo('approve_expendables');
const canApproveMilestones = canDo('approve_milestones');

const currentDisplayCurrency = displayCurrency;

const tabs = [
    { id: 'active', label: 'Active' },
    { id: 'completed', label: 'Completed' },
    { id: 'approved', label: 'Approved' },
];

const filteredMilestones = computed(() => {
    if (!milestones.value) return [];
    if (activeTab.value === 'active') return milestones.value.filter(m => m.status.toLowerCase() === 'in progress' || m.status.toLowerCase() === 'not started');
    if (activeTab.value === 'completed') return milestones.value.filter(m => m.status.toLowerCase() === 'completed');
    if (activeTab.value === 'approved') return milestones.value.filter(m => m.status.toLowerCase() === 'approved');
    return milestones.value;
});

const projectBudgetAmount = ref(null);
const projectBudgetCurrency = ref('PKR');

const users = ref([]);
const userOptions = computed(() => {
    const list = Array.isArray(users.value?.users) ? users.value.users : users.value;
    return (list || []).map(u => ({ value: u.id, label: u.name }));
});

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

const approvedTotal = computed(() => {
    if (!milestones.value || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) return 0;
    return milestones.value.reduce((total, milestone) => {
        // Only count contracts from milestones that are NOT approved
        if (milestone.status.toLowerCase() !== 'approved') {
            return total + (milestone.expendable || []).filter(e => e.status === 'Accepted').reduce((sum, e) => {
                return sum + convertCurrency(Number(e.amount || 0), e.currency, currentDisplayCurrency.value);
            }, 0);
        }
        return total;
    }, 0);
});
const pendingTotal = computed(() => {
    if (!milestones.value || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) return 0;
    return milestones.value.reduce((total, milestone) => {
        return total + (milestone.expendable || []).filter(e => e.status === 'Pending Approval').reduce((sum, e) => {
            return sum + convertCurrency(Number(e.amount || 0), e.currency, currentDisplayCurrency.value);
        }, 0);
    }, 0);
});
const remainingBudget = computed(() => {
    if (projectBudgetAmount.value == null) return formatCurrency(0, currentDisplayCurrency.value);
    const budgetInDisplay = convertCurrency(Number(projectBudgetAmount.value || 0), projectBudgetCurrency.value, currentDisplayCurrency.value);
    return formatCurrency(budgetInDisplay, currentDisplayCurrency.value);
});

const milestoneStats = (m) => {
    if(!m) {
        return {};
    }
    const budgetAmt = m.budget ? convertCurrency(Number(m.budget.amount || 0), m.budget.currency, currentDisplayCurrency.value) : 0;
    const approvedAmt = (m.expendable || [])
        .filter(x => x.status === 'Accepted')
        .reduce((sum, x) => sum + convertCurrency(Number(x.amount || 0), x.currency, currentDisplayCurrency.value), 0);
    return { budgetAmt, approvedAmt, remaining: budgetAmt - approvedAmt };
};

const hasMilestoneBudget = (m) => {
    if(!m) {
        return null;
    }
    return m.budget !== null;
};

const toggle = (m) => { m._collapsed = !m._collapsed; };

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
        projectBudgetAmount.value = Number(data.total_expendable_amount || 0);
        projectBudgetCurrency.value = data.currency;
    } catch (e) {
        console.error(e);
    }
};

const onProjectChange = async () => {
    await Promise.all([loadMilestones(), loadUsers(), loadProjectBudget()]);
};

const onModalSubmitted = () => {
    success('Operation successful!');
    loadMilestones();
};

const openExpendableModal = (milestone) => {
    activeMilestone.value = milestone;
    showExpendableModal.value = true;
};

const openBudgetModal = (milestone) => {
    activeMilestone.value = milestone;
    showBudgetModal.value = true;
};

const handleReasonSubmitted = async (reason) => {
    if (!reason || !reason.trim()) {
        return error('Reason is required.');
    }

    try {
        if (activeMilestone.value) {
            const action = activeMilestone.value.action;
            const url = `/api/milestones/${activeMilestone.value.id}/${action}`;
            await window.axios.post(url, { reason });
            success(`Milestone ${action}d`);
        } else if (activeExpendable.value) {
            const { action, id } = activeExpendable.value;
            const url = `/api/projects/${selectedProjectId.value}/expendables/${id}`;
            if (action === 'delete') {
                await window.axios.delete(url, { data: { reason } });
            } else {
                await window.axios.post(`${url}/${action}`, { reason });
            }
            success(`Expendable ${action}d`);
        }
        await Promise.all([loadMilestones(), loadProjectBudget()]);
    } catch (e) {
        error(e.response?.data?.message || 'Failed to complete action');
        console.error(e);
    } finally {
        showReasonModal.value = false;
        activeMilestone.value = null;
        activeExpendable.value = null;
    }
};

const onReasonModalClose = () => {
    showReasonModal.value = false;
    activeMilestone.value = null;
    activeExpendable.value = null;
};

const markComplete = async (m) => {
    const confirmed = await confirmPrompt('Mark this milestone as complete?');
    if (!confirmed) return;
    activeMilestone.value = { ...m, action: 'complete' };
    showReasonModal.value = true;
};

const approve = async (m) => {
    const confirmed = await confirmPrompt('Approve this completed milestone?');
    if (!confirmed) return;
    activeMilestone.value = { ...m, action: 'approve' };
    showReasonModal.value = true;
};

const rejectMilestone = async (m) => {
    const confirmed = await confirmPrompt('Reject this completed milestone?');
    if (!confirmed) return;
    activeMilestone.value = { ...m, action: 'reject' };
    showReasonModal.value = true;
};

const approveExpendable = async (e) => {
    activeExpendable.value = { ...e, action: 'accept', apiEndpoint: `/api/projects/${selectedProjectId.value}/expendables/${e.id}/accept`, httpMethod: 'post' };
    showReasonModal.value = true;
};

const rejectExpendable = async (e) => {
    activeExpendable.value = { ...e, action: 'reject', apiEndpoint: `/api/projects/${selectedProjectId.value}/expendables/${e.id}/reject`, httpMethod: 'post' };
    showReasonModal.value = true;
};

const deleteExpendable = async (e) => {
    activeExpendable.value = { ...e, action: 'delete', apiEndpoint: `/api/projects/${selectedProjectId.value}/expendables/${e.id}`, httpMethod: 'delete' };
    showReasonModal.value = true;
};

const reopen = async (m) => {
    const confirmed = await confirmPrompt('Reopen this milestone to move it back to active?');
    if (!confirmed) return;
    activeMilestone.value = { ...m, action: 'reopen' };
    showReasonModal.value = true;
};

onMounted(async () => {
    const storedCurrency = localStorage.getItem('displayCurrency');
    if (storedCurrency) currentDisplayCurrency.value = storedCurrency;
    await fetchCurrencyRates();
    await loadProjects();
    if (selectedProjectId.value) {
        await onProjectChange();
    }
});

watch(currentDisplayCurrency, async (newCurrency) => {
    if (newCurrency) {
        localStorage.setItem('displayCurrency', newCurrency);
        await Promise.all([loadProjectBudget(), loadMilestones()]);
    }
});
</script>

<template>
    <Head title="Project Expendables" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Project Expendables</h2>
                <div class="flex items-center gap-3">
                    <button @click="onProjectChange" class="p-2 rounded-full text-gray-500 hover:bg-gray-100 transition-colors" title="Refresh">
                        <RefreshIcon class="h-5 w-5" />
                    </button>
                    <div class="w-64">
                        <SelectDropdown
                            id="project-select"
                            v-model="selectedProjectId"
                            :options="projects"
                            value-key="value"
                            label-key="label"
                            placeholder="Select a project"
                            @update:modelValue="onProjectChange"
                        />
                    </div>
                    <div class="w-32">
                        <SelectDropdown
                            id="expendables-display-currency"
                            v-model="currentDisplayCurrency"
                            :options="currencyOptions"
                            value-key="value"
                            label-key="label"
                            placeholder="Currency"
                        />
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white">
                        <!-- Stats Section -->
                        <div v-if="selectedProjectId" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                            <div class="p-3 rounded bg-green-50">
                                <div class="text-xs text-green-700">Approved Contracts</div>
                                <div class="text-lg font-semibold text-green-800">{{ formatCurrency(approvedTotal, currentDisplayCurrency) }}</div>
                            </div>
                            <div class="p-3 rounded bg-yellow-50">
                                <div class="text-xs text-yellow-700">Pending Approval</div>
                                <div class="text-lg font-semibold text-yellow-800">{{ formatCurrency(pendingTotal, currentDisplayCurrency) }}</div>
                            </div>
                            <div class="p-3 rounded bg-blue-50">
                                <div class="text-xs text-blue-700">Remaining Budget</div>
                                <div class="text-lg font-semibold text-blue-800">{{ remainingBudget }}</div>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <div class="border-b border-gray-200 mb-4">
                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.id"
                                    @click="activeTab = tab.id"
                                    :class="[
                    activeTab === tab.id
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                  ]"
                                >
                                    {{ tab.label }}
                                </button>
                            </nav>
                        </div>

                        <div v-if="!selectedProjectId" class="text-gray-500">Select a project to view milestones.</div>
                        <div v-else>
                            <div v-if="loading" class="text-gray-500">Loading...</div>
                            <div v-else-if="!filteredMilestones.length" class="text-gray-500">No milestones found.</div>
                            <div v-else class="space-y-3">
                                <div v-for="m in filteredMilestones" :key="m.id" class="border rounded-lg">
                                    <div class="p-4 flex items-center justify-between cursor-pointer" @click="toggle(m)">
                                        <div class="flex-grow">
                                            <div class="font-medium">{{ m.name }}</div>
                                            <div class="text-sm text-gray-600" v-if="m.description">{{ m.description }}</div>
                                        </div>
                                        <!-- Milestone Summary: Budget/Approved/Remaining (visible even when collapsed) -->
                                        <div class="flex items-center gap-4 text-sm text-gray-700" v-if="activeTab === 'active' || canDo('approve_milestones')">
                                            <div class="hidden md:flex items-center gap-4">
                                                <span class="inline-flex items-center gap-1">
                                                  <WalletIcon class="h-4 w-4 text-gray-500" />
                                                  Budget: <span class="font-semibold">{{ formatCurrency(milestoneStats(m).budgetAmt, currentDisplayCurrency) }}</span>
                                                </span>
                                                <span class="inline-flex items-center gap-1">
                                                  <CheckCircleIcon class="h-4 w-4 text-green-600" />
                                                  Approved: <span class="font-semibold text-green-700">{{ formatCurrency(milestoneStats(m).approvedAmt, currentDisplayCurrency) }}</span>
                                                </span>
                                                <span class="inline-flex items-center gap-1">
                                                  <ArrowPathIcon class="h-4 w-4 text-blue-600" />
                                                  Remaining: <span class="font-semibold text-blue-700">{{ formatCurrency(milestoneStats(m).remaining, currentDisplayCurrency) }}</span>
                                                </span>
                                            </div>
                                            <button @click.stop="openBudgetModal(m)" class="text-gray-500 hover:text-blue-500 p-1 rounded-full bg-gray-100" v-if="activeTab === 'active'">
                                                <PencilSquareIcon v-if="hasMilestoneBudget(m)" class="h-5 w-5" />
                                                <PlusCircleIcon v-else class="h-5 w-5" />
                                            </button>
                                            <button @click.stop="toggle(m)" class="p-1">
                                                <ChevronDownIcon :class="['h-5 w-5 text-gray-500 transition-transform', m._collapsed ? '' : 'rotate-180']" />
                                            </button>
                                        </div>
                                    </div>

                                    <div v-show="!m._collapsed" class="border-t p-4 bg-gray-50" v-if="(activeTab === 'active' || activeTab === 'completed' ) || canDo('approve_milestones')">
                                        <div class="text-sm font-medium mb-2">Contracts</div>
                                        <div v-if="!m.expendable || !m.expendable.length" class="text-gray-500 text-sm">No contracts found.</div>
                                        <ul class="space-y-1">
                                            <li v-for="e in (m.expendable || [])" :key="e.id" class="flex items-center justify-between text-sm gap-2 p-2 rounded-lg bg-white shadow-sm">
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-block text-xs px-2 py-0.5 rounded-full"
                                                          :class="{
                                                              'bg-gray-100 text-gray-700': e.status === 'Pending Approval',
                                                              'bg-green-100 text-green-700': e.status === 'Accepted',
                                                              'bg-red-100 text-red-700': e.status === 'Rejected'
                                                          }">
                                                        {{ e.status || 'Pending Approval' }}
                                                    </span>
                                                    <div class="flex flex-col">
                                                        <span class="font-medium">{{ e.name }}</span>
                                                        <span v-if="e.user_name" class="text-xs text-gray-500">Contractor: {{ e.user_name }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span v-if="activeTab !== 'approved' || canManageFinancial">
                                                        {{ formatCurrency(e.amount, e.currency) }}
                                                        <span v-if="e.currency && e.currency.toUpperCase() !== currentDisplayCurrency.toUpperCase()" class="text-gray-500">
                                                          ({{ formatCurrency(convertCurrency(e.amount, e.currency, currentDisplayCurrency), currentDisplayCurrency) }})
                                                        </span>
                                                    </span>
                                                    <span v-else class="text-gray-400">Hidden</span>

                                                    <template v-if="e.status === 'Pending Approval' && activeTab !== 'approved'">
                                                        <button
                                                            v-if="canApproveMilestoneExpendables || canApproveExpendables"
                                                            @click.stop="approveExpendable(e)"
                                                            class="p-1 rounded-full text-green-600 hover:bg-green-100 transition-colors"
                                                            title="Approve">
                                                            <CheckCircleIcon class="h-5 w-5" />
                                                        </button>
                                                        <button
                                                            v-if="canApproveMilestoneExpendables || canApproveExpendables"
                                                            @click.stop="rejectExpendable(e)"
                                                            class="p-1 rounded-full text-red-600 hover:bg-red-100 transition-colors"
                                                            title="Reject">
                                                            <XCircleIcon class="h-5 w-5" />
                                                        </button>
                                                    </template>
                                                    <button
                                                        v-if="e.status === 'Rejected' && activeTab !== 'approved'"
                                                        @click.stop="deleteExpendable(e)"
                                                        class="p-1 rounded-full text-red-600 hover:bg-red-100 transition-colors"
                                                        title="Delete">
                                                        <TrashIcon class="h-5 w-5" />
                                                    </button>
                                                </div>
                                            </li>
                                        </ul>

                                        <!-- Milestone Actions -->
                                        <div class="mt-4 flex gap-2">
                                            <PrimaryButton v-if="m.status.toLowerCase() !== 'completed' && activeTab === 'active'" @click.stop="markComplete(m)">Mark Complete</PrimaryButton>
                                            <PrimaryButton v-else-if="m.status.toLowerCase() === 'completed' && activeTab === 'completed' && canApproveMilestones" @click.stop="approve(m)" class="bg-green-600 hover:bg-green-700">Approve Milestone</PrimaryButton>
                                            <SecondaryButton v-else-if="m.status.toLowerCase() === 'approved' && activeTab === 'approved' && canApproveMilestones" @click.stop="reopen(m)">Reopen Milestone</SecondaryButton>
                                            <PrimaryButton @click.stop="openExpendableModal(m)" class="flex items-center gap-1" v-if="activeTab === 'active'">
                                                <PlusIcon class="h-4 w-4"/> Add Contract
                                            </PrimaryButton>
                                            <PrimaryButton v-if="m.status.toLowerCase() === 'completed' && activeTab === 'completed' && canApproveMilestones" @click.stop="rejectMilestone(m)" class="bg-red-800 hover:bg-red-400">Reject Milestone</PrimaryButton>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <MilestoneExpendableModal
            :show="showExpendableModal"
            title="Add New Contract"
            :milestone="activeMilestone"
            :users="users"
            :currency-options="currencyOptions"
            @close="showExpendableModal = false"
            @submitted="onModalSubmitted"
            :project-total-budget="projectBudgetAmount"
            :project-budget-currency="projectBudgetCurrency"
            :milestone-stats="milestoneStats(activeMilestone)"
        />

        <MilestoneExpendableModal
            :show="showBudgetModal"
            :title="hasMilestoneBudget(activeMilestone) ? 'Update Milestone Budget' : 'Add Milestone Budget'"
            :milestone="activeMilestone"
            :users="users"
            :currency-options="currencyOptions"
            :is-budget-form="true"
            @close="showBudgetModal = false"
            @submitted="onModalSubmitted"
            :project-total-budget="projectBudgetAmount"
            :project-budget-currency="projectBudgetCurrency"
            :milestone-stats="milestoneStats(activeMilestone)"
        />

        <ReasonModal
            :show="showReasonModal"
            :title="activeMilestone?.action === 'approve' ? 'Approve Milestone' : activeMilestone?.action === 'reject' ? 'Reject Milestone' : activeMilestone?.action === 'reopen' ? 'Reopen Milestone' : (activeMilestone ? 'Provide Milestone Review' : (activeExpendable?.action === 'accept' ? 'Approve Contract' : (activeExpendable?.action === 'reject' ? 'Reject Contract' : 'Delete Contract')))"
            :message="activeMilestone?.action === 'approve' ? 'Please provide a reason for approving this milestone (required):' : activeMilestone?.action === 'reject' ? 'Please provide a reason for rejecting this milestone (required):' : activeMilestone?.action === 'reopen' ? 'Please provide a reason for reopening this milestone (required):' : (activeMilestone ? 'Please provide a review explaining why this milestone is complete (required):' : (activeExpendable?.action === 'accept' ? 'Please provide a reason for approval (required):' : (activeExpendable?.action === 'reject' ? 'Please provide a reason for rejection (required):' : 'Provide a reason for deleting this rejected expendable (required):')))"
            :type="activeMilestone?.action === 'reject' || activeMilestone?.action === 'reopen' ? 'warning' : 'info'"
            :api-endpoint="activeMilestone ? `/api/milestones/${activeMilestone.id}/${activeMilestone.action}` : activeExpendable?.apiEndpoint"
            :http-method="activeMilestone ? 'post' : activeExpendable?.httpMethod"
            @close="onReasonModalClose"
            @submitted="onModalSubmitted"
        />
    </AuthenticatedLayout>
</template>
