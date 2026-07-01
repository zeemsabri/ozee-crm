<script setup>
import { reactive, watch, computed, ref, onMounted, onUnmounted } from 'vue';
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
import SchedulePickerModal from '@/Components/Scheduler/SchedulePickerModal.vue';
import { useEmbeddedScheduler } from '@/Composables/useEmbeddedScheduler.js';

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
const projectTimezone = ref('');
const nowTick = ref(Date.now());
let clockInterval = null;

const { showScheduleModal, scheduleDraft, open, close, onSaveDraft, attachAfterCreate } = useEmbeddedScheduler();


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
  if (greetingType.value === 'custom') {
    return `Hi ${customGreetingName.value.trim() || 'there'},`;
  }

  if (recipientMode.value === 'leads' && form.lead_ids && form.lead_ids.length > 0) {
    const names = form.lead_ids.map(id => {
      const lead = leads.value.find(l => l.id === id);
      if (!lead) return null;
      const fullName = (lead.first_name ? lead.first_name + ' ' : '') + (lead.last_name || '');
      const nameParts = fullName.trim().split(' ').filter(Boolean);
      const firstName = nameParts[0] || '';
      const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
      const displayName = fullName.trim() || lead.company || 'there';
      switch (greetingType.value) {
        case 'full_name':
          return displayName;
        case 'first_name':
          return firstName || displayName;
        case 'last_name':
          return lastName || displayName;
        default:
          return displayName;
      }
    }).filter(Boolean);

    if (names.length > 0) {
      if (names.length === 1) {
        return `Hi ${names[0]},`;
      }
      return `Hi ${names.join(' & ')},`;
    }
  } else if (form.client_ids && form.client_ids.length > 0) {
    const names = form.client_ids.map(id => {
      const client = projectClients.value.find(c => c.id === id);
      if (!client) return null;
      const nameParts = client.name.split(' ').filter(Boolean);
      const firstName = nameParts[0] || '';
      const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
      switch (greetingType.value) {
        case 'full_name':
          return client.name;
        case 'first_name':
          return firstName;
        case 'last_name':
          return lastName;
        default:
          return client.name;
      }
    }).filter(Boolean);

    if (names.length > 0) {
      if (names.length === 1) {
        return `Hi ${names[0]},`;
      }
      return `Hi ${names.join(' & ')},`;
    }
  }
  return 'Hi there,';
});

const selectedClient = computed(() => {
  if (recipientMode.value !== 'clients') return null;
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

async function fetchProjectTimezone(projectId) {
  if (!projectId) {
    projectTimezone.value = '';
    return;
  }
  try {
    const response = await axios.get(`/api/projects/${projectId}/sections/basic`);
    projectTimezone.value = response.data?.timezone || '';
  } catch (e) {
    projectTimezone.value = '';
    console.error('Failed to fetch project timezone:', e);
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
  fetchProjectTimezone(form.project_id);
}, { immediate: true });

watch(() => form.project_id, (newProjectId) => {
  fetchProjectTimezone(newProjectId);
});

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
    payload.status = scheduleDraft.value ? 'delayed' : 'draft';

    const response = await axios.post('/api/emails', payload);
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

onMounted(() => {
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
      <div v-if="projectTimezone && projectCurrentTime" class="mt-3 p-3 rounded-md bg-blue-50 border border-blue-100 text-sm text-blue-900">
        <div><span class="font-semibold">Project timezone:</span> {{ projectTimezone }}</div>
        <div><span class="font-semibold">Current time:</span> {{ projectCurrentTime }}</div>
      </div>
      <div v-if="selectedClient?.timezone && selectedClientCurrentTime" class="mt-2 p-3 rounded-md bg-emerald-50 border border-emerald-100 text-sm text-emerald-900">
        <div><span class="font-semibold">Primary client timezone:</span> {{ selectedClient.timezone }}</div>
        <div><span class="font-semibold">Current time:</span> {{ selectedClientCurrentTime }}</div>
      </div>
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

    <div class="rounded-lg border border-gray-200 p-3 mb-4">
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

  <SchedulePickerModal :show="showScheduleModal" title="Schedule Email" @close="close" @save="onSaveDraft" />
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
