<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import { success, error } from '@/Utils/notification';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TextareaInput from '@/Components/TextareaInput.vue';

const props = defineProps({
    projectId: {
        type: [Number, String],
        required: true
    },
    errors: {
        type: Object,
        default: () => ({})
    },
    isSaving: {
        type: Boolean,
        default: false
    },
    canManageProjectDeliverables: {
        type: Boolean,
        default: false
    },
    canViewProjectDeliverables: {
        type: Boolean,
        default: true
    }
});

// State for deliverables and deliverable types
const deliverables = ref([]);
const deliverableTypes = ref([]); // New state for dynamic types
const isLoading = ref(true);
const milestones = ref([]);

// Form state
const showForm = ref(false);
const formMode = ref('create'); // 'create' or 'edit'
const currentDeliverable = ref({
    id: null,
    name: '',
    description: '',
    milestone_id: null,
    status: 'pending',
    due_date: '',
    details: {}
});
const formErrors = ref({});
const dynamicInputStrings = ref({});

// Status options
const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' }
];

// Computed property for the fields of the currently selected deliverable type
const dynamicFields = computed(() => {
    const selectedType = deliverableTypes.value.find(type => type.id === currentDeliverable.value.details?.deliverable_type_id);
    return selectedType?.fields || [];
});

// Load deliverables, milestones, and deliverable types when component mounts or projectId changes
watch(() => props.projectId, fetchData, { immediate: true });

async function fetchData() {
    if (!props.projectId) return;

    isLoading.value = true;
    try {
        // Fetch project deliverables
        const deliverablesResponse = await axios.get(`/api/projects/${props.projectId}/project-deliverables`);
        deliverables.value = deliverablesResponse.data;

        // Fetch milestones for the dropdown
        const milestonesResponse = await axios.get(`/api/projects/${props.projectId}/milestones`);
        milestones.value = milestonesResponse.data;

        // Fetch the user-defined deliverable types
        const deliverableTypesResponse = await axios.get(`/api/project-deliverable-types`);
        deliverableTypes.value = deliverableTypesResponse.data;

    } catch (err) {
        console.error('Error fetching data:', err);
        error('Failed to load project data');
    } finally {
        isLoading.value = false;
    }
}

function openCreateForm() {
    formMode.value = 'create';
    currentDeliverable.value = {
        id: null,
        name: '',
        description: '',
        milestone_id: null,
        status: 'pending',
        due_date: '',
        details: {
            deliverable_type_id: null,
        }
    };
    formErrors.value = {};
    showForm.value = true;
    // Initialize local state for dynamic inputs
    dynamicInputStrings.value = {};
}

function openEditForm(deliverable) {
    formMode.value = 'edit';
    // Deep copy to ensure changes in the modal don't affect the list until saved
    currentDeliverable.value = { ...deliverable, details: { ...deliverable.details } };
    formErrors.value = {};
    showForm.value = true;

    // Initialize local state for dynamic inputs from the deliverable data
    const tempStrings = {};
    for (const field of dynamicFields.value) {
        if (field.type === 'array-text' && currentDeliverable.value.details[field.key]) {
            tempStrings[field.key] = currentDeliverable.value.details[field.key].join(', ');
        }
    }
    dynamicInputStrings.value = tempStrings;
}

async function saveDeliverable() {
    formErrors.value = {};

    try {
        let response;
        // Process the dynamic input strings back into arrays before saving
        for (const field of dynamicFields.value) {
            if (field.type === 'array-text') {
                const stringValue = dynamicInputStrings.value[field.key];
                if (stringValue) {
                    currentDeliverable.value.details[field.key] = stringValue.split(',').map(s => s.trim());
                } else {
                    currentDeliverable.value.details[field.key] = [];
                }
            }
        }

        const payload = {
            ...currentDeliverable.value,
            // Convert details object to JSON string for backend
            details: JSON.stringify(currentDeliverable.value.details)
        };

        if (formMode.value === 'create') {
            response = await axios.post(`/api/projects/${props.projectId}/project-deliverables`, payload);
            success('Project deliverable created successfully');
        } else {
            response = await axios.put(`/api/project-deliverables/${currentDeliverable.value.id}`, payload);
            success('Project deliverable updated successfully');
        }

        // Refresh the list
        await fetchData();
        showForm.value = false;
    } catch (err) {
        console.error('Error saving deliverable:', err);
        if (err.response && err.response.data && err.response.data.errors) {
            formErrors.value = err.response.data.errors;
        } else {
            error('Failed to save project deliverable');
        }
    }
}

