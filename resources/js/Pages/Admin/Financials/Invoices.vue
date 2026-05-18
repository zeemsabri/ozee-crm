<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { formatCurrency } from '@/Utils/currency';
import { success, error, confirmPrompt } from '@/Utils/notification';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import MentionInput from '@/Components/ProjectTasks/MentionInput.vue';

const invoices = ref([]);
const projects = ref([]);
const projectServices = ref([]);
const loading = ref(true);
const loadingServices = ref(false);
const filterStatus = ref('');
const showCreateModal = ref(false);
const showReviewModal = ref(false);
const selectedInvoice = ref(null);
const reviewComment = ref('');
const reviewProcessing = ref(false);
const createProcessing = ref(false);
const existingProjectInvoices = ref([]);
const existingStatusFilter = ref('');
const existingServiceFilter = ref('');
const existingMilestoneFilter = ref('');

const taxTypeOptions = [
    { value: 'OUTPUT', label: 'OUTPUT - GST on Income (10%)' },
    { value: 'NONE', label: 'NONE - No Tax' },
    { value: 'EXEMPTOUTPUT', label: 'EXEMPTOUTPUT - Exempt Income' },
    { value: 'INPUT', label: 'INPUT - GST on Expenses' },
];

const buildMilestoneKey = (service, milestone, index) => `${service?.project_service_id ?? service?.id ?? 'service'}-${index}-${milestone.label}-${milestone.percentage}-${milestone.due_date ?? ''}`;

const emptyLineItem = () => ({
    project_service_id: '',
    milestone_key: '',
    label: '',
    description: '',
    quantity: 1,
    unit_price: '',
    tax_type: 'OUTPUT',
    milestone_percentage: '',
});

const form = useForm({
    project_id: '',
    client_id: '',
    total_amount: '',
    line_items: [],
});

const lineItems = ref([emptyLineItem()]);

const selectedProject = computed(() => projects.value.find(p => p.id === parseInt(form.project_id, 10)) || null);

const selectedClientId = computed(() => {
    if (!selectedProject.value) {
        return '';
    }

    return selectedProject.value.clients?.[0]?.id || selectedProject.value.client_id || '';
});

const totalAmount = computed(() => lineItems.value.reduce((sum, item) => {
    const quantity = Number(item.quantity || 1);
    const unitPrice = Number(item.unit_price || 0);
    return sum + (quantity * unitPrice);
}, 0).toFixed(2));

const getServiceLabel = (service) => service.service_id || service.service_name || 'Service';

const getMilestoneOptions = (service) => {
    if (!service?.payment_breakdown?.length) {
        return [{
            key: buildMilestoneKey(service, { label: 'Payment 1', percentage: 100, due_date: null }, 0),
            label: 'Payment 1 - 100%',
            percentage: 100,
            due_date: null,
        }];
    }

    return service.payment_breakdown.map((milestone, index) => ({
        key: buildMilestoneKey(service, milestone, index),
        label: `${milestone.label} - ${milestone.percentage}%`,
        percentage: Number(milestone.percentage || 0),
        due_date: milestone.due_date || null,
    }));
};

const getServiceById = (projectServiceId) => projectServices.value.find(service => String(service.project_service_id) === String(projectServiceId));

