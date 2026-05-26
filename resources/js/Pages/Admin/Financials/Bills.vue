<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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

const bills = ref([]);
const projects = ref([]);
const expendables = ref([]);
const transactionTypes = ref([]);
const loading = ref(true);
const filterStatus = ref('');
const showCreateModal = ref(false);

const form = useForm({
    project_id: '',
    project_expendable_id: '',
    contractor_id: '',
    transaction_type_id: '',
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
});

const selectedExpendable = computed(() => {
    return expendables.value.find((item) => String(item.id) === String(form.project_expendable_id)) || null;
});

const fetchBills = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/bills', { params: { status: filterStatus.value } });
        bills.value = data.data; // Paginated data
    } catch (err) {
        error('Failed to load bills.');
    } finally {
        loading.value = false;
    }
};

const fetchProjects = async () => {
    try {
        const { data } = await axios.get('/api/projects-for-email');
        projects.value = data.projects;
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
});

const openCreateModal = () => {
    form.reset();
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
    if (!form.project_expendable_id) return error('Please select a contract.');
    if (!form.project_id) return error('Please select a project.');
    if (!form.contractor_id) return error('Selected contract does not have a contractor assigned.');
    if (!form.transaction_type_id) return error('Please select a transaction type.');
    
    form.post(`/api/projects/${form.project_id}/bills`, {
        onSuccess: () => {
            showCreateModal.value = false;
            success('Bill created successfully.');
            fetchBills();
        },
        onError: () => {
            error('Failed to create bill. Please review the form and try again.');
        }
    });
};

const approveBill = async (bill) => {
    if (!await confirmPrompt('Approve this bill for Xero sync?')) return;
    try {
        await axios.post(route('api.bills.approve', { bill: bill.id }));
        success('Bill approved.');
        fetchBills();
    } catch (err) {
        error(err.response?.data?.message || 'Approval failed.');
    }
};

const voidBill = async (bill) => {
    if (!await confirmPrompt('Void this bill?')) return;
    try {
        await axios.post(route('api.bills.void', { bill: bill.id }));
        success('Bill voided.');
        fetchBills();
    } catch (err) {
        error(err.response?.data?.message || 'Voiding failed.');
    }
};

onMounted(() => {
    fetchBills();
    fetchProjects();
    fetchTransactionTypes();
});

const getStatusClass = (status) => {
    switch (status.toLowerCase()) {
        case 'approved': return 'bg-green-100 text-green-800';
        case 'pending_approval': return 'bg-amber-100 text-amber-800';
        case 'void': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="Contractor Bills" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Contractor Bills</h2>
                <div class="flex gap-4 items-center">
                    <select v-model="filterStatus" @change="fetchBills" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All Statuses</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="void">Void</option>
                    </select>
                    <PrimaryButton @click="openCreateModal">
                        Create Bill
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow sm:rounded-lg border border-gray-200">
                    <div v-if="loading" class="p-12 text-center text-gray-500">Loading bills...</div>
                    <div v-else-if="!bills.length" class="p-12 text-center text-gray-500">No bills found.</div>
                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project / Contract</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contractor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="bill in bills" :key="bill.id">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ bill.project?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ bill.expendable?.name }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ bill.contractor?.name }}
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                    {{ formatCurrency(bill.amount, bill.currency || bill.project?.currency) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['px-2 py-1 text-xs font-bold rounded-full', getStatusClass(bill.status)]">
                                        {{ bill.status.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button v-if="bill.status === 'pending_approval'" @click="approveBill(bill)" class="text-green-600 hover:text-green-900">Approve</button>
                                        <button v-if="bill.status === 'approved'" @click="voidBill(bill)" class="text-red-600 hover:text-red-900">Void</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Bill Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Create Contractor Bill</h3>
                
                <div class="space-y-4">
                    <div>
                        <InputLabel for="bill_project_id" value="Project" />
                        <select 
                            id="bill_project_id" 
                            v-model="form.project_id" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select Project</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.project_id" />
                    </div>

                    <div v-if="form.project_id">
                        <InputLabel for="project_expendable_id" value="Contract (Expendable)" />
                        <select 
                            id="project_expendable_id" 
                            v-model="form.project_expendable_id" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select Contract</option>
                            <option v-for="exp in expendables" :key="exp.id" :value="exp.id">
                                {{ exp.name }} ({{ exp.expendable_type?.replace('App\\Models\\', '') || 'Project' }}) - Rem: {{ formatCurrency(exp.balance, exp.currency) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.project_expendable_id" />
                    </div>

                    <div>
                        <InputLabel for="bill_transaction_type" value="Transaction Type" />
                        <select
                            id="bill_transaction_type"
                            v-model="form.transaction_type_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select Transaction Type</option>
                            <option v-for="type in transactionTypes" :key="type.id" :value="type.id">
                                {{ type.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.transaction_type_id" />
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

                    <div class="border rounded-md p-4 bg-gray-50 space-y-4">
                        <h4 class="text-sm font-semibold text-gray-800">Payment Details (Required)</h4>

                        <div>
                            <InputLabel for="bill_payment_method" value="Payment Method" />
                            <select
                                id="bill_payment_method"
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
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitBill" :disabled="form.processing">
                        Create Bill
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
