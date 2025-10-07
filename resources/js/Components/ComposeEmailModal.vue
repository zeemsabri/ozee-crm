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
import RepeatableDynamicField from '@/Components/RepeatableDynamicField.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    projectId: {
        type: Number,
        required: false,
        default: null,
    },
    clients: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const templates = ref([]);
const projects = ref([]);
const projectsLoading = ref(false);
const clientsData = ref([]);
const clientsLoading = ref(false);
const previewContent = ref('');
const previewLoading = ref(false);
const validationErrors = ref({});
const sourceModelsData = ref({});
const loadingSourceModels = ref(false);

const emailForm = reactive({
    template_id: null,
    template_data: {},
    project_id: props.projectId || null,
    client_ids: [],
    subject: '',
    greeting_name: '',
    custom_greeting_name: '',
    status: 'pending_approval',
});

const { canDo } = usePermissions(emailForm.project_id);

const selectedTemplate = computed(() => {
    return templates.value.find(template => template.id === emailForm.template_id);
});

const fetchSourceModelsData = async (template) => {
    if (!emailForm.project_id) {
        console.error('Cannot fetch source model data: No project selected');
        return;
    }

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
            const url = `/api/projects/${emailForm.project_id}/model-data/${shortModelName}`;
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
    if (!emailForm.template_id || emailForm.client_ids.length === 0 || !emailForm.project_id) {
        previewContent.value = '<p class="text-gray-500 italic">Select a project, template, and at least one recipient to see a preview.</p>';
        return;
    }

    previewLoading.value = true;
    try {
        const firstClient = emailForm.client_ids[0];
        const clientId = typeof firstClient === 'object' ? firstClient.id : firstClient;

        const response = await window.axios.post(`/api/projects/${emailForm.project_id}/email-preview`, {
            template_id: emailForm.template_id,
            client_id: clientId,
            template_data: emailForm.template_data,
        });
        previewContent.value = response.data.body_html;
        if (response.data.subject) {
            emailForm.subject = response.data.subject;
        }
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
            template_data: {},
            project_id: props.projectId || null,
            client_ids: [],
            subject: '',
            greeting_name: '',
            custom_greeting_name: '',
            status: 'pending_approval',
        });
        previewContent.value = '';
        validationErrors.value = {};
        sourceModelsData.value = {};
        fetchTemplates();

        // If projectId is not provided, fetch projects
        if (!props.projectId) {
            fetchProjects();
        }
    }
});

watch(selectedTemplate, (newTemplate) => {
    emailForm.template_data = {};
    linkFieldState.value = {};
    if (newTemplate && newTemplate.placeholders) {
        newTemplate.placeholders.forEach(placeholder => {
            if (placeholder.is_repeatable) {
                emailForm.template_data[placeholder.name] = [];
            } else if (placeholder.is_selectable) {
                emailForm.template_data[placeholder.name] = null;
            } else {
                emailForm.template_data[placeholder.name] = '';
                if (placeholder.is_dynamic && placeholder.is_link) {
                    linkFieldState.value[placeholder.name] = { label: '', url: '' };
                }
            }
        });
        fetchSourceModelsData(newTemplate);
    }
});

const prepareFormData = async () => {
    // If client_ids contains objects, extract the ids only for a cleaner payload
    if (Array.isArray(emailForm.client_ids) && emailForm.client_ids.length > 0 && typeof emailForm.client_ids[0] === 'object') {
        emailForm.client_ids = emailForm.client_ids.map(client => client.id);
    }

    // Set subject from template only if it hasn't been set by the preview
    if (!emailForm.subject && selectedTemplate.value) {
        emailForm.subject = selectedTemplate.value.subject || '';
    }

    if (emailForm.client_ids.length > 0) {
        const firstClient = emailForm.client_ids[0];
        // Check both props.clients and clientsData for the client
        const clientsToSearch = props.clients.length > 0 ? props.clients : clientsData.value;
        const clientObj = clientsToSearch.find(client => client.id === firstClient);
        if (clientObj) {
            emailForm.greeting_name = clientObj.name || '';
        }
    }

    console.log('Form data prepared:', JSON.stringify(emailForm));
    return true;
};

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

// Local state for single dynamic link placeholders
const linkFieldState = ref({});
const buildLinkString = (label, url) => `(${label || ''})[${url || ''}]`;

const apiEndpoint = computed(() => `/api/emails/templated`);

const fetchProjects = async () => {
    projectsLoading.value = true;
    try {
        const response = await window.axios.get('/api/projects-simplified');
        projects.value = response.data;
    } catch (error) {
        console.error('Failed to fetch projects:', error);
    } finally {
        projectsLoading.value = false;
    }
};

