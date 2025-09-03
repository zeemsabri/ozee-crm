<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  sourceOptions: { type: Array, default: () => [] }
});

const authUser = computed(() => usePage().props.auth.user);

// List state
const leads = ref([]);
const loading = ref(false);
const generalError = ref('');

// Pagination state
const currentPage = ref(1);
const perPage = ref(15);
const total = ref(0);
const lastPage = ref(1);

// Filters
const q = ref('');
const status = ref('');
const source = ref('');
const assigned_to_id = ref('');

// Users for assignment dropdown
const users = ref([]);

// Modals
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);

// Forms
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
  estimated_value: '',
  currency: 'USD',
  assigned_to_id: null,
  website: '',
  country: '',
  state: '',
  city: '',
  address: '',
  zip: '',
  tags: '',
  notes: '',
});

const errors = ref({});
const leadToDelete = ref(null);

const statusOptions = [
  { label: 'New', value: 'new' },
  { label: 'Contacted', value: 'contacted' },
  { label: 'Qualified', value: 'qualified' },
  { label: 'Converted', value: 'converted' },
  { label: 'Lost', value: 'lost' },
];

const fetchUsers = async () => {
  try {
    const { data } = await axios.get('/api/users');
    users.value = data;
  } catch (e) {
    console.error('Failed to load users', e);
  }
};

const fetchLeads = async () => {
  loading.value = true;
  generalError.value = '';
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
    };
    if (q.value) params.q = q.value;
    if (status.value) params.status = status.value;
    if (source.value) params.source = source.value;
    if (assigned_to_id.value) params.assigned_to_id = assigned_to_id.value;

    const { data } = await axios.get('/api/leads', { params });
    // Laravel paginator response structure
    leads.value = data.data ?? data;
    const meta = data.meta ?? null;
    if (meta) {
      currentPage.value = meta.current_page;
      perPage.value = meta.per_page;
      total.value = meta.total;
      lastPage.value = meta.last_page;
    } else {
      total.value = Array.isArray(leads.value) ? leads.value.length : 0;
      lastPage.value = 1;
    }
  } catch (error) {
    console.error('Error fetching leads', error);
    generalError.value = error?.response?.data?.message || 'Failed to fetch leads.';
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('authToken');
      window.location.href = '/login';
    }
  } finally {
    loading.value = false;
  }
};

// CRUD handlers
const openCreateModal = () => {
  Object.assign(form, {
    id: null,
    first_name: '', last_name: '', email: '', phone: '',
    company: '', title: '', status: 'new', source: '',
    pipeline_stage: '', estimated_value: '', currency: 'USD', assigned_to_id: null,
    website: '', country: '', state: '', city: '', address: '', zip: '',
    tags: '', notes: '',
  });
  errors.value = {};
  showCreateModal.value = true;
};

const createLead = async () => {
  errors.value = {};
  generalError.value = '';
  try {
    const { data } = await axios.post('/api/leads', form);
    showCreateModal.value = false;
    await fetchLeads();
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      generalError.value = error?.response?.data?.message || 'Failed to create lead';
    }
  }
};

const openEditModal = (lead) => {
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
    estimated_value: lead.estimated_value ?? '',
    currency: lead.currency || 'USD',
    assigned_to_id: lead.assigned_to_id || null,
    website: lead.website || '',
    country: lead.country || '',
    state: lead.state || '',
    city: lead.city || '',
    address: lead.address || '',
    zip: lead.zip || '',
    tags: lead.tags || '',
    notes: lead.notes || '',
  });
  errors.value = {};
  showEditModal.value = true;
};

const updateLead = async () => {
  if (!form.id) return;
  errors.value = {};
  generalError.value = '';
  try {
    await axios.put(`/api/leads/${form.id}`, form);
    showEditModal.value = false;
    await fetchLeads();
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      generalError.value = error?.response?.data?.message || 'Failed to update lead';
    }
  }
};

