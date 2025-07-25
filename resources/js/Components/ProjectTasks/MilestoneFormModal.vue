<script setup>
import { reactive, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    show: Boolean,
    projectId: Number,
});

const emit = defineEmits(['close', 'saved']);

const milestoneForm = reactive({
    name: '',
    description: '',
    completion_date: null,
    status: 'Not Started',
    project_id: props.projectId
});

const milestoneStatuses = ['Not Started', 'In Progress', 'Completed', 'Overdue'];

// Computed properties for BaseFormModal
const modalTitle = 'Add New Milestone';
const apiEndpoint = '/api/milestones';
const httpMethod = 'post';
const submitButtonText = 'Create Milestone';
const successMessage = 'Milestone created successfully!';

// Watch for changes in `show` prop to reset form data
watch(() => props.show, (newValue) => {
    if (newValue) {
        Object.assign(milestoneForm, {
            name: '',
            description: '',
            completion_date: null,
            status: 'Not Started',
            project_id: props.projectId
        });
    }
}, { immediate: true });

// Function to handle the successful submission from BaseFormModal
const handleSaved = (responseData) => {
    // The response data here will be the new milestone
    emit('saved', responseData);
    emit('close');
};

// Pass through the close event
const closeModal = () => {
    emit('close');
};
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="modalTitle"
        :api-endpoint="apiEndpoint"
        :http-method="httpMethod"
        :form-data="milestoneForm"
        :submit-button-text="submitButtonText"
        :success-message="successMessage"
        @close="closeModal"
        @submitted="handleSaved"
    >
        <template #default="{ errors }">
            <div class="space-y-4">
                <!-- Milestone Name -->
                <div>
                    <InputLabel for="milestone-name" value="Milestone Name" />
                    <TextInput
                        id="milestone-name"
                        v-model="milestoneForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Enter milestone name"
                        required
                    />
                    <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
                </div>

                <!-- Milestone Description -->
                <div>
                    <InputLabel for="milestone-description" value="Description" />
                    <textarea
                        id="milestone-description"
                        v-model="milestoneForm.description"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows="3"
                        placeholder="Enter milestone description"
                    ></textarea>
                    <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
                </div>

                <!-- Completion Date -->
                <div>
                    <InputLabel for="milestone-completion-date" value="Completion Date" />
                    <TextInput
                        id="milestone-completion-date"
                        v-model="milestoneForm.completion_date"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="errors.completion_date ? errors.completion_date[0] : ''" class="mt-2" />
                </div>

                <!-- Status -->
                <div>
                    <InputLabel for="milestone-status" value="Status" />
                    <SelectDropdown
                        id="milestone-status"
                        v-model="milestoneForm.status"
                        :options="milestoneStatuses.map(s => ({ value: s, label: s }))"
                        value-key="value"
                        label-key="label"
                        placeholder="Select status"
                        class="mt-1"
                    />
                    <InputError :message="errors.status ? errors.status[0] : ''" class="mt-2" />
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>
