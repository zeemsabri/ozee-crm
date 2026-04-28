<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    projectId: {
        type: [Number, String],
        required: true,
    },
    canManageAllCredentials: {
        type: Boolean,
        default: false,
    },
    isManagementView: {
        type: Boolean,
        default: false,
    },
});

const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const credentials = ref([]);

const createForm = reactive({
    label: '',
    username: '',
    password: '',
    pin: '',
    is_visible_to_client: false,
    expiry_days: 0,
});

const unlockPinByCredential = ref({});
const unlockedByCredential = ref({});
const unlockingByCredential = ref({});

const editingCredentialId = ref(null);
const editForm = reactive({
    label: '',
    is_visible_to_client: false,
    expiry_days: 0,
});

const sharingCredentialId = ref(null);
const availableUsers = ref([]);
const sharedUsers = ref([]);
const selectedShareUserIds = ref([]);
const shareLoading = ref(false);
const shareSaving = ref(false);

const canManage = computed(() => props.canManageAllCredentials);

const shareUserOptions = computed(() => {
    return (availableUsers.value || []).map((user) => ({
        value: user.id,
        label: user.email ? `${user.name} (${user.email})` : user.name,
    }));
});

const normalizeExpiryDays = (value) => {
    const parsed = Number(value);

    if (!Number.isFinite(parsed) || parsed < 0) {
        return 0;
    }

    return Math.min(3650, Math.floor(parsed));
};

const resetMessages = () => {
    errorMessage.value = '';
    successMessage.value = '';
};

const fetchCredentials = async () => {
    loading.value = true;
    resetMessages();

    try {
        const { data } = await axios.get(`/api/projects/${props.projectId}/vault-credentials`);
        credentials.value = data;
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Failed to load project credentials.';
    } finally {
        loading.value = false;
    }
};

const createCredential = async () => {
    saving.value = true;
    resetMessages();

    try {
        await axios.post(`/api/projects/${props.projectId}/vault-credentials`, {
            ...createForm,
            expiry_days: normalizeExpiryDays(createForm.expiry_days),
        });

        createForm.label = '';
        createForm.username = '';
        createForm.password = '';
        createForm.pin = '';
        createForm.is_visible_to_client = false;
        createForm.expiry_days = 0;

        successMessage.value = 'Credential added successfully.';
        await fetchCredentials();
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Failed to add credential.';
    } finally {
        saving.value = false;
    }
};

const unlockCredential = async (credential) => {
    const credentialId = credential.id;
    unlockingByCredential.value = {
        ...unlockingByCredential.value,
        [credentialId]: true,
    };
    resetMessages();

    try {
        const pin = unlockPinByCredential.value[credentialId] || null;
        const payload = {
            credential_id: credentialId,
        };

        if (pin) {
            payload.pin = pin;
        }

        const { data } = await axios.post('/api/vault/unlock', payload);

        unlockedByCredential.value = {
            ...unlockedByCredential.value,
            [credentialId]: data,
        };

        successMessage.value = 'Credential unlocked.';
        await fetchCredentials();
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Failed to unlock credential.';
    } finally {
        unlockingByCredential.value = {
            ...unlockingByCredential.value,
            [credentialId]: false,
        };
    }
};

