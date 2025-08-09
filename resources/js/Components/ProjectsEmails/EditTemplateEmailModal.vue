<script setup>
import { reactive, ref, computed, defineExpose, onMounted, watch } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import OZeeMultiSelect from '@/Components/CustomMultiSelect.vue';
import { useEmailTemplate } from '@/Composables/useEmailTemplate';

const props = defineProps({
    show: Boolean,
    title: String,
    apiEndpoint: String,
    httpMethod: { type: String, default: 'post' },
    submitButtonText: String,
    successMessage: String,
    emailId: Number,
    projectId: Number,
    clientId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'submitted', 'error']);

// --- STATE ---
const localFormData = reactive({
    subject: '',
    template_id: null,
    template_data: {},
});

// State for template editing
const templates = ref([]);
const sourceModelsData = ref({});
const loadingSourceModels = ref(false);
const previewContent = ref('');
const previewLoading = ref(false);

// --- COMPUTED PROPERTIES ---
const selectedTemplate = computed(() => {
    return templates.value.find(template => template.id === localFormData.template_id);
});

const inputPlaceholders = computed(() => {
    if (!selectedTemplate.value) return [];
    return selectedTemplate.value.placeholders.filter(p => p.is_dynamic || p.is_repeatable || p.is_selectable);
});

// --- METHODS ---
const fetchTemplates = async () => {
    try {
        const response = await window.axios.get('/api/email-templates');
        templates.value = response.data;
    } catch (error) {
        console.error('Failed to fetch email templates:', error);
    }
};

const fetchSourceModelsData = async (template) => {
    if (!template || !template.placeholders) return;

    loadingSourceModels.value = true;
    sourceModelsData.value = {}; // Reset previous data

    try {
        // Get all placeholders that need source model data
        const placeholdersNeedingData = template.placeholders.filter(
            p => (p.is_selectable || p.is_repeatable) && p.source_model
        );

        if (placeholdersNeedingData.length === 0) {
            loadingSourceModels.value = false;
            return;
        }

        // Group placeholders by source model to minimize API calls
        const modelGroups = {};
        placeholdersNeedingData.forEach(p => {
            if (!modelGroups[p.source_model]) {
                modelGroups[p.source_model] = [];
            }
            modelGroups[p.source_model].push(p);
        });

        // Fetch data for each source model
        const fetchPromises = Object.keys(modelGroups).map(async (modelName) => {
            try {
                const response = await window.axios.get(`/api/source-models/${encodeURIComponent(modelName)}`);

                // Store data for each placeholder using this model
                modelGroups[modelName].forEach(placeholder => {
                    sourceModelsData.value[placeholder.name] = response.data.map(item => ({
                        id: item.id,
                        label: item[placeholder.source_attribute] || item.name || `ID: ${item.id}`,
                    }));
                });
            } catch (error) {
                console.error(`Failed to fetch data for model ${modelName}:`, error);
                // Initialize with empty array to prevent errors
                modelGroups[modelName].forEach(placeholder => {
                    sourceModelsData.value[placeholder.name] = [];
                });
            }
        });

        await Promise.all(fetchPromises);
    } catch (error) {
        console.error('Error fetching source models data:', error);
    } finally {
        loadingSourceModels.value = false;
    }
};

// This function is called by the parent to initialize the modal's state
const setData = async (initialData) => {
    // Reset state first
    Object.assign(localFormData, { subject: '', template_id: null, template_data: {} });
    previewContent.value = '';
    sourceModelsData.value = {};

    // Check if the email is template-based
    if (initialData.template_id) {
        await fetchTemplates(); // Ensure templates are loaded

        localFormData.subject = initialData.subject;
        localFormData.template_id = initialData.template_id;
        localFormData.template_data = initialData.template_data || {};

        // Get the selected template
        const template = templates.value.find(t => t.id === localFormData.template_id);
        if (template) {
            // Fetch source models data for dropdowns and multi-selects
            await fetchSourceModelsData(template);
        }

        // Generate preview after setting data
        fetchPreview();
    } else {
        console.error('This modal is only for template-based emails');
    }
};

// Expose the setData function to the parent component
defineExpose({ setData });

const formatDataForApi = (data) => {
    const formattedData = { ...data };

    // Ensure we're sending the template_id and template_data
    formattedData.composition_type = 'template';

    // Make sure template_data is properly formatted
    if (typeof formattedData.template_data === 'object' && formattedData.template_data !== null) {
        // Format template data based on placeholder types
        const template = templates.value.find(t => t.id === formattedData.template_id);

        if (template && template.placeholders) {
            const formattedTemplateData = {};

            // Process each placeholder based on its type
            template.placeholders.forEach(placeholder => {
                const placeholderName = placeholder.name;
                const value = formattedData.template_data[placeholderName];

                if (value !== undefined && value !== null) {
                    if (placeholder.is_repeatable && Array.isArray(value)) {
                        // For repeatable fields (multi-select), ensure it's an array of IDs
                        formattedTemplateData[placeholderName] = value;
                    } else if (placeholder.is_selectable) {
                        // For selectable fields (dropdown), ensure it's a single value
                        formattedTemplateData[placeholderName] = value;
                    } else {
                        // For regular fields, use as is
                        formattedTemplateData[placeholderName] = value;
                    }
                }
            });

            formattedData.template_data = formattedTemplateData;
        }
    } else if (typeof formattedData.template_data === 'string') {
        // If it's a JSON string, parse it
        try {
            formattedData.template_data = JSON.parse(formattedData.template_data);
        } catch (e) {
            console.error('Error parsing template_data:', e);
            formattedData.template_data = {};
        }
    } else {
        // Default to empty object if undefined or null
        formattedData.template_data = {};
    }

    return formattedData;
};

