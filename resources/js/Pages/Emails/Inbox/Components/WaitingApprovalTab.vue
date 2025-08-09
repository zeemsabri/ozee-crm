<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    isActive: Boolean,
});

const outgoingEmails = ref([]);
const incomingEmails = ref([]);
const loading = ref(true);
const error = ref('');

const emit = defineEmits(['view-email']);

const fetchEmails = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await axios.get('/api/inbox/waiting-approval');
        outgoingEmails.value = response.data.outgoing;
        incomingEmails.value = response.data.incoming;
    } catch (err) {
        console.error('Error fetching emails waiting approval:', err);
        error.value = 'Failed to load emails waiting for approval. Please try again.';
    } finally {
        loading.value = false;
    }
};

const handleViewEmail = (email) => {
    emit('view-email', email);
};

// Watch for the isActive prop to trigger the fetch when the tab becomes active
watch(() => props.isActive, (newVal) => {
    if (newVal) {
        fetchEmails();
    }
}, { immediate: true }); // 'immediate' ensures the fetch runs on initial load if the tab is active

// Expose the refresh method for external use if needed
defineExpose({
    refresh: fetchEmails,
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-xl font-medium text-gray-900">Emails Awaiting Your Approval</h3>
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
        <div v-if="loading" class="flex justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
        </div>

        <div v-else-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ error }}</span>
        </div>

        <div v-else>
            <div v-if="incomingEmails.length === 0 && outgoingEmails.length === 0" class="text-center py-8 text-gray-500 bg-gray-50 rounded-lg">
                <p>No emails waiting for approval.</p>
            </div>

            <!-- Incoming Emails Section -->
            <div v-if="incomingEmails.length > 0" class="mb-8">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Incoming Emails</h4>
                <div class="overflow-x-auto shadow rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="email in incomingEmails" :key="email.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ email.sender?.name || 'Unknown' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ email.subject }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ email.conversation?.project?.name || 'Unknown' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ new Date(email.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button @click="handleViewEmail(email)" class="text-indigo-600 hover:text-indigo-900">
                                    View
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Outgoing Emails Section -->
            <div v-if="outgoingEmails.length > 0">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Outgoing Emails</h4>
                <div class="overflow-x-auto shadow rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="email in outgoingEmails" :key="email.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ email.subject }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ email.conversation?.project?.name || 'Unknown' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ new Date(email.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button @click="handleViewEmail(email)" class="text-indigo-600 hover:text-indigo-900">
                                    View
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
