<script setup>
import { defineProps, defineEmits, computed, watch, ref } from 'vue';
import { getEmailDetails as getEmailDetailsApi, fetchAttachments as fetchAttachmentsApi, deleteEmail as deleteEmailApi } from '@/Services/api-service.js';
import EmailAttachmentsList from '@/Pages/Emails/Inbox/Components/EmailAttachmentsList.vue';
import TaskCreationForm from '@/Pages/Emails/Inbox/Components/TaskCreationForm.vue';
import { usePermissions } from '@/Directives/permissions.js';

const props = defineProps({
    email: Object,
    canApproveEmails: Boolean,
});

const emit = defineEmits(['edit', 'reject', 'open-bulk-tasks', 'deleted']);

const attachments = ref([]);
const attachmentsLoading = ref(false);
const attachmentsError = ref(null);
const showTaskForm = ref(false);
const localEmail = ref(null);
const loadingEmailDetails = ref(false);
const users = ref([]);
const usersLoading = ref(false);
const usersError = ref(null);

const isApprovalPending = computed(() => {
    return localEmail.value?.status === 'pending_approval' || localEmail.value?.status === 'pending_approval_received';
});

const isOutgoing = computed(() => localEmail.value?.type === 'sent');

const { canDo } = usePermissions();
const canDeleteEmails = computed(() => canDo('delete_emails').value);

const showDeleteDialog = ref(false);
const deleteLoading = ref(false);
const deleteError = ref(null);

const deleteOptions = ref({
    delete_gmail: true,
    delete_local: true,
});

const openDeleteDialog = () => {
    deleteError.value = null;
    deleteOptions.value = { delete_gmail: true, delete_local: true };
    showDeleteDialog.value = true;
};

const performDelete = async () => {
    if (!props.email?.id) return;
    deleteLoading.value = true;
    deleteError.value = null;
    try {
        await deleteEmailApi(props.email.id, {
            delete_gmail: deleteOptions.value.delete_gmail,
            delete_local: deleteOptions.value.delete_local,
        });
        showDeleteDialog.value = false;
        emit('deleted');
    } catch (e) {
        deleteError.value = e?.response?.data?.message || 'Failed to delete email.';
    } finally {
        deleteLoading.value = false;
    }
};

const approveButtonText = computed(() => {
    return isOutgoing.value ? 'Approve & Send' : 'Approve';
});

const fetchEmailDetails = async () => {
    if (!props.email || !props.email.id) {
        localEmail.value = null;
        return;
    }

    loadingEmailDetails.value = true;
    localEmail.value = null;

    try {
        const response = await getEmailDetailsApi(props.email.id);
        localEmail.value = response;
        if (localEmail.value && localEmail.value.id) {
            fetchAttachments();
        }
    } catch (error) {
        console.error('Failed to fetch email details:', error);
        localEmail.value = null;
    } finally {
        loadingEmailDetails.value = false;
    }
};

const fetchAttachments = async () => {
    if (!localEmail.value || !localEmail.value.id) {
        attachments.value = [];
        return;
    }

    attachmentsLoading.value = true;
    attachmentsError.value = null;

    try {
        const response = await fetchAttachmentsApi(localEmail.value.id);
        attachments.value = response;
    } catch (error) {
        console.error('Failed to fetch attachments:', error);
        attachmentsError.value = 'Failed to load attachments.';
    } finally {
        attachmentsLoading.value = false;
    }
};

const fetchUsers = async (projectId) => {
    if (!projectId) {
        users.value = [];
        return;
    }
    usersLoading.value = true;
    usersError.value = null;
    try {
        const { data } = await axios.get(`/api/projects/${projectId}/sections/users`, { params: { type: 'users' } });
        const list = Array.isArray(data?.users) ? data.users : (Array.isArray(data) ? data : (data?.project_users || []));
        users.value = list.map(u => ({ id: u.id, name: u.name }));
    } catch (e) {
        usersError.value = 'Failed to load users';
        console.error(e);
    } finally {
        usersLoading.value = false;
    }
};

const getSanitizedBody = computed(() => {
    if (!localEmail.value || !localEmail.value.body_html) {
        return localEmail.value?.body || '';
    }

    let sanitizedBody = localEmail.value.body_html;

    const cidRegex = /src="cid:(.*?)"/g;
    sanitizedBody = sanitizedBody.replace(cidRegex, (match, cid) => {
        const attachment = attachments.value.find(att => att.cid === cid);
        return attachment ? `src="${attachment.path_url}"` : 'src=""';
    });

    const doc = new DOMParser().parseFromString(sanitizedBody, 'text/html');
    return doc.body.innerHTML;
});

