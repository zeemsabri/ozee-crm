<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
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
const loading = ref(true);
const filterStatus = ref('');
const showCreateModal = ref(false);

const form = useForm({
    project_id: '',
    project_expendable_id: '',
    amount: '',
    attachment: null,
    notes: '',
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

watch(() => form.project_id, async (newId) => {
    if (!newId) {
        expendables.value = [];
        return;
    }
    try {
        const { data } = await axios.get(`/api/projects/${newId}/expendables`);
        expendables.value = data.filter(e => e.status === 'accepted'); // Only accepted contracts
        form.project_expendable_id = '';
    } catch (err) {
        console.error('Failed to fetch expendables', err);
    }
});

const openCreateModal = () => {
    form.reset();
    showCreateModal.value = true;
};

const submitBill = () => {
    if (!form.project_expendable_id) return error('Please select a contract.');
    
    form.post(`/api/bills/expendables/${form.project_expendable_id}/bills`, {
        onSuccess: () => {
            showCreateModal.value = false;
            success('Bill created successfully.');
            fetchBills();
        },
        onError: (err) => {
            error(err.message || 'Failed to create bill.');
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
                                {{ exp.name }} (Rem: {{ formatCurrency(exp.remaining_balance, exp.currency) }})
                            </option>
                        </select>
                        <InputError :message="form.errors.project_expendable_id" />
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

                    <div>
                        <InputLabel for="bill_attachment" value="Attachment (PDF/Image)" />
                        <input 
                            id="bill_attachment" 
                            type="file" 
                            @input="form.attachment = $event.target.files[0]"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        <InputError :message="form.errors.attachment" />
                    </div>

                    <div>
                        <InputLabel for="bill_notes" value="Notes" />
                        <textarea 
                            id="bill_notes" 
                            v-model="form.notes"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows="2"
                        ></textarea>
                        <InputError :message="form.errors.notes" />
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
