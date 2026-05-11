<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    userId: { type: Number, required: true }
});

const loading = ref(false);
const notes = ref([]);
const newNote = ref('');

const fetchNotes = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/users/${props.userId}/notes`);
        notes.value = data;
    } catch (e) {
        console.error('Failed to fetch notes', e);
    } finally {
        loading.value = false;
    }
};

const addNote = async () => {
    if (!newNote.value.trim()) return;
    loading.value = true;
    try {
        const { data } = await axios.post(`/api/users/${props.userId}/notes`, { content: newNote.value });
        notes.value.unshift(data);
        newNote.value = '';
    } catch (e) {
        console.error('Failed to add note', e);
        alert('Failed to add note');
    } finally {
        loading.value = false;
    }
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

onMounted(fetchNotes);
</script>

<template>
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden h-full flex flex-col">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Internal Notes</h3>
        </div>

        <div class="flex-grow overflow-y-auto p-4 space-y-4 max-h-[400px]">
            <div v-if="loading && notes.length === 0" class="animate-pulse space-y-4">
                <div v-for="i in 2" :key="i" class="space-y-2">
                    <div class="h-3 bg-gray-100 rounded w-1/4"></div>
                    <div class="h-10 bg-gray-50 rounded w-full"></div>
                </div>
            </div>

            <div v-else-if="notes.length === 0" class="text-gray-400 text-xs text-center py-8 italic">
                No notes for this user yet.
            </div>

            <div v-for="note in notes" :key="note.id" class="relative pl-4 border-l-2 border-indigo-100 py-1">
                <div class="flex justify-between items-start mb-1">
                    <span class="text-[10px] font-bold text-indigo-600 uppercase">{{ note.author?.name || 'Unknown' }}</span>
                    <span class="text-[10px] text-gray-400">{{ formatDate(note.created_at) }}</span>
                </div>
                <p class="text-sm text-gray-600 whitespace-pre-wrap leading-relaxed">{{ note.content }}</p>
            </div>
        </div>

        <div class="p-4 bg-gray-50 border-t border-gray-100">
            <div class="space-y-2">
                <textarea 
                    v-model="newNote"
                    rows="3"
                    placeholder="Add an internal note..."
                    class="w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm bg-white"
                ></textarea>
                <div class="flex justify-end">
                    <button 
                        @click="addNote" 
                        :disabled="loading || !newNote.trim()"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-300 text-white text-xs font-semibold rounded shadow-sm transition-colors"
                    >
                        Post Note
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
