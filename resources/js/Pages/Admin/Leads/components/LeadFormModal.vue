<script setup>
import { reactive, watch, ref, computed, onMounted } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import { useLeads } from '@/Composables/useLeads.js';

const props = defineProps({
  show: { type: Boolean, default: false },
  lead: { type: Object, default: null },
  users: { type: Array, default: () => [] },
  sourceOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'lead-created', 'lead-updated']);

const { createLead, updateLead, createExistingClientEnquiry, updateExistingClientEnquiry } = useLeads();

const errors = ref({});
const saving = ref(false);
const activeTab = ref('basic');
const entryType = ref('lead');
const loadingSupportingData = ref(false);
const enquirySupportingData = ref({ clients: [], projects: [] });

const enquiryStatusOptions = [
  { label: 'Pending Quote', value: 'pending_quote' },
  { label: 'Quoted', value: 'quoted' },
  { label: 'Approved', value: 'approved' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Converted To Service', value: 'converted_to_service' },
];

const enquiryForm = reactive({
  id: null,
  project_id: null,
  client_id: null,
  service_id: '',
  description: '',
  enquiry_status: 'pending_quote',
  amount: '',
  currency: 'USD',
  frequency: 'one_off',
  start_date: '',
  show_on_leads_board: true,
});

const form = reactive({
  id: null,
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  company: '',
  title: '',
  status: 'new',
  source: '',
  pipeline_stage: '',
  assigned_to_id: null,
  campaign_id: null,
  // Less frequent fields moved to Additional tab
  estimated_value: '',
  currency: 'USD',
  website: '',
  notes: '',
  // Metadata container (sent to API)
  metadata: {},
});

// Additional arrays bound to metadata
const contexts = ref([]); // metadata.contexts: string[]
const potentialServices = ref([]); // metadata.potential_services: string[]
const servicesInput = ref('');
const contextsInput = ref('');

const socialLinks = ref([]); // metadata.social_links: {label, url}[]
const newSocial = reactive({ label: '', url: '' });

// Campaign linking state
const campaignOptions = ref([]);
const loadingCampaigns = ref(false);
const extraCampaignIds = ref([]);

onMounted(async () => {
  try {
    loadingCampaigns.value = true;
    const { data } = await window.axios.get('/api/campaigns', { params: { per_page: 1000 } });
    const items = data?.data || data || [];
    campaignOptions.value = items.map(c => ({ label: c.name, value: c.id }));
  } catch (e) {
    console.error('Failed to load campaigns', e);
    campaignOptions.value = [];
  } finally {
    loadingCampaigns.value = false;
  }

  try {
    loadingSupportingData.value = true;
    const { data } = await window.axios.get('/api/existing-client-enquiries/supporting-data');
    enquirySupportingData.value = {
      clients: data?.clients || [],
      projects: data?.projects || [],
    };
  } catch (e) {
    console.error('Failed to load enquiry supporting data', e);
    enquirySupportingData.value = { clients: [], projects: [] };
  } finally {
    loadingSupportingData.value = false;
  }
});

const userOptions = computed(() => (props.users || []).map(u => ({ label: u.name, value: u.id })));
const statusOpts = computed(() => props.statusOptions || []);
const sourceOpts = computed(() => props.sourceOptions || []);
const clientOptions = computed(() => (enquirySupportingData.value.clients || []).map(client => ({ label: client.name, value: client.id })));
const projectOptions = computed(() => {
  const selectedClientId = enquiryForm.client_id;
  const projects = enquirySupportingData.value.projects || [];
  return projects
    .filter(project => !selectedClientId || (project.client_ids || []).includes(selectedClientId))
    .map(project => ({ label: project.name, value: project.id }));
});

const resetForm = () => {
  Object.assign(form, {
    id: null,
    first_name: '', last_name: '', email: '', phone: '',
    company: '', title: '', status: 'new', source: '', pipeline_stage: '',
    assigned_to_id: null,
    campaign_id: null,
    estimated_value: '', currency: 'USD', website: '', notes: '',
    metadata: {},
  });
  contexts.value = [];
  potentialServices.value = [];
  servicesInput.value = '';
  contextsInput.value = '';
  socialLinks.value = [];
  newSocial.label = '';
  newSocial.url = '';
  extraCampaignIds.value = [];
  Object.assign(enquiryForm, {
    id: null,
    project_id: null,
    client_id: null,
    service_id: '',
    description: '',
    enquiry_status: 'pending_quote',
    amount: '',
    currency: 'USD',
    frequency: 'one_off',
    start_date: '',
    show_on_leads_board: true,
  });
  errors.value = {};
  activeTab.value = 'basic';
  entryType.value = 'lead';
};

const loadFromLead = (lead) => {
  if (!lead) {
    resetForm();
    return;
  }
  if (lead.card_type === 'existing_client_enquiry') {
    resetForm();
    entryType.value = 'existing_client_enquiry';
    Object.assign(enquiryForm, {
      id: lead.enquiry_id || lead.id,
      project_id: lead.project_id || lead.metadata?.project_id || null,
      client_id: lead.client_id || lead.metadata?.client_id || null,
      service_id: lead.service_name || lead.title || '',
      description: lead.description || lead.latest_context?.summary || '',
      enquiry_status: lead.status || 'pending_quote',
      amount: lead.amount ?? lead.metadata?.amount ?? '',
      currency: lead.currency || lead.metadata?.currency || 'USD',
      frequency: lead.frequency || lead.metadata?.frequency || 'one_off',
      start_date: lead.start_date || lead.metadata?.start_date || '',
      show_on_leads_board: lead.metadata?.show_on_leads_board ?? true,
    });
    errors.value = {};
    return;
  }

  Object.assign(form, {
    id: lead.id,
    first_name: lead.first_name || '',
    last_name: lead.last_name || '',
    email: lead.email || '',
    phone: lead.phone || '',
    company: lead.company || '',
    title: lead.title || '',
    status: lead.status || 'new',
    source: lead.source || '',
    pipeline_stage: lead.pipeline_stage || '',
    assigned_to_id: lead.assigned_to_id || null,
    campaign_id: lead.campaign_id || null,
    estimated_value: lead.estimated_value ?? '',
    currency: lead.currency || 'USD',
    website: lead.website || '',
    notes: lead.notes || '',
    metadata: lead.metadata || {},
  });
  // Hydrate metadata-backed arrays
  const md = form.metadata || {};
  contexts.value = Array.isArray(md.contexts) ? [...md.contexts] : [];
  potentialServices.value = Array.isArray(md.potential_services) ? [...md.potential_services] : [];
  socialLinks.value = Array.isArray(md.social_links) ? md.social_links.map(x => ({ label: x.label || '', url: x.url || '' })) : [];
  extraCampaignIds.value = Array.isArray(md.additional_campaign_ids) ? [...md.additional_campaign_ids] : [];
  errors.value = {};
  activeTab.value = 'basic';
  entryType.value = 'lead';
};

watch(() => props.lead, (val) => loadFromLead(val), { immediate: true });

// helpers for tag-like inputs
const addPotentialService = () => {
  const v = (servicesInput.value || '').trim();
  if (v && !potentialServices.value.includes(v)) {
    potentialServices.value.push(v);
  }
  servicesInput.value = '';
};
const removePotentialService = (idx) => potentialServices.value.splice(idx, 1);

const addContext = () => {
  const v = (contextsInput.value || '').trim();
  if (v && !contexts.value.includes(v)) {
    contexts.value.push(v);
  }
  contextsInput.value = '';
};
const removeContext = (idx) => contexts.value.splice(idx, 1);

const addSocial = () => {
  const label = (newSocial.label || '').trim();
  const url = (newSocial.url || '').trim();
  if (!url) return;
  socialLinks.value.push({ label, url });
  newSocial.label = '';
  newSocial.url = '';
};
const removeSocial = (idx) => socialLinks.value.splice(idx, 1);

const onSave = async () => {
  saving.value = true;
  errors.value = {};

  if (entryType.value === 'existing_client_enquiry') {
    if (!enquiryForm.project_id) {
      errors.value = { project_id: 'Please select a project.' };
      saving.value = false;
      return;
    }
    if (!enquiryForm.service_id.trim()) {
      errors.value = { service_id: 'Please enter a service name.' };
      saving.value = false;
      return;
    }

    const payload = {
      project_id: enquiryForm.project_id,
      client_id: enquiryForm.client_id,
      service_id: enquiryForm.service_id.trim(),
      description: enquiryForm.description,
      enquiry_status: enquiryForm.enquiry_status,
      amount: enquiryForm.amount === '' ? null : enquiryForm.amount,
      currency: enquiryForm.currency,
      frequency: enquiryForm.frequency,
      start_date: enquiryForm.start_date || null,
      show_on_leads_board: enquiryForm.show_on_leads_board,
    };

    try {
      const data = !enquiryForm.id
        ? await createExistingClientEnquiry(payload)
        : await updateExistingClientEnquiry(enquiryForm.id, payload);
      emit(enquiryForm.id ? 'lead-updated' : 'lead-created', data);
      emit('close');
    } catch (error) {
      if (error?.response?.status === 422) {
        errors.value = error.response.data.errors || {};
      } else {
        console.error('Enquiry save failed', error);
      }
    } finally {
      saving.value = false;
    }

    return;
  }

  // Frontend minimal validation: Require at least one of first or last name
  const first = (form.first_name || '').trim();
  const last = (form.last_name || '').trim();
  if (!first && !last) {
    errors.value = { first_name: 'Please enter at least a first or last name.' };
    saving.value = false;
    return;
  }

  // Merge metadata with new keys
  const md = { ...(form.metadata || {}) };
  md.contexts = contexts.value;
  md.potential_services = potentialServices.value;
  md.social_links = socialLinks.value;
  const addl = Array.isArray(extraCampaignIds.value) ? extraCampaignIds.value.filter(id => id !== form.campaign_id) : [];
  md.additional_campaign_ids = addl;
  form.metadata = md;

  try {
    if (!form.id) {
      const data = await createLead(form);
      emit('lead-created', data);
    } else {
      const data = await updateLead(form.id, form);
      emit('lead-updated', data);
    }
    emit('close');
  } catch (error) {
    if (error?.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      console.error('Save failed', error);
    }
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <Modal :show="props.show" @close="emit('close')">
    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">{{ entryType === 'existing_client_enquiry' ? (enquiryForm.id ? 'Edit Existing Client Enquiry' : 'Create Existing Client Enquiry') : (form.id ? 'Edit Lead' : 'Create Lead') }}</h3>
        <span v-if="entryType === 'lead' && form.id && props.lead?.lead_number" class="text-sm font-mono text-gray-500 bg-gray-100 px-2 py-1 rounded">
          {{ props.lead.lead_number }}
        </span>
      </div>

      <div class="mb-4 inline-flex rounded-lg bg-gray-100 p-1" v-if="!props.lead">
        <button type="button" @click="entryType='lead'" :class="['px-3 py-1.5 text-sm rounded-md transition', entryType === 'lead' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">Lead</button>
        <button type="button" @click="entryType='existing_client_enquiry'" :class="['px-3 py-1.5 text-sm rounded-md transition', entryType === 'existing_client_enquiry' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">Existing Client Enquiry</button>
      </div>

      <!-- Tabs -->
      <div v-if="entryType === 'lead'" class="mb-4 border-b border-gray-200">
        <nav class="-mb-px flex space-x-6" aria-label="Tabs">
          <button type="button" @click="activeTab='basic'" :class="['py-2 px-1 border-b-2 text-sm font-medium', activeTab==='basic' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Basic</button>
          <button type="button" @click="activeTab='additional'" :class="['py-2 px-1 border-b-2 text-sm font-medium', activeTab==='additional' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Additional</button>
        </nav>
      </div>

      <!-- Basic Tab -->
      <div v-if="entryType === 'lead' && activeTab==='basic'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <InputLabel value="First Name" />
          <TextInput v-model="form.first_name" class="mt-1 block w-full" />
          <InputError :message="errors.first_name" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Last Name" />
          <TextInput v-model="form.last_name" class="mt-1 block w-full" />
          <InputError :message="errors.last_name" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Email" />
          <TextInput v-model="form.email" class="mt-1 block w-full" />
          <InputError :message="errors.email" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Phone" />
          <TextInput v-model="form.phone" class="mt-1 block w-full" />
          <InputError :message="errors.phone" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Company" />
          <TextInput v-model="form.company" class="mt-1 block w-full" />
          <InputError :message="errors.company" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Title" />
          <TextInput v-model="form.title" class="mt-1 block w-full" />
          <InputError :message="errors.title" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Status" />
          <SelectDropdown :options="statusOpts" v-model="form.status" placeholder="Select status" />
          <InputError :message="errors.status" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Source" />
          <SelectDropdown :options="sourceOpts" v-model="form.source" placeholder="Select source" />
          <InputError :message="errors.source" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Assigned To" />
          <SelectDropdown :options="userOptions" v-model="form.assigned_to_id" placeholder="Unassigned" />
          <InputError :message="errors.assigned_to_id" class="mt-1" />
        </div>
      </div>

      <!-- Additional Tab -->
      <div v-else-if="entryType === 'lead'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <InputLabel value="Estimated Value" />
            <TextInput v-model="form.estimated_value" type="number" step="0.01" class="mt-1 block w-full" />
            <InputError :message="errors.estimated_value" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Currency (ISO 4217)" />
            <TextInput v-model="form.currency" maxlength="3" class="mt-1 block w-full uppercase" @input="form.currency = (form.currency || '').toUpperCase().slice(0,3)" placeholder="USD" />
            <InputError :message="errors.currency" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Website" />
            <TextInput v-model="form.website" class="mt-1 block w-full" />
            <InputError :message="errors.website" class="mt-1" />
          </div>
        </div>

        <div>
          <InputLabel value="Notes" />
          <textarea v-model="form.notes" class="mt-1 block w-full border-gray-300 rounded-md" rows="3"></textarea>
          <InputError :message="errors.notes" class="mt-1" />
        </div>

        <!-- Potential Services (tags) -->
        <div>
          <InputLabel value="Potential Services" />
          <div class="flex items-center mt-1">
            <input v-model="servicesInput" @keydown.enter.prevent="addPotentialService" type="text" class="flex-grow rounded-l-md border-gray-300 shadow-sm" placeholder="e.g., SEO, Web Design" />
            <button @click.prevent="addPotentialService" type="button" class="px-4 py-2 bg-gray-200 rounded-r-md text-sm font-medium hover:bg-gray-300">Add</button>
          </div>
          <div class="mt-2 flex flex-wrap gap-2">
            <span v-for="(service, index) in potentialServices" :key="index" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
              {{ service }}
              <button @click.prevent="removePotentialService(index)" class="ml-1.5 flex-shrink-0 text-indigo-400 hover:text-indigo-500">
                <svg class="h-2 w-2" stroke="currentColor" fill="none" viewBox="0 0 8 8"><path stroke-linecap="round" stroke-width="1.5" d="M1 1l6 6m0-6L1 7" /></svg>
              </button>
            </span>
          </div>
        </div>

        <!-- Contexts (tags) -->
        <div>
          <InputLabel value="Contexts" />
          <div class="flex items-center mt-1">
            <input v-model="contextsInput" @keydown.enter.prevent="addContext" type="text" class="flex-grow rounded-l-md border-gray-300 shadow-sm" placeholder="Add a context (e.g., Found on Upwork)" />
            <button @click.prevent="addContext" type="button" class="px-4 py-2 bg-gray-200 rounded-r-md text-sm font-medium hover:bg-gray-300">Add</button>
          </div>
          <div class="mt-2 flex flex-wrap gap-2">
            <span v-for="(ctx, index) in contexts" :key="index" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
              {{ ctx }}
              <button @click.prevent="removeContext(index)" class="ml-1.5 flex-shrink-0 text-emerald-400 hover:text-emerald-600">
                <svg class="h-2 w-2" stroke="currentColor" fill="none" viewBox="0 0 8 8"><path stroke-linecap="round" stroke-width="1.5" d="M1 1l6 6m0-6L1 7" /></svg>
              </button>
            </span>
          </div>
        </div>

        <!-- Social Media Links -->
        <div>
          <InputLabel value="Social Media Links" />
          <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mt-1">
            <TextInput v-model="newSocial.label" placeholder="Label (e.g., LinkedIn)" />
            <TextInput v-model="newSocial.url" placeholder="URL" class="md:col-span-2" />
          </div>
          <div class="mt-2">
            <button @click.prevent="addSocial" type="button" class="px-3 py-1.5 bg-gray-200 rounded-md text-sm font-medium hover:bg-gray-300">Add Link</button>
          </div>
          <ul class="mt-3 space-y-2">
            <li v-for="(link, idx) in socialLinks" :key="idx" class="flex items-center justify-between bg-gray-50 rounded px-3 py-2 text-sm">
              <div class="truncate"><span class="font-medium">{{ link.label || 'Link' }}</span>: <a :href="link.url" target="_blank" class="text-indigo-600 hover:underline break-all">{{ link.url }}</a></div>
              <button class="text-red-600 hover:underline" @click="removeSocial(idx)">Remove</button>
            </li>
          </ul>
        </div>

        <!-- Campaign Linking -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <InputLabel value="Primary Campaign" />
            <SelectDropdown :options="campaignOptions" v-model="form.campaign_id" placeholder="None" />
            <p class="text-xs text-gray-500 mt-1" v-if="loadingCampaigns">Loading campaigns…</p>
          </div>
          <div>
            <InputLabel value="Additional Campaigns" />
            <MultiSelectDropdown
              :options="campaignOptions"
              v-model="extraCampaignIds"
              :isMulti="true"
              placeholder="None"
            />
          </div>
        </div>
      </div>

      <div v-else class="space-y-5">
        <div v-if="loadingSupportingData" class="text-sm text-gray-500">Loading projects and clients…</div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <InputLabel value="Client" />
            <SelectDropdown :options="clientOptions" v-model="enquiryForm.client_id" placeholder="Select client" />
            <InputError :message="errors.client_id" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Project" />
            <SelectDropdown :options="projectOptions" v-model="enquiryForm.project_id" placeholder="Select project" />
            <InputError :message="errors.project_id" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Service" />
            <TextInput v-model="enquiryForm.service_id" class="mt-1 block w-full" placeholder="e.g. Flyer Design" />
            <InputError :message="errors.service_id" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Status" />
            <SelectDropdown :options="enquiryStatusOptions" v-model="enquiryForm.enquiry_status" placeholder="Select status" />
            <InputError :message="errors.enquiry_status" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Amount" />
            <TextInput v-model="enquiryForm.amount" type="number" step="0.01" class="mt-1 block w-full" />
            <InputError :message="errors.amount" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Currency" />
            <TextInput v-model="enquiryForm.currency" maxlength="10" class="mt-1 block w-full uppercase" @input="enquiryForm.currency = (enquiryForm.currency || '').toUpperCase()" />
            <InputError :message="errors.currency" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Frequency" />
            <SelectDropdown :options="[{ label: 'One Off', value: 'one_off' }, { label: 'Monthly', value: 'monthly' }]" v-model="enquiryForm.frequency" placeholder="Select frequency" />
            <InputError :message="errors.frequency" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Start Date" />
            <TextInput v-model="enquiryForm.start_date" type="date" class="mt-1 block w-full" />
            <InputError :message="errors.start_date" class="mt-1" />
          </div>
        </div>

        <div>
          <InputLabel value="Description" />
          <textarea v-model="enquiryForm.description" class="mt-1 block w-full border-gray-300 rounded-md" rows="4"></textarea>
          <InputError :message="errors.description" class="mt-1" />
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="enquiryForm.show_on_leads_board" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
          Show on leads board
        </label>
      </div>

      <div class="mt-6 flex justify-end gap-2">
        <SecondaryButton :disabled="saving" @click="emit('close')">Cancel</SecondaryButton>
        <PrimaryButton :disabled="saving" @click="onSave">{{ saving ? 'Saving...' : ((entryType === 'existing_client_enquiry' ? enquiryForm.id : form.id) ? 'Update' : 'Create') }}</PrimaryButton>
      </div>
    </div>
  </Modal>
</template>
