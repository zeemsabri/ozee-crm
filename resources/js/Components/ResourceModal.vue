<script setup>
import { ref, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    projectId: {
        type: Number,
        required: true
    },
    resource: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close', 'saved']);

const resourceForm = ref({
    name: '',
    type: 'link', // Default to link
    url: '',
    file: null, // Holds the File object for upload
    description: ''
});

const fileInputRef = ref(null); // Reference to the file input element

// Computed property for API endpoint
const apiEndpoint = computed(() => {
    return props.resource
        ? `/api/projects/${props.projectId}/resources/${props.resource.id}`
        : `/api/projects/${props.projectId}/resources`;
});

// Computed property for HTTP method
const httpMethod = computed(() => {
    return props.resource ? 'put' : 'post';
});

// Computed property for submit button text
const submitButtonText = computed(() => {
    return props.resource ? 'Update Resource' : 'Add Resource';
});

// Computed property for success message
const successMessage = computed(() => {
    return props.resource ? 'Resource updated successfully!' : 'Resource added successfully!';
});

// Watch for the modal's show prop to reset form or populate for editing
watch(() => props.show, (newValue) => {
    if (newValue) {
        if (props.resource) {
            // Editing existing resource
            resourceForm.value = {
                name: props.resource.name,
                type: props.resource.type,
                url: props.resource.type === 'link' ? props.resource.url : '',
                file: null, // File input should be cleared for editing
                description: props.resource.description || ''
            };
        } else {
            // Adding new resource
            resourceForm.value = {
                name: '',
                type: 'link',
                url: '',
                file: null,
                description: ''
            };
        }
        // Reset file input value visually
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
    }
}, { immediate: true }); // Run immediately on component mount

// Handle file selection (local to this component)
const handleFileChange = (event) => {
    resourceForm.value.file = event.target.files[0] || null;
};

// Custom data formatting for API (especially for FormData with files)
const formatDataForApi = (formData) => {
    const data = new FormData();
    data.append('name', formData.name || '');
    data.append('type', formData.type || 'link');
    data.append('description', formData.description || '');

    if (formData.type === 'link') {
        data.append('url', formData.url || '');
    } else if (formData.type === 'file' && formData.file) {
        data.append('file', formData.file);
    }

    // For PUT/PATCH requests with FormData, Laravel expects _method field
    if (httpMethod.value === 'put' || httpMethod.value === 'patch') {
        data.append('_method', 'PUT'); // Or 'PATCH' if that's what your route uses explicitly
    }

    return data;
};

// Handle successful submission from BaseFormModal
const handleSaved = (responseData) => {
    emit('saved', responseData.resource); // Assuming API returns { success: true, resource: {...} }
};

// Handle close event from BaseFormModal
const closeModal = () => {
    emit('close');
};
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="resource ? 'Edit Resource' : 'Add Resource'"
        :api-endpoint="apiEndpoint"
        :http-method="httpMethod"
        :form-data="resourceForm"
        :submit-button-text="submitButtonText"
        :success-message="successMessage"
        :format-data-for-api="formatDataForApi"
        @close="closeModal"
        @submitted="handleSaved"
    >
        <template #default="{ errors }">
            <div class="mt-6 space-y-6">
                <!-- Resource Type Selection -->
                <div>
                    <InputLabel for="resource-type" value="Resource Type" />
                    <div class="mt-2 flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" v-model="resourceForm.type" value="link" class="form-radio text-indigo-600" />
                            <span class="ml-2 text-gray-700">Link</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" v-model="resourceForm.type" value="file" class="form-radio text-indigo-600" />
                            <span class="ml-2 text-gray-700">File</span>
                        </label>
                    </div>
                </div>

                <!-- Resource Name -->
                <div>
                    <InputLabel for="resource-name" value="Name" />
                    <TextInput
                        id="resource-name"
                        v-model="resourceForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Enter resource name"
                        required
                    />
                    <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
                </div>

                <!-- URL Input (for link type) -->
                <div v-if="resourceForm.type === 'link'">
                    <InputLabel for="resource-url" value="URL" />
                    <TextInput
                        id="resource-url"
                        v-model="resourceForm.url"
                        type="url"
                        class="mt-1 block w-full"
                        placeholder="https://example.com"
                        required
                    />
                    <InputError :message="errors.url ? errors.url[0] : ''" class="mt-2" />
                </div>

                <!-- File Input (for file type) -->
                <div v-if="resourceForm.type === 'file'">
                    <InputLabel for="resource-file" value="File" />
                    <input
                        id="resource-file"
                        ref="fileInputRef"
                        type="file"
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        @change="handleFileChange"
                        :required="!resource || resourceForm.type === 'file'"
                    />
                    <InputError :message="errors.file ? errors.file[0] : ''" class="mt-2" />
                </div>

                <!-- Description -->
                <div>
                    <InputLabel for="resource-description" value="Description (Optional)" />
                    <textarea
                        id="resource-description"
                        v-model="resourceForm.description"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows="3"
                        placeholder="Enter a description for this resource"
                    ></textarea>
                    <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>

<style scoped>
/* No specific scoped styles needed here, as styling is handled by Tailwind and the custom components. */
</style>
