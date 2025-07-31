<script setup>
import { ref, watch, inject, nextTick, computed } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    selectedItem: {
        type: Object,
        default: null,
    },
    initialAuthToken: {
        type: String,
        required: true,
    },
    projectId: {
        type: [String, Number],
        required: true,
    },
    noteFor: { // e.g., 'tasks', 'documents'
        type: String,
        required: true
    }
});

const emits = defineEmits(['update:isOpen', 'note-added-success']);

const newNoteContent = ref('');
const isSubmittingNote = ref(false);
const localNotes = ref([]); // Local copy of notes for real-time updates
const notesContainerRef = ref(null); // Ref for the scrollable notes div

// Inject the showModal from ClientDashboard for showing alerts
const { showModal } = inject('modalService');

// Watch for selectedItem changes to update local notes and scroll
watch(() => props.selectedItem, (newVal) => {
    if (newVal) {
        // Ensure notes are always an array, even if null/undefined
        localNotes.value = Array.isArray(newVal.notes) ? [...newVal.notes] : [];
        nextTick(() => {
            scrollToBottom();
        });
    } else {
        // When selectedItem becomes null (e.g., sidebar closes without a specific item), clear notes
        localNotes.value = [];
    }
}, { immediate: true });

// Watch for isOpen to scroll to comments when opened
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        nextTick(() => { // Ensure DOM is updated before trying to scroll
            scrollToBottom();
        });
    } else {
        // Reset input states when sidebar closes, but DO NOT clear localNotes here.
        // localNotes are managed by the selectedItem watcher.
        newNoteContent.value = '';
        isSubmittingNote.value = false;
    }
});

// Watch localNotes to auto-scroll to bottom when new notes are added
watch(localNotes, () => {
    nextTick(() => {
        scrollToBottom();
    });
}, { deep: true }); // Deep watch is acceptable here as notes list is typically small and only appended


const closeModal = () => {
    emits('update:isOpen', false);
};

const scrollToBottom = () => {
    if (notesContainerRef.value) {
        notesContainerRef.value.scrollTop = notesContainerRef.value.scrollHeight;
    }
};

const sendNoteApiRequest = async (payload) => {
    isSubmittingNote.value = true;
    try {
        const response = await fetch(`/api/client-api/${props.noteFor}/${props.selectedItem.id}/notes`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMessage = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'API request failed.');
            throw new Error(errorMessage);
        }
        return data;
    } catch (err) {
        console.error(`Error adding note to ${props.noteFor} ${props.selectedItem.id}:`, err);
        showModal('Error', err.message || 'Failed to add message.', 'alert');
        throw err;
    } finally {
        isSubmittingNote.value = false;
    }
};

const handleAddNote = async () => {
    if (!newNoteContent.value.trim()) {
        showModal('Message Required', 'Please type a message before submitting.', 'alert');
        return;
    }

    try {
        // FIX: Changed 'content' to 'comment_text' to match the expected API payload
        const responseData = await sendNoteApiRequest({ comment_text: newNoteContent.value, type: 'note' });
        if (responseData.note) {
            localNotes.value.push(responseData.note); // Add new note to local list
            newNoteContent.value = ''; // Clear input
            emits('note-added-success'); // Notify parent (TicketsSection) if needed for a full refresh
        }
    } catch (error) {
        // Error already handled by sendNoteApiRequest
    }
};

// Determines if the note was created by the client (current user)
const isClientAuthor = (note) => {
    // Assuming 'App\\Models\\Client' identifies the client user type
    return note.creator_type === 'App\\Models\\Client';
};

// Helper to format date and time
const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// Computed property to display item status with styling (for tasks)
const itemStatusClass = computed(() => {
    if (!props.selectedItem || props.noteFor !== 'tasks') return '';
    const status = props.selectedItem.status?.toLowerCase();
    switch (status) {
        case 'completed': return 'text-green-600';
        case 'to do': return 'text-yellow-600';
        case 'in progress': return 'text-blue-600';
        case 'blocked': return 'text-red-600';
        default: return 'text-gray-600';
    }
});

