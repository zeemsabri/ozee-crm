<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { usePermissions } from '@/Directives/permissions';
import CustomMultiSelect from '@/Components/CustomMultiSelect.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

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
const validationErrors = ref({});
const sourceModelsData = ref({});
const loadingSourceModels = ref(false);

const emailForm = reactive({
    template_id: null,
    recipients: [],
    dynamic_data: {},
});

const { canDo } = usePermissions(props.projectId);

const selectedTemplate = computed(() => {
    return templates.value.find(template => template.id === emailForm.template_id);
});

const fetchSourceModelsData = async (template) => {
    loadingSourceModels.value = true;
    sourceModelsData.value = {};
    const modelNames = new Set();
    template.placeholders.forEach(p => {
        if ((p.is_repeatable || p.is_selectable) && p.source_model) {
            modelNames.add(p.source_model);
        }
    });

    try {
        const promises = Array.from(modelNames).map(modelName => {
            const shortModelName = modelName.split('\\').pop();
            const url = `/api/projects/${props.projectId}/model-data/${shortModelName}`;
            return window.axios.get(url).then(response => {
                sourceModelsData.value[shortModelName] = response.data;
            });
        });
        await Promise.all(promises);
    } catch (error) {
        console.error('Failed to fetch source model data:', error);
    } finally {
        loadingSourceModels.value = false;
    }
};

const fetchTemplates = async () => {
    try {
        const response = await window.axios.get('/api/email-templates');
        templates.value = response.data;
    } catch (error) {
        console.error('Failed to fetch email templates:', error);
    }
};

const fetchPreview = async () => {
    if (!emailForm.template_id || emailForm.recipients.length === 0) {
        previewContent.value = '<p class="text-gray-500 italic">Select a template and at least one recipient to see a preview.</p>';
        return;
    }

    previewLoading.value = true;
    try {
        const response = await window.axios.post(`/api/projects/${props.projectId}/email-preview`, {
            template_id: emailForm.template_id,
            recipient_id: emailForm.recipients[0],
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

watch(() => props.show, (newValue) => {
    if (newValue) {
        Object.assign(emailForm, {
            template_id: null,
            recipients: [],
            dynamic_data: {},
        });
        previewContent.value = '';
        validationErrors.value = {};
        sourceModelsData.value = {};
        fetchTemplates();
    }
});

watch(selectedTemplate, (newTemplate) => {
    emailForm.dynamic_data = {};
    if (newTemplate && newTemplate.placeholders) {
        newTemplate.placeholders.forEach(placeholder => {
            if (placeholder.is_dynamic) {
                emailForm.dynamic_data[placeholder.name] = '';
            } else if (placeholder.is_repeatable || placeholder.is_selectable) {
                if(placeholder.is_repeatable) {
                    emailForm.dynamic_data[placeholder.name] = [];
                } else {
                    emailForm.dynamic_data[placeholder.name] = null;
                }
            }
        });
        fetchSourceModelsData(newTemplate);
    }
});

const handleSubmitted = () => {
    emit('close');
};

const closeModal = () => {
    emit('close');
};

const templateOptions = computed(() => {
    return templates.value.map(template => ({
        value: template.id,
        label: template.name
    }));
});

const inputPlaceholders = computed(() => {
    return selectedTemplate.value ? selectedTemplate.value.placeholders.filter(p => p.is_dynamic || p.is_repeatable || p.is_selectable) : [];
});

const apiEndpoint = computed(() => `/api/projects/${props.projectId}/send-email`);

onMounted(() => {
    fetchTemplates();
});
</script>

<template>
    <BaseFormModal
        :show="show"
        title="Compose Email from Template"
        :api-endpoint="apiEndpoint"
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
                        <div v-for="placeholder in inputPlaceholders" :key="placeholder.id">
                            <div class="mb-4">
                                <InputLabel :for="placeholder.name" :value="placeholder.name" />
                                <template v-if="placeholder.is_repeatable && placeholder.source_model">
                                    <CustomMultiSelect
                                        :id="placeholder.name"
                                        v-model="emailForm.dynamic_data[placeholder.name]"
                                        :options="sourceModelsData[placeholder.source_model.split('\\').pop()] || []"
                                        :placeholder="`Select one or more ${placeholder.name}`"
                                        :label-key="placeholder.source_attribute"
                                        track-by="id"
                                        class="mt-1"
                                    />
                                    <div v-if="loadingSourceModels" class="text-xs text-gray-500 mt-1">
                                        Loading {{ placeholder.source_model.split('\\').pop() }}...
                                    </div>
                                </template>
                                <template v-else-if="placeholder.is_selectable && placeholder.source_model">
                                    <SelectDropdown
                                        :id="placeholder.name"
                                        v-model="emailForm.dynamic_data[placeholder.name]"
                                        :options="sourceModelsData[placeholder.source_model.split('\\').pop()] || []"
                                        :placeholder="`Select a ${placeholder.name}`"
                                        :value-key="placeholder.trackBy ?? 'id'"
                                        :label-key="placeholder.source_attribute"
                                        :allow-empty="true"
                                        class="mt-1"
                                    />
                                    <div v-if="loadingSourceModels" class="text-xs text-gray-500 mt-1">
                                        Loading {{ placeholder.source_model.split('\\').pop() }}...
                                    </div>
                                </template>
                                <template v-else-if="placeholder.is_dynamic && placeholder.is_link">
                                    <TextInput
                                        :id="placeholder.name"
                                        type="url"
                                        class="mt-1 block w-full"
                                        v-model="emailForm.dynamic_data[placeholder.name]"
                                        placeholder="Enter URL"
                                    />
                                </template>
                                <template v-else>
                                    <TextInput
                                        :id="placeholder.name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="emailForm.dynamic_data[placeholder.name]"
                                    />
                                </template>
                                <InputError :message="errors[`dynamic_data.${placeholder.name}`] ? errors[`dynamic_data.${placeholder.name}`][0] : ''" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Email Preview -->
                <div>
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-md font-semibold text-gray-800">Email Preview</h4>
                        <PrimaryButton
                            @click="fetchPreview"
                            :disabled="!emailForm.template_id || emailForm.recipients.length === 0 || previewLoading"
                            :class="{ 'opacity-50 cursor-not-allowed': !emailForm.template_id || emailForm.recipients.length === 0 || previewLoading }"
                        >
                            <span v-if="previewLoading" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading...
                            </span>
                            <span v-else>
                                Refresh Preview
                            </span>
                        </PrimaryButton>
                    </div>
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
