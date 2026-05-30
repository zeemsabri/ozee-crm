<script setup>
import { ref, watch, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { success, error as notifyError } from '@/Utils/notification';
import {
    LinkIcon,
    EnvelopeIcon,
    ClipboardDocumentIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    project: { type: Object, required: true },
    users: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'share-updated']);

const activeTab = ref('link');
const loadingToken = ref(false);
const loadingSend = ref(false);
const loadingTracking = ref(false);
const loadingRecipients = ref(false);
const shareEnabled = ref(false);
const shareUrl = ref(null);
const trackingSummary = ref({ email_sent: 0, email_open: 0, link_open: 0, proposal_submitted: 0 });
const trackingRows = ref([]);
const recipients = ref([]);

// Email tab state
const selectedUserIds = ref([]);
const externalEmailInput = ref('');
const externalEmails = ref([]);
const customMessage = ref('');
const userSearch = ref('');
const roleFilter = ref('all');
const recipientTypeFilter = ref('all');

const roleOptions = computed(() => {
    const list = recipients.value.length ? recipients.value : props.users;
    const uniqueRoles = [...new Map(
        list
            .filter(u => u.role_slug || u.role_name)
            .map(u => [u.role_slug || u.role_name, { value: u.role_slug || u.role_name, label: u.role_name || u.role_slug }])
    ).values()];

    return [{ value: 'all', label: 'All Roles' }, ...uniqueRoles.sort((a, b) => String(a.label).localeCompare(String(b.label)))];
});

const filteredUsers = computed(() => {
    const list = recipients.value.length ? recipients.value : props.users;
    let filtered = list;

    if (recipientTypeFilter.value !== 'all') {
        filtered = filtered.filter(u => {
            if (recipientTypeFilter.value === 'guest') return u.user_type === 'guest';
            if (recipientTypeFilter.value === 'team') return u.source === 'project_team';
            if (recipientTypeFilter.value === 'saved') return u.source === 'saved_contact';
            return true;
        });
    }

    if (roleFilter.value !== 'all') {
        filtered = filtered.filter(u => (u.role_slug || u.role_name) === roleFilter.value);
    }

    if (!userSearch.value) return filtered;
    const q = userSearch.value.toLowerCase();
    return filtered.filter(u =>
        u.name?.toLowerCase().includes(q) || u.email?.toLowerCase().includes(q)
    );
});

const allFilteredSelected = computed(() => {
    if (!filteredUsers.value.length) return false;
    return filteredUsers.value.every(u => selectedUserIds.value.includes(u.id));
});

watch(() => props.show, (val) => {
    if (val) {
        loadShareInfo();
        loadRecipients();
        loadTracking();
        selectedUserIds.value = [];
        externalEmails.value = [];
        customMessage.value = '';
        userSearch.value = '';
        roleFilter.value = 'all';
        recipientTypeFilter.value = 'all';
        externalEmailInput.value = '';
    }
});

async function loadShareInfo() {
    try {
        const { data } = await axios.get(`/api/projects/${props.project.id}/share`);
        shareEnabled.value = data.enabled;
        shareUrl.value = data.share_url;
    } catch {
        // ignore
    }
}

async function loadRecipients() {
    loadingRecipients.value = true;
    try {
        const { data } = await axios.get(`/api/projects/${props.project.id}/share/recipients`);
        recipients.value = data.recipients || [];
    } catch {
        recipients.value = props.users || [];
    } finally {
        loadingRecipients.value = false;
    }
}

async function loadTracking() {
    loadingTracking.value = true;
    try {
        const { data } = await axios.get(`/api/projects/${props.project.id}/share/tracking`);
        trackingSummary.value = data.summary || { email_sent: 0, email_open: 0, link_open: 0, proposal_submitted: 0 };
        trackingRows.value = data.rows || [];
    } catch {
        trackingSummary.value = { email_sent: 0, email_open: 0, link_open: 0, proposal_submitted: 0 };
        trackingRows.value = [];
    } finally {
        loadingTracking.value = false;
    }
}

