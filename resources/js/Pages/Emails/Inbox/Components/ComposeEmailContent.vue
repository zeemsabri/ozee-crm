<script setup>
import { reactive, watch, computed, ref, onMounted } from 'vue';
import axios from 'axios';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import OZeeMultiSelect from '@/Components/CustomMultiSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { useForm } from '@inertiajs/vue3';
import { useEmailTemplate } from '@/Composables/useEmailTemplate';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    projectId: [Number, String],
});

const emit = defineEmits(['submitted', 'error']);

// Form data for submission
const form = useForm({
    subject: '',
    client_ids: [],
    status: 'pending_approval',
    template_id: null,
    template_data: {},
    project_id: props.projectId || null,
});

// State for fetching clients and templates
const projects = ref([]);
const projectsLoading = ref(false);
const projectClients = ref([]);
const loadingClients = ref(false);
const clientsError = ref('');
const templates = ref([]);
const sourceModelsData = ref({});
const loadingSourceModels = ref(false);

// State for the Insert Link/List modals (removed as they were for standard emails)
// State for preview
const previewContent = ref('');
const previewLoading = ref(false);

const selectedTemplate = computed(() => {
    return templates.value.find(template => template.id === form.template_id);
});

const inputPlaceholders = computed(() => {
    if (!selectedTemplate.value) return [];
    return selectedTemplate.value.placeholders.filter(p => p.is_dynamic || p.is_repeatable || p.is_selectable);
});

const templateOptions = computed(() => {
    return templates.value.map(template => ({
        value: template.id,
        label: template.name
    }));
});

// --- Data Fetching Methods ---
const fetchProjects = async () => {
    projectsLoading.value = true;
    try {
        const response = await axios.get('/api/projects-simplified');
        projects.value = response.data;
    } catch (error) {
        console.error('Failed to fetch projects:', error);
    } finally {
        projectsLoading.value = false;
    }
};

const fetchProjectClients = async (projectId) => {
    if (!projectId) {
        projectClients.value = [];
        return;
    }
    loadingClients.value = true;
    clientsError.value = '';
    try {
        const response = await axios.get(`/api/projects/${projectId}/sections/clients?type=clients`);
        projectClients.value = response.data;
    } catch (e) {
        console.error('Failed to fetch project clients:', e);
        clientsError.value = e.response?.data?.message || 'Failed to load client data.';
    } finally {
        loadingClients.value = false;
    }
};

const fetchTemplates = async () => {
    try {
        const response = await axios.get('/api/email-templates');
        templates.value = response.data;
    } catch (error) {
        console.error('Failed to fetch email templates:', error);
    }
};

