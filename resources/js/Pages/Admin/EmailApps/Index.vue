<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import Modal from '@/Components/Modal.vue';
import RightSidebar from '@/Components/RightSidebar.vue';

const props = defineProps({
    apps: Array,
    templates: Array,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const appToDelete = ref(null);
const showLogsSidebar = ref(false);
const logsLoading = ref(false);
const selectedAppForLogs = ref(null);
const appLogs = ref([]);
const appLogsPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

const form = ref(defaultForm());

function defaultForm() {
    return {
        id: null,
        name: '',
        slug: '',
        description: '',
        is_active: true,
        delivery_mode: 'smtp',
        hourly_send_limit: 100,
        smtp_host: '',
        smtp_port: 587,
        smtp_username: '',
        smtp_password: '',
        smtp_encryption: 'tls',
        smtp_from_address: '',
        smtp_from_name: '',
        smtp_reply_to: '',
        api_provider: '',
        api_base_url: '',
        api_key: '',
        api_secret: '',
        template_ids: [],
    };
}

function openCreateModal() {
    form.value = defaultForm();
    showCreateModal.value = true;
}

function openEditModal(app) {
    form.value = {
        id: app.id,
        name: app.name,
        slug: app.slug || '',
        description: app.description || '',
        is_active: !!app.is_active,
        delivery_mode: app.delivery_mode || 'smtp',
        hourly_send_limit: app.hourly_send_limit || 100,
        smtp_host: app.smtp_host || '',
        smtp_port: app.smtp_port || 587,
        smtp_username: app.smtp_username || '',
        smtp_password: '',
        smtp_encryption: app.smtp_encryption || 'tls',
        smtp_from_address: app.smtp_from_address || '',
        smtp_from_name: app.smtp_from_name || '',
        smtp_reply_to: app.smtp_reply_to || '',
        api_provider: app.api_provider || '',
        api_base_url: app.api_base_url || '',
        api_key: '',
        api_secret: '',
        template_ids: [...(app.template_ids || [])],
    };

    showEditModal.value = true;
}

function closeModal() {
    showCreateModal.value = false;
    showEditModal.value = false;
    form.value = defaultForm();
}

function handleSuccess() {
    closeModal();
    router.reload({ preserveScroll: true });
}

function confirmDelete(app) {
    appToDelete.value = app;
    showDeleteModal.value = true;
}

function deleteApp() {
    window.axios.delete(route('api.email-apps.destroy', appToDelete.value.id))
        .then(() => {
            showDeleteModal.value = false;
            appToDelete.value = null;
            router.reload({ preserveScroll: true });
        });
}

function toggleTemplate(templateId) {
    if (form.value.template_ids.includes(templateId)) {
        form.value.template_ids = form.value.template_ids.filter((id) => id !== templateId);
        return;
    }

    form.value.template_ids.push(templateId);
}

function openLogsSidebar(app) {
    selectedAppForLogs.value = app;
    showLogsSidebar.value = true;
    fetchAppLogs(1);
}

function closeLogsSidebar() {
    showLogsSidebar.value = false;
    selectedAppForLogs.value = null;
    appLogs.value = [];
    appLogsPagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    };
}

function fetchAppLogs(page = 1) {
    if (!selectedAppForLogs.value) {
        return;
    }

    logsLoading.value = true;

    window.axios.get(route('api.email-apps.logs', selectedAppForLogs.value.id), {
        params: {
            page,
            per_page: appLogsPagination.value.per_page,
        },
    }).then(({ data }) => {
        appLogs.value = data.data || [];
        appLogsPagination.value = {
            current_page: data.current_page || 1,
            last_page: data.last_page || 1,
            per_page: data.per_page || 20,
            total: data.total || 0,
        };
    }).finally(() => {
        logsLoading.value = false;
    });
}

function statusBadgeClass(status) {
    if (status === 'sent') return 'bg-emerald-50 text-emerald-700 border border-emerald-100';
    if (status === 'queued') return 'bg-blue-50 text-blue-700 border border-blue-100';
    if (status === 'failed') return 'bg-red-50 text-red-700 border border-red-100';
    if (status === 'rejected' || status === 'not_supported') return 'bg-amber-50 text-amber-700 border border-amber-100';

    return 'bg-gray-50 text-gray-700 border border-gray-100';
}
</script>

<template>
    <Head title="Email Apps" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Email Apps</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Manage Email Apps</h3>
                            <p class="text-sm text-gray-500">Configure app-level SMTP/API settings and phase-2 template linkage.</p>
                        </div>
                        <PrimaryButton @click="openCreateModal">
                            <span class="mr-1">+</span> Create Email App
                        </PrimaryButton>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">App</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Mode</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Hourly Limit</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Linked Templates</th>
                                    <th class="px-6 py-3 bg-gray-50 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                                <tr v-for="app in apps" :key="app.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900">{{ app.name }}</div>
                                        <div class="text-xs text-gray-500">{{ app.slug }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium"
                                            :class="app.delivery_mode === 'smtp' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'bg-amber-50 text-amber-700 border border-amber-100'">
                                            {{ app.delivery_mode.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium"
                                            :class="app.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100'">
                                            {{ app.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 font-medium">{{ app.hourly_send_limit || 100 }}/hour</div>
                                        <div class="text-xs text-gray-500">{{ app.external_email_logs_count || 0 }} total logs</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div v-if="app.templates?.length" class="flex flex-wrap gap-1.5">
                                            <span v-for="template in app.templates" :key="template.id" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                {{ template.name }}
                                            </span>
                                        </div>
                                        <span v-else class="text-gray-400 italic">None linked</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end space-x-2">
                                            <SecondaryButton title="Logs" @click="openLogsSidebar(app)">
                                                Logs
                                            </SecondaryButton>
                                            <SecondaryButton title="Edit" @click="openEditModal(app)">
                                                Edit
                                            </SecondaryButton>
                                            <DangerButton title="Delete" @click="confirmDelete(app)">
                                                Delete
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="apps.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">
                                        No email apps created yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <BaseFormModal
            :show="showCreateModal"
            title="Create Email App"
            :api-endpoint="route('api.email-apps.store')"
            http-method="post"
            :form-data="form"
            submit-button-text="Create"
            @close="closeModal"
            @submitted="handleSuccess"
        >
            <template #default="{ errors }">
                <div class="space-y-4">
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                        <InputError :message="errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="slug" value="Slug (optional)" />
                        <TextInput id="slug" v-model="form.slug" type="text" class="mt-1 block w-full" />
                        <InputError :message="errors.slug" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="description" value="Description" />
                        <TextInput id="description" v-model="form.description" type="text" class="mt-1 block w-full" />
                        <InputError :message="errors.description" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="delivery_mode" value="Delivery Mode" />
                            <select id="delivery_mode" v-model="form.delivery_mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="smtp">SMTP</option>
                                <option value="api">API (Phase 2)</option>
                            </select>
                            <InputError :message="errors.delivery_mode" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="hourly_send_limit" value="Hourly Send Limit" />
                            <TextInput id="hourly_send_limit" v-model="form.hourly_send_limit" type="number" min="1" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-gray-500">Maximum emails queued for processing per hour for this app.</p>
                            <InputError :message="errors.hourly_send_limit" class="mt-1" />
                        </div>
                        <div class="flex items-center mt-6">
                            <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <label for="is_active" class="ml-2 text-sm text-gray-700">Active</label>
                        </div>
                    </div>

                    <div v-if="form.delivery_mode === 'smtp'" class="space-y-4 border rounded-lg p-4 bg-gray-50">
                        <h4 class="font-semibold text-sm text-gray-800">SMTP Settings</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="smtp_host" value="Host" />
                                <TextInput id="smtp_host" v-model="form.smtp_host" type="text" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_host" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="smtp_port" value="Port" />
                                <TextInput id="smtp_port" v-model="form.smtp_port" type="number" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_port" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="smtp_username" value="Username" />
                                <TextInput id="smtp_username" v-model="form.smtp_username" type="text" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_username" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="smtp_password" value="Password" />
                                <TextInput id="smtp_password" v-model="form.smtp_password" type="password" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_password" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="smtp_encryption" value="Encryption" />
                                <select id="smtp_encryption" v-model="form.smtp_encryption" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="starttls">STARTTLS</option>
                                </select>
                                <InputError :message="errors.smtp_encryption" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="smtp_from_address" value="From Email" />
                                <TextInput id="smtp_from_address" v-model="form.smtp_from_address" type="email" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_from_address" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="smtp_from_name" value="From Name" />
                                <TextInput id="smtp_from_name" v-model="form.smtp_from_name" type="text" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_from_name" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="smtp_reply_to" value="Reply-To" />
                                <TextInput id="smtp_reply_to" v-model="form.smtp_reply_to" type="email" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_reply_to" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-4 border rounded-lg p-4 bg-gray-50">
                        <h4 class="font-semibold text-sm text-gray-800">API Provider Settings (Phase 2)</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="api_provider" value="Provider" />
                                <TextInput id="api_provider" v-model="form.api_provider" type="text" class="mt-1 block w-full" placeholder="sendgrid, mailgun, etc." />
                                <InputError :message="errors.api_provider" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="api_base_url" value="Base URL" />
                                <TextInput id="api_base_url" v-model="form.api_base_url" type="url" class="mt-1 block w-full" />
                                <InputError :message="errors.api_base_url" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="api_key" value="API Key" />
                                <TextInput id="api_key" v-model="form.api_key" type="password" class="mt-1 block w-full" />
                                <InputError :message="errors.api_key" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="api_secret" value="API Secret" />
                                <TextInput id="api_secret" v-model="form.api_secret" type="password" class="mt-1 block w-full" />
                                <InputError :message="errors.api_secret" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-lg p-4">
                        <h4 class="font-semibold text-sm text-gray-800 mb-2">Linked Templates (Phase 2 Ready)</h4>
                        <div class="max-h-40 overflow-auto space-y-2">
                            <label v-for="template in templates" :key="template.id" class="flex items-center text-sm text-gray-700">
                                <input type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" :checked="form.template_ids.includes(template.id)" @change="toggleTemplate(template.id)" />
                                <span class="ml-2">{{ template.name }}</span>
                            </label>
                        </div>
                        <InputError :message="errors.template_ids" class="mt-1" />
                    </div>
                </div>
            </template>
        </BaseFormModal>

        <BaseFormModal
            v-if="showEditModal"
            :show="showEditModal"
            title="Edit Email App"
            :api-endpoint="route('api.email-apps.update', form.id)"
            http-method="put"
            :form-data="form"
            submit-button-text="Update"
            @close="closeModal"
            @submitted="handleSuccess"
        >
            <template #default="{ errors }">
                <div class="space-y-4">
                    <div>
                        <InputLabel for="edit_name" value="Name" />
                        <TextInput id="edit_name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                        <InputError :message="errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="edit_slug" value="Slug (optional)" />
                        <TextInput id="edit_slug" v-model="form.slug" type="text" class="mt-1 block w-full" />
                        <InputError :message="errors.slug" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="edit_description" value="Description" />
                        <TextInput id="edit_description" v-model="form.description" type="text" class="mt-1 block w-full" />
                        <InputError :message="errors.description" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="edit_delivery_mode" value="Delivery Mode" />
                            <select id="edit_delivery_mode" v-model="form.delivery_mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="smtp">SMTP</option>
                                <option value="api">API (Phase 2)</option>
                            </select>
                            <InputError :message="errors.delivery_mode" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="edit_hourly_send_limit" value="Hourly Send Limit" />
                            <TextInput id="edit_hourly_send_limit" v-model="form.hourly_send_limit" type="number" min="1" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-gray-500">Maximum emails queued for processing per hour for this app.</p>
                            <InputError :message="errors.hourly_send_limit" class="mt-1" />
                        </div>
                        <div class="flex items-center mt-6">
                            <input id="edit_is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <label for="edit_is_active" class="ml-2 text-sm text-gray-700">Active</label>
                        </div>
                    </div>

                    <div v-if="form.delivery_mode === 'smtp'" class="space-y-4 border rounded-lg p-4 bg-gray-50">
                        <h4 class="font-semibold text-sm text-gray-800">SMTP Settings</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="edit_smtp_host" value="Host" />
                                <TextInput id="edit_smtp_host" v-model="form.smtp_host" type="text" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_host" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_smtp_port" value="Port" />
                                <TextInput id="edit_smtp_port" v-model="form.smtp_port" type="number" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_port" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_smtp_username" value="Username" />
                                <TextInput id="edit_smtp_username" v-model="form.smtp_username" type="text" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_username" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_smtp_password" value="Password" />
                                <TextInput id="edit_smtp_password" v-model="form.smtp_password" type="password" class="mt-1 block w-full" placeholder="Leave blank to keep current password" />
                                <InputError :message="errors.smtp_password" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_smtp_encryption" value="Encryption" />
                                <select id="edit_smtp_encryption" v-model="form.smtp_encryption" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="starttls">STARTTLS</option>
                                </select>
                                <InputError :message="errors.smtp_encryption" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_smtp_from_address" value="From Email" />
                                <TextInput id="edit_smtp_from_address" v-model="form.smtp_from_address" type="email" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_from_address" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_smtp_from_name" value="From Name" />
                                <TextInput id="edit_smtp_from_name" v-model="form.smtp_from_name" type="text" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_from_name" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_smtp_reply_to" value="Reply-To" />
                                <TextInput id="edit_smtp_reply_to" v-model="form.smtp_reply_to" type="email" class="mt-1 block w-full" />
                                <InputError :message="errors.smtp_reply_to" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-4 border rounded-lg p-4 bg-gray-50">
                        <h4 class="font-semibold text-sm text-gray-800">API Provider Settings (Phase 2)</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="edit_api_provider" value="Provider" />
                                <TextInput id="edit_api_provider" v-model="form.api_provider" type="text" class="mt-1 block w-full" placeholder="sendgrid, mailgun, etc." />
                                <InputError :message="errors.api_provider" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_api_base_url" value="Base URL" />
                                <TextInput id="edit_api_base_url" v-model="form.api_base_url" type="url" class="mt-1 block w-full" />
                                <InputError :message="errors.api_base_url" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_api_key" value="API Key" />
                                <TextInput id="edit_api_key" v-model="form.api_key" type="password" class="mt-1 block w-full" placeholder="Leave blank to keep current key" />
                                <InputError :message="errors.api_key" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_api_secret" value="API Secret" />
                                <TextInput id="edit_api_secret" v-model="form.api_secret" type="password" class="mt-1 block w-full" placeholder="Leave blank to keep current secret" />
                                <InputError :message="errors.api_secret" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-lg p-4">
                        <h4 class="font-semibold text-sm text-gray-800 mb-2">Linked Templates (Phase 2 Ready)</h4>
                        <div class="max-h-40 overflow-auto space-y-2">
                            <label v-for="template in templates" :key="template.id" class="flex items-center text-sm text-gray-700">
                                <input type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" :checked="form.template_ids.includes(template.id)" @change="toggleTemplate(template.id)" />
                                <span class="ml-2">{{ template.name }}</span>
                            </label>
                        </div>
                        <InputError :message="errors.template_ids" class="mt-1" />
                    </div>
                </div>
            </template>
        </BaseFormModal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-red-600">Delete Email App</h3>
                <p class="text-sm text-gray-600 mt-2">
                    Delete <span class="font-semibold text-gray-800">{{ appToDelete?.name }}</span>? Linked external tokens will be unlinked.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton @click="deleteApp">Delete</DangerButton>
                </div>
            </div>
        </Modal>

        <RightSidebar
            :show="showLogsSidebar"
            :title="selectedAppForLogs ? `${selectedAppForLogs.name} Logs` : 'Email Logs'"
            :initial-width="50"
            @update:show="(value) => { if (!value) closeLogsSidebar(); }"
            @close="closeLogsSidebar"
        >
            <template #content>
                <div class="space-y-4">
                    <div v-if="selectedAppForLogs" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="text-sm text-gray-700">
                            <span class="font-semibold text-gray-900">App:</span> {{ selectedAppForLogs.name }}
                        </div>
                        <div class="text-sm text-gray-700 mt-1">
                            <span class="font-semibold text-gray-900">Hourly limit:</span> {{ selectedAppForLogs.hourly_send_limit || 100 }}/hour
                        </div>
                    </div>

                    <div v-if="logsLoading" class="text-sm text-gray-500">Loading logs...</div>

                    <div v-else-if="appLogs.length === 0" class="text-sm text-gray-500 italic">No logs found for this app.</div>

                    <div v-else class="space-y-3">
                        <div v-for="log in appLogs" :key="log.id" class="border border-gray-200 rounded-lg p-4 bg-white">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">{{ log.subject || '(No subject)' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">To: {{ log.to_email }}</div>
                                </div>
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(log.status)">
                                    {{ log.status }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mt-3 text-xs text-gray-600">
                                <div>
                                    <span class="font-semibold text-gray-700">Created:</span>
                                    {{ log.created_at || '-' }}
                                </div>
                                <div>
                                    <span class="font-semibold text-gray-700">Attempted:</span>
                                    {{ log.attempted_at || '-' }}
                                </div>
                                <div>
                                    <span class="font-semibold text-gray-700">Sent:</span>
                                    {{ log.sent_at || '-' }}
                                </div>
                            </div>

                            <p v-if="log.error_message" class="mt-2 text-xs text-red-600 break-words">
                                {{ log.error_message }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="flex items-center justify-between">
                    <div class="text-xs text-gray-500">
                        Page {{ appLogsPagination.current_page }} of {{ appLogsPagination.last_page }}
                        ({{ appLogsPagination.total }} logs)
                    </div>
                    <div class="flex items-center gap-2">
                        <SecondaryButton
                            :disabled="logsLoading || appLogsPagination.current_page <= 1"
                            @click="fetchAppLogs(appLogsPagination.current_page - 1)"
                        >
                            Previous
                        </SecondaryButton>
                        <SecondaryButton
                            :disabled="logsLoading || appLogsPagination.current_page >= appLogsPagination.last_page"
                            @click="fetchAppLogs(appLogsPagination.current_page + 1)"
                        >
                            Next
                        </SecondaryButton>
                    </div>
                </div>
            </template>
        </RightSidebar>
    </AuthenticatedLayout>
</template>
