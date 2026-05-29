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
});

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
        project_expendable_id: form.project_expendable_id,
        contractor_id: form.contractor_id,
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
        case 'approved': return 'bg-green-100 text-green-800';
        case 'pending_approval': return 'bg-amber-100 text-amber-800';
        case 'voided': return 'bg-red-100 text-red-800';
        case 'paid': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
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
            <PrimaryButton @click="openCreateModal" class="text-xs py-1">
                New Bill
            </PrimaryButton>
        </div>

        <div v-if="!expendable.bills?.length" class="text-sm text-gray-500 italic">
            No bills recorded for this contract.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="bill in expendable.bills" :key="bill.id">
                        <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-700">
                            {{ new Date(bill.created_at).toLocaleDateString() }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-700">
                            {{ bill.transaction_type?.name || 'N/A' }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-xs font-medium text-gray-900">
                            {{ formatCurrency(bill.amount, bill.currency || expendable.currency) }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-xs">
                            <span :class="['px-2 py-1 rounded-full text-[10px] font-bold', getStatusClass(bill.status)]">
                                {{ formatStatus(bill.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-right text-xs">
                            <div class="flex justify-end gap-2">
                                <button 
                                    v-if="bill.status === 'pending_approval' && canApprove"
                                    @click="approveBill(bill.id)"
                                    :disabled="processing"
                                    class="text-green-600 hover:text-green-900 font-bold"
                                >
                                    Approve
                                </button>
                                <Link
                                    :href="route('admin.financials.bills.show', { id: bill.id })"
                                    class="text-indigo-600 hover:text-indigo-900 font-bold"
                                >
                                    View
                                </Link>
                                <button
                                    v-if="bill.status === 'pending_approval' && canApprove && !expendable.user?.xero_contact_id"
                                    @click="openXeroSyncModal"
                                    :disabled="processing"
                                    class="text-indigo-600 hover:text-indigo-900 font-bold"
                                >
                                    Link Xero
                                </button>
                                <button 
                                    v-if="bill.status === 'approved' && canApprove"
                                    @click="voidBill(bill.id)"
                                    :disabled="processing"
                                    class="text-red-600 hover:text-red-900 font-bold"
                                >
                                    Void
                                </button>
                                <span v-if="bill.xero_invoice_id" title="Synced to Xero" class="text-blue-500">
                                    <svg class="w-4 h-4 inline" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"></path><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"></path></svg>
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
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

                    <div class="border rounded-md p-4 bg-gray-50 space-y-4">
                        <h4 class="text-sm font-semibold text-gray-800">Payment Details (Required)</h4>

                        <div>
                            <InputLabel for="payment_method" value="Payment Method" />
                            <select
                                id="payment_method"
                                v-model="form.payment_details.payment_method"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="paypal">PayPal</option>
                                <option value="other">Other</option>
                            </select>
                            <InputError :message="form.errors['payment_details.payment_method']" />
                        </div>

                        <div>
                            <InputLabel for="account_name" value="Account Name" />
                            <TextInput
                                id="account_name"
                                v-model="form.payment_details.account_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.account_name']" />
                        </div>

                        <div>
                            <InputLabel for="account_number" value="Account Number" />
                            <TextInput
                                id="account_number"
                                v-model="form.payment_details.account_number"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.account_number']" />
                        </div>

                        <div>
                            <InputLabel for="bank_name" value="Bank Name" />
                            <TextInput
                                id="bank_name"
                                v-model="form.payment_details.bank_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.bank_name']" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="bsb" value="BSB / Routing" />
                                <TextInput
                                    id="bsb"
                                    v-model="form.payment_details.bsb"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors['payment_details.bsb']" />
                            </div>
                            <div>
                                <InputLabel for="swift_code" value="SWIFT Code" />
                                <TextInput
                                    id="swift_code"
                                    v-model="form.payment_details.swift_code"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors['payment_details.swift_code']" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="iban" value="IBAN" />
                            <TextInput
                                id="iban"
                                v-model="form.payment_details.iban"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.iban']" />
                        </div>
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
</template>
