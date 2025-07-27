<script setup>
import { inject, defineProps, defineEmits } from 'vue';

const props = defineProps(['invoices']);
const emits = defineEmits(['update-invoice', 'add-activity']);

const { showModal } = inject('modalService');

const markInvoicePaid = (id) => {
    showModal('Confirm Payment', 'Are you sure you want to mark this invoice as paid?', 'confirm', () => {
        emits('update-invoice', id, 'Paid'); // Emit event to parent
        emits('add-activity', `Invoice #${id} marked as paid.`); // Emit activity
        showModal('Success', 'Invoice marked as paid.', 'alert');
    });
};
</script>

<template>
    <div id="invoices" class="section">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Your Invoices</h2>
        <div class="bg-white rounded-lg shadow-md overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="props.invoices.length === 0">
                    <td colspan="5" class="p-4 text-center text-gray-500">No invoices found.</td>
                </tr>
                <tr v-for="invoice in props.invoices" :key="invoice.id" class="border-t hover:bg-gray-50">
                    <td class="p-4 whitespace-nowrap">{{ invoice.invoiceNumber }}</td>
                    <td class="p-4 whitespace-nowrap">${{ invoice.amount.toFixed(2) }}</td>
                    <td class="p-4 whitespace-nowrap">{{ invoice.dueDate ? new Date(invoice.dueDate).toLocaleDateString() : 'N/A' }}</td>
                    <td class="p-4 whitespace-nowrap">{{ invoice.status }}</td>
                    <td class="p-4 whitespace-nowrap">
                        <button v-if="invoice.status === 'Pending'" @click="markInvoicePaid(invoice.id)" class="text-blue-600 hover:underline">Mark as Paid</button>
                        <span v-else class="text-gray-500">{{ invoice.status }}</span>
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
