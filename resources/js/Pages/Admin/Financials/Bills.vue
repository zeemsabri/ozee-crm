<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import { formatCurrency } from '@/Utils/currency';
import { success, error, confirmPrompt } from '@/Utils/notification';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { usePermissions } from '@/Directives/permissions';
import RightSidebar from '@/Components/RightSidebar.vue';

const bills = ref([]);
const projects = ref([]);
const expendables = ref([]);
const transactionTypes = ref([]);
const suppliers = ref([]);
const loading = ref(true);
const filterStatus = ref('pending_approval');
const filterProject = ref('');
const searchQuery = ref('');
const searchDebounce = ref(null);
const showCreateModal = ref(false);
const showXeroSyncModal = ref(false);
const xeroSyncLoading = ref(false);
const xeroSyncError = ref('');
const xeroCandidates = ref([]);

const pagination = ref({
    total: 0,
    current_page: 1,
    last_page: 1,
    per_page: 20,
});

watch([filterStatus, filterProject], () => {
    pagination.value.current_page = 1;
    fetchBills();
});

const selectedBillForHistory = ref(null);
const showHistorySidebar = ref(false);

const openHistorySidebar = (bill) => {
    selectedBillForHistory.value = bill;
    showHistorySidebar.value = true;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString();
};
const selectedXeroContactId = ref('');
const xeroSyncContractor = ref(null);
const approvalConfigByBillId = ref({});
const { canDo } = usePermissions();
const canViewBills = canDo('view_project_bills');
const canCreateBills = canDo('create_project_bills');
const canApproveBills = canDo('approve_project_bills');
const canVoidBills = canDo('void_project_bills');
const canDeleteBills = canDo('delete_project_bills');
const canRestoreBills = canDo('restore_project_bills');
const canLinkXeroContractors = canDo('link_xero_contractors');

const form = useForm({
    bill_type: 'contractor_bill',
    project_id: '',
    project_expendable_id: '',
    contractor_id: '',
    transaction_type_id: '',
    xero_account_code: '',
    xero_tax_type: 'INPUT',
    reference_number: '',
    due_date: '',
    currency: '',
    amount: '',
    payment_details: {
        payment_method: 'bank_transfer',
        account_name: '',
        account_number: '',
        bank_name: '',
        bsb: '',
        swift_code: '',
        iban: '',
        notes: '',
    },
    document: null,
});

const xeroAccounts = ref([]);
const showTransactionTypeModal = ref(false);
const newTransactionType = useForm({
    name: '',
    xero_account_code: '',
});

const xeroTaxTypeOptions = [
    { value: 'BASEXCLUDED', label: 'BAS Excluded' },
    { value: 'EXEMPTEXPENSES', label: 'GST Free Expenses' },
    { value: 'EXEMPTOUTPUT', label: 'GST Free Income' },
    { value: 'INPUT', label: 'GST on Expenses' },
    { value: 'INPUTTAXED', label: 'GST on Imports' },
    { value: 'OUTPUT', label: 'GST on Income' },
];

const selectedExpendable = computed(() => {
    return expendables.value.find((item) => String(item.id) === String(form.project_expendable_id)) || null;
});

const selectedTransactionType = computed(() => {
    return transactionTypes.value.find((item) => String(item.id) === String(form.transaction_type_id)) || null;
});

const xeroAccountOptions = computed(() => {
    return xeroAccounts.value
        .filter((account) => account && account.code)
        .map((account) => ({
            value: account.code,
            label: `${account.code} - ${account.name || 'Unnamed account'}`,
        }));
});

const authUser = computed(() => usePage().props.auth?.user);

const currentPendingStep = (bill) => {
    const steps = bill.approval_instance?.steps;
    if (!steps?.length) return null;
    return steps.find((s) => s.status === 'pending') || null;
};

const canApproveBill = (bill) => {
    if (!canApproveBills.value) return false;
    if (bill.status !== 'pending_approval') return false;
    const step = currentPendingStep(bill);
    if (!step) {
        // No active flow — only super admin can approve (API will guard)
        return !bill.approval_instance;
    }
    const user = authUser.value;
    if (!user) return false;
    if (step.approver_type === 'user') return Number(step.approver_user_id) === Number(user.id);
    if (step.approver_type === 'role') return Number(step.approver_role_id) === Number(user.role_id);
    return false;
};

const pendingApproverLabel = (bill) => {
    const step = currentPendingStep(bill);
    if (!step) return null;
    if (step.approver_type === 'user') return step.approver_user?.name ? `Awaiting: ${step.approver_user.name}` : null;
    if (step.approver_type === 'role') return step.approver_role?.name ? `Awaiting: ${step.approver_role.name} role` : null;
    return null;
};

const getApprovalConfig = (bill) => {
    if (!approvalConfigByBillId.value[bill.id]) {
        approvalConfigByBillId.value[bill.id] = {
            xero_account_code: bill.xero_account_code || bill.transaction_type?.xero_account_code || '',
            xero_tax_type: bill.xero_tax_type === 'NONE' ? 'EXEMPTEXPENSES' : (bill.xero_tax_type || 'INPUT'),
        };
    }

    return approvalConfigByBillId.value[bill.id];
};

