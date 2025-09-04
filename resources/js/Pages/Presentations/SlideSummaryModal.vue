<template>
  <modal @close="$emit('close')">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-4xl">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold">Slides</h2>
        <button class="btn" @click="$emit('close')">Close</button>
      </div>

      <div v-if="loading" class="text-gray-500">Loading...</div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[60vh] overflow-auto">
        <label v-for="s in slides" :key="s.id" class="border rounded-lg p-3 flex gap-2 items-start cursor-pointer">
          <input type="checkbox" v-model="selectedIds" :value="s.id" />
          <div>
            <div class="font-semibold">{{ s.title || s.template_name }}</div>
            <div class="text-xs text-gray-500">Blocks: {{ (s.content_blocks || []).length }}</div>
          </div>
        </label>
        <div v-if="!slides.length" class="text-gray-500">No slides</div>
      </div>

      <div class="mt-4 flex flex-col gap-3">
        <div class="flex gap-3">
          <button class="btn btn-primary" @click="fullDuplicate" :disabled="!canDuplicate">Create a Full Duplicate</button>
          <button class="btn" @click="createNewFromSelection" :disabled="!selectedIds.length">Create New from Selection...</button>
        </div>
        <div class="flex items-center gap-2">
          <select v-model="targetId" class="border rounded p-2 min-w-[200px]">
            <option disabled value="">Copy to... (select destination)</option>
            <option v-for="p in otherPresentations" :key="p.id" :value="p.id">{{ p.title }}</option>
          </select>
          <button class="btn" @click="copyTo" :disabled="!selectedIds.length || !targetId">Copy to...</button>
        </div>
      </div>
    </div>
  </modal>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import Modal from './Components/Modal.vue';
import api from '@/Services/presentationsApi';
import { success, error } from '@/Utils/notification';

const props = defineProps({
  presentationId: { type: Number, required: true }
});
const emit = defineEmits(['close', 'created', 'copied']);

const slides = ref([]);
const loading = ref(false);
const selectedIds = ref([]);
const targetId = ref('');
const otherPresentations = ref([]);

const canDuplicate = true;

onMounted(async () => {
  loading.value = true;
  try {
    const p = await api.get(props.presentationId);
    slides.value = p.slides || [];
    // Load destinations list (basic)
    const listRes = await api.list();
    const arr = Array.isArray(listRes) ? listRes : (listRes?.data ?? []);
    otherPresentations.value = arr.filter(x => x.id !== props.presentationId);
  } catch (e) {
    error('Failed to load slides');
  } finally {
    loading.value = false;
  }
});

async function fullDuplicate() {
  try {
    const created = await api.duplicate(props.presentationId);
    success('Presentation duplicated');
    emit('created', created);
  } catch (e) {
    error('Failed to duplicate');
  }
}

function createNewFromSelection() {
  emit('created', { _fromSelection: true, source_slide_ids: [...selectedIds.value] });
}

async function copyTo() {
  try {
    await api.copySlides(targetId.value, selectedIds.value);
    success('Slides copied');
    emit('copied');
  } catch (e) {
    error('Failed to copy slides');
  }
}
</script>

<style scoped>
.btn { @apply px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors; }
.btn-primary { @apply bg-indigo-600 text-white hover:bg-indigo-700; }
</style>
