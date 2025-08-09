<script setup>
import {reactive, computed, ref, onMounted, watch} from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import EmailEditor from '@/Components/EmailEditor.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InsertLinkModal from '@/Components/ProjectsEmails/InsertLinkModal.vue';
import InsertListModal from '@/Components/ProjectsEmails/InsertListModal.vue';

import { useEmailTemplate } from '@/Composables/useEmailTemplate'; // Keep for editing

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: { // This title will now only be 'Edit and Approve Email' or 'Reject Email'
        type: String,
        required: true,
    },
    apiEndpoint: {
        type: String,
        required: true,
    },
    httpMethod: {
        type: String,
        default: 'post',
    },
    submitButtonText: {
        type: String,
        default: 'Save',
    },
    successMessage: {
        type: String,
        default: 'Operation successful!',
    },
    emailId: { // Still needed for specific email actions
        type: Number,
        required: false
    },
    projectId: { // Still needed if actions require project context
        type: Number,
        required: true
    }
});

const emit = defineEmits(['close', 'submitted', 'error', 'fetchEmails']); // Added fetchEmails

// Create a local reactive copy of formData to be mutated by the form inputs
const localFormData = reactive({});
const renderedBodyLoading = ref(false);
const loading = ref(false);

// Fetch email data when the modal is shown
const fetchEmailData = async () => {
    if (!props.emailId) return;

    loading.value = true;
    try {
        // Clear existing properties
        for (const key in localFormData) {
            delete localFormData[key];
        }

        // For rejection modal, initialize with empty rejection reason
        if (props.title === 'Reject Email') {
            Object.assign(localFormData, {
                rejection_reason: ''
            });
            loading.value = false;
            return;
        }

        // For edit modal, fetch the email data
        const response = await window.axios.get(`/api/emails/${props.emailId}/edit-content`);
        const emailData = response.data;

        // Handle the case where body_html is provided instead of body
        if (emailData.body_html && !emailData.body) {
            emailData.body = emailData.body_html;
        }

        Object.assign(localFormData, emailData);

    } catch (error) {
        console.error('Failed to fetch email data:', error);
        emit('error', error);
    } finally {
        loading.value = false;
    }
};

// Watch for changes in show prop to fetch data when modal opens
watch(() => props.show, (newValue) => {
    if (newValue && props.emailId) {
        fetchEmailData();
    }
});

// State for modals
const showInsertLinkModal = ref(false);
const showInsertListModal = ref(false);

// Ref to hold the content directly from the EmailEditor (what the user types)
// This is the raw HTML fragment that will be processed by useEmailTemplate
const editorBodyContent = computed(() => {
    return localFormData.body || '';
});

// Use the useEmailTemplate composable to get the processed HTML fragment
// Pass only the editorBodyContent.
const { processedHtmlBody } = useEmailTemplate(editorBodyContent);

// Custom data formatting for BaseFormModal
const formatDataForApi = (data) => {
    const formattedData = { ...data };

    // The body sent to API is now just the processed HTML fragment from useEmailTemplate
    // Only apply this for 'Edit and Approve' action where body is present
    if (props.title === 'Edit and Approve Email') {
        formattedData.body = processedHtmlBody.value;
    }

    return formattedData;
};

const handleClose = () => {
    console.log('EmailActionModal is closing');
    emit('close');
};

const handleSubmit = (response) => {
    emit('submitted', response);
};

const handleError = (error) => {
    emit('error', error);
};

// --- Modal functionality ---
const openInsertLinkModal = () => {
    showInsertLinkModal.value = true;
};

const handleLinkInsert = (formattedLink) => {
    // Append the formatted link to the current body content
    localFormData.body += formattedLink;
    showInsertLinkModal.value = false;
};

const openInsertListModal = () => {
    showInsertListModal.value = true;
};

const handleListInsert = (formattedList) => {
    // Append the formatted list to the current body content
    localFormData.body += formattedList;
    showInsertListModal.value = false;
};

const emailError = ref({});
const saving = ref(false);

const saveEmail = async () => {
    saving.value = true;
    try {
        console.log('Saving email with ID:', props.emailId);
        console.log('Email data:', { subject: localFormData.subject, body: localFormData.body });

        await window.axios.post(`/api/emails/${props.emailId}/update`, {
            subject: localFormData.subject,
            body: localFormData.body
        });

        console.log('Email saved successfully');
        alert('Email saved successfully!');

        saving.value = false;
        emit('fetchEmails');
        handleClose();
    } catch (error) {
        saving.value = false;
        console.error('Error saving email:', error);

        let errorMessage = 'Failed to save email.';
        if (error.response && error.response.data.message) {
            errorMessage = error.response.data.message;
        }

        emailError.value = errorMessage;
        alert(errorMessage);
    }
};

