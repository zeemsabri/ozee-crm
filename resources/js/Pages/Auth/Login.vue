<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link } from '@inertiajs/vue3'; // Keep Head and Link for Inertia components

import { ref, reactive } from 'vue'; // Import ref and reactive for reactive state
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
});

// Reactive state for handling errors and loading
const errors = ref({}); // To store validation errors from Laravel (e.g., { email: ['...'], password: ['...'] })
const generalError = ref(''); // To store a general error message (e.g., "Invalid credentials")
const loading = ref(false); // To manage button loading state

// Function to handle form submission
const submit = async () => {
    loading.value = true; // Set loading state to true
    errors.value = {}; // Clear previous validation errors
    generalError.value = ''; // Clear previous general error

    try {
        // Send a POST request to your Laravel API login endpoint
        // Include the remember flag from the form
        // Axios will handle sending Content-Type: application/json
        const response = await axios.post('/api/login', form);

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
        form.reset('password'); // Clear the password field after attempt
    }
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
        </form>
    </GuestLayout>
</template>
