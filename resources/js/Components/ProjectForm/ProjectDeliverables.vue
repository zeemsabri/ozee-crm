<script setup>
import { ref, onMounted, watch, computed, nextTick } from 'vue';
import axios from 'axios';
import { success, error, confirmPrompt } from '@/Utils/notification';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import ChecklistComponent from '@/Components/ChecklistComponent.vue';
import { TrashIcon } from '@heroicons/vue/20/solid';
import ChecklistCreator from '@/Components/ChecklistCreator.vue';

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

const emit = defineEmits(['checklist-item-toggled']);

// State for deliverables and deliverable types
const deliverables = ref([]);
const deliverableTypes = ref([]);
const isLoading = ref(true);
const milestones = ref([]);

// Form state
const showForm = ref(false);
const formMode = ref('create');
const currentDeliverable = ref({
    id: null,
    name: '',
    description: '',
    milestone_id: null,
    status: 'pending',
    due_date: '',
    details: {
        deliverable_type_key: null,
        checklist: [],
    }
});
const formErrors = ref({});
const previousTypeKey = ref(null);

// Status options
const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' }
];

// Computed property for the placeholder of the currently selected deliverable type
const checklistPlaceholder = computed(() => {
    const selectedType = deliverableTypes.value.find(type => type.key === currentDeliverable.value.details?.deliverable_type_key);
    return selectedType?.checklistItemPlaceholder || 'e.g., Task 1, Task 2, Task 3';
});

// Load deliverables, milestones, and deliverable types when component mounts or projectId changes
watch(() => props.projectId, fetchData, { immediate: true });

async function fetchData() {
    if (!props.projectId) return;

    isLoading.value = true;
    try {
        const deliverablesResponse = await axios.get(`/api/projects/${props.projectId}/project-deliverables`);
        deliverables.value = deliverablesResponse.data;

        const milestonesResponse = await axios.get(`/api/projects/${props.projectId}/milestones`);
        milestones.value = milestonesResponse.data;

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
            deliverable_type_key: null,
            checklist: [{ name: '', completed: false }], // Always start with one empty item
        }
    };
    formErrors.value = {};
    previousTypeKey.value = null;
    showForm.value = true;
}

function openEditForm(deliverable) {
    formMode.value = 'edit';
    currentDeliverable.value = JSON.parse(JSON.stringify(deliverable));
    if (!currentDeliverable.value.details.checklist) {
        currentDeliverable.value.details.checklist = [];
    }
    // Ensure there is always an empty input field for new items
    if (currentDeliverable.value.details.checklist.length === 0 || currentDeliverable.value.details.checklist[currentDeliverable.value.details.checklist.length - 1].name !== '') {
        currentDeliverable.value.details.checklist.push({ name: '', completed: false });
    }
    formErrors.value = {};
    previousTypeKey.value = currentDeliverable.value.details.deliverable_type_key ?? null;
    showForm.value = true;
}

async function handleDeliverableTypeChange(newKey) {
    // Prevent loop if we programmatically revert the value
    if (newKey === previousTypeKey.value) {
        return;
    }

    const list = currentDeliverable.value.details?.checklist || [];
    const hasMeaningfulItems = list.some(item => (item?.name || '').trim() !== '');

    if (hasMeaningfulItems) {
        const confirmReset = await confirmPrompt(
            'Changing the deliverable type may clear your existing checklist items. Do you want to proceed?',
            { confirmText: 'Proceed', cancelText: 'Cancel', type: 'warning' }
        );
        if (!confirmReset) {
            // Revert selection
            currentDeliverable.value.details.deliverable_type_key = previousTypeKey.value;
            return;
        }
        // User confirmed: clear checklist to a single empty item
        currentDeliverable.value.details.checklist = [{ name: '', completed: false }];
    }

    // Update the previous key to the new one after a successful change
    previousTypeKey.value = newKey;
}

// Checklist item management is now handled by the ChecklistCreator component

async function saveDeliverable() {
    formErrors.value = {};

    try {
        let response;

        // Filter out any empty checklist items before sending
        const filteredChecklist = currentDeliverable.value.details.checklist.filter(item => item.name.trim() !== '');

        const payload = {
            ...currentDeliverable.value,
            details: {
                ...currentDeliverable.value.details,
                checklist: filteredChecklist
            }
        };

        if (formMode.value === 'create') {
            response = await axios.post(`/api/projects/${props.projectId}/project-deliverables`, payload);
            success('Project deliverable created successfully');
        } else {
            response = await axios.put(`/api/project-deliverables/${currentDeliverable.value.id}`, payload);
            success('Project deliverable updated successfully');
        }

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

        <div v-if="isLoading" class="flex justify-center py-8">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div>
        </div>

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
                        <ChecklistComponent
                            v-if="deliverable.details?.checklist && deliverable.details.checklist.length > 0"
                            :items="deliverable.details.checklist"
                            :api-endpoint="`/api/project-deliverables/${deliverable.id}`"
                            title="Deliverable Checklist:"
                            :payload-transformer="(items, index) => ({
                                ...deliverable,
                                details: {
                                    ...deliverable.details,
                                    checklist: items
                                }
                            })"
                            @item-toggled="(data) => $emit('checklist-item-toggled', {
                                deliverable,
                                index: data.index,
                                completed: data.completed
                            })"
                        />
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

        <Modal :show="showForm" @close="showForm = false" maxWidth="2xl">
            <div class="p-6 bg-white rounded-lg">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">
                    {{ formMode === 'create' ? 'Add New Deliverable' : 'Edit Deliverable' }}
                </h3>

                <form @submit.prevent="saveDeliverable" class="space-y-6">
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

                    <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                        <h4 class="text-lg font-semibold text-indigo-800 mb-4">Deliverable Checklist</h4>
                        <div>
                            <InputLabel for="deliverable_type_key" value="Deliverable Type" />
                            <SelectDropdown
                                id="deliverable_type_key"
                                v-model="currentDeliverable.details.deliverable_type_key"
                                :options="deliverableTypes"
                                valueKey="key"
                                labelKey="name"
                                placeholder="Select a Deliverable Type"
                                class="mt-1 block w-full"
                                required
                                @update:modelValue="handleDeliverableTypeChange"
                            />
                        </div>

                        <ChecklistCreator
                            v-model="currentDeliverable.details.checklist"
                            :placeholder="checklistPlaceholder"
                            label="Checklist Items"
                            containerClass="mt-4"
                        />
                    </div>

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
