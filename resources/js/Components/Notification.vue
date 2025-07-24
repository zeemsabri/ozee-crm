<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    type: {
        type: String,
        default: 'info',
        validator: (value) => ['success', 'error', 'info', 'warning'].includes(value)
    },
    message: {
        type: String,
        required: true
    },
    duration: {
        type: Number,
        default: 5000 // 5 seconds
    },
    id: {
        type: String,
        required: true
    }
});

const emit = defineEmits(['close']);

const isVisible = ref(false);
const timeoutId = ref(null);

const typeClasses = computed(() => {
    switch (props.type) {
        case 'success':
            return 'bg-green-50 text-green-800 border-green-400';
        case 'error':
            return 'bg-red-50 text-red-800 border-red-400';
        case 'warning':
            return 'bg-yellow-50 text-yellow-800 border-yellow-400';
        default:
            return 'bg-blue-50 text-blue-800 border-blue-400';
    }
});

const iconClass = computed(() => {
    switch (props.type) {
        case 'success':
            return 'text-green-500';
        case 'error':
            return 'text-red-500';
        case 'warning':
            return 'text-yellow-500';
        default:
            return 'text-blue-500';
    }
});

const icon = computed(() => {
    switch (props.type) {
        case 'success':
            return 'M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z';
        case 'error':
            return 'M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z';
        case 'warning':
            return 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z';
        default:
            return 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
    }
});

const close = () => {
    isVisible.value = false;
    if (timeoutId.value) {
        clearTimeout(timeoutId.value);
    }
    setTimeout(() => {
        emit('close', props.id);
    }, 300); // Wait for the fade-out animation to complete
};

onMounted(() => {
    // Show the notification with a slight delay for animation
    setTimeout(() => {
        isVisible.value = true;
    }, 100);

    // Auto-close after duration
    if (props.duration > 0) {
        timeoutId.value = setTimeout(() => {
            close();
        }, props.duration);
    }
});
</script>

<template>
    <div
        class="fixed z-50 transition-all duration-300 ease-in-out transform"
        :class="[
            isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2',
            'max-w-sm w-full shadow-lg rounded-lg pointer-events-auto border-l-4',
            typeClasses
        ]"
        role="alert"
    >
        <div class="p-4 flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5" :class="iconClass" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" :d="icon" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 w-0 flex-1 pt-0.5">
                <p class="text-sm font-medium">{{ message }}</p>
            </div>
            <div class="ml-4 flex-shrink-0 flex">
                <button
                    class="bg-transparent rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    @click="close"
                >
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
