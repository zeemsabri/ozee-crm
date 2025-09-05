<script setup>
import { reactive, watch, computed, ref, onMounted } from 'vue';
import { usePermissions } from '@/Directives/permissions.js';
import axios from 'axios';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import OZeeMultiSelect from '@/Components/CustomMultiSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import EmailEditor from '@/Components/EmailEditor.vue';
import Modal from '@/Components/Modal.vue';
import { useEmailSignature } from '@/Composables/useEmailSignature';
import { useEmailTemplate } from '@/Composables/useEmailTemplate';

const props = defineProps({
  projectId: {
    type: [Number, String],
    required: false,
    default: null,
  },
  userProjectRole: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  // Force recipient mode and preselect leads when used from Lead Details
  forceRecipientMode: {
    type: String,
    required: false,
    default: null, // 'clients' | 'leads' | null
  },
  presetLeadIds: {
    type: Array,
    required: false,
    default: () => [],
  },
  hideRecipientControls: {
    type: Boolean,
    required: false,
    default: false,
  },
});

const emit = defineEmits(['submitted', 'error']);

// --- Local form state (mirrors ComponseEmailModal.vue) ---
const form = reactive({
  project_id: props.projectId !== null ? Number(props.projectId) : null,
  client_ids: [],
  lead_ids: [],
  subject: '',
  body: '',
  status: 'pending_approval',
});

// Validation errors
const errors = reactive({
  client_ids: null,
  subject: null,
  body: null,
});

// Permissions
const { canDo } = usePermissions();
const canContactLead = computed(() => canDo('contact_lead').value);

// Recipient mode
const recipientMode = ref('clients'); // 'clients' | 'leads'

// Initialize forced recipient mode and preset leads if provided
if (props.forceRecipientMode === 'leads') {
  recipientMode.value = 'leads';
}

// Projects for selection (client mode requirement; lead mode optional)
const projects = ref([]);
const loadingProjects = ref(false);
const projectOptions = computed(() => (projects.value || []).map(p => ({ value: p.id, label: p.name })));

// Clients state
const projectClients = ref([]);
const loadingClients = ref(false);
const clientsError = ref('');

// Leads state
const leads = ref([]);
const loadingLeads = ref(false);
const leadsError = ref('');


// Greeting state
const greetingType = ref('full_name');
const customGreetingName = ref('');
const greetingTypeOptions = ref([
  { value: 'full_name', label: 'Full Name' },
  { value: 'first_name', label: 'First Name' },
  { value: 'last_name', label: 'Last Name' },
  { value: 'custom', label: 'Custom' },
]);

// Signature
const { userSignature } = useEmailSignature(computed(() => props.userProjectRole));

// useEmailTemplate
const editorBodyContent = computed(() => form.body || '');
const { processedHtmlBody } = useEmailTemplate(editorBodyContent);

// Greeting text (display only)
const greetingText = computed(() => {
  // Determine name from first selected recipient (client or lead)
  if (recipientMode.value === 'leads' && form.lead_ids && form.lead_ids.length > 0) {
    const firstLeadId = form.lead_ids[0];
    const firstLead = leads.value.find(l => l.id === firstLeadId);
    if (firstLead) {
      const fullName = (firstLead.first_name ? firstLead.first_name + ' ' : '') + (firstLead.last_name || '');
      const nameParts = fullName.trim().split(' ').filter(Boolean);
      const firstName = nameParts[0] || '';
      const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
      const displayName = fullName.trim() || firstLead.company || 'there';
      switch (greetingType.value) {
        case 'full_name':
          return `Hi ${displayName},`;
        case 'first_name':
          return `Hi ${firstName || displayName},`;
        case 'last_name':
          return `Hi ${lastName || displayName},`;
        case 'custom':
          return `Hi ${customGreetingName.value.trim() || 'there'},`;
        default:
          return `Hi ${displayName},`;
      }
    }
  } else if (form.client_ids && form.client_ids.length > 0) {
    const firstClientId = form.client_ids[0];
    const firstClient = projectClients.value.find(c => c.id === firstClientId);
    if (firstClient) {
      const nameParts = firstClient.name.split(' ').filter(Boolean);
      const firstName = nameParts[0] || '';
      const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
      switch (greetingType.value) {
        case 'full_name':
          return `Hi ${firstClient.name},`;
        case 'first_name':
          return `Hi ${firstName},`;
        case 'last_name':
          return `Hi ${lastName},`;
        case 'custom':
          return `Hi ${customGreetingName.value.trim() || 'there'},`;
        default:
          return `Hi ${firstClient.name},`;
      }
    }
  }
  return 'Hi there,';
});

