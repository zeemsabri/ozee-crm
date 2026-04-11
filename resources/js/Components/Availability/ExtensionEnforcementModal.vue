<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { useToast } from 'vue-toast-notification';
import { useExtensionStatus } from '@/Composables/useExtensionStatus';

const toast = useToast();
const showModal = ref(false);
const isChecking = ref(false);
const canBypass = ref(false);
const { status, shouldShowReminder, refreshStatus } = useExtensionStatus();

const checkExtensionStatus = async (isManual = false) => {
    if (isManual) {
        isChecking.value = true;
    }

    try {
        const data = await refreshStatus();
        if (!data) {
            return;
        }

        canBypass.value = data.can_bypass;

        // If extension is mandatory and user is not online
        if (data.extension_mandatory && !data.is_online) {
            // Check if we should ignore for now (snooze)
            const snoozeUntil = localStorage.getItem('extension_popup_snooze_until');
            const now = new Date().getTime();

            if (!isManual && snoozeUntil && now < parseInt(snoozeUntil)) {
                console.log('Enforcement: Still snoozed until ' + new Date(parseInt(snoozeUntil)).toLocaleTimeString());
                showModal.value = false;
            } else {
                showModal.value = true;
            }
            
            if (isManual) {
                toast.error('You are still appearing as offline. Please ensure the extension is active.', {
                    position: 'top-right'
                });
            }
        } else {
            showModal.value = false;
            if (isManual) {
                toast.success('Extension detected! You are now online.', {
                    position: 'top-right'
                });
            }
        }
    } catch (error) {
        console.error('Error checking extension status:', error);
    } finally {
        if (isManual) {
            isChecking.value = false;
        }
    }
};

watch(shouldShowReminder, (value) => {
    if (!value) {
        showModal.value = false;
        return;
    }

    checkExtensionStatus();
}, { immediate: true });

watch(status, (value) => {
    canBypass.value = value.can_bypass;
});

const close = () => {
    // Snooze for 5 minutes
    const snoozeTime = new Date().getTime() + 300000;
    localStorage.setItem('extension_popup_snooze_until', snoozeTime.toString());
    showModal.value = false;
};
</script>

<template>
    <Modal :show="showModal" @close="close" max-width="md" closeable>
        <div class="p-6">
            <div class="flex items-center justify-center mb-6">
                <div class="w-16 h-16 rounded-full flex items-center justify-center" :class="canBypass ? 'bg-amber-100' : 'bg-red-100'">
                    <svg class="w-10 h-10" :class="canBypass ? 'text-amber-600' : 'text-red-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>

            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">
                {{ canBypass ? 'Extension Inactive' : 'Extension Required' }}
            </h3>
            
            <p class="text-sm text-gray-600 text-center mb-6">
                <template v-if="canBypass">
                    As an administrator, you've bypassed the login check, but the extension is still inactive. Please activate it to track your activity.
                </template>
                <template v-else>
                    Your account requires the Chrome extension to be active. We've detected that you are currently <strong>offline</strong> or the extension is not running.
                </template>
            </p>

            <div class="space-y-3">
                <a :href="$page.props.chrome_extension_link || '#'" 
                   target="_blank"
                   rel="noopener noreferrer"
                   class="w-full inline-flex justify-center items-center px-4 py-3 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition-colors shadow-md">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-4H8l4-4 4 4h-3v4h-2z"/>
                    </svg>
                    Download Extension
                </a>
                
                <button @click="checkExtensionStatus(true)" 
                        :disabled="isChecking"
                        class="w-full inline-flex justify-center items-center px-4 py-3 bg-white text-gray-700 border border-gray-300 rounded-xl text-sm font-bold hover:bg-gray-50 transition-colors disabled:opacity-50">
                    <svg v-if="isChecking" class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ isChecking ? 'Verifying...' : "I've turned it on, Refresh" }}
                </button>

                <button @click="close" 
                        class="w-full text-center text-xs text-gray-400 hover:text-gray-600 transition-colors">
                    Close for now (will remind again in 5 mins)
                </button>
            </div>
        </div>
    </Modal>
</template>
