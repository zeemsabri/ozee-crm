<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { showSuccessNotification, showErrorNotification } from '@/Utils/notification';

const user = ref(null);
const telegramAccount = ref(null);
const linkCode = ref('');
const isLoading = ref(true);
const isGenerating = ref(false);

const fetchStatus = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get('/api/me/status');
        user.value = response.data;
        telegramAccount.value = response.data.telegram_account;
        linkCode.value = response.data.telegram_link_code;
    } catch (error) {
        console.error('Error fetching Telegram status:', error);
    } finally {
        isLoading.value = false;
    }
};

const generateCode = async () => {
    try {
        isGenerating.value = true;
        const response = await axios.post(`/api/users/${user.value.id}/generate-telegram-code`);
        linkCode.value = response.data.code;
        showSuccessNotification('New code generated!');
    } catch (error) {
        console.error('Error generating Telegram code:', error);
        showErrorNotification('Failed to generate code.');
    } finally {
        isGenerating.value = false;
    }
};

onMounted(() => {
    fetchStatus();
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Telegram Integration</h2>
            <p class="mt-1 text-sm text-gray-600">
                Link your account to Telegram to receive notifications and manage tasks.
            </p>
        </header>

        <div class="mt-6 space-y-6 max-w-xl">
            <div v-if="isLoading" class="animate-pulse flex space-x-4">
                <div class="flex-1 space-y-4 py-1">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="space-y-2">
                        <div class="h-4 bg-gray-200 rounded"></div>
                        <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                    </div>
                </div>
            </div>

            <div v-else-if="telegramAccount" class="bg-sky-50 border border-sky-100 rounded-lg p-4 flex items-center">
                <div class="bg-sky-500 rounded-full p-2 mr-4">
                    <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.53-1.39.51-.46-.01-1.33-.26-1.98-.48-.8-.27-1.43-.42-1.37-.89.03-.25.38-.51 1.03-.78 4.04-1.76 6.74-2.92 8.09-3.48 3.85-1.6 4.64-1.88 5.17-1.89.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.13-.03.19z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-sky-900">Linked to Telegram</p>
                    <p class="text-xs text-sky-700">Username: @{{ telegramAccount.username || telegramAccount.first_name }}</p>
                </div>
            </div>

            <div v-else class="space-y-4">
                <div v-if="linkCode" class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Your Link Code</p>
                    <div class="text-3xl font-mono font-bold text-indigo-600 tracking-widest bg-white inline-block px-6 py-2 rounded border shadow-sm mb-4">
                        #{{ linkCode }}
                    </div>
                    <p class="text-sm text-gray-600">
                        Send this code to our <span class="font-bold">Telegram Bot</span> to verify your account.
                    </p>
                    <div class="mt-4">
                         <a href="https://t.me/ozee_web_bot" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center justify-center">
                            Open Telegram Bot
                            <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                         </a>
                    </div>
                </div>

                <div v-else class="text-center py-4">
                    <p class="text-sm text-gray-500 mb-4">You haven't linked your Telegram account yet.</p>
                </div>

                <div class="flex items-center gap-4">
                    <PrimaryButton @click="generateCode" :disabled="isGenerating">
                        {{ linkCode ? 'Regenerate Code' : 'Generate Link Code' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </section>
</template>
