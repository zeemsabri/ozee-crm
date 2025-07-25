<script setup>
import { ref, onMounted, reactive } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import NotesModal from '@/Components/NotesModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { usePermissions } from '@/Directives/permissions';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    googleChatId: { // Pass project.google_chat_id
        type: String,
        default: null,
    },
    canViewNotes: {
        type: Boolean,
        required: true,
    },
    canAddNotes: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['notesUpdated']);

const notes = ref([]);
const loadingNotes = ref(true);
const notesError = ref('');

// Modal state for add/reply notes
const showAddNoteModal = ref(false);
const showReplyModal = ref(false);
const selectedNote = ref(null);
const replyContent = ref('');
const replyError = ref('');
const noteReplies = ref([]);
const loadingReplies = ref(false);

// Notes filters
const noteFilters = reactive({
    startDate: '',
    endDate: '',
    search: '',
});

let noteSearchDebounceTimer = null;

// Functions
const fetchProjectNotes = async () => {
    loadingNotes.value = true;
    notesError.value = '';
    try {
        const params = new URLSearchParams();
        if (noteFilters.startDate) { params.append('start_date', noteFilters.startDate); }
        if (noteFilters.endDate) { params.append('end_date', noteFilters.endDate); }
        if (noteFilters.search) { params.append('search', noteFilters.search); }

        const queryString = params.toString();
        const url = `/api/projects/${props.projectId}/notes${queryString ? `?${queryString}` : ''}`;

        const response = await window.axios.get(url);
        notes.value = response.data;
        emit('notesUpdated', notes.value); // Emit updated notes to parent
    } catch (error) {
        notesError.value = 'Failed to load notes data.';
        console.error('Error fetching project notes:', error);
    } finally {
        loadingNotes.value = false;
    }
};

const openAddNoteModal = () => {
    showAddNoteModal.value = true;
};

