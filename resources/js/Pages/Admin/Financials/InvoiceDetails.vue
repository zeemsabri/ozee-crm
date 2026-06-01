<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { formatCurrency } from '@/Utils/currency';
import { success, error, confirmPrompt } from '@/Utils/notification';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import MentionInput from '@/Components/ProjectTasks/MentionInput.vue';

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
});

const invoiceData = ref(props.invoice);
const notes = ref([]);
const newNoteContent = ref('');
const noteProcessing = ref(false);
const actionProcessing = ref(false);
const fileUploading = ref(false);
const isActionsMenuOpen = ref(false);
const actionsMenuRef = ref(null);
const isEditMode = ref(false);
const editProcessing = ref(false);
const editableLineItems = ref([]);
const projectServicesForEdit = ref([]);
const loadingEditServices = ref(false);

const taxTypeOptions = [
    { value: 'OUTPUT', label: 'OUTPUT - GST on Income (10%)' },
    { value: 'NONE', label: 'NONE - No Tax' },
    { value: 'EXEMPTOUTPUT', label: 'EXEMPTOUTPUT - Exempt Income' },
    { value: 'INPUT', label: 'INPUT - GST on Expenses' },
];

const displayedLineItems = computed(() => {
    return isEditMode.value ? editableLineItems.value : (invoiceData.value.invoice_items || []);
});

const calculatedTotal = computed(() => {
    return displayedLineItems.value.reduce((sum, item) => {
        return sum + (Number(item.quantity || 1) * Number(item.unit_price || 0));
    }, 0);
});

const calculatedTax = computed(() => {
    return displayedLineItems.value.reduce((sum, item) => {
        if (item.tax_type === 'OUTPUT') {
            const itemTotal = Number(item.quantity || 1) * Number(item.unit_price || 0);
            return sum + (itemTotal / 11);
        }
        return sum;
    }, 0);
});

const calculatedSubtotal = computed(() => {
    return calculatedTotal.value - calculatedTax.value;
});

const canEditInvoice = computed(() => {
    return Boolean(invoiceData.value?.can_edit_invoice && invoiceData.value?.can_financially_edit_invoice);
});

const buildMilestoneKey = (service, milestone, index) => `${service?.project_service_id ?? service?.id ?? 'service'}-${index}-${milestone.label}-${milestone.percentage}-${milestone.due_date ?? ''}`;

const getServiceLabel = (service) => service?.service_id || service?.service_name || `Service #${service?.project_service_id ?? service?.id ?? ''}`;

const getServiceById = (projectServiceId) => projectServicesForEdit.value.find(service => String(service.project_service_id) === String(projectServiceId));

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

const applyMilestoneSelection = (lineItem, service, milestone) => {
    lineItem.milestone_key = milestone.key;
    lineItem.label = milestone.label;
    lineItem.milestone_percentage = Number(milestone.percentage || 0);
    lineItem.unit_price = Number(((Number(service.amount || 0) * Number(milestone.percentage || 0)) / 100).toFixed(2));
    if (!lineItem.description) {
        lineItem.description = getServiceLabel(service);
    }
    if (!lineItem.tax_type) {
        lineItem.tax_type = 'OUTPUT';
    }
};

const syncEditableLineByService = (lineItem) => {
    const service = getServiceById(lineItem.project_service_id);
    if (!service) {
        lineItem.milestone_key = '';
        lineItem.label = '';
        lineItem.unit_price = 0;
        lineItem.milestone_percentage = 0;
        return;
    }

    const milestones = getMilestoneOptions(service);
    const milestone = milestones[0];
    if (!milestone) return;
    applyMilestoneSelection(lineItem, service, milestone);
};

const syncEditableLineByMilestone = (lineItem) => {
    const service = getServiceById(lineItem.project_service_id);
    if (!service || !lineItem.milestone_key) return;

    const milestone = getMilestoneOptions(service).find(entry => entry.key === lineItem.milestone_key);
    if (!milestone) return;
    applyMilestoneSelection(lineItem, service, milestone);
};

const addEditableLineItem = () => {
    const firstService = projectServicesForEdit.value[0];
    const item = {
        id: null,
        project_service_id: firstService?.project_service_id ?? '',
        milestone_key: '',
        label: '',
        description: '',
        quantity: 1,
        unit_price: 0,
        tax_type: 'OUTPUT',
        milestone_percentage: 0,
    };

    if (firstService) {
        syncEditableLineByService(item);
    }

    editableLineItems.value.push(item);
};

