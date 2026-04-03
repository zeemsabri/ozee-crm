<script setup>
import { 
    XMarkIcon, 
    BellAlertIcon,
    ChatBubbleLeftRightIcon,
    ClipboardDocumentListIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    summary: {
        type: Object,
        default: () => ({
            mentions: 0,
            tasks: 0,
            others: 0
        })
    }
});

const emit = defineEmits(['close', 'openSidebar']);

const handleOpenSidebar = () => {
    emit('openSidebar');
    emit('close');
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[150] flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="emit('close')"></div>

        <!-- Modal Content -->
        <div class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300">
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <BellAlertIcon class="h-6 w-6 text-indigo-600" />
                    Pending Updates
                </h3>
                <button @click="emit('close')" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8">
                <p class="text-slate-600 dark:text-slate-400 mb-6 text-center">
                    You have some unread notifications waiting for your attention.
                </p>

                <div class="space-y-4">
                    <!-- Mentions -->
                    <div v-if="summary.mentions > 0" class="flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-2xl border border-blue-100 dark:border-blue-800">
                        <div class="h-10 w-10 flex-shrink-0 bg-blue-500 rounded-xl flex items-center justify-center text-white shadow-sm ring-4 ring-blue-500/10">
                            <ChatBubbleLeftRightIcon class="h-6 w-6" />
                        </div>
                        <div class="ml-4 flex-1">
                            <span class="block text-sm font-bold text-blue-900 dark:text-blue-100">{{ summary.mentions }} Mentions</span>
                            <span class="text-xs text-blue-700 dark:text-blue-300">New team chat responses</span>
                        </div>
                    </div>

                    <!-- Tasks -->
                    <div v-if="summary.tasks > 0" class="flex items-center p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl border border-indigo-100 dark:border-indigo-800">
                        <div class="h-10 w-10 flex-shrink-0 bg-indigo-500 rounded-xl flex items-center justify-center text-white shadow-sm ring-4 ring-indigo-500/10">
                            <ClipboardDocumentListIcon class="h-6 w-6" />
                        </div>
                        <div class="ml-4 flex-1">
                            <span class="block text-sm font-bold text-indigo-900 dark:text-indigo-100">{{ summary.tasks }} Task Updates</span>
                            <span class="text-xs text-indigo-700 dark:text-indigo-300">Assignments or status changes</span>
                        </div>
                    </div>

                    <!-- Others -->
                    <div v-if="summary.others > 0" class="flex items-center p-4 bg-slate-50 dark:bg-slate-700/20 rounded-2xl border border-slate-100 dark:border-slate-600">
                        <div class="h-10 w-10 flex-shrink-0 bg-slate-500 rounded-xl flex items-center justify-center text-white shadow-sm ring-4 ring-slate-500/10">
                            <InformationCircleIcon class="h-6 w-6" />
                        </div>
                        <div class="ml-4 flex-1">
                            <span class="block text-sm font-bold text-slate-900 dark:text-gray-100">{{ summary.others }} Other Updates</span>
                            <span class="text-xs text-slate-700 dark:text-slate-400">General system notifications</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-3">
                <button @click="emit('close')"
                        class="px-5 py-2.5 rounded-xl text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors text-sm font-medium">
                    Maybe Later
                </button>
                <button @click="handleOpenSidebar"
                        class="px-6 py-2.5 bg-indigo-600 text-white rounded-xl shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all text-sm font-bold">
                    View Now
                </button>
            </div>
        </div>
    </div>
</template>
