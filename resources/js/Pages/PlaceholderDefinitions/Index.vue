<script setup>
import { ref, onMounted, computed, watch, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue'; // Assuming you have this component
import { usePermissions } from '@/Directives/permissions';
import { success, error } from '@/Utils/notification'; // Assuming you have these utilities

const { canDo } = usePermissions();

// Replace props with a ref for definitions
const definitions = ref([]);
const loading = ref(true);

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedDefinition = ref(null);

const modelsAndColumns = ref([]);
const loadingModels = ref(false);

// Replace Inertia form with a reactive object
const form = reactive({
    name: '',
    description: '',
    source_model: null,
    source_attribute: null,
    is_dynamic: false,
    is_repeatable: false,
    errors: {},
    processing: false,

    // Add a reset method
    reset() {
        this.name = '';
        this.description = '';
        this.source_model = null;
        this.source_attribute = null;
        this.is_dynamic = false;
        this.is_repeatable = false;
        this.errors = {};
    },

    // Add a method to set errors
    setError(errors) {
        this.errors = errors;
    }
});

const availableModels = computed(() => {
    return modelsAndColumns.value.map(model => ({
        label: model.name,
        value: model.full_class,
    }));
});

const availableColumns = computed(() => {
    const selectedModel = modelsAndColumns.value.find(model => model.full_class === form.source_model);
    if (!selectedModel) {
        return [];
    }
    return selectedModel.columns.map(column => ({
        label: column,
        value: column,
    }));
});

const fetchModelsAndColumns = async () => {
    loadingModels.value = true;
    try {
        const response = await window.axios.get('/api/placeholder-definitions/models-and-columns');
        modelsAndColumns.value = response.data;
    } catch (err) {
        console.error('Error fetching models and columns:', err);
        error('Failed to fetch application models and columns.');
    } finally {
        loadingModels.value = false;
    }
};

const openCreateModal = () => {
    form.reset();
    showCreateModal.value = true;
};

const openEditModal = (definition) => {
    selectedDefinition.value = definition;
    form.name = definition.name;
    form.description = definition.description;
    form.source_model = definition.source_model;
    form.source_attribute = definition.source_attribute;
    form.is_dynamic = definition.is_dynamic;
    form.is_repeatable = definition.is_repeatable;
    showEditModal.value = true;
};

const openDeleteModal = (definition) => {
    selectedDefinition.value = definition;
    showDeleteModal.value = true;
};

const closeModals = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    showDeleteModal.value = false;
    form.reset();
    selectedDefinition.value = null;
};

// These functions are no longer needed as BaseFormModal handles the API calls
// We've removed saveDefinition, updateDefinition, and deleteDefinition functions

const fetchDefinitions = async () => {
    loading.value = true;
    try {
        const response = await window.axios.get('/api/placeholder-definitions');
        definitions.value = response.data;
    } catch (err) {
        console.error('Error fetching definitions:', err);
        error('Failed to fetch placeholder definitions.');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchModelsAndColumns();
    fetchDefinitions();
});

watch(() => form.is_dynamic, (newValue) => {
    if (newValue) {
        form.source_model = null;
        form.source_attribute = null;
    }
});

watch(() => form.source_model, (newValue) => {
    if (newValue) {
        form.is_dynamic = false;
    }
});
</script>