const removeEditableLineItem = (index) => {
    if (editableLineItems.value.length === 1) {
        return;
    }
    editableLineItems.value.splice(index, 1);
};

const fetchProjectServicesForEdit = async () => {
    if (!invoiceData.value?.project_id) {
        projectServicesForEdit.value = [];
        return;
    }

    loadingEditServices.value = true;
    try {
        const { data } = await axios.get(`/api/projects/${invoiceData.value.project_id}/sections/services-payment`);
        projectServicesForEdit.value = data.service_details || [];
    } catch (err) {
        error(err.response?.data?.message || 'Failed to load project services for invoice editing.');
        projectServicesForEdit.value = [];
    } finally {
        loadingEditServices.value = false;
    }
};

const getStatusLabel = (status) => {
    switch ((status || '').toLowerCase()) {
        case 'pending_approval': return 'Awaiting Approval';
        case 'authorised': return 'Awaiting Payment';
        case 'void':
        case 'voided': return 'Voided';
        default: return (status || '').replace('_', ' ');
    }
};


const fetchNotes = async () => {
    try {
        const { data } = await axios.get(`/api/projects/${invoiceData.value.project_id}/invoices/${invoiceData.value.id}/notes`);
        notes.value = Array.isArray(data) ? data : (data?.notes || []);
    } catch (err) {
        console.error('Failed to fetch invoice notes/history', err);
    }
};

const refreshInvoice = async () => {
    try {
        const { data } = await axios.get(`/api/projects/${invoiceData.value.project_id}/invoices/${invoiceData.value.id}`, {
            params: {
                sync_xero: true,
            },
        });
        if (data) {
            invoiceData.value = data;
        }
    } catch (err) {
        console.error('Failed to refresh invoice data', err);
    }
};

const syncInvoiceAndNotes = async () => {
    await Promise.all([
        refreshInvoice(),
        fetchNotes(),
    ]);
};

const startEditInvoice = async () => {
    if (!canEditInvoice.value) return;
    await fetchProjectServicesForEdit();

    editableLineItems.value = (invoiceData.value.invoice_items || []).map((item) => ({
        id: item.id,
        project_service_id: item.project_service_id,
        milestone_key: item.milestone_key || '',
        label: item.label,
        project_service: item.project_service,
        description: item.description || '',
        quantity: Number(item.quantity || 1),
        unit_price: Number(item.unit_price || 0),
        tax_type: item.tax_type || 'OUTPUT',
        milestone_percentage: Number(item.milestone_percentage || 0),
    }));
    isEditMode.value = true;
};

const cancelEditInvoice = () => {
    isEditMode.value = false;
    editableLineItems.value = [];
};

const saveInvoiceEdits = async () => {
    if (!canEditInvoice.value) {
        error('You do not have permission to edit this invoice.');
        return;
    }

    const hasInvalidRow = editableLineItems.value.some((item) => {
        return !item.project_service_id || !item.milestone_key || !item.label || Number(item.quantity) <= 0 || Number(item.unit_price) < 0;
    });
    if (hasInvalidRow) {
        error('Each line must use a valid service milestone. Quantity must be greater than 0 and amount cannot be negative.');
        return;
    }

    editProcessing.value = true;
    try {
        const payload = {
            line_items: editableLineItems.value.map((item) => ({
                id: item.id,
                project_service_id: item.project_service_id,
                milestone_key: item.milestone_key,
                label: item.label,
                quantity: Number(item.quantity),
                unit_price: Number(item.unit_price),
                tax_type: item.tax_type,
                description: item.description,
                milestone_percentage: Number(item.milestone_percentage || 0),
            })),
        };

        const { data } = await axios.put(`/api/projects/${invoiceData.value.project_id}/invoices/${invoiceData.value.id}`, payload);
        invoiceData.value = data;
        isEditMode.value = false;
        editableLineItems.value = [];
        success('Invoice updated successfully.');
        await fetchNotes();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to update invoice.');
    } finally {
        editProcessing.value = false;
    }
};

const toggleActionsMenu = () => {
    isActionsMenuOpen.value = !isActionsMenuOpen.value;
};

