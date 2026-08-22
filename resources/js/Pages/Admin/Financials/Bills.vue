<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import { formatCurrency, calculateGst, XERO_TAX_CONFIG } from '@/Utils/currency';
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
import { 
    ClockIcon, 
    FolderIcon, 
    DocumentTextIcon, 
    TagIcon, 
    ArrowTopRightOnSquareIcon 
} from '@heroicons/vue/24/outline';

const bills = ref([]);
const projects = ref([]);
const expendables = ref([]);
const transactionTypes = ref([]);
const suppliers = ref([]);
const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];
const loading = ref(true);
const filterStatus = ref('pending_approval');
const filterProject = ref('');
const searchQuery = ref('');
const searchDebounce = ref(null);
const showCreateModal = ref(false);
const showXeroSyncModal = ref(false);
const showApproveModal = ref(false);
const billToApprove = ref(null);
const xeroSyncLoading = ref(false);
const xeroSyncError = ref('');
const xeroCandidates = ref([]);

const pagination = ref({
    total: 0,
    current_page: 1,
    last_page: 1,
    per_page: 20,
});

const statusCounts = ref({
    pending_approval: 0,
    approved: 0,
    paid: 0,
    void: 0,
    deleted: 0,
    total: 0,
});

watch([filterStatus, filterProject], () => {
    pagination.value.current_page = 1;
    fetchBills();
});

const selectedBill = ref(null);
const showBillSidebar = ref(false);

const openBillSidebar = (bill) => {
    selectedBill.value = bill;
    showBillSidebar.value = true;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString();
};

const isMilestone = (expendable) => {
    if (!expendable) return false;
    const type = expendable.expendable_type || '';
    return type.includes('Milestone') || type === 'App\\Models\\Milestone';
};

const sortedActivities = (activities) => {
    if (!activities || !activities.length) return [];
    return [...activities].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
};

const formatActivityDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' at ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const getActivityEventInfo = (activity, bill) => {
    const event = activity.event || activity.description || '';
    const desc = (activity.description || '').toLowerCase();
    
    let badgeClass = 'bg-gray-100 text-gray-700 border-gray-200';
    let label = activity.description || 'Activity';

    if (desc.includes('approved') || event.includes('approved')) {
        badgeClass = 'bg-green-100 text-green-800 border-green-200';
        label = 'Approved';
    } else if (desc.includes('void') || event.includes('void')) {
        badgeClass = 'bg-slate-100 text-slate-800 border-slate-200';
        label = 'Voided';
    } else if (desc.includes('paid') || event.includes('paid')) {
        badgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
        label = 'Paid';
    } else if (event === 'created' || desc === 'created') {
        badgeClass = 'bg-purple-100 text-purple-800 border-purple-200';
        label = 'Created';
    } else if (event === 'updated' || desc === 'updated') {
        badgeClass = 'bg-slate-100 text-slate-700 border-slate-200';
        label = 'Updated';
    } else if (event === 'deleted' || desc.includes('deleted')) {
        badgeClass = 'bg-rose-100 text-rose-800 border-rose-200';
        label = 'Deleted';
    } else if (event === 'restored' || desc.includes('restored')) {
        badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
        label = 'Restored';
    }

    return { label, badgeClass };
};