async function deleteDeliverable(id) {
    if (!confirm('Are you sure you want to delete this deliverable?')) return;

    try {
        await axios.delete(`/api/project-deliverables/${id}`);
        success('Project deliverable deleted successfully');
        await fetchData();
    } catch (err) {
        console.error('Error deleting deliverable:', err);
        error('Failed to delete project deliverable');
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
}
</script>

<template>
    <div class="p-6 bg-gray-50 rounded-xl shadow-lg">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-900">Project Deliverables</h3>
            <PrimaryButton
                v-if="canManageProjectDeliverables"
                @click="openCreateForm"
                :disabled="isSaving || isLoading"
            >
                Add Deliverable
            </PrimaryButton>
        </div>

        <!-- Loading state -->
        <div v-if="isLoading" class="flex justify-center py-8">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div>
        </div>

        <!-- Empty state -->
        <div v-else-if="deliverables.length === 0" class="bg-white p-8 rounded-lg text-center border border-dashed border-gray-300">
            <p class="text-gray-600 text-lg">No deliverables have been added to this project yet.</p>
            <PrimaryButton
                v-if="canManageProjectDeliverables"
                @click="openCreateForm"
                class="mt-6"
            >
                Add Your First Deliverable
            </PrimaryButton>
        </div>

        <!-- Deliverables list -->
        <div v-else class="space-y-6">
            <div v-for="deliverable in deliverables" :key="deliverable.id"
                 class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300">
                <div class="flex justify-between items-start">
                    <div>
                        <h4 class="text-xl font-medium text-gray-900">{{ deliverable.name }}</h4>
                        <div class="mt-2 flex items-center flex-wrap space-x-4 text-sm text-gray-500">
                            <span :class="{
                                'font-medium py-1 px-3 rounded-full text-white': true,
                                'bg-yellow-500': deliverable.status === 'pending',
                                'bg-blue-500': deliverable.status === 'in_progress',
                                'bg-green-500': deliverable.status === 'completed',
                                'bg-red-500': deliverable.status === 'cancelled'
                            }">
                                {{ deliverable.status.replace('_', ' ').toUpperCase() }}
                            </span>
                            <span v-if="deliverable.milestone" class="text-gray-700">
                                <span class="font-semibold">Milestone:</span> {{ deliverable.milestone.name }}
                            </span>
                            <span v-if="deliverable.due_date" class="text-gray-700">
                                <span class="font-semibold">Due:</span> {{ formatDate(deliverable.due_date) }}
                            </span>
                        </div>
                        <p v-if="deliverable.description" class="mt-4 text-base text-gray-600">
                            {{ deliverable.description }}
                        </p>
                        <!-- Dynamic Details Display -->
                        <div v-if="deliverable.details && Object.keys(deliverable.details).length > 1"
                             class="mt-4 p-3 bg-gray-100 rounded-lg text-sm text-gray-700 border border-gray-200">
                            <p class="font-semibold text-gray-900 mb-2">Deliverable Details:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li v-for="(value, key) in deliverable.details" :key="key" v-if="key !== 'deliverable_type_id'">
                                    <span class="font-medium text-gray-800">{{ key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }}:</span>
                                    <span class="ml-1 text-gray-700">
                                        {{ Array.isArray(value) ? value.join(', ') : value }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="canManageProjectDeliverables" class="flex space-x-2">
                        <SecondaryButton @click="openEditForm(deliverable)" size="sm">
                            Edit
                        </SecondaryButton>
                        <DangerButton @click="deleteDeliverable(deliverable.id)" size="sm">
                            Delete
                        </DangerButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showForm" @close="showForm = false" maxWidth="2xl">
            <div class="p-6 bg-white rounded-lg">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">
                    {{ formMode === 'create' ? 'Add New Deliverable' : 'Edit Deliverable' }}
                </h3>

                <form @submit.prevent="saveDeliverable" class="space-y-6">
                    <!-- General Fields -->
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            v-model="currentDeliverable.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="formErrors.name?.[0]" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="description" value="Description" />
                        <TextareaInput
                            id="description"
                            v-model="currentDeliverable.description"
                            class="mt-1 block w-full"
                            rows="3"
                        />
                        <InputError :message="formErrors.description?.[0]" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="milestone_id" value="Milestone (Optional)" />
                        <SelectDropdown
                            id="milestone_id"
                            v-model="currentDeliverable.milestone_id"
                            :options="milestones"
                            valueKey="id"
                            labelKey="name"
                            placeholder="Select a milestone"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="formErrors.milestone_id?.[0]" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="status" value="Status" />
                        <SelectDropdown
                            id="status"
                            v-model="currentDeliverable.status"
                            :options="statusOptions"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select a Status"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="formErrors.status?.[0]" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="due_date" value="Due Date (Optional)" />
                        <TextInput
                            id="due_date"
                            v-model="currentDeliverable.due_date"
                            type="date"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="formErrors.due_date?.[0]" class="mt-2" />
                    </div>

                    <!-- Dynamic Fields for Details JSON -->
                    <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                        <h4 class="text-lg font-semibold text-indigo-800 mb-4">Deliverable Specifics</h4>
                        <div>
                            <InputLabel for="deliverable_type_id" value="Deliverable Type" />
                            <SelectDropdown
                                id="deliverable_type_id"
                                v-model="currentDeliverable.details.deliverable_type_id"
                                :options="deliverableTypes"
                                valueKey="id"
                                labelKey="name"
                                placeholder="Select a Deliverable Type"
                                class="mt-1 block w-full"
                                required
                                @update:modelValue="currentDeliverable.details = { deliverable_type_id: $event }"
                            />
                        </div>

                        <!-- Conditionally rendered fields based on deliverable type using a v-for loop -->
                        <div v-for="field in dynamicFields" :key="field.key" class="mt-4">
                            <InputLabel :for="field.key" :value="field.label" />
                            <template v-if="field.type === 'textarea'">
                                <TextareaInput
                                    :id="field.key"
                                    v-model="currentDeliverable.details[field.key]"
                                    :placeholder="field.placeholder"
                                    rows="3"
                                />
                            </template>
                            <template v-else-if="field.type === 'array-text'">
                                <TextInput
                                    :id="field.key"
                                    v-model="dynamicInputStrings[field.key]"
                                    type="text"
                                    :placeholder="field.placeholder"
                                    class="mt-1 block w-full"
                                />
                            </template>
                            <template v-else>
                                <TextInput
                                    :id="field.key"
                                    v-model="currentDeliverable.details[field.key]"
                                    :type="field.type"
                                    :placeholder="field.placeholder"
                                    class="mt-1 block w-full"
                                />
                            </template>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3 mt-6">
                        <SecondaryButton @click="showForm = false" :disabled="isSaving">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit" :disabled="isSaving">
                            {{ formMode === 'create' ? 'Create' : 'Update' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>
