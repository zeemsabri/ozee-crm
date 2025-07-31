<script setup>
import { defineProps, defineEmits, computed, inject, ref } from 'vue';

const props = defineProps({
    deliverables: {
        type: Array,
        default: () => []
    },
    initialAuthToken: { // Needed for passing to modal if opened from here
        type: String,
        required: true
    },
    projectId: { // Needed for context in API calls if not part of deliverable object
        type: [String, Number],
        required: true
    }
});

const emits = defineEmits(['open-deliverable-viewer']); // Emits an event to parent to open the deliverable viewer modal

// Inject the showModal from ClientDashboard for showing alerts/confirms if needed
const { showModal } = inject('modalService');

const activeTab = ref('action-required'); // New: State for active tab
const approvalSearchQuery = ref(''); // Search query for approvals

// --- Computed properties for filtered deliverables based on tabs ---

// Action Required Deliverables (most critical, needs client action)
const actionRequiredDeliverables = computed(() => {
    return props.deliverables.filter(deliverable => {
        const isPendingReview = deliverable.status === 'pending_review';
        const isRevisionsRequested = deliverable.status === 'revisions_requested';
        const clientInteraction = deliverable.client_interaction;

        if (isPendingReview) {
            return !clientInteraction || (!clientInteraction.approved_at && !clientInteraction.rejected_at && !clientInteraction.revisions_requested_at);
        }
        if (isRevisionsRequested) {
            // A deliverable is 'revisions_requested' and needs action if client hasn't approved/rejected it yet
            return !clientInteraction || (!clientInteraction.approved_at && !clientInteraction.rejected_at);
        }
        return false;
    }).sort((a, b) => new Date(b.submitted_at) - new Date(a.submitted_at));
});

// In Review Deliverables (submitted by team, awaiting any client interaction or internal review)
const inReviewDeliverables = computed(() => {
    return props.deliverables.filter(deliverable =>
        deliverable.status === 'pending_review' || deliverable.status === 'revisions_requested'
    ).sort((a, b) => new Date(b.submitted_at) - new Date(a.submitted_at));
});

// Approved Deliverables
const approvedDeliverables = computed(() => {
    return props.deliverables.filter(deliverable =>
        deliverable.status === 'approved'
    ).sort((a, b) => new Date(b.overall_approved_at || b.updated_at) - new Date(a.overall_approved_at || a.updated_at));
});

// Rejected Deliverables
const rejectedDeliverables = computed(() => {
    return props.deliverables.filter(deliverable =>
        deliverable.status === 'rejected'
    ).sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));
});

// All Deliverables (equivalent to "Past Posts" or general archive)
const allDeliverables = computed(() => {
    return [...props.deliverables].sort((a, b) => new Date(b.submitted_at) - new Date(a.submitted_at));
});

// --- Dynamic content based on active tab and search query ---
const currentTabDeliverables = computed(() => {
    let deliverables = [];
    switch (activeTab.value) {
        case 'action-required':
            deliverables = actionRequiredDeliverables.value;
            break;
        case 'in-review':
            deliverables = inReviewDeliverables.value;
            break;
        case 'approved':
            deliverables = approvedDeliverables.value;
            break;
        case 'rejected':
            deliverables = rejectedDeliverables.value;
            break;
        case 'all-deliverables':
            deliverables = allDeliverables.value;
            break;
        default:
            deliverables = [];
    }

    // Apply search query to the currently active tab's data
    if (approvalSearchQuery.value) {
        const query = approvalSearchQuery.value.toLowerCase();
        return deliverables.filter(d =>
            d.title.toLowerCase().includes(query) ||
            (d.description && d.description.toLowerCase().includes(query)) ||
            d.type.toLowerCase().includes(query.replace(' ', '_')) ||
            d.status.toLowerCase().includes(query.replace(' ', '_')) ||
            (d.team_member?.name && d.team_member.name.toLowerCase().includes(query))
        );
    }
    return deliverables;
});


// Function to trigger opening the deliverable viewer modal in the parent
const openDeliverableViewer = (deliverable) => {
    emits('open-deliverable-viewer', deliverable);
};

// Helper to format date
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

