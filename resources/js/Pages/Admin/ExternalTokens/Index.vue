<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';

const props = defineProps({
    tokens: Array,
    projects: Array,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const tokenToDelete = ref(null);
const editingTokenId = ref(null);

const form = useForm({
    label: '',
    email: '',
    project_id: '',
    expires_at: '',
    max_uses: null,
    whitelist_domains: [],
    whitelist_ips: [],
});

const domainInput = ref('');
const ipInput = ref('');

const addDomain = () => {
    if (domainInput.value && !form.whitelist_domains.includes(domainInput.value)) {
        form.whitelist_domains.push(domainInput.value);
        domainInput.value = '';
    }
};

const removeDomain = (index) => {
    form.whitelist_domains.splice(index, 1);
};

const addIp = () => {
    if (ipInput.value && !form.whitelist_ips.includes(ipInput.value)) {
        form.whitelist_ips.push(ipInput.value);
        ipInput.value = '';
    }
};

const removeIp = (index) => {
    form.whitelist_ips.splice(index, 1);
};

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    editingTokenId.value = null;
    showCreateModal.value = true;
};

const openEditModal = (token) => {
    form.clearErrors();
    editingTokenId.value = token.id;
    
    form.label = token.label;
    form.email = token.email || '';
    form.project_id = token.project_id || '';
    // Format date for input[type="date"]
    form.expires_at = token.expires_at ? new Date(token.expires_at).toISOString().split('T')[0] : '';
    form.max_uses = token.max_uses;
    form.whitelist_domains = [...(token.whitelist?.domains || [])];
    form.whitelist_ips = [...(token.whitelist?.ips || [])];
    
    showEditModal.value = true;
};

const closeModal = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    editingTokenId.value = null;
    form.reset();
};

const handleSuccess = () => {
    closeModal();
    router.reload({ preserveScroll: true });
};

const confirmDeletion = (token) => {
    tokenToDelete.value = token;
    showDeleteModal.value = true;
};

const deleteToken = () => {
    form.delete(route('admin.external-tokens.destroy', tokenToDelete.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            router.reload({ preserveScroll: true });
        },
    });
};

const copyToken = (token) => {
    navigator.clipboard.writeText(token);
    alert('Token copied to clipboard');
};
</script>

