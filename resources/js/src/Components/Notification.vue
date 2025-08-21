<script setup>
import { computed } from 'vue';
import { CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    notification: Object,
});

const emit = defineEmits(['close']);

const notificationClass = computed(() => {
    switch (props.notification.type) {
        case 'success':
            return 'bg-green-100 text-green-700 border-green-200';
        case 'error':
            return 'bg-red-100 text-red-700 border-red-200';
        case 'warning':
            return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        default:
            return 'bg-gray-100 text-gray-700 border-gray-200';
    }
});

const notificationIcon = computed(() => {
    switch (props.notification.type) {
        case 'success':
            return CheckCircleIcon;
        case 'error':
            return XCircleIcon;
        case 'warning':
            return ExclamationTriangleIcon;
        default:
            return InformationCircleIcon;
    }
});
</script>

<template>
    <div
        v-if="notification && notification.message"
        class="fixed inset-x-0 top-0 mx-auto mt-4 max-w-sm rounded-md border p-4 shadow-lg transition-all duration-300 ease-out"
        :class="notificationClass"
    >
        <div class="flex items-center">
            <component :is="notificationIcon" class="h-6 w-6 mr-3 flex-shrink-0" />
            <div class="flex-1">
                <h4 v-if="notification.title" class="font-bold">{{ notification.title }}</h4>
                <p class="text-sm">{{ notification.message }}</p>
            </div>
            <button @click="$emit('close')" class="ml-auto -mr-1.5 p-1 rounded-md hover:bg-opacity-50">
                <XMarkIcon class="h-5 w-5" />
            </button>
        </div>
    </div>
</template>
