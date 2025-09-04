<template>
  <modal :show="true" @close="$emit('close')">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-4xl">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold">Choose a Template</h2>
        <input v-model="q" placeholder="Search templates..." class="border rounded p-2" />
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-[60vh] overflow-auto">
        <div v-for="tpl in filtered" :key="tpl.id" class="border rounded-lg p-4 hover:shadow cursor-pointer" @click="select(tpl)">
          <div class="font-semibold mb-1">{{ tpl.title }}</div>
          <div class="text-sm text-gray-500">{{ tpl.slide_count }} slides</div>
        </div>
        <div v-if="!loading && !filtered.length" class="text-gray-500">No templates found</div>
      </div>
      <div v-if="loading" class="text-gray-500">Loading templates...</div>
      <div class="mt-4 text-right">
        <button class="btn" @click="$emit('close')">Close</button>
      </div>
    </div>
  </modal>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import Modal from './Components/Modal.vue';
import api from '@/Services/presentationsApi';
import { error as showError } from '@/Utils/notification';

const emit = defineEmits(['close', 'selected']);
const templates = ref([]);
const loading = ref(false);
const q = ref('');

onMounted(async () => {
  loading.value = true;
  try {
    const res = await api.listTemplates();
    // API returns {data: []}
    templates.value = Array.isArray(res?.data) ? res.data : [];
  } catch (e) {
    showError('Failed to load templates');
  } finally {
    loading.value = false;
  }
});

const filtered = computed(() => {
  const term = q.value.toLowerCase();
  return templates.value.filter(t => (t.title || '').toLowerCase().includes(term));
});

function select(tpl) {
  emit('selected', tpl);
}
</script>

<style scoped>
.btn { @apply px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors; }
</style>
