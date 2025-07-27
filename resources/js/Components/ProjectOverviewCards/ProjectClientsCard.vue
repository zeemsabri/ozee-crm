<script setup>
import { ref, onMounted, watch, computed } from 'vue';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    canViewClientContacts: {
        type: Boolean,
        default: false,
    },
});

const clients = ref([]);
const loading = ref(true);
const error = ref(null);

const fetchClients = async () => {
    loading.value = true;
    error.value = null;
    if (!props.canViewClientContacts) {
        error.value = "You don't have permission to view project clients.";
        loading.value = false;
        return;
    }

    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/clients?type=clients`);
        clients.value = response.data;
    } catch (e) {
        console.error('Failed to fetch project clients:', e);
        error.value = e.response?.data?.message || 'Failed to load client data.';
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchClients();
});

// Watch for changes in permission prop to re-fetch if permissions are granted dynamically
watch(() => props.canViewClientContacts, () => {
    fetchClients();
});
</script>

<template>
    <div class="bg-white p-6 rounded-xl shadow-md transition-shadow hover:shadow-lg">
        <h4 class="text-lg font-semibold text-gray-900 mb-4">Project Clients</h4>

        <div v-if="loading">
            <div class="space-y-3">
                <div class="h-4 bg-gray-200 rounded animate-pulse w-3/4"></div>
                <div class="h-4 bg-gray-200 rounded animate-pulse w-2/3"></div>
                <div class="h-4 bg-gray-200 rounded animate-pulse w-1/2"></div>
            </div>
        </div>

        <div v-else-if="error" class="text-red-600 text-sm py-2">
            <p>{{ error }}</p>
        </div>

        <ul v-else-if="clients.length" class="space-y-1 text-sm text-gray-700">
            <li v-for="client in clients" :key="client.id">
                {{ client.name }} <span v-if="client.email">({{ client.email }})</span>
            </li>
        </ul>
        <p v-else class="text-gray-400 text-sm">No clients assigned.</p>
    </div>
</template>
