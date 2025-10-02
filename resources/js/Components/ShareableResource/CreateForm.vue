<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import BaseFormModal from '@/Components/BaseFormModal.vue'; // Adjust path if necessary
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TagInput from '@/Components/TagInput.vue'; // Adjust path if necessary
import Checkbox from '@/Components/Checkbox.vue'; // Assuming you have a Checkbox component

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    // The API endpoint for creating shareable resources
    apiEndpoint: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['close', 'resourceCreated']);

// Reactive form data for the new shareable resource
const form = reactive({
    title: '',
    description: '',
    url: '',
    type: 'website', // Default type
    thumbnail_url: '',
    // Deprecated per three-option visibility; keep for backward compatibility
    visible_to_client: true, // Default
    visibility: 'client',
    tags: [], // Array to hold tags from TagInput component (expected format: [{ id: 1, name: 'Tag Name' }] or [{ name: 'new_tag_timestamp' }])
});

// Options for the 'type' dropdown, fetched from API with fallback
const defaultResourceTypeOptions = [
    { value: 'website', label: 'Website' },
    { value: 'youtube', label: 'YouTube Video' },
    { value: 'document', label: 'Document' },
    { value: 'image', label: 'Image' },
    { value: 'other', label: 'Other' },
];
const resourceTypeOptions = ref(defaultResourceTypeOptions);

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/options/shareable_resource_types');
        if (Array.isArray(data) && data.length) {
            resourceTypeOptions.value = data;
        }
    } catch (e) {
        // Fallback to defaults if API fails
        resourceTypeOptions.value = defaultResourceTypeOptions;
    }
});

/**
 * Formats the data before sending it to the API.
 * This function now correctly prepares the 'tags' array to match
 * the expected input format for your 'ProcessTags' middleware.
 * It sends either the tag ID (for existing tags) or the 'new_' prefixed name (for new tags).
 *
 * @param {Object} data - The raw form data.
 * @returns {Object} The formatted data ready for API submission.
 */
const formatDataForApi = (data) => {
    const payload = {
        title: data.title,
        description: data.description,
        url: data.url,
        type: data.type,
        thumbnail_url: data.thumbnail_url,
        visibility: data.visibility, // 'client' | 'team' | 'private'
    };
    // Optional: map tags to tag_ids if provided as array of ids or objects
    if (Array.isArray(data.tags) && data.tags.length) {
        const tagIds = data.tags.map(t => (typeof t === 'object' && t !== null ? t.id : t)).filter(Boolean);
        payload.tag_ids = tagIds;
    }
    return payload;
};

/**
 * Handles the successful submission of the form from BaseFormModal.
 * Emits 'resourceCreated' event with the new resource data and closes the modal.
 * @param {Object} newResource - The data of the newly created resource.
 */
const handleSubmitted = (newResource) => {
    emit('resourceCreated', newResource);
    handleClose(); // Close the modal and reset form after successful submission
};

/**
 * Handles the modal close event.
 * Resets the form and emits the 'close' event.
 * This is crucial for clearing the TagInput's state.
 */
const handleClose = () => {
    // Reset form fields to their initial state
    form.title = '';
    form.description = '';
    form.url = '';
    form.type = 'website';
    form.thumbnail_url = '';
    form.visible_to_client = true;
    form.tags = []; // IMPORTANT: Reset the tags array to clear TagInput
    emit('close');
};
</script>

<template>
    <BaseFormModal
        :show="props.show"
        :title="'Add New Shareable Resource'"
        :apiEndpoint="props.apiEndpoint"
        httpMethod="post"
        :formData="form"
        :formatDataForApi="formatDataForApi"
        :submitButtonText="'Create Resource'"
        :successMessage="'Shareable resource created successfully!'"
        @submitted="handleSubmitted"
        @close="handleClose"
    >
        <!-- Slot content for the form fields, receiving validation errors -->
        <template #default="{ errors }">
            <div class="space-y-4">
                <!-- Title -->
                <div>
                    <InputLabel for="title" value="Title" class="mb-1" />
                    <TextInput
                        id="title"
                        type="text"
                        class="mt-1 block w-full rounded-lg shadow-sm"
                        v-model="form.title"
                        required
                    />
                    <InputError :message="errors.title ? errors.title[0] : ''" class="mt-2" />
                </div>

                <!-- URL -->
                <div>
                    <InputLabel for="url" value="Resource URL" class="mb-1" />
                    <TextInput
                        id="url"
                        type="url"
                        class="mt-1 block w-full rounded-lg shadow-sm"
                        v-model="form.url"
                        required
                    />
                    <InputError :message="errors.url ? errors.url[0] : ''" class="mt-2" />
                </div>

                <!-- Type -->
                <div>
                    <InputLabel for="type" value="Resource Type" class="mb-1" />
                    <SelectDropdown
                        id="type"
                        v-model="form.type"
                        :options="resourceTypeOptions"
                        valueKey="value"
                        labelKey="label"
                        placeholder="Select Resource Type"
                        required
                        class="mt-1 block w-full"
                    />
                    <InputError :message="errors.type ? errors.type[0] : ''" class="mt-2" />
                </div>

                <!-- Thumbnail URL (Optional) -->
                <div>
                    <InputLabel for="thumbnail_url" value="Thumbnail URL (Optional)" class="mb-1" />
                    <TextInput
                        id="thumbnail_url"
                        type="url"
                        class="mt-1 block w-full rounded-lg shadow-sm"
                        v-model="form.thumbnail_url"
                    />
                    <InputError :message="errors.thumbnail_url ? errors.thumbnail_url[0] : ''" class="mt-2" />
                </div>

                <!-- Description -->
                <div>
                    <InputLabel for="description" value="Description (Optional)" class="mb-1" />
                    <textarea
                        id="description"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full"
                        v-model="form.description"
                        rows="3"
                    ></textarea>
                    <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
                </div>

                <!-- Tags Input Field -->
                <div>
                    <TagInput
                        v-model="form.tags"
                        label="Associated Tags"
                        placeholder="Search or add tags"
                        :error="errors.tags ? errors.tags[0] : ''"
                    />
                </div>

                <!-- Visibility Options -->
                <div class="mt-4">
                    <InputLabel value="Visibility" class="mb-2" />
                    <div class="flex items-center space-x-6">
                        <label class="inline-flex items-center">
                            <input type="radio" class="form-radio" value="client" v-model="form.visibility" />
                            <span class="ml-2">Visible to Client</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" class="form-radio" value="team" v-model="form.visibility" />
                            <span class="ml-2">Visible to Team</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" class="form-radio" value="private" v-model="form.visibility" />
                            <span class="ml-2">Private</span>
                        </label>
                    </div>
                    <InputError :message="errors.visibility ? errors.visibility[0] : ''" class="mt-2" />
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>

<style scoped>
/* Add any specific styling for this form here if needed */
</style>
