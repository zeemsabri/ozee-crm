<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import TelegramIntegrationForm from '@/Pages/Profile/Partials/TelegramIntegrationForm.vue';
import BaseModal from './BaseModal.vue';

const props = defineProps({
    initialAuthToken: String,
    botName: {
        type: String,
        default: 'ozee_web_bot'
    }
});

const isLinked = ref(true); // Default to true to avoid flicker
const isLoading = ref(true);
const showModal = ref(false);

const checkStatus = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get('/api/client-api/me/status', {
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`
            }
        });
        isLinked.value = !!response.data.telegram_account;
    } catch (error) {
        console.error('Error checking Telegram status:', error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    checkStatus();
});
</script>

<template>
    <div v-if="!isLoading && !isLinked" class="bg-gradient-to-r from-sky-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white mb-6 relative overflow-hidden">
        <!-- Decorative Background Element -->
        <div class="absolute top-0 right-0 -mt-4 -mr-4 bg-white opacity-10 w-32 h-32 rounded-full"></div>
        
        <div class="flex flex-col sm:flex-row items-center justify-between relative z-10">
            <div class="flex items-center mb-4 sm:mb-0 text-center sm:text-left">
                <div class="bg-white p-3 rounded-full mr-4 hidden sm:block">
                    <svg class="h-8 w-8 text-sky-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.53-1.39.51-.46-.01-1.33-.26-1.98-.48-.8-.27-1.43-.42-1.37-.89.03-.25.38-.51 1.03-.78 4.04-1.76 6.74-2.92 8.09-3.48 3.85-1.6 4.64-1.88 5.17-1.89.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.13-.03.19z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold">Stay Updated with Telegram</h3>
                    <p class="text-sky-100 opacity-90 max-w-md">
                        Link your Telegram account to receive instant notifications about project updates, approvals, and messages.
                    </p>
                </div>
            </div>
            
            <button 
                @click="showModal = true" 
                class="bg-white text-indigo-600 px-6 py-2.5 rounded-lg font-bold shadow-md hover:bg-sky-50 transition duration-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-sky-600"
            >
                Link Account Now
            </button>
        </div>

        <!-- Integration Modal -->
        <BaseModal 
            :isOpen="showModal" 
            title="Telegram Integration" 
            @close="showModal = false"
        >
            <div class="p-1">
                <TelegramIntegrationForm 
                    statusUrl="/api/client-api/me/status"
                    generateUrl="/api/client-api/me/generate-telegram-code"
                    :botName="props.botName"
                    :authToken="props.initialAuthToken"
                />
            </div>
        </BaseModal>
    </div>
</template>
