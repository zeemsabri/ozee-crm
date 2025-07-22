<script setup>
import { reactive, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    projectId: Number,
});

const emit = defineEmits(['close', 'noteAdded']);

// Form state for adding notes
const noteForm = reactive({
    project_id: null,
    content: '',
});

const errors = ref({});
const generalError = ref('');

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
        noteForm.content = '';
        errors.value = {};
        generalError.value = '';
    }
});

const addNote = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        await window.axios.post(`/api/projects/${noteForm.project_id}/notes`, { notes: [{ content: noteForm.content }] });
        emit('noteAdded');
        emit('close');
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to add note.';
            console.error('Error adding note:', error);
        }
    }
};
</script>

<template>
    <Modal :show="show" @close="$emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Add Note</h2>
            <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
            <form @submit.prevent="addNote">
                <div class="mb-4">
                    <InputLabel for="note_content" value="Note Content" />
                    <TextInput id="note_content" type="text" class="mt-1 block w-full" v-model="noteForm.content" required />
                    <InputError :message="errors['notes.0.content'] ? errors['notes.0.content'][0] : ''" class="mt-2" />
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="$emit('close')">Cancel</SecondaryButton>
                    <PrimaryButton class="ms-3" type="submit">Add Note</PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