const fetchSourceModelsData = async (template) => {
    if (!form.project_id || !template || !template.placeholders) return;
    loadingSourceModels.value = true;
    sourceModelsData.value = {};
    try {
        const placeholdersNeedingData = template.placeholders.filter(
            p => (p.is_selectable || p.is_repeatable) && p.source_model
        );
        if (placeholdersNeedingData.length === 0) {
            loadingSourceModels.value = false;
            return;
        }
        const modelGroups = {};
        placeholdersNeedingData.forEach(p => {
            if (!modelGroups[p.source_model]) modelGroups[p.source_model] = [];
            modelGroups[p.source_model].push(p);
        });
        const fetchPromises = Object.keys(modelGroups).map(async (modelName) => {
            try {
                const response = await axios.get(`/api/source-models/${encodeURIComponent(modelName)}`);
                modelGroups[modelName].forEach(placeholder => {
                    sourceModelsData.value[placeholder.name] = response.data.map(item => ({
                        id: item.id,
                        label: item[placeholder.source_attribute] || item.name || `ID: ${item.id}`,
                    }));
                });
            } catch (error) {
                console.error(`Failed to fetch data for model ${modelName}:`, error);
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

const fetchPreview = async () => {
    if (!form.template_id || form.client_ids.length === 0 || !form.project_id) {
        previewContent.value = '<p class="text-gray-500 italic">Select a project, template, and at least one recipient to see a preview.</p>';
        return;
    }
    previewLoading.value = true;
    try {
        const payload = {
            template_id: form.template_id,
            client_id: form.client_ids[0],
            template_data: form.template_data,
        };
        const response = await axios.post(`/api/projects/${form.project_id}/email-preview`, payload);
        previewContent.value = response.data.body_html;
        if (response.data.subject) {
            form.subject = response.data.subject;
        }
    } catch (error) {
        console.error('Failed to fetch email preview:', error);
        previewContent.value = '<p class="text-red-500 italic">Error loading preview.</p>';
    } finally {
        previewLoading.value = false;
    }
};

// --- Form Submission ---
const submitForm = async () => {
    const isTemplateBased = !!form.template_id;
    if (!isTemplateBased) {
        console.error('Template not selected. All emails must be template-based.');
        return;
    }
    const apiEndpoint = '/api/emails/templated';

    const payload = {
        project_id: form.project_id,
        subject: form.subject,
        body: null, // Always null for template-based emails
        composition_type: 'template',
        status: 'pending_approval',
    };

    if (form.client_ids.length > 0) {
        payload.client_ids = form.client_ids;
    } else {
        payload.client_ids = [];
    }

    if (isTemplateBased) {
        payload.template_id = form.template_id;
        payload.template_data = form.template_data;
    }

    try {
        await axios.post(apiEndpoint, payload);
        form.reset();
        emit('submitted');
    } catch (error) {
        console.error('Email submission error:', error);
        emit('error', error);
    }
};

// --- Watchers ---
watch(() => form.project_id, (newProjectId) => {
    form.client_ids = [];
    form.template_id = null;
    form.template_data = {};
    fetchProjectClients(newProjectId);
}, { immediate: true });

watch(() => form.template_id, (newTemplateId) => {
    form.template_data = {};
    const newTemplate = templates.value.find(t => t.id === newTemplateId);
    if (newTemplate) {
        newTemplate.placeholders.forEach(placeholder => {
            if (placeholder.is_repeatable) {
                form.template_data[placeholder.name] = [];
            } else if (placeholder.is_selectable) {
                form.template_data[placeholder.name] = null;
            } else {
                form.template_data[placeholder.name] = '';
            }
        });
        fetchSourceModelsData(newTemplate);
    }
}, { immediate: true });

onMounted(() => {
    fetchTemplates();
    if (!props.projectId) {
        fetchProjects();
    }
});
</script>

<template>
    <div class="p-4 space-y-6">
        <form @submit.prevent="submitForm">
            <div class="space-y-4">
                <!-- Project Selection -->
                <div v-if="!props.projectId">
                    <InputLabel for="project" value="Select Project" />
                    <SelectDropdown
                        id="project"
                        v-model="form.project_id"
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
                    <InputError :message="form.errors.project_id" class="mt-2" />
                </div>

                <!-- Client Selection -->
                <div v-if="form.project_id">
                    <InputLabel for="client_ids" value="To (Clients)" />
                    <div v-if="loadingClients" class="text-gray-500 text-sm">Loading clients...</div>
                    <div v-else-if="clientsError" class="text-red-500 text-sm">{{ clientsError }}</div>
                    <OZeeMultiSelect
                        v-else
                        v-model="form.client_ids"
                        :options="projectClients"
                        placeholder="Select one or more clients"
                        label-key="name"
                        value-key="id"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="form.errors.client_ids" class="mt-2" />
                </div>

                <!-- Template Selection (now mandatory) -->
                <div v-if="form.project_id">
                    <InputLabel for="template-select" value="Select Template" />
                    <SelectDropdown
                        id="template-select"
                        v-model="form.template_id"
                        :options="templateOptions"
                        placeholder="Select a template"
                        value-key="value"
                        label-key="label"
                        :allow-empty="false"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.template_id" class="mt-2" />
                </div>

                <div v-if="form.template_id && form.project_id" class="space-y-4">
                    <div v-for="placeholder in inputPlaceholders" :key="placeholder.name">
                        <InputLabel :for="placeholder.name" :value="placeholder.label || placeholder.name" />

                        <div v-if="placeholder.is_repeatable">
                            <div v-if="loadingSourceModels" class="text-gray-500 text-sm mt-1">Loading options...</div>
                            <OZeeMultiSelect
                                v-else
                                v-model="form.template_data[placeholder.name]"
                                :options="sourceModelsData[placeholder.name] || []"
                                :placeholder="`Select ${placeholder.name}`"
                                label-key="label"
                                value-key="id"
                                class="mt-1"
                            />
                        </div>
                        <div v-else-if="placeholder.is_selectable">
                            <div v-if="loadingSourceModels" class="text-gray-500 text-sm mt-1">Loading options...</div>
                            <SelectDropdown
                                v-else
                                :id="placeholder.name"
                                v-model="form.template_data[placeholder.name]"
                                :options="sourceModelsData[placeholder.name] || []"
                                :placeholder="`Select a ${placeholder.name}`"
                                value-key="id"
                                label-key="label"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <TextInput
                            v-else
                            :id="placeholder.name"
                            v-model="form.template_data[placeholder.name]"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="`Enter a value for ${placeholder.name}`"
                        />
                        <InputError :message="form.errors[`template_data.${placeholder.name}`]" class="mt-2" />
                    </div>
                </div>

                <div v-if="form.template_id" class="mt-6">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-md font-semibold text-gray-800">Email Preview</h4>
                        <PrimaryButton @click="fetchPreview" :disabled="previewLoading || !form.project_id || form.client_ids.length === 0">
                            {{ previewLoading ? 'Loading...' : 'Refresh Preview' }}
                        </PrimaryButton>
                    </div>
                    <div class="bg-gray-100 p-4 rounded-lg shadow-inner min-h-[300px]" v-html="previewContent"></div>
                </div>
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing || !form.project_id || form.client_ids.length === 0">
                    Submit for Approval
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
