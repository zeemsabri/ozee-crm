<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    // canRegister prop removed - this is a closed system where only administrators can add users
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

const showClientLoginModal = ref(false);
const clientEmail = ref('');
const clientPin = ref('');
const showPinInput = ref(false);
const sendingMagicLink = ref(false);
const magicLinkSuccess = ref('');
const magicLinkError = ref('');
const verifyingPin = ref(false);
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

const handleNext = () => {
    if (!clientEmail.value || !clientEmail.value.includes('@')) {
        magicLinkError.value = 'Please enter a valid email address';
        return;
    }

    magicLinkError.value = '';
    // Security: We show PIN input for everyone to hide which emails exist or have PINs
    showPinInput.value = true;
};

const sendMagicLink = async () => {
    if (!clientEmail.value) {
        magicLinkError.value = 'Please enter your email address';
        return;
    }

    sendingMagicLink.value = true;
    magicLinkSuccess.value = '';
    magicLinkError.value = '';

    try {
        const response = await window.axios.post(
            '/api/client-magic-link',
            { email: clientEmail.value }
        );

        if (response.data.success) {
            magicLinkSuccess.value = 'Magic link and temporary PIN sent to your email. You can login with the temporary PIN below or click the magic link in your email.';
            // Keep PIN input visible so user can enter the temporary PIN
            showPinInput.value = true;
        } else {
            magicLinkError.value = response.data.message || 'Failed to send magic link';
        }
    } catch (error) {
        magicLinkError.value = error.response?.data?.message || 'An error occurred';
    } finally {
        sendingMagicLink.value = false;
    }
};

