<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import { formatCurrency, fetchCurrencyRates, convertCurrency, displayCurrency } from '@/Utils/currency';
import { success, error } from '@/Utils/notification';
import TextInput from '@/Components/TextInput.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const page = usePage();
const defaultCurrency = page.props.default_currency || 'AUD';
if (!localStorage.getItem('displayCurrency')) {
    displayCurrency.value = defaultCurrency;
}
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import BasicPropertyInput from '@/Components/BasicPropertyInput.vue';
import Modal from '@/Components/Modal.vue';
import RightSidebar from '@/Components/RightSidebar.vue';

const transactions = ref([]);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0
});

// Bank Transactions State
const activeTab = ref('system'); // 'system' or 'bank'
const bankTransactions = ref([]);
const bankFilter = ref('unreconciled'); // 'unreconciled', 'reconciled', 'all'
const bankTypeFilter = ref('bills'); // 'bills', 'expenses', 'other', 'income'
const bankStatusFilter = ref('SETTLED'); // 'all', 'SETTLED', 'PENDING'
const selectedBankTx = ref(null);

const selectedLinkedDoc = ref(null);
const linkedDocType = ref(''); // 'bill' or 'invoice'
const showLinkedDocSidebar = ref(false);

const selectedBankTxDetails = ref(null);
const loadingBankTxDetails = ref(false);
const showBankTxDetailsSidebar = ref(false);

const openBankTxDetailsSidebar = async (btxId) => {
    showBankTxDetailsSidebar.value = true;
    loadingBankTxDetails.value = true;
    selectedBankTxDetails.value = null;
    try {
        const { data } = await axios.get(`/api/admin/bank-transactions/${btxId}`);
        selectedBankTxDetails.value = data;
    } catch (err) {
        error('Failed to load bank transaction details.');
        showBankTxDetailsSidebar.value = false;
    } finally {
        loadingBankTxDetails.value = false;
    }
};

const openLinkedDocSidebar = (doc, type) => {
    selectedLinkedDoc.value = doc;
    linkedDocType.value = type;
    showLinkedDocSidebar.value = true;
};

const totalPaidLinkedDoc = computed(() => {
    if (!selectedLinkedDoc.value?.transactions) return 0;
    return selectedLinkedDoc.value.transactions.reduce((sum, tx) => {
        return sum + convertCurrency(Number(tx.amount || 0), tx.currency || 'PKR', displayCurrency.value);
    }, 0);
});

const remainingAmountLinkedDoc = computed(() => {
    if (!selectedLinkedDoc.value) return 0;
    const docCurrency = selectedLinkedDoc.value.currency || 'PKR';
    const docTotal = selectedLinkedDoc.value.amount || selectedLinkedDoc.value.total_amount || 0;
    const docTotalInDisplay = convertCurrency(Number(docTotal), docCurrency, displayCurrency.value);
    return Math.max(0, docTotalInDisplay - totalPaidLinkedDoc.value);
});

const selectedDocumentCurrency = computed(() => {
    if (transactionForm.value.bill_id) {
        return bills.value.find(b => b.id === transactionForm.value.bill_id)?.currency;
    }
    if (transactionForm.value.invoice_id) {
        return invoices.value.find(i => i.id === transactionForm.value.invoice_id)?.currency;
    }
    return null;
});
const bankPagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0
});
const bankLoading = ref(false);

const filteredBankTransactions = computed(() => {
    return bankTransactions.value.filter(tx => {
        // Unreconciled: No local transactions linked yet
        if (bankFilter.value === 'unreconciled') return !tx.local_transactions || tx.local_transactions.length === 0;
        // Reconciled (Linked tab): Has at least one local transaction linked
        if (bankFilter.value === 'reconciled') return tx.local_transactions && tx.local_transactions.length > 0;
        return true;
    });
});

const projects = ref([]);
const loading = ref(true);
const filterType = ref('all');
const filterStatus = ref('all');
const filterProject = ref('');
const searchQuery = ref('');
const searchDebounce = ref(null);

// Create Transaction Modal State
const showCreateModal = ref(false);
const filterDateFrom = ref('');
const filterDateTo = ref('');

// Attachment upload state
const showUploadModal = ref(false);
const selectedTransactionForUpload = ref(null);
const uploadFile = ref(null);
const uploadLoading = ref(false);

// Link to Document state
const showLinkDocModal = ref(false);
const selectedTransactionForLink = ref(null);
const linkDocForm = ref({
    bill_id: '',
    invoice_id: ''
});
const linkDocLoading = ref(false);
const users = ref([]);
const clients = ref([]);
const bills = ref([]);
const invoices = ref([]);
const outstandingBills = ref([]);
const outstandingInvoices = ref([]);
const createLoading = ref(false);
const formErrors = ref({});

const transactionForm = ref({
    project_id: '',
    description: '',
    amount: '',
    currency: 'AUD',
    type: 'expense',
    transaction_type: null,
    user_id: null,
    client_id: null,
    hours_spent: '',
    bill_id: null,
    invoice_id: null,
    bank_transaction_id: '',
    conversion_rate: 1,
    _bank_amount: '',
    _bank_currency: '',
});

const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

const modalTypeOptions = [
    { value: 'expense', label: 'Expense' },
    { value: 'income', label: 'Income' },
    { value: 'bonus', label: 'Bonus' },
];

const userOptions = computed(() => {
    const list = users.value.map(user => ({
        value: user.id,
        label: user.name || 'Unknown User'
    }));

    if (transactionForm.value.bill_id) {
        const selectedBill = bills.value.find(b => b.id === transactionForm.value.bill_id);
        if (selectedBill && selectedBill.contractor) {
            const hasContractor = list.some(opt => opt.value == selectedBill.contractor_id);
            if (!hasContractor) {
                list.push({
                    value: selectedBill.contractor_id,
                    label: selectedBill.contractor.name || 'Contractor'
                });
            }
        }
    }
    return list;
});

const clientOptions = computed(() => {
    const list = clients.value.map(client => ({
        value: client.id,
        label: client.name || 'Unknown Client'
    }));

    if (transactionForm.value.invoice_id) {
        const selectedInvoice = invoices.value.find(i => i.id === transactionForm.value.invoice_id);
        if (selectedInvoice && selectedInvoice.client) {
            const hasClient = list.some(opt => opt.value == selectedInvoice.client_id);
            if (!hasClient) {
                list.push({
                    value: selectedInvoice.client_id,
                    label: selectedInvoice.client.name || 'Client'
                });
            }
        }
    }
    return list;
});

const billOptions = computed(() => {
    return bills.value.map(bill => {
        let label = `OZB${bill.id} - ${bill.amount} ${bill.currency || 'AUD'} (${bill.reference_number || 'No Ref'})`;
        if (!transactionForm.value.project_id && bill.project) {
            label += ` - ${bill.project.name}`;
        }
        if (transactionForm.value.user_id && bill.contractor_id !== transactionForm.value.user_id) {
            label += ` [WARNING: User Mismatch]`;
        }
        return { value: bill.id, label };
    });
});

