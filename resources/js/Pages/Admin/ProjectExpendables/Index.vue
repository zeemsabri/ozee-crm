<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePermissions } from '@/Directives/permissions';
import { success, error } from '@/Utils/notification';

const projects = ref([]);
const selectedProjectId = ref(null);
const loading = ref(false);
const activeTab = ref('active');
const milestones = ref([]);

const { canDo } = usePermissions();
const canManageFinancial = canDo('manage_project_financial');

const tabs = [
  { id: 'active', label: 'Active' },
  { id: 'completed', label: 'Completed' },
  { id: 'approved', label: 'Approved' },
];

const filteredMilestones = computed(() => {
  if (!milestones.value) return [];
  if (activeTab.value === 'active') return milestones.value.filter(m => (m.status !== 'Completed') && !m.mark_completed_at);
  if (activeTab.value === 'completed') return milestones.value.filter(m => m.mark_completed_at && !m.approved_at);
  if (activeTab.value === 'approved') return milestones.value.filter(m => m.approved_at);
  return milestones.value;
});

const loadProjects = async () => {
  try {
    const { data } = await window.axios.get('/api/projects-simplified');
    projects.value = (data || []).map(p => ({ value: p.id, label: p.name }));
  } catch (e) {
    console.error(e);
    error('Failed to load projects');
  }
};

const loadMilestones = async () => {
  if (!selectedProjectId.value) return;
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/projects/${selectedProjectId.value}/milestones-with-expendables`);
    // Add collapsed flag for UI control
    milestones.value = (data || []).map(m => ({ ...m, _collapsed: true }));
  } catch (e) {
    console.error(e);
    error('Failed to load milestones');
  } finally {
    loading.value = false;
  }
};

const toggle = (m) => { m._collapsed = !m._collapsed; };

const promptReview = async () => {
  const text = prompt('Please provide a review explaining why this milestone is complete (required):');
  if (!text || !text.trim()) {
    alert('Review is required.');
    return null;
  }
  return text.trim();
};

const markComplete = async (m) => {
  if (!confirm('Mark this milestone as complete?')) return;
  const review = await promptReview();
  if (!review) return;
  try {
    await window.axios.post(`/api/milestones/${m.id}/complete`, { review });
    success('Milestone marked complete');
    await loadMilestones();
  } catch (e) {
    error(e.response?.data?.message || 'Failed to complete milestone');
    console.error(e);
  }
};

const approve = async (m) => {
  if (!confirm('Approve this completed milestone?')) return;
  try {
    await window.axios.post(`/api/milestones/${m.id}/approve`);
    success('Milestone approved');
    await loadMilestones();
  } catch (e) {
    error('Failed to approve milestone');
    console.error(e);
  }
};

const reopen = async (m) => {
  if (!confirm('Reopen this milestone to move it back to active?')) return;
  try {
    await window.axios.post(`/api/milestones/${m.id}/reopen`);
    success('Milestone reopened');
    await loadMilestones();
  } catch (e) {
    error('Failed to reopen milestone');
    console.error(e);
  }
};

onMounted(async () => {
  await loadProjects();
});
</script>

<template>
  <Head title="Project Expendables" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Project Expendables</h2>
        <div class="w-64">
          <SelectDropdown
            id="project-select"
            v-model="selectedProjectId"
            :options="projects"
            value-key="value"
            label-key="label"
            placeholder="Select a project"
            @update:modelValue="loadMilestones"
          />
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white">
            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-4">
              <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button
                  v-for="tab in tabs"
                  :key="tab.id"
                  @click="activeTab = tab.id"
                  :class="[
                    activeTab === tab.id
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                  ]"
                >
                  {{ tab.label }}
                </button>
              </nav>
            </div>

            <div v-if="!selectedProjectId" class="text-gray-500">Select a project to view milestones.</div>
            <div v-else>
              <div v-if="loading" class="text-gray-500">Loading...</div>
              <div v-else-if="!filteredMilestones.length" class="text-gray-500">No milestones found.</div>
              <div v-else class="space-y-3">
                <div v-for="m in filteredMilestones" :key="m.id" class="border rounded-lg">
                  <div class="p-4 flex items-center justify-between cursor-pointer" @click="toggle(m)">
                    <div>
                      <div class="font-medium">{{ m.name }}</div>
                      <div class="text-sm text-gray-600" v-if="m.description">{{ m.description }}</div>
                      <div class="text-sm mt-1">
                        <span class="font-semibold">Total:</span>
                        <span v-if="activeTab !== 'completed' || canManageFinancial">
                          {{ m.expendables_total ?? 0 }}
                        </span>
                        <span v-else class="text-gray-500">Hidden</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-xs px-2 py-1 rounded-full" :class="m.mark_completed_at ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'">
                        {{ m.approved_at ? 'Approved' : (m.mark_completed_at ? 'Completed' : (m.status || 'Active')) }}
                      </span>
                      <svg :class="['h-5 w-5 text-gray-500 transition-transform', m._collapsed ? '' : 'rotate-180']" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                    </div>
                  </div>

                  <div v-show="!m._collapsed" class="border-t p-4 bg-gray-50">
                    <div class="text-sm font-medium mb-2">Expendables</div>
                    <div v-if="!m.expendable || !m.expendable.length" class="text-gray-500 text-sm">No expendables.</div>
                    <ul class="space-y-1">
                      <li v-for="e in (m.expendable || [])" :key="e.id" class="flex justify-between text-sm">
                        <span>{{ e.name }}</span>
                        <span v-if="activeTab !== 'completed' || canManageFinancial">{{ e.amount }} {{ e.currency }}</span>
                        <span v-else class="text-gray-400">Hidden</span>
                      </li>
                    </ul>

                    <div class="mt-4 flex gap-2">
                      <PrimaryButton v-if="!m.mark_completed_at" @click.stop="markComplete(m)">Mark Complete</PrimaryButton>
                      <PrimaryButton v-else-if="!m.approved_at" @click.stop="approve(m)" class="bg-green-600 hover:bg-green-700">Approve</PrimaryButton>
                      <SecondaryButton v-else @click.stop="reopen(m)">Reopen</SecondaryButton>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