// Helper to get status class for badges
const getStatusClass = (status) => {
    switch (status.toLowerCase()) {
        case 'pending_review': return 'bg-yellow-100 text-yellow-800';
        case 'revisions_requested': return 'bg-orange-100 text-orange-800';
        case 'approved': return 'bg-green-100 text-green-800';
        case 'rejected': return 'bg-red-100 text-red-800';
        case 'completed': return 'bg-indigo-100 text-indigo-800'; // For general completed deliverables
        default: return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <div class="p-6 bg-white rounded-xl shadow-lg font-inter min-h-[calc(100vh-6rem)]">
        <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clipboard-check mr-2 w-6 h-6"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>
            Project Deliverables
        </h2>

        <!-- Tab Navigation -->
        <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 pb-2">
            <button @click="activeTab = 'action-required'"
                    :class="['px-5 py-2 rounded-lg font-semibold text-sm transition-colors duration-200',
                             activeTab === 'action-required' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">
                Action Required ({{ actionRequiredDeliverables.length }})
            </button>
<!--            <button @click="activeTab = 'in-review'"-->
<!--                    :class="['px-5 py-2 rounded-lg font-semibold text-sm transition-colors duration-200',-->
<!--                             activeTab === 'in-review' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">-->
<!--                In Review ({{ inReviewDeliverables.length }})-->
<!--            </button>-->
            <button @click="activeTab = 'approved'"
                    :class="['px-5 py-2 rounded-lg font-semibold text-sm transition-colors duration-200',
                             activeTab === 'approved' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">
                Approved ({{ approvedDeliverables.length }})
            </button>
            <button @click="activeTab = 'rejected'"
                    :class="['px-5 py-2 rounded-lg font-semibold text-sm transition-colors duration-200',
                             activeTab === 'rejected' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">
                Rejected ({{ rejectedDeliverables.length }})
            </button>
            <button @click="activeTab = 'all-deliverables'"
                    :class="['px-5 py-2 rounded-lg font-semibold text-sm transition-colors duration-200',
                             activeTab === 'all-deliverables' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">
                All Deliverables ({{ allDeliverables.length }})
            </button>
        </div>

        <!-- Approval Search Bar -->
        <div class="relative mb-6">
            <input
                type="text"
                v-model="approvalSearchQuery"
                placeholder="Search deliverables by title, type, or status..."
                class="w-full p-3 pl-10 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-200"
                aria-label="Search Deliverables"
            >
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search text-gray-400 w-5 h-5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
        </div>

        <!-- Conditional rendering based on filtered results -->
        <div v-if="currentTabDeliverables.length === 0 && !approvalSearchQuery" class="text-center text-gray-500 py-8">
            <p class="text-lg mb-2">No {{ activeTab.replace('-', ' ') }} deliverables found.</p>
            <p v-if="activeTab === 'action-required'" class="mt-2 text-sm">Great job keeping up with your project deliverables!</p>
            <p v-else-if="activeTab === 'in-review'" class="mt-2 text-sm">No deliverables are currently in review.</p>
            <p v-else-if="activeTab === 'approved'" class="mt-2 text-sm">No deliverables have been approved yet.</p>
            <p v-else-if="activeTab === 'rejected'" class="mt-2 text-sm">No deliverables have been rejected.</p>
            <p v-else-if="activeTab === 'all-deliverables'" class="mt-2 text-sm">No deliverables have been posted for this project yet.</p>
        </div>
        <div v-else-if="currentTabDeliverables.length === 0 && approvalSearchQuery" class="text-center text-gray-500 py-8">
            <p class="text-lg mb-2">No {{ activeTab.replace('-', ' ') }} deliverables match your search "{{ approvalSearchQuery }}".</p>
            <p>Try a different keyword or clear your search.</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="deliverable in currentTabDeliverables" :key="deliverable.id"
                 class="bg-gray-50 rounded-lg shadow-sm p-5 border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow duration-200">
                <div>
                    <div class="flex justify-between items-start mb-3">
                        <h3 class="text-lg font-bold text-gray-900 pr-2">{{ deliverable.title }}</h3>
                        <span :class="['px-3 py-1 rounded-full text-xs font-bold capitalize flex-shrink-0', getStatusClass(deliverable.status)]">
                            {{ deliverable.status.replace(/_/g, ' ') }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-700 mb-3 line-clamp-3">{{ deliverable.description || 'No description provided.' }}</p>
                </div>
                <div class="text-sm text-gray-600 mb-4">
                    <div class="flex items-center mb-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text mr-1 text-gray-500"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                        Type: <span class="font-medium text-gray-800 capitalize ml-1">{{ deliverable.type.replace(/_/g, ' ') }}</span>
                    </div>
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user mr-1 text-gray-500"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Submitted by: <span class="font-medium text-gray-800 ml-1">{{ deliverable.team_member?.name || 'Unknown' }}</span>
                        on {{ formatDate(deliverable.submitted_at) }}
                    </div>
                </div>
                <div class="mt-auto">
                    <button @click="openDeliverableViewer(deliverable)"
                            class="w-full bg-blue-600 text-white py-2.5 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-all duration-200 ease-in-out transform hover:scale-105 shadow-md flex items-center justify-center"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye mr-2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        Review Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}
/* Specific styling for search input to place icon inside */
.relative input[type="text"] {
    padding-left: 2.5rem; /* Adjust padding to make space for the icon */
}
</style>
