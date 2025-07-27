<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useAuthUser, usePermissions } from '@/Directives/permissions';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    // The canView prop comes from Show.vue, based on the user's project role/global permissions
    canViewUsers: {
        type: Boolean,
        default: false,
    },
});

const authUser = useAuthUser(); // Get authenticated user's data

const teamUsers = ref([]);
const loading = ref(true);
const error = ref(null);

const fetchTeamData = async () => {
    loading.value = true;
    error.value = null;
    if (!props.canViewUsers) {
        error.value = "You don't have permission to view project team members.";
        loading.value = false;
        teamUsers.value = []; // Clear previous data if permissions revoked
        return;
    }

    try {
        // Fetch project users (team members)
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/users`);
        teamUsers.value = response.data.users || []; // Assuming 'users' key in response if getClientsAndUsers returns object
        console.log('Fetched team users:', teamUsers.value);
    } catch (e) {
        console.error('Failed to fetch project users:', e);
        error.value = e.response?.data?.message || 'Failed to load team data.';
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchTeamData();
});

// Watch for changes in permission prop or authUser or project ID to re-fetch if permissions are granted dynamically
watch([() => props.canViewUsers, () => authUser.value, () => props.projectId], () => {
    fetchTeamData();
}, { deep: true });
</script>

<template>
    <div class="bg-white p-6 rounded-xl shadow-md transition-shadow hover:shadow-lg">
        <h4 class="text-lg font-semibold text-gray-900 mb-4">Project Team</h4>

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

        <div v-else>
            <ul v-if="teamUsers.length" class="space-y-1 text-sm text-gray-700 mb-4">
                <li v-for="user in teamUsers" :key="user.id">
                    {{ user.name }} (Role: <span class="font-medium">{{ user.pivot?.role || 'N/A' }}</span>)
                </li>
            </ul>
            <p v-else class="text-gray-400 text-sm mb-4">No team members assigned.</p>
        </div>
    </div>
</template>