const fetchClients = async (projectId) => {
    if (!projectId) return;

    clientsLoading.value = true;
    clientsData.value = [];
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/sections/clients?type=clients`);
        clientsData.value = response.data;
    } catch (error) {
        console.error('Failed to fetch clients:', error);
    } finally {
        clientsLoading.value = false;
    }
};

watch(() => emailForm.project_id, (newProjectId) => {
    // Clear template, recipients and dynamic placeholders when project changes
    emailForm.template_id = null;
    emailForm.template_data = {};
    emailForm.client_ids = [];
    previewContent.value = '';

    if (newProjectId) {
        fetchClients(newProjectId);
    } else {
        clientsData.value = [];
    }
});

onMounted(() => {
    fetchTemplates();
    if (!props.projectId) {
        fetchProjects();
    }
});
</script>

<template>
    <BaseFormModal
        :show="show"
        title="Compose Email from Template"
        :api-endpoint="apiEndpoint"
        http-method="post"
        :form-data="emailForm"
        :submit-button-text="'Submit for Approval'"
        success-message="Email submitted for approval successfully!"
        @close="closeModal"
        @submitted="handleSubmitted"
        :before-submit="prepareFormData"
    >
        <template #default="{ errors }">
            <div class="flex flex-col space-y-6">
                <!-- Top Section: Project, Template and Recipient Selection -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Project Selection (only shown if projectId is not provided) -->
                    <div v-if="!props.projectId">
                        <InputLabel for="project" value="Select Project" />
                        <SelectDropdown
                            id="project"
                            v-model="emailForm.project_id"
                            :options="projects"
                            placeholder="Select a project"
                            value-key="id"
                            label-key="name"
                            :allow-empty="true"
                            class="mt-1"
                        />
                        <div v-if="projectsLoading" class="text-xs text-gray-500 mt-1">
                            Loading projects...
                        </div>
                        <InputError :message="errors.project_id ? errors.project_id[0] : ''" class="mt-2" />
                    </div>

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
                        <InputLabel for="client_ids" value="Recipients" />
                        <CustomMultiSelect
                            id="client_ids"
                            v-model="emailForm.client_ids"
                            :options="props.clients.length > 0 ? props.clients : clientsData"
                            placeholder="Select clients to send to"
                            label-key="name"
                            track-by="id"
                            :preserve-search="true"
                            :object-value="true"
                            class="mt-1"
                        />
                        <div v-if="clientsLoading" class="text-xs text-gray-500 mt-1">
                            Loading clients...
                        </div>
                        <InputError :message="errors.client_ids ? errors.client_ids[0] : ''" class="mt-2" />
                    </div>
                </div>

                <!-- Middle Section: Dynamic Input Fields -->
                <div v-if="selectedTemplate">
                    <h4 class="text-md font-semibold text-gray-800 mb-3">Dynamic Placeholders</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
<div v-for="placeholder in inputPlaceholders" :key="placeholder.id">
    <div class="mb-4">
        <InputLabel :for="placeholder.name" :value="placeholder.name" />

        <!-- Repeatable + dynamic (no source model): free-form with drag-and-drop -->
        <template v-if="placeholder.is_repeatable && placeholder.is_dynamic && !placeholder.source_model">
<RepeatableDynamicField
                v-model="emailForm.template_data[placeholder.name]"
                :allow-links="Boolean(placeholder.is_link)"
                :placeholder-name="placeholder.name"
                :add-button-text="`Add ${placeholder.name}`"
                :item-placeholder="`Enter ${placeholder.name}`"
            />
        </template>

        <!-- Repeatable from source model -->
        <template v-else-if="placeholder.is_repeatable && placeholder.source_model">
            <CustomMultiSelect
                :id="placeholder.name"
                v-model="emailForm.template_data[placeholder.name]"
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

        <!-- Selectable from source model -->
        <template v-else-if="placeholder.is_selectable && placeholder.source_model">
            <SelectDropdown
                :id="placeholder.name"
                v-model="emailForm.template_data[placeholder.name]"
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

        <!-- Single dynamic link (build (Label)[URL]) -->
        <template v-else-if="placeholder.is_dynamic && placeholder.is_link">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <TextInput
                    :id="`${placeholder.name}-label`"
                    v-model="linkFieldState[placeholder.name].label"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="Link text/label"
                    @update:model-value="(val) => { emailForm.template_data[placeholder.name] = buildLinkString(val, linkFieldState[placeholder.name].url); }"
                />
                <TextInput
                    :id="`${placeholder.name}-url`"
                    v-model="linkFieldState[placeholder.name].url"
                    type="url"
                    class="mt-1 block w-full"
                    placeholder="https://example.com"
                    @update:model-value="(val) => { emailForm.template_data[placeholder.name] = buildLinkString(linkFieldState[placeholder.name].label, val); }"
                />
            </div>
        </template>

        <!-- Fallback text input -->
        <template v-else>
            <TextInput
                :id="placeholder.name"
                type="text"
                class="mt-1 block w-full"
                v-model="emailForm.template_data[placeholder.name]"
            />
        </template>

        <InputError :message="errors[`template_data.${placeholder.name}`] ? errors[`template_data.${placeholder.name}`][0] : ''" class="mt-2" />
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
                            :disabled="!emailForm.project_id || !emailForm.template_id || emailForm.client_ids.length === 0 || previewLoading"
                            :class="{ 'opacity-50 cursor-not-allowed': !emailForm.project_id || !emailForm.template_id || emailForm.client_ids.length === 0 || previewLoading }"
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