const closeActionsMenu = () => {
    isActionsMenuOpen.value = false;
};

const syncFromActionsMenu = async () => {
    closeActionsMenu();
    await syncInvoiceAndNotes();
};

const handleDocumentClick = (event) => {
    if (!actionsMenuRef.value) return;
    if (!actionsMenuRef.value.contains(event.target)) {
        closeActionsMenu();
    }
};

const addNote = async () => {
    if (!newNoteContent.value?.trim()) {
        error('Please enter a note.');
        return;
    }

    noteProcessing.value = true;
    try {
        await axios.post(`/api/projects/${invoiceData.value.project_id}/invoices/${invoiceData.value.id}/notes`, {
            note: newNoteContent.value,
        });
        success('Note added and synced with Xero.');
        newNoteContent.value = '';
        await fetchNotes();
        await refreshInvoice();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to add note.');
    } finally {
        noteProcessing.value = false;
    }
};

const handleFileUpload = async (e) => {
    const files = Array.from(e.target.files);
    if (!files.length) return;

    fileUploading.value = true;
    try {
        const formData = new FormData();
        formData.append('model_type', 'Invoice');
        formData.append('model_id', invoiceData.value.id);
        files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        await axios.post('/api/files', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        success('Attachment uploaded successfully.');
        e.target.value = '';
        await refreshInvoice();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to upload attachment.');
    } finally {
        fileUploading.value = false;
    }
};

const deleteAttachment = async (fileId) => {
    if (!await confirmPrompt('Delete this attachment?')) return;

    try {
        await axios.delete(`/api/files/${fileId}`);
        success('Attachment deleted.');
        await refreshInvoice();
    } catch (err) {
        error(err.response?.data?.message || 'Failed to delete attachment.');
    }
};

const getAttachmentName = (file) => {
    if (!file || typeof file !== 'object') return 'Attachment';
    return file.original_name || file.file_name || file.filename || 'Attachment';
};

const getAttachmentUrl = (file) => {
    if (!file || typeof file !== 'object') return '';
    return file.file_url || file.path_url || file.url || file.path || '';
};

const approveInvoice = async () => {
    if (!await confirmPrompt('Approve this invoice for Xero sync?')) return;
    actionProcessing.value = true;
    try {
        const response = await axios.post(`/api/projects/${invoiceData.value.project_id}/invoices/${invoiceData.value.id}/approve`, {
            review_comment: 'Approved from details view',
        });

        if (response?.data?.xero_email_sent === false) {
            error('Invoice approved and synced with Xero, but email could not be sent.');
        } else {
            success('Invoice approved, synced, and emailed from Xero.');
        }

        await refreshInvoice();
        await fetchNotes();
    } catch (err) {
        error(err.response?.data?.message || 'Approval failed.');
    } finally {
        actionProcessing.value = false;
    }
};

const voidInvoice = async () => {
    if (!await confirmPrompt('Void this invoice?')) return;
    actionProcessing.value = true;
    try {
        await axios.post(`/api/invoices/${invoiceData.value.id}/void`);
        success('Invoice voided.');
        await refreshInvoice();
        await fetchNotes();
    } catch (err) {
        error(err.response?.data?.message || 'Voiding failed.');
    } finally {
        actionProcessing.value = false;
    }
};

const cleanMentionText = (text) => {
    if (!text) return '';
    return text
        .replace(/@\{\d+:([^}]+)\}/g, '@$1')
        .replace(/#\{\d+:([^}:]+):([^}]+)\}/g, '#$1 $2')
        .replace(/#\{\d+:([^}]+)\}/g, '#$1');
};

const parseXeroDate = (note) => {
    if (!note || typeof note !== 'object') return null;

    const dateUTCString = note.DateUTCString || note.date_utc_string;
    if (dateUTCString) {
        const hasTimezone = /z$|[+-]\d{2}:?\d{2}$/i.test(dateUTCString);
        const parsed = new Date(hasTimezone ? dateUTCString : `${dateUTCString}Z`);
        if (!Number.isNaN(parsed.getTime())) return parsed;
    }

    const dateUTC = note.DateUTC || note.date_utc;
    if (typeof dateUTC === 'string') {
        const match = dateUTC.match(/\/Date\((\d+)(?:[+-]\d+)?\)\//);
        if (match?.[1]) {
            const parsed = new Date(Number(match[1]));
            if (!Number.isNaN(parsed.getTime())) return parsed;
        }
    }

    const fallback = note.Date || note.date || note.created_at;
    if (fallback) {
        const parsed = new Date(fallback);
        if (!Number.isNaN(parsed.getTime())) return parsed;
    }

    return null;
};

const formatHistoryDate = (note) => {
    const parsed = parseXeroDate(note);
    if (!parsed) return 'Unknown time';

    return parsed.toLocaleString('en-AU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getHistoryMessage = (note) => {
    return cleanMentionText(note?.Details || note?.details || note?.note || note?.content || note?.Changes || note?.changes || '');
};

const getStatusClass = (status) => {
    switch ((status || '').toLowerCase()) {
        case 'approved':
        case 'authorised':
        case 'paid':
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        case 'pending_approval':
        case 'draft':
            return 'bg-amber-50 text-amber-700 border border-amber-200';
        case 'rejected':
        case 'void':
        case 'voided':
            return 'bg-rose-50 text-rose-700 border border-rose-200';
        default:
            return 'bg-slate-50 text-slate-700 border border-slate-200';
    }
};

const printInvoice = () => {
    window.print();
};

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    syncInvoiceAndNotes();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <Head :title="`Invoice ${invoiceData.invoice_number || `#${invoiceData.id}`}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <!-- Left: Breadcrumbs + Title -->
                <div class="flex flex-col gap-1">
                    <nav class="flex items-center gap-1.5 text-xs text-slate-500 font-medium">
                        <Link :href="route('admin.financials.dashboard')" class="hover:text-[#00b7e5] transition-colors">
                            Sales overview
                        </Link>
                        <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                        <Link :href="route('admin.financials.invoices')" class="hover:text-[#00b7e5] transition-colors">
                            Invoices
                        </Link>
                    </nav>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-xl font-bold text-slate-800 tracking-tight">
                            Invoice {{ invoiceData.invoice_number || `#${invoiceData.id}` }}
                        </h1>
                        <span :class="['px-2.5 py-0.5 text-xs font-semibold rounded uppercase tracking-wide', getStatusClass(invoiceData.status)]">
                            {{ getStatusLabel(invoiceData.status) }}
                        </span>
                    </div>
                </div>

                <!-- Right: Action Buttons -->
                <div class="flex items-center gap-2">
                    <button
                        @click="printInvoice"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-md border border-slate-200 transition"
                        type="button"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print PDF
                    </button>

                    <PrimaryButton
                        v-if="invoiceData.status === 'pending_approval'"
                        :disabled="actionProcessing"
                        @click="approveInvoice"
                        class="!bg-[#00b7e5] hover:!bg-[#029bc2]"
                    >
                        Approve &amp; Sync Xero
                    </PrimaryButton>

                    <DangerButton
                        v-if="invoiceData.status === 'authorised'"
                        :disabled="actionProcessing"
                        @click="voidInvoice"
                    >
                        Void Invoice
                    </DangerButton>

                    <SecondaryButton
                        v-if="canEditInvoice && !isEditMode"
                        @click="startEditInvoice"
                    >
                        Edit Invoice
                    </SecondaryButton>

                    <PrimaryButton
                        v-if="isEditMode"
                        :disabled="editProcessing"
                        @click="saveInvoiceEdits"
                        class="!bg-emerald-600 hover:!bg-emerald-700"
                    >
                        {{ editProcessing ? 'Saving...' : 'Save Changes' }}
                    </PrimaryButton>

                    <SecondaryButton
                        v-if="isEditMode"
                        :disabled="editProcessing"
                        @click="cancelEditInvoice"
                    >
                        Cancel
                    </SecondaryButton>

                    <!-- Kebab menu -->
                    <div ref="actionsMenuRef" class="relative">
                        <button
                            @click.stop="toggleActionsMenu"
                            class="p-1.5 text-slate-500 hover:bg-slate-100 rounded-full border border-slate-200 transition"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                            </svg>
                        </button>
                        <div
                            v-show="isActionsMenuOpen"
                            class="absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-lg shadow-lg py-1 z-50"
                        >
                            <button @click="syncFromActionsMenu" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Refresh Sync</button>
                            <a
                                v-if="invoiceData.xero_invoice_id"
                                :href="`https://go.xero.com/AccountsReceivable/View.aspx?InvoiceID=${invoiceData.xero_invoice_id}`"
                                target="_blank"
                                @click="closeActionsMenu"
                                class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                            >View in Xero ↗</a>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Page Body -->
        <div class="py-6 bg-[#f4f5f7] min-h-screen print:bg-white">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                    <!-- ═══════════════════════════════════════════
                         LEFT PANEL: White Invoice Sheet (8 cols)
                         ═══════════════════════════════════════════ -->
                    <div class="lg:col-span-8">
                        <div class="bg-white shadow-xl border border-slate-200 rounded-lg overflow-hidden print:shadow-none print:border-none">
                            <!-- Xero brand bar -->
                            <div class="h-1 bg-[#00b7e5]"></div>

                            <div class="p-8 md:p-12">
                                <!-- Header: From + TAX INVOICE title -->
                                <div class="flex flex-col sm:flex-row justify-between items-start gap-6 pb-8 border-b border-slate-100">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-[#00b7e5] mb-1">From</p>
                                        <h2 class="text-xl font-black text-slate-900">{{ invoiceData.project?.name || 'Agency Invoicing' }}</h2>
                                        <p class="text-sm text-slate-400 mt-0.5">billing@agency.com.au</p>
                                    </div>
                                    <div class="text-left sm:text-right">
                                        <h1 class="text-3xl font-extralight tracking-widest text-slate-300 uppercase">Tax Invoice</h1>
                                        <div class="mt-3 space-y-1 text-sm">
                                            <div class="flex sm:justify-end gap-4">
                                                <span class="text-slate-400 w-28 text-right">Invoice No:</span>
                                                <span class="font-semibold text-slate-800">{{ invoiceData.invoice_number || 'Draft' }}</span>
                                            </div>
                                            <div class="flex sm:justify-end gap-4">
                                                <span class="text-slate-400 w-28 text-right">Date:</span>
                                                <span class="font-semibold text-slate-800">{{ new Date(invoiceData.created_at).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
                                            </div>
                                            <div class="flex sm:justify-end gap-4">
                                                <span class="text-slate-400 w-28 text-right">Due Date:</span>
                                                <span class="font-semibold text-slate-800">{{ invoiceData.due_date ? new Date(invoiceData.due_date).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Upon Receipt' }}</span>
                                            </div>
                                            <div class="flex sm:justify-end gap-4">
                                                <span class="text-slate-400 w-28 text-right">Reference:</span>
                                                <span class="font-semibold text-slate-800">{{ invoiceData.project?.name || '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- To + Summary -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 py-8 border-b border-slate-100">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">To</p>
                                        <h3 class="text-base font-bold text-slate-900">{{ invoiceData.client?.name || '—' }}</h3>
                                        <p class="text-sm text-slate-500 mt-0.5" v-if="invoiceData.client?.email">{{ invoiceData.client.email }}</p>
                                        <p class="text-sm text-slate-400 mt-1 whitespace-pre-line" v-if="invoiceData.client?.address">{{ invoiceData.client.address }}</p>
                                    </div>
                                    <div class="bg-slate-50 rounded-xl p-5 border border-slate-100">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Summary</p>
                                        <div class="space-y-2 text-xs">
                                            <div class="flex justify-between border-b border-slate-100 pb-1.5">
                                                <span class="text-slate-500">Status</span>
                                                <span :class="['font-bold uppercase text-[10px]', invoiceData.status === 'authorised' ? 'text-emerald-600' : invoiceData.status === 'pending_approval' ? 'text-amber-600' : 'text-slate-600']">
                                                    {{ getStatusLabel(invoiceData.status) }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between border-b border-slate-100 pb-1.5">
                                                <span class="text-slate-500">Currency</span>
                                                <span class="font-semibold text-slate-700">{{ invoiceData.currency || 'AUD' }}</span>
                                            </div>
                                        </div>
                                        <div class="mt-4 pt-3 border-t border-slate-200 flex justify-between items-baseline">
                                            <span class="text-xs text-slate-400">Amount Due</span>
                                            <span class="text-2xl font-black text-slate-900">
                                                {{ formatCurrency(invoiceData.total_amount || invoiceData.amount, invoiceData.currency || 'AUD') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Line Items Table -->
                                <div class="mt-6 overflow-x-auto">
                                    <div v-if="isEditMode" class="mb-3 flex items-center justify-between gap-3 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2">
                                        <p class="text-xs text-indigo-700">Editing is restricted to project service milestones (same rules as create invoice).</p>
                                        <button
                                            type="button"
                                            @click="addEditableLineItem"
                                            class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                                        >
                                            Add Service
                                        </button>
                                    </div>

                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="border-b-2 border-slate-200">
                                                <th class="pb-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Description</th>
                                                <th class="pb-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider w-14">Qty</th>
                                                <th class="pb-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider w-32">Unit Price</th>
                                                <th class="pb-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider w-32">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr
                                                v-for="(item, index) in (isEditMode ? editableLineItems : (invoiceData.invoice_items || []))"
                                                :key="item.id"
                                                class="hover:bg-slate-50/30 transition-colors"
                                            >
                                                <td class="py-4 pr-4">
                                                    <div v-if="!isEditMode" class="font-semibold text-slate-900">{{ item.label }}</div>
                                                    <div v-else class="space-y-2">
                                                        <select
                                                            v-model="item.project_service_id"
                                                            @change="syncEditableLineByService(item)"
                                                            class="w-full rounded border-slate-300 text-xs"
                                                            :disabled="loadingEditServices"
                                                        >
                                                            <option value="">Select Service</option>
                                                            <option
                                                                v-for="service in projectServicesForEdit"
                                                                :key="service.project_service_id"
                                                                :value="service.project_service_id"
                                                            >
                                                                {{ getServiceLabel(service) }}
                                                            </option>
                                                        </select>

                                                        <select
                                                            v-model="item.milestone_key"
                                                            @change="syncEditableLineByMilestone(item)"
                                                            class="w-full rounded border-slate-300 text-xs"
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
                                                    <div v-if="!isEditMode" class="text-xs text-slate-400 mt-0.5 leading-relaxed">{{ item.description || '' }}</div>
                                                    <textarea
                                                        v-else
                                                        v-model="item.description"
                                                        rows="2"
                                                        class="mt-1 w-full rounded border-slate-300 text-xs"
                                                    />
                                                    <div class="flex flex-wrap gap-1.5 mt-1.5">
                                                        <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-slate-100 text-slate-600 rounded">
                                                            {{ item.project_service?.service_id || getServiceLabel(getServiceById(item.project_service_id)) || 'Service' }}
                                                        </span>
                                                        <span v-if="!isEditMode" class="px-1.5 py-0.5 text-[10px] font-medium bg-slate-50 text-slate-500 border border-slate-200 rounded">
                                                            {{ item.tax_type || 'OUTPUT' }}
                                                        </span>
                                                        <select
                                                            v-else
                                                            v-model="item.tax_type"
                                                            class="px-1.5 py-0.5 text-[10px] font-medium border border-slate-200 rounded w-44"
                                                        >
                                                            <option v-for="taxType in taxTypeOptions" :key="taxType.value" :value="taxType.value">
                                                                {{ taxType.label }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                </td>
                                                <td class="py-4 text-right text-slate-600 align-top">
                                                    <span v-if="!isEditMode">{{ Number(item.quantity || 1) }}</span>
                                                    <input
                                                        v-else
                                                        v-model.number="item.quantity"
                                                        type="number"
                                                        min="0.01"
                                                        step="0.01"
                                                        class="w-20 rounded border-slate-300 text-right"
                                                    />
                                                </td>
                                                <td class="py-4 text-right text-slate-600 align-top">
                                                    <span v-if="!isEditMode">{{ formatCurrency(item.unit_price, invoiceData.currency || 'AUD') }}</span>
                                                    <span v-else>{{ formatCurrency(item.unit_price, invoiceData.currency || 'AUD') }}</span>
                                                </td>
                                                <td class="py-4 text-right font-semibold text-slate-900 align-top">
                                                    {{ formatCurrency(Number(item.quantity || 1) * Number(item.unit_price || 0), invoiceData.currency || 'AUD') }}
                                                    <div v-if="isEditMode" class="mt-2">
                                                        <button
                                                            type="button"
                                                            class="text-[11px] font-semibold text-rose-600 hover:text-rose-700"
                                                            @click="removeEditableLineItem(index)"
                                                        >
                                                            Remove
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Totals -->
                                <div class="mt-6 flex justify-end">
                                    <div class="w-72 space-y-2 text-sm">
                                        <div class="flex justify-between text-slate-500">
                                            <span>Subtotal</span>
                                            <span>{{ formatCurrency(calculatedSubtotal, invoiceData.currency || 'AUD') }}</span>
                                        </div>
                                        <div class="flex justify-between text-slate-500 pb-3 border-b border-slate-100">
                                            <span>GST 10%</span>
                                            <span>{{ formatCurrency(calculatedTax, invoiceData.currency || 'AUD') }}</span>
                                        </div>
                                        <div class="flex justify-between font-bold text-slate-800 text-base">
                                            <span>Total</span>
                                            <span>{{ formatCurrency(invoiceData.total_amount || invoiceData.amount, invoiceData.currency || 'AUD') }}</span>
                                        </div>
                                        <div class="flex justify-between items-center bg-[#f0fafe] border border-[#b3e8f9] rounded-lg p-3 mt-2">
                                            <span class="font-bold text-[#00b7e5] text-sm">Amount Due</span>
                                            <span class="font-black text-lg text-[#00708a]">{{ formatCurrency(invoiceData.total_amount || invoiceData.amount, invoiceData.currency || 'AUD') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════
                         RIGHT PANEL: Sidebar (4 cols)
                         ═══════════════════════════════════════════ -->
                    <div class="lg:col-span-4 space-y-5 print:hidden">

                        <!-- Xero Sync Card -->
                        <div class="bg-white border border-slate-200 shadow-sm rounded-lg overflow-hidden">
                            <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-[#00b7e5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Xero Sync
                                </h3>
                                <span v-if="invoiceData.xero_invoice_id" class="text-[10px] font-bold uppercase px-2 py-0.5 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded">Synced</span>
                                <span v-else class="text-[10px] font-bold uppercase px-2 py-0.5 bg-amber-50 text-amber-600 border border-amber-200 rounded">Pending</span>
                            </div>
                            <div class="p-5 space-y-3 text-xs">
                                <div class="flex justify-between items-start gap-2">
                                    <span class="text-slate-400 shrink-0">Xero ID:</span>
                                    <span class="font-medium text-slate-700 text-right truncate">{{ invoiceData.xero_invoice_id || '—' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Branding Theme:</span>
                                    <span class="font-medium text-slate-700">{{ invoiceData.xero_branding_theme_id ? 'Custom' : 'Default' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Currency:</span>
                                    <span class="font-medium text-slate-700">{{ invoiceData.currency || 'AUD' }}</span>
                                </div>
                                <div v-if="invoiceData.xero_invoice_id" class="pt-3 border-t border-slate-100">
                                    <a
                                        :href="`https://go.xero.com/AccountsReceivable/View.aspx?InvoiceID=${invoiceData.xero_invoice_id}`"
                                        target="_blank"
                                        class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 transition"
                                    >
                                        Open in Xero
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Attachments Card -->
                        <div class="bg-white border border-slate-200 shadow-sm rounded-lg overflow-hidden">
                            <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    Attachments
                                </h3>
                                <span class="text-[10px] font-bold px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full">{{ invoiceData.files?.length || 0 }}</span>
                            </div>
                            <div class="p-5 space-y-4">
                                <!-- File list -->
                                <div v-if="(invoiceData.files || []).length" class="space-y-2 max-h-44 overflow-y-auto">
                                    <div
                                        v-for="file in invoiceData.files"
                                        :key="file.id"
                                        class="flex items-center justify-between p-2.5 rounded-lg border border-slate-150 bg-slate-50/60 hover:border-slate-300 transition group"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="p-1.5 bg-slate-100 rounded text-slate-500 shrink-0">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold text-slate-800 truncate">{{ getAttachmentName(file) }}</p>
                                                <p class="text-[10px] text-slate-400">{{ new Date(file.created_at).toLocaleDateString() }}</p>
                                            </div>
                                        </div>
                                        <div class="flex gap-1 shrink-0">
                                            <a :href="getAttachmentUrl(file)" target="_blank" class="p-1 rounded hover:text-[#00b7e5] hover:bg-slate-100 transition" title="View" v-if="getAttachmentUrl(file)">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>
                                            <a :href="getAttachmentUrl(file)" target="_blank" :download="getAttachmentName(file)" class="p-1 rounded hover:text-[#00b7e5] hover:bg-slate-100 transition" title="Download" v-if="getAttachmentUrl(file)">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>
                                            <button @click="deleteAttachment(file.id)" class="p-1 rounded hover:text-rose-600 hover:bg-rose-50 transition" title="Delete">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-5 text-xs text-slate-400 italic">No files attached yet.</div>

                                <!-- Upload dropzone -->
                                <div class="relative border-2 border-dashed border-slate-200 hover:border-[#00b7e5] transition rounded-lg p-4 text-center group cursor-pointer">
                                    <input
                                        id="file-upload"
                                        name="file-upload"
                                        type="file"
                                        multiple
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        @change="handleFileUpload"
                                        :disabled="fileUploading"
                                    >
                                    <svg class="mx-auto h-7 w-7 text-slate-300 group-hover:text-[#00b7e5] transition mb-1" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <p class="text-xs font-semibold text-slate-600">Click or drag to upload</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">PDF, JPG, PNG up to 20MB</p>
                                    <div v-if="fileUploading" class="absolute inset-0 bg-white/80 rounded-lg flex items-center justify-center">
                                        <p class="text-xs font-semibold text-[#00b7e5] animate-pulse">Syncing with Xero...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- History & Notes Card -->
                        <div class="bg-white border border-slate-200 shadow-sm rounded-lg overflow-hidden">
                            <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    History &amp; Notes
                                </h3>
                                <button @click="syncInvoiceAndNotes" class="text-xs font-semibold text-[#00b7e5] hover:text-[#029bc2] transition">Refresh</button>
                            </div>
                            <div class="p-5 space-y-5">
                                <!-- Timeline stream -->
                                <div class="relative pl-5 border-l-2 border-slate-100 space-y-4 max-h-80 overflow-y-auto">
                                    <div v-if="!notes.length && !(invoiceData.comments || []).length" class="py-6 text-center text-xs text-slate-400 italic -ml-5">
                                        No history recorded yet.
                                    </div>

                                    <!-- Xero notes -->
                                    <div v-for="note in notes" :key="note.id || note.Date" class="relative">
                                        <div class="absolute -left-[22px] top-1 w-2.5 h-2.5 rounded-full bg-sky-400 border-2 border-white ring-2 ring-sky-100"></div>
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-xs font-bold text-slate-700">{{ note.User || 'Xero System' }}</span>
                                            <span class="text-[10px] text-slate-400">{{ formatHistoryDate(note) }}</span>
                                        </div>
                                        <p class="text-xs text-slate-600 bg-slate-50 rounded-lg p-2.5 border border-slate-100 leading-relaxed whitespace-pre-wrap">{{ getHistoryMessage(note) }}</p>
                                    </div>

                                    <!-- CRM comments -->
                                    <div v-for="comment in invoiceData.comments || []" :key="comment.id" class="relative">
                                        <div class="absolute -left-[22px] top-1 w-2.5 h-2.5 rounded-full bg-[#00b7e5] border-2 border-white ring-2 ring-cyan-100"></div>
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-xs font-bold text-slate-700">{{ comment.user?.name || 'CRM User' }}</span>
                                            <span class="text-[10px] text-slate-400">{{ new Date(comment.created_at).toLocaleString('en-AU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</span>
                                        </div>
                                        <p class="text-xs text-slate-600 bg-cyan-50/30 rounded-lg p-2.5 border border-cyan-100/50 leading-relaxed whitespace-pre-wrap">{{ cleanMentionText(comment.content) }}</p>
                                    </div>
                                </div>

                                <!-- Add note -->
                                <div class="pt-3 border-t border-slate-100 space-y-3">
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Add Note (Synced to Xero)</label>
                                    <MentionInput
                                        type="textarea"
                                        :project-id="Number(invoiceData.project_id)"
                                        v-model="newNoteContent"
                                        placeholder="Type a note... @mention team members"
                                    />
                                    <div class="flex justify-end">
                                        <PrimaryButton
                                            :disabled="noteProcessing || !newNoteContent.trim()"
                                            @click="addNote"
                                            class="!bg-[#00b7e5] hover:!bg-[#029bc2] text-xs px-4 py-2"
                                        >
                                            {{ noteProcessing ? 'Saving...' : 'Add Note' }}
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
