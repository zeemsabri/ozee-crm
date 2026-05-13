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

const invoices = ref([]);
const projects = ref([]);
const loading = ref(true);
const filterStatus = ref('');
const showCreateModal = ref(false);

const form = useForm({
    project_id: '',
    client_id: '',
    total_amount: '',
    due_date: '',
    description: '',
});

const fetchInvoices = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/invoices', { params: { status: filterStatus.value } });
        invoices.value = data.data; // Paginated data
    } catch (err) {
        error('Failed to load invoices.');
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

const selectedProject = ref(null);
watch(() => form.project_id, (newId) => {
    selectedProject.value = projects.value.find(p => p.id === parseInt(newId)) || null;
    if (selectedProject.value && selectedProject.value.clients.length > 0) {
        form.client_id = selectedProject.value.clients[0].id;
    } else {
        form.client_id = '';
    }
});

const openCreateModal = () => {
    form.reset();
    showCreateModal.value = true;
};

const submitInvoice = () => {
    if (!form.project_id) return error('Please select a project.');
    
    form.post(`/api/projects/${form.project_id}/invoices`, {
        onSuccess: () => {
            showCreateModal.value = false;
            success('Invoice created successfully.');
            fetchInvoices();
        },
        onError: (err) => {
            error(err.message || 'Failed to create invoice.');
        }
    });
};

const approveInvoice = async (invoice) => {
    if (!await confirmPrompt('Approve this invoice for Xero sync?')) return;
    try {
        await axios.post(route('api.invoices.approve', { invoice: invoice.id }));
        success('Invoice approved.');
        fetchInvoices();
    } catch (err) {
        error(err.response?.data?.message || 'Approval failed.');
    }
};

const voidInvoice = async (invoice) => {
    if (!await confirmPrompt('Void this invoice?')) return;
    try {
        await axios.post(route('api.invoices.void', { invoice: invoice.id }));
        success('Invoice voided.');
        fetchInvoices();
    } catch (err) {
        error(err.response?.data?.message || 'Voiding failed.');
    }
};

onMounted(() => {
    fetchInvoices();
    fetchProjects();
});

const getStatusClass = (status) => {
    switch (status.toLowerCase()) {
        case 'approved': return 'bg-green-100 text-green-800';
        case 'authorised': return 'bg-green-100 text-green-800';
        case 'pending_approval': return 'bg-amber-100 text-amber-800';
        case 'void': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="Sales Invoices" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sales Invoices</h2>
                <div class="flex gap-4 items-center">
                    <select v-model="filterStatus" @change="fetchInvoices" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All Statuses</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="authorised">Authorised (Synced)</option>
                        <option value="void">Void</option>
                    </select>
                    <PrimaryButton @click="openCreateModal">
                        Create Invoice
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow sm:rounded-lg border border-gray-200">
                    <div v-if="loading" class="p-12 text-center text-gray-500">Loading invoices...</div>
                    <div v-else-if="!invoices.length" class="p-12 text-center text-gray-500">No invoices found.</div>
                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project / Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="invoice in invoices" :key="invoice.id">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ invoice.project?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ invoice.client?.name }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ invoice.invoice_number || '---' }}
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                    {{ formatCurrency(invoice.total_amount || invoice.amount, invoice.currency || invoice.project?.currency) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['px-2 py-1 text-xs font-bold rounded-full', getStatusClass(invoice.status)]">
                                        {{ invoice.status.toUpperCase().replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button v-if="invoice.status === 'pending_approval'" @click="approveInvoice(invoice)" class="text-green-600 hover:text-green-900">Approve</button>
                                        <button v-if="invoice.status === 'authorised'" @click="voidInvoice(invoice)" class="text-red-600 hover:text-red-900">Void</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Invoice Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Create Sales Invoice</h3>
                
                <div class="space-y-4">
                    <div>
                        <InputLabel for="project_id" value="Project" />
                        <select 
                            id="project_id" 
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

                    <div v-if="selectedProject">
                        <InputLabel for="client_id" value="Client" />
                        <select 
                            id="client_id" 
                            v-model="form.client_id" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="client in selectedProject.clients" :key="client.id" :value="client.id">
                                {{ client.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.client_id" />
                    </div>

                    <div>
                        <InputLabel for="inv_amount" value="Amount" />
                        <TextInput 
                            id="inv_amount" 
                            v-model="form.total_amount" 
                            type="number" 
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.total_amount" />
                    </div>

                    <div>
                        <InputLabel for="inv_due_date" value="Due Date" />
                        <TextInput 
                            id="inv_due_date" 
                            v-model="form.due_date" 
                            type="date"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.due_date" />
                    </div>

                    <div>
                        <InputLabel for="inv_description" value="Description" />
                        <textarea 
                            id="inv_description" 
                            v-model="form.description"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows="3"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitInvoice" :disabled="form.processing">
                        Create Invoice
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
