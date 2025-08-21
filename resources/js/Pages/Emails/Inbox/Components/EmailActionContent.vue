<script setup>
import { reactive, computed, ref, watch, onMounted } from 'vue';
import axios from 'axios';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import EmailEditor from '@/Components/EmailEditor.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import OZeeMultiSelect from '@/Components/CustomMultiSelect.vue';
import { useEmailTemplate } from '@/Composables/useEmailTemplate';
import { useForm } from '@inertiajs/vue3';
import { fetchEmailPreview as fetchEmailPreviewApi } from '@/Services/api.js';

const props = defineProps({
    email: { type: Object, required: true },
    mode: {
        type: String,
        required: true, // 'edit' or 'reject'
    },
});

const emit = defineEmits(['submitted', 'error']);

const form = useForm({
    subject: '',
    body: '',
    rejection_reason: '',
    template_id: null,
    template_data: {},
});

const isSubmitting = ref(false);

// Template-specific state
const templates = ref([]);
const sourceModelsData = ref({});
const loadingSourceModels = ref(false);
const previewContent = ref('');
const previewLoading = ref(false);

const approveButtonText = computed(() => {
    return props.email?.type === 'sent' ? 'Approve & Send' : 'Approve';
});

const isTemplateBased = computed(() => {
    return !!props.email?.template_id;
});

const selectedTemplate = computed(() => {
    return templates.value.find(template => template.id === form.template_id);
});

const inputPlaceholders = computed(() => {
    if (!selectedTemplate.value) return [];
    return selectedTemplate.value.placeholders.filter(p => p.is_dynamic || p.is_repeatable || p.is_selectable);
});

// --- Data Fetching Methods ---
const fetchTemplates = async () => {
    try {
        const response = await axios.get('/api/email-templates');
        templates.value = response.data;
    } catch (error) {
        console.error('Failed to fetch email templates:', error);
    }
};

const fetchSourceModelsData = async (template) => {
    if (!props.email?.conversation?.project?.id || !template || !template.placeholders) return;
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
    if (!form.template_id || !props.email?.conversation?.project?.id) {
        previewContent.value = '<p class="text-gray-500 italic">Select a template and a project to see a preview.</p>';
        return;
    }
    previewLoading.value = true;
    try {
        const payload = {
            template_id: form.template_id,
            template_data: form.template_data,
            client_id: props.email.conversation.client_id, // Correctly added client_id
        };
        const response = await fetchEmailPreviewApi(props.email.conversation.project.id, payload);
        previewContent.value = response.body_html;
        if (response.subject) {
            form.subject = response.subject;
        }
    } catch (error) {
        console.error('Failed to fetch email preview:', error);
        previewContent.value = '<p class="text-red-500 italic">Error loading preview.</p>';
    } finally {
        previewLoading.value = false;
    }
};

const fetchEmailData = async () => {
    if (!props.email?.id) return;
    if (!props.email.template_id) {
        console.error('This email does not use a template and cannot be edited in this workflow.');
        return;
    }

    form.reset();
    templates.value = [];
    sourceModelsData.value = {};
    previewContent.value = '';

    try {
        const response = await axios.get(`/api/emails/${props.email.id}/edit-content`);
        const emailData = response.data;

        form.subject = emailData.subject;
        form.body = emailData.body;
        form.rejection_reason = '';

        if (emailData.template_id) {
            form.template_id = emailData.template_id;
            form.template_data = emailData.template_data || {};
            await fetchTemplates();
            await fetchSourceModelsData(selectedTemplate.value);
            fetchPreview();
        }

    } catch (error) {
        console.error('Failed to fetch email data:', error);
        emit('error', error);
    }
};

const submitForm = async () => {
    isSubmitting.value = true;
    let url = '';
    let payload = {};

    if (props.mode === 'reject') {
        url = `/api/emails/${props.email.id}/reject`;
        payload = { rejection_reason: form.rejection_reason };
    } else if (props.mode === 'edit') {
        url = `/api/emails/${props.email.id}/edit-and-approve`;
        if (isTemplateBased.value) {
            payload = {
                subject: form.subject,
                template_id: form.template_id,
                template_data: form.template_data,
                client_id: props.email.conversation.client_id,
            };
        } else {
            payload = {
                subject: form.subject,
                body: form.body,
            };
        }
    }

    try {
        await axios.post(url, payload);
        emit('submitted');
    } catch (error) {
        console.error('Form submission error:', error);
        emit('error', error);
    } finally {
        isSubmitting.value = false;
        form.reset();
    }
};

watch(() => props.email, (newEmail) => {
    if (newEmail) {
        fetchEmailData();
    }
}, { immediate: true });
</script>

<template>
    <div class="p-4 space-y-6">
        <form @submit.prevent="submitForm">
            <div v-if="mode === 'edit'">
                <div v-if="isTemplateBased" class="space-y-4">
                    <div class="mb-4">
                        <InputLabel for="edit_subject" value="Subject" />
                        <TextInput id="edit_subject" type="text" class="mt-1 block w-full" v-model="form.subject" required />
                        <InputError :message="form.errors.subject" class="mt-2" />
                    </div>
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
                <div v-else class="space-y-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <p class="font-bold">Error</p>
                        <p>This email does not use a template and cannot be edited in this workflow.</p>
                    </div>
                </div>
                <div v-if="isTemplateBased" class="flex items-center justify-end mt-4">
                    <PrimaryButton :class="{ 'opacity-25': isSubmitting }" :disabled="isSubmitting">
                        {{ approveButtonText }}
                    </PrimaryButton>
                </div>
            </div>
            <div v-else-if="mode === 'reject'">
                <div class="mb-6">
                    <InputLabel for="rejection_reason" value="Rejection Reason" />
                    <textarea id="rejection_reason" rows="5" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="form.rejection_reason" required placeholder="Please provide a reason for rejecting this email (minimum 10 characters)"></textarea>
                    <InputError :message="form.errors.rejection_reason" class="mt-2" />
                </div>
                <div class="flex items-center justify-end">
                    <PrimaryButton :class="{ 'opacity-25': isSubmitting }" :disabled="isSubmitting">
                        Reject Email
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </div>
</template>
