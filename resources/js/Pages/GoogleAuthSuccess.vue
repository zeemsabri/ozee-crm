<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    message: String,
    authorized_email: String,
    note: String,
    error: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <Head title="Google Authorization Success" />

    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            <!-- Success State -->
            <div v-if="!error" class="mb-6 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2 class="mt-4 text-2xl font-bold text-gray-900">Google Authorization Successful!</h2>
            </div>

            <!-- Error State -->
            <div v-else class="mb-6 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2 class="mt-4 text-2xl font-bold text-gray-900">Google Authorization Failed</h2>
            </div>

            <div class="mb-6">
                <!-- Success Message -->
                <div v-if="!error" class="bg-green-50 border-l-4 border-green-400 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">
                                {{ message }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-else class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">
                                {{ message }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Only show authorized email if it exists -->
                <div v-if="authorized_email" class="mb-4">
                    <p class="text-sm text-gray-600 font-semibold">Authorized Email:</p>
                    <p class="text-sm text-gray-800">{{ authorized_email }}</p>
                </div>

                <div class="mb-4">
                    <p class="text-sm" :class="error ? 'text-red-600' : 'text-gray-600'">{{ note }}</p>
                </div>
            </div>

            <div class="flex items-center justify-center">
                <Link
                    :href="route('dashboard')"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                >
                    Return to Dashboard
                </Link>
            </div>
        </div>
    </div>
</template>