async function toggleShare(enabled) {
    loadingToken.value = true;
    try {
        const { data } = await axios.post(`/api/projects/${props.project.id}/share/token`, { enabled });
        shareEnabled.value = data.enabled;
        shareUrl.value = data.share_url;
        emit('share-updated', { enabled: data.enabled, shareUrl: data.share_url });
        success(data.message);
    } catch (e) {
        notifyError(e.response?.data?.message || 'Failed to update share link.');
    } finally {
        loadingToken.value = false;
    }
}

async function regenerateLink() {
    if (!confirm('Regenerate the link? Anyone with the old link will no longer be able to access the project.')) return;
    loadingToken.value = true;
    try {
        const { data } = await axios.post(`/api/projects/${props.project.id}/share/regenerate`);
        shareUrl.value = data.share_url;
        emit('share-updated', { enabled: true, shareUrl: data.share_url });
        success('Link regenerated.');
    } catch (e) {
        notifyError(e.response?.data?.message || 'Failed to regenerate link.');
    } finally {
        loadingToken.value = false;
    }
}

function copyLink() {
    if (!shareUrl.value) return;
    navigator.clipboard.writeText(shareUrl.value).then(() => success('Link copied to clipboard!'));
}

function toggleUser(userId) {
    const idx = selectedUserIds.value.indexOf(userId);
    if (idx === -1) selectedUserIds.value.push(userId);
    else selectedUserIds.value.splice(idx, 1);
}

function selectAllFilteredUsers() {
    const idsToAdd = filteredUsers.value
        .map(u => u.id)
        .filter(id => !selectedUserIds.value.includes(id));

    selectedUserIds.value = [...selectedUserIds.value, ...idsToAdd];
}

function clearFilteredSelection() {
    const filteredIds = new Set(filteredUsers.value.map(u => u.id));
    selectedUserIds.value = selectedUserIds.value.filter(id => !filteredIds.has(id));
}

function addExternalEmail() {
    const val = externalEmailInput.value.trim();
    if (!val) return;
    // Basic email validation
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
        notifyError('Please enter a valid email address.');
        return;
    }
    if (!externalEmails.value.includes(val)) {
        externalEmails.value.push(val);
    }
    externalEmailInput.value = '';
}

function removeExternalEmail(email) {
    externalEmails.value = externalEmails.value.filter(e => e !== email);
}

function handleExternalEmailKeydown(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addExternalEmail();
    }
}

