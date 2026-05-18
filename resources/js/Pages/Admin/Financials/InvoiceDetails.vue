<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
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

const fetchNotes = async () => {
    try {
        const { data } = await axios.get(`/api/projects/${invoiceData.value.project_id}/invoices/${invoiceData.value.id}/notes`);
        notes.value = data.notes || [];
    } catch (err) {
        console.error('Failed to fetch invoice notes/history', err);
    }
};

const refreshInvoice = async () => {
    try {
        const { data } = await axios.get(`/api/projects/${invoiceData.value.project_id}/invoices/${invoiceData.value.id}`);
        if (data) {
            invoiceData.value = data;
        }
    } catch (err) {
        console.error('Failed to refresh invoice data', err);
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

const approveInvoice = async () => {
    if (!await confirmPrompt('Approve this invoice for Xero sync?')) return;
    actionProcessing.value = true;
    try {
        await axios.post(`/api/projects/${invoiceData.value.project_id}/invoices/${invoiceData.value.id}/approve`, {
            review_comment: 'Approved from details view',
        });
        success('Invoice approved and synced with Xero.');
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

const getStatusClass = (status) => {
    switch ((status || '').toLowerCase()) {
        case 'approved': return 'bg-green-100 text-green-800 ring-green-600/20';
        case 'authorised': return 'bg-green-100 text-green-800 ring-green-600/20';
        case 'pending_approval': return 'bg-amber-100 text-amber-800 ring-amber-600/20';
        case 'rejected': return 'bg-orange-100 text-orange-800 ring-orange-600/20';
        case 'void': return 'bg-red-100 text-red-800 ring-red-600/20';
        case 'voided': return 'bg-red-100 text-red-800 ring-red-600/20';
        default: return 'bg-gray-100 text-gray-800 ring-gray-600/20';
    }
};

onMounted(() => {
    fetchNotes();
});
</script>

<template>
    <Head :title="`Invoice ${invoiceData.invoice_number || `#${invoiceData.id}`}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('admin.financials.invoices')"
                        class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700"
                    >
                        &larr; Back to Invoices
                    </Link>
                    <div class="h-6 w-px bg-gray-200"></div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center gap-3">
                        <span>Invoice {{ invoiceData.invoice_number || `#${invoiceData.id}` }}</span>
                        <span :class="['px-3 py-1 text-xs font-semibold rounded-full ring-1 ring-inset uppercase tracking-wide', getStatusClass(invoiceData.status)]">
                            {{ (invoiceData.status || '').replace('_', ' ') }}
                        </span>
                    </h2>
                </div>

                <div class="flex items-center gap-3">
                    <PrimaryButton
                        v-if="invoiceData.status === 'pending_approval'"
                        :disabled="actionProcessing"
                        @click="approveInvoice"
                    >
                        Approve & Sync Xero
                    </PrimaryButton>

                    <DangerButton
                        v-if="invoiceData.status === 'authorised'"
                        :disabled="actionProcessing"
                        @click="voidInvoice"
                    >
                        Void Invoice
                    </DangerButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <!-- Top Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Project & Client</p>
                            <h3 class="mt-2 text-lg font-bold text-gray-900">{{ invoiceData.project?.name || '---' }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ invoiceData.client?.name || '---' }}</p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <p class="text-xs text-gray-500">Created: {{ new Date(invoiceData.created_at).toLocaleDateString() }}</p>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Financial Summary</p>
                            <div class="mt-2 flex items-baseline gap-2">
                                <h3 class="text-3xl font-extrabold text-gray-900">
                                    {{ formatCurrency(invoiceData.total_amount || invoiceData.amount, invoiceData.currency || invoiceData.project?.currency) }}
                                </h3>
                                <span class="text-sm font-semibold text-gray-500">{{ invoiceData.currency || invoiceData.project?.currency || 'AUD' }}</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between text-xs text-gray-500">
                            <span>Line Items: {{ invoiceData.invoice_items?.length || 0 }}</span>
                            <span>Tax Included</span>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Xero Integration</p>
                            <div class="mt-2 space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Invoice Number:</span>
                                    <span class="font-medium text-gray-900">{{ invoiceData.invoice_number || 'Pending Sync' }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Branding Theme:</span>
                                    <span class="font-medium text-gray-900">{{ invoiceData.xero_branding_theme_id ? 'Custom Theme' : 'Standard / Default' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-2 text-xs text-emerald-600 font-medium">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Bi-directional Sync Active</span>
                        </div>
                    </div>
                </div>

                <!-- Line Items Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="font-bold text-gray-900 text-base">Invoice Line Items</h3>
                        <span class="text-xs font-medium text-gray-500">Items are locked after creation to preserve financial integrity</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Service</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Milestone / Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tax Type</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit Price</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="item in invoiceData.invoice_items || []" :key="item.id" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ item.project_service?.service_id || 'Service' }}</td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <div class="font-medium text-gray-900">{{ item.label }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">{{ item.description || 'No additional description' }}</div>
                                    </td>
                                    <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded">{{ item.tax_type || 'OUTPUT' }}</span></td>
                                    <td class="px-6 py-4 text-right text-gray-600">{{ item.quantity || 1 }}</td>
                                    <td class="px-6 py-4 text-right text-gray-600">{{ formatCurrency(item.unit_price, invoiceData.currency || invoiceData.project?.currency || 'AUD') }}</td>
                                    <td class="px-6 py-4 text-right font-bold text-gray-900">{{ formatCurrency(Number(item.quantity || 1) * Number(item.unit_price || 0), invoiceData.currency || invoiceData.project?.currency || 'AUD') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bottom Two-Column Grid: Notes/History & Attachments -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Notes & Xero History Stream -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                        <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <div>
                                <h3 class="font-bold text-gray-900 text-base">Notes & Xero History Stream</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Live bi-directional sync with Xero historical notes</p>
                            </div>
                            <button @click="fetchNotes" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                <span>Refresh</span>
                            </button>
                        </div>

                        <div class="p-6 flex-1 space-y-4 max-h-[450px] overflow-y-auto">
                            <div v-if="!notes.length && !(invoiceData.comments || []).length" class="text-center py-12 text-sm text-gray-400">
                                No notes or history recorded yet.
                            </div>

                            <!-- Combined Stream -->
                            <div class="space-y-4">
                                <div v-for="note in notes" :key="note.id || note.Date" class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="flex justify-between items-center text-xs text-slate-500 mb-2">
                                        <span class="font-bold text-slate-700 flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                            <span>{{ note.User || 'Xero System' }}</span>
                                        </span>
                                        <span>{{ new Date(note.DateUTC || note.Date || note.created_at).toLocaleString() }}</span>
                                    </div>
                                    <p class="text-sm text-slate-800 whitespace-pre-wrap leading-relaxed">{{ cleanMentionText(note.Changes || note.Details || note.note || note.content) }}</p>
                                </div>

                                <div v-for="comment in invoiceData.comments || []" :key="comment.id" class="p-4 rounded-xl bg-indigo-50/50 border border-indigo-100">
                                    <div class="flex justify-between items-center text-xs text-indigo-600 mb-2">
                                        <span class="font-bold flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                                            <span>{{ comment.user?.name || 'CRM User' }}</span>
                                        </span>
                                        <span>{{ new Date(comment.created_at).toLocaleString() }}</span>
                                    </div>
                                    <p class="text-sm text-indigo-950 whitespace-pre-wrap leading-relaxed">{{ cleanMentionText(comment.content) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 border-t border-gray-100 bg-gray-50/30 space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Add Note to Invoice (Synced to Xero)</label>
                                <MentionInput
                                    type="textarea"
                                    :project-id="Number(invoiceData.project_id)"
                                    v-model="newNoteContent"
                                    placeholder="Type note here... supports @mentions"
                                />
                            </div>
                            <div class="flex justify-end">
                                <PrimaryButton :disabled="noteProcessing" @click="addNote">
                                    {{ noteProcessing ? 'Saving & Syncing...' : 'Add Note' }}
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>

                    <!-- Attachments Management -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                        <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <div>
                                <h3 class="font-bold text-gray-900 text-base">Invoice Attachments</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Files are automatically synced with Xero invoice attachments</p>
                            </div>
                            <span class="px-2.5 py-0.5 text-xs font-semibold bg-indigo-100 text-indigo-800 rounded-full">
                                {{ invoiceData.files?.length || 0 }} Files
                            </span>
                        </div>

                        <div class="p-6 flex-1 space-y-4 max-h-[450px] overflow-y-auto">
                            <div v-if="!(invoiceData.files || []).length" class="text-center py-12 text-sm text-gray-400">
                                No attachments uploaded yet.
                            </div>

                            <div v-else class="grid grid-cols-1 gap-3">
                                <div
                                    v-for="file in invoiceData.files || []"
                                    :key="file.id"
                                    class="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 hover:border-indigo-200 bg-white transition-all shadow-sm group"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-lg group-hover:bg-indigo-100 transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 truncate">{{ file.original_name || file.file_name }}</p>
                                            <p class="text-xs text-gray-500 mt-0.5">Uploaded {{ new Date(file.created_at).toLocaleDateString() }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a
                                            :href="file.file_url"
                                            target="_blank"
                                            class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-gray-50 rounded-lg transition-colors"
                                            title="Download / View"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        </a>
                                        <button
                                            @click="deleteAttachment(file.id)"
                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                            title="Delete"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 border-t border-gray-100 bg-gray-50/30">
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Upload New Attachment</label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl hover:border-indigo-500 transition-colors bg-white">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600 justify-center">
                                        <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                            <span>Upload a file</span>
                                            <input id="file-upload" name="file-upload" type="file" multiple class="sr-only" @change="handleFileUpload" :disabled="fileUploading">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">PNG, JPG, PDF, DOCX up to 20MB</p>
                                    <p v-if="fileUploading" class="text-xs font-semibold text-indigo-600 animate-pulse">Uploading and syncing with Xero...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
