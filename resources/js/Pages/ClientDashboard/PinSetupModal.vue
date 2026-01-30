<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    isOpen: Boolean,
    token: String,
    isReset: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'success']);

const pin = ref('');
const confirmPin = ref('');
const loading = ref(false);
const error = ref('');

const setupPin = async () => {
    if (pin.value.length < 4) {
        error.value = 'PIN must be at least 4 digits.';
        return;
    }
    if (pin.value !== confirmPin.value) {
        error.value = 'PINs do not match.';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const response = await axios.post('/api/client-api/setup-pin', {
            token: props.token,
            pin: pin.value
        });

        if (response.data.success) {
            emit('success');
            emit('close');
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to setup PIN.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="$emit('close')"></div>
        
        <!-- Modal -->
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 border border-slate-100 overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1.5 bg-indigo-600"></div>
            
            <div class="mb-6">
                <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900">{{ isReset ? 'Reset Your PIN' : 'Secure Your Access' }}</h3>
                <p class="text-slate-500 text-sm mt-1">
                    {{ isReset 
                        ? 'Create a new permanent PIN for secure access to your dashboard.' 
                        : 'Set a PIN to access your dashboard even if your link expires.' 
                    }}
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">New PIN (4-6 digits)</label>
                    <input 
                        v-model="pin"
                        type="text" 
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        placeholder="Enter 4-6 digits"
                        class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center font-mono text-xl py-3"
                        @input="pin = pin.replace(/[^0-9]/g, '')"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Confirm PIN</label>
                    <input 
                        v-model="confirmPin"
                        type="text" 
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        placeholder="Re-enter PIN"
                        class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center font-mono text-xl py-3"
                        @input="confirmPin = confirmPin.replace(/[^0-9]/g, '')"
                    >
                </div>

                <p v-if="error" class="text-red-500 text-xs font-medium">{{ error }}</p>

                <div class="pt-2 flex flex-col gap-2">
                    <button 
                        @click="setupPin"
                        :disabled="loading || pin.length < 4"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-xl transition duration-200 shadow-lg shadow-indigo-200 disabled:opacity-50"
                    >
                        Save PIN
                    </button>
                    <button 
                        @click="$emit('close')"
                        class="w-full bg-white text-slate-500 hover:text-slate-700 font-semibold py-2 px-4 rounded-xl transition duration-200 text-sm"
                    >
                        Maybe Later
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
