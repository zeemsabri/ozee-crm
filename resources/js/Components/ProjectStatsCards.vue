<script setup>
import { computed } from 'vue';

const props = defineProps({
    tasks: {
        type: Array,
        required: true,
    },
    emails: {
        type: Array,
        required: true,
    },
});

const pendingTasksCount = computed(() => {
    return props.tasks.filter(t => t.status !== 'Done').length;
});

const receivedEmailsCount = computed(() => {
    return props.emails.filter(e => e.type === 'received').length;
});

const lastEmailReceivedDate = computed(() => {
    const receivedEmails = props.emails.filter(e => e.status === 'received' || e.type === 'received'); // Ensure 'type' for incoming emails
    if (receivedEmails.length > 0) {
        // Sort by created_at in descending order to get the latest
        const sortedEmails = [...receivedEmails].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        return new Date(sortedEmails[0].created_at).toLocaleDateString();
    }
    return 'N/A';
});

const nextTaskDeadline = computed(() => {
    const incompleteTasks = props.tasks.filter(t => t.status !== 'Done');
    if (incompleteTasks.length > 0) {
        const tasksWithDueDate = incompleteTasks.filter(t => t.due_date);
        if (tasksWithDueDate.length > 0) {
            const sortedTasks = [...tasksWithDueDate].sort((a, b) => new Date(a.due_date) - new Date(b.due_date));
            return sortedTasks[0].due_date;
        }
    }
    return 'N/A';
});
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
            <h4 class="text-sm font-semibold text-gray-500 mb-1">Pending Tasks</h4>
            <p class="text-2xl font-bold text-indigo-600">{{ pendingTasksCount }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
            <h4 class="text-sm font-semibold text-gray-500 mb-1">Received Emails</h4>
            <p class="text-2xl font-bold text-indigo-600">{{ receivedEmailsCount }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
            <h4 class="text-sm font-semibold text-gray-500 mb-1">Last Email Received</h4>
            <p class="text-2xl font-bold text-indigo-600">{{ lastEmailReceivedDate }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
            <h4 class="text-sm font-semibold text-gray-500 mb-1">Next Task Deadline</h4>
            <p class="text-2xl font-bold text-indigo-600">{{ nextTaskDeadline }}</p>
        </div>
    </div>
</template>
