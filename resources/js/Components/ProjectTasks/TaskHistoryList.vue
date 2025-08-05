<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    activities: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    }
});

// Format date to a more readable format
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Get appropriate icon based on activity description
const getActivityIcon = (description) => {
    if (description.includes('created')) return '🆕';
    if (description.includes('started')) return '🚀';
    if (description.includes('completed')) return '✅';
    if (description.includes('deleted')) return '🗑️';
    if (description.includes('updated')) return '📝';
    if (description.includes('assigned')) return '👤';
    return '📋'; // Default icon
};
</script>

<template>
    <div class="task-history-list">
        <div v-if="loading" class="text-center py-4">
            <div class="inline-block animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-indigo-500"></div>
            <p class="mt-2 text-gray-500">Loading activity history...</p>
        </div>

        <div v-else-if="activities.length === 0" class="text-gray-500 text-sm py-2">
            No activity history available for this task.
        </div>

        <div v-else class="space-y-3">
            <div v-for="activity in activities" :key="activity.id" class="p-3 bg-gray-50 rounded-md">
                <div class="flex items-start">
                    <span class="text-xl mr-2">{{ getActivityIcon(activity.description) }}</span>
                    <div>
                        <p class="text-sm text-gray-700">{{ activity.description }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            By {{ activity.causer?.name || 'System' }} on {{ formatDate(activity.created_at) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.task-history-list {
    max-height: 300px;
    overflow-y: auto;
}
</style>