const beginEdit = (credential) => {
    editingCredentialId.value = credential.id;
    editForm.label = credential.label;
    editForm.is_visible_to_client = !!credential.is_visible_to_client;

    if (!credential.expires_at) {
        editForm.expiry_days = 0;
        return;
    }

    const now = new Date();
    const expiry = new Date(credential.expires_at);
    const days = Math.ceil((expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    editForm.expiry_days = Math.max(0, Number.isFinite(days) ? days : 0);
};

const cancelEdit = () => {
    editingCredentialId.value = null;
};

const saveEdit = async (credentialId) => {
    shareSaving.value = true;
    resetMessages();

    try {
        await axios.put(`/api/vault/${credentialId}`, {
            label: editForm.label,
            is_visible_to_client: editForm.is_visible_to_client,
            expiry_days: normalizeExpiryDays(editForm.expiry_days),
        });

        successMessage.value = 'Credential updated.';
        editingCredentialId.value = null;
        await fetchCredentials();
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Failed to update credential.';
    } finally {
        shareSaving.value = false;
    }
};

const deleteCredential = async (credentialId) => {
    if (!window.confirm('Delete this credential? Team credentials will be soft deleted; client credentials are permanent.')) {
        return;
    }

    resetMessages();

    try {
        await axios.delete(`/api/vault/${credentialId}`);
        successMessage.value = 'Credential deleted.';
        await fetchCredentials();
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Failed to delete credential.';
    }
};

const openSharePanel = async (credentialId) => {
    sharingCredentialId.value = credentialId;
    selectedShareUserIds.value = [];
    shareLoading.value = true;
    resetMessages();

    try {
        const [usersRes, sharedRes] = await Promise.all([
            axios.get(`/api/projects/${props.projectId}/users`),
            axios.get(`/api/vault/${credentialId}/shared-users`),
        ]);

        availableUsers.value = usersRes.data || [];
        sharedUsers.value = sharedRes.data || [];
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Failed to load sharing data.';
    } finally {
        shareLoading.value = false;
    }
};

const closeSharePanel = () => {
    sharingCredentialId.value = null;
    selectedShareUserIds.value = [];
    availableUsers.value = [];
    sharedUsers.value = [];
};

const shareCredential = async (credentialId) => {
    if (!selectedShareUserIds.value.length) {
        return;
    }

    shareSaving.value = true;
    resetMessages();

    try {
        await axios.post(`/api/vault/${credentialId}/share`, {
            user_ids: selectedShareUserIds.value,
        });

        successMessage.value = 'Credential shared successfully.';
        selectedShareUserIds.value = [];
        await openSharePanel(credentialId);
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Failed to share credential.';
    } finally {
        shareSaving.value = false;
    }
};

const revokeSharedUser = async (credentialId, userId) => {
    if (!window.confirm('Revoke this user access?')) {
        return;
    }

    shareSaving.value = true;
    resetMessages();

    try {
        await axios.delete(`/api/vault/${credentialId}/share/${userId}`);
        successMessage.value = 'Access revoked.';
        await openSharePanel(credentialId);
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Failed to revoke access.';
    } finally {
        shareSaving.value = false;
    }
};

const formatDate = (value) => {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
};

onMounted(async () => {
    await fetchCredentials();
});
</script>

<template>
    <div class="bg-white rounded-xl shadow-md p-6 space-y-6">
        <div>
            <h3 class="text-xl font-semibold text-gray-900">Project Vault Credentials</h3>
            <p class="text-sm text-gray-600 mt-1">
                Add credentials for this project and share access with team members.
            </p>
        </div>

        <div v-if="errorMessage" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ errorMessage }}
        </div>

        <div v-if="successMessage" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ successMessage }}
        </div>

        <div class="rounded-lg border border-gray-200 p-4 space-y-4">
            <h4 class="text-sm font-semibold text-gray-800">Add Credential</h4>
            <p class="text-xs text-gray-500">For label, enter a website URL, app name, or service name (for example: https://example.com, Slack, cPanel).</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input v-model="createForm.label" type="text" placeholder="Website URL, app name, or service name" class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <input v-model="createForm.username" type="text" placeholder="Username" class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <input v-model="createForm.password" type="text" placeholder="Password" class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <input v-model="createForm.pin" type="text" placeholder="PIN (4-10)" class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Expiry (days)</label>
                    <input v-model.number="createForm.expiry_days" type="number" min="0" max="3650" placeholder="0 = no expiry" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                    <p class="text-xs text-gray-500 mt-1">Set 0 for no expiry.</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="createForm.is_visible_to_client" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    Visible to client
                </label>
            </div>

            <div class="flex justify-end">
                <PrimaryButton :disabled="saving" @click="createCredential">
                    {{ saving ? 'Saving...' : 'Add Credential' }}
                </PrimaryButton>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b bg-gray-50">
                <h4 class="text-sm font-semibold text-gray-800">Credentials</h4>
            </div>

            <div v-if="loading" class="p-4 text-sm text-gray-500">Loading credentials...</div>
            <div v-else-if="!credentials.length" class="p-4 text-sm text-gray-500">No credentials found for this project.</div>

            <div v-else class="divide-y divide-gray-200">
                <div v-for="credential in credentials" :key="credential.id" class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-gray-900">{{ credential.label }}</p>
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                      :class="credential.source === 'team' ? 'bg-indigo-100 text-indigo-700' : 'bg-blue-100 text-blue-700'">
                                    {{ credential.source }}
                                </span>
                                <span v-if="credential.is_visible_to_client" class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">
                                    client-visible
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Created: {{ formatDate(credential.created_at) }}</p>
                            <p class="text-xs text-gray-500">Expires: {{ formatDate(credential.expires_at) }}</p>
                            <p class="text-xs text-gray-500">Last viewed: {{ formatDate(credential.last_viewed_at) }}</p>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap justify-end">
                            <input
                                v-model="unlockPinByCredential[credential.id]"
                                type="text"
                                placeholder="PIN (optional for shared team creds)"
                                class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />

                            <SecondaryButton :disabled="unlockingByCredential[credential.id]" @click="unlockCredential(credential)">
                                {{ unlockingByCredential[credential.id] ? 'Unlocking...' : 'Unlock' }}
                            </SecondaryButton>

                            <SecondaryButton @click="openSharePanel(credential.id)">Share</SecondaryButton>

                            <SecondaryButton v-if="canManage" @click="beginEdit(credential)">Edit</SecondaryButton>
                            <DangerButton v-if="canManage" @click="deleteCredential(credential.id)">Delete</DangerButton>
                        </div>
                    </div>

                    <div v-if="unlockedByCredential[credential.id]" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                        <p><span class="font-semibold">Username:</span> {{ unlockedByCredential[credential.id].username }}</p>
                        <p><span class="font-semibold">Password:</span> {{ unlockedByCredential[credential.id].password }}</p>
                        <p v-if="unlockedByCredential[credential.id].pin"><span class="font-semibold">PIN:</span> {{ unlockedByCredential[credential.id].pin }}</p>
                    </div>

                    <div v-if="editingCredentialId === credential.id" class="rounded-md border border-indigo-200 bg-indigo-50 p-3 space-y-3">
                        <p class="text-sm font-semibold text-indigo-900">Edit Credential</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <input v-model="editForm.label" type="text" class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Website URL, app name, or service name" />
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Expiry (days)</label>
                                <input v-model.number="editForm.expiry_days" type="number" min="0" max="3650" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="0 = no expiry" />
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="editForm.is_visible_to_client" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                Visible to client
                            </label>
                        </div>
                        <div class="flex justify-end gap-2">
                            <SecondaryButton @click="cancelEdit">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="shareSaving" @click="saveEdit(credential.id)">Save</PrimaryButton>
                        </div>
                    </div>

                    <div v-if="sharingCredentialId === credential.id" class="rounded-md border border-amber-200 bg-amber-50 p-3 space-y-3">
                        <p class="text-sm font-semibold text-amber-900">Share Credential</p>

                        <div v-if="shareLoading" class="text-sm text-gray-600">Loading team members...</div>

                        <template v-else>
                            <label class="block text-sm text-gray-700">Select team members</label>
                            <MultiSelectDropdown
                                v-model="selectedShareUserIds"
                                :options="shareUserOptions"
                                :is-multi="true"
                                placeholder="Select team members"
                                value-key="value"
                                label-key="label"
                                max-height="240px"
                            />

                            <div class="flex justify-end gap-2">
                                <SecondaryButton @click="closeSharePanel">Close</SecondaryButton>
                                <PrimaryButton :disabled="shareSaving || !selectedShareUserIds.length" @click="shareCredential(credential.id)">
                                    Share Access
                                </PrimaryButton>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-800 mb-2">Currently Shared With</p>
                                <div v-if="!sharedUsers.length" class="text-sm text-gray-500">No shared users.</div>
                                <div v-else class="space-y-2">
                                    <div v-for="user in sharedUsers" :key="user.id" class="flex items-center justify-between rounded-md bg-white border border-gray-200 px-3 py-2 text-sm">
                                        <span>{{ user.name }} ({{ user.email }})</span>
                                        <DangerButton v-if="canManage" @click="revokeSharedUser(credential.id, user.id)">Revoke</DangerButton>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="isManagementView" class="text-xs text-gray-500">
            Management mode: update, delete, and revoke are restricted to users with view_all_credentials.
        </p>
    </div>
</template>
