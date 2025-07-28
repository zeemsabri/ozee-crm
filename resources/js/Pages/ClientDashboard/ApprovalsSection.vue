<script setup>
import { defineProps, defineEmits, computed, inject } from 'vue';

const props = defineProps({
    deliverables: {
        type: Array,
        default: () => []
    },
    initialAuthToken: { // Needed for passing to modal if opened from here
        type: String,
        required: true
    },
    projectId: { // Needed for passing to modal if opened from here
        type: [String, Number],
        required: true
    }
});

const emits = defineEmits(['open-deliverable-viewer']); // Emits an event to parent to open the deliverable viewer modal

// Inject the showModal from ClientDashboard for showing alerts/confirms if needed
const { showModal } = inject('modalService');

// Computed property to filter deliverables that require client action
const actionRequiredDeliverables = computed(() => {

    return props.deliverables.filter(deliverable => {
        // A deliverable requires action if its overall status is pending_review
        // OR if it's been sent back for revisions and this specific client requested them.
        const isPendingReview = deliverable.status === 'pending_review';
        const isRevisionsRequested = deliverable.status === 'revisions_requested';

        // Check if the current client has interacted with it
        const clientInteraction = deliverable.client_interaction;

        if (isPendingReview) {
            // If pending review, and client hasn't approved/rejected/requested revisions yet
            return !clientInteraction || (!clientInteraction.approved_at && !clientInteraction.rejected_at && !clientInteraction.revisions_requested_at);
        }

        if (isRevisionsRequested) {
            // If revisions were requested, it means it's back for review.
            // You might want to refine this: is it *this* client who requested revisions?
            // For now, if the deliverable is 'revisions_requested' status, it needs action from *any* client
            // who hasn't approved it yet, or specifically the one who requested it.
            // A more robust check might involve comparing deliverable.last_revision_requester_client_id
            // against the current client's ID, which would require more data from the API.
            // For simplicity, we'll assume any client who hasn't approved should review it again.
            return !clientInteraction || (!clientInteraction.approved_at && !clientInteraction.rejected_at);
        }

        return false; // Does not require action
    });
});


// Function to trigger opening the deliverable viewer modal in the parent
const openDeliverableViewer = (deliverable) => {
    emits('open-deliverable-viewer', deliverable);
};
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md min-h-[calc(100vh-6rem)]">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Approvals Needed</h2>

        <div v-if="actionRequiredDeliverables.length === 0" class="text-center text-gray-500 py-8">
            <p>No approvals currently require your attention.</p>
            <p class="mt-2 text-sm">Great job keeping up with your project deliverables!</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="deliverable in actionRequiredDeliverables" :key="deliverable.id"
                 class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition-shadow duration-200 flex flex-col">
                <div class="p-5 flex-grow">
                    <div class="flex justify-between items-start mb-3">
                        <h3 class="text-lg font-bold text-gray-800">{{ deliverable.title }}</h3>
                        <span :class="{
                            'px-3 py-1 rounded-full text-xs font-semibold': true,
                            'bg-yellow-200 text-yellow-800': deliverable.status === 'pending_review' || deliverable.status === 'revisions_requested',
                            'bg-green-200 text-green-800': deliverable.status === 'approved', // Though these won't show in this section
                        }">
                            {{ deliverable.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()) }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 mb-2 line-clamp-3">{{ deliverable.description || 'No description provided.' }}</p>
                    <div class="text-xs text-gray-500 mb-1">
                        Type: <span class="font-medium text-gray-700 capitalize">{{ deliverable.type.replace(/_/g, ' ') }}</span>
                    </div>
                    <div class="text-xs text-gray-500 mb-3">
                        Submitted by: <span class="font-medium text-gray-700">{{ deliverable.team_member?.name || 'Unknown' }}</span>
                        on {{ new Date(deliverable.submitted_at).toLocaleDateString() }}
                    </div>
                </div>
                <div class="p-5 border-t border-gray-200 bg-gray-50 flex justify-end">
                    <button @click="openDeliverableViewer(deliverable)"
                            class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors shadow-md text-sm font-semibold"
                    >
                        Review Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed, or rely on Tailwind CSS */
</style>