<template>
    <Head title="External Tokens" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">External API Tokens</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Manage External Tokens</h3>
                            <p class="text-sm text-gray-500">Tokens for third-party systems to access external endpoints.</p>
                        </div>
                        <PrimaryButton @click="openCreateModal">
                            <span class="mr-1">+</span> Create Token
                        </PrimaryButton>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Label & Token</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Project</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Expiry</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Uses</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Whitelist</th>
                                    <th class="px-6 py-3 bg-gray-50 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                                <tr v-for="token in tokens" :key="token.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900">{{ token.label }}</div>
                                        <div class="flex items-center mt-1">
                                            <code class="text-xs text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded truncate max-w-[200px]" title="Click to copy" @click="copyToken(token.token)">{{ token.token }}</code>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 font-medium">{{ token.project?.name }}</td>
                                    <td class="px-6 py-4">
                                        <span v-if="token.expires_at" :class="new Date(token.expires_at) < new Date() ? 'text-red-500 font-bold' : 'text-gray-600'">
                                            {{ new Date(token.expires_at).toLocaleDateString() }}
                                        </span>
                                        <span v-else class="text-gray-400">Never</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="bg-gray-100 px-2 py-0.5 rounded text-gray-700">
                                            {{ token.uses_count }} / {{ token.max_uses || '∞' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <div v-if="token.whitelist?.domains?.length" class="flex flex-wrap gap-1">
                                                <span class="text-[10px] bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded border border-blue-100">Domains: {{ token.whitelist.domains.length }}</span>
                                            </div>
                                            <div v-if="token.whitelist?.ips?.length" class="flex flex-wrap gap-1">
                                                <span class="text-[10px] bg-green-50 text-green-700 px-1.5 py-0.5 rounded border border-green-100">IPs: {{ token.whitelist.ips.length }}</span>
                                            </div>
                                            <div v-if="!token.whitelist?.domains?.length && !token.whitelist?.ips?.length" class="text-gray-400 italic">
                                                Internal only
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end space-x-2">
                                            <SecondaryButton title="Edit Settings" @click="openEditModal(token)">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </SecondaryButton>
                                            <SecondaryButton title="Copy Token" @click="copyToken(token.token)">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </SecondaryButton>
                                            <DangerButton title="Delete" @click="confirmDeletion(token)">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="tokens.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">
                                        No external API tokens have been created yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <BaseFormModal
            :show="showCreateModal || showEditModal"
            :title="editingTokenId ? 'Edit External Token' : 'Create External Token'"
            :api-endpoint="editingTokenId ? route('admin.external-tokens.update', editingTokenId) : route('admin.external-tokens.store')"
            :http-method="editingTokenId ? 'patch' : 'post'"
            :form-data="form"
            :submit-button-text="editingTokenId ? 'Update Settings' : 'Generate Token'"
            @close="closeModal"
            @submitted="handleSuccess"
        >
            <template #default="{ errors }">
                <div class="space-y-5">
                    <div>
                        <InputLabel for="label" value="Token Label" />
                        <TextInput id="label" v-model="form.label" type="text" class="mt-1 block w-full" placeholder="e.g. Stripe Webhook Integration" required />
                        <p class="text-[11px] text-gray-500 mt-1">A descriptive name to identify this token's purpose.</p>
                        <InputError :message="errors.label" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Identifier Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" placeholder="system@example.com" />
                        <p class="text-[11px] text-gray-500 mt-1">Used to associate activities with a specific entity.</p>
                        <InputError :message="errors.email" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="project_id" value="Associated Project" />
                        <select id="project_id" v-model="form.project_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">None (Global Access)</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                        <p class="text-[11px] text-gray-500 mt-1">The project data this token will have access to.</p>
                        <InputError :message="errors.project_id" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="expires_at" value="Expiry Date" />
                            <TextInput id="expires_at" v-model="form.expires_at" type="date" class="mt-1 block w-full" />
                            <InputError :message="errors.expires_at" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="max_uses" value="Usage Limit" />
                            <TextInput id="max_uses" v-model="form.max_uses" type="number" class="mt-1 block w-full" placeholder="Unlimited" />
                            <InputError :message="errors.max_uses" class="mt-1" />
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <InputLabel value="Whitelisted Domains" />
                        <div class="flex mt-1">
                            <TextInput v-model="domainInput" type="text" class="block w-full text-sm" placeholder="e.g. api.stripe.com" @keyup.enter.prevent="addDomain" />
                            <SecondaryButton type="button" class="ml-2 !py-2" @click="addDomain">Add</SecondaryButton>
                        </div>
                        <div class="flex flex-wrap gap-1.5 mt-2.5">
                            <span v-for="(domain, index) in form.whitelist_domains" :key="index" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                {{ domain }}
                                <button type="button" class="ml-1.5 text-indigo-400 hover:text-indigo-600 focus:outline-none" @click="removeDomain(index)">&times;</button>
                            </span>
                            <span v-if="form.whitelist_domains.length === 0" class="text-[11px] text-gray-400 italic">No domains whitelisted</span>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <InputLabel value="Whitelisted IP Addresses" />
                        <div class="flex mt-1">
                            <TextInput v-model="ipInput" type="text" class="block w-full text-sm" placeholder="e.g. 54.187.174.169" @keyup.enter.prevent="addIp" />
                            <SecondaryButton type="button" class="ml-2 !py-2" @click="addIp">Add</SecondaryButton>
                        </div>
                        <div class="flex flex-wrap gap-1.5 mt-2.5">
                            <span v-for="(ip, index) in form.whitelist_ips" :key="index" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                                {{ ip }}
                                <button type="button" class="ml-1.5 text-emerald-400 hover:text-emerald-600 focus:outline-none" @click="removeIp(index)">&times;</button>
                            </span>
                            <span v-if="form.whitelist_ips.length === 0" class="text-[11px] text-gray-400 italic">No IPs whitelisted</span>
                        </div>
                    </div>
                </div>
            </template>
        </BaseFormModal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <div class="flex items-center text-red-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="text-lg font-bold">Revoke Token</h3>
                </div>
                <p class="text-sm text-gray-600">
                    Are you sure you want to delete the token <span class="font-bold text-gray-800">{{ tokenToDelete?.label }}</span>?
                </p>
                <p class="mt-2 text-xs text-red-500 font-medium">Any external systems currently using this token will lose access immediately.</p>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="deleteToken">
                        Revoke Access
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
