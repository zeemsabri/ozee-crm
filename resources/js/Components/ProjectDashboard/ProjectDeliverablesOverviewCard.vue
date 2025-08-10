<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import { error, success } from '@/Utils/notification';
import { ChevronDownIcon, ChevronUpIcon, ChevronRightIcon, ChevronLeftIcon } from '@heroicons/vue/20/solid';
import ChecklistComponent from '@/Components/ChecklistComponent.vue';

const props = defineProps({
    projectId: {
        type: [Number, String],
        required: true
    },
    canViewProjectDeliverables: {
        type: Boolean,
        default: true
    },
    isCollapsed: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:isCollapsed']);

// A local state to manage the collapsed status, independent of the parent
const isLocallyCollapsed = ref(props.isCollapsed);

// Watch for changes from the parent prop and update the local state
// This allows the parent to still control the collapse state if needed.
watch(() => props.isCollapsed, (newVal) => {
    isLocallyCollapsed.value = newVal;
});

const allDeliverables = ref([]);
const isLoading = ref(true);
const selectedTab = ref('in-progress');
const expandedDeliverables = ref({}); // State to track which deliverables are expanded

const filteredDeliverables = computed(() => {
    if (selectedTab.value === 'in-progress') {
        return allDeliverables.value.filter(d => d.status !== 'completed' && d.status !== 'cancelled');
    } else {
        return allDeliverables.value.filter(d => d.status === 'completed' || d.status === 'cancelled');
    }
});

const fetchDeliverables = async () => {
    if (!props.canViewProjectDeliverables) return;

    isLoading.value = true;
    try {
        const response = await axios.get(`/api/projects/${props.projectId}/project-deliverables`);
        allDeliverables.value = response.data;
    } catch (err) {
        console.error('Error fetching deliverables:', err);
        error('Failed to load project deliverables.');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchDeliverables();
});

const toggleExpand = (deliverableId) => {
    expandedDeliverables.value[deliverableId] = !expandedDeliverables.value[deliverableId];
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
};

const getCompletedChecklistCount = (checklist) => {
    if (!checklist || checklist.length === 0) return 0;
    return checklist.filter(item => item.completed).length;
};

const handleChecklistUpdate = (deliverable, data) => {
    // Optimistically update the UI
    const localDeliverable = allDeliverables.value.find(d => d.id === deliverable.id);
    if (localDeliverable) {
        localDeliverable.details.checklist[data.index].completed = data.completed;
    }

    try {
        const payload = {
            ...deliverable,
            details: {
                ...deliverable.details,
                checklist: localDeliverable.details.checklist
            }
        };

        axios.put(`/api/project-deliverables/${deliverable.id}`, payload);
        success('Checklist item updated!');
    } catch (err) {
        console.error('Error updating checklist item:', err);
        error('Failed to update checklist item');
        // Revert the local state on error
        if (localDeliverable) {
            localDeliverable.details.checklist[data.index].completed = !data.completed;
        }
    }
};

const toggleCollapse = () => {
    isLocallyCollapsed.value = !isLocallyCollapsed.value;
    emit('update:isCollapsed', isLocallyCollapsed.value);
};
</script>

<template>
    <div v-if="canViewProjectDeliverables" class="bg-white rounded-xl shadow-lg border border-gray-200 h-full flex flex-col relative">

        <!-- Mobile Collapsed View -->
        <div v-if="isLocallyCollapsed" @click="toggleCollapse" class="flex lg:hidden items-center p-4 cursor-pointer">
            <span class="text-gray-600 font-bold text-lg flex-1">Project Deliverables</span>
            <ChevronLeftIcon class="h-5 w-5 text-gray-500" />
        </div>

        <!-- Desktop Collapsed View -->
        <div v-if="isLocallyCollapsed" @click="toggleCollapse" class="hidden lg:flex items-center justify-center cursor-pointer p-2 h-full">
            <span class="text-gray-600 font-bold text-lg" style="writing-mode: vertical-rl; text-orientation: mixed;">
                Project Deliverables
            </span>
        </div>

        <!-- Expanded view: The full card content -->
        <div v-else class="h-full flex flex-col">
            <!-- Header with tabs and collapse button -->
            <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-200">
                <h3 class="text-lg sm:text-xl font-bold text-gray-900">Project Deliverables</h3>
                <button
                    @click="toggleCollapse"
                    class="p-1 rounded-full text-gray-500 hover:bg-gray-200 transition-colors"
                >
                    <ChevronRightIcon class="h-5 w-5" />
                </button>
            </div>
            <div class="flex px-4 sm:px-6 border-b border-gray-200">
                <button
                    @click="selectedTab = 'in-progress'"
                    :class="{
                        'py-2 px-3 text-sm font-medium': true,
                        'text-indigo-600 border-b-2 border-indigo-600': selectedTab === 'in-progress',
                        'text-gray-500 hover:text-gray-700': selectedTab !== 'in-progress'
                    }"
                >
                    In Progress
                </button>
                <button
                    @click="selectedTab = 'completed'"
                    :class="{
                        'py-2 px-3 text-sm font-medium': true,
                        'text-indigo-600 border-b-2 border-indigo-600': selectedTab === 'completed',
                        'text-gray-500 hover:text-gray-700': selectedTab !== 'completed'
                    }"
                >
                    Completed
                </button>
            </div>

            <!-- Main content area -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <!-- Loading state -->
                <div v-if="isLoading" class="flex justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                </div>

                <!-- Empty state -->
                <div v-else-if="filteredDeliverables.length === 0" class="bg-gray-50 p-4 sm:p-6 rounded-lg text-center text-sm">
                    <p class="text-gray-600">No {{ selectedTab }} deliverables found.</p>
                </div>

                <!-- Deliverables list -->
                <div v-else class="space-y-3 sm:space-y-4">
                    <div
                        v-for="deliverable in filteredDeliverables"
                        :key="deliverable.id"
                        class="bg-gray-50 border border-gray-200 rounded-lg p-3 sm:p-4 shadow-sm hover:shadow-md transition-all duration-300"
                    >
                        <div class="flex items-center justify-between cursor-pointer" @click="toggleExpand(deliverable.id)">
                            <div class="flex items-center space-x-2 sm:space-x-3">
                                <button class="p-1 rounded-full text-gray-500 hover:bg-gray-200 transition-colors">
                                    <ChevronDownIcon v-if="!expandedDeliverables[deliverable.id]" class="h-4 w-4 sm:h-5 sm:w-5" />
                                    <ChevronUpIcon v-else class="h-4 w-4 sm:h-5 sm:w-5" />
                                </button>
                                <div>
                                    <h4 class="text-sm sm:text-base font-medium text-gray-900">{{ deliverable.name }}</h4>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <span v-if="deliverable.details?.checklist?.length > 0" class="text-xs font-medium text-gray-600">
                                    {{ getCompletedChecklistCount(deliverable.details.checklist) }} / {{ deliverable.details.checklist.length }}
                                </span>
                                <span :class="{
                                    'font-medium py-0.5 px-2 rounded-full text-white text-xs': true,
                                    'bg-yellow-500': deliverable.status === 'pending',
                                    'bg-blue-500': deliverable.status === 'in_progress',
                                    'bg-green-500': deliverable.status === 'completed',
                                    'bg-red-500': deliverable.status === 'cancelled'
                                }">
                                    {{ deliverable.status.replace('_', ' ').toUpperCase() }}
                                </span>
                            </div>
                        </div>

                        <div v-if="expandedDeliverables[deliverable.id]" class="mt-3 pl-6 sm:mt-4 sm:pl-8">
                            <p v-if="deliverable.description" class="text-xs sm:text-sm text-gray-600 mb-2">{{ deliverable.description }}</p>
                            <div class="mt-2 flex items-center space-x-2 text-xs text-gray-500">
                                <span v-if="deliverable.milestone" class="text-gray-700">
                                    <span class="font-semibold">Milestone:</span> {{ deliverable.milestone.name }}
                                </span>
                                <span v-if="deliverable.due_date" class="text-gray-700">
                                    <span class="font-semibold">Due:</span> {{ formatDate(deliverable.due_date) }}
                                </span>
                            </div>

                            <ChecklistComponent
                                v-if="deliverable.details?.checklist && deliverable.details.checklist.length > 0"
                                :items="deliverable.details.checklist"
                                :api-endpoint="`/api/project-deliverables/${deliverable.id}`"
                                :title="null"
                                :payload-transformer="(items) => ({
                                    details: {
                                        ...deliverable.details,
                                        checklist: items
                                    }
                                })"
                                @item-toggled="(data) => handleChecklistUpdate(deliverable, data)"
                                density="tight"
                                container-class="p-0 border-0 bg-transparent text-sm"
                                title-class="hidden"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
