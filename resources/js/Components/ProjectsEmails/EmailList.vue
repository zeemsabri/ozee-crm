<script setup>
import { defineProps, defineEmits } from 'vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    emails: {
        type: Array,
        required: true
    },
    loading: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['view']);

const viewEmail = (email) => {
    emit('view', email);
};
</script>

<template>
    <div>
        <div v-if="loading" class="text-center text-gray-600 text-sm animate-pulse py-4">
            Loading email data...
        </div>
        <div v-else-if="error" class="text-center text-red-600 text-sm font-medium py-4">
            {{ error }}
        </div>
        <div v-else-if="emails.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="email in emails" :key="email.id" class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ email.subject }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ email.sender?.name || 'N/A' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ new Date(email.created_at).toLocaleDateString() }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <span
                            :class="{
                                'px-2 py-1 rounded-full text-xs font-medium': true,
                                'bg-blue-100 text-blue-800': email.type === 'sent',
                                'bg-purple-100 text-purple-800': email.type === 'received'
                            }"
                        >
                            {{ email.type ? email.type.toUpperCase() : 'N/A' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <span
                            :class="{
                                'px-2 py-1 rounded-full text-xs font-medium': true,
                                'bg-green-100 text-green-800': email.status === 'sent',
                                'bg-yellow-100 text-yellow-800': email.status === 'pending_approval',
                                'bg-red-100 text-red-800': email.status === 'rejected',
                                'bg-gray-100 text-gray-800': email.status === 'draft'
                            }"
                        >
                            {{ email.status ? email.status.replace('_', ' ').toUpperCase() : 'N/A' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <SecondaryButton class="text-indigo-600 hover:text-indigo-800" @click="viewEmail(email)">
                            View
                        </SecondaryButton>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="text-gray-400 text-sm">No email communication found.</p>
    </div>
</template>