const invoiceOptions = computed(() => {
    let filtered = invoices.value;
    if (transactionForm.value.client_id) {
        filtered = filtered.filter(i => i.client_id === transactionForm.value.client_id);
    }
    return filtered.map(invoice => {
        let label = `OZI${invoice.id} - ${invoice.total_amount} ${invoice.currency || 'AUD'} (${invoice.invoice_number || 'No Ref'})`;
        if (!transactionForm.value.project_id && invoice.project) {
            label += ` - ${invoice.project.name}`;
        }
        return { value: invoice.id, label };
    });
});

const projectOptions = computed(() => {
    return [
        { id: '', name: 'All Projects' },
        ...projects.value
    ];
});

const typeOptions = [
    { value: 'all', label: 'All Types' },
    { value: 'income', label: 'Income' },
    { value: 'expense', label: 'Expense' },
    { value: 'bonus', label: 'Bonus' }
];

const statusOptions = [
    { value: 'all', label: 'All Statuses' },
    { value: 'paid', label: 'Paid' },
    { value: 'unpaid', label: 'Unpaid' },
    { value: 'deleted', label: 'Deleted' },
];

const fetchTransactions = async (page = 1) => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/transactions', {
            params: {
                page: page,
                type: filterType.value,
                status: filterStatus.value,
                project_id: filterProject.value,
                search: searchQuery.value,
                date_from: filterDateFrom.value,
                date_to: filterDateTo.value,
            }
        });
        transactions.value = data.data;
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total
        };
    } catch (err) {
        error('Failed to load transactions.');
    } finally {
        loading.value = false;
    }
};

const fetchBankTransactions = async (page = 1) => {
    bankLoading.value = true;
    try {
        const { data } = await axios.get('/api/admin/bank-transactions', {
            params: { per_page: 50, page, type: bankTypeFilter.value, status: bankStatusFilter.value }
        });
        bankTransactions.value = data.data || [];
        bankPagination.value = {
            current_page: page,
            last_page: data.meta?.has_more ? page + 1 : page,
            total: 0
        };
    } catch (err) {
        error('Failed to load bank transactions.');
    } finally {
        bankLoading.value = false;
    }
};

watch([bankTypeFilter, bankStatusFilter], () => {
    fetchBankTransactions(1);
});

watch(activeTab, (val) => {
    if (val === 'bank' && bankTransactions.value.length === 0) {
        fetchBankTransactions();
    }
});

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

const fetchOutstandingDocs = async () => {
    try {
        const { data } = await axios.get('/api/admin/outstanding-docs');
        outstandingBills.value = data.bills || [];
        outstandingInvoices.value = data.invoices || [];
    } catch (err) {
        console.error('Failed to fetch outstanding docs', err);
    }
};

const handleSearch = () => {
    if (searchDebounce.value) clearTimeout(searchDebounce.value);
    searchDebounce.value = setTimeout(() => {
        fetchTransactions(1);
    }, 400);
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        fetchTransactions(page);
    }
};

const handleProjectChange = async () => {
    const pId = transactionForm.value.project_id;
    if (!pId) {
        users.value = [];
        clients.value = [];
        bills.value = outstandingBills.value;
        invoices.value = outstandingInvoices.value;
        return;
    }
    try {
        const [usersClientsRes, billsRes, invoicesRes] = await Promise.all([
            axios.get(`/api/projects/${pId}/sections/clients-users`),
            axios.get(`/api/projects/${pId}/bills`),
            axios.get(`/api/projects/${pId}/invoices`)
        ]);
        users.value = usersClientsRes.data.users || [];
        clients.value = usersClientsRes.data.clients || [];
        bills.value = (billsRes.data || []).filter(b => b.status === 'approved' || b.status === 'partial_paid');
        invoices.value = (invoicesRes.data || []).filter(i => i.status === 'authorised' || i.status === 'sent' || i.status === 'partial_paid');
    } catch (err) {
        console.error(err);
    }
};

const saveTransaction = async () => {
    formErrors.value = {};
    createLoading.value = true;
    try {
        let txType = transactionForm.value.transaction_type;
        if (txType && typeof txType === 'object') {
            txType = txType.value ?? txType.id ?? txType.name;
        }

        const payload = {
            ...transactionForm.value,
            transaction_type: txType,
            amount: Number(transactionForm.value.amount),
        };
        const response = await axios.post(`/api/projects/${transactionForm.value.project_id}/transactions`, payload);
        
        // Update the bank transaction status locally if linked
        if (transactionForm.value.bank_transaction_id && selectedBankTx.value) {
            const matchedTx = bankTransactions.value.find(item => item.id === selectedBankTx.value.id);
            if (matchedTx) {
                if (!matchedTx.local_transactions) {
                    matchedTx.local_transactions = [];
                }
                // Append the newly created local transaction details from API
                const newLocalTx = response.data;
                matchedTx.local_transactions.push(newLocalTx);

                // Deduct the linked bank equivalent amount based on the actual amount saved
                const bankAmount = Math.abs(matchedTx.amount);
                const conversionRate = Number(transactionForm.value.conversion_rate) || 1;
                const localAmount = Number(transactionForm.value.amount) || 0;
                const bankCurrency = matchedTx.currency || 'AUD';
                const localCurrency = transactionForm.value.currency || 'AUD';

                let linkedBankAmount = localAmount;
                if (localCurrency !== bankCurrency) {
                    if (localCurrency === 'PKR' && bankCurrency === 'AUD') {
                        linkedBankAmount = localAmount / conversionRate;
                    } else if (localCurrency === 'AUD' && bankCurrency === 'PKR') {
                        linkedBankAmount = localAmount * conversionRate;
                    } else {
                        linkedBankAmount = localAmount / conversionRate;
                    }
                }

                matchedTx.remaining_amount = Math.max(0, Number(((matchedTx.remaining_amount !== undefined ? matchedTx.remaining_amount : bankAmount) - linkedBankAmount).toFixed(2)));
                matchedTx.is_linked = matchedTx.remaining_amount <= 0.01;
            }
            selectedBankTx.value = null;
        }

        success('Transaction created successfully');
        showCreateModal.value = false;
        resetTransactionForm();
        fetchTransactions(1);
    } catch (err) {
        if (err.response?.status === 422) {
            formErrors.value = err.response.data.errors;
        } else {
            error(err.response?.data?.message || 'Failed to create transaction');
        }
    } finally {
        createLoading.value = false;
    }
};

const resetTransactionForm = () => {
    transactionForm.value = {
        project_id: '',
        description: '',
        amount: '',
        currency: 'AUD',
        type: 'expense',
        transaction_type: null,
        user_id: null,
        client_id: null,
        hours_spent: '',
        bill_id: null,
        invoice_id: null,
        bank_transaction_id: '',
    };
    users.value = [];
    clients.value = [];
    bills.value = outstandingBills.value;
    invoices.value = outstandingInvoices.value;
    formErrors.value = {};
};

