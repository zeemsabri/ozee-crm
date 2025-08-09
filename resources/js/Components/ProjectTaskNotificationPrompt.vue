<script setup>
import { computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    overdueTasks: {
        type: Number,
        required: true
    },
    dueTodayTasks: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['view-tasks']);

const totalDueOrOverdue = computed(() => props.overdueTasks + props.dueTodayTasks);
const hasTasksToShow = computed(() => totalDueOrOverdue.value > 0);

const handleViewClick = () => {
    emit('view-tasks');
};
</script>

<template>
    <div v-if="hasTasksToShow" class="bg-amber-50 border-l-4 border-amber-500 p-4 mb-6 rounded-md shadow-md">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <!-- Alert icon -->
                    <svg class="h-6 w-6 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-amber-800 font-medium">
                        <span v-if="overdueTasks > 0 && dueTodayTasks > 0">
                            This project has {{ overdueTasks }} overdue {{ overdueTasks === 1 ? 'task' : 'tasks' }} and {{ dueTodayTasks }} {{ dueTodayTasks === 1 ? 'task' : 'tasks' }} due today.
                        </span>
                        <span v-else-if="overdueTasks > 0">
                            This project has {{ overdueTasks }} overdue {{ overdueTasks === 1 ? 'task' : 'tasks' }} that require attention.
                        </span>
                        <span v-else-if="dueTodayTasks > 0">
                            This project has {{ dueTodayTasks }} {{ dueTodayTasks === 1 ? 'task' : 'tasks' }} due today.
                        </span>
                    </p>
                </div>
            </div>
            <div>
                <PrimaryButton
                    @click="handleViewClick"
                    class="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 focus:ring-amber-500"
                >
                    View
                </PrimaryButton>
            </div>
        </div>
    </div>
</template>
