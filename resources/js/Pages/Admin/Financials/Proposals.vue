<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { usePermissions } from '@/Directives/permissions';
import { formatCurrency } from '@/Utils/currency';
import { success, error, confirmPrompt } from '@/Utils/notification';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import Dropdown from '@/Components/Dropdown.vue';
import ReasonModal from '@/Components/ProjectExpendables/ReasonModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import { 
    ChevronDownIcon, 
    ChevronUpIcon, 
    DocumentIcon, 
    ArrowDownTrayIcon,
    BanknotesIcon,
    CalendarIcon,
    UserIcon,
    FolderIcon,
    DocumentTextIcon,
    ArrowTopRightOnSquareIcon,
    ClockIcon,
    CheckCircleIcon,
    XCircleIcon,
    TagIcon
} from '@heroicons/vue/24/outline';

const { canDo } = usePermissions();
const canApproveExpendables = canDo('approve_expendables');
const canApproveMilestoneExpendables = canDo('approve_milestone_expendables');

const proposals = ref([]);
const projects = ref([]);
const loading = ref(true);
const activeTab = ref('active');
const filterStatus = ref('active');
const filterSearch = ref('');
const filterProjectId = ref('');
const filterDateFrom = ref('');
const filterDateTo = ref('');
const stats = ref(null);

const setTab = (tab) => {
    activeTab.value = tab;
    if (tab === 'active') filterStatus.value = 'active';
    else if (tab === 'pending') filterStatus.value = 'Pending Approval';
    else if (tab === 'completed') filterStatus.value = 'Completed';
    else if (tab === 'all') filterStatus.value = 'all';
    else if (tab === 'rejected') filterStatus.value = 'Rejected';
};

const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const showReasonModal = ref(false);
const reasonModalTitle = ref('');
const reasonModalMessage = ref('');
const reasonModalEndpoint = ref('');
const reasonModalMethod = ref('post');

const showSidebar = ref(false);
const selectedProposal = ref(null);

const openProposalSidebar = (proposal) => {
    selectedProposal.value = proposal;
    showSidebar.value = true;
};

const fetchProposals = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            status: filterStatus.value,
            search: filterSearch.value,
            project_id: filterProjectId.value,
            date_from: filterDateFrom.value,
            date_to: filterDateTo.value,
        };
        const { data } = await axios.get('/api/admin/proposals', { params });
        proposals.value = data.data;
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
        };
        if (selectedProposal.value) {
            const updated = data.data.find(p => p.id === selectedProposal.value.id);
            if (updated) {
                selectedProposal.value = updated;
            }
        }
    } catch (err) {
        error('Failed to load proposals.');
    } finally {
        loading.value = false;
    }
};

const fetchStats = async () => {
    try {
        const params = {
            status: filterStatus.value,
            search: filterSearch.value,
            project_id: filterProjectId.value,
            date_from: filterDateFrom.value,
            date_to: filterDateTo.value,
        };
        const { data } = await axios.get('/api/admin/proposals/stats', { params });
        stats.value = data;
    } catch (err) {
        console.error('Failed to fetch stats', err);
    }
};

const fetchProjects = async () => {
    try {
        const { data } = await axios.get('/api/projects-simplified');
        projects.value = (data || []).map(p => ({ id: p.id, name: p.name }));
    } catch (err) {
        console.error('Failed to fetch projects', err);
    }
};

const clearMainFilters = () => {
    filterSearch.value = '';
    filterProjectId.value = '';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    activeTab.value = 'active';
    filterStatus.value = 'active';
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        fetchProposals(page);
    }
};

watch([filterStatus, filterProjectId, filterDateFrom, filterDateTo], () => {
    fetchProposals(1);
    fetchStats();
});

let searchTimeout;
watch(filterSearch, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchProposals(1);
        fetchStats();
    }, 400);
});

// Actions
const approveProposal = (proposal) => {
    reasonModalTitle.value = 'Approve Proposal';
    reasonModalMessage.value = `Are you sure you want to approve proposal "${proposal.name}" for ${formatCurrency(proposal.amount, proposal.currency)}? Please provide approval reason or notes.`;
    reasonModalEndpoint.value = `/api/projects/${proposal.project_id}/expendables/${proposal.id}/accept`;
    reasonModalMethod.value = 'post';
    showReasonModal.value = true;
};

const rejectProposal = (proposal) => {
    reasonModalTitle.value = 'Reject Proposal';
    reasonModalMessage.value = `Are you sure you want to reject proposal "${proposal.name}"? Please provide a reason for rejection.`;
    reasonModalEndpoint.value = `/api/projects/${proposal.project_id}/expendables/${proposal.id}/reject`;
    reasonModalMethod.value = 'post';
    showReasonModal.value = true;
};

const completeProposal = (proposal) => {
    reasonModalTitle.value = 'Complete Proposal';
    reasonModalMessage.value = `Are you sure you want to mark proposal "${proposal.name}" as completed? Please provide a reason (e.g. project completed or cancelled).`;
    reasonModalEndpoint.value = `/api/projects/${proposal.project_id}/expendables/${proposal.id}/complete`;
    reasonModalMethod.value = 'post';
    showReasonModal.value = true;
};

