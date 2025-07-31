<script setup>
import { ref, watch, inject, nextTick, computed } from 'vue';

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

const emits = defineEmits(['update:isOpen', 'deliverable-action-success']);

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
            emits('deliverable-action-success', props.deliverable.id, 'approve'); // Notify parent to re-fetch/update deliverable data
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
    } catch (error) {
        // Error already handled and shown by sendApiRequest
    }
};

const scrollToCommentsBottom = () => {
    if (commentsHistoryRef.value) {
        commentsHistoryRef.value.scrollTop = commentsHistoryRef.value.scrollHeight;
    }
};

// Computed property to determine how to display content
const getDisplayContent = computed(() => {
    if (!props.deliverable) return { type: 'none' };

    const url = props.deliverable.content_url;
    const text = props.deliverable.content_text;
    const deliverableType = props.deliverable.type;
    const mimeType = props.deliverable.mime_type;

    if (text) {
        return { type: 'text', content: text };
    }

    if (url) {
        // Handle Google Drive links
        if (url.includes('drive.google.com/file/d/')) {
            const fileIdMatch = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
            if (fileIdMatch && fileIdMatch[1]) {
                const fileId = fileIdMatch[1];
                if (mimeType && mimeType.includes('image')) {
                    return { type: 'image', src: `https://drive.google.com/uc?export=view&id=${fileId}` };
                } else {
                    return { type: 'iframe', src: `https://docs.google.com/file/d/${fileId}/preview` };
                }
            }
        }
        // Handle generic Google Docs/Sheets/Slides links
        else if (url.includes('docs.google.com')) {
            return { type: 'iframe', src: url.replace(/\/edit(\?usp=[a-zA-Z0-9]+)?/, '/preview').replace(/\/view(\?usp=[a-zA-Z0-9]+)?/, '/preview') };
        }
        // Handle direct PDF links
        else if (url.includes('.pdf') || (mimeType && mimeType.includes('pdf'))) {
            return { type: 'iframe', src: url };
        }
        // Handle direct image URLs
        else if (mimeType && mimeType.includes('image')) {
            return { type: 'image', src: url };
        }
        // Handle YouTube embeds
        else if (deliverableType === 'youtube' || url.includes('youtube.com') || url.includes('youtu.be')) {
            const videoIdMatch = url.match(/(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:youtube\.com|youtu\.be)\/(?:watch\?v=|embed\/|v\/|)([\w-]{11})(?:\S+)?/);
            if (videoIdMatch && videoIdMatch[1]) {
                return { type: 'iframe', src: `https://www.youtube.com/embed/${videoIdMatch[1]}` };
            }
        }
        // Deliverable types that are typically embeddable via iframe
        const embeddableDeliverableTypes = ['blog_post', 'report', 'contract_draft', 'proposal', 'social_media_post', 'design_mockup'];
        if (embeddableDeliverableTypes.includes(deliverableType)) {
            return { type: 'iframe', src: url };
        }

        // Fallback for external links that are not directly embeddable
        return { type: 'external_link', url: url };
    }

    return { type: 'none' }; // No content or unsupported
});

// Helper for formatting dates
const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

</script>