const itemStatusDisplay = computed(() => {
    if (!props.selectedItem || props.noteFor !== 'tasks') return '';
    return props.selectedItem.status?.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
});
</script>

<template>
    <transition name="slide-fade">
        <div v-if="isOpen && selectedItem"
             class="fixed right-0 top-0 h-full w-full sm:w-96 bg-gray-900 bg-opacity-50 flex justify-end z-50 font-inter"
             @click.self="closeModal"
        >
            <!-- Sidebar Content -->
            <div class="relative bg-white w-full sm:w-96 h-full flex flex-col p-6 shadow-2xl rounded-l-xl">
                <!-- Header -->
                <div class="flex justify-between items-center pb-5 mb-4 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 flex-1 truncate pr-2">
                        <span v-if="noteFor === 'tasks'">Task: {{ selectedItem.name }}</span>
                        <span v-else-if="noteFor === 'documents'">Document: {{ selectedItem.filename }}</span>
                        <span v-else>Details</span>
                    </h3>
                    <span v-if="noteFor === 'tasks' && selectedItem.status"
                          :class="['px-3 py-1 rounded-full text-xs font-semibold capitalize', itemStatusClass]"
                    >
                        {{ itemStatusDisplay }}
                    </span>
                    <button @click="closeModal"
                            class="text-gray-500 hover:text-gray-800 text-4xl ml-4 leading-none transition-colors duration-200"
                            aria-label="Close"
                    >&times;</button>
                </div>

                <!-- Notes History -->
                <div ref="notesContainerRef" class="flex-1 overflow-y-auto py-4 space-y-4">
                    <div v-if="localNotes.length === 0" class="text-center text-gray-500 py-8">
                        No messages for this item yet.
                    </div>
                    <div v-for="note in localNotes" :key="note.id"
                         :class="['flex', isClientAuthor(note) ? 'justify-end' : 'justify-start']"
                    >
                        <div :class="['p-4 rounded-xl max-w-[80%] shadow-sm',
                                      isClientAuthor(note) ? 'bg-indigo-500 text-white rounded-br-none' : 'bg-gray-200 text-gray-800 rounded-bl-none']">
                            <p class="font-semibold mb-1" :class="{'text-indigo-100': isClientAuthor(note)}">
                                {{ note.creator_name || 'System' }}
                            </p>
                            <p class="whitespace-pre-wrap text-base leading-relaxed">{{ note.content }}</p>
                            <p class="text-xs mt-2" :class="isClientAuthor(note) ? 'text-indigo-200 text-right' : 'text-gray-600 text-left'">
                                {{ formatDateTime(note.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- New Note Input -->
                <div class="pt-6 border-t border-gray-200 mt-4">
                    <textarea v-model="newNoteContent"
                              placeholder="Type your message here..."
                              rows="3"
                              class="w-full p-3 border border-gray-300 rounded-lg mb-3 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200 resize-y shadow-sm"
                              :disabled="isSubmittingNote"
                    ></textarea>
                    <button @click="handleAddNote"
                            class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-all duration-200 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed shadow-md flex items-center justify-center"
                            :disabled="isSubmittingNote || !newNoteContent.trim()"
                    >
                        <!-- FIX: Ensure text and SVG are correctly aligned using flexbox -->
                        <span v-if="isSubmittingNote" class="flex items-center">Sending...</span>
                        <span v-else class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-send mr-2"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M15 15l4 4"/></svg>
                            Send Message
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
/* Transition for sliding sidebar */
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: transform 0.3s ease-in-out;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
    transform: translateX(100%);
}

/* Ensure z-index is higher than main content and other modals if needed, but lower than critical alerts */
.fixed.right-0.top-0 {
    z-index: 60;
}
</style>