const getRecipientEmail = computed(() => {

    if (!props.email || !props.email.sender?.name) {
        return 'Unknown';
    }
    if(props.email.type === 'sent') {
        return props.email.recipient_email;
    }
});

const handleTasksSubmitted = () => {
    showTaskForm.value = false;
};

const toggleTaskForm = () => {
    showTaskForm.value = !showTaskForm.value;
    if (showTaskForm.value && localEmail.value?.conversation?.project?.id) {
        fetchUsers(localEmail.value.conversation.project.id);
    }
};

watch(() => props.email, (newEmail) => {
    if (newEmail) {
        fetchEmailDetails();
        showTaskForm.value = false;
    }
}, { immediate: true });

watch(localEmail, (newLocalEmail) => {
    if (newLocalEmail && newLocalEmail.id) {
        fetchAttachments();
    }
});
</script>

<template>
    <div v-if="!email" class="flex items-center justify-center h-full">
        <p class="text-gray-500">Select an email to view its details.</p>
    </div>
    <div v-else-if="loadingEmailDetails" class="flex items-center justify-center h-full">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-500"></div>
    </div>
    <div v-else-if="localEmail" class="space-y-4 p-4">
        <!-- Email Header -->
        <div class="border-b pb-4">
            <h2 class="text-xl font-semibold text-gray-800">{{ localEmail.subject }}</h2>
            <div class="text-sm text-gray-600 mt-1">
                From: {{ props.email?.sender?.name || 'Unknown' }}
            </div>
            <div v-if="props.email.type === 'sent'" class="text-sm text-gray-600 mt-1">
                To: {{ getRecipientEmail }}
            </div>
            <div class="text-sm text-gray-600 mt-1">
                Date: {{ new Date(props.email?.created_at).toLocaleString() }}
            </div>
        </div>

        <!-- Attachment and Create Tasks Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pt-4">
            <div class="w-full sm:w-auto">
                <div v-if="attachmentsLoading" class="text-gray-500">Loading attachments...</div>
                <div v-else-if="attachmentsError" class="text-red-500">{{ attachmentsError }}</div>
                <EmailAttachmentsList v-else :attachments="attachments" />
            </div>
            <div class="mt-4 sm:mt-0">
                <button
                    @click="toggleTaskForm"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    Create Tasks
                </button>
            </div>
        </div>

        <hr class="my-4"></hr>

        <div v-if="showTaskForm" class="mt-6 border-t border-gray-200 pt-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Create Tasks</h3>
            <TaskCreationForm
                :email-id="localEmail.id"
                :project-id="localEmail.conversation?.project?.id"
                :users="users"
                :users-loading="usersLoading"
                :users-error="usersError"
                @tasks-submitted="handleTasksSubmitted"
            />
        </div>

        <!-- Main Email Body -->
        <div class="prose max-w-none">
            <div v-html="getSanitizedBody"></div>
        </div>

        <!-- Rejection Reason -->
        <div v-if="localEmail.rejection_reason">
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p class="font-bold">Rejection Reason</p>
                <p>{{ localEmail.rejection_reason }}</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center pt-4">
            <div>
                <button
                    v-if="canDeleteEmails"
                    @click="openDeleteDialog"
                    class="inline-flex items-center px-3 py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                >
                    Delete
                </button>
            </div>
            <div v-if="isApprovalPending && canApproveEmails" class="flex space-x-2">
                <button
                    @click="$emit('edit', props.email)"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    {{ approveButtonText }}
                </button>
                <button
                    @click="$emit('reject', props.email)"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                >
                    Reject
                </button>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Delete Email</h3>
                <p class="text-sm text-gray-600 mb-4">Choose what to delete:</p>

                <div class="space-y-3 mb-4">
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" v-model="deleteOptions.delete_local" />
                        <span class="text-sm text-gray-700">Delete local copy</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" v-model="deleteOptions.delete_gmail" />
                        <span class="text-sm text-gray-700">Delete Gmail copy</span>
                    </label>
                </div>

                <div v-if="deleteError" class="text-sm text-red-600 mb-3">{{ deleteError }}</div>

                <div class="flex justify-end space-x-2">
                    <button @click="showDeleteDialog = false" class="px-3 py-2 rounded-md text-sm border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                    <button :disabled="deleteLoading || (!deleteOptions.delete_local && !deleteOptions.delete_gmail)"
                            @click="performDelete"
                            class="px-3 py-2 rounded-md text-sm text-white bg-red-600 hover:bg-red-700 disabled:opacity-50">
                        {{ deleteLoading ? 'Deleting...' : 'Confirm Delete' }}
                    </button>
                </div>
            </div>
        </div>


    </div>
</template>