watch(() => transactionForm.value.type, (newType) => {
    if (newType === 'income') {
        transactionForm.value.user_id = null;
        transactionForm.value.bill_id = null;
    } else {
        transactionForm.value.client_id = null;
        transactionForm.value.invoice_id = null;
    }
});

watch(() => transactionForm.value.bill_id, (newBillId) => {
    if (newBillId) {
        const selectedBill = bills.value.find(b => b.id === newBillId);
        if (selectedBill) {
            transactionForm.value.user_id = selectedBill.contractor_id;
            if (!transactionForm.value.project_id && selectedBill.project_id) {
                transactionForm.value.project_id = selectedBill.project_id;
            }
            if (selectedBill.transaction_type) {
                transactionForm.value.transaction_type = {
                    id: selectedBill.transaction_type_id,
                    name: selectedBill.transaction_type.name
                };
            }
            if (transactionForm.value.bank_transaction_id) {
                transactionForm.value.currency = selectedBill.currency || 'AUD';
                
                let defaultRate = transactionForm.value.conversion_rate;
                if (!defaultRate || defaultRate === 1) {
                    defaultRate = convertCurrency(1, transactionForm.value._bank_currency, transactionForm.value.currency);
                }
                transactionForm.value.conversion_rate = defaultRate;
                
                const billRemaining = selectedBill.remaining_amount !== undefined ? selectedBill.remaining_amount : selectedBill.amount;
                const bankRemainingInLocal = Number((transactionForm.value._bank_amount * defaultRate).toFixed(2));
                const maxAllowed = Math.min(bankRemainingInLocal, billRemaining);
                
                transactionForm.value.amount = maxAllowed;
                transactionForm.value._max_allowed = maxAllowed;
            } else {
                transactionForm.value.amount = selectedBill.remaining_amount !== undefined ? selectedBill.remaining_amount : selectedBill.amount;
                transactionForm.value.currency = selectedBill.currency || 'AUD';
            }
        }
    }
});

watch(() => transactionForm.value.invoice_id, (newInvoiceId) => {
    if (newInvoiceId) {
        const selectedInvoice = invoices.value.find(i => i.id === newInvoiceId);
        if (selectedInvoice) {
            transactionForm.value.client_id = selectedInvoice.client_id;
            if (!transactionForm.value.project_id && selectedInvoice.project_id) {
                transactionForm.value.project_id = selectedInvoice.project_id;
            }
            if (transactionForm.value.bank_transaction_id) {
                transactionForm.value.currency = selectedInvoice.currency || 'AUD';
                
                let defaultRate = transactionForm.value.conversion_rate;
                if (!defaultRate || defaultRate === 1) {
                    defaultRate = convertCurrency(1, transactionForm.value._bank_currency, transactionForm.value.currency);
                }
                transactionForm.value.conversion_rate = defaultRate;
                
                const invoiceRemaining = selectedInvoice.remaining_amount !== undefined ? selectedInvoice.remaining_amount : selectedInvoice.total_amount;
                const bankRemainingInLocal = Number((transactionForm.value._bank_amount * defaultRate).toFixed(2));
                const maxAllowed = Math.min(bankRemainingInLocal, invoiceRemaining);
                
                transactionForm.value.amount = maxAllowed;
                transactionForm.value._max_allowed = maxAllowed;
            } else {
                transactionForm.value.amount = selectedInvoice.remaining_amount !== undefined ? selectedInvoice.remaining_amount : selectedInvoice.total_amount;
                transactionForm.value.currency = selectedInvoice.currency || 'AUD';
            }
        }
    }
});

// Watch filters to trigger fetch
watch([filterDateFrom, filterDateTo, filterStatus, filterType, filterProject], () => {
    fetchTransactions(1);
});

// File upload helpers
const openUploadModal = (tx) => {
    selectedTransactionForUpload.value = tx;
    uploadFile.value = null;
    showUploadModal.value = true;
};

const handleFileChange = (e) => {
    uploadFile.value = e.target.files[0];
};

