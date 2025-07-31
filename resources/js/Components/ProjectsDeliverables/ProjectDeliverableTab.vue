<script setup>
import { ref, onMounted, watch, defineProps, defineEmits } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CreateDeliverableModal from '@/Components/ProjectsDeliverables/CreateDeliverableModal.vue'; // Adjust path as needed

const props = defineProps({
    projectId: {
        type: [String, Number],
        required: true,
    },
    // projectUsers prop is removed as it's no longer needed by CreateDeliverableModal
    // projectUsers: { // Pass project users for assigning team member in modal
    //     type: Array,
    //     default: () => [],
    // },
    canCreateDeliverables: { // Permission prop
        type: Boolean,
        default: false,
    },
    canViewDeliverables: { // Permission prop
        type: Boolean,
        default: false,
    }
});

const emits = defineEmits(['deliverablesUpdated']);

const deliverables = ref([]);
const isLoading = ref(true);
const error = ref(null);
const showCreateDeliverableModal = ref(false);

const fetchDeliverables = async () => {
    if (!props.canViewDeliverables) {
        error.value = "You do not have permission to view deliverables.";
        isLoading.value = false;
        return;
    }

    isLoading.value = true;
    error.value = null;
    try {
        const response = await window.axios.get(route('projects.deliverables.index', props.projectId));
        deliverables.value = response.data;
    } catch (err) {
        console.error('Error fetching deliverables:', err);
        error.value = 'Failed to load deliverables.';
    } finally {
        isLoading.value = false;
    }
};

const handleDeliverableSaved = () => {
    fetchDeliverables(); // Re-fetch deliverables after a new one is saved
    emits('deliverablesUpdated'); // Notify parent (Show.vue) if needed
};

// Fetch deliverables when component mounts or projectId changes
onMounted(() => {
    fetchDeliverables();
});

watch(() => props.projectId, () => {
    fetchDeliverables();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md min-h-[calc(100vh-6rem)]">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold text-gray-800">Project Deliverables & Approvals</h3>
            <PrimaryButton
                v-if="canCreateDeliverables"
                @click="showCreateDeliverableModal = true"
                class="bg-indigo-600 hover:bg-indigo-700 transition-colors"
            >
                Add New Deliverable
            </PrimaryButton>
        </div>

        <div v-if="isLoading" class="text-center py-8 text-gray-600">Loading deliverables...</div>
        <div v-else-if="error" class="text-center py-8 text-red-600">{{ error }}</div>
        <div v-else-if="deliverables.length === 0" class="text-center py-8 text-gray-500">
            No deliverables found for this project.
        </div>
        <div v-else class="space-y-4">
            <div v-for="deliverable in deliverables" :key="deliverable.id"
                 class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-200 flex items-center justify-between hover:shadow-md transition-shadow">
                <div>
                    <h4 class="text-lg font-medium text-gray-900">{{ deliverable.title }}</h4>
                    <p class="text-sm text-gray-600 line-clamp-2">{{ deliverable.description || 'No description.' }}</p>
                    <div class="mt-2 text-xs text-gray-500">
                        Type: <span class="font-semibold capitalize">{{ deliverable.type.replace(/_/g, ' ') }}</span> |
                        Status: <span :class="{
                            'font-semibold': true,
                            'text-yellow-700': deliverable.status === 'pending_review' || deliverable.status === 'revisions_requested',
                            'text-green-700': deliverable.status === 'approved',
                            'text-blue-700': deliverable.status === 'completed',
                            'text-gray-700': deliverable.status === 'for_information', // NEW: Style for 'for_information'
                        }">{{ deliverable.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()) }}</span> |
                        Submitted by: <span class="font-semibold">{{ deliverable.team_member?.name || 'Unknown' }}</span>
                        on {{ new Date(deliverable.submitted_at).toLocaleDateString() }}
                    </div>
                    <div v-if="deliverable.content_url" class="mt-2 text-xs text-blue-600 hover:underline">
                        <a :href="deliverable.content_url" target="_blank" rel="noopener noreferrer">View Content Link</a>
                    </div>
                    <div v-if="deliverable.attachment_path" class="mt-2 text-xs text-blue-600 hover:underline">
                        <a :href="deliverable.attachment_path" target="_blank" rel="noopener noreferrer">View Attachment</a>
                    </div>
                    <div v-if="deliverable.is_visible_to_client" class="mt-2 text-xs text-green-600">
                        Visible to Client
                    </div>
                    <div v-else class="mt-2 text-xs text-red-600">
                        Not Visible to Client
                    </div>
                </div>
                <!-- You can add action buttons here, e.g., Edit, View, Send for Approval (if not already sent) -->
                <div>
                    <!-- Example: Edit button -->
                    <!-- <button class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Edit</button> -->
                </div>
            </div>
        </div>

        <CreateDeliverableModal
            :show="showCreateDeliverableModal"
            :project-id="projectId"
            @close="showCreateDeliverableModal = false"
            @saved="handleDeliverableSaved"
        />
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed, or rely on Tailwind CSS */
</style>
