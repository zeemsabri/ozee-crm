<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { formatCurrency, XERO_TAX_CONFIG, extractGstFromTotal } from '@/Utils/currency';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { usePermissions } from '@/Directives/permissions';
import { success, error } from '@/Utils/notification';
import axios from 'axios';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import BasicPropertyInput from '@/Components/BasicPropertyInput.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    bill: {
        type: Object,
        required: true,
    },
    transaction_types: {
        type: Array,
        default: () => [],
    },
});

const bill = computed(() => props.bill);
const { canDo } = usePermissions(() => bill.value?.project_id);
const canEditBillPermission = canDo('edit_project_bills');
const canApproveBillPermission = canDo('approve_project_bills');
const canEdit = computed(() => bill.value?.status === 'pending_approval' && canEditBillPermission.value);
const xeroAccounts = ref([]);
const xeroTaxTypeOptions = [
    { value: 'BASEXCLUDED', label: 'BAS Excluded' },
    { value: 'EXEMPTEXPENSES', label: 'GST Free Expenses' },
    { value: 'EXEMPTOUTPUT', label: 'GST Free Income' },
    { value: 'INPUT', label: 'GST on Expenses' },
    { value: 'INPUTTAXED', label: 'GST on Imports' },
    { value: 'OUTPUT', label: 'GST on Income' },
];
const xeroTaxTypeLabels = Object.fromEntries(xeroTaxTypeOptions.map((option) => [option.value, option.label]));
const xeroAccountOptions = computed(() => {
    const liveCodes = xeroAccounts.value.map((account) => account.code);
    const mappedCodes = props.transaction_types
        .map((transactionType) => transactionType.xero_account_code)
        .filter(Boolean)
        .map((code) => String(code).toUpperCase());

    const uniqueCodes = [...new Set([...liveCodes, ...mappedCodes])];

    if (bill.value?.xero_account_code && !uniqueCodes.includes(bill.value.xero_account_code)) {
        uniqueCodes.unshift(bill.value.xero_account_code);
    }

    if (!bill.value?.xero_account_code && bill.value?.transactionType?.xero_account_code && !uniqueCodes.includes(bill.value.transactionType.xero_account_code)) {
        uniqueCodes.unshift(bill.value.transactionType.xero_account_code);
    }
    return uniqueCodes.map((code) => {
        const account = xeroAccounts.value.find((item) => item.code === code);
        return {
            value: code,
            label: account ? `${account.code} - ${account.name}` : code,
        };
    });
});

const form = useForm({
    amount: bill.value?.amount || '',
    reference_number: bill.value?.reference_number || '',
    due_date: bill.value?.due_date || '',
    currency: bill.value?.currency || bill.value?.project?.currency || 'AUD',
    xero_account_code: bill.value?.xero_account_code || bill.value?.transactionType?.xero_account_code || '',
    xero_tax_type: bill.value?.xero_tax_type === 'NONE' ? 'EXEMPTEXPENSES' : (bill.value?.xero_tax_type || 'INPUT'),
    // Supplier-uploaded bills arrive without one — the guest has no way to know it — so
    // this is where it gets set.
    transaction_type_id: bill.value?.transaction_type_id || null,
    payment_details: {
        payment_method: bill.value?.payment_detail?.payment_method || 'bank_transfer',
        account_name: bill.value?.payment_detail?.details?.account_name || '',
        account_number: bill.value?.payment_detail?.details?.account_number || '',
        bank_name: bill.value?.payment_detail?.details?.bank_name || '',
        bsb: bill.value?.payment_detail?.details?.bsb || '',
        swift_code: bill.value?.payment_detail?.details?.swift_code || '',
        iban: bill.value?.payment_detail?.details?.iban || '',
        notes: bill.value?.payment_detail?.details?.notes || '',
        // Non-bank destinations. Required by the API whenever the matching method is
        // selected, and carried on bills suppliers submit themselves.
        paypal_email: bill.value?.payment_detail?.details?.paypal_email || '',
        payoneer_email: bill.value?.payment_detail?.details?.payoneer_email || '',
        wise_email: bill.value?.payment_detail?.details?.wise_email || '',
        wallet_address: bill.value?.payment_detail?.details?.wallet_address || '',
        coin_type: bill.value?.payment_detail?.details?.coin_type || '',
    },
});

