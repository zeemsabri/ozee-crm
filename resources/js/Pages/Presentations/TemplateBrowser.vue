<template>
    <modal :show="true" @close="$emit('close')">
        <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-xl w-full max-w-4xl transform transition-all flex flex-col">
            <!-- Modal Header -->
            <div class="flex-shrink-0 flex items-center justify-between pb-4 border-b border-slate-200">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Choose a Template</h2>
                    <p class="text-sm text-slate-500">Select a pre-built presentation to get started.</p>
                </div>
                <button @click="$emit('close')" class="icon-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                </button>
            </div>

            <!-- Search Input -->
            <div class="flex-shrink-0 pt-4">
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" /></svg>
                    <input v-model="q" placeholder="Search templates..." class="w-full border border-slate-300 rounded-lg py-2 pl-10 pr-4 focus:ring-2 focus:ring-oz-blue focus:border-oz-blue transition-shadow" />
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-grow overflow-y-auto mt-6 -mx-6 px-6" style="max-height: 60vh;">
                <div v-if="loading" class="text-center py-10 text-slate-500">Loading templates...</div>
                <div v-else-if="!filtered.length" class="text-center py-10 text-slate-500">No templates found.</div>
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="tpl in filtered" :key="tpl.id" class="template-card" @click="select(tpl)">
                        <div class="aspect-video bg-slate-100 rounded-lg flex items-center justify-center mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-oz-blue/50"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                        </div>
                        <div class="font-semibold text-slate-800 truncate">{{ tpl.title }}</div>
                        <div class="text-sm text-slate-500">{{ tpl.slide_count }} slides</div>
                    </div>
                </div>
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

</style>
