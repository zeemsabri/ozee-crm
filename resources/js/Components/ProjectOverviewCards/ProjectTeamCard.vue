<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useAuthUser, usePermissions } from '@/Directives/permissions';
import { UsersIcon, CheckCircleIcon, XCircleIcon, EnvelopeIcon } from '@heroicons/vue/20/solid'; // Added EnvelopeIcon for a cleaner look

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    canViewUsers: {
        type: Boolean,
        default: false,
    },
});

const authUser = useAuthUser();
const teamUsers = ref([]);
const loading = ref(true);
const error = ref(null);

const fetchTeamData = async () => {
    loading.value = true;
    error.value = null;
    if (!props.canViewUsers) {
        error.value = "You don't have permission to view project team members.";
        loading.value = false;
        teamUsers.value = [];
        return;
    }

    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/users`);
        teamUsers.value = response.data.users || [];
    } catch (e) {
        console.error('Failed to fetch project users:', e);
        error.value = e.response?.data?.message || 'Failed to load team data.';
    } finally {
        loading.value = false;
    }
};

const getFormattedAvailability = (user) => {
    if (!user.availabilities || user.availabilities.length === 0) {
        return 'No availability submitted.';
    }

    const todayAvailability = user.availabilities[0];

    if (!todayAvailability.is_available) {
        return `Not available: ${todayAvailability.reason || 'N/A'}`;
    }

    if (todayAvailability.time_slots && todayAvailability.time_slots.length > 0) {
        const slots = todayAvailability.time_slots.map(slot => `${slot.start_time}-${slot.end_time}`);
        return `Available: ${slots.join(', ')}`;
    } else {
        return 'Available: No specific slots.';
    }
};

const getAvailabilityIcon = (user) => {
    if (!user.availabilities || user.availabilities.length === 0) {
        return null;
    }
    return user.availabilities[0].is_available ? CheckCircleIcon : XCircleIcon;
};

onMounted(() => {
    fetchTeamData();
});

watch([() => props.canViewUsers, () => authUser.value, () => props.projectId], () => {
    fetchTeamData();
}, { deep: true });
</script>

<template>
    <div class="bg-white p-4 rounded-xl shadow-md transition-shadow hover:shadow-lg flex flex-col h-full">
        <h4 class="text-sm font-semibold text-gray-900 mb-3">Project Team</h4>

        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <div class="space-y-2 w-full">
                <div class="h-3 bg-gray-200 rounded animate-pulse w-3/4 mx-auto"></div>
                <div class="h-3 bg-gray-200 rounded animate-pulse w-2/3 mx-auto"></div>
            </div>
        </div>

        <div v-else-if="error" class="flex-1 flex items-center justify-center text-red-600 text-xs text-center">
            <p>{{ error }}</p>
        </div>

        <div v-else class="flex-1 flex flex-col overflow-hidden">
            <div class="flex items-center space-x-2 text-gray-500 mb-3">
                <UsersIcon class="h-5 w-5" />
                <span class="text-xs font-semibold">{{ teamUsers.length }} Member(s)</span>
            </div>

            <ul v-if="teamUsers.length" class="space-y-2 text-sm text-gray-700 flex-1 overflow-y-auto pr-2" style="max-height: 250px;">
                <li v-for="user in teamUsers" :key="user.id" class="p-2 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">
                    <div class="flex items-center space-x-2">
                        <div class="flex-shrink-0 bg-indigo-500 rounded-full h-8 w-8 flex items-center justify-center text-white font-medium text-xs">
                            {{ user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1">
                            <strong class="text-gray-900 text-sm">{{ user.name }}</strong>
                            <p class="text-xs text-gray-500">{{ user.pivot?.role || 'N/A' }}</p>
                        </div>
                        <div class="flex-shrink-0 flex items-center space-x-2 ml-auto">
                            <span class="text-xs text-gray-500">{{ getFormattedAvailability(user) }}</span>
                            <component v-if="getAvailabilityIcon(user)" :is="getAvailabilityIcon(user)" :class="{'h-5 w-5': true, 'text-green-500': user.availabilities && user.availabilities.length > 0 && user.availabilities[0].is_available, 'text-red-500': user.availabilities && user.availabilities.length > 0 && !user.availabilities[0].is_available}" />
                        </div>
                    </div>
                </li>
            </ul>
            <p v-else class="text-gray-400 text-xs flex-1 flex items-center justify-center">No team members assigned.</p>
        </div>
    </div>
</template>
