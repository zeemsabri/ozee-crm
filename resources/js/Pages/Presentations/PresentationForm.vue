<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded shadow w-full max-w-lg">
      <h2 class="text-xl font-semibold mb-4">Create Presentation</h2>
      <form @submit.prevent="submit">
        <div class="mb-3">
          <label class="block text-sm font-medium mb-1">Title</label>
          <input v-model="form.title" type="text" class="w-full border rounded p-2" required />
        </div>
        <div class="mb-3">
          <label class="block text-sm font-medium mb-1">Type</label>
          <select v-model="form.type" class="w-full border rounded p-2">
            <option value="audit_report">Audit Report</option>
            <option value="proposal">Proposal</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1">Presentable</label>
          <div class="flex gap-2">
            <select v-model="form.presentable_type" class="border rounded p-2">
              <option :value="'App\\\\Models\\\\Client'">Client</option>
              <option :value="'App\\\\Models\\\\Lead'">Lead</option>
            </select>
            <input v-model.number="form.presentable_id" type="number" placeholder="ID" class="border rounded p-2 w-32" />
          </div>
          <p class="text-xs text-gray-500 mt-1">Note: Replace with searchable dropdown later.</p>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn" @click="$emit('close')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</template>
<script setup>
import { reactive } from 'vue';
import api from '@/Services/presentationsApi';

const emit = defineEmits(['close','created']);
const form = reactive({ title: '', type: 'audit_report', presentable_type: 'App\\Models\\Client', presentable_id: null });

async function submit() {
  const payload = { ...form };
  // fix escaping for backend
  if (payload.presentable_type === 'App\\\\Models\\\\Client') payload.presentable_type = 'App\\Models\\Client';
  if (payload.presentable_type === 'App\\\\Models\\\\Lead') payload.presentable_type = 'App\\Models\\Lead';
  const created = await api.create(payload);
  emit('created', created);
}
</script>
<style scoped>
.btn{ @apply px-3 py-1 bg-gray-100 rounded; }
.btn-primary{ @apply bg-indigo-600 text-white; }
</style>
