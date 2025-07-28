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
        teamUsers.value = response.data.users || [];
        console.log('Fetched team users:', teamUsers.value);
    } catch (e) {
        console.error('Failed to fetch project users:', e);
        error.value = e.response?.data?.message || 'Failed to load team data.';
    } finally {
        loading.value = false;
    }
};

// Helper function to format the availability slots
const getFormattedAvailability = (user) => {
    // Assuming 'availabilities' array only contains today's entry if any
    if (!user.availabilities || user.availabilities.length === 0) {
        return 'No availability submitted for today.';
    }

    const todayAvailability = user.availabilities[0]; // Take the first (and assumed only) entry

    if (!todayAvailability.is_available) {
        return `Not available today: ${todayAvailability.reason || 'No reason provided'}`;
    }

    if (todayAvailability.time_slots && todayAvailability.time_slots.length > 0) {
        const slots = todayAvailability.time_slots.map(slot => `${slot.start_time}-${slot.end_time}`);
        return `${slots.join(', ')}`;
    } else {
        return 'No specific slots provided.';
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
            <ul v-if="teamUsers.length" class="space-y-2 text-sm text-gray-700 mb-4">
                <li v-for="user in teamUsers" :key="user.id" class="border-b pb-2 last:border-b-0">
                    <div>
                        <strong>{{ user.name }}</strong> (Role: <span class="font-medium">{{ user.pivot?.role || 'N/A' }}</span>)
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        Today's Availability: {{ getFormattedAvailability(user) }}
                    </div>
                </li>
            </ul>
            <p v-else class="text-gray-400 text-sm mb-4">No team members assigned.</p>
        </div>
    </div>
</template>
