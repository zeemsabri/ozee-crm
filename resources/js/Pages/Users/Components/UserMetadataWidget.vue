<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    userId: { type: Number, required: true },
    canManageKeys: { type: Boolean, default: false }
});

const loading = ref(false);
const metadata = ref({});
const keys = ref([]);
const editing = ref(false);
const editData = ref({});

const showAddKey = ref(false);
const newKey = ref({ key: '', label: '', type: 'text' });

const fetchMetadata = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/users/${props.userId}/metadata`);
        metadata.value = data.metadata || {};
        keys.value = data.keys || [];
        // Sync editData with current metadata
        keys.value.forEach(k => {
            editData.value[k.key] = metadata.value[k.key] || '';
        });
    } catch (e) {
        console.error('Failed to fetch metadata', e);
    } finally {
        loading.value = false;
    }
};

const saveMetadata = async () => {
    loading.value = true;
    try {
        await axios.post(`/api/users/${props.userId}/metadata`, { metadata: editData.value });
        metadata.value = { ...editData.value };
        editing.value = false;
    } catch (e) {
        console.error('Failed to save metadata', e);
        alert('Failed to save metadata');
    } finally {
        loading.value = false;
    }
};

const addKey = async () => {
    if (!newKey.value.key || !newKey.value.label) return;
    loading.value = true;
    try {
        const { data } = await axios.post('/api/user-metadata-keys', newKey.value);
        keys.value.push(data);
        editData.value[data.key] = '';
        showAddKey.value = false;
        newKey.value = { key: '', label: '', type: 'text' };
    } catch (e) {
        console.error('Failed to add key', e);
        alert(e.response?.data?.message || 'Failed to add key');
    } finally {
        loading.value = false;
    }
};

const cancelEdit = () => {
    editing.value = false;
    // Reset editData
    keys.value.forEach(k => {
        editData.value[k.key] = metadata.value[k.key] || '';
    });
};

onMounted(fetchMetadata);
</script>

<template>
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">User Information</h3>
            <button 
                v-if="!editing" 
                @click="editing = true"
                class="text-indigo-600 hover:text-indigo-800 text-xs font-medium"
            >
                Edit
            </button>
        </div>

        <div class="p-4">
            <div v-if="loading && !editing" class="animate-pulse space-y-3">
                <div v-for="i in 3" :key="i" class="h-4 bg-gray-100 rounded w-full"></div>
            </div>

            <div v-else-if="editing" class="space-y-4">
                <div v-for="k in keys" :key="k.key" class="space-y-1">
                    <InputLabel :for="k.key" :value="k.label" />
                    <input 
                        v-if="k.type === 'date'"
                        :id="k.key"
                        type="date"
                        v-model="editData[k.key]"
                        class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                    />
                    <TextInput 
                        v-else
                        :id="k.key"
                        v-model="editData[k.key]"
                        class="w-full"
                        :type="k.type"
                    />
                </div>

                <div class="flex gap-2 pt-2">
                    <PrimaryButton @click="saveMetadata" :disabled="loading">Save</PrimaryButton>
                    <SecondaryButton @click="cancelEdit">Cancel</SecondaryButton>
                </div>

            </div>

            <div v-else class="space-y-3">
                <div v-if="keys.length === 0" class="text-gray-400 text-xs text-center py-2 italic">
                    No additional information available.
                </div>
                <div v-for="k in keys" :key="k.key" class="flex flex-col">
                    <span class="text-[10px] uppercase font-bold text-gray-400 leading-none mb-1">{{ k.label }}</span>
                    <span class="text-sm text-gray-700 font-medium">
                        {{ metadata[k.key] || '—' }}
                    </span>
                </div>
            </div>

            <div v-if="canManageKeys" class="mt-6 pt-4 border-t border-gray-100">
                <button 
                    v-if="!showAddKey" 
                    @click="showAddKey = true"
                    class="text-xs text-gray-500 hover:text-indigo-600 flex items-center gap-1"
                >
                    <span class="text-lg">+</span> Add custom field
                </button>
                
                <div v-else class="bg-gray-50 p-3 rounded space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <InputLabel value="Key (slug)" class="text-xs" />
                            <TextInput v-model="newKey.key" placeholder="e.g. twitter_handle" class="w-full text-xs" />
                        </div>
                        <div>
                            <InputLabel value="Label" class="text-xs" />
                            <TextInput v-model="newKey.label" placeholder="e.g. Twitter" class="w-full text-xs" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Type" class="text-xs" />
                        <select v-model="newKey.type" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-xs">
                            <option value="text">Text</option>
                            <option value="date">Date</option>
                            <option value="number">Number</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button @click="addKey" :disabled="loading" class="text-xs bg-indigo-600 text-white px-2 py-1 rounded">Add Field</button>
                        <button @click="showAddKey = false" class="text-xs text-gray-600">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
