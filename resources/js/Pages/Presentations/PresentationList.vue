<template>
  <AuthenticatedLayout>
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Presentations</h1>
        <button @click="showCreate = true" class="btn btn-primary" aria-label="Create new presentation">+ New</button>
      </div>

      <div class="mb-4">
        <input
          v-model="search"
          placeholder="Search by title or client/lead..."
          class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
          aria-label="Search presentations"
        />
      </div>

      <div class="relative overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Presentable</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
              <th class="px-6 py-3"></th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="p in filtered" :key="p.id">
              <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">{{ p.title }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">{{ p.presentable_name || p.presentable?.name || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">{{ p.type }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">{{ formatDate(p.created_at) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex gap-2 justify-end">
                <button @click="goEdit(p.id)" class="btn btn-xs">Edit</button>
                <button @click="copyShare(p)" class="btn btn-xs">Share</button>
                <button @click="destroy(p.id)" class="btn btn-xs text-red-500">Delete</button>
              </td>
            </tr>
            <tr v-if="!loading && !filtered.length">
              <td colspan="5" class="px-6 py-8 text-center text-gray-500">No presentations found</td>
            </tr>
          </tbody>
        </table>
        <div v-if="loading" class="absolute inset-0 bg-white/70 backdrop-blur-sm flex items-center justify-center rounded-lg">
          <div class="flex items-center gap-3 text-gray-600">
            <span class="inline-block h-5 w-5 border-2 border-gray-300 border-t-indigo-600 rounded-full animate-spin" aria-hidden="true"></span>
            <span>Loading...</span>
          </div>
        </div>
      </div>

      <PresentationForm v-if="showCreate" @close="showCreate = false" @created="onCreated" />
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { success, error, confirmPrompt } from '@/Utils/notification';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PresentationForm from './PresentationForm.vue';
import api from '@/Services/presentationsApi';

const presentations = ref([]);
const loading = ref(false);
const showCreate = ref(false);
const search = ref('');

onMounted(load);

async function load() {
  loading.value = true;
  try {
    const res = await api.list();
    // Normalize to array in case API returns a wrapped object
    presentations.value = Array.isArray(res) ? res : (res?.data ?? []);
  } catch (e) {
    error('Failed to load presentations');
  } finally {
    loading.value = false;
  }
}

const filtered = computed(() => {
  const q = search.value.toLowerCase();
  const list = Array.isArray(presentations.value) ? presentations.value : [];
  return list.filter(p =>
    (p.title || '').toLowerCase().includes(q) ||
    (p.presentable_name || p.presentable?.name || '').toLowerCase().includes(q)
  );
});

function formatDate(d) {
  if (!d) return '-';
  try { return new Date(d).toLocaleDateString(); } catch { return d; }
}

function goEdit(id) {
  // SPA navigation with Inertia
  router.visit(`/presentations/${id}/edit`);
}

async function copyShare(p) {
  const url = `${window.location.origin}/view/${p.share_token || ''}`;
  await navigator.clipboard.writeText(url);
  success('Share link copied to clipboard');
}

async function destroy(id) {
  const ok = await confirmPrompt('Delete this presentation?', { confirmText: 'Delete', cancelText: 'Cancel', type: 'warning' });
  if (!ok) return;
  try {
    await api.destroy(id);
    presentations.value = presentations.value.filter(x => x.id !== id);
    success('Presentation deleted');
  } catch (e) {
    error('Failed to delete presentation');
  }
}

function onCreated(newP) {
  showCreate.value = false;
  if (newP) presentations.value.unshift(newP);
}
</script>

<style scoped>
.btn { @apply px-3 py-1 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors; }
.btn-primary { @apply bg-indigo-600 text-white hover:bg-indigo-700; }
.btn-xs { @apply text-xs; }
</style>
