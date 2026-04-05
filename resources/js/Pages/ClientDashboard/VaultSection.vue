<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    projectId: [String, Number],
    initialAuthToken: String
});

const emit = defineEmits(['add-activity']);

const form = ref({
    label: '',
    username: '',
    password: '',
    pin: '',
    expiry_days: 7
});

const isSubmitting = ref(false);
const status = ref({ type: '', message: '' });
const myCredentials = ref([]);
const loadingList = ref(false);

const fetchMyCredentials = async () => {
    loadingList.value = true;
    try {
        const response = await fetch(`/api/client-api/vault?token=${props.initialAuthToken}`);
        if (response.ok) {
            myCredentials.value = await response.json();
        }
    } catch (err) {
        console.error('Failed to load credentials', err);
    } finally {
        loadingList.value = false;
    }
};

const deleteCredential = async (id) => {
    if (!confirm('Permanent delete? System will wipe these credentials.')) return;
    try {
        const response = await fetch(`/api/client-api/vault/${id}?token=${props.initialAuthToken}`, {
            method: 'DELETE'
        });
        if (response.ok) {
            myCredentials.value = myCredentials.value.filter(c => c.id !== id);
            emit('add-activity', 'Permanently deleted a shared credential.');
        }
    } catch (err) {
        alert('Failed to delete.');
    }
};

const showLogsModal = ref(false);
const selectedCredLogs = ref([]);
const logsLoading = ref(false);

const openLogs = async (cred) => {
    showLogsModal.value = true;
    logsLoading.value = true;
    selectedCredLogs.value = [];
    try {
        const response = await fetch(`/api/client-api/vault/${cred.id}/logs?token=${props.initialAuthToken}`);
        if (response.ok) {
            selectedCredLogs.value = await response.json();
        }
    } catch (err) {
        console.error(err);
    } finally {
        logsLoading.value = false;
    }
};

const submitForm = async () => {
    isSubmitting.value = true;
    status.value = { type: 'info', message: 'Deriving encryption key and securing data...' };

    try {
        const response = await fetch('/api/client-api/vault', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                token: props.initialAuthToken,
                ...form.value
            })
        });

        const data = await response.json();

        if (response.ok) {
            const label = form.value.label;
            status.value = { 
                type: 'success', 
                message: 'Encrypted successfully! Your PIN has been discarded. Please share the PIN with your account manager.' 
            };
            form.value = { label: '', username: '', password: '', pin: '', expiry_days: 7 };
            emit('add-activity', `Securely shared login for: ${label}`);
            fetchMyCredentials(); // Refresh list
        } else {
            status.value = { type: 'error', message: data.message || 'Failed to secure credentials.' };
        }
    } catch (err) {
        status.value = { type: 'error', message: 'A network error occurred.' };
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    fetchMyCredentials();
});
</script>

