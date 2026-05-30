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
import InvoicesSection from '@/Components/ProjectInvoices/InvoicesSection.vue';
import ProjectProgressTimeline from '@/Components/ProjectProgressTimeline.vue';
import Modal from '@/Components/Modal.vue';
import BillManagement from '@/Components/ProjectExpendables/BillManagement.vue';
import ProjectShareModal from '@/Components/Modals/ProjectShareModal.vue';
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
    MagnifyingGlassIcon,
    DocumentTextIcon,
    ClipboardDocumentListIcon,
    PlayCircleIcon,
    BanknotesIcon
} from '@heroicons/vue/24/outline';

// -- State & Data --
const projects = ref([]);
const selectedProjectId = ref(null);
const loading = ref(false);
const activeView = ref('proposals'); // 'proposals', 'planning', 'execution', 'financials'
const activeTab = ref('active');
const milestones = ref([]);
const projectContracts = ref([]);
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
const showShareModal = ref(false);
const expandedProposalRows = ref({});

const projectContexts = ref([]);
const projectMeetings = ref([]);
const billFilterUser = ref('');
const billFilterMilestone = ref('');

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

// Props
const props = defineProps({
    transaction_types: {
        type: Array,
        default: () => [],
    }
});

const selectedProject = computed(() => {
    const project = projects.value.find(option => option.value === Number(selectedProjectId.value));

    if (!project) {
        return null;
    }

    return {
        id: Number(project.value),
        name: project.label,
    };
});

const tabs = [
    { id: 'active', label: 'Active Milestones' },
    { id: 'completed', label: 'Completed' },
    { id: 'approved', label: 'Approved' },
    { id: 'invoices', label: 'Sales Invoices' },
];

const VIEW_MODES = [
    { id: 'proposals', label: 'Proposals', icon: DocumentTextIcon, desc: 'Review, shortlist, and approve bids & contracts' },
    { id: 'planning', label: 'Planning', icon: ClipboardDocumentListIcon, desc: 'Structure milestones, define budgets, and tasks' },
    { id: 'execution', label: 'Execution & QA', icon: PlayCircleIcon, desc: 'Track progress, review tasks, and QA milestones' },
    { id: 'financials', label: 'Financials', icon: BanknotesIcon, desc: 'Manage budgets, bills, and project finances' },
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
    let total = 0;
    projectContracts.value.forEach(e => {
        if (e.status === 'Pending Approval' || e.status === 'Shortlisted') {
            total += convertCurrency(parseFloat(e.amount ?? 0), e.currency || currentDisplayCurrency.value, currentDisplayCurrency.value);
        }
    });
    (milestones.value || []).forEach(m => {
        (m.expendable || []).forEach(e => {
            if (e.status === 'Pending Approval' || e.status === 'Shortlisted') {
                total += convertCurrency(parseFloat(e.amount ?? 0), e.currency || currentDisplayCurrency.value, currentDisplayCurrency.value);
            }
        });
    });
    return total;
});

