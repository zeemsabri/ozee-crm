<template>
    <modal :show="true" @close="$emit('close')">
        <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-xl w-full max-w-5xl transform transition-all flex flex-col">
            <!-- Modal Header -->
            <div class="flex-shrink-0 flex items-center justify-between pb-4 border-b border-slate-200">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Manage Slides</h2>
                    <p class="text-sm text-slate-500">Duplicate the presentation, or select slides to create a new one or copy.</p>
                </div>
                <button @click="$emit('close')" class="icon-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                </button>
            </div>

            <!-- Slides Grid -->
            <div class="flex-grow overflow-y-auto mt-6 -mx-6 px-6" style="max-height: 55vh;">
                <div v-if="loading" class="text-center py-10 text-slate-500">Loading slides...</div>
                <div v-else-if="!slides.length" class="text-center py-10 text-slate-500">This presentation has no slides.</div>
                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <label v-for="s in slides" :key="s.id" class="slide-card" :class="{ 'selected': selectedIds.includes(s.id) }">
                        <div class="aspect-video bg-slate-100 rounded-md flex items-center justify-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-1.621-.87a3 3 0 01-.879-2.122v-1.007" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" /></svg>
                        </div>
                        <div class="flex-grow">
                            <div class="font-semibold text-sm text-slate-700 truncate">{{ s.title || s.template_name }}</div>
                            <div class="text-xs text-slate-400">Blocks: {{ (s.content_blocks || []).length }}</div>
                        </div>
                        <input type="checkbox" v-model="selectedIds" :value="s.id" class="slide-checkbox" />
                    </label>
                </div>
            </div>

            <!-- Action Footer -->
            <div class="flex-shrink-0 pt-6 mt-6 border-t border-slate-200 flex flex-col md:flex-row gap-4 justify-between">
                <div class="flex flex-col sm:flex-row gap-3">
                    <button class="btn-primary-outline" @click="fullDuplicate" :disabled="!canDuplicate">Create Full Duplicate</button>
                    <button class="btn-primary" @click="createNewFromSelection" :disabled="!selectedIds.length">New from Selection ({{ selectedIds.length }})</button>
                </div>
                <div class="flex items-center gap-2">
                    <select v-model="targetId" class="select-input">
                        <option disabled value="">Copy {{ selectedIds.length }} slides to...</option>
                        <option v-for="p in otherPresentations" :key="p.id" :value="p.id">{{ p.title }}</option>
                    </select>
                    <button class="btn-secondary" @click="copyTo" :disabled="!selectedIds.length || !targetId">Copy</button>
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
        success(`${selectedIds.value.length} slides copied successfully`);
        emit('copied');
    } catch (e) {
        error('Failed to copy slides');
    }
}
</script>

<style scoped>
.icon-btn { height: 2rem; width: 2rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; color: #94a3b8; transition: background-color 0.2s, color 0.2s; }
.icon-btn:hover { background-color: #f1f5f9; color: #475569; }
.slide-card { position: relative; background-color: white; border-radius: 0.5rem; padding: 0.5rem; border: 2px solid #e2e8f0; cursor: pointer; transition: border-color 0.2s, background-color 0.2s; display: flex; flex-direction: column; }
.slide-card.selected { border-color: #29438E; background-color: rgba(41, 67, 142, 0.05); }
.btn-primary { padding: 0.625rem 1rem; background-color: #29438E; color: white; border-radius: 0.5rem; font-weight: 600; font-size: 0.875rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); transition: all 0.2s; }
.btn-primary:hover { opacity: 0.9; }
.btn-primary:focus { outline: 2px solid transparent; outline-offset: 2px; box-shadow: 0 0 0 2px white, 0 0 0 4px #29438E; }
.btn-primary:disabled { background-color: #d1d5db; cursor: not-allowed; }
.btn-primary-outline { padding: 0.625rem 1rem; background-color: white; color: #29438E; border: 1px solid #29438E; border-radius: 0.5rem; font-weight: 600; font-size: 0.875rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); transition: all 0.2s; }
.btn-primary-outline:hover { background-color: rgba(41, 67, 142, 0.05); }
.btn-primary-outline:focus { outline: 2px solid transparent; outline-offset: 2px; box-shadow: 0 0 0 2px white, 0 0 0 4px #29438E; }
.btn-primary-outline:disabled { border-color: #d1d5db; color: #9ca3af; background-color: white; cursor: not-allowed; }
.btn-secondary { padding: 0.5rem 0.75rem; background-color: #f1f5f9; color: #334155; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; transition: background-color 0.2s; }
.btn-secondary:hover { background-color: #e2e8f0; }
.btn-secondary:disabled { background-color: #f1f5f9; color: #9ca3af; cursor: not-allowed; }
.select-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.5rem 2rem 0.5rem 0.75rem; font-size: 0.875rem; transition: box-shadow 0.2s, border-color 0.2s; }
@media (min-width: 768px) { .select-input { width: auto; } }
.select-input:focus { outline: none; border-color: #29438E; box-shadow: 0 0 0 2px #29438E; }
.slide-checkbox { position: absolute; top: 0.5rem; right: 0.5rem; height: 1rem; width: 1rem; border-radius: 0.25rem; color: #29438E; }
.slide-checkbox:focus { --tw-ring-color: #29438E; }
</style>

