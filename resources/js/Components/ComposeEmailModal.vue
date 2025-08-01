<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { usePermissions } from '@/Directives/permissions';
import CustomMultiSelect from '@/Components/CustomMultiSelect.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    projectId: {
        type: Number,
        required: true,
    },
    clients: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const templates = ref([]);
const previewContent = ref('');
const previewLoading = ref(false);
const validationErrors = ref({}); // Keep local validation errors for dynamic fields

// Form data for BaseFormModal
const emailForm = reactive({
    template_id: null,
    recipients: [],
    dynamic_data: {},
});

const { canDo } = usePermissions(props.projectId);

// Get the selected template object from its ID
const selectedTemplate = computed(() => {
    return templates.value.find(template => template.id === emailForm.template_id);
});

// Fetch email templates with their placeholders
const fetchTemplates = async () => {
    try {
        const response = await window.axios.get('/api/email-templates');
        templates.value = response.data;
    } catch (error) {
        console.error('Failed to fetch email templates:', error);
    }
};

// Fetch the rendered preview from the backend
const fetchPreview = async () => {
    // We only need a preview if a template is selected and at least one recipient is chosen
    if (!emailForm.template_id || emailForm.recipients.length === 0) {
        previewContent.value = '<p class="text-gray-500 italic">Select a template and at least one recipient to see a preview.</p>';
        return;
    }

    previewLoading.value = true;
    try {
        const response = await window.axios.post('/api/email-preview', {
            template_id: emailForm.template_id,
            recipient_id: emailForm.recipients[0], // Use the first recipient for preview
            dynamic_data: emailForm.dynamic_data,
        });
        previewContent.value = response.data.body_html;
    } catch (error) {
        console.error('Failed to fetch email preview:', error);
        previewContent.value = '<p class="text-red-500 italic">Error loading preview.</p>';
    } finally {
        previewLoading.value = false;
    }
};

// Clear state when the modal is opened
watch(() => props.show, (newValue) => {
    if (newValue) {
        Object.assign(emailForm, {
            template_id: null,
            recipients: [],
            dynamic_data: {},
        });
        previewContent.value = '';
        validationErrors.value = {};
        fetchTemplates();
    }
});

// Watch for changes to trigger the preview fetch
watch(() => [emailForm.template_id, emailForm.recipients, emailForm.dynamic_data], () => {
    // Debounce the preview fetch to avoid excessive API calls
    // Wait for the user to finish typing or selecting before fetching the preview
    const timeout = setTimeout(fetchPreview, 500);
    return () => clearTimeout(timeout);
}, { deep: true });

// Watch for template selection to populate dynamic form fields
watch(selectedTemplate, (newTemplate) => {
    emailForm.dynamic_data = {}; // Reset dynamic data
    if (newTemplate && newTemplate.placeholders) {
        newTemplate.placeholders.forEach(placeholder => {
            if (placeholder.is_dynamic) {
                emailForm.dynamic_data[placeholder.name] = '';
            }
        });
    }
});

// Handle successful submission from BaseFormModal
const handleSubmitted = () => {
    // success('Email sent successfully!'); // Assuming a global success utility
    emit('close');
};

// Handle closing the modal
const closeModal = () => {
    emit('close');
};

// Computed property to format templates for the dropdown
const templateOptions = computed(() => {
    return templates.value.map(template => ({
        value: template.id,
        label: template.name
    }));
});

// Filter placeholders to only show dynamic ones in the form
const dynamicPlaceholders = computed(() => {
    return selectedTemplate.value ? selectedTemplate.value.placeholders.filter(p => p.is_dynamic) : [];
});

onMounted(() => {
    fetchTemplates();
});
</script>

<template>
    <BaseFormModal
        :show="show"
        title="Compose Email from Template"
        api-endpoint="/api/send-email"
        http-method="post"
        :form-data="emailForm"
        :submit-button-text="'Send Email'"
        success-message="Email sent successfully!"
        @close="closeModal"
        @submitted="handleSubmitted"
    >
        <template #default="{ errors }">
            <div class="flex flex-col space-y-6">
                <!-- Top Section: Template and Recipient Selection -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Template Selection -->
                    <div>
                        <InputLabel for="template" value="Select Template" />
                        <SelectDropdown
                            id="template"
                            v-model="emailForm.template_id"
                            :options="templateOptions"
                            placeholder="Select a template"
                            value-key="value"
                            label-key="label"
                            :allow-empty="true"
                            class="mt-1"
                        />
                        <InputError :message="errors.template_id ? errors.template_id[0] : ''" class="mt-2" />
                    </div>

                    <!-- Recipient Selection (multi select box) -->
                    <div>
                        <InputLabel for="recipients" value="Recipients" />
                        <CustomMultiSelect
                            id="recipients"
                            v-model="emailForm.recipients"
                            :options="clients"
                            placeholder="Select clients to send to"
                            label-key="name"
                            track-by="id"
                            class="mt-1"
                        />
                        <InputError :message="errors.recipients ? errors.recipients[0] : ''" class="mt-2" />
                    </div>
                </div>

                <!-- Middle Section: Dynamic Input Fields -->
                <div v-if="selectedTemplate">
                    <h4 class="text-md font-semibold text-gray-800 mb-3">Dynamic Placeholders</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div v-for="placeholder in dynamicPlaceholders" :key="placeholder.id">
                            <div class="mb-4">
                                <InputLabel :for="placeholder.name" :value="placeholder.name" />
                                <TextInput
                                    :id="placeholder.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="emailForm.dynamic_data[placeholder.name]"
                                />
                                <InputError :message="errors[`dynamic_data.${placeholder.name}`] ? errors[`dynamic_data.${placeholder.name}`][0] : ''" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Email Preview -->
                <div>
                    <h4 class="text-md font-semibold text-gray-800 mb-3">Email Preview</h4>
                    <div class="bg-gray-100 p-4 rounded-lg shadow-inner min-h-[300px]">
                        <div v-if="previewLoading" class="flex items-center justify-center h-full">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-500"></div>
                        </div>
                        <div v-else v-html="previewContent"></div>
                    </div>
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>
