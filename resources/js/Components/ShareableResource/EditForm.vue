<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
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
    // The API endpoint for updating shareable resources
    apiEndpoint: {
        type: String,
        required: true,
    },
    // The resource to edit
    resource: {
        type: Object,
        required: true,
    }
});


const emit = defineEmits(['close', 'resourceUpdated']);

// Reactive form data for the resource being edited
const form = reactive({
    title: '',
    description: '',
    url: '',
    type: 'website',
    thumbnail_url: '',
    visible_to_client: true,
    tags: [], // Array to hold tags from TagInput component
});

// Options for the 'type' dropdown, based on your migration file
const resourceTypeOptions = computed(() => [
    { value: 'website', label: 'Website' },
    { value: 'youtube', label: 'YouTube Video' },
    { value: 'document', label: 'Document' },
    { value: 'image', label: 'Image' },
    { value: 'other', label: 'Other' },
]);

// Watch for changes in the resource prop and update the form
onMounted(() => {
    populateForm();
});

// Populate the form with the resource data
const populateForm = () => {
    if (props.resource) {
        form.title = props.resource.title || '';
        form.description = props.resource.description || '';
        form.url = props.resource.url || '';
        form.type = props.resource.type || 'website';
        form.thumbnail_url = props.resource.thumbnail_url || '';
        form.visible_to_client = props.resource.visible_to_client !== undefined ? props.resource.visible_to_client : true;
        // Initialize tags v-model as array of IDs/new_* strings and pass full objects via initialTags prop
        const incomingTags = props.resource.tags || [];
        form.tags = Array.isArray(incomingTags) ? incomingTags.map(t => (typeof t === 'object' && t !== null ? t.id : t)).filter(v => v !== undefined && v !== null) : [];
    }
};

// Watch for changes in the incoming resource and repopulate the form
watch(
    () => props.resource,
    () => {
        populateForm();
    },
    { immediate: true, deep: true }
);

/**
 * Formats the data before sending it to the API.
 * This function prepares the 'tags' array to match
 * the expected input format for your 'ProcessTags' middleware.
 *
 * @param {Object} data - The raw form data.
 * @returns {Object} The formatted data ready for API submission.
 */
const formatDataForApi = (data) => {
    // Build payload similar to ProjectEditBasicInfo approach: send ids via 'tags' and also 'tag_ids'
    const tagIds = Array.isArray(data.tags) ? data.tags : [];
    return {
        title: data.title,
        description: data.description,
        url: data.url,
        type: data.type,
        thumbnail_url: data.thumbnail_url,
        visible_to_client: data.visible_to_client,
        // Provide both keys to be safe across controllers/middleware
        tags: tagIds,
        tag_ids: tagIds,
    };
};

/**
 * Handles the successful submission of the form from BaseFormModal.
 * Emits 'resourceUpdated' event with the updated resource data and closes the modal.
 * @param {Object} updatedResource - The data of the updated resource.
 */
const handleSubmitted = (updatedResource) => {
    emit('resourceUpdated', updatedResource);
    handleClose(); // Close the modal and reset form after successful submission
};

/**
 * Handles the modal close event.
 * Resets the form and emits the 'close' event.
 */
const handleClose = () => {
    emit('close');
};
</script>

<template>
    <BaseFormModal
        :show="props.show"
        :title="'Edit Shareable Resource'"
        :apiEndpoint="`${props.apiEndpoint}/${props.resource.id}`"
        httpMethod="put"
        :formData="form"
        :submitButtonText="'Update Resource'"
        :successMessage="'Shareable resource updated successfully!'"
        :formatDataForApi="formatDataForApi"
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
                        :initialTags="props.resource?.tags || []"
                        label="Associated Tags"
                        placeholder="Search or add tags"
                        :error="errors.tags ? errors.tags[0] : ''"
                    />
                </div>

                <!-- Visible to Client Checkbox -->
                <div class="flex items-center mt-4">
                    <Checkbox id="visible_to_client" v-model:checked="form.visible_to_client" />
                    <InputLabel for="visible_to_client" value="Visible to Client" class="ml-2" />
                    <InputError :message="errors.visible_to_client ? errors.visible_to_client[0] : ''" class="mt-2" />
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>

<style scoped>
/* Add any specific styling for this form here if needed */
</style>