const verifyPin = async () => {
    if (!clientPin.value || clientPin.value.length < 4) {
        magicLinkError.value = 'Please enter a valid PIN';
        return;
    }

    verifyingPin.value = true;
    magicLinkError.value = '';

    try {
        const response = await window.axios.post('/api/client-api/verify-pin', {
            email: clientEmail.value,
            pin: clientPin.value
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
    } catch (error) {
        if (error.response?.status === 429) {
            magicLinkError.value = 'Too many attempts.';
            startLockoutTimer(error.response.data.lockout_seconds);
        } else {
            magicLinkError.value = error.response?.data?.message || 'Invalid credentials. Please attempt again.';
        }
    } finally {
        verifyingPin.value = false;
    }
};

const closeModal = () => {
    clientEmail.value = '';
    clientPin.value = '';
    showPinInput.value = false;
    magicLinkSuccess.value = '';
    magicLinkError.value = '';
    lockoutSeconds.value = 0;
    if (lockoutTimer) clearInterval(lockoutTimer);
    showClientLoginModal.value = false;
};

const openClientLoginModal = () => {
    closeModal();
    showClientLoginModal.value = true;
};
</script>

<template>
    <Head title="OZEE CRM" />
    <div class="bg-gray-50 min-h-screen">
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <h1 class="ml-2 text-xl font-bold text-gray-900">OZEE CRM</h1>
                </div>
                <nav v-if="canLogin" class="flex space-x-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Dashboard
                    </Link>

                    <template v-else>
                        <div class="flex space-x-4">
                            <Link
                                :href="route('login')"
                                class="px-4 py-2 text-sm font-medium text-indigo-600 bg-white border border-indigo-600 rounded-md hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Team Log in
                            </Link>
                            <button
                                @click="openClientLoginModal"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Client Login
                            </button>
                        </div>
                    </template>
                </nav>
            </div>
        </header>

        <div class="bg-indigo-700 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <h2 class="text-3xl md:text-4xl font-bold mb-4">Welcome to OZEE CRM!</h2>
                        <p class="text-lg md:text-xl mb-6">Your central hub for managing client interactions, projects, and communications. This system is exclusively for our invited employees, contractors, and clients to collaborate seamlessly.</p>
                        <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                            <Link
                                :href="route('login')"
                                class="px-6 py-3 text-base font-medium text-indigo-700 bg-white rounded-md shadow-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
                            >
                                Access Your Account
                            </Link>
                        </div>
                    </div>
                    <div class="hidden md:block">
                        <img src="https://cdn.pixabay.com/photo/2018/03/10/12/00/teamwork-3213924_1280.jpg" alt="Collaborative Teamwork" class="rounded-lg shadow-xl">
                    </div>
                </div>
            </div>
        </div>

        <div class="py-12 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                        Comprehensive Tools for Your Workflow
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        Everything you need to manage your projects, communications, and client relationships efficiently.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">Centralized Communication</h3>
                        <p class="mt-2 text-base text-gray-600">
                            Effortlessly manage all your client and internal communications in one place, ensuring everyone is on the same page.
                        </p>
                    </div>

                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">Streamlined Approvals</h3>
                        <p class="mt-2 text-base text-gray-600">
                            Efficiently manage any approval processes, whether for documents, content, or other critical items, ensuring quick turnaround times.
                        </p>
                    </div>

                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">Comprehensive Project & Client Management</h3>
                        <p class="mt-2 text-base text-gray-600">
                            Organize all your projects and client information, keeping data accessible and relationships strong.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="py-12 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                        Benefits for Everyone in Your Ecosystem
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        OZEE CRM provides tailored value for employees, contractors, and clients alike.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">For Employees & Contractors</h3>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Efficiently manage your tasks and projects
                            </li>
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Collaborate seamlessly on client interactions
                            </li>
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Access all necessary information in one place
                            </li>
                        </ul>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">For Managers</h3>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Oversee projects and team progress
                            </li>
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Ensure consistent communication quality
                            </li>
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Gain insights with comprehensive reporting
                            </li>
                        </ul>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">For Clients</h3>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Stay informed on project progress
                            </li>
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Easily access relevant project documents and communications
                            </li>
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Direct and clear communication channels
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-indigo-600 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
                <h2 class="text-2xl md:text-3xl font-bold mb-4">Ready to enhance your team's collaboration and client management?</h2>
                <p class="text-lg mb-8">If you've received an invitation, please log in to access OZEE CRM.</p>
                <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <Link
                        :href="route('login')"
                        class="px-6 py-3 text-base font-medium text-white bg-indigo-500 border border-white rounded-md shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Log In
                    </Link>
                </div>
            </div>
        </div>

        <footer class="bg-gray-800 text-white py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <h3 class="ml-2 text-xl font-bold">OZEE CRM</h3>
                        </div>
                        <p class="mt-2 text-gray-400">
                            Your comprehensive CRM for seamless internal operations and client management.
                        </p>
                    </div>
                    <div class="mt-8 md:mt-0 text-right">

                    </div>
                </div>
            </div>
        </footer>

        <!-- Client Login Modal -->
        <Modal :show="showClientLoginModal" @close="closeModal">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Client Login</h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Success Message -->
                <div v-if="magicLinkSuccess" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md">
                    {{ magicLinkSuccess }}
                </div>

                <!-- Error Message -->
                <div v-if="magicLinkError" class="mb-4 p-3 bg-red-100 text-red-800 rounded-md">
                    {{ magicLinkError }}
                </div>

                <div class="mb-4">
                    <InputLabel for="client-email" value="Email Address" />
                    <input
                        id="client-email"
                        type="email"
                        v-model="clientEmail"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                        placeholder="your@email.com"
                        @keyup.enter="!showPinInput ? handleNext() : null"
                    />
                </div>

                <div v-if="showPinInput" class="mb-4">
                    <InputLabel for="client-pin" value="Enter PIN (Permanent or Temporary)" />
                    <input
                        id="client-pin"
                        type="password"
                        v-model="clientPin"
                        maxlength="6"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full text-center tracking-[0.5em] font-bold text-lg"
                        placeholder=""
                        @keyup.enter="verifyPin"
                    />
                    <div v-if="lockoutSeconds > 0" class="mt-2 p-2 bg-amber-50 text-amber-700 text-xs rounded-md border border-amber-100 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Try again in {{ lockoutSeconds }}s
                    </div>
                </div>

                <div class="mt-6">
                    <!-- Step 1: Initial State -->
                    <div v-if="!showPinInput && !magicLinkSuccess">
                        <PrimaryButton
                            @click="handleNext"
                            class="bg-indigo-600 hover:bg-indigo-700 w-full justify-center py-2.5"
                        >
                            Next Step
                        </PrimaryButton>
                    </div>

                    <!-- Step 2: PIN Input State -->
                    <div v-if="showPinInput" class="space-y-4">
                        <PrimaryButton
                            @click="verifyPin"
                            :disabled="verifyingPin || clientPin.length < 4 || lockoutSeconds > 0"
                            class="bg-indigo-600 hover:bg-indigo-700 w-full justify-center py-2.5"
                        >
                            {{ verifyingPin ? 'Verifying...' : 'Log in' }}
                        </PrimaryButton>

                        <div class="relative">
                            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                            <div class="relative flex justify-center text-xs"><span class="px-2 bg-white text-gray-400">OR</span></div>
                        </div>

                        <SecondaryButton
                            @click="sendMagicLink"
                            :disabled="sendingMagicLink"
                            class="w-full justify-center py-2.5"
                        >
                            {{ sendingMagicLink ? 'Sending...' : 'Email me a new Magic Link' }}
                        </SecondaryButton>

                        <p class="text-xs text-center text-gray-500">
                             Check your email for your permanent PIN or request a magic link to receive a temporary PIN.
                        </p>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
