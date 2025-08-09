<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import EmailList from '@/Components/ProjectsEmails/EmailList.vue';

const props = defineProps({
    isActive: Boolean,
});

const emails = ref([]);
const loading = ref(true);
const error = ref('');

const emit = defineEmits(['view-email']);

const fetchEmails = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await axios.get('/api/inbox/new-emails');
        emails.value = response.data;
    } catch (err) {
        console.error('Error fetching new emails:', err);
        error.value = 'Failed to load new emails. Please try again.';
    } finally {
        loading.value = false;
    }
};

const handleViewEmail = (email) => {
    emit('view-email', email);

    // Remove the email from the list since it's now read
    emails.value = emails.value.filter(e => e.id !== email.id);
};

// Watch for the isActive prop to trigger the fetch when the tab becomes active
watch(() => props.isActive, (newVal) => {
    if (newVal) {
        fetchEmails();
    }
}, { immediate: true }); // 'immediate' ensures the fetch runs on initial load if the tab is active

// Expose methods to parent component
defineExpose({
    refresh: fetchEmails,
});
</script>

<template>
    <div>
        <div class="flex justify-end mb-4">
            <button
                @click="fetchEmails"
                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </button>
        </div>
        <div v-if="emails.length === 0 && !loading" class="text-center py-8 text-gray-500">
            <p>No new emails to display.</p>
        </div>

        <EmailList
            :emails="emails"
            :loading="loading"
            :error="error"
            @view="handleViewEmail"
        />
    </div>
</template>
