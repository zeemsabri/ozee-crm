<script setup>
import { ref, onMounted, watch, defineProps, defineEmits, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import InputLabel from '@/Components/InputLabel.vue';
import CreateDeliverableModal from '@/Components/ProjectsDeliverables/CreateDeliverableModal.vue';
import { useFormatter } from '@/Composables/useFormatter'; // Assuming you have a composable for date formatting

const props = defineProps({
    projectId: {
        type: [String, Number],
        required: true,
    },
    canCreateDeliverables: {
        type: Boolean,
        default: false,
    },
    canViewDeliverables: {
        type: Boolean,
        default: false,
    }
});

const emits = defineEmits(['deliverablesUpdated', 'openDeliverableDetailSidebar']);

const deliverables = ref([]);
const isLoading = ref(true);
const error = ref(null);
const showCreateDeliverableModal = ref(false);
const { formatDate } = useFormatter(); // Use a hypothetical useFormatter composable

const deliverableFilters = ref({
    status: '',
    type: '',
    visibility: '',
    clientStatus: '', // New filter
});

const statusFilterOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'pending_review', label: 'Pending Review' },
    { value: 'for_information', label: 'For Information' },
    { value: 'approved', label: 'Approved' },
    { value: 'revisions_requested', label: 'Revisions Requested' },
    { value: 'completed', label: 'Completed' },
];

const typeFilterOptions = [
    { value: '', label: 'All Types' },
    { value: 'blog_post', label: 'Blog Post' },
    { value: 'design_mockup', label: 'Design Mockup' },
    { value: 'social_media_post', label: 'Social Media Post' },
    { value: 'report', label: 'Report' },
    { value: 'contract_draft', label: 'Contract Draft' },
    { value: 'proposal', label: 'Proposal' },
    { value: 'other', label: 'Other' },
];

const visibilityFilterOptions = [
    { value: '', label: 'All Visibility' },
    { value: 'visible_to_client', label: 'Visible to Client' },
    { value: 'not_visible_to_client', label: 'Not Visible to Client' },
];

const clientStatusFilterOptions = [
    { value: '', label: 'All Client Status' },
    { value: 'read', label: 'Read' },
    { value: 'unread', label: 'Unread' },
    { value: 'approved', label: 'Approved' },
    { value: 'revisions_requested', label: 'Revisions Requested' },
];

const getClientStatus = (deliverable) => {
    if (!deliverable.is_visible_to_client) {
        return { status: 'N/A', class: 'bg-gray-100 text-gray-800' };
    }
    const interaction = deliverable.client_interactions?.[0];
    if (!interaction) {
        return { status: 'Unread', class: 'bg-red-100 text-red-800' };
    }

    if (interaction.approved_at) {
        return { status: 'Approved', class: 'bg-green-100 text-green-800' };
    }
    if (interaction.revisions_requested_at) {
        return { status: 'Revisions Requested', class: 'bg-yellow-100 text-yellow-800' };
    }
    if (interaction.rejected_at) {
        return { status: 'Rejected', class: 'bg-red-100 text-red-800' };
    }
    if (interaction.read_at) {
        return { status: `Read on ${new Date(interaction.read_at).toLocaleDateString()}`, class: 'bg-blue-100 text-blue-800' };
    }

    return { status: 'Unread', class: 'bg-red-100 text-red-800' };
};

const filteredDeliverables = computed(() => {
    if (!deliverables.value.length) return [];

    return deliverables.value.filter(deliverable => {
        const clientStatusInfo = getClientStatus(deliverable);

        // Status filter
        if (deliverableFilters.value.status && deliverable.status !== deliverableFilters.value.status) {
            return false;
        }

        // Type filter
        if (deliverableFilters.value.type && deliverable.type !== deliverableFilters.value.type) {
            return false;
        }

        // Visibility filter
        if (deliverableFilters.value.visibility) {
            if (deliverableFilters.value.visibility === 'visible_to_client' && !deliverable.is_visible_to_client) {
                return false;
            }
            if (deliverableFilters.value.visibility === 'not_visible_to_client' && deliverable.is_visible_to_client) {
                return false;
            }
        }

        // Client Status filter (New)
        if (deliverableFilters.value.clientStatus) {
            if (deliverableFilters.value.clientStatus === 'read' && !clientStatusInfo.status.startsWith('Read')) {
                return false;
            }
            if (deliverableFilters.value.clientStatus === 'unread' && clientStatusInfo.status !== 'Unread') {
                return false;
            }
            if (deliverableFilters.value.clientStatus === 'approved' && clientStatusInfo.status !== 'Approved') {
                return false;
            }
            if (deliverableFilters.value.clientStatus === 'revisions_requested' && clientStatusInfo.status !== 'Revisions Requested') {
                return false;
            }
        }

        return true;
    });
});

const resetFilters = () => {
    deliverableFilters.value.status = '';
    deliverableFilters.value.type = '';
    deliverableFilters.value.visibility = '';
    deliverableFilters.value.clientStatus = ''; // Reset new filter
};

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
    fetchDeliverables();
    emits('deliverablesUpdated');
};

