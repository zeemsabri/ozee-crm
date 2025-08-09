<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TestFormModal from '@/Components/TestFormModal.vue';

const showModal = ref(false);

const openModal = () => {
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const handleSubmitted = (data) => {
    alert('Form submitted successfully!');
    console.log('Submitted data:', data);
};
</script>

<template>
    <Head title="Form Modal Test" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Form Modal Test</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-medium mb-4">Testing BaseFormModal Component</h3>
                        <p class="mb-4">
                            This page demonstrates the BaseFormModal component using axios for API requests.
                            Click the button below to open a test form modal.
                        </p>

                        <div class="mt-4">
                            <PrimaryButton @click="openModal">
                                Open Test Form Modal
                            </PrimaryButton>
                        </div>

                        <div class="mt-8">
                            <h4 class="text-md font-medium mb-2">Implementation Notes:</h4>
                            <ul class="list-disc pl-5 space-y-2">
                                <li>
                                    The BaseFormModal component uses axios for API requests instead of Inertia.js.
                                </li>
                                <li>
                                    It supports various HTTP methods: POST, PUT, PATCH, and DELETE.
                                </li>
                                <li>
                                    It provides hooks for data formatting and pre-submission validation.
                                </li>
                                <li>
                                    It handles validation errors and displays them in the form.
                                </li>
                                <li>
                                    It emits events for form submission success and errors.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Form Modal -->
        <TestFormModal
            :show="showModal"
            @close="closeModal"
            @submitted="handleSubmitted"
        />
    </AuthenticatedLayout>
</template>
