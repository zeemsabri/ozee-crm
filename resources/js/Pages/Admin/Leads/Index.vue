<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

import { useLeads } from '@/Composables/useLeads.js';
import LeadsFilters from './components/LeadsFilters.vue';
import LeadKanban from './components/LeadKanban.vue';
import LeadFormModal from './components/LeadFormModal.vue';

const props = defineProps({
  sourceOptions: { type: Array, default: () => [] }
});

const statusOptions = [
  { label: 'New', value: 'new' },
  { label: 'Contacted', value: 'contacted' },
  { label: 'Qualified', value: 'qualified' },
  { label: 'Converted', value: 'converted' },
  { label: 'Lost', value: 'lost' },
];

const {
  leads,
  leadsByStatus,
  loading,
  generalError,
  filters,
  users,
  fetchUsers,
  fetchLeads,
  resetFilters,
  deleteLead,
  updateLead,
  currentPage,
  lastPage,
  total,
  changePage,
} = useLeads();

const showForm = ref(false);
const editingLead = ref(null);

const openCreate = () => {
  editingLead.value = null;
  showForm.value = true;
};

const openEdit = (lead) => {
  editingLead.value = lead;
  showForm.value = true;
};

const onMove = async ({ id, status }) => {
  try {
    const lead = leads.value.find(l => l.id === id);
    if (lead && lead.status === status) return;
    await updateLead(id, { status });
  } catch (e) {
    console.error('Failed to move lead', e);
  }
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
        <PrimaryButton @click="openCreate">New Lead</PrimaryButton>
      </div>
    </template>

    <div class="py-6 min-h-screen w-full">
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <div v-if="generalError" class="mb-4 text-red-600">{{ generalError }}</div>

            <LeadsFilters
              :filters="filters"
              :source-options="props.sourceOptions"
              :status-options="statusOptions"
              :users="users"
              :loading="loading"
              @apply="() => { currentPage = 1; fetchLeads(); }"
              @reset="resetFilters"
            />

            <LeadKanban
              :leads-by-status="leadsByStatus"
              :loading="loading"
              @edit="openEdit"
              @delete="(lead) => deleteLead(lead.id)"
              @move="onMove"
            />

            <div class="mt-4 flex items-center justify-between">
              <div class="text-sm text-gray-600">Page {{ currentPage }} of {{ lastPage }} — Total {{ total }}</div>
              <div class="flex gap-2">
                <button class="px-3 py-1.5 text-sm bg-gray-100 rounded disabled:opacity-50" :disabled="currentPage <= 1" @click="changePage(currentPage - 1)">Previous</button>
                <button class="px-3 py-1.5 text-sm bg-gray-100 rounded disabled:opacity-50" :disabled="currentPage >= lastPage" @click="changePage(currentPage + 1)">Next</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <LeadFormModal
      :show="showForm"
      :lead="editingLead"
      :users="users"
      :source-options="props.sourceOptions"
      :status-options="statusOptions"
      @close="showForm = false"
      @lead-created="fetchLeads()"
      @lead-updated="fetchLeads()"
    />
  </AuthenticatedLayout>
</template>