const unifiedContracts = computed(() => {
    let list = [...(projectContracts.value || []).map(e => ({ ...e, is_project_level: true }))];
    (milestones.value || []).forEach(m => {
        if (m.expendable && m.expendable.length) {
            m.expendable.forEach(e => {
                list.push({ ...e, is_project_level: false, milestone_name: m.name, milestone_id: m.id });
            });
        }
    });
    return list.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
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

const loadProjectContracts = async () => {
    if (!selectedProjectId.value) return;
    try {
        const { data } = await window.axios.get(`/api/projects/${selectedProjectId.value}/expendables?type=project_contracts`);
        projectContracts.value = data || [];
    } catch (e) {
        console.error(e);
        error('Failed to load project contracts');
    }
};

const loadProjectContexts = async () => {
    if (!selectedProjectId.value) return;
    try {
        const { data } = await window.axios.get(`/api/projects/${selectedProjectId.value}/contexts`);
        projectContexts.value = data || [];
    } catch (e) {
        console.error(e);
    }
};

const loadProjectMeetings = async () => {
    if (!selectedProjectId.value) return;
    try {
        const { data } = await window.axios.get(`/api/projects/${selectedProjectId.value}/meetings`);
        projectMeetings.value = data || [];
    } catch (e) {
        console.error(e);
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
        projectContracts.value = [];
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
    await Promise.all([
        loadMilestones(), 
        loadUsers(), 
        loadProjectBudget(), 
        loadProjectContracts(),
        loadProjectContexts(),
        loadProjectMeetings()
    ]);
};

const onModalSubmitted = async () => {
    success('Operation successful!');
    showReasonModal.value = false;
    activeMilestone.value = null;
    activeExpendable.value = null;
    // Refresh milestones and financial stats after any submission (including budget updates)
    if (selectedProjectId.value) {
        await Promise.all([loadMilestones(), loadProjectBudget(), loadProjectContracts()]);
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

const openProjectExpendableModal = () => {
    activeMilestone.value = null;
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

const toggleProposalDetails = (proposalId) => {
    expandedProposalRows.value = {
        ...expandedProposalRows.value,
        [proposalId]: !expandedProposalRows.value[proposalId],
    };
};

const isProposalExpanded = (proposalId) => !!expandedProposalRows.value[proposalId];

const coverLetterPreview = (text, max = 180) => {
    if (!text) return 'No cover letter provided.';
    const clean = String(text).trim();
    if (clean.length <= max) return clean;
    return `${clean.slice(0, max)}...`;
};

const proposalReference = (proposal) => `OZP-${proposal?.id ?? 'N/A'}`;

const proposerEmail = (proposal) => proposal?.user?.email || null;
const proposerPhone = (proposal) => proposal?.user?.metadata?.phone || null;

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

const paymentTermsSummary = (proposal) => {
    const parsed = parsePaymentTerms(proposal.payment_terms);
    if (!parsed) {
        return proposal.payment_terms || 'No payment terms provided.';
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

const paymentTermsBreakdown = (proposal) => {
    const parsed = parsePaymentTerms(proposal.payment_terms);
    if (!parsed || !Array.isArray(parsed.installments)) {
        return [];
    }

    const amount = Number(proposal.amount || 0);
    return parsed.installments.map((item) => {
        const pct = Number(item.percentage || 0);
        return {
            label: item.label || 'Installment',
            percentage: pct,
            amount: (amount * pct) / 100,
        };
    });
};

const shortlistProposal = async (proposal, shortlist = true) => {
    if (!proposal || !proposal.id || !selectedProjectId.value) return;

    try {
        await window.axios.post(`/api/projects/${selectedProjectId.value}/expendables/${proposal.id}/shortlist`, {
            shortlisted: shortlist,
        });
        success(shortlist ? 'Proposal shortlisted.' : 'Proposal moved back to pending.');
        await Promise.all([loadMilestones(), loadProjectContracts()]);
    } catch (e) {
        console.error(e);
        error(e?.response?.data?.message || 'Failed to update shortlist status.');
    }
};

const hasPendingContracts = (m) => {
    if (!m || !Array.isArray(m.expendable)) return false;
    return m.expendable.some(e => {
        const status = e?.status || 'Pending Approval';
        return status === 'Pending Approval' || status === 'Shortlisted';
    });
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
            await Promise.all([loadProjectBudget(), loadMilestones(), loadProjectContracts()]);
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
                    <button
                        v-if="selectedProjectId"
                        @click="showShareModal = true"
                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:border-emerald-700 focus:ring active:bg-emerald-700 transition ease-in-out duration-150"
                    >
                        Share Project
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- View Switcher -->
                <div v-if="selectedProjectId" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-200">
                        <button
                            v-for="view in VIEW_MODES"
                            :key="view.id"
                            @click="activeView = view.id"
                            class="flex flex-col items-center justify-center p-4 transition-colors relative"
                            :class="[
                                activeView === view.id ? 'bg-indigo-50 text-indigo-700' : 'hover:bg-gray-50 text-gray-600'
                            ]"
                        >
                            <div class="flex items-center gap-2 font-semibold text-lg mb-1">
                                <component :is="view.icon" class="w-6 h-6" :class="activeView === view.id ? 'text-indigo-600' : 'text-gray-400'" />
                                {{ view.label }}
                            </div>
                            <span class="text-xs text-center" :class="activeView === view.id ? 'text-indigo-500' : 'text-gray-400'">
                                {{ view.desc }}
                            </span>
                            <div v-if="activeView === view.id" class="absolute bottom-0 left-0 right-0 h-1 bg-indigo-600"></div>
                        </button>
                    </div>
                </div>

                <!-- Project Glance (Planning View Only) -->
                <section v-if="selectedProjectId && activeView === 'planning'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Latest Context/Updates -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <DocumentTextIcon class="w-5 h-5 text-indigo-600" />
                            Latest Updates & Emails
                        </h3>
                        <div v-if="!projectContexts.length" class="text-sm text-gray-500 italic">No recent updates found.</div>
                        <ul v-else class="space-y-4">
                            <li v-for="context in projectContexts" :key="context.id" class="border-l-2 border-indigo-200 pl-4 py-1">
                                <div class="text-sm font-medium text-gray-800">{{ context.summary }}</div>
                                <div class="text-xs text-gray-500 mt-1 flex justify-between">
                                    <span>{{ context.user?.name || 'System' }}</span>
                                    <span>{{ new Date(context.created_at).toLocaleDateString() }}</span>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Upcoming Meetings -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <PlayCircleIcon class="w-5 h-5 text-indigo-600" />
                            Upcoming Meetings
                        </h3>
                        <div v-if="!projectMeetings.length" class="text-sm text-gray-500 italic">No upcoming meetings scheduled.</div>
                        <ul v-else class="space-y-4">
                            <li v-for="meeting in projectMeetings.slice(0,3)" :key="meeting.id" class="flex items-start gap-3 bg-gray-50 p-3 rounded-lg">
                                <div class="bg-white border border-gray-200 rounded p-2 text-center min-w-[50px]">
                                    <div class="text-xs text-red-500 font-bold uppercase">{{ new Date(meeting.start_time).toLocaleDateString(undefined, { month: 'short' }) }}</div>
                                    <div class="text-lg font-bold text-gray-800 leading-none">{{ new Date(meeting.start_time).getDate() }}</div>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">{{ meeting.title || 'Project Sync' }}</div>
                                    <div class="text-xs text-gray-500 mt-1">{{ new Date(meeting.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Financial Summary Dashboard -->
                <section v-if="selectedProjectId && activeView === 'financials'">
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-2 mb-6 border-b pb-4">
                            <BanknotesIcon class="w-6 h-6 text-indigo-600" />
                            <h3 class="text-xl font-semibold text-gray-900">Financial Summary</h3>
                        </div>
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
                            <div :class="['rounded-lg p-5 shadow-sm flex flex-col justify-between', (expendableBudget.total_budget - expendableBudget.total_approved_contract_amount) >= 0 ? 'bg-emerald-50' : 'bg-rose-50']">
                                <div>
                                    <div :class="['text-sm font-medium mb-1', (expendableBudget.total_budget - expendableBudget.total_approved_contract_amount) >= 0 ? 'text-emerald-700' : 'text-rose-700']">Project Profit / Loss</div>
                                    <div :class="['text-3xl font-bold', (expendableBudget.total_budget - expendableBudget.total_approved_contract_amount) >= 0 ? 'text-emerald-900' : 'text-rose-900']">
                                        {{ fmtBudget(expendableBudget.total_budget - expendableBudget.total_approved_contract_amount) }}
                                    </div>
                                </div>
                                <p :class="['text-xs mt-2', (expendableBudget.total_budget - expendableBudget.total_approved_contract_amount) >= 0 ? 'text-emerald-600' : 'text-rose-600']">Total Budget minus Approved Contracts.</p>
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
                    </div>
                </section>

                <!-- Project Progress Timeline -->
                <section v-if="selectedProjectId && (activeView === 'execution' || activeView === 'financials')">
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-2 mb-4">
                            <PlayCircleIcon class="w-6 h-6 text-indigo-600" />
                            <h3 class="text-xl font-semibold text-gray-900">Project Progress</h3>
                        </div>
                        <ProjectProgressTimeline :milestones="milestones" />
                    </div>
                </section>

                <!-- Proposals Section: All pending/rejected contracts (project + milestone level) -->
                <section v-if="selectedProjectId && activeView === 'proposals'" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between border-b pb-4 mb-4">
                        <div class="flex items-center gap-2">
                            <DocumentTextIcon class="w-6 h-6 text-indigo-600" />
                            <h3 class="text-xl font-semibold text-gray-900">Proposals & Pending Contracts</h3>
                            <span class="ml-2 text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full" title="Shows all project-level and milestone-level contracts that are pending or rejected">
                                All proposals across milestones
                            </span>
                        </div>
                        <PrimaryButton @click="openProjectExpendableModal" class="flex items-center gap-1">
                            <PlusIcon class="h-4 w-4" /> Add Proposal/Contract
                        </PrimaryButton>
                    </div>
                    <div v-if="!unifiedContracts.filter(e => e.status !== 'Accepted').length" class="text-center text-gray-500 text-sm py-8">
                        <DocumentTextIcon class="h-10 w-10 mx-auto mb-2 text-gray-300" />
                        No pending proposals or contracts found.
                    </div>
                    <ul v-else class="space-y-3">
                        <li v-for="e in unifiedContracts.filter(c => c.status !== 'Accepted')" :key="e.id"
                            class="p-4 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 transition-colors">
                            <!-- Contract Header Row -->
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase shadow-sm border"
                                          :class="{
                                              'bg-amber-50 text-amber-700 border-amber-200': e.status === 'Pending Approval' || !e.status,
                                              'bg-indigo-50 text-indigo-700 border-indigo-200': e.status === 'Shortlisted',
                                              'bg-rose-50 text-rose-700 border-rose-200': e.status === 'Rejected'
                                          }">
                                        {{ e.status || 'Pending Approval' }}
                                    </span>
                                    <div>
                                        <div class="text-sm font-bold text-gray-900">{{ e.name }}</div>
                                        <div class="text-[11px] font-semibold text-indigo-600 mt-0.5">{{ proposalReference(e) }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5 flex flex-wrap gap-2">
                                            <span v-if="e.user && e.user.name" class="font-medium">{{ e.user.name }}</span>
                                            <a v-if="proposerEmail(e)" :href="`mailto:${proposerEmail(e)}`" class="text-indigo-600 hover:underline">{{ proposerEmail(e) }}</a>
                                            <a v-if="proposerPhone(e)" :href="`tel:${proposerPhone(e)}`" class="text-emerald-600 hover:underline">{{ proposerPhone(e) }}</a>
                                            <span v-if="e.milestone_name" class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded text-[10px] font-semibold">
                                                📌 {{ e.milestone_name }}
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded text-[10px] font-semibold">
                                                🏗 Project Level
                                            </span>
                                            <span>&bull; {{ paymentTermsSummary(e) }}</span>
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">{{ coverLetterPreview(e.description) }}</div>
                                    </div>
                                </div>
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full sm:w-auto">
                                    <div class="text-right sm:text-left flex-shrink-0">
                                        <div class="text-sm font-bold text-gray-900">
                                            {{ formatCurrency(convertCurrency(parseFloat(e.amount ?? 0), e.currency || currentDisplayCurrency, currentDisplayCurrency), currentDisplayCurrency) }}
                                        </div>
                                        <div v-if="e.currency && e.currency?.toUpperCase() !== currentDisplayCurrency?.toUpperCase()" class="text-gray-400 text-[10px] font-medium">
                                            ({{ formatCurrency(parseFloat(e.amount ?? 0), e.currency) }})
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <template v-if="e.status === 'Pending Approval' || e.status === 'Shortlisted' || !e.status">
                                            <button
                                                v-if="canApproveExpendables || canApproveMilestoneExpendables"
                                                @click.stop="shortlistProposal(e, e.status !== 'Shortlisted')"
                                                class="inline-flex items-center justify-center px-2.5 py-1.5 border rounded-md text-xs font-semibold transition-colors"
                                                :class="e.status === 'Shortlisted'
                                                    ? 'border-slate-300 text-slate-700 bg-slate-50 hover:bg-slate-100'
                                                    : 'border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100'"
                                                :title="e.status === 'Shortlisted' ? 'Move back to pending' : 'Shortlist proposal'"
                                            >
                                                {{ e.status === 'Shortlisted' ? 'Unshortlist' : 'Shortlist' }}
                                            </button>
                                            <button v-if="canApproveExpendables || canApproveMilestoneExpendables" @click.stop="approveExpendable(e)" class="inline-flex items-center justify-center p-1.5 border border-emerald-200 rounded-md text-emerald-600 bg-emerald-50 hover:bg-emerald-100 hover:border-emerald-300 transition-colors" title="Approve">
                                                <CheckCircleIcon class="h-4 w-4" />
                                            </button>
                                            <button v-if="canApproveExpendables || canApproveMilestoneExpendables" @click.stop="rejectExpendable(e)" class="inline-flex items-center justify-center p-1.5 border border-rose-200 rounded-md text-rose-600 bg-rose-50 hover:bg-rose-100 hover:border-rose-300 transition-colors" title="Reject">
                                                <XCircleIcon class="h-4 w-4" />
                                            </button>
                                        </template>
                                        <button v-if="e.status === 'Rejected'" @click.stop="deleteExpendable(e)" class="inline-flex items-center justify-center p-1.5 border border-gray-200 rounded-md text-gray-500 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-200 transition-colors" title="Delete">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button
                                    @click.stop="toggleProposalDetails(e.id)"
                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                                >
                                    {{ isProposalExpanded(e.id) ? 'Hide full proposal' : 'Open full proposal' }}
                                </button>
                            </div>

                            <div v-if="isProposalExpanded(e.id)" class="mt-3 border-t border-gray-200 pt-3 space-y-3">
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold mb-1">Cover Letter</p>
                                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ e.description || 'No cover letter provided.' }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold mb-1">Payment Terms</p>
                                    <p class="text-sm text-gray-700">{{ paymentTermsSummary(e) }}</p>
                                    <ul v-if="paymentTermsBreakdown(e).length" class="mt-2 space-y-1">
                                        <li v-for="(line, idx) in paymentTermsBreakdown(e)" :key="`${e.id}-pay-${idx}`" class="text-xs text-gray-600 flex items-center justify-between bg-gray-50 border border-gray-200 rounded px-2 py-1">
                                            <span>{{ line.label }} ({{ line.percentage.toFixed(2) }}%)</span>
                                            <span class="font-semibold text-gray-800">
                                                {{ formatCurrency(convertCurrency(line.amount, e.currency || currentDisplayCurrency, currentDisplayCurrency), currentDisplayCurrency) }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Financials: Approved Contracts Only with Bill Management -->
                <section v-if="selectedProjectId && activeView === 'financials'" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between border-b pb-4 mb-4">
                        <div class="flex items-center gap-2">
                            <DocumentTextIcon class="w-6 h-6 text-indigo-600" />
                            <h3 class="text-xl font-semibold text-gray-900">Approved Contracts & Bills</h3>
                            <span class="ml-2 text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full" title="Only approved contracts appear here for payment tracking">
                                Approved only
                            </span>
                        </div>
                    </div>
                    <div v-if="!projectContracts.filter(e => e.status === 'Accepted').length" class="text-center text-gray-500 text-sm py-8">
                        <BanknotesIcon class="h-10 w-10 mx-auto mb-2 text-gray-300" />
                        No approved contracts yet. Approve proposals in the Proposals tab.
                    </div>
                    <ul v-else class="space-y-4">
                        <li v-for="e in projectContracts.filter(c => c.status === 'Accepted')" :key="e.id"
                            class="rounded-xl border border-emerald-100 bg-emerald-50/30 overflow-hidden">
                            <!-- Contract Summary Header -->
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-4 border-b border-emerald-100">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Approved
                                    </span>
                                    <div>
                                        <div class="text-sm font-bold text-gray-900">{{ e.name }}</div>
                                        <div class="text-[11px] font-semibold text-indigo-600 mt-0.5">{{ proposalReference(e) }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5 flex gap-2">
                                            <span v-if="e.user && e.user.name">{{ e.user.name }}</span>
                                            <span v-if="e.payment_terms">&bull; Terms: {{ e.payment_terms }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-sm font-bold text-gray-900">
                                        {{ formatCurrency(convertCurrency(parseFloat(e.amount ?? 0), e.currency || currentDisplayCurrency, currentDisplayCurrency), currentDisplayCurrency) }}
                                    </div>
                                    <div v-if="e.currency && e.currency?.toUpperCase() !== currentDisplayCurrency?.toUpperCase()" class="text-gray-400 text-[10px] font-medium">
                                        ({{ formatCurrency(parseFloat(e.amount ?? 0), e.currency) }})
                                    </div>
                                </div>
                            </div>
                            <!-- Bill Management -->
                            <div class="p-4">
                                <BillManagement 
                                    :expendable="e" 
                                    :transaction-types="transaction_types"
                                    :can-approve="canApproveExpendables"
                                    @updated="loadProjectContracts"
                                />
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Milestone Section: Planning and Execution views only -->
                <section v-if="selectedProjectId && (activeView === 'planning' || activeView === 'execution')" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4 mb-4">
                        <div class="flex items-center gap-2">
                            <ClipboardDocumentListIcon v-if="activeView === 'planning'" class="w-6 h-6 text-indigo-600" />
                            <PlayCircleIcon v-else-if="activeView === 'execution'" class="w-6 h-6 text-indigo-600" />
                            <DocumentTextIcon v-else-if="activeView === 'proposals'" class="w-6 h-6 text-indigo-600" />
                            <BanknotesIcon v-else class="w-6 h-6 text-indigo-600" />
                            <h3 class="text-xl font-semibold text-gray-900">Project Milestones</h3>
                            <button
                                v-if="activeView === 'planning'"
                                @click="showMilestoneFormModal = true"
                                class="p-1.5 rounded-full text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 ml-2"
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
                        <div v-else-if="activeTab === 'invoices' && selectedProjectId">
                            <InvoicesSection 
                                :project="{ id: selectedProjectId }" 
                                :can-approve="canApproveMilestoneExpendables || canApproveExpendables"
                            />
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
                                        <!-- Budget Summary (Planning & Financials Only) -->
                                        <div v-if="activeView === 'planning' || activeView === 'financials'" class="hidden sm:flex items-center gap-4 border-r pr-4 border-gray-200">
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

                                        <!-- Budget Edit Icon (Planning Only) -->
                                        <button v-if="activeView === 'planning' && activeTab === 'active'" @click.stop="openBudgetModal(m)" class="p-2 rounded-full text-gray-500 hover:text-blue-500 hover:bg-gray-100 transition-colors">
                                            <PencilSquareIcon v-if="hasMilestoneBudget(m)" class="h-5 w-5" title="Edit Budget" />
                                            <PlusCircleIcon v-else class="h-5 w-5" title="Add Budget" />
                                        </button>

                                        <!-- Other Actions & Toggle Button Group -->
                                        <div class="flex items-center gap-2">
                                            <button @click.stop="openReasonsList(m)" class="p-2 rounded-full text-blue-600 hover:bg-blue-100 transition-colors" title="View reasons/history">
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
                                    <!-- Task Stats (Execution & Planning) -->
                                    <div v-if="activeView === 'planning' || activeView === 'execution'" class="mb-5">
                                        <h5 class="text-sm font-semibold text-gray-800 mb-2">Task Stats</h5>
                                        <div class="flex flex-wrap gap-2 text-xs">
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 border border-gray-200">
                                                Total <span class="font-bold text-gray-900">{{ m.tasks_total_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 border border-slate-200">
                                                To Do <span class="font-bold text-slate-900">{{ m.tasks_todo_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-700 border border-blue-200">
                                                In Progress <span class="font-bold text-blue-900">{{ m.tasks_in_progress_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-yellow-100 text-yellow-700 border border-yellow-200">
                                                Paused <span class="font-bold text-yellow-900">{{ m.tasks_paused_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-orange-100 text-orange-700 border border-orange-200">
                                                Blocked <span class="font-bold text-orange-900">{{ m.tasks_blocked_count || 0 }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-green-100 text-green-700 border border-green-200">
                                                Done <span class="font-bold text-green-900">{{ m.tasks_done_count || 0 }}</span>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Contracts / Proposals: Planning View Only -->
                                    <template v-if="activeView === 'planning'">
                                        <div class="flex justify-between items-center mb-4 mt-6">
                                            <h4 class="text-lg font-semibold text-gray-800">Milestone Contracts</h4>
                                            <div class="flex items-center gap-2">
                                                <PrimaryButton @click.stop="openExpendableModal(m)" class="flex items-center gap-1" v-if="activeTab === 'active'">
                                                    <PlusIcon class="h-4 w-4" /> Add Contract
                                                </PrimaryButton>
                                            </div>
                                        </div>
                                        <div v-if="!m.expendable || !m.expendable.length" class="text-center text-gray-500 text-sm py-4">No contracts found.</div>
                                        <div v-else class="border border-gray-200 rounded-lg overflow-hidden bg-white">
                                            <ul class="divide-y divide-gray-200">
                                                <li v-for="e in (m.expendable || [])" :key="e.id"
                                                    class="p-4 hover:bg-gray-50 transition-colors">
                                                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                                        <div class="flex items-center gap-3">
                                                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase shadow-sm border"
                                                                  :class="{
                                                                      'bg-amber-50 text-amber-700 border-amber-200': e.status === 'Pending Approval' || !e.status,
                                                                      'bg-indigo-50 text-indigo-700 border-indigo-200': e.status === 'Shortlisted',
                                                                      'bg-emerald-50 text-emerald-700 border-emerald-200': e.status === 'Accepted',
                                                                      'bg-rose-50 text-rose-700 border-rose-200': e.status === 'Rejected'
                                                                  }">
                                                                {{ e.status || 'Pending Approval' }}
                                                            </span>
                                                            <div>
                                                                <div class="text-sm font-bold text-gray-900">{{ e.name }}</div>
                                                                <div class="text-xs text-gray-500 mt-0.5 flex gap-2">
                                                                    <span v-if="e.user && e.user.name">{{ e.user.name }}</span>
                                                                    <a v-if="proposerEmail(e)" :href="`mailto:${proposerEmail(e)}`" class="text-indigo-600 hover:underline">{{ proposerEmail(e) }}</a>
                                                                    <a v-if="proposerPhone(e)" :href="`tel:${proposerPhone(e)}`" class="text-emerald-600 hover:underline">{{ proposerPhone(e) }}</a>
                                                                    <span>&bull; {{ paymentTermsSummary(e) }}</span>
                                                                </div>
                                                                <div class="text-xs text-gray-600 mt-1">{{ coverLetterPreview(e.description, 140) }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full sm:w-auto">
                                                            <div class="text-right sm:text-left flex-shrink-0">
                                                                <div class="text-sm font-bold text-gray-900">
                                                                    {{ formatCurrency(convertCurrency(parseFloat(e.amount ?? 0), e.currency || currentDisplayCurrency, currentDisplayCurrency), currentDisplayCurrency) }}
                                                                </div>
                                                                <div v-if="e.currency && e.currency?.toUpperCase() !== currentDisplayCurrency?.toUpperCase()" class="text-gray-400 text-[10px] font-medium">
                                                                    ({{ formatCurrency(parseFloat(e.amount ?? 0), e.currency) }})
                                                                </div>
                                                            </div>
                                                            <div class="flex gap-2">
                                                                <!-- Add Tasks: Planning Only, Accepted contracts -->
                                                                <button v-if="e.status === 'Accepted'" @click.stop="onAddTasksClick(m, e)" class="inline-flex items-center justify-center p-1.5 border border-indigo-200 rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-300 transition-colors" title="Add tasks">
                                                                    <PlusIcon class="h-4 w-4" />
                                                                </button>
                                                                <!-- Contract Action Buttons -->
                                                                <template v-if="(e.status === 'Pending Approval' || e.status === 'Shortlisted' || !e.status) && activeTab !== 'approved'">
                                                                    <button
                                                                        v-if="canApproveMilestoneExpendables || canApproveExpendables"
                                                                        @click.stop="shortlistProposal(e, e.status !== 'Shortlisted')"
                                                                        class="inline-flex items-center justify-center px-2 py-1 border rounded-md text-[11px] font-semibold transition-colors"
                                                                        :class="e.status === 'Shortlisted'
                                                                            ? 'border-slate-300 text-slate-700 bg-slate-50 hover:bg-slate-100'
                                                                            : 'border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100'"
                                                                    >
                                                                        {{ e.status === 'Shortlisted' ? 'Unshortlist' : 'Shortlist' }}
                                                                    </button>
                                                                    <button v-if="canApproveMilestoneExpendables || canApproveExpendables" @click.stop="approveExpendable(e)" class="inline-flex items-center justify-center p-1.5 border border-emerald-200 rounded-md text-emerald-600 bg-emerald-50 hover:bg-emerald-100 hover:border-emerald-300 transition-colors" title="Approve">
                                                                        <CheckCircleIcon class="h-4 w-4" />
                                                                    </button>
                                                                    <button v-if="canApproveMilestoneExpendables || canApproveExpendables" @click.stop="rejectExpendable(e)" class="inline-flex items-center justify-center p-1.5 border border-rose-200 rounded-md text-rose-600 bg-rose-50 hover:bg-rose-100 hover:border-rose-300 transition-colors" title="Reject">
                                                                        <XCircleIcon class="h-4 w-4" />
                                                                    </button>
                                                                </template>
                                                                <button v-if="e.status === 'Rejected' && activeTab !== 'approved'" @click.stop="deleteExpendable(e)" class="inline-flex items-center justify-center p-1.5 border border-gray-200 rounded-md text-gray-500 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-200 transition-colors" title="Delete">
                                                                    <TrashIcon class="h-4 w-4" />
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mt-3 border-t border-gray-100 pt-3">
                                                        <button
                                                            @click.stop="toggleProposalDetails(e.id)"
                                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                                                        >
                                                            {{ isProposalExpanded(e.id) ? 'Hide full proposal' : 'Open full proposal' }}
                                                        </button>
                                                        <div v-if="isProposalExpanded(e.id)" class="mt-2 space-y-2">
                                                            <div>
                                                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold mb-1">Cover Letter</p>
                                                                <p class="text-sm text-gray-700 whitespace-pre-line">{{ e.description || 'No cover letter provided.' }}</p>
                                                            </div>
                                                            <div>
                                                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold mb-1">Payment Terms</p>
                                                                <p class="text-sm text-gray-700">{{ paymentTermsSummary(e) }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </template>

                                    <!-- Milestone-level Action Buttons -->
                                    <div v-if="activeView === 'planning' || activeView === 'execution'" class="mt-6 pt-4 border-t border-gray-200 flex flex-col items-start gap-2">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <!-- PM Button (Planning) -->
                                            <PrimaryButton
                                                v-if="activeView === 'planning' && m.status.toLowerCase() !== 'completed' && activeTab === 'active'"
                                                @click.stop="markComplete(m)"
                                                :disabled="hasPendingContracts(m) && hasAnyContracts(m)"
                                                :title="(hasPendingContracts(m) && hasAnyContracts(m)) ? 'Approve or reject all contracts before completing the milestone.' : ''"
                                            >
                                                Mark Complete (Ready for QA)
                                            </PrimaryButton>

                                            <!-- QA Buttons (Execution) -->
                                            <template v-if="activeView === 'execution' && canApproveMilestones">
                                                <PrimaryButton v-if="m.status.toLowerCase() === 'completed' && activeTab === 'completed'" @click.stop="approve(m)" class="bg-green-600 hover:bg-green-700">Approve Milestone</PrimaryButton>
                                                <PrimaryButton v-if="m.status.toLowerCase() === 'completed' && activeTab === 'completed'" @click.stop="rejectMilestone(m)" class="bg-red-600 hover:bg-red-700">Reject (Back to Planning)</PrimaryButton>
                                                <SecondaryButton v-if="m.status.toLowerCase() === 'approved' && activeTab === 'approved'" @click.stop="reopen(m)">Reopen Milestone</SecondaryButton>
                                            </template>
                                        </div>
                                        <p v-if="activeView === 'planning' && m.status.toLowerCase() !== 'completed' && activeTab === 'active' && hasPendingContracts(m) && hasAnyContracts(m)" class="text-sm text-red-600">
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
            :project-id="selectedProjectId"
            :milestones="milestones"
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

        <ProjectShareModal
            :show="showShareModal"
            :project="selectedProject || {}"
            :users="users"
            @close="showShareModal = false"
            @share-updated="onProjectChange"
        />
    </AuthenticatedLayout>
</template>