const xeroCandidateOptions = computed(() => {
    return xeroCandidates.value.map((candidate) => ({
        value: candidate.contact_id,
        label: `${candidate.name || 'Unnamed Contact'}${candidate.email ? ` (${candidate.email})` : ''}`,
    }));
});

const projectOptions = computed(() => {
    return [
        { id: '', name: 'All Projects' },
        ...projects.value
    ];
});

const expendableOptions = computed(() => {
    return expendables.value.map(exp => ({
        id: exp.id,
        label: `${exp.name} (${exp.expendable_type?.replace('App\\Models\\', '') || 'Project'}) - Rem: ${formatCurrency(exp.balance, exp.currency)}`
    }));
});

const fetchBills = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/bills', { 
            params: { 
                status: filterStatus.value,
                project_id: filterProject.value,
                search: searchQuery.value,
                page: pagination.value.current_page
            } 
        });
        bills.value = data.data || [];
        pagination.value = {
            total: data.total || 0,
            current_page: data.current_page || 1,
            last_page: data.last_page || 1,
            per_page: data.per_page || 20,
        };
        bills.value.forEach((bill) => {
            getApprovalConfig(bill);
        });
    } catch (err) {
        error('Failed to load bills.');
    } finally {
        loading.value = false;
    }
};

const fetchProjects = async () => {
    try {
        const { data } = await axios.get('/api/projects-simplified');
        projects.value = [...(data || [])].sort((a, b) =>
            String(a?.name || '').localeCompare(String(b?.name || ''), undefined, { sensitivity: 'base' })
        );
    } catch (err) {
        console.error('Failed to fetch projects', err);
    }
};

const fetchTransactionTypes = async () => {
    try {
        const { data } = await axios.get('/api/transaction-types');
        transactionTypes.value = data || [];
    } catch (err) {
        error('Failed to load transaction types.');
    }
};

const fetchSuppliers = async () => {
    try {
        const { data } = await axios.get('/api/users?user_type=supplier');
        suppliers.value = data || [];
    } catch (err) {
        console.error('Failed to fetch suppliers', err);
    }
};

const fetchXeroAccounts = async () => {
    try {
        const { data } = await axios.get(route('api.xero.accounts', { category: 'expense' }));
        xeroAccounts.value = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('Failed to fetch Xero expense accounts', err);
        xeroAccounts.value = [];
    }
};

const submitNewTransactionType = () => {
    newTransactionType.processing = true;
    newTransactionType.clearErrors();
    axios.post('/api/transaction-types', {
        name: newTransactionType.name,
        xero_account_code: newTransactionType.xero_account_code
    }).then((res) => {
        showTransactionTypeModal.value = false;
        success('Transaction type created successfully.');
        fetchTransactionTypes().then(() => {
            if (res.data && res.data.id) {
                form.transaction_type_id = res.data.id;
            }
        });
        newTransactionType.reset();
    }).catch((err) => {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            newTransactionType.setError(err.response.data.errors);
        } else {
            error(err.response?.data?.message || 'Failed to create transaction type.');
        }
    }).finally(() => {
        newTransactionType.processing = false;
    });
};

watch(() => form.project_id, async (newId) => {
    if (!newId) {
        expendables.value = [];
        form.project_expendable_id = '';
        form.contractor_id = '';
        return;
    }
    try {
        const { data } = await axios.get(`/api/projects/${newId}/expendables`, {
            params: {
                for_billing: true,
            },
        });
        expendables.value = data;
        form.project_expendable_id = '';
        form.contractor_id = '';
    } catch (err) {
        console.error('Failed to fetch expendables', err);
    }
});

watch(() => form.project_expendable_id, () => {
    form.contractor_id = selectedExpendable.value?.user_id || '';
    if (!form.currency && selectedExpendable.value?.currency) {
        form.currency = selectedExpendable.value.currency;
    }
});

watch(() => form.transaction_type_id, () => {
    if (selectedTransactionType.value?.xero_account_code && !form.xero_account_code) {
        form.xero_account_code = selectedTransactionType.value.xero_account_code;
    }
});

const openCreateModal = () => {
    form.reset();
    form.bill_type = 'contractor_bill';
    form.payment_details = {
        payment_method: 'bank_transfer',
        account_name: '',
        account_number: '',
        bank_name: '',
        bsb: '',
        swift_code: '',
        iban: '',
        notes: '',
    };
    form.xero_tax_type = 'INPUT';
    form.reference_number = '';
    form.due_date = '';
    form.currency = '';
    form.document = null;
    showCreateModal.value = true;
};

const handleAttachmentUpload = (e, billId) => {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('document', file);

    const config = {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    };

    axios.post(`/api/bills/${billId}/attachments`, formData, config)
        .then(() => {
            success('Attachment uploaded successfully.');
            fetchBills(); // Refresh list to show the link
        })
        .catch((err) => {
            error(err.response?.data?.message || 'Failed to upload attachment.');
        });
};

