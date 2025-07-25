<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    projectId: {
        type: Number,
        required: true,
    },
    projectClients: { // Pass project.clients
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const sendingMagicLink = ref(false);
const selectedClientEmail = ref('');
const magicLinkSuccess = ref('');
const magicLinkError = ref('');

const sendMagicLink = async () => {
    if (!selectedClientEmail.value) {
        magicLinkError.value = 'Please select a client email';
        return;
    }

    sendingMagicLink.value = true;
    magicLinkSuccess.value = '';
    magicLinkError.value = '';

    try {
        const response = await window.axios.post(
            `/api/projects/${props.projectId}/magic-link`,
            { email: selectedClientEmail.value }
        );

        if (response.data.success) {
            magicLinkSuccess.value = response.data.message;
            selectedClientEmail.value = ''; // Reset after successful send
        } else {
            magicLinkError.value = response.data.message || 'Failed to send magic link';
        }
    } catch (error) {
        console.error('Error sending magic link:', error);
        magicLinkError.value = error.response?.data?.message || 'An error occurred while sending the magic link';
    } finally {
        sendingMagicLink.value = false;
    }
};

const closeModal = () => {
    selectedClientEmail.value = '';
    magicLinkSuccess.value = '';
    magicLinkError.value = '';
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Send Magic Link to Client</h3>
                <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Success Message -->
            <div v-if="magicLinkSuccess" class="mb-4 p-3 bg-green-100 text-green-800 rounded-md">
                {{ magicLinkSuccess }}
            </div>

            <!-- Error Message -->
            <div v-if="magicLinkError" class="mb-4 p-3 bg-red-100 text-red-800 rounded-md">
                {{ magicLinkError }}
            </div>

            <div class="mb-4">
                <InputLabel for="client-email" value="Select Client" />
                <select
                    id="client-email"
                    v-model="selectedClientEmail"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                >
                    <option value="">Select a client</option>
                    <option v-for="client in projectClients" :key="client.id" :value="client.email">
                        {{ client.name }} ({{ client.email }})
                    </option>
                </select>
                <p class="mt-2 text-sm text-gray-500">
                    The magic link will be sent to the selected client's email address.
                </p>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <SecondaryButton @click="closeModal" type="button">
                    Cancel
                </SecondaryButton>
                <PrimaryButton
                    @click="sendMagicLink"
                    :disabled="sendingMagicLink || !selectedClientEmail"
                    class="bg-purple-600 hover:bg-purple-700"
                >
                    {{ sendingMagicLink ? 'Sending...' : 'Send Magic Link' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
