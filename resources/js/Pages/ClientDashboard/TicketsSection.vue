<script setup>
import { ref, inject, defineProps, defineEmits } from 'vue';

const props = defineProps(['tickets']);
const emits = defineEmits(['add-ticket', 'add-activity']);

const title = ref('');
const description = ref('');
const priority = ref('Medium');
const { showModal } = inject('modalService');

const handleSubmit = () => {
    if (!title.value.trim() || !description.value.trim()) {
        showModal('Input Required', 'Please fill in both the ticket title and description.', 'alert');
        return;
    }

    const newTicket = {
        id: Date.now(), // Simple unique ID
        title: title.value,
        description: description.value,
        priority: priority.value,
        status: 'Open',
        date: new Date().toISOString(), // Use ISO string for date
    };

    emits('add-ticket', newTicket); // Emit event to parent to update tickets
    emits('add-activity', `New ticket created: ${title.value}`); // Emit activity

    showModal('Success', 'Your ticket has been submitted successfully!', 'alert');
    title.value = '';
    description.value = '';
    priority.value = 'Medium';
};
</script>

<template>
    <div id="tickets" class="section">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Tasks & Support Tickets</h2>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Create New Ticket</h3>
            <input
                type="text"
                placeholder="Ticket Title (e.g., Update Website Banner)"
                class="w-full p-3 mb-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                v-model="title"
            />
            <textarea
                placeholder="Describe the issue or task in detail"
                class="w-full p-3 mb-3 border border-gray-300 rounded-lg h-24 resize-y focus:ring-blue-500 focus:border-blue-500"
                v-model="description"
            ></textarea>
            <select
                class="w-full p-3 mb-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                v-model="priority"
            >
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
                <option value="Urgent">Urgent</option>
            </select>
            <button @click="handleSubmit" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Submit Ticket</button>
        </div>
        <div class="bg-white rounded-lg shadow-md overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="props.tickets.length === 0">
                    <td colspan="4" class="p-4 text-center text-gray-500">No tickets found.</td>
                </tr>
                <tr v-for="ticket in props.tickets" :key="ticket.id" class="border-t hover:bg-gray-50">
                    <td class="p-4 whitespace-nowrap">{{ ticket.title }}</td>
                    <td class="p-4 whitespace-nowrap">{{ ticket.status }}</td>
                    <td class="p-4 whitespace-nowrap">{{ ticket.priority }}</td>
                    <td class="p-4 whitespace-nowrap">{{ ticket.date ? new Date(ticket.date).toLocaleDateString() : 'N/A' }}</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed, or rely on Tailwind CSS */
</style>
