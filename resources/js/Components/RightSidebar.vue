<script setup>
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3'; // Assuming usePage is available for breakpoints

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Details',
    },
    initialWidth: {
        type: Number,
        default: 50, // Default to 50% of viewport width
    },
    minWidth: {
        type: Number,
        default: 30, // Minimum width as percentage
    },
    maxWidth: {
        type: Number,
        default: 90, // Maximum width as percentage
    },
});

const emit = defineEmits(['close', 'update:show']);

const sidebarWidth = ref(props.initialWidth); // Stored as a percentage
const isResizing = ref(false);

const handleMouseDown = (e) => {
    isResizing.value = true;
    document.addEventListener('pointermove', handleMouseMove);
    document.addEventListener('pointerup', handleMouseUp);
};

const handleMouseMove = (e) => {
    if (!isResizing.value) return;

    // Calculate new width based on mouse position relative to viewport
    // The sidebar opens from the right, so width is (viewportWidth - mouseX)
    const viewportWidth = window.innerWidth;
    const newWidthPx = viewportWidth - e.clientX;
    const newWidthPercent = (newWidthPx / viewportWidth) * 100;

    // Apply min/max constraints
    const constrainedWidth = Math.max(props.minWidth, Math.min(props.maxWidth, newWidthPercent));
    sidebarWidth.value = constrainedWidth;
};

const handleMouseUp = () => {
    isResizing.value = false;
    document.removeEventListener('pointermove', handleMouseMove);
    document.removeEventListener('pointerup', handleMouseUp);
};

// Watch for changes in the 'show' prop to manage body overflow
watch(() => props.show, (newValue) => {
    if (newValue) {
        document.body.style.overflow = 'hidden'; // Prevent scrolling on main content
    } else {
        document.body.style.overflow = ''; // Restore scrolling
    }
}, { immediate: true });

const closeSidebar = () => {
    emit('update:show', false);
    emit('close');
};

// Adjust initial width for mobile (e.g., full screen)
const isMobile = computed(() => {
    // A simple check, you might want to use a more robust breakpoint system
    // from usePage().props.app.breakpoint if available in your Inertia setup
    return window.innerWidth < 768; // Tailwind's 'md' breakpoint
});

// Watch for isMobile changes to adjust sidebarWidth on the fly
watch(isMobile, (newVal) => {
    if (newVal && props.show) {
        sidebarWidth.value = 100; // Full width on mobile
    } else if (!newVal && props.show) {
        sidebarWidth.value = props.initialWidth; // Restore initial on desktop
    }
}, { immediate: true });

// Ensure initial width is applied when component becomes visible
watch(() => props.show, (newVal) => {
    if (newVal) {
        if (isMobile.value) {
            sidebarWidth.value = 100;
        } else {
            sidebarWidth.value = props.initialWidth;
        }
    }
});

</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition ease-out duration-300 transform"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition ease-in duration-200 transform"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex"
                :class="{ 'pointer-events-none': !show }"
            >
                <!-- Overlay -->
<!--                <div-->
<!--                    class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity"-->
<!--                    @click="closeSidebar"-->
<!--                ></div>-->

                <!-- Sidebar Content -->
                <div
                    class="relative ml-auto h-full bg-white shadow-xl flex flex-col rounded-l-xl"
                    :style="{ width: isMobile ? '100%' : `${sidebarWidth}vw` }"
                >
                    <!-- Resizing Handle (only on desktop) -->
                    <div
                        v-if="!isMobile"
                        class="absolute left-0 top-0 h-full w-2 cursor-col-resize -ml-1 z-10"
                        @pointerdown="handleMouseDown"
                        title="Drag to resize"
                    ></div>

                    <!-- Header -->
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-white rounded-tl-xl sticky top-0 z-20">
                        <h3 class="text-xl font-semibold text-gray-900">{{ title }}</h3>
                        <button
                            @click="closeSidebar"
                            class="text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Content Area -->
                    <div class="flex-1 overflow-y-auto p-6 scrollbar-thin scrollbar-thumb-rounded scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                        <slot name="content"></slot>
                    </div>

                    <!-- Footer -->
                    <div v-if="$slots.footer" class="p-4 border-t border-gray-200 bg-white sticky bottom-0 z-20">
                        <slot name="footer"></slot>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<style scoped>
/* Custom scrollbar styles for better UX */
/* For Webkit browsers (Chrome, Safari) */
.scrollbar-thin::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: var(--tw-bg-gray-100);
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: var(--tw-bg-gray-300);
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: var(--tw-bg-gray-400);
}

/* For Firefox */
.scrollbar-thin {
    scrollbar-width: thin;
    scrollbar-color: var(--tw-bg-gray-300) var(--tw-bg-gray-100);
}
</style>