const formatPaymentTerms = (terms) => {
    if (!terms) return '';
    let parsed = terms;
    if (typeof terms === 'string') {
        try {
            parsed = JSON.parse(terms);
        } catch (e) {
            return terms; // Return as-is if it's plain text
        }
    }
    
    if (parsed && typeof parsed === 'object') {
        if (parsed.type === 'installments' && Array.isArray(parsed.installments)) {
            return parsed.installments.map((inst, index) => `${index + 1}. ${inst.label} (${inst.percentage}%)`).join('\n');
        }
        // Fallback for other JSON formats
        return JSON.stringify(parsed, null, 2);
    }
    
    return terms;
};

const submitBill = () => {
    if (form.bill_type === 'contractor_bill') {
        if (!form.project_expendable_id) return error('Please select a contract.');
        if (!form.project_id) return error('Please select a project.');
        if (!form.contractor_id) return error('Selected contract does not have a contractor assigned.');
        if (!form.transaction_type_id) return error('Please select a transaction type.');
    } else {
        if (!form.project_id) return error('Please select a project.');
    }

    form.clearErrors();

    const formData = new FormData();
    formData.append('bill_type', form.bill_type);
    if (form.project_id) formData.append('project_id', form.project_id);
    if (form.project_expendable_id) formData.append('project_expendable_id', form.project_expendable_id);
    if (form.contractor_id) formData.append('contractor_id', form.contractor_id);
    if (form.transaction_type_id) formData.append('transaction_type_id', form.transaction_type_id);
    formData.append('xero_account_code', form.xero_account_code || selectedTransactionType.value?.xero_account_code || '');
    formData.append('xero_tax_type', form.xero_tax_type);
    if (form.reference_number) formData.append('reference_number', form.reference_number);
    if (form.due_date) formData.append('due_date', form.due_date);
    formData.append('currency', form.currency || 'AUD');
    formData.append('amount', form.amount);
    
    if (form.bill_type === 'contractor_bill') {
        formData.append('payment_details[payment_method]', form.payment_details.payment_method);
        if (form.payment_details.account_name) formData.append('payment_details[account_name]', form.payment_details.account_name);
        if (form.payment_details.account_number) formData.append('payment_details[account_number]', form.payment_details.account_number);
        if (form.payment_details.bank_name) formData.append('payment_details[bank_name]', form.payment_details.bank_name);
        if (form.payment_details.bsb) formData.append('payment_details[bsb]', form.payment_details.bsb);
        if (form.payment_details.swift_code) formData.append('payment_details[swift_code]', form.payment_details.swift_code);
        if (form.payment_details.iban) formData.append('payment_details[iban]', form.payment_details.iban);
        if (form.payment_details.notes) formData.append('payment_details[notes]', form.payment_details.notes);
    }

    if (form.document) {
        formData.append('document', form.document);
    }

    form.processing = true;

    axios.post(`/api/projects/${form.project_id}/bills`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
        .then(() => {
            showCreateModal.value = false;
            success('Bill created successfully.');
            fetchBills();
        })
        .catch((err) => {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                form.setError(err.response.data.errors);
                error(err.response?.data?.message || 'Please review the form and try again.');
                return;
            }

            error(err.response?.data?.message || 'Failed to create bill. Please review the form and try again.');
        })
        .finally(() => {
            form.processing = false;
        });
};

const approveBill = async (bill) => {
    if (!await confirmPrompt('Approve this bill for Xero sync?')) return;
    if (!canApproveBills.value) {
        error('You do not have permission to approve bills.');
        return;
    }
    try {
        const approvalConfig = getApprovalConfig(bill);
        if (!approvalConfig.xero_account_code) {
            error('Please set Xero Account before approving this bill.');
            return;
        }

        await axios.post(route('api.bills.approve', { bill: bill.id }), {
            xero_account_code: approvalConfig.xero_account_code,
            xero_tax_type: approvalConfig.xero_tax_type,
        });
        success('Bill approved.');
        fetchBills();
    } catch (err) {
        const message = err.response?.data?.message || 'Approval failed.';
        if (message.includes('not linked to Xero') && bill?.contractor?.id) {
            await openXeroSyncModal(bill.contractor);
            return;
        }

        error(message);
    }
};

const voidBill = async (bill) => {
    if (!await confirmPrompt('Void this bill?')) return;
    if (!canVoidBills.value) {
        error('You do not have permission to void bills.');
        return;
    }
    try {
        await axios.post(route('api.bills.void', { bill: bill.id }));
        success('Bill voided.');
        fetchBills();
    } catch (err) {
        error(err.response?.data?.message || 'Voiding failed.');
    }
};

const deleteBill = async (bill) => {
    if (!await confirmPrompt('Are you sure you want to delete this bill?')) return;
    if (!canDeleteBills.value) {
        error('You do not have permission to delete bills.');
        return;
    }
    try {
        await axios.delete(route('api.bills.destroy', { bill: bill.id }));
        success('Bill deleted.');
        fetchBills();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to delete bill.');
    }
};

