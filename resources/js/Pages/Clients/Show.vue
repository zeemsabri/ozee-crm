<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import CustomComposeEmailContent from '@/Pages/Emails/Inbox/Components/CustomComposeEmailContent.vue';
import CustomEmailApprovalContent from '@/Pages/Emails/Inbox/Components/CustomEmailApprovalContent.vue';
import EmailDetailsContent from '@/Pages/Emails/Inbox/Components/EmailDetailsContent.vue';
import EmailActionContent from '@/Pages/Emails/Inbox/Components/EmailActionContent.vue';
import ReceivedEmailActionContent from '@/Pages/Emails/Inbox/Components/ReceivedEmailActionContent.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { useClientDetails } from '@/Composables/useClientDetails.js';
import { usePage } from '@inertiajs/vue3';
import { showSuccessNotification, showErrorNotification } from '@/Utils/notification';
import axios from 'axios';



const props = defineProps({
    id: { type: Number, required: true },
    lead: { type: Object, default: null },
});

// Centralized data fetching via composable
const idRef = ref(props.id);
const { loading, error, client: clientState, lead: leadState, fullName, presentations: leadPresentations, emails, notes, notesLoading, notesError, savingNote, xeroLogs, fetchClientDetails, fetchNotes, addNote } = useClientDetails(idRef);

const user = computed(() => usePage().props.auth.user);
const isSuperAdmin = computed(() => {
    if (!user.value) return false;
    return user.value.role_data?.slug === 'super-admin' ||
           user.value.role === 'super_admin' ||
           user.value.role === 'super-admin';
});
const isManager = computed(() => {
    if (!user.value) return false;
    return user.value.role_data?.slug === 'manager' ||
           user.value.role === 'manager' ||
           user.value.role === 'manager-role' ||
           user.value.role === 'manager_role';
});
const canManageClients = computed(() => isSuperAdmin.value || isManager.value);

// Xero Sync State
const showXeroSyncModal = ref(false);
const xeroSyncLoading = ref(false);
const xeroSyncError = ref('');
const xeroCandidates = ref([]);
const selectedXeroContactId = ref(null);

const openXeroSyncModal = async () => {
    xeroCandidates.value = [];
    selectedXeroContactId.value = null;
    xeroSyncError.value = '';
    showXeroSyncModal.value = true;

    await fetchXeroCandidates();
};

const fetchXeroCandidates = async () => {
    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const response = await axios.get(`/api/clients/${idRef.value}/xero-contact-candidates`);
        xeroCandidates.value = response.data?.candidates || [];

        if (xeroCandidates.value.length === 1) {
            selectedXeroContactId.value = xeroCandidates.value[0].contact_id;
        }
    } catch (error) {
        xeroSyncError.value = error.response?.data?.message || 'Failed to fetch Xero contacts.';
    } finally {
        xeroSyncLoading.value = false;
    }
};

const syncClientWithXero = async () => {
    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const payload = {};
        if (selectedXeroContactId.value) {
            payload.selected_contact_id = selectedXeroContactId.value;
        }

        await axios.post(`/api/clients/${idRef.value}/xero-contact-sync`, payload);
        showSuccessNotification('Client synced with Xero successfully.');
        showXeroSyncModal.value = false;
        await fetchClientDetails();
    } catch (error) {
        const responseData = error.response?.data || {};
        if (responseData.requires_selection && Array.isArray(responseData.candidates)) {
            xeroCandidates.value = responseData.candidates;
        }
        xeroSyncError.value = responseData.message || 'Failed to sync with Xero.';
        showErrorNotification(xeroSyncError.value);
    } finally {
        xeroSyncLoading.value = false;
    }
};

const createXeroContact = async () => {
    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        await axios.post(`/api/clients/${idRef.value}/xero-contact-create`);
        showSuccessNotification('Xero contact created and linked successfully.');
        showXeroSyncModal.value = false;
        await fetchClientDetails();
    } catch (error) {
        xeroSyncError.value = error.response?.data?.message || 'Failed to create Xero contact.';
        showErrorNotification(xeroSyncError.value);
    } finally {
        xeroSyncLoading.value = false;
    }
};

const xeroCandidateOptions = computed(() => {
    return xeroCandidates.value.map((candidate) => ({
        value: candidate.contact_id,
        label: `${candidate.name || 'Unnamed Contact'}${candidate.email ? ` (${candidate.email})` : ''}`,
    }));
});

