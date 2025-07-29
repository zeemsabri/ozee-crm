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
    noteFor: {
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
watch(() => props.selectedItem, (newTask) => {
    if (newTask) {
        localNotes.value = [...(newTask.notes || [])];
        nextTick(() => {
            scrollToBottom();
        });
    } else {
        localNotes.value = []; // Clear notes when no task is selected
    }
}, { immediate: true });

// Watch for localNotes changes to auto-scroll
watch(localNotes, () => {
    nextTick(() => {
        scrollToBottom();
    });
}, { deep: true }); // Deep watch is fine for notes as it's a small array

const closeModal = () => {
    emits('update:isOpen', false);
    newNoteContent.value = ''; // Clear input on close
    isSubmittingNote.value = false;
};

const scrollToBottom = () => {
    if (notesContainerRef.value) {
        notesContainerRef.value.scrollTop = notesContainerRef.value.scrollHeight;
    }
};

const sendNoteApiRequest = async (payload) => {
    isSubmittingNote.value = true;

    console.log(props.selectedItem);

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
        console.error(`Error adding note to task ${props.selectedItem.id}:`, err);
        showModal('Error', err.message || 'Failed to add comment.', 'alert');
        throw err;
    } finally {
        isSubmittingNote.value = false;
    }
};

const handleAddNote = async () => {
    if (!newNoteContent.value.trim()) {
        showModal('Note Required', 'Please type a message before submitting.', 'alert');
        return;
    }

    try {
        const responseData = await sendNoteApiRequest({ comment_text: newNoteContent.value });
        if (responseData.note) {
            localNotes.value.push(responseData.note); // Add new note to local list
            newNoteContent.value = ''; // Clear input
            emits('note-added-success'); // Notify parent (TicketsSection) if needed for a full refresh
        }
    } catch (error) {
        // Error already handled by sendNoteApiRequest
    }
};

const getAuthorName = (note) => {

    return note.creator_name ?? 'UNKNOWN';
};

const getAuthorType = (note) => {
    if (note.author && note.author.type) {
        return note.author.type === 'client' ? 'You' : 'Team';
    }
    return 'Unknown';
};

const isClientAuthor = (note) => {
    return note.author && note.author.type === 'client';
};

// Computed property to display task status with styling
const taskStatusClass = computed(() => {
    if (!props.selectedItem) return '';
    const status = props.selectedItem.status;
    switch (status) {
        case 'completed': return 'text-green-600';
        case 'pending': return 'text-yellow-600';
        case 'in_progress': return 'text-blue-600';
        case 'blocked': return 'text-red-600';
        default: return 'text-gray-600';
    }
});

const taskStatusDisplay = computed(() => {
    if (!props.selectedItem) return '';
    return props.selectedItem.status?.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
});
</script>

<template>
    <transition name="slide-fade">
        <div v-if="isOpen && selectedItem"
             class="fixed right-0 top-0 h-full w-full sm:w-96 bg-white shadow-xl flex flex-col z-40 transition-transform duration-300 ease-in-out"
             @click.self="closeModal"
        >
            <!-- Overlay to click outside -->
            <div class="absolute inset-0 bg-gray-900 bg-opacity-50" @click="closeModal"></div>

            <!-- Sidebar Content -->
            <div class="relative bg-white w-full h-full flex flex-col p-4">
                <!-- Header -->
                <div class="flex justify-between items-center pb-4 border-b border-gray-200">
                    <h3 v-if="selectedItem.name" class="text-xl font-semibold text-gray-800 flex-1 truncate pr-2">Task: {{ selectedItem.name }}</h3>
                    <h3 v-if="selectedItem.filename" class="text-xl font-semibold text-gray-800 flex-1 truncate pr-2">File: {{ selectedItem.filename }}</h3>
                    <span class="text-sm font-medium" :class="taskStatusClass">{{ taskStatusDisplay }}</span>
                    <button @click="closeModal"
                            class="text-gray-500 hover:text-gray-800 text-3xl ml-4 leading-none"
                            aria-label="Close"
                    >&times;</button>
                </div>

                <!-- Notes History -->
                <div ref="notesContainerRef" class="flex-1 overflow-y-auto py-4 space-y-4">
                    <div v-if="localNotes.length === 0" class="text-center text-gray-500 py-8">
                        No notes for this task yet.
                    </div>
                    <div v-for="note in localNotes" :key="note.id"
                         :class="['flex items-start', isClientAuthor(note) ? 'justify-end' : 'justify-start']"
                    >
                        <div :class="['p-3 rounded-lg max-w-[80%]',
                                      isClientAuthor(note) ? 'bg-blue-100 text-blue-800 rounded-br-none' : 'bg-gray-100 text-gray-800 rounded-bl-none']">
                            <p class="font-semibold mb-1">
                                <span v-if="!isClientAuthor(note)">{{ getAuthorName(note) }}</span>:
                            </p>
                            <p class="whitespace-pre-wrap">{{ note.content }}</p>
                            <p class="text-xs text-gray-500 mt-1 text-right">
                                {{ new Date(note.created_at).toLocaleString() }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- New Note Input -->
                <div class="pt-4 border-t border-gray-200">
                    <textarea v-model="newNoteContent"
                              placeholder="Type your message here..."
                              rows="3"
                              class="w-full p-2 border border-gray-300 rounded-lg mb-2 focus:ring-blue-500 focus:border-blue-500 resize-y"
                              :disabled="isSubmittingNote"
                    ></textarea>
                    <button @click="handleAddNote"
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="isSubmittingNote || !newNoteContent.trim()"
                    >
                        <span v-if="isSubmittingNote">Sending...</span>
                        <span v-else>Send Message</span>
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

/* Ensure z-index is lower than BaseModal (z-[100]) but higher than main content */
.fixed.right-0.top-0 {
    z-index: 60; /* Higher than main content (which doesn't have explicit z-index) but lower than confirmation modal */
}
</style>