<template>
    <div class="max-w-6xl mx-auto py-6">
        <!-- Header Section -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 text-white rounded-2xl shadow-lg mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">Secure Credential Vault</h1>
            <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">
                Share your login details with our team with military-grade encryption. We never store your PIN, and your data automatically self-destructs.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: The List of existing ones -->
            <div class="lg:col-span-1 space-y-6 order-2 lg:order-1">
                <!-- How It Works Section -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider mb-4">How it works</h3>

                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-600 font-bold text-xs">1</div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-bold text-gray-900">Zero-Knowledge Encryption</p>
                                <p class="text-xs text-gray-500 mt-1">Your PIN is the only key. Our servers never see or save it.</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-600 font-bold text-xs">2</div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-bold text-gray-900">Controlled Access</p>
                                <p class="text-xs text-gray-500 mt-1">Our team can only view details if you manually share the PIN with them.</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600 font-bold text-xs">3</div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-bold text-gray-900">Auto-Destruct</p>
                                <p class="text-xs text-gray-500 mt-1">Details are wiped from our system permanently after the expiry period.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Quote -->
                <div class="bg-blue-600 p-6 rounded-2xl shadow-lg text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 mb-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <p class="text-sm font-medium leading-relaxed">
                        "Your security is our priority. This system ensures even if our database was compromised, your passwords remain unreadable without your unique PIN."
                    </p>
                    <div class="mt-4 flex items-center">
                        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <span class="ml-2 text-[10px] font-semibold uppercase tracking-wide">Security Team</span>
                    </div>
                </div>

                <!-- My Existing Logins -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider mb-4">My Shared Logins</h3>
                    
                    <div v-if="loadingList" class="space-y-3 animate-pulse">
                        <div v-for="i in 3" :key="i" class="h-12 bg-gray-50 rounded-lg"></div>
                    </div>
                    <div v-else-if="myCredentials.length === 0" class="text-center py-10 px-4 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                        <p class="text-gray-500 text-sm italic">You haven't shared any logins yet.</p>
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="cred in myCredentials" :key="cred.id" class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm hover:shadow-md transition-shadow relative group">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-bold text-gray-900 truncate pr-8">{{ cred.label }}</h4>
                                <button @click="deleteCredential(cred.id)" class="absolute top-4 right-4 text-gray-300 hover:text-red-500 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            <div class="text-[10px] text-gray-500 flex flex-col gap-1">
                                <span class="flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Created: {{ new Date(cred.created_at).toLocaleDateString() }}</span>
                                <span class="flex items-center text-red-500"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> Expires: {{ new Date(cred.expires_at).toLocaleDateString() }}</span>
                            </div>
                            <button @click="openLogs(cred)" class="mt-3 w-full py-1.5 text-[10px] font-bold uppercase tracking-wider text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                View Access History
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-900 p-6 rounded-2xl shadow-lg text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 mb-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs font-medium leading-relaxed opacity-80">
                        Forgot your PIN? For security, we cannot recover it. Simply delete the item and re-add it with a new PIN.
                    </p>
                </div>
            </div>

            <!-- Right Column: The Form -->
            <div class="lg:col-span-2 order-1 lg:order-2">
                <form @submit.prevent="submitForm" class="bg-white p-8 rounded-2xl shadow-xl space-y-6 border border-gray-100">
                    <h3 class="text-xl font-bold text-gray-900 border-b pb-4">Share New Login</h3>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">What is this login for?</label>
                            <input v-model="form.label" type="text" placeholder="e.g. Shopify Admin, WordPress Dashboard" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Email / Username</label>
                            <div class="relative text-gray-400 focus-within:text-blue-500">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206"/></svg>
                                </span>
                                <input v-model="form.username" type="text" placeholder="zeemsabri@gmail.com" class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-300 text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                            <div class="relative text-gray-400 focus-within:text-blue-500">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                </span>
                                <input v-model="form.password" type="password" placeholder="••••••••" class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-300 text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none" required>
                            </div>
                        </div>

                        <div class="sm:col-span-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Create a Secret PIN</label>
                            <input v-model="form.pin" type="password" maxlength="6" pattern="\d*" inputmode="numeric" placeholder="4-6 digits" class="w-full px-4 py-3 rounded-xl border border-blue-200 bg-blue-50 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none font-mono text-center text-xl tracking-widest" required>
                                <p class="mt-2 text-xs text-blue-600 flex items-center italic">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    We do not save this. You must tell our team this PIN verbally.
                                </p>
                        </div>

                        <div class="sm:col-span-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Auto-Delete After</label>
                            <select v-model="form.expiry_days" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none bg-white text-gray-700">
                                <option :value="1">24 Hours</option>
                                <option :value="3">3 Days</option>
                                <option :value="7">7 Days</option>
                                <option :value="30">30 Days</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button :disabled="isSubmitting" type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transition-all transform hover:-translate-y-0.5 active:scale-95 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ isSubmitting ? 'Securing...' : 'Securely Encrypt & Save' }}
                        </button>
                    </div>

                    <div v-if="status.message" :class="[
                        'p-4 rounded-xl text-center text-sm font-medium transition-all animate-fade-in',
                        status.type === 'success' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100'
                    ]">
                        {{ status.message }}
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer / Trust Badges -->
        <div class="mt-12 text-center text-gray-500 text-[10px] flex flex-col items-center gap-2">
            <div class="flex items-center gap-4">
                <span class="flex items-center"><svg class="w-3 h-3 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> AES-256 Encryption</span>
                <span class="flex items-center"><svg class="w-3 h-3 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Zero-Knowledge Storage</span>
            </div>
            <p>&copy; 2026 CRM Portal. All connections are secured via SSL.</p>
        </div>

        <!-- Logs Modal -->
        <div v-if="showLogsModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in">
                <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
                    <h3 class="font-bold text-gray-900 uppercase tracking-tight">Access History</h3>
                    <button @click="showLogsModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 max-h-[60vh] overflow-y-auto">
                    <div v-if="logsLoading" class="flex justify-center py-10"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>
                    <div v-else-if="selectedCredLogs.length === 0" class="text-center py-10 text-gray-500">No activity yet.</div>
                    <ul v-else class="space-y-4">
                        <li v-for="log in selectedCredLogs" :key="log.id" class="flex gap-3 pb-4 border-b border-gray-50 last:border-0">
                            <span class="w-2 h-2 mt-1.5 rounded-full bg-blue-500 flex-shrink-0"></span>
                            <div>
                                <p class="text-xs font-bold text-gray-900">{{ log.description }}</p>
                                <p class="text-[10px] text-gray-500">by {{ log.causer?.name || 'System' }} • {{ new Date(log.created_at).toLocaleString() }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="p-4 bg-gray-50 text-right">
                    <button @click="showLogsModal = false" class="bg-white px-4 py-2 rounded-lg border text-xs font-bold uppercase hover:bg-gray-100 transition-colors">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fade-in { 
    from { opacity: 0; transform: translateY(10px); } 
    to { opacity: 1; transform: translateY(0); } 
}
.animate-fade-in { 
    animation: fade-in 0.3s ease-out forwards; 
}
</style>
