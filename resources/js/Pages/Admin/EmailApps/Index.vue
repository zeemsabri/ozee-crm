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

const props = defineProps({
    apps: Array,
    templates: Array,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const appToDelete = ref(null);

const form = ref(defaultForm());

function defaultForm() {
    return {
        id: null,
        name: '',
        slug: '',
        description: '',
        is_active: true,
        delivery_mode: 'smtp',
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
    window.axios.delete(route('admin.email-apps.destroy', appToDelete.value.id))
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
                                        <div v-if="app.templates?.length" class="flex flex-wrap gap-1.5">
                                            <span v-for="template in app.templates" :key="template.id" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                {{ template.name }}
                                            </span>
                                        </div>
                                        <span v-else class="text-gray-400 italic">None linked</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end space-x-2">
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
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500 italic">
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
            :api-endpoint="route('admin.email-apps.store')"
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
            :api-endpoint="route('admin.email-apps.update', { emailApp: form.id })"
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
    </AuthenticatedLayout>
</template>
