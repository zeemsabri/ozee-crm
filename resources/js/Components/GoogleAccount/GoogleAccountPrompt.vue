<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { showSuccessNotification, showErrorNotification } from '@/Utils/notification';

const hasGoogleCredentials = ref(false);
const isLoading = ref(true);
const showPrompt = ref(true);

// Check if the user has Google credentials
const checkGoogleCredentials = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get('/api/user/google-chat/check-credentials');
        hasGoogleCredentials.value = response.data.has_credentials;
        showPrompt.value = !hasGoogleCredentials.value;
        console.log('Google credentials check:', { hasGoogleCredentials: hasGoogleCredentials.value, showPrompt: showPrompt.value });
    } catch (error) {
        console.error('Error checking Google credentials:', error);
        showErrorNotification('Failed to check Google account status');
        // If there's an error, we'll show the prompt anyway
        showPrompt.value = true;
    } finally {
        isLoading.value = false;
    }
};

// Connect Google account
const connectGoogleAccount = () => {
    // Redirect to Google OAuth
    window.location.href = '/user/google/redirect';
};

// Check credentials on component mount
onMounted(() => {
    checkGoogleCredentials();
});
</script>

<template>
    <!-- Always show during development -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 bg-white border-b border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-lg font-medium text-gray-900">Connect your Google account</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        To use Google Chat features for notes and standups, you need to connect your Google account.
                    </p>
                </div>
                <div class="ml-4">
                    <button
                        type="button"
                        @click="connectGoogleAccount"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        Connect Google Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
