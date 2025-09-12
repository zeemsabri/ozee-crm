<script setup>
import { useWorkflowStore } from '../Store/workflowStore';
import { computed } from 'vue';

const store = useWorkflowStore();
const modalState = computed(() => store.modalState);

const handleConfirm = () => {
    if (modalState.value.onConfirm) {
        modalState.value.onConfirm();
    }
    store.hideModal();
};

const handleCancel = () => {
    if (modalState.value.onCancel) {
        modalState.value.onCancel();
    }
    store.hideModal();
};
</script>

<template>
    <div v-if="modalState.show" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm">
            <h3 class="text-lg font-semibold text-gray-800">{{ modalState.title }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ modalState.message }}</p>

            <div v-if="modalState.type === 'confirm'" class="mt-6 flex justify-end gap-3">
                <button @click="handleCancel" class="px-4 py-2 text-sm rounded-md border text-gray-700 hover:bg-gray-50">Cancel</button>
                <button @click="handleConfirm" class="px-4 py-2 text-sm rounded-md bg-red-600 text-white hover:bg-red-700">Confirm</button>
            </div>
            <div v-else class="mt-6 flex justify-end">
                <button @click="handleConfirm" class="px-4 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">OK</button>
            </div>
        </div>
    </div>
</template>
