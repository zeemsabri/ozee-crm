<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import {
    XMarkIcon,
    BellAlertIcon,
    MegaphoneIcon,
    CheckBadgeIcon,
    ExclamationTriangleIcon,
    ArrowTopRightOnSquareIcon,
    UserGroupIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    unreadNotices: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close']);

const acknowledgeChecked = ref(false);
const saving = ref(false);

const acknowledgeNotices = async () => {
    if (!acknowledgeChecked.value || saving.value) return;

    saving.value = true;
    try {
        const ids = props.unreadNotices.map(n => n.id);
        if (ids.length === 0) {
            emit('close');
            return;
        }
        await axios.post('/api/notices/acknowledge', { notice_ids: ids });
        acknowledgeChecked.value = false;
        emit('close');
    } catch (e) {
        console.error('Failed to acknowledge notices', e);
    } finally {
        saving.value = false;
    }
};

const getNoticeIcon = (type) => {
    switch (type) {
        case 'Warning':
            return ExclamationTriangleIcon;
        case 'Updates':
            return MegaphoneIcon;
        case 'Final Notice':
            return CheckBadgeIcon;
        default:
            return BellAlertIcon;
    }
};

const getNoticeColor = (type) => {
    switch (type) {
        case 'Warning':
            return 'text-red-500 bg-red-100';
        case 'Updates':
            return 'text-blue-500 bg-blue-100';
        case 'Final Notice':
            return 'text-yellow-500 bg-yellow-100';
        default:
            return 'text-indigo-500 bg-indigo-100';
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emit('close')"></div>

        <!-- Modal Content -->
        <div class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-2xl w-full mx-auto transform transition-all duration-300 scale-100"
             :class="{'scale-95 opacity-0': !show}">

            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <BellAlertIcon class="h-7 w-7 text-indigo-500" />
                    Important Notices
                </h3>
                <button @click="emit('close')" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                    <XMarkIcon class="h-6 w-6" />
                </button>
            </div>

            <!-- Notices List - Scrollable Body -->
            <div class="max-h-[70vh] overflow-y-auto p-6 space-y-4">
                <div v-if="unreadNotices.length === 0" class="text-center text-gray-500 py-8">
                    All notices have been read.
                </div>
                <div v-for="notice in unreadNotices" :key="notice.id" class="bg-gray-50 dark:bg-gray-700 rounded-2xl p-5 shadow-sm border border-gray-200 dark:border-gray-600">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-2">
                                <component :is="getNoticeIcon(notice.type)" class="h-5 w-5" :class="getNoticeColor(notice.type)" />
                                <div class="font-bold text-lg text-gray-900 dark:text-white truncate">
                                    {{ notice.title }}
                                </div>
                            </div>
                            <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">
                                {{ notice.description }}
                            </div>
                        </div>
                        <div v-if="notice.url" class="flex-shrink-0">
                            <a :href="`/notices/${notice.id}/redirect`" target="_blank"
                               class="flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200 transition-colors font-medium text-sm">
                                Open Link
                                <ArrowTopRightOnSquareIcon class="h-4 w-4 ml-1" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 border-t border-gray-200 dark:border-gray-700 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center w-full md:w-auto">
                    <input id="acknowledge" type="checkbox" v-model="acknowledgeChecked"
                           class="h-5 w-5 text-indigo-600 rounded-lg border-gray-300 dark:border-gray-500 dark:bg-gray-600 focus:ring-indigo-500 focus:ring-offset-0 transition-colors" />
                    <label for="acknowledge" class="ml-3 text-sm text-gray-700 dark:text-gray-300 select-none">
                        I have read and understood this notice.
                    </label>
                </div>
                <div class="flex justify-end gap-3 w-full md:w-auto">
                    <button @click="emit('close')"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-sm font-medium">
                        Close
                    </button>
                    <button @click="acknowledgeNotices" :disabled="!acknowledgeChecked || saving"
                            class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl shadow-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-indigo-700 transition-colors text-sm font-medium">
                        <span v-if="saving">Acknowledging...</span>
                        <span v-else>Acknowledge</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
