<script setup>
import { inject, defineProps, defineEmits } from 'vue';

const props = defineProps(['approvals']);
const emits = defineEmits(['update-approval', 'add-activity']);

const { showModal } = inject('modalService');

const handleApprovalAction = (id, action) => {
    showModal('Confirm Action', `Are you sure you want to ${action.toLowerCase()} this item?`, 'confirm', () => {
        emits('update-approval', id, action); // Emit event to parent
        emits('add-activity', `${action} approval for item ID: ${id}`); // Emit activity
        showModal('Success', `Item ${id} has been ${action.toLowerCase()}.`, 'alert');
    });
};
</script>

<template>
    <div id="approvals" class="section">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Pending Approvals</h2>
        <div class="bg-white rounded-lg shadow-md overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="props.approvals.length === 0">
                    <td colspan="5" class="p-4 text-center text-gray-500">No pending approvals.</td>
                </tr>
                <tr v-for="approval in props.approvals" :key="approval.id" class="border-t hover:bg-gray-50">
                    <td class="p-4 whitespace-nowrap">{{ approval.title }}</td>
                    <td class="p-4 whitespace-nowrap">{{ approval.type }}</td>
                    <td class="p-4 whitespace-nowrap">
                        <span class="status">{{ approval.status }}</span>
                    </td>
                    <td class="p-4 whitespace-nowrap">{{ approval.date ? new Date(approval.date).toLocaleDateString() : 'N/A' }}</td>
                    <td class="p-4 whitespace-nowrap">
                        <template v-if="approval.status === 'Pending'">
                            <button @click="handleApprovalAction(approval.id, 'Approved')" class="text-green-600 hover:text-green-800 font-semibold mr-3">Approve</button>
                            <button @click="handleApprovalAction(approval.id, 'Rejected')" class="text-red-600 hover:text-red-800 font-semibold">Reject</button>
                        </template>
                        <span v-else class="text-gray-500">{{ approval.status }}</span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed, or rely on Tailwind CSS */
</style>
