<script setup>
import { reactive, ref, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue'; // Import the new base modal
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    projectId: Number,
});

const emit = defineEmits(['close', 'noteAdded']);

// Form state for adding notes
const noteForm = reactive({
    project_id: null,
    // The backend expects an array of notes, so we'll structure it that way.
    // BaseFormModal will send this reactive object directly.
    notes: [{ content: '' }],
});

// Watch for changes in projectId prop
watch(() => props.projectId, (newProjectId) => {
    if (newProjectId) {
        noteForm.project_id = newProjectId;
    }
}, { immediate: true });

// Reset form when modal is closed
watch(() => props.show, (isVisible) => {
    if (isVisible) {
        noteForm.project_id = props.projectId;
        noteForm.notes[0].content = ''; // Reset the content of the first note
        // BaseFormModal will handle clearing its own generalError and validationErrors
    }
});

// Computed property for API endpoint
const apiEndpoint = computed(() => `/api/projects/${noteForm.project_id}/notes`);

// Handle the 'submitted' event from BaseFormModal
const handleNoteSubmitted = (responseData) => {
    emit('noteAdded'); // Notify parent that note was added
    // BaseFormModal will automatically close if closeOnSuccess is true (default)
};

// Handle submission error from BaseFormModal (optional, BaseFormModal shows a generic error)
const handleSubmissionError = (error) => {
    console.error('Error in NotesModal submission:', error);
};
</script>

<template>
    <BaseFormModal
        :show="show"
        title="Add Note"
        :api-endpoint="apiEndpoint"
        http-method="post"
        :form-data="noteForm"
        submit-button-text="Add Note"
        success-message="Note added successfully!"
        @close="$emit('close')"
        @submitted="handleNoteSubmitted"
        @error="handleSubmissionError"
    >
        <!-- Use a scoped slot to access validation errors from BaseFormModal -->
        <template #default="{ errors }">
            <div class="mb-4">
                <InputLabel for="note_content" value="Note Content" />
                <TextInput
                    id="note_content"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="noteForm.notes[0].content"
                    required
                />
                <!-- Note: Backend validation for notes in an array usually uses dot notation (e.g., 'notes.0.content') -->
                <InputError :message="errors['notes.0.content'] ? errors['notes.0.content'][0] : ''" class="mt-2" />
            </div>
        </template>
    </BaseFormModal>
</template>