// Insert Link modal state
const showInsertLinkModal = ref(false);
const linkText = ref('');
const linkUrl = ref('');
const linkError = ref('');

// Insert List modal state
const showInsertListModal = ref(false);
const listItemsInput = ref('');
const listType = ref('bullet'); // bullet | numbered
const listError = ref('');

function resetValidation() {
  errors.client_ids = null;
  errors.subject = null;
  errors.body = null;
}

async function fetchClients() {
  if (!form.project_id) {
    projectClients.value = [];
    return;
  }
  loadingClients.value = true;
  clientsError.value = '';
  try {
    const response = await axios.get(`/api/projects/${form.project_id}/sections/clients?type=clients`);
    projectClients.value = response.data;
  } catch (e) {
    console.error('Failed to fetch project clients:', e);
    clientsError.value = e.response?.data?.message || 'Failed to load client data.';
  } finally {
    loadingClients.value = false;
  }
}

async function fetchLeads() {
  loadingLeads.value = true;
  leadsError.value = '';
  try {
    const { data } = await axios.get('/api/leads', { params: { per_page: 100 } });
    const list = data?.data ?? data;
    leads.value = Array.isArray(list) ? list : [];
  } catch (e) {
    console.error('Failed to fetch leads:', e);
    leadsError.value = e.response?.data?.message || 'Failed to load leads.';
  } finally {
    loadingLeads.value = false;
  }
}

async function fetchProjects() {
  loadingProjects.value = true;
  try {
    const response = await axios.get('/api/projects-simplified');
    projects.value = response.data || [];
  } catch (e) {
    console.error('Failed to fetch projects:', e);
  } finally {
    loadingProjects.value = false;
  }
}

watch(() => props.projectId, (newVal) => {
  form.project_id = newVal !== null ? Number(newVal) : null;
  form.client_ids = [];
  form.lead_ids = [];
  if (recipientMode.value === 'clients') {
    if (!projects.value.length) fetchProjects();
    fetchClients();
  } else {
    fetchLeads();
    if (!projects.value.length) fetchProjects();
  }
}, { immediate: true });

// When forced to leads and presetLeadIds provided, prefill lead_ids
watch(() => [props.forceRecipientMode, props.presetLeadIds], () => {
  if (props.forceRecipientMode === 'leads' && Array.isArray(props.presetLeadIds) && props.presetLeadIds.length) {
    form.lead_ids = [...props.presetLeadIds];
    // Ensure leads list is loaded for greeting name
    if (!leads.value.length) fetchLeads();
  }
}, { immediate: true });

// Insert Link
const openInsertLinkModal = () => {
  linkText.value = '';
  linkUrl.value = '';
  linkError.value = '';
  showInsertLinkModal.value = true;
};

const insertLinkIntoEditor = () => {
  if (!linkText.value.trim()) {
    linkError.value = 'Link text cannot be empty.';
    return;
  }
  let urlToInsert = linkUrl.value.trim();
  if (!urlToInsert.startsWith('http://') && !urlToInsert.startsWith('https://')) {
    urlToInsert = 'http://' + urlToInsert;
  }
  try { new URL(urlToInsert); } catch (e) {
    linkError.value = 'Please enter a valid URL (e.g., https://example.com or www.example.com).';
    return;
  }
  const formattedLink = `[${linkText.value.trim()}] {${urlToInsert}}`;
  form.body += formattedLink;
  showInsertLinkModal.value = false;
  linkText.value = '';
  linkUrl.value = '';
  linkError.value = '';
};

// Insert List
const openInsertListModal = () => {
  listItemsInput.value = '';
  listType.value = 'bullet';
  listError.value = '';
  showInsertListModal.value = true;
};