const restoreBill = async (bill) => {
    if (!await confirmPrompt('Restore this deleted bill?')) return;
    if (!canRestoreBills.value) {
        error('You do not have permission to restore bills.');
        return;
    }
    try {
        await axios.post(route('api.bills.restore', { bill: bill.id }));
        success('Bill restored.');
        fetchBills();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to restore bill.');
    }
};

const fetchXeroCandidates = async () => {
    if (!xeroSyncContractor.value?.id) {
        return;
    }

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const response = await axios.get(`/api/users/${xeroSyncContractor.value.id}/xero-contact-candidates`);
        xeroCandidates.value = response.data?.candidates || [];

        if (xeroCandidates.value.length === 1) {
            selectedXeroContactId.value = xeroCandidates.value[0].contact_id;
        }
    } catch (err) {
        xeroSyncError.value = err.response?.data?.message || 'Failed to fetch Xero contacts.';
    } finally {
        xeroSyncLoading.value = false;
    }
};

const openXeroSyncModal = async (contractor) => {
    if (!canLinkXeroContractors.value) {
        error('You do not have permission to link contractors to Xero.');
        return;
    }

    xeroSyncContractor.value = contractor;
    xeroCandidates.value = [];
    selectedXeroContactId.value = '';
    xeroSyncError.value = '';
    showXeroSyncModal.value = true;

    await fetchXeroCandidates();
};

const syncContractorWithXero = async () => {
    if (!xeroSyncContractor.value?.id) {
        return;
    }

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const payload = selectedXeroContactId.value
            ? { selected_contact_id: selectedXeroContactId.value }
            : {};

        await axios.post(`/api/users/${xeroSyncContractor.value.id}/xero-contact-sync`, payload);
        success('Contractor linked to Xero successfully.');
        showXeroSyncModal.value = false;
        await fetchBills();
    } catch (err) {
        const responseData = err.response?.data || {};
        if (responseData.requires_selection && Array.isArray(responseData.candidates)) {
            xeroCandidates.value = responseData.candidates;
        }

        xeroSyncError.value = responseData.message || 'Failed to link contractor to Xero.';
        error(xeroSyncError.value);
    } finally {
        xeroSyncLoading.value = false;
    }
};

const createXeroContactForContractor = async () => {
    if (!xeroSyncContractor.value?.id) {
        return;
    }

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        await axios.post(`/api/users/${xeroSyncContractor.value.id}/xero-contact-create`);
        success('Xero contact created and linked successfully.');
        showXeroSyncModal.value = false;
        await fetchBills();
    } catch (err) {
        xeroSyncError.value = err.response?.data?.message || 'Failed to create Xero contact.';
        error(xeroSyncError.value);
    } finally {
        xeroSyncLoading.value = false;
    }
};

const handleSearch = () => {
    if (searchDebounce.value) clearTimeout(searchDebounce.value);
    searchDebounce.value = setTimeout(() => {
        fetchBills();
    }, 400);
};

onMounted(() => {
    fetchBills();
    fetchProjects();
    fetchSuppliers();
    fetchTransactionTypes();
    fetchXeroAccounts();
});

const viewBillDetails = (billId) => {
    router.visit(route('admin.financials.bills.show', { id: billId }));
};

const getStatusClass = (status, bill) => {
    if (bill && bill.deleted_at) return 'bg-gray-100 text-gray-700 border-gray-200 border';
    switch (status.toLowerCase()) {
        case 'approved': return 'bg-emerald-50 text-emerald-700 border-emerald-100 border';
        case 'pending_approval': return 'bg-amber-50 text-amber-700 border-amber-100 border';
        case 'void': return 'bg-rose-50 text-rose-700 border-rose-100 border';
        default: return 'bg-blue-50 text-blue-700 border-blue-100 border';
    }
};
</script>

