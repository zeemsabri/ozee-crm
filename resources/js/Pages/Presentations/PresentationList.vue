<template>
  <AuthenticatedLayout>
    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-4">
          <h1 class="text-2xl font-semibold">Presentations</h1>
          <button class="btn btn-primary" @click="openCreate">Create New Presentation</button>
        </div>

        <div class="bg-white shadow rounded">
          <table class="min-w-full">
            <thead>
              <tr class="text-left border-b">
                <th class="p-3">Title</th>
                <th class="p-3">Type</th>
                <th class="p-3">Presentable</th>
                <th class="p-3">Created</th>
                <th class="p-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in items" :key="p.id" class="border-b">
                <td class="p-3">{{ p.title }}</td>
                <td class="p-3">{{ p.type }}</td>
                <td class="p-3">{{ p.presentable_type?.split('\\\\').pop() }} #{{ p.presentable_id }}</td>
                <td class="p-3">{{ new Date(p.created_at).toLocaleString() }}</td>
                <td class="p-3 space-x-2">
                  <button class="btn btn-sm" @click="goEdit(p.id)">Edit</button>
                  <button class="btn btn-sm" @click="copyShare(p)">Share</button>
                  <button class="btn btn-sm text-red-600" @click="destroy(p.id)">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <PresentationForm v-if="showForm" @close="showForm=false" @created="onCreated" />
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import api from '@/Services/presentationsApi';
import PresentationForm from './PresentationForm.vue';

const items = ref([]);
const showForm = ref(false);

async function load() {
  const data = await api.list();
  items.value = data.data || data; // handle pagination wrapper
}

function openCreate() { showForm.value = true; }
function onCreated(p) {
  showForm.value = false;
  load();
}
function goEdit(id) {
  router.visit(`/presentations/${id}/edit`);
}
async function destroy(id) {
  if (!confirm('Delete this presentation?')) return;
  await api.destroy(id);
  await load();
}
async function copyShare(p) {
  // We need share_token; fetch fresh in case not included
  const fresh = await api.get(p.id);
  const url = `${window.location.origin}/view/${fresh.share_token}`;
  await navigator.clipboard.writeText(url);
  alert('Link copied to clipboard');
}

onMounted(load);
</script>

<style scoped>
.btn{ @apply px-3 py-1 bg-gray-100 rounded; }
.btn-primary{ @apply bg-indigo-600 text-white; }
.btn-sm{ @apply text-sm; }
</style>
