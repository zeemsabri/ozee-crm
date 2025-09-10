<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    id: {
        type: Number,
        default: null,
    },
    mode: {
        type: String,
        default: 'create',
    },
});

const isEditing = computed(() => props.mode === 'edit' || !!props.id);

const form = useForm({
    name: '',
    target_audience: '',
    services_offered: [],
    goal: '',
    ai_persona: '',
    email_template: '',
    is_active: true,
    shareable_resource_ids: [],
});

// For the tag-like input for services
const servicesInput = ref('');
const addService = () => {
    const service = servicesInput.value.trim();
    if (service && !form.services_offered.includes(service)) {
        form.services_offered.push(service);
    }
    servicesInput.value = '';
};
const removeService = (index) => {
    form.services_offered.splice(index, 1);
};


const loading = ref(false);
const loadError = ref('');

// Shareable Resources for Multi Select
const shareableResourceOptions = ref([]);
const shareableResourcesLoading = ref(false);
const loadShareableResources = async () => {
    shareableResourcesLoading.value = true;
    try {
        const { data } = await axios.get('/api/shareable-resources', { params: { per_page: 1000 } });
        const items = data.data ?? data; // in case endpoint returns non-paginated in future
        shareableResourceOptions.value = items.map((r) => ({ label: r.title, value: r.id }));
    } catch (e) {
        console.error('Failed to load resources', e);
        shareableResourceOptions.value = [];
    } finally {
        shareableResourcesLoading.value = false;
    }
};

const loadCampaign = async () => {
    if (!props.id) return;
    loading.value = true;
    loadError.value = '';
    try {
        const { data } = await axios.get(`/api/campaigns/${props.id}`);
        form.name = data.name || '';
        form.target_audience = data.target_audience || '';
        form.services_offered = Array.isArray(data.services_offered) ? data.services_offered : [];
        form.goal = data.goal || '';
        form.ai_persona = data.ai_persona || '';
        form.email_template = data.email_template || '';
        form.is_active = !!data.is_active;
        form.shareable_resource_ids = Array.isArray(data.shareable_resource_ids) ? data.shareable_resource_ids : [];
    } catch (e) {
        console.error('Failed to load campaign', e);
        loadError.value = 'Failed to load campaign';
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadShareableResources();
    if (isEditing.value) {
        loadCampaign();
    }
});

const submit = async () => {
    const url = isEditing.value ? `/api/campaigns/${props.id}` : '/api/campaigns';
    const method = isEditing.value ? 'put' : 'post';
    form.processing = true;
    try {
        const payload = {
            name: form.name,
            target_audience: form.target_audience || null,
            services_offered: Array.isArray(form.services_offered) && form.services_offered.length ? form.services_offered : null,
            goal: form.goal || null,
            ai_persona: form.ai_persona || null,
            email_template: form.email_template || null,
            is_active: !!form.is_active,
            shareable_resource_ids: Array.isArray(form.shareable_resource_ids) ? form.shareable_resource_ids : [],
        };
        if (method === 'post') {
            await axios.post(url, payload);
            window.location.href = '/campaigns';
        } else {
            await axios.put(url, payload);
        }
    } catch (e) {
        console.error('Form submission error:', e);
        alert('Failed to save campaign');
    } finally {
        form.processing = false;
    }
};

// --- Lead Management ---
const leads = ref([]);
const leadsLoading = ref(false);
const leadSearchQuery = ref('');
const searchResults = ref([]);
const searching = ref(false);

const fetchLeads = async () => {
    if (!isEditing.value) return;
    leadsLoading.value = true;
    try {
        const response = await axios.get(`/api/campaigns/${props.id}/leads`);
        leads.value = response.data.data || [];
    } catch (error) {
        console.error("Failed to fetch leads:", error);
    } finally {
        leadsLoading.value = false;
    }
};

watch(leadSearchQuery, (newValue) => {
    if (newValue.length < 2) {
        searchResults.value = [];
        return;
    }
    searchLeads();
});

const searchLeads = async () => {
    searching.value = true;
    try {
        const response = await axios.get(`/api/leads/search?q=${leadSearchQuery.value}`);
        searchResults.value = response.data;
    } catch (error) {
        console.error("Failed to search leads:", error);
    } finally {
        searching.value = false;
    }
};

const attachLead = async (leadId) => {
    try {
        await axios.post(`/api/campaigns/${props.id}/leads`, { lead_id: leadId });
        leadSearchQuery.value = '';
        searchResults.value = [];
        await fetchLeads(); // Refresh the list
    } catch (error) {
        console.error("Failed to attach lead:", error);
        alert('Failed to attach lead. They may already be in another campaign.');
    }
};

const detachLead = async (leadId) => {
    try {
        await axios.delete(`/api/campaigns/${props.id}/leads/${leadId}`);
        await fetchLeads(); // Refresh the list
    } catch (error) {
        console.error("Failed to detach lead:", error);
    }
};


onMounted(() => {
    if (isEditing.value) {
        fetchLeads();
    }
})

</script>

