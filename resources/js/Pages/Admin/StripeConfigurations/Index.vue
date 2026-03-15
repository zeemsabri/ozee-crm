<template>
    <Head title="Stripe Configurations" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Stripe Configurations
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900">Registered Apps</h3>
                        <PrimaryButton @click="openCreateModal">
                            Add New Configuration
                        </PrimaryButton>
                    </div>

                    <div v-if="configurations.length === 0" class="text-center py-4 text-gray-500">
                        No Stripe configurations found.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">App Name / ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Public Key</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="config in configurations" :key="config.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ config.app_name }}</div>
                                        <div class="text-sm text-gray-500">{{ config.app_id }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500">{{ config.stripe_public_key }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ new Date(config.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="openEditModal(config)" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                        <button @click="confirmDeletion(config)" class="text-red-600 hover:text-red-900">Delete</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    {{ isEditing ? 'Edit Configuration' : 'Add New Stripe Configuration' }}
                </h2>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="app_name" value="App Name" />
                        <TextInput
                            id="app_name"
                            v-model="form.app_name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g. My Awesome Shop"
                        />
                        <InputError :message="form.errors.app_name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="app_id" value="App ID (Unique)" />
                        <TextInput
                            id="app_id"
                            v-model="form.app_id"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g. awesome-shop-prod"
                        />
                        <InputError :message="form.errors.app_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="stripe_public_key" value="Stripe Public Key" />
                        <TextInput
                            id="stripe_public_key"
                            v-model="form.stripe_public_key"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="pk_test_..."
                        />
                        <InputError :message="form.errors.stripe_public_key" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="stripe_secret_key" value="Stripe Secret Key" />
                        <TextInput
                            id="stripe_secret_key"
                            v-model="form.stripe_secret_key"
                            type="password"
                            class="mt-1 block w-full"
                            :placeholder="isEditing ? 'Leave blank to keep unchanged' : 'sk_test_...'"
                        />
                        <InputError :message="form.errors.stripe_secret_key" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="settings" value="Additional Settings & Webhook Logic (JSON)" />
                        <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md text-xs text-blue-800 mb-2">
                            <p class="font-bold mb-1">💡 Webhook Automation Tip:</p>
                            <p>You can define what happens after a successful payment here. Example:</p>
                            <pre class="mt-1 bg-white p-2 rounded border overflow-x-auto">{
  "webhook_secret": "whsec_...",
  "webhook_logic": [
    {
      "event": "checkout.session.completed",
      "actions": [
        {
          "type": "update_model",
          "model": "App\\Models\\Enrollment",
          "query_field": "id",
          "metadata_field": "enrollment_ids",
          "update": { "status": "paid" }
        }
      ]
    }
  ]
}</pre>
                            <p class="mt-2 font-bold">🤖 AI Prompt for Webhook Logic:</p>
                            <p class="italic text-blue-700 select-all">"Create a JSON config for Stripe webhook: when checkout.session.completed happens, update the 'App\Models\YourModel' records whose IDs are in 'metadata.your_id_field' setting 'status' to 'paid'."</p>
                        </div>
                        <textarea
                            id="settings"
                            v-model="settingsRaw"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm font-mono text-xs"
                            rows="8"
                            placeholder='{ "webhook_logic": [...] }'
                        ></textarea>
                        <InputError :message="form.errors.settings" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                    <PrimaryButton
                        class="ml-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ isEditing ? 'Update' : 'Create' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Are you sure you want to delete this configuration?</h2>
                <p class="mt-1 text-sm text-gray-600">This action cannot be undone.</p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="confirmingDeletion = false">Cancel</SecondaryButton>
                    <DangerButton
                        class="ml-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteConfig"
                    >
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    configurations: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDeletion = ref(false);
const configToDelete = ref(null);
const settingsRaw = ref('');

const form = useForm({
    app_name: '',
    app_id: '',
    stripe_public_key: '',
    stripe_secret_key: '',
    settings: null,
});

watch(settingsRaw, (val) => {
    try {
        form.settings = val ? JSON.parse(val) : null;
    } catch (e) {
        form.settings = null;
    }
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    settingsRaw.value = '';
    showModal.value = true;
};

const openEditModal = (config) => {
    isEditing.value = true;
    editingId.value = config.id;
    form.reset();
    form.app_name = config.app_name;
    form.app_id = config.app_id;
    form.stripe_public_key = config.stripe_public_key;
    form.stripe_secret_key = ''; // Don't show existing secret key
    settingsRaw.value = config.settings ? JSON.stringify(config.settings, null, 2) : '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.stripe-configurations.update', editingId.value), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('admin.stripe-configurations.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const confirmDeletion = (config) => {
    configToDelete.value = config;
    confirmingDeletion.value = true;
};

const deleteConfig = () => {
    form.delete(route('admin.stripe-configurations.destroy', configToDelete.value.id), {
        onSuccess: () => (confirmingDeletion.value = false),
    });
};
</script>
