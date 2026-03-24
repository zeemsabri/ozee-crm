<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

import { useLeads } from '@/Composables/useLeads.js';
import LeadsFilters from './components/LeadsFilters.vue';
import LeadKanban from './components/LeadKanban.vue';
import LeadCard from './components/LeadCard.vue';
import LeadFormModal from './components/LeadFormModal.vue';

const props = defineProps({
  sourceOptions: { type: Array, default: () => [] }
});

const statusOptions = [
  { label: 'New', value: 'new' },
  { label: 'Hot Incoming', value: 'hot_incoming' },
  { label: 'Hot Outgoing', value: 'hot_outgoing' },
  { label: 'Processing', value: 'processing' },
  { label: 'Contacted', value: 'contacted' },
  { label: 'Outreach Sent', value: 'outreach_sent' },
  { label: 'Qualified', value: 'qualified' },
  { label: 'Converted', value: 'converted' },
  { label: 'Sequence Completed', value: 'sequence_completed' },
  { label: 'Generation Failed', value: 'generation_failed' },
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
const viewMode = ref('kanban'); // 'kanban' or 'list'

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
        <div class="flex items-center gap-4">
            <div class="flex bg-gray-100 p-1 rounded-lg">
                <button 
                class="px-3 py-1 text-sm rounded-md transition"
                :class="viewMode === 'kanban' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700'"
                @click="viewMode = 'kanban'"
                >
                Kanban
                </button>
                <button 
                class="px-3 py-1 text-sm rounded-md transition"
                :class="viewMode === 'list' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700'"
                @click="viewMode = 'list'"
                >
                All Leads
                </button>
            </div>
            <PrimaryButton @click="openCreate">New Lead</PrimaryButton>
        </div>
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
              v-if="viewMode === 'kanban'"
              :leads-by-status="leadsByStatus"
              :loading="loading"
              :filters="filters"
              @edit="openEdit"
              @delete="(lead) => deleteLead(lead.id)"
              @move="onMove"
            />

            <div v-else class="mt-6">
                <div v-if="loading" class="flex justify-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                </div>
                <div v-else-if="leads.length === 0" class="text-center py-12 text-gray-500">
                    No leads found.
                </div>
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div v-for="lead in leads" :key="lead.id">
                        <LeadCard 
                            :lead="lead"
                            @edit="openEdit"
                            @delete="(lead) => deleteLead(lead.id)"
                        />
                    </div>
                </div>
            </div>

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
