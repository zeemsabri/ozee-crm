<script setup>
import { computed, ref, onMounted } from 'vue';
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
const projectServices = ref([]);
const loadingServices = ref(false);
const createProcessing = ref(false);
const existingStatusFilter = ref('');
const existingServiceFilter = ref('');
const existingMilestoneFilter = ref('');

const buildMilestoneKey = (service, milestone, index) => `${service?.project_service_id ?? service?.id ?? 'service'}-${index}-${milestone.label}-${milestone.percentage}-${milestone.due_date ?? ''}`;

const emptyLineItem = () => ({
    project_service_id: '',
    milestone_key: '',
    label: '',
    quantity: 1,
    unit_price: '',
    tax_type: 'OUTPUT',
    milestone_percentage: '',
});

const form = useForm({
    client_id: '',
    total_amount: '',
    line_items: [],
});

const lineItems = ref([emptyLineItem()]);

const defaultClientId = computed(() => props.project.client_id || props.project.client?.id || props.project.clients?.[0]?.id || '');

const totalAmount = computed(() => lineItems.value.reduce((sum, item) => {
    return sum + (Number(item.quantity || 1) * Number(item.unit_price || 0));
}, 0).toFixed(2));

const getServiceLabel = (service) => service.service_id || 'Service';

const getServiceById = (projectServiceId) => projectServices.value.find(service => String(service.project_service_id) === String(projectServiceId));

const existingInvoiceMilestones = computed(() => {
    return invoices.value.flatMap((invoice) => {
        const invoiceNumber = invoice.invoice_number || `ID ${invoice.id}`;

        return (invoice.invoice_items || []).map((item) => ({
            id: `${invoice.id}-${item.id}`,
            invoice_number: invoiceNumber,
            invoice_status: String(invoice.status || '').toLowerCase(),
            project_service_id: String(item.project_service_id || ''),
            service_label: item.project_service?.service_id || 'Service',
            milestone_key: String(item.milestone_key || ''),
            milestone_label: item.label || item.milestone_key,
            amount: Number(item.quantity || 1) * Number(item.unit_price || 0),
        }));
    });
});

const filteredExistingInvoiceMilestones = computed(() => {
    const status = String(existingStatusFilter.value || '').toLowerCase();
    const service = String(existingServiceFilter.value || '');
    const milestoneSearch = String(existingMilestoneFilter.value || '').trim().toLowerCase();

    return existingInvoiceMilestones.value.filter((row) => {
        const statusMatch = !status || row.invoice_status === status;
        const serviceMatch = !service || row.project_service_id === service;
        const milestoneMatch = !milestoneSearch
            || row.milestone_label.toLowerCase().includes(milestoneSearch)
            || row.milestone_key.toLowerCase().includes(milestoneSearch);

        return statusMatch && serviceMatch && milestoneMatch;
    });
});

const selectedDraftMilestoneKeys = computed(() => {
    const keys = lineItems.value
        .filter(item => item.project_service_id && item.milestone_key)
        .map(item => `${item.project_service_id}|${item.milestone_key}`);

    return new Set(keys);
});

const draftDuplicateRows = computed(() => {
    if (!selectedDraftMilestoneKeys.value.size) {
        return [];
    }

    return existingInvoiceMilestones.value.filter((row) => selectedDraftMilestoneKeys.value.has(`${row.project_service_id}|${row.milestone_key}`));
});

const normalizeMilestoneLabel = (value) => String(value || '').trim().toLowerCase();

const selectedDraftServiceIds = computed(() => {
    return new Set(
        lineItems.value
            .filter(item => item.project_service_id)
            .map(item => String(item.project_service_id))
    );
});

const selectedDraftMilestoneLabels = computed(() => {
    return new Set(
        lineItems.value
            .filter(item => item.label)
            .map(item => normalizeMilestoneLabel(item.label))
            .filter(Boolean)
    );
});

const getExistingMilestoneMatchType = (row) => {
    const isExactMatch = selectedDraftMilestoneKeys.value.has(`${row.project_service_id}|${row.milestone_key}`);
    if (isExactMatch) {
        return 'exact';
    }

    const isServiceMatch = selectedDraftServiceIds.value.has(String(row.project_service_id));
    if (isServiceMatch) {
        return 'service';
    }

    const isMilestoneMatch = selectedDraftMilestoneLabels.value.has(normalizeMilestoneLabel(row.milestone_label));
    if (isMilestoneMatch) {
        return 'milestone';
    }

    return 'none';
};

const getExistingMilestoneRowClass = (row) => {
    switch (getExistingMilestoneMatchType(row)) {
        case 'exact':
            return 'bg-red-50 ring-1 ring-inset ring-red-200';
        case 'service':
            return 'bg-sky-50 ring-1 ring-inset ring-sky-200';
        case 'milestone':
            return 'bg-amber-50 ring-1 ring-inset ring-amber-200';
        default:
            return '';
    }
};

const getExistingMilestoneBadgeText = (row) => {
    switch (getExistingMilestoneMatchType(row)) {
        case 'exact':
            return 'Exact Match';
        case 'service':
            return 'Same Service';
        case 'milestone':
            return 'Same Milestone';
        default:
            return '';
    }
};

