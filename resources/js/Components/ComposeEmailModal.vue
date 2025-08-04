<script setup>
import { reactive, ref, computed, defineExpose } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import EmailEditor from '@/Components/EmailEditor.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import CustomMultiSelect from '@/Components/CustomMultiSelect.vue';
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
});

const emit = defineEmits(['close', 'submitted', 'error']);

// --- STATE ---
const localFormData = reactive({
    subject: '',
    body: '',
    rejection_reason: '',
    template_id: null,
    template_data: {},
});

// State to control which editor is shown: 'custom' or 'template'
const compositionType = ref('custom');

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

const templateOptions = computed(() => {
    return templates.value.map(template => ({ value: template.id, label: template.name }));
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
    // ... (This function can be copied from your original ComposeEmailModal.vue)
};

// This function is called by the parent to initialize the modal's state
const setData = async (initialData) => {
    // Reset state first
    Object.assign(localFormData, { subject: '', body: '', rejection_reason: '', template_id: null, template_data: {} });
    previewContent.value = '';

    // Check if the email is template-based
    if (initialData.template_id) {
        compositionType.value = 'template';
        await fetchTemplates(); // Ensure templates are loaded

        localFormData.subject = initialData.subject;
        localFormData.template_id = initialData.template_id;
        localFormData.template_data = initialData.template_data || {};

    } else {
        // It's a custom/rich-text email
        compositionType.value = 'custom';
        localFormData.subject = initialData.subject;
        localFormData.body = initialData.body;
    }
};

// Expose the setData function to the parent component
defineExpose({ setData });


const { processedHtmlBody } = useEmailTemplate(() => localFormData.body);

const formatDataForApi = (data) => {
    const formattedData = { ...data };

    if (props.title === 'Edit and Approve Email') {
        // Add composition_type to inform the backend
        formattedData.composition_type = compositionType.value;

        if (compositionType.value === 'template') {
            // For template emails, send template data and nullify body
            formattedData.body = null;
        } else {
            // For custom emails, send processed body and nullify template data
            formattedData.body = processedHtmlBody.value;
            formattedData.template_id = null;
            formattedData.template_data = {};
        }
    }
    return formattedData;
};

const fetchPreview = async () => {
    // This function can be adapted from your ComposeEmailModal to generate a preview
    // for the currently edited template data.
    previewLoading.value = true;
    try {
        const payload = {
            template_id: localFormData.template_id,
            template_data: localFormData.template_data,
            client_id: props.initialFormData?.client_id // Assuming client_id is passed
        };
        const response = await window.axios.post(`/api/projects/${props.projectId}/email-preview`, payload);
        previewContent.value = response.data.body_html;
        localFormData.subject = response.data.subject;
    } catch (error) {
        previewContent.value = '<p class="text-red-500 italic">Error loading preview.</p>';
    } finally {
        previewLoading.value = false;
    }
};


const handleClose = () => { emit('close'); };
const handleSubmit = (response) => { emit('submitted', response); };
const handleError = (error) => { emit('error', error); };
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
            <!-- UI for Rejecting -->
            <div v-if="title === 'Reject Email'">
                <!-- ... rejection reason textarea ... -->
            </div>

            <!-- UI for Editing (now handles both types) -->
            <div v-else-if="title === 'Edit and Approve Email'" class="space-y-6">
                <!-- Common Subject Field -->
                <div class="mb-4">
                    <InputLabel for="edit_subject" value="Subject" />
                    <TextInput id="edit_subject" type="text" class="mt-1 block w-full" v-model="localFormData.subject" required />
                    <InputError :message="errors.subject ? errors.subject[0] : ''" class="mt-2" />
                </div>

                <!-- TEMPLATE EDITING UI -->
                <div v-if="compositionType === 'template'">
                    <h4 class="text-md font-semibold text-gray-800 mb-3 border-b pb-2">Editing Template Fields</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-4">
                        <div v-for="placeholder in inputPlaceholders" :key="placeholder.id">
                            <InputLabel :for="placeholder.name" :value="placeholder.name" />
                            <!-- Add inputs for template_data here based on placeholder type -->
                            <TextInput type="text" class="mt-1 block w-full" v-model="localFormData.template_data[placeholder.name]" />
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

                <!-- CUSTOM/RICH-TEXT EDITING UI -->
                <div v-else>
                    <InputLabel for="edit_body" value="Email Body" />
                    <EmailEditor id="edit_body" v-model="localFormData.body" placeholder="Edit your email here..." height="300px" class="mt-1" />
                    <InputError :message="errors.body ? errors.body[0] : ''" class="mt-2" />
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>
