<script setup>
import { defineProps, defineEmits } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        required: true
    },
    email: {
        type: Object,
        required: true
    },
    canApproveEmails: {
        type: Boolean,
        required: true
    }
});

const emit = defineEmits(['close', 'edit', 'reject']);

const handleClose = () => {
    emit('close');
};

const handleEdit = () => {
    emit('edit', props.email);
};

const handleReject = () => {
    emit('reject', props.email);
};
</script>

<template>
    <Modal :show="show" @close="handleClose">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Email Details</h3>
                <button @click="handleClose" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div v-if="email" class="space-y-4">
                <div class="border-b pb-4">
                    <h4 class="text-xl font-medium text-gray-900 mb-2">{{ email.subject }}</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600">From: <span class="text-gray-900">{{ email.sender?.name || 'N/A' }}</span></p>
                            <p class="text-gray-600 mt-1">Status:
                                <span :class="{
                                        'px-2 py-1 rounded-full text-xs font-medium': true,
                                        'bg-green-100 text-green-800': email.status === 'sent',
                                        'bg-yellow-100 text-yellow-800': email.status === 'pending_approval',
                                        'bg-red-100 text-red-800': email.status === 'rejected',
                                        'bg-gray-100 text-gray-800': email.status === 'draft'
                                    }">
                                    {{ email.status ? email.status.replace('_', ' ').toUpperCase() : 'N/A' }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-600">Date: <span class="text-gray-900">{{ new Date(email.created_at).toLocaleString() }}</span></p>
                        </div>
                    </div>
                </div>

                <div class="prose max-w-none">
                    <div v-html="email.body"></div>
                </div>

                <div v-if="email.rejection_reason" class="mt-4 p-4 bg-red-50 rounded-md">
                    <h5 class="font-medium text-red-800">Rejection Reason:</h5>
                    <p class="text-red-700">{{ email.rejection_reason }}</p>
                </div>

                <div v-if="email.approver" class="mt-4 text-sm text-gray-600">
                    <p>Approved/Rejected by: {{ email.approver.name }}</p>
                    <p v-if="email.sent_at">Sent at: {{ new Date(email.sent_at).toLocaleString() }}</p>
                </div>

                <div v-if="(email.status === 'pending_approval' || email.status === 'pending_approval_received') && canApproveEmails" class="mt-6 flex justify-end space-x-2">
                    <PrimaryButton @click="handleEdit" class="bg-blue-600 hover:bg-blue-700">Edit & Approve</PrimaryButton>
                    <SecondaryButton @click="handleReject" class="text-red-600 hover:text-red-800">Reject</SecondaryButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
