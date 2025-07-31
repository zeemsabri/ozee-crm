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
    urgency: 'medium', // New: Default urgency
    attachment: null, // New: For file upload
});
const isSubmitting = ref(false);
const formErrors = ref({});
const selectedFile = ref(null); // New: To display selected file name

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
        urgency: 'medium',
        attachment: null,
    };
    selectedFile.value = null;
    formErrors.value = {};
};

const handleFileChange = (event) => {
    const file = event.target.files ? event.target.files[0] : null;
    if (file) {
        // Basic validation: max 5MB, common document/image types
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            showModal('File Too Large', 'Please select a file smaller than 5MB.', 'alert');
            selectedFile.value = null;
            newTaskForm.value.attachment = null;
            return;
        }
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowedTypes.includes(file.type)) {
            showModal('Unsupported File Type', 'Please upload a PDF, image (JPG, PNG, GIF), or Word document.', 'alert');
            selectedFile.value = null;
            newTaskForm.value.attachment = null;
            return;
        }

        selectedFile.value = file.name;
        newTaskForm.value.attachment = file;
    } else {
        selectedFile.value = null;
        newTaskForm.value.attachment = null;
    }
};


const handleSubmit = async () => {
    formErrors.value = {}; // Clear previous errors
    if (!newTaskForm.value.title.trim()) {
        formErrors.value.title = 'Title is required.';
        return;
    }

    isSubmitting.value = true;
    try {
        const formData = new FormData();
        formData.append('title', newTaskForm.value.title);
        formData.append('description', newTaskForm.value.description || '');
        formData.append('urgency', newTaskForm.value.urgency);
        formData.append('project_id', props.projectId);

        if (newTaskForm.value.attachment) {
            formData.append('attachment', newTaskForm.value.attachment);
        }

        const response = await fetch(`/api/client-api/tasks`, {
            method: 'POST',
            headers: {
                // 'Content-Type': 'multipart/form-data' is NOT set here; browser sets it automatically with FormData
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Accept': 'application/json'
            },
            body: formData,
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
    <div v-if="isOpen" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-[100] p-4 font-inter">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 relative transform transition-all duration-300 scale-100 opacity-100">
            <!-- Close Button -->
            <button @click="closeModal"
                    class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-4xl leading-none transition-colors duration-200"
                    aria-label="Close"
            >&times;</button>

            <h3 class="text-2xl font-bold text-gray-900 mb-6 text-center">Create New Task</h3>

            <form @submit.prevent="handleSubmit">
                <div class="mb-5">
                    <label for="title" class="block text-gray-800 text-sm font-semibold mb-2">Task Title:</label>
                    <input type="text" id="title" v-model="newTaskForm.title"
                           class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
                           :class="{'border-red-500': formErrors.title}"
                           placeholder="e.g., Review website copy"
                    >
                    <p v-if="formErrors.title" class="text-red-500 text-xs italic mt-1">{{ formErrors.title[0] }}</p>
                </div>

                <div class="mb-5">
                    <label for="description" class="block text-gray-800 text-sm font-semibold mb-2">Description (Optional):</label>
                    <textarea id="description" v-model="newTaskForm.description"
                              rows="4"
                              class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200 resize-y"
                              :class="{'border-red-500': formErrors.description}"
                              placeholder="Provide more details about the task..."
                    ></textarea>
                    <p v-if="formErrors.description" class="text-red-500 text-xs italic mt-1">{{ formErrors.description[0] }}</p>
                </div>

                <!-- Urgency Selector -->
                <div class="mb-5">
                    <label for="urgency" class="block text-gray-800 text-sm font-semibold mb-2">Urgency:</label>
                    <select id="urgency" v-model="newTaskForm.urgency"
                            class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
                            :class="{'border-red-500': formErrors.urgency}"
                    >
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                    <p v-if="formErrors.urgency" class="text-red-500 text-xs italic mt-1">{{ formErrors.urgency[0] }}</p>
                </div>

                <!-- File Upload -->
                <div class="mb-6">
                    <label for="attachment" class="block text-gray-800 text-sm font-semibold mb-2">Attach Document (Optional):</label>
                    <input type="file" id="attachment" @change="handleFileChange"
                           class="block w-full text-sm text-gray-700
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-lg file:border-0
                                file:text-sm file:font-semibold
                                file:bg-indigo-50 file:text-indigo-700
                                hover:file:bg-indigo-100 transition-colors duration-200
                           "
                           :class="{'border-red-500': formErrors.attachment}"
                    >
                    <p v-if="selectedFile" class="text-gray-600 text-sm mt-2">Selected file: <span class="font-medium">{{ selectedFile }}</span></p>
                    <p v-if="formErrors.attachment" class="text-red-500 text-xs italic mt-1">{{ formErrors.attachment[0] }}</p>
                </div>

                <div class="flex items-center justify-end space-x-4">
                    <button type="button" @click="closeModal"
                            class="px-6 py-3 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="isSubmitting"
                    >
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200 ease-in-out transform hover:scale-105 shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="isSubmitting"
                    >
                        <span v-if="isSubmitting" class="flex items-center">
                            <svg class="animate-spin h-5 w-5 text-white mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Creating...
                        </span>
                        <span v-else>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus-circle mr-2 inline-block"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
                            Create Task
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}
</style>