const fetchPreview = async () => {
    previewLoading.value = true;
    try {
        // Format template data for API
        const formattedTemplateData = {};

        // Get the selected template to check placeholder types
        const template = templates.value.find(t => t.id === localFormData.template_id);

        if (template && template.placeholders) {
            // Process each placeholder based on its type
            template.placeholders.forEach(placeholder => {
                const placeholderName = placeholder.name;
                const value = localFormData.template_data[placeholderName];

                if (value !== undefined && value !== null) {
                    if (placeholder.is_repeatable && Array.isArray(value)) {
                        // For repeatable fields (multi-select), ensure it's an array of IDs
                        formattedTemplateData[placeholderName] = value;
                    } else if (placeholder.is_selectable) {
                        // For selectable fields (dropdown), ensure it's a single value
                        formattedTemplateData[placeholderName] = value;
                    } else {
                        // For regular fields, use as is
                        formattedTemplateData[placeholderName] = value;
                    }
                }
            });
        } else {
            // If template info isn't available, use data as is
            Object.assign(formattedTemplateData, localFormData.template_data);
        }

        // Create the payload with required fields
        const payload = {
            template_id: localFormData.template_id,
            template_data: formattedTemplateData,
        };

        // Add client_id if available
        if (props.clientId) {
            payload.client_id = props.clientId;
        }

        const response = await window.axios.post(`/api/projects/${props.projectId}/email-preview`, payload);
        previewContent.value = response.data.body_html;
        localFormData.subject = response.data.subject;
    } catch (error) {
        console.error('Error fetching preview:', error);
        previewContent.value = '<p class="text-red-500 italic">Error loading preview.</p>';
    } finally {
        previewLoading.value = false;
    }
};

const handleClose = () => { emit('close'); };
const handleSubmit = (response) => { emit('submitted', response); };
const handleError = (error) => { emit('error', error); };

// Watch for changes in show prop to ensure modal is properly initialized
watch(() => props.show, (newValue) => {
    if (newValue && props.emailId) {
        // If the modal is shown and we have an emailId, fetch the email data
        fetchEmailData();
    }
});

const fetchEmailData = async () => {
    if (!props.emailId) return;

    try {
        const response = await window.axios.get(`/api/emails/${props.emailId}/edit-content`);
        const emailData = response.data;

        if (!emailData.template_id) {
            console.error('This email does not use a template');
            emit('close');
            return;
        }

        await setData(emailData);
    } catch (error) {
        console.error('Failed to fetch email data:', error);
        emit('error', error);
    }
};

onMounted(() => {
    fetchTemplates();
});
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="title"
        :api-endpoint="apiEndpoint"
        :http-method="httpMethod"
        :form-data="localFormData"
        :submit-button-text="submitButtonText"
        :success-message="successMessage"
        :format-data-for-api="formatDataForApi"
        @close="handleClose"
        @submitted="handleSubmit"
        @error="handleError"
        max-width="4xl"
    >
        <template #default="{ errors }">
            <!-- Common Subject Field -->
            <div class="mb-4">
                <InputLabel for="edit_subject" value="Subject" />
                <TextInput id="edit_subject" type="text" class="mt-1 block w-full" v-model="localFormData.subject" required />
                <InputError :message="errors.subject ? errors.subject[0] : ''" class="mt-2" />
            </div>

            <!-- TEMPLATE EDITING UI -->
            <div>
                <h4 class="text-md font-semibold text-gray-800 mb-3 border-b pb-2">Editing Template Fields</h4>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-4">
                    <div v-for="placeholder in inputPlaceholders" :key="placeholder.id">
                        <InputLabel :for="placeholder.name" :value="placeholder.name" />

                        <!-- Dynamic text input for regular fields -->
                        <TextInput
                            v-if="placeholder.is_dynamic && !placeholder.is_selectable && !placeholder.is_repeatable"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="localFormData.template_data[placeholder.name]"
                        />

                        <!-- Dropdown for selectable fields -->
                        <SelectDropdown
                            v-else-if="placeholder.is_selectable"
                            :id="placeholder.name"
                            v-model="localFormData.template_data[placeholder.name]"
                            :options="sourceModelsData[placeholder.name] || []"
                            value-key="id"
                            label-key="label"
                            class="mt-1 block w-full"
                            :disabled="loadingSourceModels"
                        />

                        <!-- Multi-select for repeatable fields -->
                        <div v-else-if="placeholder.is_repeatable">
                            <div v-if="loadingSourceModels" class="text-gray-500 text-sm mt-1">Loading options...</div>
                            <OZeeMultiSelect
                                v-else
                                v-model="localFormData.template_data[placeholder.name]"
                                :options="sourceModelsData[placeholder.name] || []"
                                :placeholder="`Select ${placeholder.name}`"
                                label-key="label"
                                value-key="id"
                                class="mt-1"
                            />
                        </div>

                        <!-- Fallback to text input if type is unknown -->
                        <TextInput
                            v-else
                            type="text"
                            class="mt-1 block w-full"
                            v-model="localFormData.template_data[placeholder.name]"
                        />

                        <InputError :message="errors[`template_data.${placeholder.name}`] ? errors[`template_data.${placeholder.name}`][0] : ''" class="mt-2" />
                    </div>
                </div>
                <!-- Preview Section for Templates -->
                <div class="mt-6">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-md font-semibold text-gray-800">Email Preview</h4>
                        <PrimaryButton @click="fetchPreview" :disabled="previewLoading">
                            {{ previewLoading ? 'Loading...' : 'Refresh Preview' }}
                        </PrimaryButton>
                    </div>
                    <div class="bg-gray-100 p-4 rounded-lg shadow-inner min-h-[300px]" v-html="previewContent"></div>
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>
