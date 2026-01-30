<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    message: {
        type: String,
        default: 'Something went wrong with your magic link.'
    },
    token: String,
    email: String,
    hasPin: Boolean,
});

const pin = ref('');
const manualEmail = ref('');
const error = ref('');
const loading = ref(false);
const successMessage = ref('');
const lockoutSeconds = ref(0);
let lockoutTimer = null;

const startLockoutTimer = (seconds) => {
    lockoutSeconds.value = seconds;
    if (lockoutTimer) clearInterval(lockoutTimer);
    
    lockoutTimer = setInterval(() => {
        lockoutSeconds.value--;
        if (lockoutSeconds.value <= 0) {
            clearInterval(lockoutTimer);
        }
    }, 1000);
};

onUnmounted(() => {
    if (lockoutTimer) clearInterval(lockoutTimer);
});

const activeEmail = computed(() => props.email || manualEmail.value);

const verifyPin = async () => {
    if (pin.value.length < 4) {
        error.value = 'Please enter a valid PIN.';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const response = await axios.post('/api/client-api/verify-pin', {
            token: props.token,
            email: activeEmail.value,
            pin: pin.value
        });

        if (response.data.success) {
            localStorage.setItem('client_dashboard_token', response.data.auth_token);
            // Store flag if user logged in with temporary PIN (for forcing PIN reset)
            if (response.data.used_temporary_pin) {
                localStorage.setItem('client_used_temp_pin', 'true');
            }
            // Use the signed URL from the backend to ensure proper signature validation
            window.location.href = response.data.redirect_url;
        }
    } catch (err) {
        if (err.response?.status === 429) {
            error.value = 'Too many attempts.';
            startLockoutTimer(err.response.data.lockout_seconds);
        } else {
            error.value = err.response?.data?.message || 'Invalid credentials. Please attempt again.';
        }
    } finally {
        loading.value = false;
    }
};

const requestNewLink = async () => {
    const emailToUse = activeEmail.value;
    
    if (!emailToUse || !emailToUse.includes('@')) {
        error.value = 'Please enter a valid email address.';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const response = await axios.post('/api/client-magic-link', {
            email: emailToUse
        });

        if (response.data.success) {
            successMessage.value = 'Magic link and temporary PIN sent to your email. You can login with the temporary PIN below or click the magic link in your email.';
        }
    } catch (err) {
        error.value = 'An error occurred. Please try again.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Access Issue" />

    <div class="min-h-screen flex flex-col items-center justify-center bg-slate-50 p-4">
        <div class="bg-white shadow-2xl rounded-2xl p-8 max-w-md w-full border border-slate-100">
            <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6 text-red-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-slate-900 mb-2 text-center">Access Issue</h1>
            <p class="text-slate-600 mb-8 text-center">{{ message }}</p>

            <div v-if="successMessage" class="bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 border border-emerald-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                {{ successMessage }}
            </div>

            <div v-if="error" class="bg-red-50 text-red-700 p-3 rounded-lg mb-6 text-xs font-medium border border-red-100">
                {{ error }}
            </div>

            <div class="space-y-6">
                <!-- Step 1: Manual Email if not provided -->
                <div v-if="!props.email" class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700 uppercase tracking-wider">Confirm Your Email</label>
                    <input 
                        v-model="manualEmail"
                        type="email"
                        placeholder="your@email.com"
                        class="block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-3"
                    >
                </div>

                <!-- PIN verification -->
                <div class="p-5 bg-slate-50 rounded-xl border border-slate-200">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Instance Access via PIN</label>
                    <div class="space-y-4">
                        <input 
                            v-model="pin"
                            type="password"
                            placeholder="Enter PIN"
                            maxlength="6"
                            :disabled="lockoutSeconds > 0"
                            class="block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center tracking-[0.5em] font-bold py-3 text-lg"
                        >
                        
                        <div v-if="lockoutSeconds > 0" class="p-2 bg-amber-50 text-amber-700 text-xs rounded-md border border-amber-100 flex items-center gap-2">
                             Try again in {{ lockoutSeconds }}s
                        </div>

                        <button 
                            @click="verifyPin"
                            :disabled="loading || pin.length < 4 || lockoutSeconds > 0"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition duration-200 shadow-lg shadow-indigo-100 disabled:opacity-50"
                        >
                            {{ loading ? 'Verifying...' : 'Unlock Now' }}
                        </button>
                    </div>
                </div>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-100"></div></div>
                    <div class="relative flex justify-center text-xs"><span class="px-2 bg-white text-slate-400">OR</span></div>
                </div>

                <button 
                    @click="requestNewLink"
                    :disabled="loading"
                    class="w-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold py-3 rounded-xl transition duration-200"
                >
                    Email Me a New Link
                </button>
                
                <p class="text-xs text-center text-slate-500">
                    Check your email for your permanent PIN or request a magic link to receive a temporary PIN.
                </p>
            </div>

            <p class="mt-8 text-slate-400 text-xs text-center border-t border-slate-100 pt-6 leading-relaxed">
                For security, permanent PINs can be set in your dashboard settings.<br>
                Need help? <a href="#" class="text-indigo-500 font-medium">Contact Support</a>
            </p>
        </div>
    </div>
</template>
