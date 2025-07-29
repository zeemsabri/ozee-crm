<script setup>
import { computed, watch } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    projectId: { // Assuming projectId is passed from parent and is needed for context
        type: [Number, String],
        required: true
    },
    projectForm: {
        type: Object,
        required: true,
        default: () => ({
            id: null,
            notes: [] // Array of note objects { id: null, content: '...', created_at: '...', creator_name: '...' }
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
    },
    // NEW PROP: Controlled by the parent component
    isSaving: {
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
 * Includes a timestamp and explicitly sets id to null for new notes.
 */
const addNote = () => {
    if (props.canAddProjectNotes) {
        localProjectForm.value.notes.push({
            id: null, // Explicitly null for new notes
            content: '',
            created_at: new Date().toISOString(), // Add current timestamp
            creator_name: 'You' // Default creator for newly added notes in frontend
        });
    }
};

/**
 * Removes a note from the notes array at the given index.
 * @param {number} index - The index of the note to remove.
 */
const removeNote = (index) => {
    if (props.canAddProjectNotes) {
        // For existing notes, you might want to mark them for deletion
        // instead of immediately splicing, then filter on save.
        // For simplicity, this example still splices.
        localProjectForm.value.notes.splice(index, 1);
    }
};

/**
 * Emits the 'updateNotes' event to the parent component to save the notes.
 * This function now explicitly constructs the payload to ensure 'id' is included.
 */
const saveNotes = () => {
    // Map the notes to ensure only relevant fields are sent and 'id' is always present if it exists
    const notesToSave = localProjectForm.value.notes.map(note => ({
        id: note.id || null, // Ensure 'id' is included, or null if new
        content: note.content,
        // Include other fields if your backend expects them for updates, e.g.:
        // type: note.type,
        // chat_message_id: note.chat_message_id,
        // parent_id: note.parent_id,
    }));
    console.log('Payload being emitted for save:', notesToSave); // Debugging: check this in your browser console

    // Emit the event. The parent component is now fully responsible for managing the loading state.
    emit('updateNotes', notesToSave);
};

/**
 * Formats a date string into a more readable format.
 * @param {string} dateString - The date string to format.
 * @returns {string} Formatted date string.
 */
const formatNoteDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// --- Debugging Watcher ---
watch(() => props.projectForm.notes, (newNotes) => {
    console.log('ProjectFormNotes received notes:', newNotes);
}, { deep: true, immediate: true });
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-xl font-inter">
        <h3 class="text-2xl font-semibold text-gray-800 mb-6">Project Notes Board</h3>

        <div v-if="!canViewProjectNotes" class="p-6 bg-gray-50 rounded-lg text-gray-600 text-center border border-gray-200 shadow-sm">
            You do not have permission to view project notes.
        </div>

        <div v-else>
            <div class="mb-6 flex justify-between items-center">
                <PrimaryButton
                    @click="addNote"
                    type="button"
                    class="px-5 py-2.5 rounded-lg text-base shadow-sm hover:shadow-md transition-all duration-200"
                    :disabled="!canAddProjectNotes || isSaving"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add New Note
                </PrimaryButton>

                <PrimaryButton
                    @click="saveNotes"
                    :disabled="!localProjectForm.id || !canAddProjectNotes || isSaving"
                    :class="{ 'opacity-50 cursor-not-allowed': isSaving }"
                    class="px-6 py-3 rounded-lg text-lg shadow-md hover:shadow-lg transition-all duration-200"
                >
                    <span v-if="isSaving" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                    <span v-else>Save All Notes</span>
                </PrimaryButton>
            </div>

            <p v-if="!localProjectForm.id" class="text-sm text-red-500 mb-4 bg-red-50 p-3 rounded-md border border-red-200">
                Please save the project first before adding or editing notes.
            </p>

            <div v-if="localProjectForm.notes && localProjectForm.notes.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="(note, index) in localProjectForm.notes"
                    :key="note.id || `new-note-${index}`"
                    class="relative bg-white p-5 rounded-lg shadow-md border border-gray-200 flex flex-col h-64 transition-all duration-200 ease-in-out hover:shadow-lg"
                    :class="{ 'opacity-75 cursor-not-allowed': !canAddProjectNotes || isSaving }"
                >
                    <div class="flex justify-between items-start mb-3">
                        <InputLabel :value="`Note ${index + 1}`" class="text-sm font-semibold text-gray-700" />
                        <button
                            v-if="canAddProjectNotes"
                            type="button"
                            @click="removeNote(index)"
                            class="p-1 rounded-full text-red-500 hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200"
                            title="Remove note"
                            :disabled="isSaving"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <textarea
                        v-model="note.content"
                        :readonly="!canAddProjectNotes || isSaving"
                        placeholder="Type your note content here..."
                        class="flex-grow border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm block w-full resize-none transition-colors duration-200 p-3 text-sm overflow-auto"
                        rows="6"
                    ></textarea>

                    <InputError :message="errors[`notes.${index}.content`] ? errors[`notes.${index}.content`][0] : ''" class="mt-2" />

                    <div class="mt-auto pt-3 border-t border-gray-100 text-xs text-gray-500 flex justify-between items-center">
                        <span>By: <span class="font-medium text-gray-700">{{ note.creator_name || 'Unknown' }}</span></span>
                        <span>Last updated: {{ formatNoteDate(note.created_at) }}</span>
                    </div>
                </div>
            </div>
            <div v-else class="p-8 bg-gray-50 rounded-lg text-gray-600 text-center border border-gray-200 shadow-sm flex flex-col items-center justify-center h-64">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-lg font-medium">No notes found for this project.</p>
                <p class="text-sm mt-2">Click "Add New Note" to start creating your project notes.</p>
            </div>
        </div>
    </div>
</template>

<style>
/* Ensure Inter font is applied if not globally */
.font-inter {
    font-family: 'Inter', sans-serif;
}
</style>