const presentableActivityAttributes = (attributes) => {
    if (!attributes) return [];
    const ignoredKeys = ['project_id', 'contractor_id', 'project_expendable_id', 'transaction_type_id', 'xero_invoice_id'];
    const friendlyNames = {
        amount: 'Amount',
        currency: 'Currency',
        status: 'Status',
        reference_number: 'Reference',
        due_date: 'Due Date',
        xero_account_code: 'Xero Account',
        xero_tax_type: 'Tax Type'
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

const billSubtotal = ref('');

const calculatedGst = computed(() => {
    const st = parseFloat(billSubtotal.value) || 0;
    return calculateGst(st, form.xero_tax_type);
});

const calculatedTotal = computed(() => {
    const st = parseFloat(billSubtotal.value) || 0;
    return parseFloat((st + calculatedGst.value).toFixed(2));
});

const selectedTaxTypeDescription = computed(() => {
    return XERO_TAX_CONFIG[form.xero_tax_type]?.description || '';
});

watch([billSubtotal, () => form.xero_tax_type], () => {
    form.amount = calculatedTotal.value || billSubtotal.value;
});

const xeroAccounts = ref([]);
const showTransactionTypeModal = ref(false);
const newTransactionType = useForm({
    name: '',
    xero_account_code: '',
});

const xeroTaxTypeOptions = Object.entries(XERO_TAX_CONFIG).map(([value, cfg]) => ({
    value,
    label: cfg.label,
}));

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
        const paginationData = data.pagination || {};
        bills.value = paginationData.data || [];
        pagination.value = {
            total: paginationData.total || 0,
            current_page: paginationData.current_page || 1,
            last_page: paginationData.last_page || 1,
            per_page: paginationData.per_page || 20,
        };
        statusCounts.value = data.counts || {
            pending_approval: 0,
            approved: 0,
            paid: 0,
            void: 0,
            deleted: 0,
            total: 0,
        };
        bills.value.forEach((bill) => {
            getApprovalConfig(bill);
        });
        if (selectedBill.value) {
            const updated = bills.value.find(b => b.id === selectedBill.value.id);
            if (updated) {
                selectedBill.value = updated;
            }
        }
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

watch(() => form.contractor_id, async (newId) => {
    if (!newId) return;
    try {
        const { data } = await axios.get(`/api/users/${newId}/latest-payment-details`);
        if (data) {
            form.payment_details.payment_method = data.payment_method || 'bank_transfer';
            form.payment_details.account_name = data.account_name || '';
            form.payment_details.account_number = data.account_number || '';
            form.payment_details.bank_name = data.bank_name || '';
            form.payment_details.bsb = data.bsb || '';
            form.payment_details.swift_code = data.swift_code || '';
            form.payment_details.iban = data.iban || '';
            form.payment_details.notes = data.notes || '';
        }
    } catch (err) {
        console.error('Failed to fetch latest payment details', err);
    }
});

watch(() => form.transaction_type_id, () => {
    if (selectedTransactionType.value?.xero_account_code && !form.xero_account_code) {
        form.xero_account_code = selectedTransactionType.value.xero_account_code;
    }
});

const openCreateModal = () => {
    form.reset();
    billSubtotal.value = '';
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
    if (!form.reference_number) return error('Please enter a Reference Number.');
    if (!form.xero_account_code) return error('Please select a Xero Account.');
    if (!form.currency) return error('Please select a Currency.');

    if (form.bill_type === 'contractor_bill') {
        if (!form.project_expendable_id) return error('Please select a contract.');
        if (!form.project_id) return error('Please select a project.');
        if (!form.contractor_id) return error('Selected contract does not have a contractor assigned.');
        if (!form.transaction_type_id) return error('Please select a transaction type.');
    } else {
        if (!form.project_id) return error('Please select a project.');
        if (!form.contractor_id) return error('Please select a Supplier.');
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

const openApproveModal = (bill) => {
    billToApprove.value = bill;
    showApproveModal.value = true;
};

const confirmApproveBill = async () => {
    const bill = billToApprove.value;
    if (!bill) return;
    if (!canApproveBills.value) {
        error('You do not have permission to approve bills.');
        return;
    }
    try {
        const approvalConfig = getApprovalConfig(bill);
        if (!approvalConfig.xero_account_code) {
            error('Please select a Xero Account before approving this bill.');
            return;
        }

        await axios.post(route('api.bills.approve', { bill: bill.id }), {
            xero_account_code: approvalConfig.xero_account_code,
            xero_tax_type: approvalConfig.xero_tax_type,
        });
        success('Bill approved.');
        showApproveModal.value = false;
        billToApprove.value = null;
        fetchBills();
    } catch (err) {
        const message = err.response?.data?.message || 'Approval failed.';
        if (message.includes('not linked to Xero') && bill?.contractor?.id) {
            showApproveModal.value = false;
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

// View bill details is now handled by RightSidebar

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
                <div v-if="!loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
                    <!-- Pending Approval Card -->
                    <div class="bg-white border border-amber-200 rounded-lg p-4 shadow-sm border-l-4 border-l-amber-500">
                        <div class="text-[10px] font-semibold text-amber-600 uppercase tracking-wider">Pending Approval</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-amber-950">{{ statusCounts.pending_approval }}</div>
                        </div>
                    </div>

                    <!-- Approved Card -->
                    <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm border-l-4 border-l-blue-500">
                        <div class="text-[10px] font-semibold text-blue-600 uppercase tracking-wider">Approved</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-blue-950">{{ statusCounts.approved }}</div>
                        </div>
                    </div>

                    <!-- Paid Card -->
                    <div class="bg-white border border-emerald-200 rounded-lg p-4 shadow-sm border-l-4 border-l-emerald-500">
                        <div class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wider">Paid / Partial</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-emerald-950">{{ statusCounts.paid }}</div>
                        </div>
                    </div>

                    <!-- Void Card -->
                    <div class="bg-white border border-rose-200 rounded-lg p-4 shadow-sm border-l-4 border-l-rose-500">
                        <div class="text-[10px] font-semibold text-rose-600 uppercase tracking-wider">Voided</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-rose-950">{{ statusCounts.void }}</div>
                        </div>
                    </div>

                    <!-- Total Active Card -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm border-l-4 border-l-gray-400">
                        <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Total Active</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-gray-900">{{ statusCounts.total }}</div>
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
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Information</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="bill in bills" :key="bill.id" class="hover:bg-gray-50 cursor-pointer transition-colors" @click="!bill.deleted_at && openBillSidebar(bill)">
                                    <td class="px-3 py-4 text-center">
                                        <svg v-if="!bill.deleted_at" class="w-4 h-4 text-gray-400 hover:text-indigo-600 transition-colors inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </td>
                                    <td class="px-6 py-4">
                                        <!-- Bill Number & Project -->
                                        <div class="flex items-center gap-1.5 mb-1.5">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                {{ bill.bill_number || ('OZB' + bill.id) }}
                                            </span>
                                            <Link 
                                                v-if="bill.project_id"
                                                :href="route('projects.show', { id: bill.project_id })" 
                                                class="text-sm font-semibold text-gray-900 hover:text-indigo-600 hover:underline inline-flex items-center gap-1"
                                                @click.stop
                                                title="Open Project"
                                            >
                                                <FolderIcon class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" />
                                                <span class="truncate max-w-[180px]">{{ bill.project?.name || 'Project #' + bill.project_id }}</span>
                                            </Link>
                                            <span v-else class="text-sm font-semibold text-gray-400">No Project</span>
                                        </div>

                                        <!-- Proposal & Milestone -->
                                        <div v-if="bill.expendable" class="flex items-center flex-wrap gap-1 mt-0.5">
                                            <Link 
                                                :href="route('admin.financials.proposals', { id: bill.expendable.id })" 
                                                class="inline-flex items-center gap-1 text-xs text-indigo-700 hover:text-indigo-900 hover:underline font-medium bg-indigo-50/70 hover:bg-indigo-100/70 px-1.5 py-0.5 rounded border border-indigo-200 transition-colors"
                                                @click.stop
                                                title="Open Proposal"
                                            >
                                                <DocumentTextIcon class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" />
                                                <span class="font-mono text-[10px] font-bold text-indigo-700">{{ bill.expendable.expendable_number || ('OZX' + bill.expendable.id) }}</span>
                                                <span class="truncate max-w-[140px] text-gray-800">{{ bill.expendable.name }}</span>
                                            </Link>
                                            <span 
                                                v-if="isMilestone(bill.expendable)"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] bg-emerald-50 text-emerald-700 font-semibold border border-emerald-100"
                                                :title="`Milestone: ${bill.expendable.expendable?.name || bill.expendable.name}`"
                                            >
                                                <TagIcon class="w-3 h-3 text-emerald-600 flex-shrink-0" />
                                                <span class="truncate max-w-[130px]">Milestone: {{ bill.expendable.expendable?.name || bill.expendable.name }}</span>
                                            </span>
                                        </div>
                                        <div v-else class="text-xs text-gray-400 italic mt-0.5">
                                            No Proposal Attached
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="font-medium text-gray-900">{{ bill.contractor?.name }}</div>
                                        <div v-if="!bill.contractor?.xero_contact_id" class="text-xs text-amber-600 font-semibold mt-1">
                                            Not linked to Xero
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500 space-y-1">
                                        <div v-if="bill.xero_account_code || bill.transaction_type?.xero_account_code">
                                            <span class="font-semibold text-gray-700">Acc:</span> {{ bill.xero_account_code || bill.transaction_type?.xero_account_code }}
                                        </div>
                                        <div v-if="bill.transaction_type?.name">
                                            <span class="font-semibold text-gray-700">Type:</span> {{ bill.transaction_type.name }}
                                        </div>
                                        <div v-if="bill.due_date">
                                            <span class="font-semibold text-gray-700">Due:</span> {{ formatDate(bill.due_date) }}
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
                                            <button v-if="canViewBills && !bill.deleted_at" @click="openBillSidebar(bill)" class="text-indigo-600 hover:text-indigo-900 font-semibold">View</button>
                                            <button
                                                v-if="canLinkXeroContractors && !bill.contractor?.xero_contact_id && !bill.deleted_at"
                                                @click="openXeroSyncModal(bill.contractor)"
                                                class="text-indigo-600 hover:text-indigo-900 font-semibold"
                                            >
                                                Link Xero
                                            </button>
                                            <button v-if="canApproveBill(bill) && !bill.deleted_at" @click="openApproveModal(bill)" class="text-emerald-600 hover:text-emerald-900 font-semibold">Approve</button>
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
                        <InputLabel for="supplier_id" value="Supplier" />
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
                        <InputLabel for="bill_xero_tax_type" value="Tax Type" />
                        <SelectDropdown
                            id="bill_xero_tax_type"
                            v-model="form.xero_tax_type"
                            :options="xeroTaxTypeOptions"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select Tax Type"
                        />
                        <p v-if="selectedTaxTypeDescription" class="mt-1 text-xs text-gray-500 italic">{{ selectedTaxTypeDescription }}</p>
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
                        <SelectDropdown
                            id="bill_currency"
                            v-model="form.currency"
                            :options="currencyOptions"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select Currency"
                        />
                        <InputError :message="form.errors.currency" />
                    </div>

                    <div>
                        <InputLabel for="bill_subtotal" value="Subtotal (excl. GST)" />
                        <TextInput 
                            id="bill_subtotal" 
                            v-model="billSubtotal" 
                            type="number" 
                            step="0.01"
                            class="mt-1 block w-full"
                            placeholder="0.00"
                        />
                        <!-- GST Breakdown -->
                        <div v-if="billSubtotal" class="mt-2 text-sm bg-gray-50 rounded-md border border-gray-200 overflow-hidden">
                            <div class="px-3 py-2 border-b border-gray-200">
                                <div class="flex justify-between items-center text-gray-600">
                                    <span>Subtotal</span>
                                    <span>{{ formatCurrency(parseFloat(billSubtotal) || 0, form.currency || 'AUD') }}</span>
                                </div>
                            </div>
                            <div class="px-3 py-2 border-b border-gray-200">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">
                                        GST
                                        <span v-if="calculatedGst > 0" class="text-xs text-gray-400 ml-1">({{ XERO_TAX_CONFIG[form.xero_tax_type]?.rate * 100 }}%)</span>
                                    </span>
                                    <span v-if="calculatedGst > 0" class="font-medium text-gray-900">
                                        {{ formatCurrency(calculatedGst, form.currency || 'AUD') }}
                                    </span>
                                    <span v-else class="text-xs italic text-gray-400">No GST ({{ XERO_TAX_CONFIG[form.xero_tax_type]?.label || 'N/A' }})</span>
                                </div>
                            </div>
                            <div class="px-3 py-2 bg-white">
                                <div class="flex justify-between items-center font-semibold text-gray-900">
                                    <span>Total (sent to Xero)</span>
                                    <span class="text-indigo-700">{{ formatCurrency(calculatedTotal, form.currency || 'AUD') }}</span>
                                </div>
                            </div>
                        </div>
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
                                autocomplete="new-password"
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
                                autocomplete="new-password"
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
                                autocomplete="new-password"
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
                                    autocomplete="new-password"
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
                                    autocomplete="new-password"
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
                                autocomplete="new-password"
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
                                autocomplete="new-password"
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

        <!-- Approve Bill Modal -->
        <Modal :show="showApproveModal" @close="showApproveModal = false" maxWidth="md">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Approve Bill</h3>
                <div v-if="billToApprove" class="space-y-4">
                    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-amber-700">
                                    Approving this bill will queue it to sync to Xero as a Purchase Invoice.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="approve_xero_account" value="Xero Account" />
                        <SelectDropdown
                            id="approve_xero_account"
                            v-model="getApprovalConfig(billToApprove).xero_account_code"
                            :options="xeroAccountOptions"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select Xero Account"
                        />
                    </div>
                    <div>
                        <InputLabel for="approve_xero_tax" value="Tax Type" />
                        <SelectDropdown
                            id="approve_xero_tax"
                            v-model="getApprovalConfig(billToApprove).xero_tax_type"
                            :options="xeroTaxTypeOptions"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select Tax Type"
                        />
                        <p v-if="XERO_TAX_CONFIG[getApprovalConfig(billToApprove).xero_tax_type]?.description" class="mt-1 text-xs text-gray-500 italic">
                            {{ XERO_TAX_CONFIG[getApprovalConfig(billToApprove).xero_tax_type]?.description }}
                        </p>
                    </div>

                    <!-- GST Preview for this bill -->
                    <div class="text-sm bg-gray-50 rounded-md border border-gray-200 overflow-hidden">
                        <div class="px-3 py-2 bg-gray-100 border-b border-gray-200">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bill Amount Breakdown</span>
                        </div>
                        <div class="px-3 py-2 border-b border-gray-200 flex justify-between text-gray-600">
                            <span>Bill Total (as entered)</span>
                            <span class="font-medium text-gray-900">{{ formatCurrency(billToApprove.amount, billToApprove.currency || 'AUD') }}</span>
                        </div>
                        <div class="px-3 py-2 border-b border-gray-200 flex justify-between">
                            <span class="text-gray-600">
                                GST Component
                                <span v-if="XERO_TAX_CONFIG[getApprovalConfig(billToApprove).xero_tax_type]?.rate > 0" class="text-xs text-gray-400 ml-1">
                                    ({{ XERO_TAX_CONFIG[getApprovalConfig(billToApprove).xero_tax_type]?.rate * 100 }}% incl.)
                                </span>
                            </span>
                            <span v-if="XERO_TAX_CONFIG[getApprovalConfig(billToApprove).xero_tax_type]?.rate > 0" class="font-medium text-gray-900">
                                {{ formatCurrency(
                                    parseFloat(billToApprove.amount) * XERO_TAX_CONFIG[getApprovalConfig(billToApprove).xero_tax_type].rate / (1 + XERO_TAX_CONFIG[getApprovalConfig(billToApprove).xero_tax_type].rate),
                                    billToApprove.currency || 'AUD'
                                ) }}
                            </span>
                            <span v-else class="text-xs italic text-gray-400">No GST</span>
                        </div>
                        <div class="px-3 py-2 bg-white flex justify-between font-semibold text-gray-900">
                            <span>Subtotal (excl. GST)</span>
                            <span v-if="XERO_TAX_CONFIG[getApprovalConfig(billToApprove).xero_tax_type]?.rate > 0" class="text-indigo-700">
                                {{ formatCurrency(
                                    parseFloat(billToApprove.amount) / (1 + XERO_TAX_CONFIG[getApprovalConfig(billToApprove).xero_tax_type].rate),
                                    billToApprove.currency || 'AUD'
                                ) }}
                            </span>
                            <span v-else class="text-indigo-700">{{ formatCurrency(billToApprove.amount, billToApprove.currency || 'AUD') }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showApproveModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="confirmApproveBill">
                        Approve
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

        <RightSidebar v-model:show="showBillSidebar" :title="`Bill Details - ${selectedBill?.bill_number || ('#' + selectedBill?.id)}`">
            <template #content>
                <div v-if="selectedBill" class="space-y-6">
                    <!-- Bill Details Summary -->
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-md font-semibold text-gray-900">Bill Summary</h4>
                            <Link :href="route('admin.financials.bills.show', { id: selectedBill.id })" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                Full Edit & Approval
                            </Link>
                        </div>
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500 font-medium">Contractor</dt>
                                <dd class="text-gray-900 mt-0.5 font-semibold">{{ selectedBill.contractor?.name || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Project</dt>
                                <dd class="mt-0.5">
                                    <Link 
                                        v-if="selectedBill.project_id"
                                        :href="route('projects.show', { id: selectedBill.project_id })" 
                                        class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-900 font-semibold hover:underline"
                                        title="Open Project"
                                    >
                                        <FolderIcon class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" />
                                        <span class="truncate">{{ selectedBill.project?.name || 'Project #' + selectedBill.project_id }}</span>
                                        <ArrowTopRightOnSquareIcon class="w-3 h-3 text-gray-400" />
                                    </Link>
                                    <span v-else class="text-gray-900">N/A</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Proposal</dt>
                                <dd class="mt-0.5">
                                    <Link 
                                        v-if="selectedBill.expendable"
                                        :href="route('admin.financials.proposals', { id: selectedBill.expendable.id })" 
                                        class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-900 font-semibold hover:underline"
                                        title="Open Proposal Details"
                                    >
                                        <DocumentTextIcon class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" />
                                        <span class="font-mono text-[11px] font-bold text-indigo-700 bg-indigo-50 px-1 py-0.2 rounded border border-indigo-100">
                                            {{ selectedBill.expendable.expendable_number || ('OZX' + selectedBill.expendable.id) }}
                                        </span>
                                        <span class="truncate max-w-[130px]">{{ selectedBill.expendable.name }}</span>
                                        <ArrowTopRightOnSquareIcon class="w-3 h-3 text-gray-400" />
                                    </Link>
                                    <span v-else class="text-gray-400 text-xs italic">N/A</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Scope / Milestone</dt>
                                <dd class="mt-0.5">
                                    <span 
                                        v-if="isMilestone(selectedBill.expendable)"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs bg-emerald-50 text-emerald-700 font-semibold border border-emerald-100"
                                        :title="`Milestone: ${selectedBill.expendable.expendable?.name || selectedBill.expendable.name}`"
                                    >
                                        <TagIcon class="w-3.5 h-3.5 text-emerald-600 flex-shrink-0" />
                                        <span class="truncate">{{ selectedBill.expendable.expendable?.name || selectedBill.expendable.name }}</span>
                                    </span>
                                    <span 
                                        v-else-if="selectedBill.expendable"
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-indigo-50 text-indigo-700 font-semibold border border-indigo-100"
                                    >
                                        Project Scope
                                    </span>
                                    <span v-else class="text-gray-400 text-xs">N/A</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Status</dt>
                                <dd class="text-gray-900 mt-0.5">
                                    <span :class="['px-2 py-0.5 text-xs font-bold rounded-full border', getStatusClass(selectedBill.status, selectedBill)]">
                                        {{ selectedBill.status.toUpperCase() }}
                                    </span>
                                </dd>
                            </div>
                            <div v-if="selectedBill.reference_number">
                                <dt class="text-gray-500 font-medium">Reference</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedBill.reference_number }}</dd>
                            </div>
                            <div v-if="selectedBill.due_date">
                                <dt class="text-gray-500 font-medium">Due Date</dt>
                                <dd class="text-gray-900 mt-0.5">{{ formatDate(selectedBill.due_date) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Transaction Type</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedBill.transaction_type?.name || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Xero Account</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedBill.xero_account_code || selectedBill.transaction_type?.xero_account_code || 'N/A' }}</dd>
                            </div>
                        </dl>

                        <!-- GST Breakdown -->
                        <div class="mt-4 text-sm bg-white rounded-md border border-gray-200 overflow-hidden">
                            <div class="px-3 py-2 bg-gray-100 border-b border-gray-200">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Amount Breakdown
                                    <span class="ml-1 font-normal normal-case text-gray-400">
                                        ({{ XERO_TAX_CONFIG[selectedBill.xero_tax_type]?.label || selectedBill.xero_tax_type || 'No tax type' }})
                                    </span>
                                </span>
                            </div>
                            <template v-if="XERO_TAX_CONFIG[selectedBill.xero_tax_type]?.rate > 0">
                                <div class="px-3 py-2 border-b border-gray-200 flex justify-between text-gray-600">
                                    <span>Subtotal (excl. GST)</span>
                                    <span class="font-medium text-gray-900">
                                        {{ formatCurrency(
                                            parseFloat(selectedBill.amount) / (1 + XERO_TAX_CONFIG[selectedBill.xero_tax_type].rate),
                                            selectedBill.currency || selectedBill.project?.currency || 'AUD'
                                        ) }}
                                    </span>
                                </div>
                                <div class="px-3 py-2 border-b border-gray-200 flex justify-between text-gray-600">
                                    <span>GST ({{ XERO_TAX_CONFIG[selectedBill.xero_tax_type].rate * 100 }}%)</span>
                                    <span class="font-medium text-gray-900">
                                        {{ formatCurrency(
                                            parseFloat(selectedBill.amount) * XERO_TAX_CONFIG[selectedBill.xero_tax_type].rate / (1 + XERO_TAX_CONFIG[selectedBill.xero_tax_type].rate),
                                            selectedBill.currency || selectedBill.project?.currency || 'AUD'
                                        ) }}
                                    </span>
                                </div>
                                <div class="px-3 py-2 flex justify-between font-semibold text-gray-900">
                                    <span>Total (incl. GST)</span>
                                    <span class="text-indigo-700">{{ formatCurrency(selectedBill.amount, selectedBill.currency || selectedBill.project?.currency || 'AUD') }}</span>
                                </div>
                            </template>
                            <template v-else>
                                <div class="px-3 py-2 flex justify-between text-gray-600">
                                    <span>Amount (No GST)</span>
                                    <span class="font-semibold text-gray-900">{{ formatCurrency(selectedBill.amount, selectedBill.currency || selectedBill.project?.currency || 'AUD') }}</span>
                                </div>
                                <div class="px-3 py-2 bg-gray-50 text-xs text-gray-400 italic border-t border-gray-200">
                                    {{ XERO_TAX_CONFIG[selectedBill.xero_tax_type]?.description || 'No GST applies to this bill.' }}
                                </div>
                            </template>
                        </div>
                    </div>


                    <!-- Transaction History -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-900 mb-3">Linked Transactions</h4>
                        <div v-if="selectedBill.transactions && selectedBill.transactions.length" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="tx in selectedBill.transactions" :key="tx.id">
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ formatDate(tx.created_at) }}</td>
                                        <td class="px-3 py-2 text-gray-900 truncate max-w-[120px]">{{ tx.description || '—' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap font-medium text-gray-900">{{ formatCurrency(tx.amount, tx.currency) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                            No transactions linked to this bill.
                        </div>
                    </div>

                    <!-- Activity History & Audit Trail -->
                    <div class="border-t border-gray-200 pt-6">
                        <h4 class="text-md font-bold text-gray-900 mb-4 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <ClockIcon class="w-5 h-5 text-indigo-500" />
                                Activity History & Audit Trail
                            </span>
                            <span class="text-xs font-semibold bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full border border-indigo-100">
                                {{ selectedBill.activities ? selectedBill.activities.length : 0 }} Events
                            </span>
                        </h4>

                        <div v-if="selectedBill.activities && selectedBill.activities.length" class="space-y-3">
                            <div 
                                v-for="activity in sortedActivities(selectedBill.activities)" 
                                :key="activity.id"
                                class="relative bg-white border border-gray-200 rounded-lg p-3.5 shadow-sm hover:border-gray-300 transition-colors"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">
                                            {{ (activity.causer?.name || (activity.description === 'created' ? (selectedBill.contractor?.name || 'C') : 'S')).charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-gray-900">
                                                {{ activity.causer?.name || (activity.description === 'created' ? (selectedBill.contractor?.name || 'Contractor') : 'System') }}
                                            </div>
                                            <div class="text-[10px] text-gray-400">
                                                {{ formatActivityDate(activity.created_at) }}
                                            </div>
                                        </div>
                                    </div>

                                    <span :class="['px-2 py-0.5 text-[10px] font-bold rounded-full border', getActivityEventInfo(activity, selectedBill).badgeClass]">
                                        {{ getActivityEventInfo(activity, selectedBill).label }}
                                    </span>
                                </div>

                                <!-- Log Description / Note -->
                                <p class="text-xs text-gray-700 mt-2 font-medium">
                                    {{ activity.description }}
                                </p>

                                <!-- Reason / Note if provided -->
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
                        <div v-else class="text-center py-6 text-gray-500 bg-gray-50 rounded-lg border border-dashed border-gray-300 text-xs italic">
                            No activity history recorded for this bill yet.
                        </div>
                    </div>
                </div>
            </template>
        </RightSidebar>
    </AuthenticatedLayout>
</template>