<template>
    <Head title="Bills / Expenses" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bills / Expenses</h2>
                <div class="flex gap-4 items-center">
                    <PrimaryButton v-if="canCreateBills" @click="openCreateModal">
                        Create Bill
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-[100%] px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Stats Dashboard Grid -->
                <div v-if="!loading && bills.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                    <!-- Total Count Card -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm border-l-4 border-l-indigo-500">
                        <div class="text-[10px] font-semibold text-indigo-600 uppercase tracking-wider">Filtered Bills</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-indigo-950">{{ pagination.total }}</div>
                        </div>
                    </div>
                </div>

                <!-- Filters & Search Panel -->
                <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6 shadow-sm">
                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 items-end">
                        <!-- Search input -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Search Bills</label>
                            <TextInput
                                v-model="searchQuery"
                                @input="handleSearch"
                                type="text"
                                placeholder="Search by contractor name, amount, ref..."
                                class="w-full text-sm"
                            />
                        </div>

                        <!-- Project selection -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Project</label>
                            <SelectDropdown
                                v-model="filterProject"
                                :options="projectOptions"
                                valueKey="id"
                                labelKey="name"
                                placeholder="All Projects"
                            />
                        </div>

                        <!-- Status selector buttons -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                            <div class="flex bg-gray-50 border border-gray-200 p-1 rounded-lg w-full">
                                <button
                                    v-for="st in [
                                        { value: 'pending_approval', label: 'Pending' },
                                        { value: 'all', label: 'All' },
                                        { value: 'approved', label: 'Approved' },
                                        { value: 'void', label: 'Void' },
                                        { value: 'deleted', label: 'Deleted' }
                                    ]"
                                    :key="st.value"
                                    @click="filterStatus = st.value"
                                    :class="[
                                        'flex-1 text-center py-1.5 text-xs font-semibold rounded-md transition-colors whitespace-nowrap',
                                        filterStatus === st.value
                                            ? 'bg-indigo-600 text-white shadow-sm'
                                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                    ]"
                                >
                                    {{ st.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bills List Card -->
                <div class="bg-white shadow sm:rounded-lg border border-gray-200 overflow-hidden">
                    <div v-if="loading" class="p-12 text-center text-gray-500">Loading bills...</div>
                    <div v-else-if="!canViewBills" class="p-12 text-center text-gray-500">You do not have permission to view bills.</div>
                    <div v-else-if="!bills.length" class="p-12 text-center text-gray-500">No bills found.</div>
                    <div v-else class="w-full overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-8 px-3 py-3"></th> <!-- Navigation Indicator -->
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Project / Contract</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Contractor</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="bill in bills" :key="bill.id" class="hover:bg-gray-50 cursor-pointer transition-colors" @click="!bill.deleted_at && viewBillDetails(bill.id)">
                                    <td class="px-3 py-4 text-center">
                                        <svg v-if="!bill.deleted_at" class="w-4 h-4 text-gray-400 hover:text-indigo-600 transition-colors inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-gray-900">{{ bill.project?.name }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">{{ bill.expendable?.name }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="font-medium text-gray-900">{{ bill.contractor?.name }}</div>
                                        <div v-if="!bill.contractor?.xero_contact_id" class="text-xs text-amber-600 font-semibold mt-1">
                                            Not linked to Xero
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900">
                                        {{ formatCurrency(bill.amount, bill.currency || bill.project?.currency) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span v-if="bill.deleted_at" class="px-2 py-0.5 rounded text-[11px] font-semibold border bg-rose-50 text-rose-700 border-rose-100">
                                                DELETED
                                            </span>
                                            <span :class="['px-2 py-0.5 rounded text-[11px] font-semibold border', getStatusClass(bill.status, bill)]">
                                                {{ bill.status.toUpperCase() }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium" @click.stop>
                                        <div class="flex justify-end gap-2 items-center">
                                            <Link v-if="canViewBills && !bill.deleted_at" :href="route('admin.financials.bills.show', { id: bill.id })" class="text-indigo-600 hover:text-indigo-900 font-semibold">View</Link>
                                            <button v-if="!bill.deleted_at" @click="openHistorySidebar(bill)" class="text-indigo-600 hover:text-indigo-900 font-semibold ml-1">Payments</button>
                                            <button
                                                v-if="canLinkXeroContractors && !bill.contractor?.xero_contact_id && !bill.deleted_at"
                                                @click="openXeroSyncModal(bill.contractor)"
                                                class="text-indigo-600 hover:text-indigo-900 font-semibold"
                                            >
                                                Link Xero
                                            </button>
                                            <button v-if="canApproveBill(bill) && !bill.deleted_at" @click="approveBill(bill)" class="text-emerald-600 hover:text-emerald-900 font-semibold">Approve</button>
                                            <button v-if="bill.status === 'approved' && canVoidBills && !bill.deleted_at" @click="voidBill(bill)" class="text-rose-600 hover:text-rose-900 font-semibold">Void</button>
                                            <button v-if="bill.status === 'pending_approval' && canDeleteBills && !bill.deleted_at" @click="deleteBill(bill)" class="text-rose-600 hover:text-rose-900 font-semibold">Delete</button>
                                            <button v-if="bill.deleted_at && canRestoreBills" @click="restoreBill(bill)" class="text-emerald-600 hover:text-emerald-900 font-semibold">Restore</button>
                                            
                                            <template v-if="bill.files && bill.files.length > 0">
                                                <a :href="bill.files[0].path_url" target="_blank" class="text-blue-600 hover:text-blue-900 font-semibold ml-2">View PDF</a>
                                            </template>
                                            <template v-else>
                                                <label class="text-blue-600 hover:text-blue-900 font-semibold ml-2 cursor-pointer">
                                                    Upload PDF
                                                    <input type="file" class="hidden" accept=".pdf" @change="(e) => handleAttachmentUpload(e, bill.id)" />
                                                </label>
                                            </template>
                                        </div>
                                        <div v-if="bill.status === 'pending_approval' && pendingApproverLabel(bill) && !bill.deleted_at" class="mt-1 text-xs text-amber-600 font-medium">
                                            {{ pendingApproverLabel(bill) }}
                                        </div>
                                        <div v-if="bill.status === 'pending_approval' && !bill.deleted_at" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-[320px] ml-auto">
                                            <SelectDropdown
                                                :id="`approve_xero_account_${bill.id}`"
                                                v-model="getApprovalConfig(bill).xero_account_code"
                                                :options="xeroAccountOptions"
                                                valueKey="value"
                                                labelKey="label"
                                                placeholder="Xero Account"
                                            />
                                            <div class="w-full">
                                                <SelectDropdown
                                                    :id="`approve_xero_tax_${bill.id}`"
                                                    v-model="getApprovalConfig(bill).xero_tax_type"
                                                    :options="xeroTaxTypeOptions"
                                                    valueKey="value"
                                                    labelKey="label"
                                                    placeholder="Select Tax Type"
                                                />
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div v-if="pagination.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <button :disabled="pagination.current_page === 1" @click="pagination.current_page--; fetchBills()" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                                Previous
                            </button>
                            <button :disabled="pagination.current_page === pagination.last_page" @click="pagination.current_page++; fetchBills()" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                                Next
                            </button>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-semibold">{{ (pagination.current_page - 1) * pagination.per_page + 1 }}</span>
                                    to
                                    <span class="font-semibold">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span>
                                    of
                                    <span class="font-semibold">{{ pagination.total }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <button :disabled="pagination.current_page === 1" @click="pagination.current_page = 1; fetchBills()" class="relative inline-flex items-center px-2 py-1.5 rounded-l-md border border-gray-300 bg-white text-xs font-semibold text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                        &laquo; First
                                    </button>
                                    <button :disabled="pagination.current_page === 1" @click="pagination.current_page--; fetchBills()" class="relative inline-flex items-center px-2 py-1.5 border border-gray-300 bg-white text-xs font-semibold text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                        &lsaquo; Prev
                                    </button>
                                    <span class="relative inline-flex items-center px-4 py-1.5 border border-gray-300 bg-indigo-50 text-xs font-bold text-indigo-600">
                                        Page {{ pagination.current_page }} of {{ pagination.last_page }}
                                    </span>
                                    <button :disabled="pagination.current_page === pagination.last_page" @click="pagination.current_page++; fetchBills()" class="relative inline-flex items-center px-2 py-1.5 border border-gray-300 bg-white text-xs font-semibold text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                        Next &rsaquo;
                                    </button>
                                    <button :disabled="pagination.current_page === pagination.last_page" @click="pagination.current_page = pagination.last_page; fetchBills()" class="relative inline-flex items-center px-2 py-1.5 rounded-r-md border border-gray-300 bg-white text-xs font-semibold text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                        Last &raquo;
                                    </button>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Bill Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="4xl">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Create Bill</h3>
                    
                    <div class="flex bg-gray-100 p-1 rounded-lg">
                        <button 
                            @click="form.bill_type = 'contractor_bill'"
                            :class="['px-4 py-1.5 text-sm font-medium rounded-md', form.bill_type === 'contractor_bill' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700']"
                        >
                            Contractor Bill
                        </button>
                        <button 
                            @click="form.bill_type = 'general_expense'"
                            :class="['px-4 py-1.5 text-sm font-medium rounded-md', form.bill_type === 'general_expense' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700']"
                        >
                            General Expense
                        </button>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Form Column -->
                    <div :class="(form.bill_type === 'contractor_bill' && selectedExpendable) ? 'md:col-span-2' : 'md:col-span-3'" class="space-y-4">
                    <div>
                        <InputLabel for="bill_project_id" value="Project" />
                        <SelectDropdown
                            id="bill_project_id"
                            v-model="form.project_id"
                            :options="projects"
                            value-key="id"
                            label-key="name"
                            placeholder="Select Project"
                        />
                        <InputError :message="form.errors.project_id" />
                    </div>

                    <div v-if="form.project_id && form.bill_type === 'contractor_bill'">
                        <InputLabel for="project_expendable_id" value="Contract (Expendable)" />
                        <SelectDropdown
                            id="project_expendable_id"
                            v-model="form.project_expendable_id"
                            :options="expendableOptions"
                            valueKey="id"
                            labelKey="label"
                            placeholder="Select Contract"
                        />
                        <InputError :message="form.errors.project_expendable_id" />
                    </div>

                    <div v-if="form.bill_type === 'general_expense'">
                        <InputLabel for="supplier_id" value="Supplier (Optional)" />
                        <SelectDropdown
                            id="supplier_id"
                            v-model="form.contractor_id"
                            :options="suppliers"
                            valueKey="id"
                            labelKey="name"
                            placeholder="Select Supplier"
                        />
                        <InputError :message="form.errors.contractor_id" />
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <InputLabel for="bill_transaction_type" value="Transaction Type" class="mb-0" />
                            <button
                                type="button"
                                @click="showTransactionTypeModal = true"
                                class="text-xs text-indigo-600 hover:text-indigo-900 focus:outline-none"
                            >
                                + New Type
                            </button>
                        </div>
                        <SelectDropdown
                            id="bill_transaction_type"
                            v-model="form.transaction_type_id"
                            :options="transactionTypes"
                            valueKey="id"
                            labelKey="name"
                            placeholder="Select Transaction Type"
                        />
                        <InputError :message="form.errors.transaction_type_id" />
                    </div>

                    <div>
                        <InputLabel for="bill_xero_account_code" value="Xero Account" />
                        <SelectDropdown
                            id="bill_xero_account_code"
                            v-model="form.xero_account_code"
                            :options="xeroAccountOptions"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select Xero Account (Optional)"
                        />
                        <InputError :message="form.errors.xero_account_code" />
                    </div>

                    <div>
                        <InputLabel for="bill_xero_tax_type" value="Xero Tax Type" />
                        <SelectDropdown
                            id="bill_xero_tax_type"
                            v-model="form.xero_tax_type"
                            :options="xeroTaxTypeOptions"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select Tax Type"
                        />
                        <InputError :message="form.errors.xero_tax_type" />
                    </div>

                    <div>
                        <InputLabel for="bill_reference_number" value="Reference Number" />
                        <TextInput
                            id="bill_reference_number"
                            v-model="form.reference_number"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.reference_number" />
                    </div>

                    <div>
                        <InputLabel for="bill_due_date" value="Due Date" />
                        <TextInput
                            id="bill_due_date"
                            v-model="form.due_date"
                            type="date"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.due_date" />
                    </div>

                    <div>
                        <InputLabel for="bill_currency" value="Currency" />
                        <TextInput
                            id="bill_currency"
                            v-model="form.currency"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g. AUD"
                        />
                        <InputError :message="form.errors.currency" />
                    </div>

                    <div>
                        <InputLabel for="bill_amount" value="Amount" />
                        <TextInput 
                            id="bill_amount" 
                            v-model="form.amount" 
                            type="number" 
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div v-if="form.bill_type === 'contractor_bill'" class="border rounded-md p-4 bg-gray-50 space-y-4">
                        <h4 class="text-sm font-semibold text-gray-800">Payment Details (Required)</h4>

                        <div>
                            <InputLabel for="bill_payment_method" value="Payment Method" />
                            <SelectDropdown
                                id="bill_payment_method"
                                v-model="form.payment_details.payment_method"
                                :options="[
                                    { value: 'bank_transfer', label: 'Bank Transfer' },
                                    { value: 'paypal', label: 'PayPal' },
                                    { value: 'other', label: 'Other' }
                                ]"
                                valueKey="value"
                                labelKey="label"
                                placeholder="Select Payment Method"
                            />
                            <InputError :message="form.errors['payment_details.payment_method']" />
                        </div>

                        <div>
                            <InputLabel for="bill_account_name" value="Account Name" />
                            <TextInput
                                id="bill_account_name"
                                v-model="form.payment_details.account_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.account_name']" />
                        </div>

                        <div>
                            <InputLabel for="bill_account_number" value="Account Number" />
                            <TextInput
                                id="bill_account_number"
                                v-model="form.payment_details.account_number"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.account_number']" />
                        </div>

                        <div>
                            <InputLabel for="bill_bank_name" value="Bank Name" />
                            <TextInput
                                id="bill_bank_name"
                                v-model="form.payment_details.bank_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.bank_name']" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="bill_bsb" value="BSB / Routing" />
                                <TextInput
                                    id="bill_bsb"
                                    v-model="form.payment_details.bsb"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors['payment_details.bsb']" />
                            </div>
                            <div>
                                <InputLabel for="bill_swift" value="SWIFT Code" />
                                <TextInput
                                    id="bill_swift"
                                    v-model="form.payment_details.swift_code"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors['payment_details.swift_code']" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="bill_iban" value="IBAN" />
                            <TextInput
                                id="bill_iban"
                                v-model="form.payment_details.iban"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.iban']" />
                        </div>

                        <div>
                            <InputLabel for="bill_notes" value="Payment Notes" />
                            <textarea
                                id="bill_notes"
                                v-model="form.payment_details.notes"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                rows="2"
                            ></textarea>
                            <InputError :message="form.errors['payment_details.notes']" />
                        </div>
                    </div>

                    <!-- Add a divider for attachments -->
                    <div class="col-span-1 md:col-span-2 pt-4 border-t border-gray-200 mt-2">
                        <InputLabel for="bill_document" value="Bill Attachment (PDF)" />
                        <input
                            type="file"
                            id="bill_document"
                            accept=".pdf"
                            @change="(e) => form.document = e.target.files[0]"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        <InputError :message="form.errors.document" />
                    </div>
                    </div> <!-- THIS IS THE MISSING CLOSING DIV FOR FORM COLUMN -->
                    
                    <!-- Contract Stats Column -->
                    <div v-if="form.bill_type === 'contractor_bill' && selectedExpendable" class="md:col-span-1">
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 sticky top-4">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">Contract Details</h4>
                            
                            <dl class="space-y-3 text-sm">
                                <div>
                                    <dt class="text-gray-500 font-medium">Original Amount</dt>
                                    <dd class="text-gray-900 mt-1">{{ formatCurrency(selectedExpendable.amount, selectedExpendable.currency) }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-gray-500 font-medium">Remaining Balance</dt>
                                    <dd class="text-gray-900 mt-1 font-semibold">{{ formatCurrency(selectedExpendable.balance, selectedExpendable.currency) }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-gray-500 font-medium">Utilized (Paid/Pending)</dt>
                                    <dd class="text-gray-900 mt-1">{{ formatCurrency(selectedExpendable.amount - selectedExpendable.balance, selectedExpendable.currency) }}</dd>
                                </div>
                                
                                <div v-if="selectedExpendable.payment_terms">
                                    <dt class="text-gray-500 font-medium mt-4">Payment Terms</dt>
                                    <dd class="text-gray-900 mt-1 whitespace-pre-wrap text-xs bg-white p-2 rounded border border-gray-100">{{ formatPaymentTerms(selectedExpendable.payment_terms) }}</dd>
                                </div>
                                
                                <div v-if="selectedExpendable.description">
                                    <dt class="text-gray-500 font-medium mt-4">Description</dt>
                                    <dd class="text-gray-900 mt-1 whitespace-pre-wrap text-xs">{{ selectedExpendable.description }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitBill" :disabled="form.processing">
                        Create Bill
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showXeroSyncModal" @close="showXeroSyncModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-2">Link Contractor to Xero</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Contractor: <span class="font-medium">{{ xeroSyncContractor?.name }}</span>
                </p>

                <div v-if="xeroSyncError" class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    {{ xeroSyncError }}
                </div>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="xero_contact_candidate" value="Existing Xero Contact" />
                        <SelectDropdown
                            id="xero_contact_candidate"
                            v-model="selectedXeroContactId"
                            :options="[
                                { value: '', label: 'Auto-select if single match' },
                                ...xeroCandidateOptions
                            ]"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select Xero Contact"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showXeroSyncModal = false">Cancel</SecondaryButton>
                    <SecondaryButton @click="fetchXeroCandidates" :disabled="xeroSyncLoading">
                        Refresh Matches
                    </SecondaryButton>
                    <SecondaryButton @click="createXeroContactForContractor" :disabled="xeroSyncLoading">
                        Create In Xero
                    </SecondaryButton>
                    <PrimaryButton @click="syncContractorWithXero" :disabled="xeroSyncLoading">
                        Link Selected
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Transaction Type Modal -->
        <Modal :show="showTransactionTypeModal" @close="showTransactionTypeModal = false" maxWidth="md">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Create Transaction Type</h3>
                
                <div class="space-y-4">
                    <div>
                        <InputLabel for="tt_name" value="Name" />
                        <TextInput
                            id="tt_name"
                            v-model="newTransactionType.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g. Software Subscriptions"
                        />
                        <InputError :message="newTransactionType.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="tt_xero_account" value="Xero Account" />
                        <SelectDropdown
                            id="tt_xero_account"
                            v-model="newTransactionType.xero_account_code"
                            :options="xeroAccountOptions"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select Xero Account (Optional)"
                        />
                        <InputError :message="newTransactionType.errors.xero_account_code" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showTransactionTypeModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitNewTransactionType" :disabled="newTransactionType.processing">
                        Create
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <RightSidebar v-model:show="showHistorySidebar" :title="`Payment History - Bill #${selectedBillForHistory?.id}`">
            <template #content>
                <div v-if="selectedBillForHistory" class="space-y-6">
                    <!-- Bill Details Summary -->
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <h4 class="text-md font-semibold text-gray-900 mb-2">Bill Summary</h4>
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500 font-medium">Contractor</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedBillForHistory.contractor?.name || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Project</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedBillForHistory.project?.name || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Amount</dt>
                                <dd class="text-gray-900 mt-0.5 font-semibold">{{ formatCurrency(selectedBillForHistory.amount, selectedBillForHistory.currency) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Status</dt>
                                <dd class="text-gray-900 mt-0.5">
                                    <span :class="['px-2 py-0.5 text-xs font-bold rounded-full', getStatusClass(selectedBillForHistory.status, selectedBillForHistory)]">
                                        {{ selectedBillForHistory.status.toUpperCase() }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Transaction History -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-900 mb-3">Linked Transactions</h4>
                        <div v-if="selectedBillForHistory.transactions && selectedBillForHistory.transactions.length" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="tx in selectedBillForHistory.transactions" :key="tx.id">
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ formatDate(tx.created_at) }}</td>
                                        <td class="px-3 py-2 text-gray-900">{{ tx.description || '—' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ tx.transaction_type?.name || '—' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap font-medium text-gray-900">{{ formatCurrency(tx.amount, tx.currency) }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ tx.user?.name || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                            No transactions linked to this bill.
                        </div>
                    </div>
                </div>
            </template>
        </RightSidebar>
    </AuthenticatedLayout>
</template>
