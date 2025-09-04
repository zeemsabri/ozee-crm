<template>
    <modal :show="true" @close="$emit('close')" aria-label="Create presentation modal">
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
                    <label class="block text-sm font-medium text-gray-600 mb-1">Link to Client or Lead</label>
                    <SelectDropdown
                        v-model="selectedPresentable"
                        :options="presentableOptions"
                        :placeholder="'Search clients and leads...'"
                        aria-label="Search and select client or lead"
                    />
                    <p class="text-xs text-gray-500 mt-1">Start typing to search by client/lead name, company, or email.</p>

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
import { reactive, ref, toRefs, watch } from 'vue';
import { error as showError, success as showSuccess } from '@/Utils/notification';
import api from '@/Services/presentationsApi';
import Modal from './Components/Modal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const emit = defineEmits(['close', 'created']);
const props = defineProps({
    templateId: { type: Number, default: null },
    sourceSlideIds: { type: Array, default: () => [] },
});
const { templateId, sourceSlideIds } = toRefs(props);

const form = reactive({
    title: '',
    type: 'audit_report',
    presentable_type: 'App\\Models\\Client',
    presentable_id: null,
});
const submitting = ref(false);
const errorMessage = ref('');

// Presentable unified search using SelectDropdown
const presentableOptions = ref([]);
const selectedPresentable = ref(null); // value will be like 'client:123' or 'lead:45'
const presentableQuery = ref('');
const presentableScope = ref('all'); // all | client | lead

watch(selectedPresentable, (val) => {
    if (!val) {
        form.presentable_id = null;
        return;
    }
    const [type, idStr] = String(val).split(':');
    form.presentable_id = Number(idStr);
    form.presentable_type = type === 'lead' ? 'App\\Models\\Lead' : 'App\\Models\\Client';
});

const debouncedFetch = debounce(fetchPresentables, 250);
watch(presentableScope, () => { fetchPresentables(); });

async function fetchPresentables() {
    const q = presentableQuery.value.toLowerCase();
    const types = presentableScope.value === 'all' ? ['client','lead'] : [presentableScope.value];
    let combined = [];
    for (const key of types) {
        try {
            const res = await window.axios.get(`/api/source-models/${key}`);
            const items = Array.isArray(res?.data) ? res.data : [];
            const mapped = items.map(it => {
                const name = it.name || [it.first_name, it.last_name].filter(Boolean).join(' ') || it.company || it.email || `#${it.id}`;
                const hay = [name, it.company, it.email].filter(Boolean).join(' ').toLowerCase();
                return { value: `${key}:${it.id}`, label: `[${key === 'client' ? 'Client' : 'Lead'}] ${name}`, _hay: hay };
            });
            combined = combined.concat(mapped);
        } catch (e) {
            // fail soft per type
        }
    }
    if (q) combined = combined.filter(o => o._hay.includes(q));
    combined = combined.slice(0, 50);
    // Ensure current selection is present in options so the label shows
    if (selectedPresentable.value && !combined.find(o => o.value === selectedPresentable.value)) {
        const [type, id] = String(selectedPresentable.value).split(':');
        combined.unshift({ value: selectedPresentable.value, label: `[${type === 'client' ? 'Client' : 'Lead'}] #${id}` });
    }
    presentableOptions.value = combined;
}

function debounce(fn, delay) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
    };
}

// Prefetch initial options (empty query)
fetchPresentables();

async function submit() {
    submitting.value = true;
    errorMessage.value = '';
    const payload = { ...form };
    if (templateId.value) payload.template_id = templateId.value;
    if (sourceSlideIds.value && sourceSlideIds.value.length) payload.source_slide_ids = sourceSlideIds.value;
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

</script>

<style scoped>
.btn {
    @apply px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors;
}
.btn-primary {
    @apply bg-indigo-600 text-white hover:bg-indigo-700;
}
</style>