const shortlistProposal = async (proposal, shortlist = true) => {
    const actionText = shortlist ? 'shortlist' : 'move back to pending';
    if (!await confirmPrompt(`Are you sure you want to ${actionText} proposal "${proposal.name}"?`)) return;

    try {
        await axios.post(`/api/projects/${proposal.project_id}/expendables/${proposal.id}/shortlist`, {
            shortlisted: shortlist,
        });
        success(shortlist ? 'Proposal shortlisted.' : 'Proposal moved back to pending.');
        fetchProposals(pagination.value.current_page);
        fetchStats();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to update shortlist status.');
    }
};

const onModalSubmitted = () => {
    showReasonModal.value = false;
    success('Operation successful!');
    fetchProposals(pagination.value.current_page);
    fetchStats();
};

onMounted(() => {
    fetchProposals(1);
    fetchProjects();
    fetchStats();
});

const getStatusClass = (status) => {
    if (!status) return 'bg-gray-100 text-gray-800';
    switch (status.toLowerCase()) {
        case 'accepted': return 'bg-green-100 text-green-800 border border-green-200';
        case 'completed': return 'bg-blue-100 text-blue-800 border border-blue-200';
        case 'shortlisted': return 'bg-indigo-100 text-indigo-800 border border-indigo-200';
        case 'pending approval': return 'bg-amber-100 text-amber-800 border border-amber-200';
        case 'rejected': return 'bg-red-100 text-red-800 border border-red-200';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getProposalPaymentInfo = (proposal) => {
    const totalAmount = Number(proposal.amount) || 0;
    let paidAmount = 0;
    
    if (proposal.bills && proposal.bills.length) {
        proposal.bills.forEach(bill => {
            if (bill.paid_amount != null) {
                paidAmount += Number(bill.paid_amount);
            } else if (bill.transactions && bill.transactions.length) {
                bill.transactions.forEach(tx => {
                    if (tx.is_paid) paidAmount += Number(tx.amount);
                });
            }
        });
    }
    
    const percentage = totalAmount > 0 ? Math.min(100, Math.round((paidAmount / totalAmount) * 100)) : 0;
    const remainingAmount = Math.max(0, totalAmount - paidAmount);
    
    return {
        paidAmount,
        totalAmount,
        remainingAmount,
        percentage
    };
};

const hasPermissionToApprove = (proposal) => {
    const isMilestone = proposal.expendable_type === 'App\\Models\\Milestone' || proposal.expendable_type === 'Milestone';
    if (isMilestone) {
        return canApproveMilestoneExpendables;
    }
    return canApproveExpendables;
};

const isMilestoneScope = (proposal) => {
    return proposal.expendable_type === 'App\\Models\\Milestone' || proposal.expendable_type === 'Milestone';
};

const paymentTypeLabel = {
    fixed: 'Fixed Price',
    installments: 'Custom Installments',
    milestone: 'Milestone Based',
    retainer: 'Monthly Retainer',
    hourly: 'Hourly',
};

const parsePaymentTerms = (terms) => {
    if (!terms) return null;
    if (typeof terms === 'object') return terms;
    try {
        return JSON.parse(terms);
    } catch {
        return null;
    }
};

const paymentTermsSummary = (terms) => {
    const parsed = parsePaymentTerms(terms);
    if (!parsed) {
        return terms || 'No payment terms provided.';
    }

    const type = parsed.type || 'custom';
    if (['installments', 'milestone', 'fixed'].includes(type) && Array.isArray(parsed.installments)) {
        const split = parsed.installments
            .map(i => `${i.label}: ${Number(i.percentage || 0).toFixed(2)}%`)
            .join(' | ');
        return `${paymentTypeLabel[type] || 'Payment Plan'} - ${split}`;
    }
    if (type === 'retainer') {
        return `Retainer: ${parsed.months || 1} month(s)`;
    }
    if (type === 'hourly') {
        return `Hourly: ${parsed.hourly_rate || 0}/hr, est. ${parsed.estimated_hours || 0} hrs`;
    }
    return 'Custom payment terms';
};

const sortedActivities = (activities) => {
    if (!activities || !activities.length) return [];
    return [...activities].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
};

const formatActivityDate = (dateStr) => {
    if (!dateStr) return '---';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' at ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const getActivityEventInfo = (activity, proposal) => {
    const event = activity.event || activity.description || '';
    const desc = (activity.description || '').toLowerCase();
    
    let badgeClass = 'bg-gray-100 text-gray-700 border-gray-200';
    let label = activity.description || 'Activity';

    if (event === 'expendable.accepted' || desc.includes('accepted')) {
        badgeClass = 'bg-green-100 text-green-800 border-green-200';
        label = 'Accepted';
    } else if (event === 'expendable.rejected' || desc.includes('rejected')) {
        badgeClass = 'bg-red-100 text-red-800 border-red-200';
        label = 'Rejected';
    } else if (event === 'expendable.shortlisted' || desc.includes('shortlisted')) {
        badgeClass = 'bg-indigo-100 text-indigo-800 border-indigo-200';
        label = 'Shortlisted';
    } else if (event === 'expendable.unshortlisted' || desc.includes('unshortlisted') || desc.includes('moved back to pending')) {
        badgeClass = 'bg-amber-100 text-amber-800 border-amber-200';
        label = 'Moved to Pending';
    } else if (event === 'expendable.completed' || event === 'expendable.auto_completed' || desc.includes('completed')) {
        badgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
        label = event === 'expendable.auto_completed' ? 'Auto Completed' : 'Completed';
    } else if (event === 'created' || desc === 'created') {
        badgeClass = 'bg-purple-100 text-purple-800 border-purple-200';
        label = 'Created';
    } else if (event === 'updated' || desc === 'updated') {
        badgeClass = 'bg-slate-100 text-slate-700 border-slate-200';
        label = 'Updated';
    } else if (event === 'expendable.deleted' || desc.includes('deleted')) {
        badgeClass = 'bg-rose-100 text-rose-800 border-rose-200';
        label = 'Deleted';
    }

    return { label, badgeClass };
};

const presentableActivityAttributes = (attributes) => {
    if (!attributes) return [];
    const ignoredKeys = ['project_id', 'user_id', 'expendable_id', 'expendable_type', 'balance', 'name'];
    const friendlyNames = {
        amount: 'Amount',
        currency: 'Currency',
        status: 'Status',
        description: 'Description',
        payment_terms: 'Payment Terms'
    };

    return Object.entries(attributes)
        .filter(([key, val]) => !ignoredKeys.includes(key) && val !== null && val !== '')
        .map(([key, val]) => {
            return {
                key,
                label: friendlyNames[key] || key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' '),
                value: val
            };
        });
};
</script>

<template>
    <Head title="Proposals Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Proposals Management</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-[100%] px-4 sm:px-6 lg:px-8">
                <!-- Stats Dashboard Grid -->
                <div v-if="stats" class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                    <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Proposals</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-gray-900">{{ stats.total.count }}</div>
                        </div>
                    </div>
                    <div class="bg-white border border-amber-200 rounded-lg p-4 shadow-sm border-l-4 border-l-amber-500">
                        <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Pending</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-amber-950">{{ stats.pending.count }}</div>
                        </div>
                    </div>
                    <div class="bg-white border border-indigo-200 rounded-lg p-4 shadow-sm border-l-4 border-l-indigo-500">
                        <div class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">Shortlisted</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-indigo-950">{{ stats.shortlisted.count }}</div>
                        </div>
                    </div>
                    <div class="bg-white border border-emerald-200 rounded-lg p-4 shadow-sm border-l-4 border-l-emerald-500">
                        <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Accepted</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-emerald-950">{{ stats.accepted.count }}</div>
                        </div>
                    </div>
                    <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm border-l-4 border-l-blue-500">
                        <div class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Completed</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-blue-950">{{ stats.completed?.count || 0 }}</div>
                        </div>
                    </div>
                    <div class="bg-white border border-red-200 rounded-lg p-4 shadow-sm border-l-4 border-l-red-500">
                        <div class="text-xs font-semibold text-red-600 uppercase tracking-wider">Rejected</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-red-950">{{ stats.rejected.count }}</div>
                        </div>
                    </div>
                </div>

                <!-- Main Filters Row -->
                <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6 shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                        <!-- Search Input -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Search</label>
                            <input
                                v-model="filterSearch"
                                type="text"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200"
                                placeholder="Search proposal, project, user..."
                            />
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status Filter</label>
                            <select v-model="filterStatus" class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200">
                                <option value="active">Active Proposals</option>
                                <option value="all">All Statuses</option>
                                <option value="Pending Approval">Pending Approval</option>
                                <option value="Shortlisted">Shortlisted</option>
                                <option value="Accepted">Accepted</option>
                                <option value="Completed">Completed</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        
                        <!-- Project Filter -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Project</label>
                            <SelectDropdown
                                v-model="filterProjectId"
                                :options="projects"
                                value-key="id"
                                label-key="name"
                                placeholder="All Projects"
                            />
                        </div>

                        <!-- From Date -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">From Date</label>
                            <input
                                v-model="filterDateFrom"
                                type="date"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200"
                            />
                        </div>

                        <!-- To Date -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">To Date</label>
                            <input
                                v-model="filterDateTo"
                                type="date"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200"
                            />
                        </div>
                    </div>
                    
                    <!-- Clear Filters Button -->
                    <div v-if="filterSearch || filterProjectId || filterDateFrom || filterDateTo || (filterStatus && filterStatus !== 'active')" class="mt-3 flex justify-end">
                        <button
                            @click="clearMainFilters"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-900 flex items-center gap-1"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg border border-gray-200">
                    <!-- Tab Navigation Bar -->
                    <div class="border-b border-gray-200 bg-gray-50/80 px-4 py-3 flex flex-wrap items-center justify-between gap-3 sm:rounded-t-lg">
                        <nav class="flex space-x-1 sm:space-x-2" aria-label="Tabs">
                            <button
                                @click="setTab('active')"
                                :class="[
                                    activeTab === 'active' 
                                        ? 'bg-indigo-600 text-white font-bold shadow-sm' 
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium',
                                    'px-3.5 py-1.5 text-xs sm:text-sm rounded-lg transition-all flex items-center gap-2'
                                ]"
                            >
                                <span>Active Proposals</span>
                                <span v-if="stats" class="px-2 py-0.5 rounded-full text-[10px]" :class="activeTab === 'active' ? 'bg-indigo-700 text-white' : 'bg-gray-200 text-gray-700'">
                                    {{ (stats.pending?.count || 0) + (stats.shortlisted?.count || 0) + (stats.accepted?.count || 0) }}
                                </span>
                            </button>

                            <button
                                @click="setTab('pending')"
                                :class="[
                                    activeTab === 'pending' 
                                        ? 'bg-amber-600 text-white font-bold shadow-sm' 
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium',
                                    'px-3.5 py-1.5 text-xs sm:text-sm rounded-lg transition-all flex items-center gap-2'
                                ]"
                            >
                                <span>Pending</span>
                                <span v-if="stats" class="px-2 py-0.5 rounded-full text-[10px]" :class="activeTab === 'pending' ? 'bg-amber-700 text-white' : 'bg-gray-200 text-gray-700'">
                                    {{ stats.pending?.count || 0 }}
                                </span>
                            </button>

                            <button
                                @click="setTab('completed')"
                                :class="[
                                    activeTab === 'completed' 
                                        ? 'bg-blue-600 text-white font-bold shadow-sm' 
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium',
                                    'px-3.5 py-1.5 text-xs sm:text-sm rounded-lg transition-all flex items-center gap-2'
                                ]"
                            >
                                <span>Completed</span>
                                <span v-if="stats" class="px-2 py-0.5 rounded-full text-[10px]" :class="activeTab === 'completed' ? 'bg-blue-700 text-white' : 'bg-gray-200 text-gray-700'">
                                    {{ stats.completed?.count || 0 }}
                                </span>
                            </button>

                            <button
                                @click="setTab('all')"
                                :class="[
                                    activeTab === 'all' 
                                        ? 'bg-gray-800 text-white font-bold shadow-sm' 
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium',
                                    'px-3.5 py-1.5 text-xs sm:text-sm rounded-lg transition-all flex items-center gap-2'
                                ]"
                            >
                                <span>All Proposals</span>
                                <span v-if="stats" class="px-2 py-0.5 rounded-full text-[10px]" :class="activeTab === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-200 text-gray-700'">
                                    {{ stats.total?.count || 0 }}
                                </span>
                            </button>

                            <button
                                @click="setTab('rejected')"
                                :class="[
                                    activeTab === 'rejected' 
                                        ? 'bg-red-600 text-white font-bold shadow-sm' 
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium',
                                    'px-3.5 py-1.5 text-xs sm:text-sm rounded-lg transition-all flex items-center gap-2'
                                ]"
                            >
                                <span>Rejected</span>
                                <span v-if="stats" class="px-2 py-0.5 rounded-full text-[10px]" :class="activeTab === 'rejected' ? 'bg-red-700 text-white' : 'bg-gray-200 text-gray-700'">
                                    {{ stats.rejected?.count || 0 }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <div v-if="loading" class="p-12 text-center text-gray-500">Loading proposals...</div>
                    <div v-else-if="!proposals.length" class="p-12 text-center text-gray-500">No proposals found in this view.</div>
                    <div v-else class="w-full overflow-visible">
                        <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-8 px-3 py-3"></th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Proposal</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Scope</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Project</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Payment Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template v-for="proposal in proposals" :key="proposal.id">
                                    <tr class="hover:bg-gray-50 cursor-pointer" @click="openProposalSidebar(proposal)">
                                        <td class="px-3 py-3 text-center whitespace-nowrap">
                                            <ArrowTopRightOnSquareIcon class="w-4 h-4 text-gray-400 hover:text-indigo-600 transition-colors inline-block" />
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                            {{ proposal.created_at ? new Date(proposal.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '---' }}
                                        </td>
                                        <td class="px-4 py-3 text-xs min-w-[200px]">
                                            <div class="flex items-center gap-1.5 mb-1">
                                                <span class="px-1.5 py-0.5 rounded text-[11px] font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                    {{ proposal.expendable_number || ('OZX' + proposal.id) }}
                                                </span>
                                            </div>
                                            <div class="font-medium text-gray-900 leading-snug">{{ proposal.name }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                            <span v-if="isMilestoneScope(proposal)" class="px-2 py-0.5 rounded text-[11px] bg-indigo-50 text-indigo-700 font-semibold border border-indigo-100">
                                                Milestone: {{ proposal.expendable?.name || 'Milestone Scope' }}
                                            </span>
                                            <span v-else class="px-2 py-0.5 rounded text-[11px] bg-emerald-50 text-emerald-700 font-semibold border border-emerald-100">
                                                Project Scope
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 min-w-[150px]">
                                            {{ proposal.project?.name || '---' }}
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500">
                                            {{ proposal.user?.name || '---' }}
                                        </td>
                                        <!-- Unified Payment Status Column -->
                                        <td class="px-4 py-3 whitespace-nowrap relative">
                                            <div class="flex flex-col gap-1 w-max">
                                                <div class="text-xs font-bold text-gray-900 flex items-center justify-between gap-4">
                                                    <span>{{ formatCurrency(proposal.amount, proposal.currency) }}</span>
                                                    <span v-if="(proposal.status === 'Accepted' || proposal.status === 'Completed') && proposal.bills && proposal.bills.length" class="text-[10px] text-gray-400 font-medium">
                                                        {{ proposal.bills.filter(b => b.status === 'paid' || b.status === 'partial_paid').length }}/{{ proposal.bills.length }} Paid
                                                    </span>
                                                </div>
                                                
                                                <div v-if="proposal.status === 'Accepted' || proposal.status === 'Completed'" class="group relative z-10 hover:z-50 flex items-center gap-2 cursor-help mt-0.5">
                                                    <div class="w-32 bg-gray-200 rounded-full h-1.5 overflow-hidden flex shadow-inner">
                                                        <div
                                                            class="h-full rounded-full transition-all duration-500"
                                                            :class="[
                                                                getProposalPaymentInfo(proposal).percentage === 0 ? 'bg-gray-300' :
                                                                getProposalPaymentInfo(proposal).percentage >= 100 ? 'bg-emerald-500' :
                                                                'bg-gradient-to-r from-amber-400 to-indigo-600'
                                                            ]"
                                                            :style="{ width: `${getProposalPaymentInfo(proposal).percentage}%` }"
                                                        ></div>
                                                    </div>
                                                    
                                                    <!-- Combined Tooltip Card -->
                                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-50 w-56 bg-gray-900 text-white text-xs rounded-lg p-3 shadow-xl border border-gray-700">
                                                        <div class="font-bold text-gray-200 border-b border-gray-700 pb-1 mb-1.5 flex justify-between items-center">
                                                            <span>Payment Progress</span>
                                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-800 text-emerald-400 font-bold">{{ getProposalPaymentInfo(proposal).percentage }}%</span>
                                                        </div>
                                                        <div class="space-y-1.5 text-[11px] mb-2 border-b border-gray-700 pb-2">
                                                            <div class="flex justify-between">
                                                                <span class="text-gray-400">Total:</span>
                                                                <span class="font-semibold">{{ formatCurrency(getProposalPaymentInfo(proposal).totalAmount, proposal.currency) }}</span>
                                                            </div>
                                                            <div class="flex justify-between">
                                                                <span class="text-gray-400">Paid:</span>
                                                                <span class="font-semibold text-emerald-400">{{ formatCurrency(getProposalPaymentInfo(proposal).paidAmount, proposal.currency) }}</span>
                                                            </div>
                                                            <div class="flex justify-between">
                                                                <span class="text-gray-400">Remaining:</span>
                                                                <span class="font-semibold text-amber-400">{{ formatCurrency(getProposalPaymentInfo(proposal).remainingAmount, proposal.currency) }}</span>
                                                            </div>
                                                        </div>
                                                        <div class="font-bold text-gray-200 border-b border-gray-700 pb-1 mb-1.5">
                                                            Bills Summary
                                                        </div>
                                                        <div class="space-y-1.5 text-[11px]" v-if="proposal.bills && proposal.bills.length">
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex items-center gap-1.5">
                                                                    <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                                                    <span class="text-gray-300">Total Bills:</span>
                                                                </div>
                                                                <span class="font-semibold text-gray-200">{{ proposal.bills.length }}</span>
                                                            </div>
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex items-center gap-1.5">
                                                                    <span class="w-2 h-2 rounded-full bg-green-400"></span>
                                                                    <span class="text-gray-300">Approved:</span>
                                                                </div>
                                                                <span class="font-semibold text-green-400">{{ proposal.bills.filter(b => b.status === 'approved').length }}</span>
                                                            </div>
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex items-center gap-1.5">
                                                                    <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                                                                    <span class="text-gray-300">Paid:</span>
                                                                </div>
                                                                <span class="font-semibold text-blue-400">{{ proposal.bills.filter(b => b.status === 'paid' || b.status === 'partial_paid').length }}</span>
                                                            </div>
                                                        </div>
                                                        <div v-else class="text-gray-400 text-[10px] italic">No bills assigned</div>
                                                        <!-- Tooltip arrow -->
                                                        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span :class="['px-2 py-0.5 text-[11px] font-bold rounded-full', getStatusClass(proposal.status)]">
                                                {{ proposal.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs font-medium whitespace-nowrap" @click.stop>
                                            <div class="flex justify-end" v-if="hasPermissionToApprove(proposal)">
                                                <Dropdown align="right" width="48">
                                                    <template #trigger>
                                                        <button class="text-gray-500 hover:text-gray-700 focus:outline-none p-1 rounded-full hover:bg-gray-100">
                                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                                            </svg>
                                                        </button>
                                                    </template>
                                                    <template #content>
                                                        <button
                                                            v-if="proposal.status === 'Pending Approval' || proposal.status === 'Shortlisted' || proposal.status === 'Rejected'"
                                                            @click="approveProposal(proposal)"
                                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-green-700 hover:bg-green-50 font-semibold"
                                                        >
                                                            Accept
                                                        </button>
                                                        <button
                                                            v-if="(proposal.status === 'Pending Approval' || proposal.status === 'Shortlisted' || proposal.status === 'Accepted') && (!proposal.bills || !proposal.bills.length)"
                                                            @click="rejectProposal(proposal)"
                                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-700 hover:bg-red-50 font-semibold"
                                                        >
                                                            Reject
                                                        </button>
                                                        <button
                                                            v-if="proposal.status === 'Accepted' || proposal.status === 'Pending Approval' || proposal.status === 'Shortlisted'"
                                                            @click="completeProposal(proposal)"
                                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-blue-700 hover:bg-blue-50 font-semibold"
                                                        >
                                                            Complete Proposal
                                                        </button>
                                                        <button
                                                            v-if="proposal.status === 'Pending Approval' || proposal.status === 'Rejected'"
                                                            @click="shortlistProposal(proposal, true)"
                                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-indigo-700 hover:bg-indigo-50 font-semibold"
                                                        >
                                                            Shortlist
                                                        </button>
                                                        <button
                                                            v-if="proposal.status === 'Shortlisted'"
                                                            @click="shortlistProposal(proposal, false)"
                                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-50 font-semibold"
                                                        >
                                                            Move to Pending
                                                        </button>
                                                        <button
                                                            v-if="proposal.status === 'Rejected'"
                                                            @click="shortlistProposal(proposal, false)"
                                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-50 font-semibold"
                                                        >
                                                            Move to Pending
                                                        </button>
                                                        <span v-if="proposal.status === 'Completed'" class="block px-4 py-2 text-start text-xs text-gray-400 font-semibold italic">
                                                            Proposal Completed
                                                        </span>
                                                    </template>
                                                </Dropdown>
                                            </div>
                                            <div v-else class="text-xs text-gray-400 italic">No Permissions</div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <!-- Pagination Footer -->
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between sm:rounded-b-lg">
                            <div class="text-sm text-gray-600">
                                Total: <span class="font-semibold">{{ pagination.total }}</span> proposals
                            </div>
                            <div class="flex items-center gap-2" v-if="pagination.last_page > 1">
                                <SecondaryButton
                                    @click="changePage(pagination.current_page - 1)"
                                    :disabled="pagination.current_page === 1"
                                >
                                    Previous
                                </SecondaryButton>
                                <span class="text-sm text-gray-600">
                                    Page {{ pagination.current_page }} of {{ pagination.last_page }}
                                </span>
                                <SecondaryButton
                                    @click="changePage(pagination.current_page + 1)"
                                    :disabled="pagination.current_page === pagination.last_page"
                                >
                                    Next
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reason Modal for Accept/Reject -->
        <ReasonModal
            :show="showReasonModal"
            :title="reasonModalTitle"
            :message="reasonModalMessage"
            :api-endpoint="reasonModalEndpoint"
            :http-method="reasonModalMethod"
            @close="showReasonModal = false"
            @submitted="onModalSubmitted"
        />

        <!-- Right Sidebar for Details & Bills -->
        <RightSidebar
            :show="showSidebar"
            @close="showSidebar = false"
            title="Proposal Details & Financials"
            :initialWidth="50"
        >
            <template #content>
                <div v-if="selectedProposal" class="p-6 space-y-6">
                <!-- Proposal Header -->
                <div class="border-b border-gray-200 pb-6">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    {{ selectedProposal.expendable_number || ('OZX' + selectedProposal.id) }}
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900">{{ selectedProposal.name }}</h3>
                            <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                                <CalendarIcon class="w-4 h-4" />
                                Created on {{ selectedProposal.created_at ? new Date(selectedProposal.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '---' }}
                            </p>
                        </div>
                        <span :class="['px-2.5 py-1 text-xs font-bold rounded-full border', getStatusClass(selectedProposal.status)]">
                            {{ selectedProposal.status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-6 bg-gray-50 rounded-lg p-4">
                        <div>
                            <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Project</span>
                            <span class="text-sm font-semibold text-gray-900 flex items-center gap-1.5 mt-1">
                                <FolderIcon class="w-4 h-4 text-gray-400" />
                                {{ selectedProposal.project?.name || '---' }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted By</span>
                            <span class="text-sm font-semibold text-gray-900 flex items-center gap-1.5 mt-1">
                                <UserIcon class="w-4 h-4 text-gray-400" />
                                {{ selectedProposal.user?.name || '---' }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Proposal Amount</span>
                            <span class="text-base font-bold text-gray-900 flex items-center gap-1.5 mt-1">
                                <BanknotesIcon class="w-4 h-4 text-emerald-500" />
                                {{ formatCurrency(selectedProposal.amount, selectedProposal.currency) }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Scope Type</span>
                            <span class="text-sm font-semibold text-gray-900 mt-1 block">
                                <span v-if="isMilestoneScope(selectedProposal)" class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-indigo-50 text-indigo-700 font-semibold border border-indigo-100">
                                    Milestone: {{ selectedProposal.expendable?.name || 'Milestone Scope' }}
                                </span>
                                <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-emerald-50 text-emerald-700 font-semibold border border-emerald-100">
                                    Project Scope
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Description & Payment Terms -->
                <div class="space-y-4">
                    <div>
                        <h4 class="text-sm font-bold text-gray-800 flex items-center gap-1.5">
                            <DocumentTextIcon class="w-4 h-4 text-gray-500" />
                            Proposal Description
                        </h4>
                        <div class="mt-2 bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-600 whitespace-pre-wrap leading-relaxed">
                            {{ selectedProposal.description || 'No description provided.' }}
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-bold text-gray-800">Payment Terms</h4>
                        <div class="mt-2 bg-white border border-gray-200 rounded-lg p-3 text-xs italic text-gray-600">
                            {{ paymentTermsSummary(selectedProposal.payment_terms) }}
                        </div>
                    </div>
                </div>

                <!-- Attached Documents -->
                <div>
                    <h4 class="text-sm font-bold text-gray-800 mb-2">Attached Documents</h4>
                    <div v-if="selectedProposal.files && selectedProposal.files.length" class="grid grid-cols-1 gap-2">
                        <div v-for="file in selectedProposal.files" :key="file.id" class="flex items-center justify-between p-2.5 bg-white rounded border border-gray-200 shadow-sm">
                            <div class="flex items-center gap-2 min-w-0">
                                <DocumentIcon class="w-4 h-4 text-indigo-500 flex-shrink-0" />
                                <span class="text-xs font-medium text-gray-700 truncate max-w-xs" :title="file.filename">{{ file.filename }}</span>
                            </div>
                            <a
                                :href="`/api/files/${file.id}/download`"
                                target="_blank"
                                class="text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1 text-xs font-semibold"
                            >
                                <ArrowDownTrayIcon class="w-3.5 h-3.5" /> Download
                            </a>
                        </div>
                    </div>
                    <p v-else class="text-gray-500 text-xs italic">No documents attached.</p>
                </div>

                <!-- Bills Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h4 class="text-md font-bold text-gray-900 mb-4 flex items-center justify-between">
                        <span>Associated Contractor Bills</span>
                        <span class="text-xs font-semibold bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full border border-indigo-100">
                            {{ selectedProposal.bills ? selectedProposal.bills.length : 0 }} Bills Total
                        </span>
                    </h4>

                    <!-- Mini Bills Stats -->
                    <div class="grid grid-cols-3 gap-2 mb-4 text-center">
                        <div class="bg-amber-50 border border-amber-100 rounded-lg p-2">
                            <span class="block text-[10px] font-semibold text-amber-600 uppercase tracking-wider">Pending</span>
                            <span class="text-base font-bold text-amber-950 mt-0.5 block">
                                {{ selectedProposal.bills ? selectedProposal.bills.filter(b => b.status === 'pending_approval').length : 0 }}
                            </span>
                        </div>
                        <div class="bg-green-50 border border-green-100 rounded-lg p-2">
                            <span class="block text-[10px] font-semibold text-green-600 uppercase tracking-wider">Approved</span>
                            <span class="text-base font-bold text-green-950 mt-0.5 block">
                                {{ selectedProposal.bills ? selectedProposal.bills.filter(b => b.status === 'approved').length : 0 }}
                            </span>
                        </div>
                        <div class="bg-blue-50 border border-blue-100 rounded-lg p-2">
                            <span class="block text-[10px] font-semibold text-blue-600 uppercase tracking-wider">Paid / Partial</span>
                            <span class="text-base font-bold text-blue-950 mt-0.5 block">
                                {{ selectedProposal.bills ? selectedProposal.bills.filter(b => b.status === 'paid' || b.status === 'partial_paid').length : 0 }}
                            </span>
                        </div>
                    </div>

                    <!-- Bills List -->
                    <div v-if="selectedProposal.bills && selectedProposal.bills.length" class="space-y-4">
                        <div v-for="bill in selectedProposal.bills" :key="bill.id" class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                            <div class="bg-gray-50 px-4 py-3 flex justify-between items-center border-b border-gray-150">
                                <div>
                                    <span class="text-xs font-bold text-gray-700">OZB{{ bill.id }}</span>
                                    <span class="text-[10px] font-medium text-gray-400 ml-2" v-if="bill.due_date">
                                        Due: {{ new Date(bill.due_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) }}
                                    </span>
                                </div>
                                <span :class="['px-2 py-0.5 text-[10px] font-bold rounded-full', bill.status === 'approved' ? 'bg-green-100 text-green-800' : bill.status === 'pending_approval' ? 'bg-amber-100 text-amber-800' : bill.status === 'paid' ? 'bg-blue-100 text-blue-800' : bill.status === 'partial_paid' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800']">
                                    {{ bill.status }}
                                </span>
                            </div>
                            <div class="p-4 space-y-3">
                                <div class="flex justify-between items-center text-sm">
                                    <div class="text-gray-500">Contractor</div>
                                    <div class="font-medium text-gray-900">{{ bill.contractor?.name || '---' }}</div>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <div class="text-gray-500">Amount</div>
                                    <div class="font-semibold text-gray-900">{{ formatCurrency(bill.amount, bill.currency || selectedProposal.currency) }}</div>
                                </div>

                                <!-- Transactions nested breakdown -->
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <div class="text-xs font-bold text-gray-700 mb-2">Transactions</div>
                                    <div v-if="bill.transactions && bill.transactions.length" class="space-y-2">
                                        <div v-for="tx in bill.transactions" :key="tx.id" class="bg-gray-50 rounded p-2 text-xs border border-gray-100">
                                            <div class="flex justify-between items-center">
                                                <span class="font-medium text-gray-800 truncate max-w-[200px]" :title="tx.description">{{ tx.description || 'No description' }}</span>
                                                <span :class="['px-1.5 py-0.5 rounded-full text-[9px] font-bold', tx.is_paid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                                    {{ tx.is_paid ? 'Paid' : 'Unpaid' }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center mt-1 text-[10px] text-gray-400">
                                                <span>{{ tx.payment_date ? new Date(tx.payment_date).toLocaleDateString('en-GB') : (tx.created_at ? new Date(tx.created_at).toLocaleDateString('en-GB') : '---') }}</span>
                                                <span class="font-bold text-gray-700">{{ formatCurrency(tx.amount, tx.currency || bill.currency) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-gray-400 italic">No transactions recorded for this bill.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-gray-500 text-xs italic text-center py-4 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                        No bills have been added for this proposal.
                    </p>
                </div>

                <!-- Activity Log & History Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h4 class="text-md font-bold text-gray-900 mb-4 flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <ClockIcon class="w-5 h-5 text-indigo-500" />
                            Activity History & Audit Trail
                        </span>
                        <span class="text-xs font-semibold bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full border border-indigo-100">
                            {{ selectedProposal.activities ? selectedProposal.activities.length : 0 }} Events
                        </span>
                    </h4>

                    <div v-if="selectedProposal.activities && selectedProposal.activities.length" class="space-y-3">
                        <div 
                            v-for="activity in sortedActivities(selectedProposal.activities)" 
                            :key="activity.id"
                            class="relative bg-white border border-gray-200 rounded-lg p-3.5 shadow-sm hover:border-gray-300 transition-colors"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">
                                        {{ (activity.causer?.name || (activity.description === 'created' ? (selectedProposal.user?.name || 'P') : 'S')).charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-gray-900">
                                            {{ activity.causer?.name || (activity.description === 'created' ? (selectedProposal.user?.name || 'Proposer') : 'System') }}
                                        </div>
                                        <div class="text-[10px] text-gray-400">
                                            {{ formatActivityDate(activity.created_at) }}
                                        </div>
                                    </div>
                                </div>

                                <span :class="['px-2 py-0.5 text-[10px] font-bold rounded-full border', getActivityEventInfo(activity, selectedProposal).badgeClass]">
                                    {{ getActivityEventInfo(activity, selectedProposal).label }}
                                </span>
                            </div>

                            <!-- Log Description / Note -->
                            <p class="text-xs text-gray-700 mt-2 font-medium">
                                {{ activity.description }}
                            </p>

                            <!-- Reason if provided (e.g., on accept, reject, complete) -->
                            <div v-if="activity.properties?.reason" class="mt-2 text-xs bg-amber-50/70 border border-amber-200/80 rounded-md p-2.5 text-amber-900">
                                <span class="font-semibold block text-[10px] uppercase tracking-wider text-amber-700 mb-0.5">Reason / Note:</span>
                                <span class="italic whitespace-pre-wrap">{{ activity.properties.reason }}</span>
                            </div>

                            <!-- Changed Attributes if tracked -->
                            <div v-if="presentableActivityAttributes(activity.properties?.attributes).length" class="mt-2 space-y-1 bg-gray-50 border border-gray-150 rounded p-2.5 text-[11px] text-gray-600">
                                <div class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold mb-1">Changed Attributes</div>
                                <div v-for="attr in presentableActivityAttributes(activity.properties?.attributes)" :key="attr.key" class="grid grid-cols-3 gap-2 border-b border-gray-100 last:border-0 pb-1 last:pb-0">
                                    <div class="font-semibold text-gray-700 col-span-1 truncate" :title="attr.label">{{ attr.label }}</div>
                                    <div class="col-span-2 text-gray-600 truncate" :title="String(attr.value)">
                                        {{ String(attr.value).length > 80 ? String(attr.value).substring(0, 80) + '...' : attr.value }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-gray-500 text-xs italic text-center py-4 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                        No activity recorded for this proposal yet.
                    </p>
                </div>
            </div>
        </template>
    </RightSidebar>
    </AuthenticatedLayout>
</template>