const getExistingMilestoneBadgeClass = (row) => {
    switch (getExistingMilestoneMatchType(row)) {
        case 'exact':
            return 'bg-red-100 text-red-800';
        case 'service':
            return 'bg-sky-100 text-sky-800';
        case 'milestone':
            return 'bg-amber-100 text-amber-800';
        default:
            return 'bg-gray-100 text-gray-700';
    }
};

const clearExistingFilters = () => {
    existingStatusFilter.value = '';
    existingServiceFilter.value = '';
    existingMilestoneFilter.value = '';
};

const getMilestoneOptions = (service) => {
    if (!service?.payment_breakdown?.length) {
        return [{
            key: buildMilestoneKey(service, { label: 'Payment 1', percentage: 100, due_date: null }, 0),
            label: 'Payment 1 - 100%',
            percentage: 100,
        }];
    }

    return service.payment_breakdown.map((milestone, index) => ({
        key: buildMilestoneKey(service, milestone, index),
        label: `${milestone.label} - ${milestone.percentage}%`,
        percentage: Number(milestone.percentage || 0),
    }));
};

const syncLineItem = (item) => {
    const service = getServiceById(item.project_service_id);
    if (!service) {
        item.milestone_key = '';
        item.label = '';
        item.unit_price = '';
        item.milestone_percentage = '';
        return;
    }

    const milestone = getMilestoneOptions(service)[0];
    if (!milestone) {
        return;
    }

    item.milestone_key = milestone.key;
    item.label = milestone.label;
    item.milestone_percentage = milestone.percentage;
    item.unit_price = ((Number(service.amount || 0) * Number(milestone.percentage || 0)) / 100).toFixed(2);
};

const syncMilestoneSelection = (item) => {
    const service = getServiceById(item.project_service_id);
    if (!service || !item.milestone_key) {
        return;
    }

    const milestone = getMilestoneOptions(service).find(entry => entry.key === item.milestone_key);
    if (!milestone) {
        return;
    }

    item.label = milestone.label;
    item.milestone_percentage = milestone.percentage;
    item.unit_price = ((Number(service.amount || 0) * Number(milestone.percentage || 0)) / 100).toFixed(2);
};

const addLineItem = () => {
    lineItems.value.push(emptyLineItem());
};

const removeLineItem = (index) => {
    if (lineItems.value.length === 1) {
        lineItems.value[0] = emptyLineItem();
        return;
    }

    lineItems.value.splice(index, 1);
};

const loadProjectServices = async () => {
    loadingServices.value = true;
    try {
        const { data } = await axios.get(`/api/projects/${props.project.id}/sections/services-payment`);
        projectServices.value = data.service_details || [];
        lineItems.value = [emptyLineItem()];
        if (projectServices.value.length) {
            lineItems.value[0].project_service_id = projectServices.value[0].project_service_id;
            syncLineItem(lineItems.value[0]);
        }
    } catch (err) {
        error(err.response?.data?.message || 'Failed to load project services.');
    } finally {
        loadingServices.value = false;
    }
};

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
    form.clearErrors();
    form.client_id = defaultClientId.value;
    lineItems.value = [emptyLineItem()];
    clearExistingFilters();
    if (projectServices.value.length) {
        lineItems.value[0].project_service_id = projectServices.value[0].project_service_id;
        syncLineItem(lineItems.value[0]);
    }
    showCreateModal.value = true;
};

const submitInvoice = async () => {
    form.client_id = defaultClientId.value;
    form.total_amount = totalAmount.value;
    form.line_items = lineItems.value
        .filter(item => item.project_service_id && item.milestone_key)
        .map(item => ({
            project_service_id: item.project_service_id,
            milestone_key: item.milestone_key,
            label: item.label,
            quantity: Number(item.quantity || 1),
            unit_price: Number(item.unit_price || 0),
            milestone_percentage: Number(item.milestone_percentage || 0),
            tax_type: item.tax_type || 'OUTPUT',
        }));

    createProcessing.value = true;
    try {
        await axios.post(`/api/projects/${props.project.id}/invoices`, {
            project_id: Number(props.project.id),
            client_id: Number(form.client_id),
            total_amount: form.total_amount,
            line_items: form.line_items,
        });

            showCreateModal.value = false;
            success('Invoice created successfully.');
            await loadInvoices();
        } catch (err) {
            const validationErrors = err.response?.data?.errors || {};
            const lineItemError = validationErrors.line_items?.[0]
                || validationErrors['line_items.0.milestone_key']?.[0]
                || validationErrors['line_items.0.project_service_id']?.[0];

            if (lineItemError) {
                error(lineItemError);
                return;
            }

            error(err.response?.data?.message || 'Failed to create invoice.');
        } finally {
            createProcessing.value = false;
        }
};

