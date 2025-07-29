<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    projectForm: {
        type: Object,
        required: true,
        default: () => ({
            id: null,
            notes: [] // Array of note objects { content: '...' }
        })
    },
    errors: {
        type: Object,
        default: () => ({})
    },
    canAddProjectNotes: {
        type: Boolean,
        default: false
    },
    canViewProjectNotes: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:projectForm', 'updateNotes']);

// Computed property for v-model binding
const localProjectForm = computed({
    get: () => props.projectForm,
    set: (value) => emit('update:projectForm', value)
});

/**
 * Adds a new empty note to the notes array.
 */
const addNote = () => {
    if (props.canAddProjectNotes) {
        localProjectForm.value.notes.push({ content: '' });
    }
};

/**
 * Removes a note from the notes array at the given index.
 * @param {number} index - The index of the note to remove.
 */
const removeNote = (index) => {
    if (props.canAddProjectNotes) {
        localProjectForm.value.notes.splice(index, 1);
    }
};

/**
 * Emits the 'updateNotes' event to the parent component to save the notes.
 */
const saveNotes = () => {
    emit('updateNotes', localProjectForm.value.notes);
};
</script>

<template>
    <div class="space-y-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Project Notes</h3>

        <div v-if="canViewProjectNotes" class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <InputLabel value="Notes Content" class="mb-3 text-lg" />
            <div v-if="localProjectForm.notes && localProjectForm.notes.length > 0">
                <div v-for="(note, index) in localProjectForm.notes" :key="index" class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-100 shadow-sm">
                    <div class="flex items-start mb-3">
                        <textarea
                            v-model="note.content"
                            :readonly="!canAddProjectNotes"
                            placeholder="Type your note content here..."
                            class="flex-grow border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm block w-full h-32 resize-y transition-colors duration-200 p-3"
                        ></textarea>
                        <button
                            v-if="canAddProjectNotes"
                            type="button"
                            @click="removeNote(index)"
                            class="ml-3 p-2 rounded-full text-red-500 hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200"
                            title="Remove note"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <InputError :message="errors[`notes.${index}.content`] ? errors[`notes.${index}.content`][0] : ''" class="mt-2" />
                </div>
            </div>
            <div v-else class="p-6 bg-gray-50 rounded-lg text-gray-600 text-center border border-gray-200 shadow-sm">
                No notes found for this project. Click "Add Note" to create one.
            </div>

            <div v-if="canAddProjectNotes" class="mt-6 flex justify-between items-center">
                <PrimaryButton @click="addNote" type="button" class="px-5 py-2.5 rounded-lg text-base shadow-sm hover:shadow-md transition-all duration-200">
                    Add Note
                </PrimaryButton>
                <PrimaryButton
                    @click="saveNotes"
                    :disabled="!localProjectForm.id || !canAddProjectNotes"
                    class="px-6 py-3 rounded-lg text-lg shadow-md hover:shadow-lg transition-all duration-200"
                >
                    Save Notes
                </PrimaryButton>
            </div>
            <p v-if="!localProjectForm.id" class="text-sm text-red-500 mt-3">
                Please save the project first before adding notes.
            </p>
        </div>
        <div v-else class="p-6 bg-gray-50 rounded-lg text-gray-600 text-center border border-gray-200 shadow-sm">
            You do not have permission to view project notes.
        </div>
    </div>
</template>