// The values the API's required_if rules key off, plus the legacy ones already in the
// data so an existing bill's method never renders as blank.
const paymentMethodOptions = computed(() => {
    const options = [
        { value: 'bank_local', label: 'Bank transfer (local)' },
        { value: 'bank_wire', label: 'Bank transfer (international)' },
        { value: 'paypal', label: 'PayPal' },
        { value: 'payoneer', label: 'Payoneer' },
        { value: 'wise', label: 'Wise' },
        { value: 'crypto', label: 'Crypto' },
        { value: 'other', label: 'Other' },
    ];
    const current = form.payment_details.payment_method;
    if (current && !options.some((o) => o.value === current)) {
        options.push({ value: current, label: `${current} (legacy)` });
    }
    return options;
});

const isBankMethod = computed(() =>
    ['bank_local', 'bank_wire', 'bank_transfer', 'other'].includes(form.payment_details.payment_method),
);

const statusLabel = computed(() => (bill.value?.status || '').replace(/_/g, ' ').toUpperCase());

const taxLabel = computed(() => {
    if (!bill.value?.xero_tax_type) {
        return 'N/A';
    }

    if (bill.value.xero_tax_type === 'NONE') {
        return 'NONE (legacy)';
    }

    return xeroTaxTypeLabels[bill.value.xero_tax_type] || bill.value.xero_tax_type;
});

const authUser = computed(() => usePage().props.auth?.user);

const currentPendingStep = computed(() => {
    const steps = bill.value?.approval_instance?.steps;
    if (!steps?.length) return null;
    return steps.find((s) => s.status === 'pending') || null;
});

const canApproveBill = computed(() => {
    if (bill.value?.status !== 'pending_approval') return false;
    if (!canApproveBillPermission.value) return false;
    const step = currentPendingStep.value;
    if (!step) {
        // No active flow step — show button only when there's no instance (super admin path)
        return !bill.value?.approval_instance;
    }
    const user = authUser.value;
    if (!user) return false;
    if (step.approver_type === 'user') return Number(step.approver_user_id) === Number(user.id);
    if (step.approver_type === 'role') return Number(step.approver_role_id) === Number(user.role_id);
    return false;
});

const approveProcessing = ref(false);
const approveForm = ref({
    xero_account_code: '',
    xero_tax_type: 'INPUT',
});

const approveBill = async () => {
    if (!confirm('Approve this bill?')) return;
    if (!canApproveBillPermission.value) {
        error('You do not have permission to approve bills.');
        return;
    }
    approveProcessing.value = true;
    try {
        await axios.post(route('api.bills.approve', { bill: bill.value.id }), {
            xero_account_code: approveForm.value.xero_account_code || bill.value.xero_account_code || bill.value.transactionType?.xero_account_code || '',
            xero_tax_type: approveForm.value.xero_tax_type || bill.value.xero_tax_type || 'INPUT',
        });
        success('Bill approved.');
        window.location.reload();
    } catch (err) {
        error(err.response?.data?.message || 'Approval failed.');
    } finally {
        approveProcessing.value = false;
    }
};