async function sendInvites() {
    if (selectedUserIds.value.length === 0 && externalEmails.value.length === 0) {
        notifyError('Please select at least one recipient.');
        return;
    }
    loadingSend.value = true;
    try {
        const { data } = await axios.post(`/api/projects/${props.project.id}/share/email`, {
            user_ids: selectedUserIds.value,
            external_emails: externalEmails.value,
            message: customMessage.value || null,
        });
        success(data.message);
        await loadRecipients();
        await loadTracking();
        selectedUserIds.value = [];
        externalEmails.value = [];
        customMessage.value = '';
    } catch (e) {
        const errs = e.response?.data?.errors;
        notifyError(errs ? Object.values(errs).flat().join(' ') : (e.response?.data?.message || 'Failed to send invites.'));
    } finally {
        loadingSend.value = false;
    }
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Share Project</h2>
                <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 mb-5">
                <button
                    @click="activeTab = 'link'"
                    class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium border-b-2 transition"
                    :class="activeTab === 'link' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                >
                    <LinkIcon class="w-4 h-4" />
                    Copy Link
                </button>
                <button
                    @click="activeTab = 'email'"
                    class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium border-b-2 transition"
                    :class="activeTab === 'email' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                >
                    <EnvelopeIcon class="w-4 h-4" />
                    Send by Email
                </button>
                <button
                    @click="activeTab = 'tracking'"
                    class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium border-b-2 transition"
                    :class="activeTab === 'tracking' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                >
                    Tracking
                </button>
            </div>

            <!-- Tab: Copy Link -->
            <div v-if="activeTab === 'link'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Public Access</p>
                        <p class="text-xs text-gray-400 mt-0.5">Allow anyone with the link to view this project and submit proposals.</p>
                    </div>
                    <button
                        @click="toggleShare(!shareEnabled)"
                        :disabled="loadingToken"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :class="shareEnabled ? 'bg-blue-600' : 'bg-gray-300'"
                    >
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                            :class="shareEnabled ? 'translate-x-6' : 'translate-x-1'"
                        />
                    </button>
                </div>

                <template v-if="shareEnabled && shareUrl">
                    <div class="flex gap-2">
                        <input
                            :value="shareUrl"
                            readonly
                            class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-gray-50 focus:outline-none"
                        />
                        <button
                            @click="copyLink"
                            class="flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition"
                        >
                            <ClipboardDocumentIcon class="w-4 h-4" />
                            Copy
                        </button>
                    </div>
                    <button
                        @click="regenerateLink"
                        :disabled="loadingToken"
                        class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-red-500 transition disabled:opacity-50"
                    >
                        <ArrowPathIcon class="w-3.5 h-3.5" />
                        Regenerate link (invalidates current link)
                    </button>
                </template>

                <div v-if="!shareEnabled" class="text-sm text-gray-400 italic">
                    Enable public access above to generate a shareable link.
                </div>
            </div>

            <!-- Tab: Send by Email -->
            <div v-else-if="activeTab === 'email'" class="space-y-4">
                <div v-if="!shareEnabled" class="p-3 bg-yellow-50 border border-yellow-200 rounded text-yellow-700 text-sm">
                    ⚠ Public sharing is not enabled. Please enable it in the "Copy Link" tab first.
                </div>

                <!-- Saved recipients -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">Saved Recipients</label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="allFilteredSelected ? clearFilteredSelection() : selectAllFilteredUsers()"
                                :disabled="!filteredUsers.length"
                                class="text-xs px-2 py-1 rounded border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                            >
                                {{ allFilteredSelected ? 'Clear Filtered' : 'Select All Filtered' }}
                            </button>
                            <button
                                type="button"
                                @click="selectedUserIds = []"
                                :disabled="selectedUserIds.length === 0"
                                class="text-xs px-2 py-1 rounded border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                            >
                                Clear All
                            </button>
                            <button
                                type="button"
                                @click="loadRecipients"
                                :disabled="loadingRecipients"
                                class="text-xs px-2 py-1 rounded border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                            >
                                {{ loadingRecipients ? 'Refreshing…' : 'Refresh' }}
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mb-2">Includes project team users and past proposers/guest contacts you've previously invited or received proposals from.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
                        <select
                            v-model="roleFilter"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
                        </select>
                        <select
                            v-model="recipientTypeFilter"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="all">All Recipient Types</option>
                            <option value="team">Project Team</option>
                            <option value="saved">Saved Contacts</option>
                            <option value="guest">Guest Users</option>
                        </select>
                    </div>
                    <input
                        v-model="userSearch"
                        type="text"
                        placeholder="Search recipients…"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <div class="max-h-36 overflow-y-auto border border-gray-100 rounded-lg divide-y divide-gray-50">
                        <label
                            v-for="u in filteredUsers"
                            :key="u.id"
                            class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :value="u.id"
                                :checked="selectedUserIds.includes(u.id)"
                                @change="toggleUser(u.id)"
                                class="rounded text-blue-600"
                            />
                            <div>
                                <p class="text-sm font-medium text-gray-800">
                                    {{ u.name }}
                                    <span v-if="u.user_type === 'guest'" class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">guest</span>
                                    <span v-else-if="u.role_name" class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-700">{{ u.role_name }}</span>
                                </p>
                                <p class="text-xs text-gray-400">{{ u.email }}<span v-if="u.source === 'saved_contact'" class="ml-1">• saved</span></p>
                            </div>
                        </label>
                        <div v-if="filteredUsers.length === 0" class="px-3 py-3 text-sm text-gray-400 text-center">
                            No recipients found.
                        </div>
                    </div>
                    <p v-if="selectedUserIds.length > 0" class="text-xs text-blue-600 mt-1">{{ selectedUserIds.length }} user(s) selected</p>
                </div>

                <!-- External emails -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">External Email Addresses</label>
                    <div class="flex gap-2">
                        <input
                            v-model="externalEmailInput"
                            type="email"
                            placeholder="email@example.com"
                            class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @keydown="handleExternalEmailKeydown"
                        />
                        <button
                            @click="addExternalEmail"
                            class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-lg transition"
                        >
                            Add
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">Press Enter or comma to add multiple.</p>
                    <div v-if="externalEmails.length > 0" class="flex flex-wrap gap-2 mt-2">
                        <span
                            v-for="e in externalEmails"
                            :key="e"
                            class="flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 text-xs rounded-full"
                        >
                            {{ e }}
                            <button @click="removeExternalEmail(e)" class="text-blue-400 hover:text-red-500">×</button>
                        </span>
                    </div>
                </div>

                <!-- Custom message -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Custom Message (optional)</label>
                    <textarea
                        v-model="customMessage"
                        rows="3"
                        placeholder="Add a personal note to include in the invitation email…"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                <button
                    @click="sendInvites"
                    :disabled="loadingSend || !shareEnabled"
                    class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg transition"
                >
                    {{ loadingSend ? 'Sending…' : 'Send Invites' }}
                </button>
            </div>

            <div v-else-if="activeTab === 'tracking'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600">See who was sent the link, opened it, and submitted proposals.</p>
                    <button
                        @click="loadTracking"
                        :disabled="loadingTracking"
                        class="text-xs px-2 py-1 rounded border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                    >
                        {{ loadingTracking ? 'Refreshing…' : 'Refresh' }}
                    </button>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                        <div class="text-xs text-gray-500">Emails Sent</div>
                        <div class="text-lg font-semibold text-gray-900">{{ trackingSummary.email_sent }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                        <div class="text-xs text-gray-500">Emails Opened</div>
                        <div class="text-lg font-semibold text-gray-900">{{ trackingSummary.email_open }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                        <div class="text-xs text-gray-500">Link Opened</div>
                        <div class="text-lg font-semibold text-gray-900">{{ trackingSummary.link_open }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                        <div class="text-xs text-gray-500">Proposals Submitted</div>
                        <div class="text-lg font-semibold text-gray-900">{{ trackingSummary.proposal_submitted }}</div>
                    </div>
                </div>

                <div class="border border-gray-100 rounded-lg overflow-hidden">
                    <div class="max-h-72 overflow-y-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-left px-3 py-2 font-medium">Name</th>
                                <th class="text-left px-3 py-2 font-medium">Email</th>
                                <th class="text-center px-3 py-2 font-medium">Sent</th>
                                <th class="text-center px-3 py-2 font-medium">Opened</th>
                                <th class="text-center px-3 py-2 font-medium">Link</th>
                                <th class="text-center px-3 py-2 font-medium">Submitted</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-if="!trackingRows.length && !loadingTracking">
                                <td colspan="6" class="px-3 py-4 text-center text-gray-400">No tracking data yet.</td>
                            </tr>
                            <tr v-for="row in trackingRows" :key="row.user_id" class="border-t border-gray-100">
                                <td class="px-3 py-2">{{ row.name || 'Unknown' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ row.email || '-' }}</td>
                                <td class="px-3 py-2 text-center">{{ row.email_sent ? '✓' : '—' }}</td>
                                <td class="px-3 py-2 text-center">{{ row.email_open ? '✓' : '—' }}</td>
                                <td class="px-3 py-2 text-center">{{ row.link_open ? '✓' : '—' }}</td>
                                <td class="px-3 py-2 text-center">{{ row.proposal_submitted ? '✓' : '—' }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
