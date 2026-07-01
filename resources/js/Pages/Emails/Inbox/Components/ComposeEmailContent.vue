<script setup>
import { reactive, watch, computed, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import OZeeMultiSelect from '@/Components/CustomMultiSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import RepeatableDynamicField from '@/Components/RepeatableDynamicField.vue';
import { useForm } from '@inertiajs/vue3';
import { useEmailTemplate } from '@/Composables/useEmailTemplate';
import Modal from '@/Components/Modal.vue';
import SchedulePickerModal from '@/Components/Scheduler/SchedulePickerModal.vue';
import { useEmbeddedScheduler } from '@/Composables/useEmbeddedScheduler.js';

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
const projectTimezone = ref('');
const nowTick = ref(Date.now());
let clockInterval = null;

const { showScheduleModal, scheduleDraft, open, close, onSaveDraft, attachAfterCreate } = useEmbeddedScheduler();

// State for the Insert Link/List modals (removed as they were for standard emails)
// State for preview
const previewContent = ref('');
const previewLoading = ref(false);

const selectedTemplate = computed(() => {
    return templates.value.find(template => template.id === form.template_id);
});

const selectedClient = computed(() => {
    const firstClientId = form.client_ids?.[0];
    if (!firstClientId) return null;
    return projectClients.value.find((client) => client.id === firstClientId) || null;
});

const projectCurrentTime = computed(() => {
    if (!projectTimezone.value) return null;
    try {
        return new Date(nowTick.value).toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: projectTimezone.value,
        });
    } catch (e) {
        return null;
    }
});

const selectedClientCurrentTime = computed(() => {
    if (!selectedClient.value?.timezone) return null;
    try {
        return new Date(nowTick.value).toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: selectedClient.value.timezone,
        });
    } catch (e) {
        return null;
    }
});

const scheduleSummary = computed(() => {
    const d = scheduleDraft.value;
    if (!d) return null;
    const time = d.time || '00:00';
    switch (d.mode) {
        case 'once':
            return `One-time at ${String(d.start_at || '').replace('T', ' ')}`;
        case 'daily':
            return `Daily at ${time}`;
        case 'weekly': {
            const names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const label = (d.days_of_week || []).map(i => names[i]).join(', ');
            return `Weekly on ${label || '—'} at ${time}`;
        }
        case 'monthly':
            if (d.day_of_month) return `Monthly on day ${d.day_of_month} at ${time}`;
            return `Monthly by weekday at ${time}`;
        case 'yearly':
            return `Yearly at ${time}`;
        case 'cron':
            return `Custom cron: ${d.cron || '* * * * *'}`;
    }
    return null;
});

const inputPlaceholders = computed(() => {
    if (!selectedTemplate.value) return [];
    return selectedTemplate.value.placeholders.filter(p => p.is_dynamic || p.is_repeatable || p.is_selectable);
});

// Local state for non-repeatable link placeholders (label + url -> "(Label)[URL]")
const linkFieldState = ref({});

const parseLinkString = (val) => {
    const match = typeof val === 'string' ? val.match(/^\((.*?)\)\[(.*?)\]$/) : null;
    return { label: match ? match[1] : '', url: match ? match[2] : '' };
};

const buildLinkString = (label, url) => `(${label || ''})[${url || ''}]`;

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

const fetchProjectTimezone = async (projectId) => {
    if (!projectId) {
        projectTimezone.value = '';
        return;
    }
    try {
        const response = await axios.get(`/api/projects/${projectId}/sections/basic`);
        projectTimezone.value = response.data?.timezone || '';
    } catch (error) {
        projectTimezone.value = '';
        console.error('Failed to fetch project timezone:', error);
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
            client_ids: form.client_ids,
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
        status: scheduleDraft.value ? 'delayed' : 'draft',
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
        const response = await axios.post(apiEndpoint, payload);
        const emailId = response?.data?.id;

        if (emailId && scheduleDraft.value) {
            if (!scheduleDraft.value.name) {
                scheduleDraft.value.name = `Delayed email: ${form.subject || `Email #${emailId}`}`;
            }
            const schedule = await attachAfterCreate('App\\Models\\Email', emailId);
            if (!schedule) {
                // Fallback to draft if scheduling failed to avoid a stuck delayed email.
                await axios.post(`/api/emails/${emailId}/update`, { status: 'draft' });
            }
        }

        form.reset();
        projectTimezone.value = '';
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
    fetchProjectTimezone(newProjectId);
}, { immediate: true });

