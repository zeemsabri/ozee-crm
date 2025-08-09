<script setup>
import { computed, ref, onMounted } from 'vue';

const navElement = ref(null);

const props = defineProps({
    selectedTab: {
        type: String,
        default: null, // 'null' for overview
    },
    canViewEmails: {
        type: Boolean,
        required: true,
    },
    canViewNotes: {
        type: Boolean,
        required: true,
    },
    canViewDeliverables: { // NEW PROP
        type: Boolean,
        required: true,
    },
    canViewSeoReports: { // NEW PROP for SEO Reports
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['update:selectedTab']);

const currentTab = computed({
    get: () => props.selectedTab,
    set: (value) => emit('update:selectedTab', value),
});

// Method to get the navigation element for scrolling
const scrollIntoView = (options) => {
    if (navElement.value) {
        navElement.value.scrollIntoView(options);
    }
};

// Expose methods and refs to parent component
defineExpose({
    scrollIntoView
});
</script>

<template>
    <div class="bg-white p-4 rounded-xl shadow-md mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8 overflow-x-auto" ref="navElement">
                <button
                    @click="currentTab = null"
                    :class="[
                        currentTab === null
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    Overview
                </button>
                <button
                    @click="currentTab = 'tasks'"
                    :class="[
                        currentTab === 'tasks'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    Project Tasks
                </button>
                <button
                    v-if="canViewEmails"
                    @click="currentTab = 'emails'"
                    :class="[
                        currentTab === 'emails'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    Email Communication
                </button>
                <button
                    v-if="canViewNotes"
                    @click="currentTab = 'notes'"
                    :class="[
                        currentTab === 'notes'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    Notes
                </button>
                <button
                    v-if="canViewNotes"
                    @click="currentTab = 'standups'"
                    :class="[
                        currentTab === 'standups'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    Daily Standups
                </button>
                <button
                    v-if="canViewDeliverables"
                    @click="currentTab = 'deliverables'"
                    :class="[
                    currentTab === 'deliverables'
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    Deliverables
                </button>

                <button
                    v-if="canViewSeoReports"
                    @click="currentTab = 'seo-reports'"
                    :class="[
                    currentTab === 'seo-reports'
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    SEO Reports
                </button>

            </nav>
        </div>
    </div>
</template>
