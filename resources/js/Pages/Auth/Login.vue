<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, usePage } from '@inertiajs/vue3'; // Keep Head, Link, and usePage for Inertia components

import { ref, reactive, onMounted } from 'vue'; // Import ref, reactive, onMounted for reactive state
import axios from 'axios'; // Import axios for making HTTP requests

// Props received from Laravel controller (default for Breeze login page)
defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: '',
    },
});

// Reactive state for the login form
const form = reactive({
    email: '',
    password: '',
    remember: false, // For "Remember me" functionality
    timezone: '',
    bypass_extension: false,
});

// Reactive state for handling errors and loading
const errors = ref({}); // To store validation errors from Laravel (e.g., { email: ['...'], password: ['...'] })
const generalError = ref(''); // To store a general error message (e.g., "Invalid credentials")
const loading = ref(false); // To manage button loading state

// Detect and set timezone
onMounted(() => {
    console.log('Login Mount: Chrome Extension Link =', usePage().props.chrome_extension_link);
    try {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        form.timezone = tz;
    } catch (e) {
        form.timezone = '';
    }
});

// Function to handle form submission
const submit = async () => {
    loading.value = true; // Set loading state to true
    errors.value = {}; // Clear previous validation errors
    generalError.value = ''; // Clear previous general error

    try {
        // Send a POST request to your Laravel login endpoint
        // Include the remember flag from the form
        // Axios will handle sending Content-Type: application/json
        const response = await axios.post('/login', form);

        // Extract token and user data from the successful API response
        const token = response.data.token;
        const user = response.data.user;

        // Store authentication details in localStorage
        localStorage.setItem('authToken', token);
        localStorage.setItem('userRole', user.role); // Store user's role for frontend logic
        localStorage.setItem('userId', user.id);     // Store user's ID
        localStorage.setItem('userEmail', user.email); // Store user's email

        // Also store whether this is a remembered session
        localStorage.setItem('remembered', form.remember ? 'true' : 'false');

        // Set the Authorization header globally for all future Axios requests
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        // Redirect to the dashboard by forcing a full page reload.
        // This ensures the AuthenticatedLayout's onMounted hook runs fresh
        // and picks up the new token from localStorage.
        window.location.href = route('dashboard');

        // Note: Code after window.location.href will not be executed immediately
        // as the browser navigates away.

    } catch (error) {
        // Handle different types of errors from the API
        if (error.response) {
            if (error.response.status === 422) {
                // Validation errors (e.g., email format, password strength)
                errors.value = error.response.data.errors;
            } else if (error.response.status === 419) {
                // CSRF token mismatch / session expired
                generalError.value = 'Your session has expired. A new security token has been loaded. Please try logging in again.';
                // Fetch a fresh CSRF cookie automatically
                try {
                    await axios.get('/sanctum/csrf-cookie');
                } catch (csrfError) {
                    console.error('Failed to refresh CSRF token:', csrfError);
                }
            } else if (error.response.data.message) {
                // General error message from Laravel (e.g., "These credentials do not match our records.")
                generalError.value = error.response.data.message;
            } else {
                // Other API error without a specific message
                generalError.value = 'An unexpected API error occurred.';
                console.error('API Error during login:', error.response.data);
            }
        } else {
            // Network error or other client-side issue
            generalError.value = 'An unexpected error occurred during login. Please check your network connection.';
            console.error('Network or client-side error during login:', error);
        }

        // Clear any potentially lingering or incorrect token/role if login failed
        localStorage.removeItem('authToken');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userId');
        localStorage.removeItem('userEmail');
        delete axios.defaults.headers.common['Authorization']; // Remove header for safety
    } finally {
        loading.value = false; // Reset loading state
        form.password = ''; // Clear the password field after attempt
        // We do NOT reset bypass_extension here, so the next click with it works
    }
};

const loginWithBypass = () => {
    form.bypass_extension = true;
    submit();
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="errors.email ? errors.email[0] : ''" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="errors.password ? errors.password[0] : ''" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600"
                    >Remember me</span
                    >
                </label>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Forgot your password?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': loading }"
                    :disabled="loading"
                >
                    Log in
                </PrimaryButton>
            </div>
            <div v-if="generalError" class="mt-4 text-sm text-red-600 text-center">
                {{ generalError }}
            </div>

            <div v-if="errors.extension_bypass_required" class="mt-6 p-4 bg-amber-50 rounded-xl border border-amber-200">
                <div class="flex items-center mb-2">
                    <svg class="w-5 h-5 text-amber-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <p class="text-sm font-bold text-amber-800">Extension Check Failed</p>
                </div>
                <p class="text-xs text-amber-700 mb-4">
                    {{ errors.extension_bypass_required[0] }}
                </p>
                <div class="flex justify-center">
                    <PrimaryButton type="button" @click="loginWithBypass">
                        Login Anyway (Bypass)
                    </PrimaryButton>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <p class="text-xs text-gray-400 mb-3 uppercase font-bold tracking-widest">Chrome Extension Required?</p>
                <a :href="$page.props.chrome_extension_link || '#'" 
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center px-4 py-2 bg-indigo-50 text-indigo-700 rounded-xl text-xs font-bold hover:bg-indigo-100 transition-colors shadow-sm ring-1 ring-inset ring-indigo-700/10">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-4H8l4-4 4 4h-3v4h-2z"/>
                    </svg>
                    Download Chrome Extension
                </a>
                <p class="text-[10px] text-gray-400 mt-2">Required for users with mandatory extension policy enabled</p>
            </div>
        </form>
    </GuestLayout>
</template>