const submitAttachment = async () => {
    if (!uploadFile.value || !selectedTransactionForUpload.value) return;
    uploadLoading.value = true;
    const formData = new FormData();
    formData.append('document', uploadFile.value);
    try {
        await axios.post(`/api/transactions/${selectedTransactionForUpload.value.id}/attachments`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        success('Attachment uploaded successfully');
        showUploadModal.value = false;
        fetchTransactions(pagination.value.current_page);
    } catch (err) {
        error(err.response?.data?.message || 'Failed to upload attachment');
    } finally {
        uploadLoading.value = false;
    }
};

// Document linking helpers
const openBankLinkModal = (btx, type) => {
    selectedBankTx.value = btx;
    const remainingAmount = btx.remaining_amount !== undefined ? btx.remaining_amount : Math.abs(btx.amount);
    
    let rate = 1;
    if (btx.client_rate) {
        rate = Number(btx.client_rate);
    } else if (btx.details && btx.details.client_rate) {
        rate = Number(btx.details.client_rate);
    }

    transactionForm.value = {
        project_id: '',
        description: btx.merchant_name || btx.description || btx.reference || '',
        amount: remainingAmount,
        currency: btx.currency || 'AUD',
        type: type,
        transaction_type: null,
        user_id: null,
        client_id: null,
        hours_spent: '',
        bill_id: null,
        invoice_id: null,
        bank_transaction_id: btx.id || btx.reference_id || '',
        conversion_rate: rate,
        _bank_amount: remainingAmount,
        _bank_currency: btx.currency || 'AUD',
        _max_allowed: null
    };
    
    bills.value = outstandingBills.value;
    invoices.value = outstandingInvoices.value;
    
    showCreateModal.value = true;
};

const calculateAmountFromRate = () => {
    if (transactionForm.value._bank_amount && transactionForm.value.conversion_rate) {
        transactionForm.value.amount = Number((transactionForm.value._bank_amount * transactionForm.value.conversion_rate).toFixed(2));
    }
};

const openLinkDocModal = async (tx) => {
    selectedTransactionForLink.value = tx;
    linkDocForm.value = {
        bill_id: tx.bill_id || '',
        invoice_id: tx.invoice_id || ''
    };
    showLinkDocModal.value = true;
    
    try {
        const [billsRes, invoicesRes] = await Promise.all([
            axios.get(`/api/projects/${tx.project_id}/bills`),
            axios.get(`/api/projects/${tx.project_id}/invoices`)
        ]);
        bills.value = (billsRes.data || []).filter(b => b.status === 'approved' || b.status === 'partial_paid');
        invoices.value = (invoicesRes.data || []).filter(i => i.status === 'authorised' || i.status === 'sent' || i.status === 'partial_paid');
    } catch (err) {
        console.error(err);
    }
};

const submitLinkDoc = async () => {
    if (!selectedTransactionForLink.value) return;
    linkDocLoading.value = true;
    const tx = selectedTransactionForLink.value;
    const isExpense = tx.type === 'expense' || tx.type === 'bonus';
    const endpoint = isExpense 
        ? `/api/transactions/${tx.id}/link-bill` 
        : `/api/transactions/${tx.id}/link-invoice`;
    const payload = isExpense 
        ? { bill_id: linkDocForm.value.bill_id } 
        : { invoice_id: linkDocForm.value.invoice_id };

    try {
        await axios.post(endpoint, payload);
        success('Transaction linked successfully');
        showLinkDocModal.value = false;
        fetchTransactions(pagination.value.current_page);
        fetchBankTransactions(bankPagination.value?.current_page || 1);
    } catch (err) {
        error(err.response?.data?.message || 'Failed to link transaction');
    } finally {
        linkDocLoading.value = false;
    }
};

const handleUnlink = async (tx) => {
    if (!confirm('Are you sure you want to unlink this transaction?')) return;
    const isExpense = tx.type === 'expense' || tx.type === 'bonus';
    const endpoint = isExpense 
        ? `/api/transactions/${tx.id}/unlink-bill` 
        : `/api/transactions/${tx.id}/unlink-invoice`;

    try {
        await axios.post(endpoint);
        success('Transaction unlinked successfully');
        fetchTransactions(pagination.value.current_page);
        fetchBankTransactions(bankPagination.value?.current_page || 1);
    } catch (err) {
        error(err.response?.data?.message || 'Failed to unlink transaction');
    }
};

const handleDelete = async (tx) => {
    if (!confirm('Are you sure you want to delete this transaction?')) return;
    try {
        await axios.delete(`/api/transactions/${tx.id}`);
        success('Transaction soft deleted');
        fetchTransactions(pagination.value.current_page);
        fetchBankTransactions(bankPagination.value?.current_page || 1);
    } catch (err) {
        error(err.response?.data?.message || 'Failed to delete transaction');
    }
};

const handleRestore = async (tx) => {
    try {
        await axios.post(`/api/transactions/${tx.id}/restore`);
        success('Transaction restored successfully');
        fetchTransactions(pagination.value.current_page);
        fetchBankTransactions(bankPagination.value?.current_page || 1);
    } catch (err) {
        error(err.response?.data?.message || 'Failed to restore transaction');
    }
};

onMounted(async () => {
    await fetchCurrencyRates();
    fetchTransactions();
    fetchProjects();
    fetchOutstandingDocs();
});

const getStatusClass = (isPaid) => {
    return isPaid ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800';
};

const getStatusLabel = (isPaid) => {
    return isPaid ? 'PAID' : 'UNPAID';
};

const getTypeClass = (type) => {
    switch (type?.toLowerCase()) {
        case 'income': return 'bg-blue-100 text-blue-800';
        case 'expense': return 'bg-red-100 text-red-800';
        case 'bonus': return 'bg-purple-100 text-purple-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-AU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    } catch (e) {
        return dateStr;
    }
};
</script>

<template>
    <Head title="Project Transactions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Project Transactions</h2>
                <PrimaryButton @click="showCreateModal = true">Create Transaction</PrimaryButton>
            </div>
        </template>

        <div class="py-6 sm:py-12">
            <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button 
                            @click="activeTab = 'system'"
                            :class="[activeTab === 'system' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']"
                        >
                            System Transactions
                        </button>
                        <button 
                            @click="activeTab = 'bank'"
                            :class="[activeTab === 'bank' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']"
                        >
                            Bank Transactions (Airwallex)
                        </button>
                    </nav>
                </div>

                <div v-if="activeTab === 'system'" class="space-y-6">
                    <!-- Filters & Search -->
                <div class="bg-white p-4 shadow sm:rounded-lg border border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Search</label>
                            <TextInput
                                v-model="searchQuery"
                                @input="handleSearch"
                                type="text"
                                placeholder="Search desc, amount, ref..."
                                class="block w-full text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                            <SelectDropdown
                                v-model="filterStatus"
                                :options="statusOptions"
                                valueKey="value"
                                labelKey="label"
                                placeholder="All Statuses"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Type</label>
                            <SelectDropdown
                                v-model="filterType"
                                :options="typeOptions"
                                valueKey="value"
                                labelKey="label"
                                placeholder="All Types"
                            />
                        </div>
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
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">From Date</label>
                            <input
                                v-model="filterDateFrom"
                                type="date"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">To Date</label>
                            <input
                                v-model="filterDateTo"
                                type="date"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                            />
                        </div>
                    </div>
                </div>

                <!-- Transactions List -->
                <div class="bg-white overflow-x-auto shadow sm:rounded-lg border border-gray-200">
                    <div v-if="loading" class="p-12 text-center text-gray-500">Loading transactions...</div>
                    <div v-else-if="!transactions.length" class="p-12 text-center text-gray-500">No transactions found.</div>
                    <div v-else>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attachments</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="tx in transactions" :key="tx.id">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(tx.created_at) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ tx.project?.name || 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500">
                                        {{ tx.description }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span :class="['px-2 py-1 text-xs font-bold rounded-full', getTypeClass(tx.type)]">
                                            {{ tx.type?.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500">
                                        <div v-if="tx.user">{{ tx.user.name }} (User)</div>
                                        <div v-else-if="tx.client">{{ tx.client.name }} (Client)</div>
                                        <div v-else>-</div>
                                    </td>
                                    <td class="px-4 py-4 text-sm font-semibold text-gray-900">
                                        {{ formatCurrency(tx.amount, tx.currency || tx.project?.currency) }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span :class="['px-2 py-1 text-xs font-bold rounded-full', getStatusClass(tx.is_paid)]">
                                            {{ getStatusLabel(tx.is_paid) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(tx.payment_date) }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ tx.bank_transaction_id || '-' }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500">
                                        <div class="flex flex-col gap-1">
                                            <div v-for="file in tx.files" :key="file.id" class="flex items-center gap-1">
                                                <a :href="file.path_url" target="_blank" class="text-indigo-600 hover:text-indigo-900 truncate max-w-[120px]" :title="file.filename">
                                                    {{ file.filename }}
                                                </a>
                                            </div>
                                            <button @click="openUploadModal(tx)" class="text-xs text-indigo-600 hover:underline flex items-center gap-0.5">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                Upload PDF
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex gap-3 items-center">
                                            <!-- Restore for deleted -->
                                            <button v-if="tx.deleted_at" @click="handleRestore(tx)" class="text-green-600 hover:text-green-950 font-semibold text-xs">
                                                Restore
                                            </button>
                                            
                                            <!-- Normal active actions -->
                                            <template v-else>
                                                <template v-if="tx.type === 'expense' || tx.type === 'bonus'">
                                                    <button v-if="!tx.bill_id" @click="openLinkDocModal(tx)" class="text-indigo-600 hover:text-indigo-955 font-semibold text-xs">
                                                        Link Bill
                                                    </button>
                                                    <div v-else class="flex space-x-1 items-center">
                                                        <button @click="openLinkedDocSidebar(tx.bill, 'bill')" class="text-indigo-600 hover:text-indigo-950 font-semibold text-xs">
                                                            View Bill
                                                        </button>
                                                        <span class="text-gray-300">|</span>
                                                        <button @click="handleUnlink(tx)" class="text-amber-600 hover:text-amber-950 font-semibold text-xs">
                                                            Unlink
                                                        </button>
                                                    </div>
                                                </template>
                                                <template v-else-if="tx.type === 'income'">
                                                    <button v-if="!tx.invoice_id" @click="openLinkDocModal(tx)" class="text-indigo-600 hover:text-indigo-955 font-semibold text-xs">
                                                        Link Invoice
                                                    </button>
                                                    <div v-else class="flex space-x-1 items-center">
                                                        <button @click="openLinkedDocSidebar(tx.invoice, 'invoice')" class="text-indigo-600 hover:text-indigo-950 font-semibold text-xs">
                                                            View Invoice
                                                        </button>
                                                        <span class="text-gray-300">|</span>
                                                        <button @click="handleUnlink(tx)" class="text-amber-600 hover:text-amber-955 font-semibold text-xs">
                                                            Unlink
                                                        </button>
                                                    </div>
                                                </template>

                                                <button @click="handleDelete(tx)" class="text-red-600 hover:text-red-950 font-semibold text-xs">
                                                    Delete
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Pagination Footer -->
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <SecondaryButton @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">Previous</SecondaryButton>
                                <SecondaryButton @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">Next</SecondaryButton>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing page <span class="font-medium">{{ pagination.current_page }}</span> of <span class="font-medium">{{ pagination.last_page }}</span> (<span class="font-medium">{{ pagination.total }}</span> results)
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <SecondaryButton @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">Previous</SecondaryButton>
                                    <SecondaryButton @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">Next</SecondaryButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div> <!-- End System Transactions Tab -->

                <!-- Bank Transactions Tab -->
                <div v-else-if="activeTab === 'bank'" class="space-y-6">
                    <!-- Bank Filter -->
                    <div class="bg-white p-4 shadow sm:rounded-lg border border-gray-200 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                        <div class="flex space-x-2">
                            <button 
                                @click="bankFilter = 'unreconciled'"
                                :class="[bankFilter === 'unreconciled' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-4 py-2 rounded-md text-sm font-medium transition-colors']"
                            >
                                Pending Match
                            </button>
                            <button 
                                @click="bankFilter = 'reconciled'"
                                :class="[bankFilter === 'reconciled' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-4 py-2 rounded-md text-sm font-medium transition-colors']"
                            >
                                Linked
                            </button>
                            <button 
                                @click="bankFilter = 'all'"
                                :class="[bankFilter === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-4 py-2 rounded-md text-sm font-medium transition-colors']"
                            >
                                All
                            </button>
                        </div>
                        <div class="flex space-x-2 items-center">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Type:</span>
                            <button 
                                @click="bankTypeFilter = 'bills'"
                                :class="[bankTypeFilter === 'bills' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-3 py-1.5 rounded-md text-xs font-medium transition-colors']"
                            >
                                Outgoing (Bills)
                            </button>
                            <button 
                                @click="bankTypeFilter = 'expenses'"
                                :class="[bankTypeFilter === 'expenses' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-3 py-1.5 rounded-md text-xs font-medium transition-colors']"
                            >
                                Outgoing (Expenses)
                            </button>
                            <button 
                                @click="bankTypeFilter = 'other'"
                                :class="[bankTypeFilter === 'other' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-3 py-1.5 rounded-md text-xs font-medium transition-colors']"
                            >
                                Outgoing (Other)
                            </button>
                            <button 
                                @click="bankTypeFilter = 'income'"
                                :class="[bankTypeFilter === 'income' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-3 py-1.5 rounded-md text-xs font-medium transition-colors']"
                            >
                                Incoming (Income)
                            </button>
                        </div>
                        <div class="flex space-x-2 items-center border-l border-gray-200 pl-4 hidden md:flex">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Status:</span>
                            <button 
                                @click="bankStatusFilter = 'all'"
                                :class="[bankStatusFilter === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-3 py-1.5 rounded-md text-xs font-medium transition-colors']"
                            >
                                All
                            </button>
                            <button 
                                @click="bankStatusFilter = 'SETTLED'"
                                :class="[bankStatusFilter === 'SETTLED' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-3 py-1.5 rounded-md text-xs font-medium transition-colors']"
                            >
                                Settled
                            </button>
                            <button 
                                @click="bankStatusFilter = 'PENDING'"
                                :class="[bankStatusFilter === 'PENDING' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'px-3 py-1.5 rounded-md text-xs font-medium transition-colors']"
                            >
                                Pending
                            </button>
                        </div>
                    </div>

                    <div class="bg-white overflow-x-auto shadow sm:rounded-lg border border-gray-200">
                        <div v-if="bankLoading" class="p-12 text-center text-gray-500">Loading bank transactions...</div>
                        <div v-else-if="!filteredBankTransactions.length" class="p-12 text-center text-gray-500">No bank transactions found for this filter.</div>
                        <div v-else>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Net / Fee</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type / Src</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank Ref</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(btx, idx) in filteredBankTransactions" :key="idx" class="hover:bg-gray-50 cursor-pointer" @click="openBankTxDetailsSidebar(btx.id)">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(btx.created_at || btx.date) }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 font-medium hover:text-indigo-600">
                                            <div>{{ btx.merchant_name || btx.description || btx.reference || 'N/A' }}</div>
                                            <div v-if="btx.payment_details?.beneficiary?.bank_details?.account_name" class="text-xs text-indigo-600 mt-1 flex items-center font-normal">
                                                <svg class="h-3.5 w-3.5 mr-1 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                To: {{ btx.payment_details.beneficiary.bank_details.account_name }}
                                                <span v-if="btx.payment_details.reference" class="text-gray-400 ml-1 font-light font-sans">({{ btx.payment_details.reference }})</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            {{ formatCurrency(btx.amount, btx.currency || 'AUD') }}
                                            <span v-if="btx.is_consolidated" class="block text-xs font-semibold text-purple-700 mt-0.5">
                                                Funded: {{ formatCurrency(btx.funding_amount, btx.funding_currency) }}
                                            </span>
                                            <span v-if="btx.remaining_amount !== undefined && btx.remaining_amount < Math.abs(btx.amount)" class="block text-xs font-normal text-gray-500">
                                                (Rem: {{ formatCurrency(btx.remaining_amount, btx.currency || 'AUD') }})
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatCurrency(btx.net, btx.currency || 'AUD') }} /
                                            <span class="text-xs text-red-500">{{ formatCurrency(btx.fee, btx.currency || 'AUD') }}</span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            <span :class="[btx.status === 'SETTLED' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800', 'px-2 py-0.5 rounded-full text-xs font-semibold']">
                                                {{ btx.status || 'PENDING' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span v-if="btx.is_consolidated" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-800 mb-1">Consolidated</span>
                                            <span class="block font-medium">{{ btx.transaction_type }}</span>
                                            <span class="block text-xs text-gray-400">{{ btx.source_type }}</span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ btx.id || btx.reference_id || 'N/A' }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900" @click.stop>
                                            <div class="flex flex-col space-y-2">
                                                <div v-if="btx.local_transactions?.length > 0" class="flex items-center space-x-2">
                                                    <span :class="[btx.remaining_amount <= 0.01 ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800', 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium']">
                                                        {{ btx.remaining_amount <= 0.01 ? 'Linked' : 'Partially Linked' }}
                                                    </span>
                                                </div>
                                                <div v-if="btx.remaining_amount > 0.01 || btx.remaining_amount === undefined" class="flex space-x-2 items-center">
                                                    <template v-if="btx.amount < 0">
                                                        <PrimaryButton type="button" @click="openBankLinkModal(btx, 'expense')" class="text-xs px-2 py-1">
                                                            Link Bill
                                                        </PrimaryButton>
                                                        <button type="button" @click="openBankLinkModal(btx, 'income')" class="text-[10px] text-gray-400 hover:text-indigo-600 underline px-1" title="Exception: Link to Invoice">
                                                            + Inv
                                                        </button>
                                                    </template>
                                                    <template v-else>
                                                        <PrimaryButton type="button" @click="openBankLinkModal(btx, 'income')" class="text-xs px-2 py-1">
                                                            Link Invoice
                                                        </PrimaryButton>
                                                        <button type="button" @click="openBankLinkModal(btx, 'expense')" class="text-[10px] text-gray-400 hover:text-indigo-600 underline px-1" title="Exception: Link to Bill">
                                                            + Bill
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <!-- Pagination Footer -->
                            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                                <div class="flex-1 flex justify-between sm:hidden">
                                    <SecondaryButton @click="fetchBankTransactions(bankPagination.current_page - 1)" :disabled="bankPagination.current_page === 1">Previous</SecondaryButton>
                                    <SecondaryButton @click="fetchBankTransactions(bankPagination.current_page + 1)" :disabled="bankPagination.current_page === bankPagination.last_page">Next</SecondaryButton>
                                </div>
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            Showing page <span class="font-medium">{{ bankPagination.current_page }}</span>
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <SecondaryButton @click="fetchBankTransactions(bankPagination.current_page - 1)" :disabled="bankPagination.current_page === 1">Previous</SecondaryButton>
                                        <SecondaryButton @click="fetchBankTransactions(bankPagination.current_page + 1)" :disabled="bankPagination.current_page === bankPagination.last_page">Next</SecondaryButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- End Bank Transactions Tab -->
            </div>
        </div>

        <!-- Create Transaction Modal -->
        <Modal :show="showCreateModal" @close="() => { showCreateModal = false; resetTransactionForm(); }" maxWidth="2xl">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 border-b pb-3 mb-4">Create Transaction</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="project_id" value="Project" />
                        <SelectDropdown
                            id="project_id"
                            v-slot:default
                            v-model="transactionForm.project_id"
                            :options="projects"
                            valueKey="id"
                            labelKey="name"
                            placeholder="Select a project"
                            @change="handleProjectChange"
                        />
                        <InputError :message="formErrors.project_id?.[0]" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="type" value="Type" />
                        <SelectDropdown
                            id="type"
                            v-model="transactionForm.type"
                            :options="modalTypeOptions"
                        />
                        <InputError :message="formErrors.type?.[0]" class="mt-2" />
                    </div>

                    <!-- User Selector (Expense/Bonus) -->
                    <div v-if="transactionForm.type === 'expense' || transactionForm.type === 'bonus'">
                        <InputLabel for="user_id" value="User (Contractor)" />
                        <SelectDropdown
                            id="user_id"
                            v-model="transactionForm.user_id"
                            :options="userOptions"
                            placeholder="Select user"
                            :disabled="!!transactionForm.bill_id"
                        />
                        <InputError :message="formErrors.user_id?.[0]" class="mt-2" />
                    </div>

                    <!-- Client Selector (Income) -->
                    <div v-if="transactionForm.type === 'income'">
                        <InputLabel for="client_id" value="Client" />
                        <SelectDropdown
                            id="client_id"
                            v-model="transactionForm.client_id"
                            :options="clientOptions"
                            placeholder="Select client"
                            :disabled="!!transactionForm.invoice_id"
                        />
                        <InputError :message="formErrors.client_id?.[0]" class="mt-2" />
                    </div>

                    <!-- Bill Linker (Expense/Bonus) -->
                    <div v-if="transactionForm.type === 'expense' || transactionForm.type === 'bonus'">
                        <InputLabel for="bill_id" value="Link to Bill (Optional)" />
                        <SelectDropdown
                            id="bill_id"
                            v-model="transactionForm.bill_id"
                            :options="billOptions"
                            placeholder="Select bill to pay"
                        />
                        <InputError :message="formErrors.bill_id?.[0]" class="mt-2" />
                    </div>

                    <!-- Invoice Linker (Income) -->
                    <div v-if="transactionForm.type === 'income'">
                        <InputLabel for="invoice_id" value="Link to Invoice (Optional)" />
                        <SelectDropdown
                            id="invoice_id"
                            v-model="transactionForm.invoice_id"
                            :options="invoiceOptions"
                            placeholder="Select invoice to pay"
                        />
                        <InputError :message="formErrors.invoice_id?.[0]" class="mt-2" />
                    </div>

                    <!-- Bank Transaction ID -->
                    <div class="md:col-span-2">
                        <InputLabel for="bank_transaction_id" value="Bank Transaction ID (Optional)" />
                        <TextInput
                            id="bank_transaction_id"
                            v-model="transactionForm.bank_transaction_id"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Enter bank reference ID for reconciliation"
                        />
                        <InputError :message="formErrors.bank_transaction_id?.[0]" class="mt-2" />
                    </div>

                    <!-- Bank Transaction Details & Conversion Rate Edit -->
                    <div v-if="transactionForm.bank_transaction_id" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 border border-dashed border-indigo-200 p-4 rounded-md bg-indigo-50/30">
                        <div>
                            <InputLabel for="bank_amount_display" :value="'Bank Amount (' + transactionForm._bank_currency + ')'" />
                            <TextInput
                                id="bank_amount_display"
                                :value="transactionForm._bank_amount"
                                type="text"
                                class="mt-1 block w-full bg-gray-50 text-gray-500"
                                disabled
                            />
                        </div>
                        <div>
                            <InputLabel for="conversion_rate" value="Conversion Rate (1 Bank Unit = ?)" />
                            <TextInput
                                id="conversion_rate"
                                v-model="transactionForm.conversion_rate"
                                type="number"
                                step="0.000001"
                                class="mt-1 block w-full bg-white font-medium border-indigo-300 focus:border-indigo-500"
                                @input="calculateAmountFromRate"
                            />
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="description" value="Description" />
                        <TextInput
                            id="description"
                            v-model="transactionForm.description"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="formErrors.description?.[0]" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="amount" :value="'Amount (' + transactionForm.currency + ')'" />
                        <TextInput
                            id="amount"
                            v-model="transactionForm.amount"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <div v-if="transactionForm._max_allowed !== null" class="mt-1 text-xs text-gray-500">
                            Maximum allowed: {{ transactionForm._max_allowed }} {{ transactionForm.currency }}
                        </div>
                        <InputError :message="formErrors.amount?.[0]" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="currency" value="Currency" />
                        <SelectDropdown
                            id="currency"
                            v-model="transactionForm.currency"
                            :options="currencyOptions"
                            :disabled="!!transactionForm.bill_id || !!transactionForm.invoice_id"
                        />
                        <InputError :message="formErrors.currency?.[0]" class="mt-2" />
                    </div>

                    <div>
                        <BasicPropertyInput
                            v-model="transactionForm.transaction_type"
                            label="Transaction Type"
                            placeholder="Select or add transaction type"
                            :required="true"
                            search-url="/api/transaction-types/search"
                            :disabled="!!transactionForm.bill_id"
                        />
                        <InputError :message="formErrors.transaction_type_id?.[0]" class="mt-2" />
                    </div>

                    <div v-if="transactionForm.type === 'expense' || transactionForm.type === 'bonus'">
                        <InputLabel for="hours_spent" value="Hours Spent (Optional)" />
                        <TextInput
                            id="hours_spent"
                            v-model="transactionForm.hours_spent"
                            type="number"
                            step="0.1"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="formErrors.hours_spent?.[0]" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <SecondaryButton :disabled="createLoading" @click="() => { showCreateModal = false; resetTransactionForm(); }">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="createLoading" @click="saveTransaction">
                        {{ createLoading ? 'Creating...' : 'Create' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Upload Attachment Modal -->
        <Modal :show="showUploadModal" @close="showUploadModal = false" maxWidth="lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 border-b pb-3 mb-4">Upload PDF Attachment</h3>
                <div class="space-y-4">
                    <p class="text-sm text-gray-500">Upload a PDF receipt or invoice copy to attach to this transaction.</p>
                    <div>
                        <input
                            type="file"
                            accept=".pdf"
                            @change="handleFileChange"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <SecondaryButton :disabled="uploadLoading" @click="showUploadModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="uploadLoading || !uploadFile" @click="submitAttachment">
                        {{ uploadLoading ? 'Uploading...' : 'Upload' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Link to Document Modal -->
        <Modal :show="showLinkDocModal" @close="showLinkDocModal = false" maxWidth="lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 border-b pb-3 mb-4">
                    Link Transaction to {{ selectedTransactionForLink?.type === 'expense' || selectedTransactionForLink?.type === 'bonus' ? 'Bill' : 'Invoice' }}
                </h3>
                <div class="space-y-4">
                    <div v-if="selectedTransactionForLink?.type === 'expense' || selectedTransactionForLink?.type === 'bonus'">
                        <InputLabel for="link_bill_id" value="Select Bill" />
                        <SelectDropdown
                            id="link_bill_id"
                            v-model="linkDocForm.bill_id"
                            :options="billOptions"
                            placeholder="Select bill to link"
                        />
                    </div>
                    <div v-else-if="selectedTransactionForLink?.type === 'income'">
                        <InputLabel for="link_invoice_id" value="Select Invoice" />
                        <SelectDropdown
                            id="link_invoice_id"
                            v-model="linkDocForm.invoice_id"
                            :options="invoiceOptions"
                            placeholder="Select invoice to link"
                        />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <SecondaryButton :disabled="linkDocLoading" @click="showLinkDocModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="linkDocLoading" @click="submitLinkDoc">
                        {{ linkDocLoading ? 'Linking...' : 'Link' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <RightSidebar v-model:show="showLinkedDocSidebar" :title="linkedDocType === 'bill' ? `Bill Details #${selectedLinkedDoc?.id}` : `Invoice Details #${selectedLinkedDoc?.id}`">
            <template #content>
                <div v-if="selectedLinkedDoc" class="space-y-6">
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <h4 class="text-md font-semibold text-gray-900 mb-4">Summary</h4>
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div class="col-span-2">
                                <dt class="text-gray-500 font-medium">Description</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedLinkedDoc.description || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Amount</dt>
                                <dd class="text-gray-900 mt-0.5 font-semibold">
                                    {{ formatCurrency(convertCurrency(Number(selectedLinkedDoc.amount || selectedLinkedDoc.total_amount || 0), selectedLinkedDoc.currency, displayCurrency), displayCurrency) }}
                                    <div v-if="selectedLinkedDoc.currency && selectedLinkedDoc.currency.toUpperCase() !== displayCurrency.toUpperCase()" class="text-[10px] text-gray-400 font-medium mt-0.5">
                                        {{ formatCurrency(Number(selectedLinkedDoc.amount || selectedLinkedDoc.total_amount || 0), selectedLinkedDoc.currency) }}
                                    </div>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Status</dt>
                                <dd class="text-gray-900 mt-0.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 capitalize">
                                        {{ selectedLinkedDoc.status }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Total Paid</dt>
                                <dd class="text-gray-900 mt-0.5 font-semibold text-green-700">
                                    {{ formatCurrency(totalPaidLinkedDoc, displayCurrency) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Remaining</dt>
                                <dd class="text-gray-900 mt-0.5 font-semibold text-red-600">
                                    {{ formatCurrency(remainingAmountLinkedDoc, displayCurrency) }}
                                </dd>
                            </div>
                            <div v-if="linkedDocType === 'bill'">
                                <dt class="text-gray-500 font-medium">Contractor</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedLinkedDoc.contractor?.name || 'N/A' }}</dd>
                            </div>
                            <div v-else>
                                <dt class="text-gray-500 font-medium">Client</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedLinkedDoc.client?.name || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Project</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedLinkedDoc.project?.name || 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="border-t pt-4">
                        <h4 class="text-md font-semibold text-gray-900 mb-2">Internal Notes / Info</h4>
                        <p class="text-sm text-gray-600">Created: {{ formatDate(selectedLinkedDoc.created_at) }}</p>
                    </div>

                    <!-- Transaction History -->
                    <div class="border-t pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-md font-semibold text-gray-900">Linked Transactions</h4>
                            <div class="w-28">
                                <SelectDropdown
                                    id="sidebar-display-currency"
                                    v-model="displayCurrency"
                                    :options="currencyOptions"
                                    placeholder="Currency"
                                />
                            </div>
                        </div>
                        <div v-if="selectedLinkedDoc.transactions && selectedLinkedDoc.transactions.length" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Txs Amount</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="tx in selectedLinkedDoc.transactions" :key="tx.id">
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ formatDate(tx.created_at) }}</td>
                                        <td class="px-3 py-2 text-gray-900">{{ tx.description || '—' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-500 font-medium">
                                            {{ formatCurrency(Number(tx.amount || 0), tx.currency) }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap font-semibold text-indigo-700">
                                            {{ formatCurrency(convertCurrency(Number(tx.amount || 0), tx.currency, displayCurrency), displayCurrency) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                            No transactions linked to this document.
                        </div>
                    </div>
                </div>
            </template>
        </RightSidebar>

        <RightSidebar v-model:show="showBankTxDetailsSidebar" :title="`Bank Transaction Details`">
            <template #content>
                <div v-if="loadingBankTxDetails" class="p-12 text-center text-gray-500">
                    Loading bank transaction details...
                </div>
                <div v-else-if="selectedBankTxDetails" class="space-y-6">
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <h4 class="text-md font-semibold text-gray-900 mb-4">Airwallex Core Data</h4>
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div class="col-span-2">
                                <dt class="text-gray-500 font-medium">Description</dt>
                                <dd class="text-gray-900 mt-0.5 font-medium text-base">{{ selectedBankTxDetails.description || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">ID</dt>
                                <dd class="text-gray-900 mt-0.5 select-all">{{ selectedBankTxDetails.id }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Status</dt>
                                <dd class="text-gray-900 mt-0.5">
                                    <span :class="[selectedBankTxDetails.status === 'SETTLED' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800', 'px-2 py-0.5 rounded-full text-xs font-semibold']">
                                        {{ selectedBankTxDetails.status }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Amount</dt>
                                <dd class="text-gray-900 mt-0.5 font-bold text-lg text-indigo-900">
                                    {{ formatCurrency(selectedBankTxDetails.amount, selectedBankTxDetails.currency) }}
                                </dd>
                            </div>
                            <div v-if="selectedBankTxDetails.is_consolidated">
                                <dt class="text-gray-500 font-medium">Funding Amount</dt>
                                <dd class="text-gray-900 mt-0.5 font-bold text-indigo-900">
                                    {{ formatCurrency(selectedBankTxDetails.funding_amount, selectedBankTxDetails.funding_currency) }}
                                </dd>
                            </div>
                            <div v-if="selectedBankTxDetails.is_consolidated">
                                <dt class="text-gray-500 font-medium">Exchange Rate</dt>
                                <dd class="text-gray-900 mt-0.5 font-semibold text-purple-700">
                                    {{ selectedBankTxDetails.client_rate }} ({{ selectedBankTxDetails.currency_pair }})
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Net Outflow</dt>
                                <dd class="text-gray-900 mt-0.5">{{ formatCurrency(selectedBankTxDetails.net, selectedBankTxDetails.currency) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Fee</dt>
                                <dd class="text-gray-900 mt-0.5 text-red-600 font-medium">{{ formatCurrency(selectedBankTxDetails.fee, selectedBankTxDetails.currency) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Remaining Bal</dt>
                                <dd class="text-gray-900 mt-0.5 font-semibold text-green-700">
                                    {{ formatCurrency(selectedBankTxDetails.remaining_amount, selectedBankTxDetails.currency) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="border-t pt-4">
                        <h4 class="text-md font-semibold text-gray-900 mb-4">Metadata</h4>
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500 font-medium">Transaction Type</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedBankTxDetails.transaction_type }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Source Type</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedBankTxDetails.source_type }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Source ID</dt>
                                <dd class="text-gray-900 mt-0.5 text-xs select-all">{{ selectedBankTxDetails.source_id || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Created At</dt>
                                <dd class="text-gray-900 mt-0.5">{{ formatDate(selectedBankTxDetails.created_at) }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-gray-500 font-medium">Settled At</dt>
                                <dd class="text-gray-900 mt-0.5">{{ formatDate(selectedBankTxDetails.settled_at) }}</dd>
                            </div>
                        </dl>
                    </div>

                     <div v-if="selectedBankTxDetails.payment_details" class="border-t pt-4">
                        <h4 class="text-md font-semibold text-gray-900 mb-4">Payment Details (Airwallex)</h4>
                        <dl class="grid grid-cols-2 gap-4 text-sm bg-indigo-50/50 p-4 rounded-lg border border-indigo-100">
                            <div class="col-span-2">
                                <dt class="text-gray-500 font-medium">Beneficiary Name</dt>
                                <dd class="text-gray-900 mt-0.5 font-semibold text-base">{{ selectedBankTxDetails.payment_details.beneficiary?.bank_details?.account_name || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Bank Name</dt>
                                <dd class="text-gray-900 mt-0.5">{{ selectedBankTxDetails.payment_details.beneficiary?.bank_details?.bank_name || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">IBAN / Account Number</dt>
                                <dd class="text-gray-900 mt-0.5 text-xs select-all">{{ selectedBankTxDetails.payment_details.beneficiary?.bank_details?.iban || '—' }}</dd>
                            </div>
                            <div v-if="selectedBankTxDetails.payment_details.beneficiary?.additional_info?.personal_email">
                                <dt class="text-gray-500 font-medium">Beneficiary Email</dt>
                                <dd class="text-gray-900 mt-0.5 text-xs">{{ selectedBankTxDetails.payment_details.beneficiary.additional_info.personal_email }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 font-medium">Payment Status</dt>
                                <dd class="text-gray-900 mt-0.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                        {{ selectedBankTxDetails.payment_details.status }}
                                    </span>
                                </dd>
                            </div>
                            <div class="col-span-2" v-if="selectedBankTxDetails.payment_details.reference">
                                <dt class="text-gray-500 font-medium">Payment Reference</dt>
                                <dd class="text-gray-900 mt-0.5 italic">"{{ selectedBankTxDetails.payment_details.reference }}"</dd>
                            </div>
                            <div v-if="selectedBankTxDetails.payment_details.reason">
                                <dt class="text-gray-500 font-medium">Payment Reason</dt>
                                <dd class="text-gray-900 mt-0.5 capitalize">{{ selectedBankTxDetails.payment_details.reason.replace(/_/g, ' ') }}</dd>
                            </div>
                            <div v-if="selectedBankTxDetails.payment_details.payment_amount">
                                <dt class="text-gray-500 font-medium">Amount Paid (Beneficiary)</dt>
                                <dd class="text-gray-900 mt-0.5 font-semibold text-indigo-900">{{ formatCurrency(selectedBankTxDetails.payment_details.payment_amount, selectedBankTxDetails.payment_details.payment_currency) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="border-t pt-4">
                        <h4 class="text-md font-semibold text-gray-900 mb-4">Linked Bills / Invoices</h4>
                        <div v-if="selectedBankTxDetails.local_transactions && selectedBankTxDetails.local_transactions.length" class="space-y-3">
                            <div v-for="tx in selectedBankTxDetails.local_transactions" :key="tx.id" class="p-3 bg-gray-50 border rounded-md text-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-medium text-gray-800">{{ tx.project?.name }}</span>
                                    <span class="font-semibold text-indigo-700">{{ formatCurrency(tx.amount, tx.currency) }}</span>
                                </div>
                                <div class="text-xs text-gray-500 space-y-1">
                                    <div v-if="tx.bill">Linked Bill: <span class="font-medium">#{{ tx.bill.id }}</span> ({{ tx.bill.description }})</div>
                                    <div v-if="tx.invoice">Linked Invoice: <span class="font-medium">#{{ tx.invoice.id }}</span> ({{ tx.invoice.description }})</div>
                                    <div>Linked On: {{ formatDate(tx.created_at) }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-gray-400 bg-gray-50 border border-dashed rounded-md">
                            No local records currently linked.
                        </div>
                    </div>
                </div>
            </template>
        </RightSidebar>
    </AuthenticatedLayout>
</template>