<template>
    <Head title="Placeholder Definitions" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-3xl text-gray-800 leading-tight">Placeholder Definitions</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-semibold text-gray-900">Manage Placeholders</h3>
                        <PrimaryButton v-if="canDo('manage_placeholder_definitions')" @click="openCreateModal">
                            Create New Definition
                        </PrimaryButton>
                    </div>

                    <!-- Loading state -->
                    <div v-if="loading" class="flex justify-center items-center py-12">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-500"></div>
                    </div>

                    <!-- Data loaded with results -->
                    <div v-else-if="definitions.length" class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm mt-6">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Description
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Source
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Dynamic
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="definition in definitions" :key="definition.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ definition.name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ definition.description || 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                        <span v-if="definition.source_model">
                                            {{ definition.source_model.split('\\').pop() }} -> {{ definition.source_attribute }}
                                        </span>
                                    <span v-else>N/A</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span v-if="definition.is_dynamic" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            Yes
                                        </span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            No
                                        </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <SecondaryButton @click="openEditModal(definition)">Edit</SecondaryButton>
                                        <DangerButton @click="openDeleteModal(definition)">Delete</DangerButton>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- No data state -->
                    <div v-else class="text-center text-gray-500 py-8">
                        <p>No placeholder definitions found.</p>
                        <PrimaryButton v-if="canDo('manage_placeholder_definitions')" @click="openCreateModal" class="mt-4">
                            Create First Definition
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Definition Modal -->
        <BaseFormModal
            :show="showCreateModal"
            title="Create New Placeholder Definition"
            api-endpoint="/api/placeholder-definitions"
            http-method="post"
            :form-data="form"
            submit-button-text="Save Definition"
            success-message="Placeholder definition created successfully!"
            @close="closeModals"
            @submitted="fetchDefinitions"
        >
            <template #default="{ errors }">
                <div class="space-y-4">
                    <div>
                        <InputLabel for="create-name" value="Placeholder Name" />
                        <TextInput id="create-name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError class="mt-2" :message="errors.name ? errors.name[0] : ''" />
                    </div>
                    <div>
                        <InputLabel for="create-description" value="Description" />
                        <textarea
                            id="create-description"
                            v-model="form.description"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full"
                        ></textarea>
                        <InputError class="mt-2" :message="errors.description ? errors.description[0] : ''" />
                    </div>
                    <div>
                        <InputLabel for="create-source_model" value="Source Model" />
                        <SelectDropdown
                            id="create-source_model"
                            v-model="form.source_model"
                            :options="availableModels"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select a source model"
                            class="mt-1 block w-full"
                            :disabled="form.is_dynamic"
                        />
                        <InputError class="mt-2" :message="errors.source_model ? errors.source_model[0] : ''" />
                    </div>
                    <div>
                        <InputLabel for="create-source_attribute" value="Source Attribute (Column)" />
                        <SelectDropdown
                            id="create-source_attribute"
                            v-model="form.source_attribute"
                            :options="availableColumns"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select a column"
                            class="mt-1 block w-full"
                            :disabled="!form.source_model || form.is_dynamic"
                        />
                        <InputError class="mt-2" :message="errors.source_attribute ? errors.source_attribute[0] : ''" />
                    </div>
                    <div>
                        <label class="flex items-center">
                            <Checkbox name="create-is_dynamic" v-model:checked="form.is_dynamic" />
                            <span class="ml-2 text-sm text-gray-600">Is a dynamic variable (e.g., magic link, due date)?</span>
                        </label>
                        <InputError class="mt-2" :message="errors.is_dynamic ? errors.is_dynamic[0] : ''" />
                    </div>
                </div>
            </template>
        </BaseFormModal>

        <!-- Edit Definition Modal -->
        <BaseFormModal
            :show="showEditModal"
            title="Edit Placeholder Definition"
            :api-endpoint="selectedDefinition ? `/api/placeholder-definitions/${selectedDefinition.id}` : ''"
            http-method="put"
            :form-data="form"
            submit-button-text="Update Definition"
            success-message="Placeholder definition updated successfully!"
            @close="closeModals"
            @submitted="fetchDefinitions"
        >
            <template #default="{ errors }">
                <div class="space-y-4">
                    <div>
                        <InputLabel for="edit-name" value="Placeholder Name" />
                        <TextInput id="edit-name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError class="mt-2" :message="errors.name ? errors.name[0] : ''" />
                    </div>
                    <div>
                        <InputLabel for="edit-description" value="Description" />
                        <textarea
                            id="edit-description"
                            v-model="form.description"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full"
                        ></textarea>
                        <InputError class="mt-2" :message="errors.description ? errors.description[0] : ''" />
                    </div>
                    <div>
                        <InputLabel for="edit-source_model" value="Source Model" />
                        <SelectDropdown
                            id="edit-source_model"
                            v-model="form.source_model"
                            :options="availableModels"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select a source model"
                            class="mt-1 block w-full"
                            :disabled="form.is_dynamic"
                        />
                        <InputError class="mt-2" :message="errors.source_model ? errors.source_model[0] : ''" />
                    </div>
                    <div>
                        <InputLabel for="edit-source_attribute" value="Source Attribute (Column)" />
                        <SelectDropdown
                            id="edit-source_attribute"
                            v-model="form.source_attribute"
                            :options="availableColumns"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select a column"
                            class="mt-1 block w-full"
                            :disabled="!form.source_model || form.is_dynamic"
                        />
                        <InputError class="mt-2" :message="errors.source_attribute ? errors.source_attribute[0] : ''" />
                    </div>
                    <div>
                        <label class="flex items-center">
                            <Checkbox name="edit-is_dynamic" v-model:checked="form.is_dynamic" />
                            <span class="ml-2 text-sm text-gray-600">Is a dynamic variable (e.g., magic link, due date)?</span>
                        </label>
                        <label class="flex items-center">
                            <Checkbox name="edit-is_repeatable" v-model:checked="form.is_repeatable" />
                            <span class="ml-2 text-sm text-gray-600">Is a repeatable fields?</span>
                        </label>
                        <InputError class="mt-2" :message="errors.is_repeatable ? errors.is_dynamic[0] : ''" />
                    </div>
                </div>
            </template>
        </BaseFormModal>

        <!-- Delete Definition Confirmation Modal -->
        <BaseFormModal
            :show="showDeleteModal"
            title="Delete Placeholder Definition"
            :api-endpoint="selectedDefinition ? `/api/placeholder-definitions/${selectedDefinition.id}` : ''"
            http-method="delete"
            :form-data="{}"
            submit-button-text="Delete"
            success-message="Placeholder definition deleted successfully!"
            @close="closeModals"
            @submitted="fetchDefinitions"
        >
            <template #default>
                <div class="py-4">
                    <p class="text-gray-700">
                        Are you sure you want to delete the definition "{{ selectedDefinition?.name }}"? This action cannot be undone.
                    </p>
                </div>
            </template>
        </BaseFormModal>
    </AuthenticatedLayout>
</template>