const saveBill = async () => {
    try {
        const payload = {
            amount: form.amount,
            reference_number: form.reference_number,
            due_date: form.due_date,
            currency: form.currency,
            xero_account_code: form.xero_account_code,
            xero_tax_type: form.xero_tax_type,
            transaction_type_id: form.transaction_type_id,
            payment_details: {
                payment_method: form.payment_details.payment_method,
                account_name: form.payment_details.account_name,
                account_number: form.payment_details.account_number,
                bank_name: form.payment_details.bank_name,
                bsb: form.payment_details.bsb,
                swift_code: form.payment_details.swift_code,
                iban: form.payment_details.iban,
                notes: form.payment_details.notes,
                paypal_email: form.payment_details.paypal_email,
                payoneer_email: form.payment_details.payoneer_email,
                wise_email: form.payment_details.wise_email,
                wallet_address: form.payment_details.wallet_address,
                coin_type: form.payment_details.coin_type,
            },
        };

        await axios.put(route('api.bills.update', { project: bill.value.project_id, bill: bill.value.id }), payload);
        success('Bill updated successfully.');
        window.location.reload();
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            form.setError(err.response.data.errors);
            error(err.response?.data?.message || 'Please review the form and try again.');
            return;
        }

        error(err.response?.data?.message || 'Failed to update bill.');
    }
};

const fetchXeroAccounts = async () => {
    try {
        const { data } = await axios.get(route('api.xero.accounts'));
        xeroAccounts.value = Array.isArray(data) ? data : [];
    } catch {
        xeroAccounts.value = [];
    }
};

const showLinkModal = ref(false);
const users = ref([]);
const clients = ref([]);
const createLoading = ref(false);
const formErrors = ref({});
const linkFormMode = ref('new'); // 'new' or 'existing'
const existingTransactions = ref([]);
const selectedTransactionId = ref('');

const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

const transactionForm = ref({
    description: `Payment for Bill #${bill.value.id}`,
    amount: bill.value.amount || '',
    currency: bill.value.currency || 'AUD',
    type: 'expense',
    transaction_type: bill.value.transaction_type ? { id: bill.value.transaction_type_id, name: bill.value.transaction_type.name } : null,
    user_id: bill.value.contractor_id || null,
    hours_spent: '',
    bill_id: bill.value.id,
    bank_transaction_id: '',
});

const userOptions = computed(() => {
    return users.value.map(user => ({
        value: user.id,
        label: user.name || 'Unknown User'
    }));
});

const fetchProjectData = async () => {
    if (!bill.value?.project_id) return;
    try {
        const { data } = await axios.get(`/api/projects/${bill.value.project_id}/sections/clients-users`);
        users.value = data.users || [];
        clients.value = data.clients || [];
    } catch (err) {
        console.error(err);
    }
};

const fetchUnlinkedTransactions = async () => {
    try {
        const { data } = await axios.get('/api/admin/transactions', {
            params: {
                project_id: bill.value.project_id,
                status: 'unpaid',
                type: 'expense'
            }
        });
        existingTransactions.value = data.data || [];
    } catch (err) {
        console.error(err);
    }
};

const openLinkModal = () => {
    showLinkModal.value = true;
    fetchProjectData();
    fetchUnlinkedTransactions();
};

