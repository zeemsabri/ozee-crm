<script setup>
import { ref, watch, inject, nextTick } from 'vue';

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

const emits = defineEmits(['update:isOpen', 'deliverable-action-success']); // Removed 'comment-added' as comments are now handled internally or by a full refresh

const clientComment = ref('');
const apiError = ref(null); // Local error state for modal actions
const isSubmitting = ref(false); // New: to manage loading state for buttons
const commentsList = ref([]); // New: Local ref to manage comments displayed in the UI
const commentsHistoryRef = ref(null); // New: Ref for the comments history div to enable scrolling

// Inject the showModal from ClientDashboard for showing alerts/confirms
const { showModal } = inject('modalService');

// Watch for changes in the deliverable prop to reset comment and initialize commentsList
watch(() => props.deliverable, (newVal) => {
    if (newVal) {
        clientComment.value = newVal.client_interaction?.feedback_text || '';
        commentsList.value = [...(newVal.comments || [])]; // Initialize local comments list
    }
}, { immediate: true }); // immediate: true ensures this runs on initial load

// Watch for isOpen to mark as read and scroll to comments
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.deliverable) {
        markDeliverableAsRead();
        nextTick(() => { // Ensure DOM is updated before trying to scroll
            scrollToCommentsBottom();
        });
    } else if (!newVal) {
        // Reset states when modal closes
        clientComment.value = '';
        apiError.value = null;
        isSubmitting.value = false;
        commentsList.value = [];
    }
});

// Watch commentsList to auto-scroll to bottom when new comments are added
watch(commentsList, () => {
    nextTick(() => {
        scrollToCommentsBottom();
    });
}, { deep: true }); // Deep watch is acceptable here as comments list is typically small and only appended


const closeModal = () => {
    emits('update:isOpen', false);
};

const sendApiRequest = async (endpoint, method, payload = null) => {
    apiError.value = null;
    isSubmitting.value = true; // Set submitting state
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
            // Include validation errors if available
            const errorMessage = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'API request failed.');
            throw new Error(errorMessage);
        }
        return data;
    } catch (err) {
        console.error(`Error during API call to ${endpoint}:`, err);
        apiError.value = err.message || 'An unexpected error occurred.';
        showModal('Error', apiError.value, 'alert'); // Show error to user
        throw err; // Re-throw to be caught by specific action handlers
    } finally {
        isSubmitting.value = false; // Clear submitting state
    }
};

