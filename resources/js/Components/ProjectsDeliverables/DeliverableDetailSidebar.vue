<script setup>
import { ref, watch, computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue'; // Assuming you have this
import { useForm } from '@inertiajs/vue3'; // For form submission
import { useFormatter } from '@/Composables/useFormatter'; // Import the useFormatter composable

const props = defineProps({
    projectId:  {
        type: Number,
        required: true
    },
    deliverableId: {
        type: Number,
        required: true,
    },
    // No need for projectUsers here, as team_member_id is auto-assigned
});

const emit = defineEmits(['close', 'deliverable-updated']);

const deliverable = ref(null);
const loadingDeliverable = ref(true);
const deliverableError = ref('');

// Use the formatter composable
const { formatDate, formatDateTime, timeAgo } = useFormatter();

// Form for adding new comments
const commentForm = useForm({
    comment_text: '',
    context: '', // Optional context for the comment
});

const fetchDeliverableDetails = async () => {
    loadingDeliverable.value = true;
    deliverableError.value = '';
    try {
        // Fetch deliverable with its client interactions and comments
        const response = await window.axios.get(route('projects.deliverables.show', {
            project: props.projectId,
            deliverable: props.deliverableId
        }));
        deliverable.value = response.data;
    } catch (error) {
        deliverableError.value = 'Failed to load deliverable details.';
        console.error('Error fetching deliverable details:', error);
    } finally {
        loadingDeliverable.value = false;
    }
};

watch(() => props.deliverableId, (newDeliverableId) => {
    if (newDeliverableId) {
        fetchDeliverableDetails();
    } else {
        deliverable.value = null; // Clear deliverable details if no deliverableId
    }
}, { immediate: true });

const isSubmittingComment = ref(false);

const addComment = async () => {
    if (!deliverable.value) return;

    isSubmittingComment.value = true;
    commentForm.errors = {}; // Clear previous errors

    try {
        const response = await window.axios.post(`/api/projects/${props.projectId}/deliverables/${props.deliverableId}/comments`, {
            comment_text: commentForm.comment_text,
            context: commentForm.context,
        });

        // On success:
        commentForm.comment_text = ''; // Reset comment text
        commentForm.context = '';     // Reset context
        fetchDeliverableDetails();    // Re-fetch deliverable to get updated comments
        emit('deliverable-updated');  // Notify parent of update

    } catch (error) {
        console.error('Error adding comment:', error);
        if (error.response && error.response.status === 422) {
            // Validation errors
            commentForm.errors = error.response.data.errors;
        } else {
            // General API error
            // You might want to show a global error message here
            console.error('Failed to add comment:', error.response?.data?.message || error.message);
        }
    } finally {
        isSubmittingComment.value = false;
    }
};

// Helper for status styling
const deliverableStatusClass = computed(() => {
    if (!deliverable.value) return '';
    return {
        'bg-yellow-100 text-yellow-800': deliverable.value.status === 'pending_review' || deliverable.value.status === 'revisions_requested',
        'bg-green-100 text-green-800': deliverable.value.status === 'approved',
        'bg-blue-100 text-blue-800': deliverable.value.status === 'completed',
        'bg-gray-100 text-gray-800': deliverable.value.status === 'for_information',
    };
});

// Helper for interaction status text
const getInteractionStatus = (interaction) => {
    let status = 'No Action Yet';
    let timestamp = '';

    if (interaction.approved_at) {
        status = 'Approved';
        timestamp = formatDateTime(interaction.approved_at);
    } else if (interaction.revisions_requested_at) {
        status = 'Revisions Requested';
        timestamp = formatDateTime(interaction.revisions_requested_at);
    } else if (interaction.rejected_at) {
        status = 'Rejected';
        timestamp = formatDateTime(interaction.rejected_at);
    } else if (interaction.read_at) {
        status = 'Read';
        timestamp = formatDateTime(interaction.read_at);
    }

    return { status, timestamp };
};

// Helper to determine comment creator type for display
const getCommentCreatorType = (comment) => {
    if (comment.creator_type === 'App\\Models\\Client') {
        return 'Client';
    } else if (comment.creator_type === 'App\\Models\\User') {
        return 'Team Member';
    }
    return 'Unknown';
};

</script>

<template>
    <div v-if="loadingDeliverable" class="text-center py-8 text-gray-500">
        Loading deliverable details...
    </div>
    <div v-else-if="deliverableError" class="text-center py-8 text-red-600">
        {{ deliverableError }}
    </div>
    <div v-else-if="deliverable" class="space-y-6">
        <!-- Deliverable Overview -->
        <div class="bg-gray-50 p-4 rounded-lg shadow-sm">
            <h4 class="text-xl font-bold text-gray-900 mb-2">{{ deliverable.title }}</h4>
            <div class="flex items-center space-x-2 text-sm text-gray-600 mb-4">
                <span class="px-2 py-1 rounded-full text-xs font-medium" :class="deliverableStatusClass">
                    {{ deliverable.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()) }}
                </span>
                <span class="text-indigo-600 font-medium">
                    | {{ deliverable.type.replace(/_/g, ' ') }} |
                </span>
                <span class="text-gray-700">
                    Submitted by: {{ deliverable.team_member?.name || 'Unknown' }}
                </span>
            </div>
            <p class="text-gray-700 leading-relaxed">{{ deliverable.description || 'No description provided.' }}</p>

            <div class="mt-4 space-y-2 text-sm">
                <div v-if="deliverable.content_url" class="text-blue-600 hover:underline">
                    <a :href="deliverable.content_url" target="_blank" rel="noopener noreferrer">View Content Link ({{ deliverable.mime_type || 'Unknown Type' }})</a>
                </div>
                <div v-if="deliverable.attachment_path" class="text-blue-600 hover:underline">
                    <a :href="deliverable.attachment_path" target="_blank" rel="noopener noreferrer">View Attachment</a>
                </div>
                <div :class="deliverable.is_visible_to_client ? 'text-green-600' : 'text-red-600'">
                    Visibility: {{ deliverable.is_visible_to_client ? 'Visible to Client' : 'Not Visible to Client' }}
                </div>
                <div v-if="deliverable.overall_approved_at" class="text-green-600 font-medium">
                    Overall Approved by Client on {{ formatDateTime(deliverable.overall_approved_at) }}
                </div>
            </div>
        </div>

        <!-- Client Interactions -->
        <div class="bg-white p-4 rounded-lg shadow-sm">
            <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Client Interactions</h5>
            <div v-if="deliverable.client_interactions && deliverable.client_interactions.length > 0" class="space-y-3">
                <div v-for="interaction in deliverable.client_interactions" :key="interaction.id" class="p-3 bg-gray-50 rounded-md">
                    <p class="font-semibold text-gray-800">
                        {{ interaction.client?.name || 'Unknown Client' }}
                    </p>
                    <p class="text-sm text-gray-700">
                        Status: <span class="font-medium">{{ getInteractionStatus(interaction).status }}</span>
                        <span v-if="getInteractionStatus(interaction).timestamp" class="text-xs text-gray-500 ml-2">
                            ({{ getInteractionStatus(interaction).timestamp }})
                        </span>
                    </p>
                    <p v-if="interaction.feedback_text" class="text-sm text-gray-600 mt-1">Feedback: "{{ interaction.feedback_text }}"</p>
                </div>
            </div>
            <div v-else class="text-gray-500 text-sm py-2">No client interactions recorded yet.</div>
        </div>

        <!-- Comments -->
        <div class="bg-white p-4 rounded-lg shadow-sm">
            <div class="flex justify-between items-center mb-4 border-b pb-2">
                <h5 class="text-lg font-semibold text-gray-800">Comments</h5>
                <PrimaryButton @click="addComment" :disabled="commentForm.processing" class="bg-indigo-600 hover:bg-indigo-700 transition-colors">
                    Add Comment
                </PrimaryButton>
            </div>
            <div class="space-y-3 mb-4">
                <div>
                    <InputLabel for="new-comment-text" value="New Comment" class="sr-only" />
                    <textarea
                        id="new-comment-text"
                        v-model="commentForm.comment_text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows="3"
                        placeholder="Type your comment here..."
                    ></textarea>
                    <InputError :message="commentForm.errors.comment_text" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="comment-context" value="Context (Optional, e.g., 'Paragraph 2', 'Image 1')" class="sr-only" />
                    <TextInput
                        id="comment-context"
                        v-model="commentForm.context"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Context (optional)"
                    />
                    <InputError :message="commentForm.errors.context" class="mt-2" />
                </div>
            </div>

            <div v-if="deliverable.comments && deliverable.comments.length > 0" class="space-y-3">
                <div v-for="comment in deliverable.comments" :key="comment.id" class="p-3 bg-gray-50 rounded-md">
                    <p class="text-sm text-gray-700">{{ comment.content }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        By {{ comment.creator_name || 'Unknown' }} ({{ getCommentCreatorType(comment) }})
                        <span v-if="comment.created_at">
                            {{ timeAgo(comment.created_at) }}
                        </span>
                        <span v-if="comment.context" class="ml-2 text-gray-400">({{ comment.context }})</span>
                    </p>
                </div>
            </div>
            <div v-else class="text-gray-500 text-sm py-2">No comments for this deliverable yet.</div>
        </div>
    </div>
    <div v-else class="text-center py-8 text-gray-500">
        Select a deliverable to view details.
    </div>
</template>