<template>
    <div v-if="isOpen && deliverable" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 p-4 font-inter">
        <div class="bg-white rounded-2xl shadow-xl w-full h-full max-w-7xl max-h-[95vh] flex flex-col relative">
            <!-- Header -->
            <div class="flex items-center justify-between p-5 border-b border-gray-200">
                <h3 class="text-2xl font-bold text-gray-900 truncate pr-10">Review: {{ deliverable?.title }}</h3>
                <button @click="closeModal"
                        class="text-gray-500 hover:text-gray-800 text-4xl leading-none transition-colors duration-200"
                        aria-label="Close"
                >&times;</button>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-hidden flex flex-col lg:flex-row">
                <!-- Deliverable Content Viewer (Left/Top) -->
                <div class="flex-1 p-6 bg-gray-50 overflow-auto flex items-center justify-center rounded-bl-2xl lg:rounded-bl-none lg:rounded-tl-2xl">
                    <div v-if="getDisplayContent.type === 'iframe'" class="w-full h-full flex items-center justify-center">
                        <iframe :src="getDisplayContent.src"
                                class="w-full h-full border-0 rounded-lg shadow-md"
                                allowfullscreen
                                frameborder="0"
                                allow="autoplay"
                                sandbox="allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-scripts allow-same-origin"
                        ></iframe>
                    </div>
                    <div v-else-if="getDisplayContent.type === 'image'" class="w-full h-full flex items-center justify-center">
                        <img :src="getDisplayContent.src"
                             :alt="deliverable.title"
                             class="max-w-full max-h-full object-contain rounded-lg shadow-md"
                             onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/000000?text=Image+Not+Found';"
                        />
                    </div>
                    <div v-else-if="getDisplayContent.type === 'text'" class="p-6 text-gray-700 bg-white rounded-lg shadow-md max-w-prose overflow-auto max-h-full">
                        <h4 class="font-bold text-xl mb-3 text-gray-800">Text Content:</h4>
                        <p class="whitespace-pre-wrap leading-relaxed">{{ getDisplayContent.content }}</p>
                    </div>
                    <div v-else-if="getDisplayContent.type === 'external_link'" class="text-center text-gray-600 p-8">
                        <p class="text-lg mb-4">This content cannot be embedded directly. Please open it in a new tab:</p>
                        <a :href="getDisplayContent.url" target="_blank" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition-colors duration-200">
                            Open in New Tab
                            <svg class="inline-block w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        </a>
                    </div>
                    <div v-else class="text-center text-gray-600 p-8">
                        <p class="text-lg">No preview available for this deliverable.</p>
                        <p v-if="deliverable?.content_url" class="mt-4">
                            <a :href="deliverable.content_url" target="_blank" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition-colors duration-200">
                                Try Opening in New Tab
                                <svg class="inline-block w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        </p>
                    </div>
                </div>

                <!-- Comments and Action Buttons (Right/Bottom) -->
                <div class="lg:w-96 p-6 border-l border-gray-200 flex flex-col bg-gray-100 rounded-br-2xl lg:rounded-bl-none">
                    <h4 class="text-xl font-semibold mb-4 text-gray-900">Your Feedback:</h4>
                    <textarea v-model="clientComment"
                              placeholder="Leave your comments or feedback here..."
                              rows="5"
                              class="w-full p-3 border border-gray-300 rounded-lg mb-4 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200 resize-y shadow-sm"
                    ></textarea>

                    <div class="space-y-3 mb-6">
                        <button @click="handleApprove"
                                class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-700 transition-all duration-200 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:scale-100"
                                :disabled="isSubmitting || deliverable?.client_interaction?.approved_at || deliverable?.client_interaction?.rejected_at || deliverable?.status === 'approved'"
                        >
                            <span v-if="isSubmitting && !deliverable?.client_interaction?.approved_at">Approving...</span>
                            <span v-else>Approve Deliverable</span>
                        </button>
                        <button @click="handleRequestRevisions"
                                class="w-full bg-red-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-red-700 transition-all duration-200 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:scale-100"
                                :disabled="isSubmitting || !clientComment.trim() || deliverable?.client_interaction?.approved_at || deliverable?.client_interaction?.rejected_at || deliverable?.status === 'approved'"
                        >
                            <span v-if="isSubmitting && !deliverable?.client_interaction?.revisions_requested_at">Requesting Revisions...</span>
                            <span v-else>Request Revisions</span>
                        </button>
                        <button @click="handleAddComment"
                                class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-all duration-200 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:scale-100"
                                :disabled="isSubmitting || !clientComment.trim()"
                        >
                            <span v-if="isSubmitting">Adding Comment...</span>
                            <span v-else>Add General Comment</span>
                        </button>
                    </div>

                    <h4 class="text-xl font-semibold mb-4 text-gray-900 mt-auto pt-4 border-t border-gray-200">Comments History:</h4>
                    <div ref="commentsHistoryRef" class="bg-white p-4 rounded-lg flex-1 overflow-y-auto text-sm text-gray-700 shadow-inner">
                        <!-- Client's last action feedback -->
                        <div v-if="deliverable?.client_interaction?.feedback_text" class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <p class="font-bold text-blue-800 mb-1">Your Last Feedback:</p>
                            <p class="mb-1">{{ deliverable.client_interaction.feedback_text }}</p>
                            <p class="text-xs text-gray-500">
                                ({{ formatDateTime(deliverable.client_interaction.updated_at) }})
                            </p>
                        </div>

                        <!-- All general comments -->
                        <div v-if="commentsList && commentsList.length > 0" class="space-y-3">
                            <div v-for="comment in commentsList" :key="comment.id" class="p-3 bg-gray-50 rounded-lg shadow-sm border border-gray-200">
                                <p class="font-semibold text-indigo-700">{{ comment.creator_name || 'Client' }}</p>
                                <p class="mt-1">{{ comment.content || comment.comment_text }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ formatDateTime(comment.created_at) }}
                                    <span v-if="comment.context" class="ml-2 text-gray-400 italic">({{ comment.context }})</span>
                                </p>
                            </div>
                        </div>
                        <p v-else-if="!deliverable?.client_interaction?.feedback_text && commentsList.length === 0" class="text-center text-gray-500 py-4">
                            No feedback or comments yet for this deliverable.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}
/* Ensure iframe takes full height within its container */
iframe, img {
    height: 100%;
    width: 100%;
}
</style>
