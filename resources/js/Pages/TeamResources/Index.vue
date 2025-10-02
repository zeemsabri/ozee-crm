<script setup>
import { ref, onMounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { success, error } from '@/Utils/notification.js';

const resources = ref([]);
const isLoading = ref(false);
const fetchError = ref(null);
const search = ref('');
const page = ref(1);
const perPage = ref(10);
const total = ref(0);

// New: option metadata and projects for selection
const typeOptions = ref([]); // from /api/options/shareable_resource_types
const projects = ref([]);    // from /api/projects-simplified

const selectedResource = ref(null);
const showCopyModal = ref(false);
const selectedProjectId = ref(null);

async function fetchResources() {
  isLoading.value = true;
  fetchError.value = null;
  try {
    const { data } = await window.axios.get('/api/shareable-resources', {
      params: { visible_to_team: true, q: search.value || undefined, per_page: perPage.value, page: page.value }
    });
    resources.value = data.data || [];
    total.value = data.total || 0;
  } catch (e) {
    console.error('Failed to load team resources', e);
    fetchError.value = 'Failed to load team resources';
    error(fetchError.value);
  } finally {
    isLoading.value = false;
  }
}

async function fetchTypeOptions() {
  try {
    const { data } = await window.axios.get('/api/options/shareable_resource_types');
    typeOptions.value = Array.isArray(data) ? data : [];
  } catch (_) {
    typeOptions.value = [];
  }
}

async function fetchProjects() {
  try {
    const { data } = await window.axios.get('/api/projects-simplified');
    projects.value = Array.isArray(data) ? data : [];
  } catch (_) {
    projects.value = [];
  }
}

onMounted(() => {
  fetchTypeOptions();
  fetchProjects();
  fetchResources();
});

watch([search, page, perPage], fetchResources);

function typeAllows(resourceType, action) {
  const opt = typeOptions.value.find(o => o.value === resourceType);
  const allow = Array.isArray(opt?.allow) ? opt.allow : [];
  return allow.includes(action);
}

function openCopyModal(resource) {
  selectedResource.value = resource;
  selectedProjectId.value = null;
  showCopyModal.value = true;
}

async function confirmCopy() {
  if (!selectedResource.value || !selectedProjectId.value) {
    return error('Please select a project');
  }
  try {
    await window.axios.post(`/api/shareable-resources/${selectedResource.value.id}/copy-to-project`, {
      project_id: selectedProjectId.value,
    });
    success('Copied to project.');
    showCopyModal.value = false;
    selectedResource.value = null;
  } catch (e) {
    console.error(e);
    error(e?.response?.data?.message || 'Failed to copy.');
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Team Resources</h1>
        <div class="flex items-center space-x-2">
          <input v-model="search" placeholder="Search resources..." class="border rounded-md p-2" />
        </div>
      </div>

      <div v-if="isLoading" class="text-gray-500">Loading...</div>
      <div v-else-if="fetchError" class="text-red-600">{{ fetchError }}</div>
      <div v-else>
        <div v-if="!resources.length" class="text-gray-500">No resources found.</div>
        <div v-else class="overflow-x-auto rounded border">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tags</th>
              <th class="px-6 py-3"></th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="r in resources" :key="r.id">
              <td class="px-6 py-4">{{ r.title }}</td>
              <td class="px-6 py-4">{{ r.type }}</td>
              <td class="px-6 py-4 text-indigo-600"><a :href="r.url" target="_blank" rel="noopener">{{ r.url }}</a></td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap gap-1">
                  <span v-for="tag in (r.tags || [])" :key="tag.id" class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-800">{{ tag.name }}</span>
                  <span v-if="!r.tags || !r.tags.length" class="text-gray-400 text-sm">No tags</span>
                </div>
              </td>
              <td class="px-6 py-4 text-right">
                <button
                  v-if="typeAllows(r.type, 'copy')"
                  class="px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded"
                  @click="openCopyModal(r)"
                >Copy</button>
              </td>
            </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-between mt-4">
          <div class="text-sm text-gray-600">Total: {{ total }}</div>
          <div class="space-x-2">
            <button class="px-3 py-1 border rounded" :disabled="page<=1" @click="page = Math.max(1, page-1)">Prev</button>
            <button class="px-3 py-1 border rounded" :disabled="resources.length < perPage" @click="page = page+1">Next</button>
          </div>
        </div>
      </div>

      <!-- Copy Modal (simple) -->
      <div v-if="showCopyModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded shadow p-4 w-full max-w-md">
          <h3 class="text-lg font-semibold mb-3">Copy to Project</h3>
          <div class="mb-3">
            <label class="block text-sm text-gray-700 mb-1">Select Project</label>
            <select v-model="selectedProjectId" class="border rounded w-full p-2">
              <option :value="null">-- Select --</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div class="flex justify-end gap-2">
            <button class="px-3 py-1.5 border rounded" @click="showCopyModal=false">Cancel</button>
            <button class="px-3 py-1.5 bg-emerald-600 text-white rounded" @click="confirmCopy">Copy</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