const saveEmailAndApprove = async () => {
    saving.value = true;
    try {
        console.log('Approving email with ID:', props.emailId);
        console.log('Email data for approval:', {
            subject: localFormData.subject,
            body: processedHtmlBody.value
        });

        const response = await window.axios.post(`/api/emails/${props.emailId}/edit-and-approve`, {
            subject: localFormData.subject,
            body: processedHtmlBody.value
        });

        console.log('Email approved successfully:', response.data);
        alert('Email updated and approved successfully!');

        saving.value = false;
        emit('submitted', response.data);
        handleClose();
    } catch (error) {
        saving.value = false;
        console.error('Error approving email:', error);

        let errorMessage = 'Failed to approve email.';
        if (error.response && error.response.data.message) {
            errorMessage = error.response.data.message;
        } else if (error.message) {
            errorMessage = error.message;
        }

        emailError.value = errorMessage;
        alert(errorMessage);
        emit('error', error);
    }
};

const previewEmail = () => {
    if (props.emailId) {
        const previewUrl = `/emails/${props.emailId}/preview`;
        window.open(previewUrl, '_blank');
    } else {
        console.warn('Cannot preview email: Email ID not available.');
    }
};
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="title"
        :api-endpoint="apiEndpoint"
        :http-method="httpMethod"
        :form-data="localFormData"
        :submit-button-text="submitButtonText"
        :success-message="successMessage"
        :format-data-for-api="formatDataForApi"
        @close="handleClose"
        @submitted="handleSubmit"
        @error="handleError"
        max-width="3xl"
    >
        <template #default="{ errors }">
            <div v-if="title === 'Edit and Approve Email'">
                <div class="mb-4">
                    <InputLabel for="edit_subject" value="Subject" />
                    <TextInput id="edit_subject" type="text" class="mt-1 block w-full" v-model="localFormData.subject" required />
                    <InputError :message="errors.subject ? errors.subject[0] : ''" class="mt-2" />
                </div>

                <div class="mb-6">
                    <div class="mb-2 flex justify-end space-x-2">
                        <SecondaryButton type="button" @click="openInsertListModal">
                            Insert List
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="openInsertLinkModal">
                            Insert Link
                        </SecondaryButton>
                    </div>

                    <InputLabel for="edit_body" value="Email Body" class="sr-only" />
                    <div v-if="renderedBodyLoading" class="min-h-[300px] flex items-center justify-center bg-gray-50 rounded-md">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-500"></div>
                    </div>
                    <div v-else>
                        <div v-if="!editorBodyContent" class="text-red-500 mb-2">Warning: Email body content is empty!</div>
                        <EmailEditor id="edit_body" v-model="localFormData.body" :value="editorBodyContent" placeholder="Edit your email here..." height="300px" />
                    </div>
                    <InputError :message="errors.body ? errors.body[0] : ''" class="mt-2" />
                </div>

                <div class="mb-2 flex justify-end space-x-2">
                    <PrimaryButton type="button" :disabled="saving" @click="saveEmail">
                        {{ saving ? 'Saving...' : 'Save' }}
                    </PrimaryButton>
                    <SecondaryButton type="button" @click="previewEmail">
                        Preview Email
                    </SecondaryButton>
                </div>
            </div>

            <div v-else-if="title === 'Reject Email'">
                <div class="mb-6">
                    <InputLabel for="rejection_reason" value="Rejection Reason" />
                    <textarea id="rejection_reason" rows="5" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="localFormData.rejection_reason" required placeholder="Please provide a reason for rejecting this email (minimum 10 characters)"></textarea>
                    <InputError :message="errors.rejection_reason ? errors.rejection_reason[0] : ''" class="mt-2" />
                </div>
            </div>
        </template>
    </BaseFormModal>

    <InsertLinkModal
        :show="showInsertLinkModal"
        @close="showInsertLinkModal = false"
        @insert="handleLinkInsert"
    />

    <InsertListModal
        :show="showInsertListModal"
        @close="showInsertListModal = false"
        @insert="handleListInsert"
    />
</template>

<style scoped>
/* No specific Multiselect styles needed anymore as it's custom. */
/* You can remove these if this is the only place vue-multiselect was used. */
.multiselect {
    min-height: 38px;
}
.multiselect__tags {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem;
}
.multiselect__tag {
    background: #e5e7eb;
    color: #374151;
}
.multiselect__tag-icon:after {
    color: #6b7280;
}
</style>