<template>
    <Head :title="isEditing ? 'Edit Campaign' : 'Create Campaign'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ isEditing ? `Edit Campaign: ${form.name}` : 'Create New Campaign' }}
                </h2>
                <div>
                    <Link href="/campaigns">
                        <SecondaryButton>Back to Campaigns</SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 md:p-8 space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <!-- Left Column: Core Details -->
                            <div class="md:col-span-1 space-y-6">
                                <div>
                                    <label for="name" class="block font-medium text-sm text-gray-700">Campaign Name</label>
                                    <input id="name" v-model="form.name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                                    <p class="text-xs text-gray-500 mt-1">A descriptive name for internal use.</p>
                                </div>

                                <div>
                                    <label for="goal" class="block font-medium text-sm text-gray-700">Campaign Goal</label>
                                    <input id="goal" v-model="form.goal" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <p class="text-xs text-gray-500 mt-1">e.g., "Book a discovery call".</p>
                                </div>

                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Services Offered</label>
                                    <div class="flex items-center mt-1">
                                        <input v-model="servicesInput" @keydown.enter.prevent="addService" type="text" class="flex-grow rounded-l-md border-gray-300 shadow-sm" placeholder="e.g., SEO, Web Design">
                                        <button @click.prevent="addService" type="button" class="px-4 py-2 bg-gray-200 rounded-r-md text-sm font-medium hover:bg-gray-300">Add</button>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span v-for="(service, index) in form.services_offered" :key="index" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ service }}
                                            <button @click.prevent="removeService(index)" class="ml-1.5 flex-shrink-0 text-indigo-400 hover:text-indigo-500">
                                                <svg class="h-2 w-2" stroke="currentColor" fill="none" viewBox="0 0 8 8"><path stroke-linecap="round" stroke-width="1.5" d="M1 1l6 6m0-6L1 7" /></svg>
                                            </button>
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <label for="is_active" class="font-medium text-sm text-gray-700">Campaign Active</label>
                                    <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </div>
                            </div>

                            <!-- Right Column: AI Brain -->
                            <div class="md:col-span-2 space-y-6 bg-gray-50 p-6 rounded-lg border">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                    OZ-E Configuration
                                </h3>
                                <div>
                                    <label for="target_audience" class="block font-medium text-sm text-gray-700">Target Audience</label>
                                    <textarea id="target_audience" v-model="form.target_audience" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Describe the ideal client for OZ-E. Be specific.</p>
                                </div>

                                <div>
                                    <label for="ai_persona" class="block font-medium text-sm text-gray-700">AI Persona</label>
                                    <textarea id="ai_persona" v-model="form.ai_persona" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Define the tone and style OZ-E should use. e.g., "Friendly and professional, focusing on benefits."</p>
                                </div>

                                <div>
                                    <label for="email_template" class="block font-medium text-sm text-gray-700">Base Email Template</label>
                                    <textarea id="email_template" v-model="form.email_template" rows="8" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm font-mono text-sm"></textarea>
                                    <p class="text-xs text-gray-500 mt-1">The "seed text" OZ-E will personalize for each lead. Use placeholders like `{{first_name}}` or `{{company}}`.</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label class="block font-medium text-sm text-gray-700">Link Shareable Resources</label>
                                <MultiSelectDropdown
                                    v-model="form.shareable_resource_ids"
                                    :options="shareableResourceOptions"
                                    :isMulti="true"
                                    placeholder="Select resources to link"
                                />
                                <p class="text-xs text-gray-500 mt-1">Choose one or more resources to associate with this campaign.</p>
                            </div>

                            <div class="flex items-center justify-end">
                                <PrimaryButton :disabled="form.processing || shareableResourcesLoading">
                                    {{ isEditing ? 'Save Changes' : 'Create Campaign' }}
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Lead Management Section (only for existing campaigns) -->
                <div v-if="isEditing" class="mt-8 bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Manage Leads in this Campaign</h3>

                        <div class="mb-6">
                            <label for="lead_search" class="block font-medium text-sm text-gray-700">Attach New Lead</label>
                            <div class="relative mt-1">
                                <input id="lead_search" v-model="leadSearchQuery" type="text" class="block w-full md:w-1/2 rounded-md border-gray-300 shadow-sm" placeholder="Search by name or email...">
                                <div v-if="searchResults.length" class="absolute z-10 mt-1 w-full md:w-1/2 bg-white shadow-lg rounded-md border">
                                    <ul>
                                        <li v-for="lead in searchResults" :key="lead.id" @click="attachLead(lead.id)" class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm">
                                            {{ lead.full_name }} ({{ lead.email }})
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div v-if="leadsLoading">Loading leads...</div>
                        <div v-else-if="leads.length === 0" class="text-sm text-gray-500">No leads have been attached to this campaign yet.</div>
                        <ul v-else class="space-y-2">
                            <li v-for="lead in leads" :key="lead.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                                <div class="text-sm">
                                    <span class="font-medium">{{ lead.full_name }}</span>
                                    <span class="text-gray-500 ml-2">{{ lead.email }}</span>
                                </div>
                                <button @click="detachLead(lead.id)" class="text-sm text-red-600 hover:underline">Detach</button>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