const approveInvoice = async (invoiceId) => {
    if (!await confirmPrompt('Approve this invoice? It will be synced to Xero.')) return;
    
    try {
        await axios.post(`/api/projects/${props.project.id}/invoices/${invoiceId}/approve`);
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
onMounted(loadProjectServices);

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
                    <div v-if="loadingServices" class="text-sm text-gray-500">Loading services...</div>

                    <div v-else class="grid gap-6 lg:grid-cols-3">
                        <div class="space-y-4 lg:col-span-2">
                            <div class="flex items-center justify-between">
                                <InputLabel value="Invoice Line Items" />
                                <SecondaryButton type="button" @click="addLineItem">Add Line</SecondaryButton>
                            </div>

                            <div v-for="(item, index) in lineItems" :key="index" class="rounded-lg border border-gray-200 p-4 space-y-4 bg-gray-50">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`line_service_${index}`" value="Service" />
                                        <select
                                            :id="`line_service_${index}`"
                                            v-model="item.project_service_id"
                                            @change="syncLineItem(item)"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select Service</option>
                                            <option v-for="service in projectServices" :key="service.project_service_id" :value="service.project_service_id">
                                                {{ getServiceLabel(service) }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <InputLabel :for="`line_milestone_${index}`" value="Milestone" />
                                        <select
                                            :id="`line_milestone_${index}`"
                                            v-model="item.milestone_key"
                                            @change="syncMilestoneSelection(item)"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            :disabled="!item.project_service_id"
                                        >
                                            <option value="">Select Milestone</option>
                                            <option v-for="milestone in getMilestoneOptions(getServiceById(item.project_service_id))" :key="milestone.key" :value="milestone.key">
                                                {{ milestone.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <InputLabel :for="`line_qty_${index}`" value="Quantity" />
                                        <TextInput :id="`line_qty_${index}`" v-model="item.quantity" type="number" min="1" step="1" class="mt-1 block w-full" />
                                    </div>

                                    <div>
                                        <InputLabel :for="`line_tax_${index}`" value="Tax Type" />
                                        <TextInput :id="`line_tax_${index}`" v-model="item.tax_type" class="mt-1 block w-full" />
                                    </div>

                                    <div>
                                        <InputLabel value="Unit Price" />
                                        <div class="mt-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">
                                            {{ formatCurrency(Number(item.unit_price || 0), props.project.currency || 'AUD') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-500">
                                        {{ item.label || 'Select a milestone to calculate the amount.' }}
                                    </div>
                                    <button type="button" class="text-sm text-red-600 hover:text-red-900" @click="removeLineItem(index)">Remove</button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between rounded-lg bg-gray-100 px-4 py-3">
                                <span class="text-sm font-medium text-gray-700">Estimated Total</span>
                                <span class="text-base font-semibold text-gray-900">{{ formatCurrency(Number(totalAmount), props.project.currency || 'AUD') }}</span>
                            </div>
                        </div>

                        <aside class="rounded-lg border border-gray-200 bg-gray-50 p-4 flex max-h-[65vh] flex-col gap-3 overflow-hidden">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-gray-900">Existing Milestones</h4>
                                <button
                                    type="button"
                                    class="text-xs text-indigo-600 hover:text-indigo-800"
                                    @click="clearExistingFilters"
                                >
                                    Clear Filters
                                </button>
                            </div>

                            <div class="space-y-2">
                                <select
                                    v-model="existingStatusFilter"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="pending_approval">Pending Approval</option>
                                    <option value="authorised">Authorised</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="voided">Voided</option>
                                </select>

                                <select
                                    v-model="existingServiceFilter"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">All Services</option>
                                    <option v-for="service in projectServices" :key="service.project_service_id" :value="String(service.project_service_id)">
                                        {{ getServiceLabel(service) }}
                                    </option>
                                </select>

                                <TextInput
                                    v-model="existingMilestoneFilter"
                                    type="text"
                                    class="block w-full"
                                    placeholder="Filter milestone"
                                />
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto rounded border border-gray-200 bg-white">
                                <div v-if="!filteredExistingInvoiceMilestones.length" class="p-3 text-xs text-gray-500">
                                    No invoiced milestones match these filters.
                                </div>
                                <div
                                    v-for="row in filteredExistingInvoiceMilestones"
                                    :key="row.id"
                                    :class="['border-b border-gray-100 p-3 last:border-b-0 transition-colors', getExistingMilestoneRowClass(row)]"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-xs font-semibold text-gray-900">{{ row.milestone_label }}</p>
                                        <span
                                            v-if="getExistingMilestoneMatchType(row) !== 'none'"
                                            :class="['inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide', getExistingMilestoneBadgeClass(row)]"
                                        >
                                            {{ getExistingMilestoneBadgeText(row) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-600 mt-1">{{ row.service_label }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ row.invoice_number }} • {{ row.invoice_status.toUpperCase() }}</p>
                                    <p class="text-xs text-gray-500">{{ formatCurrency(row.amount, props.project.currency || 'AUD') }}</p>
                                </div>
                            </div>
                        </aside>
                    </div>

                    <div v-if="draftDuplicateRows.length" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        One or more selected milestones are already invoiced for this project. Review the Existing Milestones panel before creating this invoice.
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitInvoice" :disabled="createProcessing">
                        Create Invoice
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
