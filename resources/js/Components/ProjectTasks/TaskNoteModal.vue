<script setup>
import { reactive, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    taskForNote: Object, // The task object to which the note is being added
});

const emit = defineEmits(['close', 'note-added']);

const noteForm = reactive({
    note: '', // The content of the note
});

// Computed properties for BaseFormModal
const modalTitle = computed(() => `Add Note to Task: "${props.taskForNote?.name}"`);
const apiEndpoint = computed(() => `/api/tasks/${props.taskForNote?.id}/notes`);
const httpMethod = 'post';
const submitButtonText = 'Add Note';
const successMessage = 'Note added successfully!';

// Watch for changes in `show` prop to reset form data
watch(() => props.show, (newValue) => {
    if (newValue) {
        Object.assign(noteForm, {
            note: '',
        });
    }
}, { immediate: true });

// Function to handle the successful submission from BaseFormModal
const handleSaved = (responseData) => {
    emit('note-added', responseData); // Emit the response data if needed by parent
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
        :form-data="noteForm"
        :submit-button-text="submitButtonText"
        :success-message="successMessage"
        @close="closeModal"
        @submitted="handleSaved"
    >
        <template #default="{ errors }">
            <div class="space-y-4">
                <div v-if="taskForNote" class="mb-4 p-3 bg-gray-100 rounded-md">
                    <p class="text-sm font-medium text-gray-900">{{ taskForNote.name }}</p>
                    <p class="text-xs text-gray-500 mt-1">Status: {{ taskForNote.status }}</p>
                </div>

                <div class="mb-4">
                    <InputLabel for="task-note-content" value="Note Content" />
                    <textarea
                        id="task-note-content"
                        v-model="noteForm.note"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full h-32"
                        placeholder="Enter your note..."
                        required
                    ></textarea>
                    <InputError :message="errors.note ? errors.note[0] : ''" class="mt-2" />
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>
