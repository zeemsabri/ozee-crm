<template>
    <modal @close="$emit('close')" aria-label="Create presentation modal">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-lg">
            <h2 class="text-xl font-bold mb-1">Create Presentation</h2>
            <p v-if="errorMessage" class="text-sm text-red-600 mb-3" role="alert">{{ errorMessage }}</p>
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1">Title</label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                        required
                        autofocus
                        aria-label="Presentation title"
                    />
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1">Type</label>
                    <select
                        v-model="form.type"
                        class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                        aria-label="Presentation type"
                    >
                        <option value="audit_report">Audit Report</option>
                        <option value="proposal">Proposal</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-600 mb-1">Presentable</label>
                    <async-search-dropdown
                        v-model="form.presentable_id"
                        :type="form.presentable_type"
                        :api="searchApi"
                        class="border border-gray-200 rounded-lg"
                        aria-label="Select presentable"
                    />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="$emit('close')" class="btn" aria-label="Cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="submitting" aria-label="Save presentation">
                        {{ submitting ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    </modal>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { error as showError, success as showSuccess } from '@/Utils/notification';
import api from '@/Services/presentationsApi';
import Modal from './Components/Modal.vue';
import AsyncSearchDropdown from './Components/AsyncSearchDropdown.vue';

const emit = defineEmits(['close', 'created']);
const form = reactive({
    title: '',
    type: 'audit_report',
    presentable_type: 'App\\Models\\Client',
    presentable_id: null,
});
const submitting = ref(false);
const errorMessage = ref('');

async function submit() {
    submitting.value = true;
    errorMessage.value = '';
    const payload = { ...form };
    if (payload.presentable_type === 'App\\\\Models\\\\Client') payload.presentable_type = 'App\\Models\\Client';
    if (payload.presentable_type === 'App\\\\Models\\\\Lead') payload.presentable_type = 'App\\Models\\Lead';
    try {
        const created = await api.create(payload);
        showSuccess('Presentation created');
        emit('created', created);
    } catch (e) {
        const msg = e?.response?.data?.message || 'Failed to create presentation';
        errorMessage.value = msg;
        showError(msg);
    } finally {
        submitting.value = false;
    }
}

async function searchApi(query, type) {
    // Placeholder for API call to search clients/leads
    return [];
}
</script>

<style scoped>
.btn {
    @apply px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors;
}
.btn-primary {
    @apply bg-indigo-600 text-white hover:bg-indigo-700;
}
</style>