const saveNewTransaction = async () => {
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
        await axios.post(`/api/projects/${bill.value.project_id}/transactions`, payload);
        success('Transaction created and linked to bill successfully');
        showLinkModal.value = false;
        window.location.reload();
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

const linkExistingTransaction = async () => {
    if (!selectedTransactionId.value) return;
    createLoading.value = true;
    try {
        await axios.post(`/api/transactions/${selectedTransactionId.value}/link-bill`, {
            bill_id: bill.value.id
        });
        success('Transaction linked to bill successfully');
        showLinkModal.value = false;
        window.location.reload();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to link transaction');
    } finally {
        createLoading.value = false;
    }
};

const unlinkTransaction = async (txId) => {
    if (!confirm('Are you sure you want to unlink this transaction from the bill?')) return;
    try {
        await axios.post(`/api/transactions/${txId}/unlink-bill`);
        success('Transaction unlinked successfully');
        window.location.reload();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to unlink transaction');
    }
};

onMounted(() => {
    fetchXeroAccounts();
});
</script>

<template>
    <Head :title="`Bill #${bill.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bill #{{ bill.id }}</h2>
                    <p class="text-sm text-gray-500">{{ bill.project?.name }} · {{ bill.expendable?.name }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('admin.financials.bills')">
                        <PrimaryButton>Back to Bills</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow sm:rounded-lg border border-gray-200 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Bill Summary</h3>
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Status</dt>
                                    <dd class="font-medium text-gray-900">{{ statusLabel }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Contractor</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.contractor?.name || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Amount</dt>
                                    <dd class="font-medium text-gray-900">{{ formatCurrency(bill.amount, bill.currency || bill.project?.currency || 'AUD') }}</dd>
                                </div>
                                <!-- GST Breakdown -->
                                <div class="rounded-md border border-gray-200 overflow-hidden text-sm">
                                    <div class="px-3 py-1.5 bg-gray-100 border-b border-gray-200">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Tax Breakdown
                                            <span class="ml-1 font-normal normal-case text-gray-400">
                                                ({{ XERO_TAX_CONFIG[bill.xero_tax_type]?.label || bill.xero_tax_type || 'None' }})
                                            </span>
                                        </span>
                                    </div>
                                    <template v-if="XERO_TAX_CONFIG[bill.xero_tax_type]?.rate > 0">
                                        <div class="px-3 py-2 border-b border-gray-200 flex justify-between text-gray-600">
                                            <span>Subtotal (excl. GST)</span>
                                            <span class="font-medium text-gray-900">
                                                {{ formatCurrency(
                                                    parseFloat(bill.amount) / (1 + XERO_TAX_CONFIG[bill.xero_tax_type].rate),
                                                    bill.currency || bill.project?.currency || 'AUD'
                                                ) }}
                                            </span>
                                        </div>
                                        <div class="px-3 py-2 border-b border-gray-200 flex justify-between text-gray-600">
                                            <span>GST ({{ XERO_TAX_CONFIG[bill.xero_tax_type].rate * 100 }}%)</span>
                                            <span class="font-medium text-gray-900">
                                                {{ formatCurrency(
                                                    extractGstFromTotal(bill.amount, bill.xero_tax_type),
                                                    bill.currency || bill.project?.currency || 'AUD'
                                                ) }}
                                            </span>
                                        </div>
                                        <div class="px-3 py-2 flex justify-between font-semibold text-gray-900">
                                            <span>Total (incl. GST)</span>
                                            <span class="text-indigo-700">{{ formatCurrency(bill.amount, bill.currency || bill.project?.currency || 'AUD') }}</span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="px-3 py-2 flex justify-between text-gray-600">
                                            <span>Amount (No GST)</span>
                                            <span class="font-semibold text-gray-900">{{ formatCurrency(bill.amount, bill.currency || bill.project?.currency || 'AUD') }}</span>
                                        </div>
                                        <div class="px-3 py-2 bg-gray-50 text-xs text-gray-400 italic border-t border-gray-200">
                                            {{ XERO_TAX_CONFIG[bill.xero_tax_type]?.description || 'No GST applies to this bill.' }}
                                        </div>
                                    </template>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Reference Number</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.reference_number || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Due Date</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.due_date || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Transaction Type</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.transactionType?.name || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Xero Account</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.xero_account_code || bill.transactionType?.xero_account_code || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Tax Type</dt>
                                    <dd class="font-medium text-gray-900">{{ taxLabel }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Xero Invoice ID</dt>
                                    <dd class="font-medium text-gray-900 break-all flex items-center gap-2">
                                        {{ bill.xero_invoice_id || 'Not synced yet' }}
                                        <a v-if="bill.xero_invoice_id" :href="`https://go.xero.com/AccountsPayable/View.aspx?invoiceID=${bill.xero_invoice_id}`" target="_blank" class="text-indigo-600 hover:text-indigo-900 text-xs">
                                            (View in Xero)
                                        </a>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Payment Details Snapshot</h3>
                            <div v-if="bill.payment_detail" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm space-y-2">
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Method</span><span class="font-medium">{{ bill.payment_detail.payment_method }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Account Name</span><span class="font-medium">{{ bill.payment_detail.details?.account_name }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Account Number</span><span class="font-medium">{{ bill.payment_detail.details?.account_number }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Bank Name</span><span class="font-medium">{{ bill.payment_detail.details?.bank_name || 'N/A' }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">BSB</span><span class="font-medium">{{ bill.payment_detail.details?.bsb || 'N/A' }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">SWIFT</span><span class="font-medium">{{ bill.payment_detail.details?.swift_code || 'N/A' }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">IBAN</span><span class="font-medium">{{ bill.payment_detail.details?.iban || 'N/A' }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Notes</span><span class="font-medium text-right max-w-xs">{{ bill.payment_detail.details?.notes || 'N/A' }}</span></div>
                            </div>
                            <div v-else class="text-sm text-gray-500">No payment snapshot stored.</div>
                        </div>
                    </div>
                </div>

                <div v-if="canEdit" class="bg-white shadow sm:rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Before Approval</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="bill_amount" value="Amount" />
                            <TextInput id="bill_amount" v-model="form.amount" type="number" step="0.01" class="mt-1 block w-full" />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div>
                            <InputLabel for="bill_reference_number" value="Reference Number" />
                            <TextInput id="bill_reference_number" v-model="form.reference_number" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors.reference_number" />
                        </div>
                        <div>
                            <InputLabel for="bill_due_date" value="Due Date" />
                            <TextInput id="bill_due_date" v-model="form.due_date" type="date" class="mt-1 block w-full" />
                            <InputError :message="form.errors.due_date" />
                        </div>
                        <div>
                            <InputLabel for="bill_currency" value="Currency" />
                            <TextInput id="bill_currency" v-model="form.currency" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors.currency" />
                        </div>
                        <div>
                            <InputLabel for="bill_xero_account_code" value="Xero Account" />
                            <select id="bill_xero_account_code" v-model="form.xero_account_code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select Xero account</option>
                                <option v-for="option in xeroAccountOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.xero_account_code" />
                        </div>
                        <div>
                            <InputLabel for="bill_xero_tax_type" value="Tax Type" />
                            <select id="bill_xero_tax_type" v-model="form.xero_tax_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="option in xeroTaxTypeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.xero_tax_type" />
                        </div>
                        <div>
                            <InputLabel for="bill_transaction_type" value="Transaction Type" />
                            <select id="bill_transaction_type" v-model="form.transaction_type_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option :value="null">Not set</option>
                                <option v-for="type in transaction_types" :key="type.id" :value="type.id">{{ type.name }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Supplier-uploaded bills arrive without one.</p>
                            <InputError :message="form.errors.transaction_type_id" />
                        </div>
                        <div>
                            <InputLabel for="bill_payment_method" value="Payment Method" />
                            <select id="bill_payment_method" v-model="form.payment_details.payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="option in paymentMethodOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors['payment_details.payment_method']" />
                        </div>
                        <div>
                            <InputLabel for="bill_account_name" value="Account Name" />
                            <TextInput id="bill_account_name" v-model="form.payment_details.account_name" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.account_name']" />
                        </div>
                        <div>
                            <InputLabel for="bill_account_number" value="Account Number" />
                            <TextInput id="bill_account_number" v-model="form.payment_details.account_number" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.account_number']" />
                        </div>
                        <div v-if="isBankMethod">
                            <InputLabel for="bill_bank_name" value="Bank Name" />
                            <TextInput id="bill_bank_name" v-model="form.payment_details.bank_name" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.bank_name']" />
                        </div>
                        <div v-if="isBankMethod">
                            <InputLabel for="bill_bsb" value="BSB / Routing" />
                            <TextInput id="bill_bsb" v-model="form.payment_details.bsb" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.bsb']" />
                        </div>
                        <div v-if="isBankMethod">
                            <InputLabel for="bill_swift" value="SWIFT Code" />
                            <TextInput id="bill_swift" v-model="form.payment_details.swift_code" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.swift_code']" />
                        </div>
                        <div v-if="isBankMethod">
                            <InputLabel for="bill_iban" value="IBAN" />
                            <TextInput id="bill_iban" v-model="form.payment_details.iban" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.iban']" />
                        </div>
                        <div v-if="form.payment_details.payment_method === 'paypal'">
                            <InputLabel for="bill_paypal_email" value="PayPal Email" />
                            <TextInput id="bill_paypal_email" v-model="form.payment_details.paypal_email" type="email" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.paypal_email']" />
                        </div>
                        <div v-if="form.payment_details.payment_method === 'payoneer'">
                            <InputLabel for="bill_payoneer_email" value="Payoneer Email" />
                            <TextInput id="bill_payoneer_email" v-model="form.payment_details.payoneer_email" type="email" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.payoneer_email']" />
                        </div>
                        <div v-if="form.payment_details.payment_method === 'wise'">
                            <InputLabel for="bill_wise_email" value="Wise Email" />
                            <TextInput id="bill_wise_email" v-model="form.payment_details.wise_email" type="email" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.wise_email']" />
                        </div>
                        <div v-if="form.payment_details.payment_method === 'crypto'">
                            <InputLabel for="bill_coin_type" value="Coin / Network" />
                            <TextInput id="bill_coin_type" v-model="form.payment_details.coin_type" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.coin_type']" />
                        </div>
                        <div v-if="form.payment_details.payment_method === 'crypto'">
                            <InputLabel for="bill_wallet_address" value="Wallet Address" />
                            <TextInput id="bill_wallet_address" v-model="form.payment_details.wallet_address" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.wallet_address']" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="bill_notes" value="Payment Notes" />
                            <textarea id="bill_notes" v-model="form.payment_details.notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3"></textarea>
                            <InputError :message="form.errors['payment_details.notes']" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton :disabled="form.processing" @click="window.location.reload()">Reset</SecondaryButton>
                        <PrimaryButton :disabled="form.processing" @click="saveBill">
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </PrimaryButton>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Approval Flow</h3>
                    <div v-if="bill.approval_instance?.steps?.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Step</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Approver</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acted By</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Comment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="step in bill.approval_instance.steps" :key="step.id">
                                    <td class="px-4 py-2 text-sm">{{ step.step_order }}</td>
                                    <td class="px-4 py-2 text-sm">{{ step.label || (step.approver_type === 'role' ? step.approver_role?.name : step.approver_user?.name) }}</td>
                                    <td class="px-4 py-2 text-sm">{{ (step.status || '').toUpperCase() }}</td>
                                    <td class="px-4 py-2 text-sm">{{ step.acted_by?.name || '—' }}</td>
                                    <td class="px-4 py-2 text-sm">{{ step.comment || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-sm text-gray-500">No approval flow assigned to this bill.</div>

                    <div v-if="canApproveBill" class="mt-6 border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">
                            Your Approval Action
                            <span v-if="currentPendingStep" class="ml-2 text-xs font-normal text-gray-500">
                                (Step {{ currentPendingStep.step_order }}: {{ currentPendingStep.label || (currentPendingStep.approver_type === 'role' ? currentPendingStep.approver_role?.name : currentPendingStep.approver_user?.name) }})
                            </span>
                        </h4>
                        <div class="flex items-end gap-3 flex-wrap">
                            <div>
                                <InputLabel value="Xero Account Code" />
                                <select v-model="approveForm.xero_account_code" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">Use bill's current code</option>
                                    <option v-for="option in xeroAccountOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Tax Type" />
                                <select v-model="approveForm.xero_tax_type" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option v-for="option in xeroTaxTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <PrimaryButton :disabled="approveProcessing" @click="approveBill" class="bg-green-600 hover:bg-green-700 focus:ring-green-500">
                                {{ approveProcessing ? 'Approving…' : 'Approve Bill' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- Transactions Card -->
                <div class="bg-white shadow sm:rounded-lg border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Linked Transactions (Payments)</h3>
                        <PrimaryButton @click="openLinkModal" v-if="bill.status === 'approved' || bill.status === 'partial_paid' || bill.status === 'paid'">
                            Add Payment / Link Transaction
                        </PrimaryButton>
                    </div>

                    <div v-if="bill.transactions?.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="tx in bill.transactions" :key="tx.id">
                                    <td class="px-4 py-2 text-sm">{{ new Date(tx.created_at).toLocaleDateString('en-AU') }}</td>
                                    <td class="px-4 py-2 text-sm">{{ tx.description }}</td>
                                    <td class="px-4 py-2 text-sm">{{ tx.user?.name || '—' }}</td>
                                    <td class="px-4 py-2 text-sm">{{ tx.transaction_type?.name || '—' }}</td>
                                    <td class="px-4 py-2 text-sm font-semibold">{{ formatCurrency(tx.amount, tx.currency) }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        <button @click="unlinkTransaction(tx.id)" class="text-red-600 hover:text-red-900 font-semibold">
                                            Unlink
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-sm text-gray-500">No transactions linked to this bill.</div>
                </div>
            </div>
        </div>

        <!-- Link Transaction Modal -->
        <Modal :show="showLinkModal" @close="showLinkModal = false" maxWidth="2xl">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 border-b pb-3 mb-4">Add Payment for Bill #{{ bill.id }}</h3>

                <div class="flex gap-4 mb-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" value="new" v-model="linkFormMode" class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-700">Create New Transaction</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" value="existing" v-model="linkFormMode" class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-700">Link Existing Transaction</span>
                    </label>
                </div>

                <div v-if="linkFormMode === 'new'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        <InputLabel for="amount" value="Amount" />
                        <TextInput
                            id="amount"
                            v-model="transactionForm.amount"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="formErrors.amount?.[0]" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="currency" value="Currency" />
                        <SelectDropdown
                            id="currency"
                            v-model="transactionForm.currency"
                            :options="currencyOptions"
                            :disabled="true"
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
                            :disabled="true"
                        />
                        <InputError :message="formErrors.transaction_type_id?.[0]" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="user_id" value="User (Contractor)" />
                        <SelectDropdown
                            id="user_id"
                            v-model="transactionForm.user_id"
                            :options="userOptions"
                            placeholder="Select user"
                            :disabled="true"
                        />
                        <InputError :message="formErrors.user_id?.[0]" class="mt-2" />
                    </div>

                    <div>
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

                <div v-else class="space-y-4">
                    <div v-if="!existingTransactions.length" class="text-sm text-gray-500">
                        No unpaid expense transactions found for this project to link.
                    </div>
                    <div v-else class="max-h-60 overflow-y-auto">
                        <InputLabel for="existing_tx" value="Select Unpaid Expense Transaction" />
                        <select id="existing_tx" v-model="selectedTransactionId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Select a transaction...</option>
                            <option v-for="tx in existingTransactions" :key="tx.id" :value="tx.id">
                                {{ new Date(tx.created_at).toLocaleDateString('en-AU') }} - {{ tx.description }} - {{ formatCurrency(tx.amount, tx.currency) }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <SecondaryButton :disabled="createLoading" @click="showLinkModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="createLoading" @click="linkFormMode === 'new' ? saveNewTransaction() : linkExistingTransaction()">
                        {{ createLoading ? 'Saving...' : (linkFormMode === 'new' ? 'Create & Link' : 'Link Transaction') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