const viewDeliverableDetails = (deliverableId) => {
    emits('openDeliverableDetailSidebar', deliverableId);
};

onMounted(() => {
    fetchDeliverables();
});

watch(() => props.projectId, () => {
    fetchDeliverables();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md min-h-[calc(100vh-6rem)]">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-gray-900">Project Deliverables & Approvals</h4>
            <div class="flex gap-2">
                <SecondaryButton @click="fetchDeliverables" :disabled="isLoading" class="text-indigo-600 hover:text-indigo-800">
                    <span v-if="!isLoading">Refresh</span>
                    <span v-else>Loading...</span>
                </SecondaryButton>
                <PrimaryButton
                    v-if="canCreateDeliverables"
                    @click="showCreateDeliverableModal = true"
                    class="bg-indigo-600 hover:bg-indigo-700 transition-colors"
                >
                    Add New Deliverable
                </PrimaryButton>
            </div>
        </div>

        <div class="mb-6 bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <InputLabel for="deliverable-status-filter" value="Status" />
                    <SelectDropdown
                        id="deliverable-status-filter"
                        v-model="deliverableFilters.status"
                        :options="statusFilterOptions"
                        placeholder="All Statuses"
                        class="mt-1"
                    />
                </div>
                <div>
                    <InputLabel for="deliverable-type-filter" value="Type" />
                    <SelectDropdown
                        id="deliverable-type-filter"
                        v-model="deliverableFilters.type"
                        :options="typeFilterOptions"
                        placeholder="All Types"
                        class="mt-1"
                    />
                </div>
                <div>
                    <InputLabel for="deliverable-visibility-filter" value="Visibility" />
                    <SelectDropdown
                        id="deliverable-visibility-filter"
                        v-model="deliverableFilters.visibility"
                        :options="visibilityFilterOptions"
                        placeholder="All Visibility"
                        class="mt-1"
                    />
                </div>
                <div>
                    <InputLabel for="deliverable-client-status-filter" value="Client Status" />
                    <SelectDropdown
                        id="deliverable-client-status-filter"
                        v-model="deliverableFilters.clientStatus"
                        :options="clientStatusFilterOptions"
                        placeholder="All Client Status"
                        class="mt-1"
                    />
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button
                    type="button"
                    @click="resetFilters"
                    class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
                >
                    Clear Filters
                </button>
            </div>

            <div v-if="Object.values(deliverableFilters).some(v => v !== null && v !== '')" class="mt-3 text-sm text-gray-600">
                <p>
                    Showing {{ filteredDeliverables.length }} of {{ deliverables.length }} deliverables
                    <span v-if="filteredDeliverables.length === 0" class="text-red-600 font-medium">
                        (No deliverables match the current filters)
                    </span>
                </p>
            </div>
        </div>

        <div v-if="isLoading" class="text-center text-gray-600 text-sm animate-pulse py-4">
            Loading deliverables...
        </div>

        <div v-else-if="error" class="text-center py-4">
            <p class="text-red-600 text-sm font-medium">{{ error }}</p>
        </div>

        <div v-else-if="filteredDeliverables.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted By</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted On</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visibility</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client Status</th> <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="deliverable in filteredDeliverables" :key="deliverable.id" class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ deliverable.title }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 capitalize">{{ deliverable.type.replace(/_/g, ' ') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <span :class="{
                            'px-2 py-1 rounded-full text-xs font-medium': true,
                            'bg-yellow-100 text-yellow-800': deliverable.status === 'pending_review' || deliverable.status === 'revisions_requested',
                            'bg-green-100 text-green-800': deliverable.status === 'approved',
                            'bg-blue-100 text-blue-800': deliverable.status === 'completed',
                            'bg-gray-100 text-gray-800': deliverable.status === 'for_information',
                        }">
                            {{ deliverable.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ deliverable.team_member?.name || 'Unknown' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ new Date(deliverable.submitted_at).toLocaleDateString() }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <span :class="{
                            'px-2 py-1 rounded-full text-xs font-medium': true,
                            'bg-green-100 text-green-800': deliverable.is_visible_to_client,
                            'bg-red-100 text-red-800': !deliverable.is_visible_to_client,
                        }">
                            {{ deliverable.is_visible_to_client ? 'Visible' : 'Hidden' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <span :class="['px-2 py-1 rounded-full text-xs font-medium', getClientStatus(deliverable).class]">
                            {{ getClientStatus(deliverable).status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end space-x-2">
                            <button
                                @click="viewDeliverableDetails(deliverable.id)"
                                class="text-purple-600 hover:text-purple-800 text-sm font-medium"
                            >
                                View Details
                            </button>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="text-center py-4">
            <p class="text-gray-400 text-sm">No deliverables found for this project.</p>
            <p class="text-gray-500 text-sm mt-2">
                Click the "Add New Deliverable" button to create one.
            </p>
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