// Vault State
const vaultCredentials = ref([]);
const vaultLoading = ref(false);
const vaultLogs = ref([]);
const logsLoading = ref(false);
const showUnlockModal = ref(false);
const unlockForm = ref({ credential_id: null, pin: '' });
const unlockedData = ref(null);
const unlocking = ref(false);

const fetchVaultCredentials = async () => {
    vaultLoading.value = true;
    try {
        const { data } = await axios.get(`/api/clients/${idRef.value}/vault`);
        vaultCredentials.value = data;
    } catch (e) {
        console.error('Failed to load vault', e);
    } finally {
        vaultLoading.value = false;
    }
};

const fetchVaultLogs = async (credentialId) => {
    logsLoading.value = true;
    vaultLogs.value = [];
    try {
        const { data } = await axios.get(`/api/vault/${credentialId}/logs`);
        vaultLogs.value = data;
    } catch (e) {
        console.error('Failed to load vault logs', e);
    } finally {
        logsLoading.value = false;
    }
};

const openLogs = async (cred) => {
    sidebar.value = { show: true, mode: 'vault-logs', title: `Logs: ${cred.label}`, data: cred, loading: true };
    await fetchVaultLogs(cred.id);
    sidebar.value.loading = false;
};

const openUnlock = (cred) => {
    unlockForm.value = { credential_id: cred.id, pin: '' };
    unlockedData.value = null;
    showUnlockModal.value = true;
};

const handleUnlock = async () => {
    unlocking.value = true;
    try {
        const { data } = await axios.post('/api/vault/unlock', unlockForm.value);
        unlockedData.value = data;
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to unlock');
    } finally {
        unlocking.value = false;
    }
};

const deleteCredential = async (id) => {
    if (!confirm('Permanent delete?')) return;
    try {
        await axios.delete(`/api/vault/${id}`);
        vaultCredentials.value = vaultCredentials.value.filter(c => c.id !== id);
    } catch (e) {
        alert('Failed to delete');
    }
};

// Local UI state
const noteInput = ref('');

// Convert to Client
const showConfirm = ref(false);
const converting = ref(false);
const convertError = ref('');
const convertToClient = async () => {
    if (!leadState.value) return;
    converting.value = true;
    convertError.value = '';
    try {
        const { data } = await window.axios.post(`/api/leads/${leadState.value.id}/convert`);
        const newClientId = data?.client_id || data?.id || data;
        showConfirm.value = false;
        if (newClientId) {
            window.location.href = `/clients/${newClientId}`; // redirect to client profile
        }
    } catch (e) {
        console.error('Conversion failed', e);
        convertError.value = e?.response?.data?.message || 'Failed to convert lead';
    } finally {
        converting.value = false;
    }
};



// Sidebar state
const sidebar = ref({ show: false, mode: null, title: '', data: null, loading: false });

const openCompose = () => {
    sidebar.value = { show: true, mode: 'custom-compose', title: 'Compose Email to Lead', data: null, loading: false };
};

const openApproval = (email) => {
    sidebar.value = { show: true, mode: 'view-email', title: 'Email Details', data: email, loading: false };
};

const handleEditEmail = (email) => {
    sidebar.value.show = true;
    sidebar.value.data = email;
    if (email.type === 'received' && (email.status === 'pending_approval_received' || email.status === 'received')) {
        sidebar.value.mode = 'received-edit';
        sidebar.value.title = 'Approve Received Email';
    } else {
        if (!email.template_id) {
            sidebar.value.mode = 'custom-edit';
            sidebar.value.title = 'Edit & Approve Custom Email';
        } else {
            sidebar.value.mode = 'edit';
            sidebar.value.title = 'Edit & Approve Template Email';
        }
    }
};

const handleSidebarSubmitted = () => {
    sidebar.value.show = false;
    fetchClientDetails();
};

const changeEmailPage = (page) => {
    // No-op or remove if not needed
};

onMounted(async () => {
    await fetchClientDetails();
    await fetchNotes();
    await fetchVaultCredentials();
});
</script>

