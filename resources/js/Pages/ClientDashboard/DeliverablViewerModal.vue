<script setup>
import { ref, watch, inject } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    deliverable: {
        type: Object,
        default: null,
    },
    initialAuthToken: { // Needed for API calls within the modal
        type: String,
        required: true
    },
    projectId: { // Needed for context in API calls if not part of deliverable object
        type: [String, Number],
        required: true
    }
});

const emits = defineEmits(['update:isOpen', 'deliverable-action-success', 'comment-added']);

const clientComment = ref('');
const apiError = ref(null); // Local error state for modal actions

// Inject the showModal from ClientDashboard for showing alerts/confirms
const { showModal } = inject('modalService');

// Watch for changes in the deliverable prop to reset comment or fetch comments
watch(() => props.deliverable, (newVal) => {
    if (newVal) {
        clientComment.value = newVal.client_interaction?.feedback_text || '';
        markDeliverableAsRead(); // Mark as read when opened
        // TODO: Fetch comments specific to this deliverable here if you had a dedicated comments API
        // For now, we're just displaying the interaction feedback.
    }
}, { immediate: true });


const closeModal = () => {
    emits('update:isOpen', false);
    clientComment.value = '';
    apiError.value = null; // Clear error on close
};

const sendApiRequest = async (endpoint, method, payload = null) => {
    apiError.value = null;
    try {
        const response = await fetch(`/api/client-api/deliverables/${props.deliverable.id}/${endpoint}`, {
            method: method,
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: payload ? JSON.stringify(payload) : null,
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'API request failed.');
        }
        return data;
    } catch (err) {
        console.error(`Error during API call to ${endpoint}:`, err);
        apiError.value = err.message || 'An unexpected error occurred.';
        showModal('Error', apiError.value, 'alert'); // Show error to user
        throw err; // Re-throw to be caught by specific action handlers
    }
};

const markDeliverableAsRead = async () => {
    if (props.deliverable && (!props.deliverable.client_interaction || !props.deliverable.client_interaction.read_at)) {
        try {
            await sendApiRequest('mark-read', 'POST');
            // Notify parent that the deliverable's read status might have changed
            emits('deliverable-action-success');
        } catch (error) {
            // Error already shown by sendApiRequest, just log
        }
    }
};

const handleApprove = async () => {
    showModal('Confirm Approval', 'Are you sure you want to approve this deliverable? This action cannot be undone easily.', 'confirm', async () => {
        try {
            await sendApiRequest('approve', 'POST', { feedback_text: clientComment.value });
            showModal('Success', 'Deliverable approved successfully!', 'alert');
            emits('deliverable-action-success'); // Notify parent to re-fetch/update deliverable data
            closeModal();
        } catch (error) {
            // Error already handled and shown by sendApiRequest
        }
    });
};

const handleRequestRevisions = async () => {
    if (!clientComment.value.trim()) {
        showModal('Feedback Required', 'Please provide detailed feedback for requesting revisions.', 'alert');
        return;
    }

    showModal('Confirm Revisions', 'Are you sure you want to request revisions for this deliverable? Your feedback will be sent to the team.', 'confirm', async () => {
        try {
            await sendApiRequest('request-revisions', 'POST', { feedback_text: clientComment.value });
            showModal('Success', 'Revision request sent successfully!', 'alert');
            emits('deliverable-action-success'); // Notify parent
            closeModal();
        } catch (error) {
            // Error already handled and shown by sendApiRequest
        }
    });
};

const handleAddComment = async () => {
    if (!clientComment.value.trim()) {
        showModal('Comment Required', 'Please type a comment before submitting.', 'alert');
        return;
    }
    try {
        const data = await sendApiRequest('comments', 'POST', { comment_text: clientComment.value });
        showModal('Success', 'Comment added successfully!', 'alert');
        clientComment.value = ''; // Clear comment box after submission
        emits('comment-added', data.comment); // Optionally emit the new comment
        // Optionally re-fetch deliverable or its comments to show the new one
    } catch (error) {
        // Error already handled and shown by sendApiRequest
    }
};


const getEmbedUrl = (url, type) => {
    if (!url) return null;

    if (type === 'google_doc' || type === 'report' || url.includes('docs.google.com') || url.includes('.pdf')) {
        if (url.includes('docs.google.com')) {
            // Attempt to convert Google Docs share link to embeddable preview link
            // 'usp=drivesdk' or 'usp=sharing' or 'view' can often be replaced by 'preview'
            return url.replace(/\/edit(\?usp=[a-zA-Z0-9]+)?/, '/preview').replace(/\/view(\?usp=[a-zA-Z0-9]+)?/, '/preview');
        }
        // For direct PDFs, they are often embeddable
        return url;
    }
    // For other types like social_media_post (image), we'll handle with <img> tag
    return url;
};
</script>