watch(() => form.template_id, (newTemplateId) => {
    form.template_data = {};
    linkFieldState.value = {}; // reset link field local state
    const newTemplate = templates.value.find(t => t.id === newTemplateId);
    if (newTemplate) {
        newTemplate.placeholders.forEach(placeholder => {
            if (placeholder.is_repeatable) {
                // For repeatable + dynamic, we still initialize as an array
                form.template_data[placeholder.name] = [];
            } else if (placeholder.is_selectable) {
                form.template_data[placeholder.name] = null;
            } else {
                // dynamic single values (including single link) start empty
                form.template_data[placeholder.name] = '';
                if (placeholder.is_dynamic && placeholder.is_link) {
                    linkFieldState.value[placeholder.name] = { label: '', url: '' };
                }
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
    clockInterval = setInterval(() => {
        nowTick.value = Date.now();
    }, 30000);
});

onUnmounted(() => {
    if (clockInterval) {
        clearInterval(clockInterval);
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

                    <div v-if="projectTimezone && projectCurrentTime" class="mt-3 p-3 rounded-md bg-blue-50 border border-blue-100 text-sm text-blue-900">
                        <div><span class="font-semibold">Project timezone:</span> {{ projectTimezone }}</div>
                        <div><span class="font-semibold">Current time:</span> {{ projectCurrentTime }}</div>
                    </div>
                    <div v-if="selectedClient?.timezone && selectedClientCurrentTime" class="mt-2 p-3 rounded-md bg-emerald-50 border border-emerald-100 text-sm text-emerald-900">
                        <div><span class="font-semibold">Primary client timezone:</span> {{ selectedClient.timezone }}</div>
                        <div><span class="font-semibold">Current time:</span> {{ selectedClientCurrentTime }}</div>
                    </div>
                </div>

                <div v-if="form.project_id" class="rounded-lg border border-gray-200 p-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Send Later</p>
                            <p v-if="scheduleSummary" class="text-xs text-gray-600 mt-1">{{ scheduleSummary }}</p>
                            <p v-else class="text-xs text-gray-500 mt-1">No schedule attached.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="open" class="px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 rounded-md hover:bg-indigo-100">
                                {{ scheduleSummary ? 'Edit Schedule' : 'Add Schedule' }}
                            </button>
                            <button v-if="scheduleDraft" type="button" @click="scheduleDraft = null" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                                Clear
                            </button>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2">When a schedule is set, this email is saved with status delayed.</p>
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

    <!-- Repeatable + dynamic: free-form multi items with drag-and-drop -->
    <div v-if="placeholder.is_repeatable && placeholder.is_dynamic">
<RepeatableDynamicField
            v-model="form.template_data[placeholder.name]"
            :allow-links="Boolean(placeholder.is_link)"
            :placeholder-name="placeholder.name"
            :add-button-text="`Add ${placeholder.label || placeholder.name}`"
            :item-placeholder="`Enter ${placeholder.label || placeholder.name}`"
        />
    </div>

    <!-- Repeatable (from source model): multi-select -->
    <div v-else-if="placeholder.is_repeatable">
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

    <!-- Selectable (single from source model): dropdown -->
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

    <!-- Single dynamic link: label + url to build (Label)[URL] -->
    <div v-else-if="placeholder.is_dynamic && placeholder.is_link" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <TextInput
            :id="`${placeholder.name}-label`"
            v-model="linkFieldState[placeholder.name].label"
            type="text"
            class="mt-1 block w-full"
            placeholder="Link text/label"
            @update:model-value="(val) => { form.template_data[placeholder.name] = buildLinkString(val, linkFieldState[placeholder.name].url); }"
        />
        <TextInput
            :id="`${placeholder.name}-url`"
            v-model="linkFieldState[placeholder.name].url"
            type="url"
            class="mt-1 block w-full"
            placeholder="https://example.com"
            @update:model-value="(val) => { form.template_data[placeholder.name] = buildLinkString(linkFieldState[placeholder.name].label, val); }"
        />
    </div>

    <!-- Fallback: single-line text input for dynamic value -->
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
    <SchedulePickerModal :show="showScheduleModal" title="Schedule Email" @close="close" @save="onSaveDraft" />
</template>