<template>
    <Head :title="`Client: ${fullName}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Client Details</h2>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <PrimaryButton @click="openCompose">Compose Email</PrimaryButton>
                    <span v-if="clientState?.lead_id" class="text-sm text-gray-500">Converted from Lead</span>
                </div>
            </div>
        </template>

        <div class="py-6 min-h-screen w-full">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="error" class="mb-4 text-red-600">{{ error }}</div>
                        <div v-if="loading" class="animate-pulse space-y-3">
                            <div class="h-5 bg-gray-200 rounded w-1/3"></div>
                            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                            <div class="h-32 bg-gray-100 rounded"></div>
                        </div>

                        <div v-if="clientState && !loading" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Details -->
                            <section class="lg:col-span-2 space-y-6">
                                <div>
                                    <h3 class="text-lg font-semibold mb-2">Profile</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                        <div><span class="text-gray-500">Name:</span> <span class="font-medium">{{ fullName }}</span></div>
                                        <div v-if="clientState?.email"><span class="text-gray-500">Email:</span> <span class="font-medium">{{ clientState.email }}</span></div>
                                        <div v-if="clientState?.phone"><span class="text-gray-500">Phone:</span> <span class="font-medium">{{ clientState.phone }}</span></div>
                                        <div v-if="clientState?.company"><span class="text-gray-500">Company:</span> <span class="font-medium">{{ clientState.company }}</span></div>
                                        <div v-if="clientState?.address"><span class="text-gray-500">Address:</span> <span class="font-medium">{{ clientState.address }}</span></div>
                                        <div v-if="clientState?.notes"><span class="text-gray-500">Notes:</span> <span class="font-medium">{{ clientState.notes }}</span></div>
                                    </div>
                                </div>

                                <!-- Xero Integration Section -->
                                <div class="mt-8 bg-emerald-50 border border-emerald-100 rounded-xl p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center gap-2">
                                            <div class="p-2 bg-emerald-100 rounded-lg">
                                                <svg class="w-6 h-6 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <h3 class="text-lg font-bold text-emerald-900">Xero Integration</h3>
                                        </div>
                                        <div v-if="clientState?.xero_contact_id" class="flex items-center gap-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                                CONNECTED
                                            </span>
                                            <SecondaryButton @click="openXeroSyncModal" class="!py-1 !text-xs">Re-sync</SecondaryButton>
                                        </div>
                                        <PrimaryButton v-else @click="openXeroSyncModal" class="!bg-emerald-600 hover:!bg-emerald-700 !py-1.5 !text-xs">
                                            Link to Xero
                                        </PrimaryButton>
                                    </div>

                                    <div v-if="clientState?.xero_contact_id" class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-white p-4 rounded-lg border border-emerald-100 mb-6">
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Xero Contact Name</label>
                                            <div class="text-sm font-bold text-gray-900">{{ clientState.xero_contact_name }}</div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Xero Contact Email</label>
                                            <div class="text-sm font-bold text-gray-900">{{ clientState.xero_contact_email || 'No email set' }}</div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Last Sync</label>
                                            <div class="text-sm font-medium text-gray-700">{{ clientState.xero_synced_at ? new Date(clientState.xero_synced_at).toLocaleString() : 'Never' }}</div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Sync Mode</label>
                                            <div class="text-sm font-medium text-gray-700 uppercase">{{ clientState.xero_sync_mode || 'Manual' }}</div>
                                        </div>
                                    </div>

                                    <div v-if="xeroLogs.length > 0">
                                        <h4 class="text-xs font-bold text-emerald-900 uppercase tracking-widest mb-3">Sync History</h4>
                                        <div class="space-y-2 max-h-40 overflow-y-auto pr-2">
                                            <div v-for="log in xeroLogs" :key="log.id" class="text-xs bg-white/50 p-2 rounded border border-emerald-50 flex justify-between items-start">
                                                <div>
                                                    <span class="font-bold text-gray-700">{{ log.description }}</span>
                                                    <div class="text-gray-500 mt-0.5">By {{ log.causer?.name || 'System' }}</div>
                                                </div>
                                                <div class="text-gray-400 text-[10px]">{{ new Date(log.created_at).toLocaleString() }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else-if="!clientState?.xero_contact_id" class="text-sm text-emerald-800 bg-emerald-100/50 p-4 rounded-lg border border-emerald-100 italic">
                                        Link this client to Xero to keep contacts in sync and manage invoices easily.
                                    </div>
                                </div>

                                <!-- Vault Section -->
                                <div class="mt-8 bg-blue-50 border border-blue-100 rounded-xl p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            <h3 class="text-lg font-bold text-blue-900">Secure Client Vault</h3>
                                        </div>
                                        <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-2 py-1 rounded-full">AES-256 PBKDF2</span>
                                    </div>

                                    <div v-if="vaultLoading" class="text-sm text-gray-500">Loading vault...</div>
                                    <div v-else-if="vaultCredentials.length === 0" class="text-sm text-blue-800 bg-blue-100 p-4 rounded-lg border border-blue-200">
                                        No credentials shared by this client yet.
                                    </div>
                                    <div v-else class="space-y-3">
                                        <div v-for="cred in vaultCredentials" :key="cred.id" class="flex items-center justify-between bg-white p-4 rounded-lg border border-blue-100 shadow-sm">
                                            <div>
                                                <div class="font-semibold text-gray-900">{{ cred.label }}</div>
                                                <div class="text-xs text-gray-500">
                                                    Expires: {{ new Date(cred.expires_at).toLocaleDateString() }} 
                                                    <span v-if="cred.last_viewed_at" class="ml-2">• Last Viewed: {{ new Date(cred.last_viewed_at).toLocaleDateString() }}</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <SecondaryButton @click="openLogs(cred)" class="!py-1 !text-xs !bg-gray-100 !text-gray-700 hover:!bg-gray-200">Logs</SecondaryButton>
                                                <SecondaryButton @click="openUnlock(cred)" class="!py-1 !text-xs">Unlock</SecondaryButton>
                                                <button @click="deleteCredential(cred.id)" class="text-red-500 hover:text-red-700 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Emails Section -->
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="text-lg font-semibold">Emails</h3>
                                        <PrimaryButton @click="openCompose">Compose Email</PrimaryButton>
                                    </div>
                                    <div v-if="loading" class="text-gray-500 text-sm">Loading emails...</div>
                                    <div v-else-if="error" class="text-red-600 text-sm">{{ error }}</div>
                                    <div v-else-if="emails.length === 0" class="text-gray-500 text-sm">No emails found for this lead.</div>
                                    <div v-else class="overflow-x-auto shadow rounded-md border border-gray-100">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="e in emails" :key="e.id" class="hover:bg-gray-50 cursor-pointer" @click="openApproval(e)">
                                                <td class="px-4 py-2 text-sm text-gray-900">{{ e.subject }}</td>
                                                <td class="px-4 py-2 text-sm">
                            <span :class="{
                              'px-2 py-1 rounded-full text-xs font-medium': true,
                              'bg-green-100 text-green-800': e.status === 'sent',
                              'bg-yellow-100 text-yellow-800': e.status === 'pending_approval' || e.status === 'pending_approval_received',
                              'bg-blue-100 text-blue-800': e.status === 'received'
                            }">{{ (e.status || 'n/a').replace(/_/g, ' ').toUpperCase() }}</span>
                                                </td>
                                                <td class="px-4 py-2 text-sm text-gray-500">{{ e.type }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-500">{{ new Date(e.created_at).toLocaleString() }}</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>

                            <!-- Notes / Activity -->
                            <aside class="lg:col-span-1 space-y-6">
                                <!-- Presentations Section -->
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="text-lg font-semibold">Presentations</h3>
                                    </div>
                                    <div v-if="loading" class="text-gray-500 text-sm">Loading presentations...</div>
                                    <div v-else-if="error" class="text-red-600 text-sm">{{ error }}</div>
                                    <div v-else-if="leadPresentations.length === 0" class="text-gray-500 text-sm">No presentations found for this lead.</div>
                                    <ul v-else class="divide-y divide-gray-200 rounded-md border border-gray-200">
                                        <li v-for="p in leadPresentations" :key="p.id" class="p-3 flex items-center justify-between">
                                            <div>
                                                <div class="font-medium">{{ p.title }}</div>
                                                <div class="text-xs text-gray-500">Type: {{ p.type }}</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <PrimaryButton as="a" :href="`/view/${p.share_token}`" target="_blank" title="Open public preview">View</PrimaryButton>
                                                <PrimaryButton as="a" :href="`/presentations/${p.id}/edit`" title="Edit in builder">Edit</PrimaryButton>
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <div>
                                    <h3 class="text-lg font-semibold mb-2">Notes</h3>
                                    <div class="mb-3 flex gap-2">
                                        <input
                                            v-model="noteInput"
                                            type="text"
                                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Add a note..."
                                        />
                                        <PrimaryButton :disabled="savingNote || !noteInput.trim()" @click="async () => { await addNote(noteInput); noteInput = ''; }">{{ savingNote ? 'Saving...' : 'Save' }}</PrimaryButton>
                                    </div>
                                    <div v-if="notesError" class="text-red-600 text-sm mb-2">{{ notesError }}</div>
                                    <div v-if="notesLoading" class="text-gray-500 text-sm">Loading notes...</div>
                                    <div class="relative">
                                        <div class="absolute left-3 top-0 bottom-0 w-px bg-gray-200" aria-hidden="true"></div>
                                        <ul class="space-y-4">
                                            <li v-for="n in notes" :key="n.id" class="relative pl-8">
                                                <div class="absolute left-0 top-1.5 w-2 h-2 bg-indigo-400 rounded-full"></div>
                                                <div class="border rounded-md p-3 bg-white">
                                                    <div class="text-sm whitespace-pre-wrap">{{ n.body || n.content || n.note || '' }}</div>
                                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-600" v-if="n.type">{{ n.type }}</span>
                                                        <span>By {{ n.user?.name || 'User' }} • {{ new Date(n.created_at).toLocaleString() }}</span>
                                                    </div>
                                                </div>
                                            </li>
                                            <li v-if="!notesLoading && notes.length === 0" class="text-sm text-gray-500">No notes have been added yet.</li>
                                        </ul>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <Modal :show="showConfirm" @close="showConfirm = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-2">Convert to Client</h3>
                <p>Are you sure you want to convert this lead into a client?</p>
                <div v-if="convertError" class="text-red-600 text-sm mt-2">{{ convertError }}</div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton :disabled="converting" @click="showConfirm = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="converting" @click="convertToClient">{{ converting ? 'Converting...' : 'Convert' }}</PrimaryButton>
                </div>
            </div>
        </Modal>
        <!-- Right Sidebar for Compose / Approve -->
        <RightSidebar :show="sidebar.show" :title="sidebar.title" @close="sidebar.show = false">
            <template #content>
                <div v-if="sidebar.mode === 'custom-compose'">
                    <CustomComposeEmailContent
                        :project-id="null"
                        :user-project-role="{}"
                        force-recipient-mode="leads"
                        :preset-lead-ids="[idRef]"
                        :hide-recipient-controls="true"
                        @submitted="handleSidebarSubmitted"
                        @error="() => {}"
                    />
                </div>
                <div v-else-if="sidebar.mode === 'view-email'">
                    <EmailDetailsContent
                        :email="sidebar.data"
                        :can-approve-emails="sidebar.data?.can_approve"
                        @edit="handleEditEmail"
                        @reject="(email) => { sidebar.mode = 'reject'; sidebar.data = email; sidebar.title = 'Reject Email'; }"
                    />
                </div>
                <div v-else-if="sidebar.mode === 'edit' || sidebar.mode === 'reject'">
                    <EmailActionContent
                        :email="sidebar.data"
                        :mode="sidebar.mode"
                        @submitted="handleSidebarSubmitted"
                    />
                </div>
                <div v-else-if="sidebar.mode === 'received-edit'">
                    <ReceivedEmailActionContent
                        :email="sidebar.data"
                        @submitted="handleSidebarSubmitted"
                        @error="() => {}"
                    />
                </div>
                <div v-else-if="sidebar.mode === 'custom-edit'">
                    <CustomEmailApprovalContent
                        :email="sidebar.data"
                        @submitted="handleSidebarSubmitted"
                        @error="() => {}"
                    />
                </div>
                <!-- Vault Logs View -->
                <div v-else-if="sidebar.mode === 'vault-logs'" class="space-y-6">
                    <div v-if="logsLoading" class="flex items-center justify-center py-12">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    </div>
                    <div v-else-if="vaultLogs.length === 0" class="text-center py-12 text-gray-500 italic">
                        No activity recorded yet for this credential.
                    </div>
                    <div v-else class="flow-root">
                        <ul role="list" class="-mb-8">
                            <li v-for="(log, idx) in vaultLogs" :key="log.id">
                                <div class="relative pb-8">
                                    <span v-if="idx !== vaultLogs.length - 1" class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-blue-50 flex items-center justify-center ring-8 ring-white">
                                                <svg class="h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                            <div>
                                                <p class="text-sm text-gray-500">
                                                    {{ log.description }} 
                                                    <span class="font-medium text-gray-900">by {{ log.causer?.name || 'System' }}</span>
                                                </p>
                                            </div>
                                            <div class="whitespace-nowrap text-right text-sm text-gray-500">
                                                <time :datetime="log.created_at">{{ new Date(log.created_at).toLocaleString() }}</time>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </template>
        </RightSidebar>

        <!-- Unlock Vault Modal -->
        <Modal :show="showUnlockModal" @close="showUnlockModal = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Unlock Credentials</h3>
                
                <div v-if="unlockedData" class="bg-green-50 p-6 rounded-xl border border-green-200 mb-6 animate-pulse">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-green-700 uppercase">Username / Email</label>
                            <div class="text-lg font-mono break-all">{{ unlockedData.username }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-green-700 uppercase">Password</label>
                            <div class="text-lg font-mono break-all">{{ unlockedData.password }}</div>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-green-600 italic">This data will disappear when you close this modal.</p>
                </div>
                
                <form v-else @submit.prevent="handleUnlock" class="space-y-4">
                    <p class="text-sm text-gray-600">Enter the PIN provided by the client to decrypt these credentials.</p>
                    <div>
                        <InputLabel for="unlock_pin" value="Enter PIN" />
                        <TextInput 
                            id="unlock_pin" 
                            type="password" 
                            class="mt-1 block w-full text-center font-mono tracking-widest text-xl" 
                            v-model="unlockForm.pin" 
                            required 
                            autofocus 
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <SecondaryButton @click="showUnlockModal = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="unlocking" type="submit">
                            {{ unlocking ? 'Decrypting...' : 'Unlock Data' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Xero Sync Modal -->
        <Modal :show="showXeroSyncModal" @close="showXeroSyncModal = false" maxWidth="2xl">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2">Sync with Xero Contact</h2>

                <p class="text-sm text-gray-600 mb-4" v-if="clientState">
                    Client: <span class="font-bold text-gray-900">{{ clientState.name }}</span>
                    <span v-if="clientState.email" class="text-gray-500 ml-1">({{ clientState.email }})</span>
                </p>

                <div v-if="xeroSyncError" class="mb-4 rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
                    {{ xeroSyncError }}
                </div>

                <div v-if="xeroSyncLoading" class="flex flex-col items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600 mb-2"></div>
                    <div class="text-xs text-gray-500 font-medium">Communicating with Xero...</div>
                </div>

                <div v-else>
                    <div v-if="xeroCandidates.length === 0" class="bg-gray-50 rounded-xl p-6 text-center border-2 border-dashed border-gray-200">
                        <p class="text-sm text-gray-500">No matching contacts found in Xero for this client.</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div>
                            <InputLabel value="Select Existing Xero Contact" class="font-bold text-xs uppercase text-gray-500 mb-1" />
                            <SelectDropdown
                                :options="xeroCandidateOptions"
                                v-model="selectedXeroContactId"
                                placeholder="Select a matching Xero contact"
                                class="!rounded-xl"
                            />
                        </div>

                        <div class="max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50">
                            <div
                                v-for="candidate in xeroCandidates"
                                :key="candidate.contact_id"
                                class="border-b border-gray-200 px-4 py-3 text-sm last:border-b-0 hover:bg-white transition-colors"
                            >
                                <div class="font-bold text-gray-900">{{ candidate.name || 'Unnamed Contact' }}</div>
                                <div class="text-xs text-gray-500">{{ candidate.email || 'No email' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <SecondaryButton @click="showXeroSyncModal = false" class="!rounded-xl">Cancel</SecondaryButton>
                    <SecondaryButton
                        v-if="!xeroSyncLoading"
                        @click="createXeroContact"
                        class="!bg-emerald-50 !text-emerald-700 !border-emerald-200 hover:!bg-emerald-100 !rounded-xl"
                        title="Create this client as a new contact in Xero"
                    >
                        Create in Xero
                    </SecondaryButton>
                    <PrimaryButton
                        :disabled="xeroSyncLoading || (xeroCandidates.length === 0 && !selectedXeroContactId)"
                        @click="syncClientWithXero"
                        class="!bg-indigo-600 hover:!bg-indigo-700 !rounded-xl shadow-lg shadow-indigo-100"
                    >
                        Save Link
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