<template>
    <div v-if="isOpen && deliverable" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full h-full max-w-6xl max-h-[90vh] flex flex-col relative">
            <!-- Header -->
            <div class="flex justify-between items-center p-4 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 truncate pr-10">Review: {{ deliverable?.title }}</h3>
                <button @click="closeModal"
                        class="text-gray-500 hover:text-gray-800 text-3xl transition-colors leading-none"
                        aria-label="Close"
                >&times;</button>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-hidden flex flex-col lg:flex-row">
                <!-- Deliverable Content Viewer (Left/Top) -->
                <div class="flex-1 p-4 bg-gray-50 overflow-auto flex items-center justify-center">
                    <div v-if="deliverable.content_url" class="w-full h-full flex items-center justify-center">
                        <img v-if="deliverable.type === 'social_media_post' || deliverable.type === 'design_mockup'"
                             :src="deliverable.content_url"
                             :alt="deliverable.title"
                             class="max-w-full max-h-full object-contain rounded-lg shadow-md"
                             onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/000000?text=Image+Not+Found';"
                        />
                        <iframe v-else-if="['blog_post', 'report', 'contract_draft', 'proposal'].includes(deliverable.type) || deliverable.content_url.includes('docs.google.com') || deliverable.content_url.includes('.pdf')"
                                :src="getEmbedUrl(deliverable.content_url, deliverable.type)"
                                class="w-full h-full border-0 rounded-lg shadow-md"
                                allowfullscreen
                                frameborder="0"
                                allow="autoplay"
                                sandbox="allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-scripts allow-same-origin"
                        ></iframe>
                        <div v-else class="text-center text-gray-600 p-8">
                            <p class="text-lg mb-2">Unsupported content type or unable to embed:</p>
                            <a :href="deliverable.content_url" target="_blank" class="text-blue-600 hover:underline font-semibold">
                                Open in New Tab
                                <svg class="inline-block w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        </div>
                    </div>
                    <div v-else-if="deliverable.content_text" class="p-4 text-gray-700 bg-white rounded-lg shadow-md max-w-prose overflow-auto max-h-full">
                        <h4 class="font-bold text-lg mb-2">Text Content:</h4>
                        <p class="whitespace-pre-wrap">{{ deliverable.content_text }}</p>
                    </div>
                    <div v-else class="text-center text-gray-600 p-8">
                        <p>No preview available for this deliverable.</p>
                    </div>
                </div>

                <!-- Comments and Action Buttons (Right/Bottom) -->
                <div class="lg:w-96 p-4 border-l border-gray-200 flex flex-col">
                    <h4 class="text-lg font-semibold mb-3 text-gray-800">Your Feedback:</h4>
                    <textarea v-model="clientComment"
                              placeholder="Leave your comments or reasons for revision/rejection here..."
                              rows="4"
                              class="w-full p-2 border border-gray-300 rounded-lg mb-4 focus:ring-blue-500 focus:border-blue-500 resize-y"
                    ></textarea>

                    <div class="space-y-3 mb-4">
                        <button @click="handleApprove"
                                class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="deliverable?.client_interaction?.approved_at || deliverable?.client_interaction?.rejected_at || deliverable?.status === 'approved'"
                        >
                            Approve
                        </button>
                        <button @click="handleRequestRevisions"
                                class="w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!clientComment.trim() || deliverable?.client_interaction?.approved_at || deliverable?.client_interaction?.rejected_at || deliverable?.status === 'approved'"
                        >
                            Request Revisions
                        </button>
                    </div>

                    <h4 class="text-lg font-semibold mb-3 text-gray-800 mt-auto">Comments History:</h4>
                    <div class="bg-gray-100 p-3 rounded-lg flex-1 overflow-y-auto text-sm text-gray-600">
                        <div v-if="deliverable && deliverable.client_interaction && deliverable.client_interaction.feedback_text">
                            <p class="font-bold text-gray-800">Your Last Action:</p>
                            <p class="mb-2">{{ deliverable.client_interaction.feedback_text }}</p>
                            <p class="text-xs text-gray-500">
                                ({{ new Date(deliverable.client_interaction.updated_at).toLocaleString() }})
                            </p>
                        </div>
                        <!-- Placeholder for other comments from DeliverableComment model -->
                        <div class="mt-4 pt-4 border-t border-gray-200" v-if="deliverable.comments && deliverable.comments.length > 0">
                            <p class="font-bold text-gray-800 mb-2">All Comments:</p>
                            <div v-for="comment in deliverable.comments" :key="comment.id" class="mb-3 p-2 bg-white rounded-lg shadow-sm">
                                <p class="font-semibold text-blue-700">{{ comment.client?.name || 'Client' }}</p>
                                <p>{{ comment.comment_text }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ new Date(comment.created_at).toLocaleString() }}
                                    <span v-if="comment.context" class="ml-2 text-gray-400">({{ comment.context }})</span>
                                </p>
                            </div>
                        </div>
                        <p v-else-if="!deliverable.client_interaction || !deliverable.client_interaction.feedback_text" class="text-center text-gray-500">
                            No feedback or comments yet for this deliverable.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Ensure iframe takes full height within its container */
iframe {
    height: 100%;
}
</style>