const insertListIntoEditor = () => {
  const items = listItemsInput.value.split('\n').map(i => i.trim()).filter(Boolean);
  if (items.length === 0) {
    listError.value = 'Please enter at least one list item.';
    return;
  }
  const listTag = listType.value === 'bullet' ? 'ul' : 'ol';
  let formattedList = `<${listTag}>`;
  items.forEach(item => { formattedList += `<li>${item}</li>`; });
  formattedList += `</${listTag}>`;
  form.body += formattedList;
  showInsertListModal.value = false;
  listItemsInput.value = '';
  listError.value = '';
};

function formatDataForApi() {
  const payload = { ...form };
  if (recipientMode.value === 'clients') {
    // Ensure project_id required in client flow
    if (!payload.project_id) {
      errors.client_ids = 'Please select a project first.';
      return null;
    }
    payload.client_ids = (payload.client_ids || []).map(id => ({ id }));
    payload.lead_ids = [];
  } else {
    // Lead flow: optional project; if not selected, leave null to let backend default to Project::LEADS
    payload.lead_ids = (payload.lead_ids || []).map(id => ({ id }));
    payload.client_ids = [];
    if (!props.projectId) {
      // keep payload.project_id as null if not chosen in lead mode
      payload.project_id = payload.project_id ? Number(payload.project_id) : null;
    }
  }
  payload.body = processedHtmlBody.value;
  payload.greeting_name = greetingText.value;
  payload.custom_greeting_name = customGreetingName.value.trim();
  payload.first_client_id = form.client_ids && form.client_ids.length ? form.client_ids[0] : null;
  return payload;
}

async function submit() {
  resetValidation();
  try {
    const payload = formatDataForApi();
    if (!payload) return; // client mode requires project
    await axios.post('/api/emails', payload);
    emit('submitted');
  } catch (e) {
    console.error('Failed to submit email:', e);
    const ve = e.response?.data?.errors;
    if (ve) {
      errors.client_ids = ve.client_ids?.[0] || ve.recipient?.[0] || null;
      errors.subject = ve.subject?.[0] || null;
      errors.body = ve.body?.[0] || null;
    }
    emit('error', e);
  }
}
</script>

