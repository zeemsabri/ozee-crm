<script setup>
import { ref, inject } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    initialAuthToken: {
        type: String,
        required: true,
    },
    projectId: {
        type: [String, Number],
        required: true,
    },
});

const emits = defineEmits(['update:isOpen', 'task-created-success']);

const newTaskForm = ref({
    title: '',
    description: '',
    due_date: '',
});
const isSubmitting = ref(false);
const formErrors = ref({});

const { showModal } = inject('modalService');

const closeModal = () => {
    emits('update:isOpen', false);
    resetForm();
    formErrors.value = {}; // Clear errors on close
};

const resetForm = () => {
    newTaskForm.value = {
        title: '',
        description: '',
        due_date: '',
    };
    formErrors.value = {};
};

const handleSubmit = async () => {
    formErrors.value = {}; // Clear previous errors
    if (!newTaskForm.value.title.trim()) {
        formErrors.value.title = 'Title is required.';
        return;
    }

    isSubmitting.value = true;
    try {
        const response = await fetch(`/api/client-api/tasks`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                ...newTaskForm.value,
                project_id: props.projectId // Ensure project_id is sent, though backend also infers from auth
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422 && data.errors) {
                formErrors.value = data.errors; // Set validation errors
                showModal('Validation Error', 'Please correct the highlighted fields.', 'alert');
            } else {
                showModal('Error', data.message || 'Failed to create task.', 'alert');
            }
            throw new Error(data.message || 'API request failed.');
        }

        showModal('Success', 'New task created successfully!', 'alert');
        emits('task-created-success', data.task); // Emit the newly created task
        closeModal();
    } catch (error) {
        console.error('Error creating task:', error);
        // Error already shown by showModal in most cases
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 relative">
            <!-- Close Button -->
            <button @click="closeModal"
                    class="absolute top-3 right-4 text-gray-500 hover:text-gray-800 text-3xl transition-colors leading-none"
                    aria-label="Close"
            >&times;</button>

            <h3 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Create New Task</h3>

            <form @submit.prevent="handleSubmit">
                <div class="mb-4">
                    <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Title:</label>
                    <input type="text" id="title" v-model="newTaskForm.title"
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           :class="{'border-red-500': formErrors.title}"
                    >
                    <p v-if="formErrors.title" class="text-red-500 text-xs italic mt-1">{{ formErrors.title[0] }}</p>
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description (Optional):</label>
                    <textarea id="description" v-model="newTaskForm.description"
                              rows="4"
                              class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline resize-y"
                              :class="{'border-red-500': formErrors.description}"
                    ></textarea>
                    <p v-if="formErrors.description" class="text-red-500 text-xs italic mt-1">{{ formErrors.description[0] }}</p>
                </div>

                <div class="mb-6">
                    <label for="due_date" class="block text-gray-700 text-sm font-bold mb-2">Due Date (Optional):</label>
                    <input type="date" id="due_date" v-model="newTaskForm.due_date"
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           :class="{'border-red-500': formErrors.due_date}"
                    >
                    <p v-if="formErrors.due_date" class="text-red-500 text-xs italic mt-1">{{ formErrors.due_date[0] }}</p>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="isSubmitting"
                    >
                        <span v-if="isSubmitting">Creating...</span>
                        <span v-else>Create Task</span>
                    </button>
                    <button type="button" @click="closeModal"
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition-colors"
                            :disabled="isSubmitting"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
/* Scoped styles if needed, but Tailwind handles most of it */
</style>