const existingInvoiceMilestones = computed(() => {
    return existingProjectInvoices.value.flatMap((invoice) => {
        const invoiceNumber = invoice.invoice_number || `ID ${invoice.id}`;
        const invoiceStatus = String(invoice.status || '').toLowerCase();

        return (invoice.invoice_items || []).map((item) => ({
            id: `${invoice.id}-${item.id}`,
            invoice_id: invoice.id,
            invoice_number: invoiceNumber,
            invoice_status: invoiceStatus,
            project_service_id: String(item.project_service_id || ''),
            service_label: item.project_service?.service_id || 'Service',
            milestone_key: String(item.milestone_key || ''),
            milestone_label: item.label || item.milestone_key,
            amount: Number(item.quantity || 1) * Number(item.unit_price || 0),
            created_at: invoice.created_at,
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

    return existingInvoiceMilestones.value.filter((row) => {
        return selectedDraftMilestoneKeys.value.has(`${row.project_service_id}|${row.milestone_key}`);
    });
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

const syncLineItem = (item) => {
    const service = getServiceById(item.project_service_id);
    if (!service) {
        item.milestone_key = '';
        item.label = '';
        item.unit_price = '';
        item.milestone_percentage = '';
        return;
    }

    const milestones = getMilestoneOptions(service);
    const milestone = milestones[0];
    if (!milestone) {
        return;
    }

    item.milestone_key = milestone.key;
    item.label = milestone.label;
    if (!item.description) {
        item.description = getServiceLabel(service);
    }
    item.milestone_percentage = milestone.percentage;
    item.unit_price = ((Number(service.amount || 0) * Number(milestone.percentage || 0)) / 100).toFixed(2);
    if (!item.tax_type) {
        item.tax_type = 'OUTPUT';
    }
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
    if (!item.description) {
        item.description = getServiceLabel(service);
    }
    item.milestone_percentage = milestone.percentage;
    item.unit_price = ((Number(service.amount || 0) * Number(milestone.percentage || 0)) / 100).toFixed(2);
};

const fetchProjectServices = async (projectId) => {
    projectServices.value = [];
    if (!projectId) {
        return;
    }

    loadingServices.value = true;
    try {
        const { data } = await axios.get(`/api/projects/${projectId}/sections/services-payment`);
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

const fetchProjectInvoices = async (projectId) => {
    existingProjectInvoices.value = [];
    if (!projectId) {
        return;
    }

    try {
        const { data } = await axios.get(`/api/projects/${projectId}/invoices`);
        existingProjectInvoices.value = Array.isArray(data) ? data : [];
    } catch (err) {
        error(err.response?.data?.message || 'Failed to load existing invoices for this project.');
    }
};

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

watch(() => form.project_id, (newId) => {
    if (selectedProject.value && selectedProject.value.clients?.length > 0) {
        form.client_id = selectedProject.value.clients[0].id;
    } else {
        form.client_id = '';
    }
    fetchProjectServices(newId);
    fetchProjectInvoices(newId);
    clearExistingFilters();
});

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    projectServices.value = [];
    lineItems.value = [emptyLineItem()];
    existingProjectInvoices.value = [];
    clearExistingFilters();
    showCreateModal.value = true;
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

const submitInvoice = async () => {
    if (!form.project_id) return error('Please select a project.');

    form.clearErrors();
    form.client_id = selectedClientId.value || form.client_id;
    form.total_amount = totalAmount.value;
    form.line_items = lineItems.value
        .filter(item => item.project_service_id && item.milestone_key)
        .map(item => ({
            project_service_id: item.project_service_id,
            milestone_key: item.milestone_key,
            label: item.label,
            description: item.description,
            quantity: Number(item.quantity || 1),
            unit_price: Number(item.unit_price || 0),
            milestone_percentage: Number(item.milestone_percentage || 0),
            tax_type: item.tax_type || 'OUTPUT',
        }));
    
    if (form.line_items.length === 0) {
        return error('Please add at least one valid invoice line item.');
    }

    createProcessing.value = true;
    try {
        await axios.post(`/api/projects/${form.project_id}/invoices`, {
            project_id: Number(form.project_id),
            client_id: Number(form.client_id),
            total_amount: form.total_amount,
            line_items: form.line_items,
        });

            showCreateModal.value = false;
            success('Invoice created successfully.');
            await fetchInvoices();
            await fetchProjectInvoices(form.project_id);
        } catch (err) {
            const validationErrors = err.response?.data?.errors || {};
            if (validationErrors.project_id?.[0]) {
                form.setError('project_id', validationErrors.project_id[0]);
            }
            if (validationErrors.client_id?.[0]) {
                form.setError('client_id', validationErrors.client_id[0]);
            }

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

const openReviewModal = (invoice) => {
    selectedInvoice.value = invoice;
    reviewComment.value = '';
    showReviewModal.value = true;
};

const closeReviewModal = () => {
    showReviewModal.value = false;
    selectedInvoice.value = null;
    reviewComment.value = '';
};

const cleanMentionText = (text) => {
    if (!text) {
        return '';
    }

    return text
        .replace(/@\{\d+:([^}]+)\}/g, '@$1')
        .replace(/#\{\d+:([^}:]+):([^}]+)\}/g, '#$1 $2')
        .replace(/#\{\d+:([^}]+)\}/g, '#$1');
};

const reviewEntries = computed(() => {
    if (!selectedInvoice.value?.comments?.length) {
        return [];
    }

    return [...selectedInvoice.value.comments].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

const approveInvoice = async (invoice, fromModal = false) => {
    if (!await confirmPrompt('Approve this invoice for Xero sync?')) return;
    try {
        reviewProcessing.value = true;
        await axios.post(`/api/projects/${invoice.project_id}/invoices/${invoice.id}/approve`, {
            review_comment: reviewComment.value || null,
        });
        success('Invoice approved.');
        if (fromModal) {
            closeReviewModal();
        }
        fetchInvoices();
    } catch (err) {
        error(err.response?.data?.message || 'Approval failed.');
    } finally {
        reviewProcessing.value = false;
    }
};

const rejectInvoice = async (invoice) => {
    if (!reviewComment.value?.trim()) {
        error('Add a rejection comment before rejecting this invoice.');
        return;
    }
    if (!await confirmPrompt('Reject this invoice?')) return;

    try {
        reviewProcessing.value = true;
        await axios.post(`/api/projects/${invoice.project_id}/invoices/${invoice.id}/reject`, {
            review_comment: reviewComment.value,
        });
        success('Invoice rejected.');
        closeReviewModal();
        fetchInvoices();
    } catch (err) {
        error(err.response?.data?.message || 'Rejection failed.');
    } finally {
        reviewProcessing.value = false;
    }
};

const addReviewComment = async (invoice) => {
    if (!reviewComment.value?.trim()) {
        error('Add a comment first.');
        return;
    }

    try {
        reviewProcessing.value = true;
        await axios.post(`/api/projects/${invoice.project_id}/invoices/${invoice.id}/comment`, {
            comment: reviewComment.value,
        });
        success('Comment added.');
        reviewComment.value = '';
        await fetchInvoices();
        if (selectedInvoice.value) {
            const latest = invoices.value.find(entry => entry.id === selectedInvoice.value.id);
            if (latest) {
                selectedInvoice.value = latest;
            }
        }
    } catch (err) {
        error(err.response?.data?.message || 'Comment failed.');
    } finally {
        reviewProcessing.value = false;
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
        case 'rejected': return 'bg-orange-100 text-orange-800';
        case 'void': return 'bg-red-100 text-red-800';
        case 'voided': return 'bg-red-100 text-red-800';
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
                        <option value="rejected">Rejected</option>
                        <option value="voided">Voided</option>
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
                                        <button v-if="invoice.status === 'pending_approval'" @click="openReviewModal(invoice)" class="text-indigo-600 hover:text-indigo-900">Review</button>
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

                    <div v-if="loadingServices" class="text-sm text-gray-500">
                        Loading services...
                    </div>

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
                                            <option
                                                v-for="milestone in getMilestoneOptions(getServiceById(item.project_service_id))"
                                                :key="milestone.key"
                                                :value="milestone.key"
                                            >
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
                                        <select
                                            :id="`line_tax_${index}`"
                                            v-model="item.tax_type"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option v-for="taxType in taxTypeOptions" :key="taxType.value" :value="taxType.value">
                                                {{ taxType.label }}
                                            </option>
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500">This sends the selected Xero tax code exactly as shown.</p>
                                    </div>

                                    <div>
                                        <InputLabel value="Unit Price" />
                                        <div class="mt-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">
                                            {{ formatCurrency(Number(item.unit_price || 0), selectedProject?.currency || 'AUD') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <InputLabel :for="`line_description_${index}`" value="Description (Xero)" />
                                    <textarea
                                        :id="`line_description_${index}`"
                                        v-model="item.description"
                                        rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="This description is sent to Xero line item"
                                    />
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
                                <span class="text-base font-semibold text-gray-900">{{ formatCurrency(Number(totalAmount), selectedProject?.currency || 'AUD') }}</span>
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
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ row.invoice_number }} • {{ row.invoice_status.toUpperCase() }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ formatCurrency(row.amount, selectedProject?.currency || 'AUD') }}</p>
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

        <Modal :show="showReviewModal" @close="closeReviewModal">
            <div class="p-6" v-if="selectedInvoice">
                <h3 class="text-lg font-semibold">Invoice Review</h3>
                <p class="mt-1 text-sm text-gray-600">
                    {{ selectedInvoice.project?.name }}
                    <span class="mx-2">•</span>
                    {{ selectedInvoice.client?.name }}
                    <span class="mx-2">•</span>
                    {{ formatCurrency(selectedInvoice.total_amount || selectedInvoice.amount, selectedInvoice.currency || selectedInvoice.project?.currency) }}
                </p>

                <div class="mt-5">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Line Items</h4>
                    <div class="max-h-56 overflow-auto rounded-md border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Service</th>
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-left">Tax</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="lineItem in selectedInvoice.invoice_items || []" :key="lineItem.id">
                                    <td class="px-3 py-2">{{ lineItem.project_service?.service_id || 'Service' }}</td>
                                    <td class="px-3 py-2">{{ lineItem.description || lineItem.label }}</td>
                                    <td class="px-3 py-2">{{ lineItem.tax_type || 'OUTPUT' }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(Number(lineItem.quantity || 1) * Number(lineItem.unit_price || 0), selectedInvoice.currency || selectedInvoice.project?.currency || 'AUD') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-5">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Review Comments</h4>
                    <div class="space-y-2 max-h-40 overflow-auto rounded-md border border-gray-200 p-3 bg-gray-50">
                        <p v-if="!reviewEntries.length" class="text-sm text-gray-500">No review comments yet.</p>
                        <div v-for="entry in reviewEntries" :key="entry.id" class="rounded bg-white border border-gray-200 p-2">
                            <div class="text-xs text-gray-500">
                                <span class="font-semibold text-gray-700">{{ entry.user?.name || 'User' }}</span>
                                <span class="mx-1">•</span>
                                <span class="uppercase">{{ entry.action }}</span>
                                <span class="mx-1">•</span>
                                <span>{{ new Date(entry.created_at).toLocaleString() }}</span>
                            </div>
                            <p v-if="entry.content" class="mt-1 text-sm text-gray-700 whitespace-pre-wrap">{{ cleanMentionText(entry.content) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <InputLabel value="Add Comment (supports @mentions)" />
                    <MentionInput
                        type="textarea"
                        :project-id="Number(selectedInvoice.project_id)"
                        v-model="reviewComment"
                        placeholder="Add approval/rejection notes and mention assignees"
                    />
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <SecondaryButton @click="closeReviewModal">Close</SecondaryButton>
                    <SecondaryButton :disabled="reviewProcessing" @click="addReviewComment(selectedInvoice)">Add Comment</SecondaryButton>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                        :disabled="reviewProcessing"
                        @click="rejectInvoice(selectedInvoice)"
                    >
                        Reject
                    </button>
                    <PrimaryButton :disabled="reviewProcessing" @click="approveInvoice(selectedInvoice, true)">
                        Approve & Sync
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
