<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { formatCurrency } from '@/Utils/currency';
import { success, error, confirmPrompt } from '@/Utils/notification';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    canApprove: {
        type: Boolean,
        default: false,
    }
});

const invoices = ref([]);
const loading = ref(false);
const showCreateModal = ref(false);

const form = useForm({
    amount: '',
    description: '',
    due_date: '',
});

const loadInvoices = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/projects/${props.project.id}/invoices`);
        invoices.value = data;
    } catch (err) {
        error('Failed to load invoices.');
    } finally {
        loading.value = false;
    }
};

const openCreateModal = () => {
    form.reset();
    showCreateModal.value = true;
};

const submitInvoice = () => {
    form.post(`/api/projects/${props.project.id}/invoices`, {
        onSuccess: () => {
            showCreateModal.value = false;
            success('Invoice created successfully.');
            loadInvoices();
        },
        onError: (err) => {
            error(err.message || 'Failed to create invoice.');
        }
    });
};

const approveInvoice = async (invoiceId) => {
    if (!await confirmPrompt('Approve this invoice? It will be synced to Xero.')) return;
    
    try {
        await axios.post(`/api/invoices/${invoiceId}/approve`);
        success('Invoice approved and synced to Xero.');
        loadInvoices();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to approve invoice.');
    }
};

const voidInvoice = async (invoiceId) => {
    if (!await confirmPrompt('Void this invoice?')) return;
    
    try {
        await axios.post(`/api/invoices/${invoiceId}/void`);
        success('Invoice voided successfully.');
        loadInvoices();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to void invoice.');
    }
};

onMounted(loadInvoices);

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
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Sales Invoices</h3>
            <PrimaryButton @click="openCreateModal">
                Create Invoice
            </PrimaryButton>
        </div>

        <div v-if="loading" class="text-center py-12">
            <p class="text-gray-500">Loading invoices...</p>
        </div>

        <div v-else-if="!invoices.length" class="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
            <p class="text-gray-500">No invoices found for this project.</p>
        </div>

        <div v-else class="overflow-hidden bg-white shadow sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="invoice in invoices" :key="invoice.id">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ invoice.invoice_number || 'PENDING' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                            {{ formatCurrency(invoice.amount, invoice.currency) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ invoice.due_date ? new Date(invoice.due_date).toLocaleDateString() : 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span :class="['px-2 py-1 rounded-full text-xs font-bold', getStatusClass(invoice.status)]">
                                {{ formatStatus(invoice.status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end gap-3">
                                <button 
                                    v-if="invoice.status === 'pending_approval' && canApprove"
                                    @click="approveInvoice(invoice.id)"
                                    class="text-green-600 hover:text-green-900"
                                >
                                    Approve
                                </button>
                                <button 
                                    v-if="invoice.status === 'approved' && canApprove"
                                    @click="voidInvoice(invoice.id)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    Void
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Invoice Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Create Sales Invoice</h3>
                
                <div class="space-y-4">
                    <div>
                        <InputLabel for="inv_amount" value="Amount" />
                        <TextInput 
                            id="inv_amount" 
                            v-model="form.amount" 
                            type="number" 
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.amount" />
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
    </div>
</template>