<template>
  <div class="space-y-4">
      <!-- Recipient Type Toggle -->
    <div v-if="!hideRecipientControls" class="flex gap-3 items-center mb-2">
      <label class="inline-flex items-center">
        <input type="radio" class="mr-2" value="clients" v-model="recipientMode" @change="() => { form.client_ids=[]; form.lead_ids=[]; if (recipientMode==='clients') fetchClients(); }"> Clients
      </label>
      <label v-if="canContactLead" class="inline-flex items-center">
        <input type="radio" class="mr-2" value="leads" v-model="recipientMode" @change="() => { form.client_ids=[]; form.lead_ids=[]; if (recipientMode==='leads') fetchLeads(); }"> Leads
      </label>
    </div>

    <!-- Clients: Project selection required -->
    <div v-if="recipientMode==='clients'" class="mb-4 space-y-3">
      <div>
        <InputLabel for="client_project" value="Select Project" />
        <div v-if="loadingProjects" class="text-gray-500 text-sm mt-1">Loading projects...</div>
        <SelectDropdown
          v-else
          id="client_project"
          v-model="form.project_id"
          :options="projectOptions"
          value-key="value"
          label-key="label"
          placeholder="Choose a project"
          class="mt-1 w-full"
          @update:modelValue="fetchClients"
        />
      </div>
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
      />
      <InputError :message="errors.client_ids" class="mt-2" />
    </div>

    <!-- Leads Selector -->
    <div v-else-if="canContactLead" class="mb-4 space-y-4">
      <div>
        <InputLabel for="lead_project" value="Select Project (optional)" />
        <div v-if="loadingProjects" class="text-gray-500 text-sm mt-1">Loading projects...</div>
        <SelectDropdown
          v-else
          id="lead_project"
          v-model="form.project_id"
          :options="projectOptions"
          value-key="value"
          label-key="label"
          placeholder="Choose a project or leave empty"
          class="mt-1 w-full"
        />
        <p class="text-xs text-gray-500 mt-1">If not selected, backend will default to the Project::LEADS project.</p>
      </div>
      <InputLabel for="lead_ids" value="To (Leads)" />
      <div v-if="loadingLeads" class="text-gray-500 text-sm">Loading leads...</div>
      <div v-else-if="leadsError" class="text-red-500 text-sm">{{ leadsError }}</div>
      <OZeeMultiSelect
        v-else-if="!hideRecipientControls"
        v-model="form.lead_ids"
        :options="leads"
        placeholder="Select one or more leads"
        label-key="full_name"
        value-key="id"
      />
      <InputError :message="errors.client_ids" class="mt-2" />
    </div>


    <div class="mb-4">
      <InputLabel for="subject" value="Subject" />
      <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="form.subject" required />
      <InputError :message="errors.subject" class="mt-2" />
    </div>

    <div class="mb-6">
      <div class="mb-2 flex justify-end space-x-2">
        <SecondaryButton type="button" @click="openInsertListModal">Insert List</SecondaryButton>
        <SecondaryButton type="button" @click="openInsertLinkModal">Insert Link</SecondaryButton>
      </div>

      <div class="mb-4">
        <InputLabel for="greeting_type" value="Address Client By" />
        <SelectDropdown
          id="greeting_type"
          v-model="greetingType"
          :options="greetingTypeOptions"
          value-key="value"
          label-key="label"
          class="mt-1 block w-full"
        />
        <div v-if="greetingType === 'custom'" class="mt-2">
          <InputLabel for="custom_greeting_name" value="Custom Name" />
          <TextInput id="custom_greeting_name" type="text" class="mt-1 block w-full" v-model="customGreetingName" placeholder="e.g., Azaan" />
        </div>
      </div>

      <p class="text-gray-700 text-base mb-2">{{ greetingText }}</p>

      <InputLabel for="body" value="Email Body" class="sr-only" />
      <EmailEditor id="body" v-model="form.body" placeholder="Compose your email here..." height="300px" />
      <InputError :message="errors.body" class="mt-2" />
    </div>

    <div v-if="userSignature" class="unselectable-signature" v-html="userSignature"></div>

    <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
      <PrimaryButton @click="submit">Submit for Approval</PrimaryButton>
    </div>
  </div>

  <!-- Insert Link Modal -->
  <Modal :show="showInsertLinkModal" @close="showInsertLinkModal = false" max-width="md">
    <div class="p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Insert Link</h3>
      <div v-if="linkError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">{{ linkError }}</span>
      </div>
      <div class="mb-4">
        <InputLabel for="link_text" value="Link Text" />
        <TextInput id="link_text" type="text" class="mt-1 block w-full" v-model="linkText" @keyup.enter="insertLinkIntoEditor" />
      </div>
      <div class="mb-6">
        <InputLabel for="link_url" value="URL" />
        <TextInput id="link_url" type="text" class="mt-1 block w-full" v-model="linkUrl" placeholder="e.g., https://www.example.com" @keyup.enter="insertLinkIntoEditor" />
      </div>
      <div class="flex justify-end space-x-3">
        <SecondaryButton @click="showInsertLinkModal = false">Cancel</SecondaryButton>
        <PrimaryButton @click="insertLinkIntoEditor">Insert</PrimaryButton>
      </div>
    </div>
  </Modal>

  <!-- Insert List Modal -->
  <Modal :show="showInsertListModal" @close="showInsertListModal = false" max-width="md">
    <div class="p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Insert List</h3>
      <div v-if="listError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">{{ listError }}</span>
      </div>
      <div class="mb-4">
        <InputLabel for="list_items" value="List Items (one per line)" />
        <textarea id="list_items" rows="6" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="listItemsInput" placeholder="Enter each list item on a new line"></textarea>
      </div>
      <div class="mb-6">
        <InputLabel for="list_type" value="List Type" />
        <select id="list_type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="listType">
          <option value="bullet">Bulleted List</option>
          <option value="numbered">Numbered List</option>
        </select>
      </div>
      <div class="flex justify-end space-x-3">
        <SecondaryButton @click="showInsertListModal = false">Cancel</SecondaryButton>
        <PrimaryButton @click="insertListIntoEditor">Insert List</PrimaryButton>
      </div>
    </div>
  </Modal>
</template>

<style scoped>
.unselectable-signature {
  user-select: none;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  pointer-events: none;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
  font-size: 0.875rem;
  color: #6b7280;
}
.unselectable-signature a { pointer-events: auto; cursor: pointer; }
</style>
