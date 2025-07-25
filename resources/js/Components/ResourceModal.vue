<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

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
    file: null,
    description: ''
});

const errors = ref({});
const isSubmitting = ref(false);
const fileInputRef = ref(null);

// Reset form when modal is opened/closed
watch(() => props.show, (newValue) => {
    if (newValue) {
        // If editing an existing resource
        if (props.resource) {
            resourceForm.value = {
                name: props.resource.name,
                type: props.resource.type,
                url: props.resource.type === 'link' ? props.resource.url : '',
                file: null,
                description: props.resource.description || ''
            };
        } else {
            // If adding a new resource
            resourceForm.value = {
                name: '',
                type: 'link',
                url: '',
                file: null,
                description: ''
            };
        }
        errors.value = {};
    }
});

// Handle file selection
const handleFileChange = (event) => {
    resourceForm.value.file = event.target.files[0] || null;
};

// Submit the form
const submitForm = async () => {
    isSubmitting.value = true;
    errors.value = {};

    try {
        // Create FormData for file upload
        const formData = new FormData();
        formData.append('name', resourceForm.value.name);
        formData.append('type', resourceForm.value.type);
        formData.append('description', resourceForm.value.description);

        if (resourceForm.value.type === 'link') {
            formData.append('url', resourceForm.value.url);
        } else if (resourceForm.value.file) {
            formData.append('file', resourceForm.value.file);
        }

        let response;

        if (props.resource) {
            // Update existing resource
            response = await window.axios.put(
                `/api/projects/${props.projectId}/resources/${props.resource.id}`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            );
        } else {
            // Create new resource
            response = await window.axios.post(
                `/api/projects/${props.projectId}/resources`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            );
        }

        if (response.data.success) {
            emit('saved', response.data.resource);
            emit('close');
        } else {
            console.error('Error saving resource:', response.data.message);
        }
    } catch (error) {
        console.error('Error saving resource:', error);

        if (error.response && error.response.data && error.response.data.errors) {
            errors.value = error.response.data.errors;
        } else {
            errors.value = {
                general: [error.response?.data?.message || 'An error occurred while saving the resource.']
            };
        }
    } finally {
        isSubmitting.value = false;
    }
};

// Close the modal
const closeModal = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="closeModal" max-width="md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                {{ resource ? 'Edit Resource' : 'Add Resource' }}
            </h2>

            <div class="mt-6 space-y-6">
                <!-- Resource Type Selection -->
                <div>
                    <InputLabel for="resource-type" value="Resource Type" />
                    <div class="mt-2 flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" v-model="resourceForm.type" value="link" class="form-radio text-indigo-600" />
                            <span class="ml-2">Link</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" v-model="resourceForm.type" value="file" class="form-radio text-indigo-600" />
                            <span class="ml-2">File</span>
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

                <!-- General Error Message -->
                <div v-if="errors.general" class="text-red-600 text-sm">
                    {{ errors.general[0] }}
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="closeModal">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="submitForm" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Saving...' : (resource ? 'Update' : 'Add') }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
