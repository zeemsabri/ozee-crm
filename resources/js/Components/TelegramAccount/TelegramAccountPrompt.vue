<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const hasTelegramLinked = ref(false);
const isLoading = ref(true);
const showPrompt = ref(false);

// Check if the user has Telegram linked
const checkTelegramStatus = async () => {
    try {
        isLoading.value = true;
        // Assuming we have an endpoint that returns if user has telegram_account
        const response = await axios.get('/api/me/status');
        hasTelegramLinked.value = !!response.data.telegram_account;
        showPrompt.value = !hasTelegramLinked.value;
    } catch (error) {
        console.error('Error checking Telegram status:', error);
        showPrompt.value = true;
    } finally {
        isLoading.value = false;
    }
};

const goToProfile = () => {
    window.location.href = '/profile';
};

onMounted(() => {
    checkTelegramStatus();
});
</script>

<template>
    <div v-if="showPrompt && !isLoading" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 border-l-4 border-sky-500">
        <div class="p-6 bg-white border-b border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-sky-100 rounded-md p-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-lg font-medium text-gray-900">Link your Telegram account</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Get instant notifications and manage your tasks directly from Telegram by linking your account.
                    </p>
                </div>
                <div class="ml-4">
                    <button
                        type="button"
                        @click="goToProfile"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500"
                    >
                        Link Telegram
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