const openDeleteModal = (lead) => {
  leadToDelete.value = lead;
  showDeleteModal.value = true;
};

const deleteLead = async () => {
  if (!leadToDelete.value) return;
  try {
    await axios.delete(`/api/leads/${leadToDelete.value.id}`);
    showDeleteModal.value = false;
    leadToDelete.value = null;
    await fetchLeads();
  } catch (error) {
    console.error('Failed to delete lead', error);
    generalError.value = error?.response?.data?.message || 'Failed to delete lead';
  }
};

const fullName = (lead) => {
  const fn = lead.first_name || '';
  const ln = lead.last_name || '';
  return `${fn} ${ln}`.trim() || '(no name)';
};

const resetFilters = () => {
  q.value = '';
  status.value = '';
  source.value = '';
  assigned_to_id.value = '';
  currentPage.value = 1;
  fetchLeads();
};

const changePage = (page) => {
  if (page < 1 || page > lastPage.value) return;
  currentPage.value = page;
  fetchLeads();
};

onMounted(async () => {
  await fetchUsers();
  await fetchLeads();
});
</script>

<template>
  <Head title="Leads" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Leads</h2>
        <PrimaryButton @click="openCreateModal">New Lead</PrimaryButton>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <div v-if="generalError" class="mb-4 text-red-600">{{ generalError }}</div>

            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-5">
              <div>
                <InputLabel for="q" value="Search" />
                <TextInput id="q" v-model="q" class="mt-1 block w-full" placeholder="Name, email, phone, company" @keyup.enter="() => { currentPage = 1; fetchLeads(); }" />
              </div>
              <div>
                <InputLabel for="status" value="Status" />
                <select id="status" v-model="status" class="mt-1 block w-full border-gray-300 rounded-md">
                  <option value="">All</option>
                  <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <InputLabel for="source" value="Source" />
                <select id="source" v-model="source" class="mt-1 block w-full border-gray-300 rounded-md">
                  <option value="">All</option>
                  <option v-for="opt in props.sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <InputLabel for="assigned_to" value="Assigned To" />
                <select id="assigned_to" v-model="assigned_to_id" class="mt-1 block w-full border-gray-300 rounded-md">
                  <option value="">Any</option>
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
              <div class="flex items-end gap-2">
                <PrimaryButton @click="() => { currentPage = 1; fetchLeads(); }">Apply</PrimaryButton>
                <SecondaryButton @click="resetFilters">Reset</SecondaryButton>
              </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned</th>
                    <th class="px-4 py-2"></th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-if="loading">
                    <td colspan="8" class="px-4 py-6 text-center text-gray-500">Loading...</td>
                  </tr>
                  <tr v-else-if="!leads || leads.length === 0">
                    <td colspan="8" class="px-4 py-6 text-center text-gray-500">No leads found</td>
                  </tr>
                  <tr v-for="lead in leads" :key="lead.id">
                    <td class="px-4 py-2 whitespace-nowrap">{{ fullName(lead) }}</td>
                    <td class="px-4 py-2 whitespace-nowrap">{{ lead.email || '-' }}</td>
                    <td class="px-4 py-2 whitespace-nowrap">{{ lead.phone || '-' }}</td>
                    <td class="px-4 py-2 whitespace-nowrap">{{ lead.company || '-' }}</td>
                    <td class="px-4 py-2 whitespace-nowrap capitalize">{{ lead.status || '-' }}</td>
                    <td class="px-4 py-2 whitespace-nowrap">{{ lead.source || '-' }}</td>
                    <td class="px-4 py-2 whitespace-nowrap">{{ lead.assigned_to?.name || lead.assigned_to_id ? (users.find(u => u.id === lead.assigned_to_id)?.name || '-') : '-' }}</td>
                    <td class="px-4 py-2 text-right">
                      <div class="flex gap-2 justify-end">
                        <SecondaryButton @click="openEditModal(lead)">Edit</SecondaryButton>
                        <DangerButton @click="openDeleteModal(lead)">Delete</DangerButton>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4 flex items-center justify-between">
              <div class="text-sm text-gray-600">Page {{ currentPage }} of {{ lastPage }} — Total {{ total }}</div>
              <div class="flex gap-2">
                <SecondaryButton :disabled="currentPage <= 1" @click="changePage(currentPage - 1)">Previous</SecondaryButton>
                <SecondaryButton :disabled="currentPage >= lastPage" @click="changePage(currentPage + 1)">Next</SecondaryButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <Modal :show="showCreateModal" @close="showCreateModal = false">
      <div class="p-6">
        <h3 class="text-lg font-semibold mb-4">Create Lead</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
            <select v-model="form.status" class="mt-1 block w-full border-gray-300 rounded-md">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <InputError :message="errors.status" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Source" />
            <select v-model="form.source" class="mt-1 block w-full border-gray-300 rounded-md">
              <option value="">Select source</option>
              <option v-for="opt in props.sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <InputError :message="errors.source" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Assigned To" />
            <select v-model="form.assigned_to_id" class="mt-1 block w-full border-gray-300 rounded-md">
              <option :value="null">Unassigned</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <InputError :message="errors.assigned_to_id" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Estimated Value" />
            <TextInput v-model="form.estimated_value" type="number" step="0.01" class="mt-1 block w-full" />
            <InputError :message="errors.estimated_value" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Website" />
            <TextInput v-model="form.website" class="mt-1 block w-full" />
            <InputError :message="errors.website" class="mt-1" />
          </div>
          <div class="md:col-span-2">
            <InputLabel value="Notes" />
            <textarea v-model="form.notes" class="mt-1 block w-full border-gray-300 rounded-md" rows="3"></textarea>
            <InputError :message="errors.notes" class="mt-1" />
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
          <PrimaryButton @click="createLead">Create</PrimaryButton>
        </div>
      </div>
    </Modal>

    <!-- Edit Modal -->
    <Modal :show="showEditModal" @close="showEditModal = false">
      <div class="p-6">
        <h3 class="text-lg font-semibold mb-4">Edit Lead</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
            <select v-model="form.status" class="mt-1 block w-full border-gray-300 rounded-md">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <InputError :message="errors.status" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Source" />
            <select v-model="form.source" class="mt-1 block w-full border-gray-300 rounded-md">
              <option value="">Select source</option>
              <option v-for="opt in props.sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <InputError :message="errors.source" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Assigned To" />
            <select v-model="form.assigned_to_id" class="mt-1 block w-full border-gray-300 rounded-md">
              <option :value="null">Unassigned</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <InputError :message="errors.assigned_to_id" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Estimated Value" />
            <TextInput v-model="form.estimated_value" type="number" step="0.01" class="mt-1 block w-full" />
            <InputError :message="errors.estimated_value" class="mt-1" />
          </div>
          <div>
            <InputLabel value="Website" />
            <TextInput v-model="form.website" class="mt-1 block w-full" />
            <InputError :message="errors.website" class="mt-1" />
          </div>
          <div class="md:col-span-2">
            <InputLabel value="Notes" />
            <textarea v-model="form.notes" class="mt-1 block w-full border-gray-300 rounded-md" rows="3"></textarea>
            <InputError :message="errors.notes" class="mt-1" />
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <SecondaryButton @click="showEditModal = false">Cancel</SecondaryButton>
          <PrimaryButton @click="updateLead">Update</PrimaryButton>
        </div>
      </div>
    </Modal>

    <!-- Delete Modal -->
    <Modal :show="showDeleteModal" @close="showDeleteModal = false">
      <div class="p-6">
        <h3 class="text-lg font-semibold mb-4">Delete Lead</h3>
        <p>Are you sure you want to delete this lead? This action cannot be undone.</p>
        <div class="mt-6 flex justify-end gap-2">
          <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
          <DangerButton @click="deleteLead">Delete</DangerButton>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>