const replyToNote = async (note) => {
    selectedNote.value = note;
    replyContent.value = '';
    replyError.value = '';
    noteReplies.value = [];
    loadingReplies.value = true;
    showReplyModal.value = true;

    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/notes/${note.id}/replies`);
        if (response.data.success) {
            noteReplies.value = response.data.replies;
        } else {
            console.error('Failed to fetch replies:', response.data.message);
        }
    } catch (error) {
        console.error('Error fetching replies:', error);
    } finally {
        loadingReplies.value = false;
    }
};

const submitReply = async () => {
    if (!selectedNote.value || !replyContent.value.trim()) {
        replyError.value = 'Reply content is required';
        return;
    }

    replyError.value = '';
    try {
        const response = await window.axios.post(
            `/api/projects/${props.projectId}/notes/${selectedNote.value.id}/reply`,
            { content: replyContent.value }
        );

        if (response.data.success) {
            replyContent.value = '';
            loadingReplies.value = true;
            const repliesResponse = await window.axios.get(`/api/projects/${props.projectId}/notes/${selectedNote.value.id}/replies`);
            if (repliesResponse.data.success) {
                noteReplies.value = repliesResponse.data.replies;
            }
            loadingReplies.value = false;
            await fetchProjectNotes(); // Refresh main notes list to update reply count
        } else {
            replyError.value = response.data.message || 'Failed to send reply';
        }
    } catch (error) {
        console.error('Error sending reply:', error);
        replyError.value = error.response?.data?.message || 'An error occurred while sending the reply';
    }
};

const applyNoteFilters = () => {
    fetchProjectNotes();
};

const resetNoteFilters = () => {
    noteFilters.startDate = '';
    noteFilters.endDate = '';
    noteFilters.search = '';
    fetchProjectNotes();
};

const debounceNoteSearch = () => {
    clearTimeout(noteSearchDebounceTimer);
    noteSearchDebounceTimer = setTimeout(() => {
        applyNoteFilters();
    }, 500);
};

onMounted(() => {
    if (props.canViewNotes) {
        fetchProjectNotes();
    }
});
</script>

<template>
    <div v-if="canViewNotes" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-gray-900">Project Notes</h4>
            <div class="flex gap-3">
                <PrimaryButton v-if="canAddNotes" class="bg-indigo-600 hover:bg-indigo-700 transition-colors" @click="openAddNoteModal">
                    Add Note
                </PrimaryButton>
            </div>
        </div>

        <!-- Notes Filters -->
        <div class="mb-4 bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <InputLabel for="noteStartDate" value="From Date" />
                    <TextInput type="date" id="noteStartDate" v-model="noteFilters.startDate" class="mt-1 block w-full" @change="applyNoteFilters" />
                </div>
                <div>
                    <InputLabel for="noteEndDate" value="To Date" />
                    <TextInput type="date" id="noteEndDate" v-model="noteFilters.endDate" class="mt-1 block w-full" @change="applyNoteFilters" />
                </div>

                <div>
                    <InputLabel for="noteSearchFilter" value="Search Content" />
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <TextInput type="text" id="noteSearchFilter" v-model="noteFilters.search" class="block w-full pr-10" placeholder="Search in note content..." @input="debounceNoteSearch" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 flex justify-end">
                <button type="button" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" @click="resetNoteFilters">
                    Reset Filters
                </button>
            </div>
        </div>

        <!-- Note about Daily Standups -->
        <div class="mb-8 p-4 bg-blue-50 rounded-md">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm text-blue-800">
                    Daily Standups are now available in their own dedicated tab.
                    <!-- Emitting an event to tell parent to change tab -->
                    <button @click="$emit('changeTab', 'standups')" class="text-blue-600 hover:text-blue-800 font-medium underline">
                        Click here to view Daily Standups
                    </button>
                </p>
            </div>
        </div>

        <!-- Notes List -->
        <div>
            <h5 class="text-md font-semibold text-gray-900 mb-4 border-b pb-2">Notes</h5>
            <div v-if="loadingNotes" class="text-center text-gray-600 text-sm animate-pulse py-4">
                Loading notes...
            </div>
            <div v-else-if="notesError" class="text-center text-red-600 text-sm font-medium py-4">
                {{ notesError }}
            </div>
            <div v-else-if="notes.length" class="space-y-4">
                <div v-for="note in notes" :key="note.id" class="p-4 bg-gray-50 rounded-md shadow-sm hover:bg-gray-100 transition-colors">
                    <div class="flex justify-between">
                        <div class="flex-grow">
                            <p class="text-sm" :class="{'text-gray-700': note.content !== '[Encrypted content could not be decrypted]', 'text-red-500 italic': note.content === '[Encrypted content could not be decrypted]'}">
                                {{ note.content }}
                                <span v-if="note.content === '[Encrypted content could not be decrypted]'" class="text-xs text-red-400 block mt-1">
                                    (There was an issue decrypting this note. Please contact an administrator.)
                                </span>
                            </p>
                            <div class="flex items-center mt-1">
                                <p class="text-xs text-gray-500">Added by {{ note.user?.name || 'Unknown' }} on {{ new Date(note.created_at).toLocaleDateString() }}</p>
                                <span v-if="note.reply_count > 0" class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-800 text-xs rounded-full">
                                    {{ note.reply_count }} {{ note.reply_count === 1 ? 'reply' : 'replies' }}
                                </span>
                            </div>
                        </div>
                        <div v-if="googleChatId && note.chat_message_id">
                            <SecondaryButton class="text-sm text-indigo-600 hover:text-indigo-800" @click="replyToNote(note)">
                                View
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="text-gray-400 text-sm">No notes available.</p>
        </div>

        <!-- Add Note Modal -->
        <NotesModal :show="showAddNoteModal" :project-id="projectId" @close="showAddNoteModal = false" @note-added="fetchProjectNotes" />

        <!-- Reply to Note Modal -->
        <Modal :show="showReplyModal" @close="showReplyModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Reply to Note</h3>
                    <button @click="showReplyModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="selectedNote" class="mb-4 p-3 bg-gray-100 rounded-md">
                    <p class="text-sm text-gray-700">{{ selectedNote.content }}</p>
                    <p class="text-xs text-gray-500 mt-1">Added by {{ selectedNote.user?.name || 'Unknown' }} on {{ new Date(selectedNote.created_at).toLocaleDateString() }}</p>
                </div>

                <!-- Replies Section -->
                <div v-if="selectedNote" class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Replies</h4>
                    <div v-if="loadingReplies" class="text-center py-4">
                        <p class="text-gray-500 text-sm">Loading replies...</p>
                    </div>
                    <div v-else-if="noteReplies.length" class="space-y-3 max-h-60 overflow-y-auto">
                        <div v-for="reply in noteReplies" :key="reply.id" class="p-2 bg-gray-50 rounded border-l-2 border-indigo-300">
                            <p class="text-sm text-gray-700">{{ reply.content }}</p>
                            <p class="text-xs text-gray-500 mt-1">Replied by {{ reply.user?.name || 'Unknown' }} on {{ new Date(reply.created_at).toLocaleDateString() }}</p>
                        </div>
                    </div>
                    <div v-else class="text-center py-3">
                        <p class="text-gray-500 text-sm">No replies yet. Be the first to reply!</p>
                    </div>
                </div>

                <div class="mb-4" v-if="canAddNotes">
                    <InputLabel for="reply-content" value="Your Reply" />
                    <textarea id="reply-content" v-model="replyContent" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full h-32" placeholder="Enter your reply..."></textarea>
                    <p v-if="replyError" class="mt-2 text-sm text-red-600">{{ replyError }}</p>
                </div>

                <div v-if="canAddNotes" class="mt-6 flex justify-end">
                    <SecondaryButton @click="showReplyModal = false" class="mr-2">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitReply">Send Reply</PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