const markDeliverableAsRead = async () => {
    // Only mark as read if it hasn't been read by this client yet, or if interaction isn't loaded
    if (props.deliverable && (!props.deliverable.client_interaction || !props.deliverable.client_interaction.read_at)) {
        try {
            await sendApiRequest('mark-read', 'POST');
            // Notify parent that the deliverable's read status might have changed
            emits('deliverable-action-success');
        } catch (error) {
            console.error('Error marking deliverable as read:', error);
            // Don't prevent modal from opening on this error
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
        commentsList.value.push(data.comment); // Add new comment to local list
        clientComment.value = ''; // Clear comment box after submission
        // No need to emit 'comment-added' to parent if commentsList is updated locally
    } catch (error) {
        // Error already handled and shown by sendApiRequest
    }
};

const scrollToCommentsBottom = () => {
    if (commentsHistoryRef.value) {
        commentsHistoryRef.value.scrollTop = commentsHistoryRef.value.scrollHeight;
    }
};


const getEmbedUrl = (url, type) => {
    if (!url) return null;

    // Handle Google Drive image links for direct embedding
    if (url.includes('drive.google.com/file/d/')) {
        const fileIdMatch = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
        if (fileIdMatch && fileIdMatch[1]) {
            const fileId = fileIdMatch[1];
            // This is a common pattern for directly embedding Google Drive images
            return `https://drive.google.com/uc?export=view&id=${fileId}`;
        }
    }

    // Handle Google Docs/Sheets/Slides for embedding
    if (type === 'google_doc' || type === 'report' || url.includes('docs.google.com') || url.includes('.pdf')) {
        if (url.includes('docs.google.com')) {
            // Attempt to convert Google Docs share link to embeddable preview link
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
            <!--Header--><div class="flex justify-between items-center p-4 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 truncate pr-10">Review: {{ deliverable?.title }}</h3>
            <button @click="closeModal"
                    class="text-gray-500 hover:text-gray-800 text-3xl transition-colors leading-none"
                    aria-label="Close"
            >&times;</button>
        </div>

            <!--Content Area--><div class="flex-1 overflow-hidden flex flex-col lg:flex-row">
            <!--Deliverable Content Viewer (Left/Top)--><div class="flex-1 p-4 bg-gray-50 overflow-auto flex items-center justify-center">
            <div v-if="deliverable.content_url" class="w-full h-full flex items-center justify-center">
<!--                <img v-if="deliverable.type === 'social_media_post' || deliverable.type === 'design_mockup'"-->
<!--                     :src="getEmbedUrl(deliverable.content_url, deliverable.type)"-->
<!--                     :alt="deliverable.title"-->
<!--                     class="max-w-full max-h-full object-contain rounded-lg shadow-md"-->
<!--                     onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/000000?text=Image+Not+Found';"-->
<!--                />-->

                <iframe v-if="['blog_post', 'report', 'contract_draft', 'proposal', 'social_media_post', 'design_mockup'].includes(deliverable.type) || deliverable.content_url.includes('docs.google.com') || deliverable.content_url.includes('.pdf')"
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

            <!--Comments and Action Buttons (Right/Bottom)--><div class="lg:w-96 p-4 border-l border-gray-200 flex flex-col">
            <h4 class="text-lg font-semibold mb-3 text-gray-800">Your Feedback / General Comment:</h4>
            <textarea v-model="clientComment"
                      placeholder="Leave your comments here..."
                      rows="4"
                      class="w-full p-2 border border-gray-300 rounded-lg mb-4 focus:ring-blue-500 focus:border-blue-500 resize-y"
            ></textarea>

            <div class="space-y-3 mb-4">
                <button @click="handleApprove"
                        class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="isSubmitting || deliverable?.client_interaction?.approved_at || deliverable?.client_interaction?.rejected_at || deliverable?.status === 'approved'"
                >
                    <span v-if="isSubmitting && !deliverable?.client_interaction?.approved_at">Approving...</span>
                    <span v-else>Approve</span>
                </button>
                <button @click="handleRequestRevisions"
                        class="w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="isSubmitting || !clientComment.trim() || deliverable?.client_interaction?.approved_at || deliverable?.client_interaction?.rejected_at || deliverable?.status === 'approved'"
                >
                    <span v-if="isSubmitting && !deliverable?.client_interaction?.revisions_requested_at">Requesting Revisions...</span>
                    <span v-else>Request Revisions</span>
                </button>
                <button @click="handleAddComment"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="isSubmitting || !clientComment.trim()"
                >
                    <span v-if="isSubmitting">Adding Comment...</span>
                    <span v-else>Add Comment</span>
                </button>
            </div>

            <h4 class="text-lg font-semibold mb-3 text-gray-800 mt-auto">Comments History:</h4>
            <div ref="commentsHistoryRef" class="bg-gray-100 p-3 rounded-lg flex-1 overflow-y-auto text-sm text-gray-600">
                <!--Client's last action feedback--><div v-if="deliverable && deliverable.client_interaction && deliverable.client_interaction.feedback_text" class="mb-3">
                <p class="font-bold text-gray-800">Your Last Action Feedback:</p>
                <p class="mb-1">{{ deliverable.client_interaction.feedback_text }}</p>
                <p class="text-xs text-gray-500">
                    ({{ new Date(deliverable.client_interaction.updated_at).toLocaleString() }})
                </p>
            </div>
                <!--All general comments--><div class="mt-4 pt-4 border-t border-gray-200" v-if="commentsList && commentsList.length > 0">
                <p class="font-bold text-gray-800 mb-2">All Client Comments:</p>
                <div v-for="comment in commentsList" :key="comment.id" class="mb-3 p-2 bg-white rounded-lg shadow-sm">
                    <p class="font-semibold text-blue-700">{{ comment.client?.name || 'Client' }}</p>
                    <p>{{ comment.comment_text }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ new Date(comment.created_at).toLocaleString() }}
                        <span v-if="comment.context" class="ml-2 text-gray-400">({{ comment.context }})</span>
                    </p>
                </div>
            </div>
                <p v-else-if="!deliverable?.client_interaction?.feedback_text && commentsList.length === 0" class="text-center text-gray-500">
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
