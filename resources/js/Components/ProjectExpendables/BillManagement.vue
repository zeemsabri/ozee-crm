<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { usePermissions } from '@/Directives/permissions';
import { formatCurrency, displayCurrency } from '@/Utils/currency';
import { success, error, confirmPrompt } from '@/Utils/notification';

const props = defineProps({
    expendable: {
        type: Object,
        required: true,
    },
    transactionTypes: {
        type: Array,
        default: () => [],
    },
    canApprove: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['updated']);

const { canDo } = usePermissions(() => props.expendable.project_id);
const canViewBills = canDo('view_project_bills');
const canCreateBills = canDo('create_project_bills');
const canApproveBills = canDo('approve_project_bills');
const canVoidBills = canDo('void_project_bills');
const canLinkXeroContractors = canDo('link_xero_contractors');

const showCreateModal = ref(false);
const processing = ref(false);
const showXeroSyncModal = ref(false);
const xeroSyncLoading = ref(false);
const xeroSyncError = ref('');
const xeroCandidates = ref([]);
const selectedXeroContactId = ref('');

const form = useForm({
    amount: '',
    project_expendable_id: '',
    contractor_id: '',
    transaction_type_id: '',
    currency: '',
    payment_details: {
        payment_method: 'bank_local',
        account_name: '',
        account_number: '',
        bank_name: '',
        bsb: '',
        swift_code: '',
        iban: '',
        paypal_email: '',
        payoneer_email: '',
        wise_email: '',
        wallet_address: '',
        coin_type: '',
        notes: '',
    },
});

const PAYMENT_METHODS = [
    { value: 'bank_local',      label: '🏦 Bank Transfer (Local)', fields: ['account_name', 'account_number', 'bank_name', 'bsb'] },
    { value: 'bank_wire',       label: '🌐 International Wire Transfer', fields: ['account_name', 'account_number', 'bank_name', 'swift_code', 'iban'] },
    { value: 'paypal',          label: '💙 PayPal', fields: ['paypal_email'] },
    { value: 'payoneer',        label: '🟠 Payoneer', fields: ['payoneer_email'] },
    { value: 'wise',            label: '💚 Wise (TransferWise)', fields: ['wise_email', 'account_number'] },
    { value: 'crypto',          label: '₿ Cryptocurrency', fields: ['wallet_address', 'coin_type'] },
    { value: 'other',           label: '📝 Other', fields: ['notes'] },
];

const paymentMethodOptions = PAYMENT_METHODS.map(m => ({ value: m.value, label: m.label }));

const currentMethod = computed(() =>
    PAYMENT_METHODS.find(m => m.value === form.payment_details.payment_method) || PAYMENT_METHODS[0]
);

function hasField(field) {
    return currentMethod.value.fields.includes(field);
}

function onMethodChange() {
    // Reset all method-specific fields when switching
    Object.assign(form.payment_details, {
        account_name: '',
        account_number: '',
        bank_name: '',
        bsb: '',
        swift_code: '',
        iban: '',
        paypal_email: '',
        payoneer_email: '',
        wise_email: '',
        wallet_address: '',
        coin_type: '',
        notes: '',
    });
}

const typeOptions = computed(() => 
    props.transactionTypes.map(t => ({ value: t.id, label: t.name }))
);

const xeroCandidateOptions = computed(() =>
    xeroCandidates.value.map((candidate) => ({
        value: candidate.contact_id,
        label: `${candidate.name || 'Unnamed Contact'}${candidate.email ? ` (${candidate.email})` : ''}`,
    }))
);

const openCreateModal = () => {
    form.reset();
    form.amount = props.expendable.balance;
    form.project_expendable_id = props.expendable.id;
    form.contractor_id = props.expendable.user_id || '';
    form.currency = props.expendable.currency || 'AUD';
    form.payment_details = {
        payment_method: 'bank_local',
        account_name: '',
        account_number: '',
        bank_name: '',
        bsb: '',
        swift_code: '',
        iban: '',
        paypal_email: '',
        payoneer_email: '',
        wise_email: '',
        wallet_address: '',
        coin_type: '',
        notes: '',
    };
    showCreateModal.value = true;
};

const submitBill = () => {
    if (!props.expendable.project_id) {
        error('Unable to create bill because project context is missing.');
        return;
    }

    if (!form.contractor_id) {
        error('This contract has no assigned contractor.');
        return;
    }

    form.clearErrors();

    const payload = {
        amount: form.amount,
        currency: form.currency || props.expendable.currency,
        project_expendable_id: form.project_expendable_id,
        contractor_id: form.contractor_id,
        transaction_type_id: form.transaction_type_id,
        payment_details: {
            payment_method: form.payment_details.payment_method,
            // Common fields — backend stores as JSON blob, send only filled fields
            account_name:   form.payment_details.account_name   || null,
            account_number: form.payment_details.account_number || null,
            bank_name:      form.payment_details.bank_name      || null,
            bsb:            form.payment_details.bsb            || null,
            swift_code:     form.payment_details.swift_code     || null,
            iban:           form.payment_details.iban           || null,
            paypal_email:   form.payment_details.paypal_email   || null,
            payoneer_email: form.payment_details.payoneer_email || null,
            wise_email:     form.payment_details.wise_email     || null,
            wallet_address: form.payment_details.wallet_address || null,
            coin_type:      form.payment_details.coin_type      || null,
            notes:          form.payment_details.notes          || null,
        },
    };

    form.processing = true;

    axios.post(`/api/projects/${props.expendable.project_id}/bills`, payload)
        .then(() => {
            showCreateModal.value = false;
            success('Bill created successfully.');
            emit('updated');
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

const approveBill = async (billId) => {
    if (!await confirmPrompt('Approve this bill? It will be synced to Xero.')) return;
    
    if (!canApproveBills.value) {
        error('You do not have permission to approve bills.');
        return;
    }

    processing.value = true;
    try {
        await axios.post(route('api.bills.approve', { bill: billId }));
        success('Bill approved and synced to Xero.');
        emit('updated');
    } catch (err) {
        const message = err.response?.data?.message || 'Failed to approve bill.';
        if (message.includes('not linked to Xero')) {
            await openXeroSyncModal();
            return;
        }

        error(message);
    } finally {
        processing.value = false;
    }
};

const fetchXeroCandidates = async () => {
    if (!props.expendable.user_id) {
        return;
    }

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const response = await axios.get(`/api/users/${props.expendable.user_id}/xero-contact-candidates`);
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

const openXeroSyncModal = async () => {
    xeroCandidates.value = [];
    selectedXeroContactId.value = '';
    xeroSyncError.value = '';
    showXeroSyncModal.value = true;

    await fetchXeroCandidates();
};

const syncContractorWithXero = async () => {
    if (!props.expendable.user_id) {
        return;
    }

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const payload = selectedXeroContactId.value
            ? { selected_contact_id: selectedXeroContactId.value }
            : {};

        await axios.post(`/api/users/${props.expendable.user_id}/xero-contact-sync`, payload);
        success('Contractor linked to Xero successfully.');
        showXeroSyncModal.value = false;
        emit('updated');
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
    if (!props.expendable.user_id) {
        return;
    }

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        await axios.post(`/api/users/${props.expendable.user_id}/xero-contact-create`);
        success('Xero contact created and linked successfully.');
        showXeroSyncModal.value = false;
        emit('updated');
    } catch (err) {
        xeroSyncError.value = err.response?.data?.message || 'Failed to create Xero contact.';
        error(xeroSyncError.value);
    } finally {
        xeroSyncLoading.value = false;
    }
};

const voidBill = async (billId) => {
    if (!await confirmPrompt('Void this bill? This will cancel it in Xero.')) return;

    if (!canVoidBills.value) {
        error('You do not have permission to void bills.');
        return;
    }
    
    processing.value = true;
    try {
        await axios.post(route('api.bills.void', { bill: billId }));
        success('Bill voided successfully.');
        emit('updated');
    } catch (err) {
        error(err.response?.data?.message || 'Failed to void bill.');
    } finally {
        processing.value = false;
    }
};

const getStatusClass = (status) => {
    switch (status.toLowerCase()) {
        case 'approved': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'pending_approval': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'voided': return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'paid': return 'bg-blue-50 text-blue-700 border-blue-200';
        default: return 'bg-gray-50 text-gray-600 border-gray-200';
    }
};

const formatStatus = (status) => {
    return status.replace('_', ' ').toUpperCase();
};
</script>

<template>
    <div class="mt-4 border-t pt-4">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-md font-semibold text-gray-700">Bills & Payments</h4>
            <PrimaryButton v-if="canCreateBills" @click="openCreateModal" class="text-xs py-1">
                New Bill
            </PrimaryButton>
        </div>

        <div v-if="!canViewBills" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            You do not have permission to view project bills.
        </div>

        <div v-else>
            <div v-if="!expendable.bills?.length" class="flex flex-col items-center justify-center py-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" /></svg>
                </div>
                <p class="text-sm font-medium text-gray-500">No bills recorded</p>
                <p class="text-xs text-gray-400 mt-0.5">Create a bill to start tracking payments for this contract.</p>
            </div>

            <div v-else class="space-y-2 mt-1">
                <div
                    v-for="bill in expendable.bills"
                    :key="bill.id"
                    class="group relative flex flex-col sm:flex-row items-start sm:items-center gap-3 rounded-xl border bg-white px-4 py-3 hover:shadow-sm transition-all duration-150"
                    :class="{
                        'border-amber-200 bg-amber-50/30': bill.status === 'pending_approval',
                        'border-emerald-200 bg-emerald-50/20': bill.status === 'approved',
                        'border-blue-200 bg-blue-50/20': bill.status === 'paid',
                        'border-rose-200 bg-rose-50/20': bill.status === 'voided',
                        'border-gray-200': !['pending_approval','approved','paid','voided'].includes(bill.status),
                    }"
                >
                    <!-- Left status stripe -->
                    <div class="absolute left-0 top-0 bottom-0 w-0.5 rounded-l-xl"
                        :class="{
                            'bg-amber-400': bill.status === 'pending_approval',
                            'bg-emerald-500': bill.status === 'approved',
                            'bg-blue-500': bill.status === 'paid',
                            'bg-rose-400': bill.status === 'voided',
                            'bg-gray-300': !['pending_approval','approved','paid','voided'].includes(bill.status),
                        }"
                    ></div>

                    <!-- Left status stripe -->
                    <div class="flex-1 min-w-0 pl-2">
                        <div class="flex items-center flex-wrap gap-2">
                            <span class="text-xs font-semibold text-gray-700">
                                {{ new Date(bill.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) }}
                            </span>
                            <span v-if="bill.transaction_type?.name" class="text-[10px] bg-gray-100 text-gray-500 border border-gray-200 px-1.5 py-0.5 rounded-full font-medium">
                                {{ bill.transaction_type.name }}
                            </span>
                            <span
                                :class="['text-[10px] font-bold tracking-wider uppercase px-2 py-0.5 rounded-full border', getStatusClass(bill.status)]"
                            >
                                {{ formatStatus(bill.status) }}
                            </span>
                            <span v-if="bill.xero_invoice_id" title="Synced to Xero" class="inline-flex items-center gap-1 text-[10px] font-medium text-blue-600 bg-blue-50 border border-blue-200 px-1.5 py-0.5 rounded-full">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
                                Xero
                            </span>
                        </div>
                        <div v-if="bill.contractor?.name" class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-gray-500">
                            <span class="font-medium text-gray-700">{{ bill.contractor.name }}</span>
                            <button
                                v-if="canLinkXeroContractors && !bill.contractor?.xero_contact_id"
                                @click="openXeroSyncModal"
                                :disabled="processing"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100 font-semibold transition-all disabled:opacity-50"
                            >
                                Link Xero
                            </button>
                        </div>
                        <div v-if="bill.payment_details?.notes" class="text-[11px] text-gray-400 mt-0.5 truncate">{{ bill.payment_details.notes }}</div>
                    </div>

                    <!-- Amount -->
                    <div class="flex-shrink-0 text-right">
                        <div class="text-sm font-bold text-gray-900">{{ formatCurrency(Number(bill.amount), bill.currency || expendable.currency) }}</div>
                    </div>

                    <!-- Actions -->
                    <div class="flex-shrink-0 flex items-center gap-1.5">
                        <button
                            v-if="bill.status === 'pending_approval' && canApproveBills"
                            @click="approveBill(bill.id)"
                            :disabled="processing"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100 text-xs font-semibold transition-all disabled:opacity-50"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            Approve
                        </button>
                        <Link
                            v-if="canViewBills"
                            :href="route('admin.financials.bills.show', { id: bill.id })"
                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-gray-200 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 transition-all"
                            title="View bill"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                        </Link>
                        <button
                            v-if="bill.status === 'approved' && canVoidBills"
                            @click="voidBill(bill.id)"
                            :disabled="processing"
                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-gray-200 text-gray-400 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-200 transition-all disabled:opacity-50"
                            title="Void bill"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Create Bill Modal -->
            <Modal :show="showCreateModal" @close="showCreateModal = false">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Create New Bill</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Expendable: {{ expendable.name }}<br>
                        Remaining Balance: {{ formatCurrency(expendable.balance, expendable.currency) }}
                    </p>

                    <div class="space-y-4">
                        <div>
                            <InputLabel for="amount" value="Bill Amount" />
                            <TextInput 
                                id="amount" 
                                v-model="form.amount" 
                                type="number" 
                                step="0.01"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div>
                            <InputLabel for="type" value="Transaction Type" />
                            <SelectDropdown
                                id="type"
                                v-model="form.transaction_type_id"
                                :options="typeOptions"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.transaction_type_id" />
                        </div>

                        <div>
                            <InputLabel for="description" value="Description" />
                            <textarea
                                id="description"
                                v-model="form.payment_details.notes"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                rows="2"
                            ></textarea>
                            <InputError :message="form.errors['payment_details.notes']" />
                        </div>

                        <div class="border rounded-xl bg-gray-50 p-4 space-y-4">
                            <h4 class="text-sm font-semibold text-gray-800">Payment Details (Required)</h4>

                            <!-- Payment Method Selector -->
                            <div>
                                <InputLabel for="payment_method" value="Payment Method" />
                                <select
                                    id="payment_method"
                                    v-model="form.payment_details.payment_method"
                                    @change="onMethodChange"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option v-for="m in paymentMethodOptions" :key="m.value" :value="m.value">
                                        {{ m.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors['payment_details.payment_method']" />
                            </div>

                            <!-- Method hint banner -->
                            <div class="rounded-lg bg-indigo-50 border border-indigo-200 px-3 py-2 text-xs text-indigo-700 flex items-center gap-2">
                                <span class="text-base">{{ currentMethod.label.split(' ')[0] }}</span>
                                <span>Fill in the <strong>{{ currentMethod.label.split(' ').slice(1).join(' ') }}</strong> details below</span>
                            </div>

                            <!-- ─── Bank Transfer (Local) ─── -->
                            <template v-if="hasField('bsb') && !hasField('swift_code')">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="account_name" value="Account Name" />
                                        <TextInput id="account_name" v-model="form.payment_details.account_name" type="text" class="mt-1 block w-full" placeholder="Full name on account" />
                                        <InputError :message="form.errors['payment_details.account_name']" />
                                    </div>
                                    <div>
                                        <InputLabel for="account_number" value="Account Number" />
                                        <TextInput id="account_number" v-model="form.payment_details.account_number" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors['payment_details.account_number']" />
                                    </div>
                                    <div>
                                        <InputLabel for="bank_name" value="Bank Name" />
                                        <TextInput id="bank_name" v-model="form.payment_details.bank_name" type="text" class="mt-1 block w-full" placeholder="e.g. Commonwealth Bank" />
                                        <InputError :message="form.errors['payment_details.bank_name']" />
                                    </div>
                                    <div>
                                        <InputLabel for="bsb" value="BSB / Routing Number" />
                                        <TextInput id="bsb" v-model="form.payment_details.bsb" type="text" class="mt-1 block w-full" placeholder="e.g. 062-000" />
                                        <InputError :message="form.errors['payment_details.bsb']" />
                                    </div>
                                </div>
                                  </template>

                            <!-- ─── International Wire ─── -->
                            <template v-else-if="hasField('swift_code')">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="account_name" value="Beneficiary Name" />
                                        <TextInput id="account_name" v-model="form.payment_details.account_name" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors['payment_details.account_name']" />
                                    </div>
                                    <div>
                                        <InputLabel for="bank_name" value="Bank Name" />
                                        <TextInput id="bank_name" v-model="form.payment_details.bank_name" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors['payment_details.bank_name']" />
                                    </div>
                                    <div>
                                        <InputLabel for="account_number" value="Account Number" />
                                        <TextInput id="account_number" v-model="form.payment_details.account_number" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors['payment_details.account_number']" />
                                    </div>
                                    <div>
                                        <InputLabel for="swift_code" value="SWIFT / BIC Code" />
                                        <TextInput id="swift_code" v-model="form.payment_details.swift_code" type="text" class="mt-1 block w-full" placeholder="e.g. CBAUAU2S" />
                                        <InputError :message="form.errors['payment_details.swift_code']" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <InputLabel for="iban" value="IBAN (if applicable)" />
                                        <TextInput id="iban" v-model="form.payment_details.iban" type="text" class="mt-1 block w-full" placeholder="e.g. GB29 NWBK 6016 1331 9268 19" />
                                        <InputError :message="form.errors['payment_details.iban']" />
                                    </div>
                                </div>
                            </template>

                            <!-- ─── PayPal ─── -->
                            <template v-else-if="hasField('paypal_email')">
                                <div>
                                    <InputLabel for="paypal_email" value="PayPal Email Address" />
                                    <TextInput id="paypal_email" v-model="form.payment_details.paypal_email" type="email" class="mt-1 block w-full" placeholder="contractor@example.com" />
                                    <InputError :message="form.errors['payment_details.paypal_email']" />
                                    <p class="text-xs text-gray-400 mt-1">Funds will be sent to this PayPal account</p>
                                </div>
                            </template>

                            <!-- ─── Payoneer ─── -->
                            <template v-else-if="hasField('payoneer_email')">
                                <div>
                                    <InputLabel for="payoneer_email" value="Payoneer Email / Username" />
                                    <TextInput id="payoneer_email" v-model="form.payment_details.payoneer_email" type="email" class="mt-1 block w-full" placeholder="contractor@example.com" />
                                    <InputError :message="form.errors['payment_details.payoneer_email']" />
                                    <p class="text-xs text-gray-400 mt-1">Must match the registered Payoneer account email</p>
                                </div>
                            </template>

                            <!-- ─── Wise ─── -->
                            <template v-else-if="hasField('wise_email')">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="wise_email" value="Wise Email" />
                                        <TextInput id="wise_email" v-model="form.payment_details.wise_email" type="email" class="mt-1 block w-full" placeholder="contractor@example.com" />
                                        <InputError :message="form.errors['payment_details.wise_email']" />
                                    </div>
                                    <div>
                                        <InputLabel for="account_number" value="Account Number (optional)" />
                                        <TextInput id="account_number" v-model="form.payment_details.account_number" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors['payment_details.account_number']" />
                                    </div>
                                </div>
                            </template>

                            <!-- ─── Crypto ─── -->
                            <template v-else-if="hasField('wallet_address')">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2">
                                        <InputLabel for="wallet_address" value="Wallet Address" />
                                        <TextInput id="wallet_address" v-model="form.payment_details.wallet_address" type="text" class="mt-1 block w-full font-mono text-xs" placeholder="e.g. 0x71C7656EC7ab88b098defB751B7401B5f6d8976F" />
                                        <InputError :message="form.errors['payment_details.wallet_address']" />
                                    </div>
                                    <div>
                                        <InputLabel for="coin_type" value="Coin / Network" />
                                        <select id="coin_type" v-model="form.payment_details.coin_type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Select coin</option>
                                            <option value="BTC">Bitcoin (BTC)</option>
                                            <option value="ETH">Ethereum (ETH)</option>
                                            <option value="USDT_TRC20">USDT (TRC-20)</option>
                                            <option value="USDT_ERC20">USDT (ERC-20)</option>
                                            <option value="USDC">USD Coin (USDC)</option>
                                            <option value="BNB">BNB (BSC)</option>
                                            <option value="other">Other</option>
                                        </select>
                                        <InputError :message="form.errors['payment_details.coin_type']" />
                                    </div>
                                </div>
                                <p class="text-xs text-amber-600 mt-1">⚠️ Double-check the wallet address and network — crypto transfers cannot be reversed.</p>
                            </template>

                            <!-- ─── Other ─── -->
                            <template v-else>
                                <div>
                                    <InputLabel for="notes" value="Payment Instructions / Notes" />
                                    <textarea id="notes" v-model="form.payment_details.notes" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Describe how payment should be made..."></textarea>
                                    <InputError :message="form.errors['payment_details.notes']" />
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
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
                    Contractor: <span class="font-medium">{{ expendable.user?.name || 'Unknown' }}</span>
                </p>

                <div v-if="xeroSyncError" class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    {{ xeroSyncError }}
                </div>

                <div>
                    <InputLabel for="xero_contact_candidate" value="Existing Xero Contact" />
                    <select
                        id="xero_contact_candidate"
                        v-model="selectedXeroContactId"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Auto-select if single match</option>
                        <option v-for="option in xeroCandidateOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
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
    </div>
    </div>
</template>
