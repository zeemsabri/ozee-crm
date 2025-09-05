<template>
    <modal :show="true" @close="$emit('close')">
        <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-xl w-full transform transition-all">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">How would you like to start?</h2>
                <button @click="$emit('close')" class="h-8 w-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                </button>
            </div>

            <div v-if="mode==='choice'" class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div @click="$emit('scratch')" class="p-6 bg-slate-50 rounded-xl border border-slate-200 text-center cursor-pointer transition group hover:-translate-y-1 hover:shadow-lg hover:border-oz-blue">
                    <div class="h-16 w-16 rounded-full flex items-center justify-center mx-auto mb-4 transition-transform bg-blue-100 text-blue-700 group-hover:scale-110">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.165 12.165h5.625m-5.625 0a4.5 4.5 0 11-8.325-2.024 4.5 4.5 0 018.325 2.024zM8.25 4.5h7.5m0 0l-3.75 3.75M12 4.5l3.75 3.75" /></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Start from Scratch</h3>
                    <p class="text-sm text-slate-500 mt-1">Begin with a blank presentation and build it your way.</p>
                </div>

                <div @click="$emit('choose-template')" class="p-6 bg-slate-50 rounded-xl border border-slate-200 text-center cursor-pointer transition group hover:-translate-y-1 hover:shadow-lg hover:border-oz-blue">
                    <div class="h-16 w-16 rounded-full flex items-center justify-center mx-auto mb-4 transition-transform bg-amber-100 text-amber-700 group-hover:scale-110">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Use a Template</h3>
                    <p class="text-sm text-slate-500 mt-1">Choose a professionally designed template to get started faster.</p>
                </div>

                <div @click="mode='surprise'" class="p-6 bg-slate-50 rounded-xl border border-slate-200 text-center cursor-pointer transition group hover:-translate-y-1 hover:shadow-lg hover:border-oz-blue">
                    <div class="h-16 w-16 rounded-full flex items-center justify-center mx-auto mb-4 transition-transform bg-purple-100 text-purple-700 group-hover:scale-110">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-8 h-8"><path d="M12 2a5 5 0 00-5 5v1.1A5.002 5.002 0 002 13a5 5 0 005 5h.1A5.002 5.002 0 0013 22a5 5 0 005-5v-.1A5.002 5.002 0 0022 11a5 5 0 00-5-5h-.1A5.002 5.002 0 0012 2z"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Surprise Me</h3>
                    <p class="text-sm text-slate-500 mt-1">Auto-generate a presentation by title and slide count.</p>
                </div>
            </div>

            <div v-else class="mt-6 space-y-4">
                <div class="flex items-center gap-2 text-slate-700">
                    <button @click="mode='choice'" class="text-sm text-slate-500 hover:text-slate-700">← Back</button>
                    <h3 class="text-base font-bold">Surprise Me</h3>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input v-model="form.title" class="w-full border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-indigo-500" placeholder="e.g., Website Redesign Proposal" />
                    <p v-if="errors.title" class="text-sm text-red-600 mt-1">{{ errors.title }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Number of slides</label>
                    <input type="number" min="3" max="20" v-model.number="form.slide_count" class="w-32 border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-indigo-500" />
                    <p class="text-xs text-slate-500 mt-1">Between 3 and 20.</p>
                    <p v-if="errors.slide_count" class="text-sm text-red-600 mt-1">{{ errors.slide_count }}</p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button @click="$emit('close')" type="button" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">Cancel</button>
                    <button @click="submit" :disabled="submitting" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-75">
                        {{ submitting ? 'Generating...' : 'Generate' }}
                    </button>
                </div>
            </div>
        </div>
    </modal>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { success, error as showError } from '@/Utils/notification';
import Modal from './Components/Modal.vue';

const emit = defineEmits(['close', 'scratch', 'choose-template', 'created']);

const mode = ref('choice');
const form = reactive({ title: '', slide_count: 8 });
const submitting = ref(false);
const errors = reactive({});

async function submit() {
    errors.title = '';
    errors.slide_count = '';
    if (!form.title?.trim()) {
        errors.title = 'Title is required';
        return;
    }
    if (!Number.isFinite(form.slide_count) || form.slide_count < 3 || form.slide_count > 20) {
        errors.slide_count = 'Slide count must be between 3 and 20';
        return;
    }
    submitting.value = true;
    try {
        const res = await window.axios.post('/api/presentations/generate', { title: form.title.trim(), slide_count: form.slide_count });
        success('Presentation generated');
        emit('created', res?.data);
        emit('close');
    } catch (e) {
        const msg = e?.response?.data?.message || 'Failed to generate presentation';
        showError(msg);
    } finally {
        submitting.value = false;
    }
}
</script>

<style scoped>
</style>

