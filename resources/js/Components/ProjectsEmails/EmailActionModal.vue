<script setup>
import {reactive, watch, computed, ref} from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import EmailEditor from '@/Components/EmailEditor.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';

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
    initialFormData: {
        type: Object,
        default: () => ({}),
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

// State for the Insert Link modal
const showInsertLinkModal = ref(false);
const linkText = ref('');
const linkUrl = ref('');
const linkError = ref('');

// State for the Insert List modal
const showInsertListModal = ref(false);
const listItemsInput = ref(''); // Raw text input for list items
const listType = ref('bullet'); // 'bullet' or 'numbered'
const listError = ref('');

// Ref to hold the content directly from the EmailEditor (what the user types)
// This is the raw HTML fragment that will be processed by useEmailTemplate
const editorBodyContent = computed(() => localFormData.body || '');

// Use the useEmailTemplate composable to get the processed HTML fragment
// Pass only the editorBodyContent.
const { processedHtmlBody } = useEmailTemplate(editorBodyContent);


// Watch for changes in initialFormData prop to update localFormData
watch(() => props.show, (newValue) => {
    if (newValue) { // Only run when modal is shown
        // Clear existing properties
        for (const key in localFormData) {
            delete localFormData[key];
        }
        // Deep copy initialFormData.
        Object.assign(localFormData, JSON.parse(JSON.stringify(props.initialFormData)));
    }
}, { immediate: true });


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
    emit('close');
};

const handleSubmit = (response) => {
    emit('submitted', response);
};

const handleError = (error) => {
    emit('error', error);
};

// --- Insert Link functionality ---
const openInsertLinkModal = () => {
    linkText.value = '';
    linkUrl.value = '';
    linkError.value = '';
    showInsertLinkModal.value = true;
};

const insertLinkIntoEditor = () => {
    if (!linkText.value.trim()) {
        linkError.value = 'Link text cannot be empty.';
        return;
    }
    let urlToInsert = linkUrl.value.trim();
    if (!urlToInsert.startsWith('http://') && !urlToInsert.startsWith('https://')) {
        urlToInsert = 'http://' + urlToInsert; // Default to http if no protocol
    }

    // Basic URL validation
    try {
        new URL(urlToInsert);
    } catch (e) {
        linkError.value = 'Please enter a valid URL (e.g., https://example.com or www.example.com).';
        return;
    }

    const formattedLink = `[${linkText.value.trim()}] {${urlToInsert}}`;

    // Append the formatted link to the current body content
    localFormData.body += formattedLink;

    showInsertLinkModal.value = false;
    linkText.value = '';
    linkUrl.value = '';
    linkError.value = '';
};

// --- Insert List functionality ---
const openInsertListModal = () => {
    listItemsInput.value = '';
    listType.value = 'bullet';
    listError.value = '';
    showInsertListModal.value = true;
};

const insertListIntoEditor = () => {
    const items = listItemsInput.value.split('\n').map(item => item.trim()).filter(item => item !== '');

    if (items.length === 0) {
        listError.value = 'Please enter at least one list item.';
        return;
    }

    // Format the list items into a structured string for useEmailTemplate
    const listTag = listType.value === 'bullet' ? 'ul' : 'ol';
    let formattedList = `<${listTag}>`;
    items.forEach(item => {
        formattedList += `<li>${item}</li>`;
    });
    formattedList += `</${listTag}>`;

    // Append the formatted list to the current body content
    localFormData.body += formattedList;

    showInsertListModal.value = false;
    listItemsInput.value = '';
    listError.value = '';
};

const emailError = ref({});
const saving = ref(false);

const saveEmail = async () => {

    saving.value = true;
    try {
        await window.axios.post(`/api/emails/${props.emailId}/update`, {
            subject: localFormData.subject,
            body: localFormData.body
        });
        // Use a simple notification here since it's not a form submission via BaseFormModal
        // Assuming 'success' utility is available globally or imported
        if (typeof success === 'function') {
            success('Email approved successfully!');
        } else {
            console.log('Email approved successfully!');
        }

        saving.value = false;

    } catch (error) {

        saving.value = false;

        if (error.response && error.response.data.message) {
            emailError.value = error.response.data.message;
        } else {
            emailError.value = 'Failed to approve email.';
            console.error('Error approving email directly:', error);
        }
        // Assuming 'error' utility is available globally or imported
        if (typeof error === 'function') {
            error(emailError.value);
        }
    }
};

const saveEmailAndApprove = async () => {
    try {
        // This is a direct API call for "Edit & Approve" specifically
        // It bypasses BaseFormModal's internal submission
        const response = await window.axios.post(`/api/emails/${props.emailId}/edit-and-approve`, {
            subject: localFormData.subject,
            body: processedHtmlBody.value // Use the processed body for saving
        });

        // Use a simple notification here since it's not a form submission via BaseFormModal
        // Assuming 'success' utility is available globally or imported
        if (typeof success === 'function') { // Check if 'success' is a defined global function
            success('Email updated and approved successfully!');
        } else {
            alert('Email updated and approved successfully!'); // Fallback alert
        }
        emit('submitted', response.data); // Emit submitted event for parent to refresh
        handleClose(); // Close the modal
    } catch (error) {
        let errorMessage = 'Failed to approve email.';
        if (error.response && error.response.data.message) {
            errorMessage = error.response.data.message;
        } else if (error.message) {
            errorMessage = error.message;
        }
        // Assuming 'error' utility is available globally or imported
        if (typeof errorGlobal === 'function') { // Check for global 'error' function
            errorGlobal(errorMessage);
        } else {
            console.error('Error approving email directly:', error);
            alert(errorMessage); // Fallback alert
        }
        emit('error', error);
    }
};

// New function to preview email
const previewEmail = () => {
    if (props.emailId) {
        // Construct the URL using a base path or a named route if you have one on the frontend
        const previewUrl = `/emails/${props.emailId}/preview`; // Adjust if your base URL is different
        window.open(previewUrl, '_blank');
    } else {
        // Handle cases where email ID is not available (e.g., trying to preview a new unsaved email)
        console.warn('Cannot preview email: Email ID not available.');
        // Optionally show an alert or notification to the user
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
                    <EmailEditor id="edit_body" v-model="localFormData.body" placeholder="Edit your email here..." height="300px" />
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

    <Modal :show="showInsertLinkModal" @close="showInsertLinkModal = false" max-width="md">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Insert Link</h3>
            <div v-if="linkError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ linkError }}</span>
            </div>
            <div class="mb-4">
                <InputLabel for="link_text" value="Link Text" />
                <TextInput id="link_text" type="text" class="mt-1 block w-full" v-model="linkText" @keyup.enter="insertLinkIntoEditor" />
            </div>
            <div class="mb-6">
                <InputLabel for="link_url" value="URL" />
                <TextInput id="link_url" type="text" class="mt-1 block w-full" v-model="linkUrl" placeholder="e.g., https://www.example.com" @keyup.enter="insertLinkIntoEditor" />
            </div>
            <div class="flex justify-end space-x-3">
                <SecondaryButton @click="showInsertLinkModal = false">Cancel</SecondaryButton>
                <PrimaryButton @click="insertLinkIntoEditor">Insert</PrimaryButton>
            </div>
        </div>
    </Modal>

    <Modal :show="showInsertListModal" @close="showInsertListModal = false" max-width="md">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Insert List</h3>
            <div v-if="listError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ listError }}</span>
            </div>
            <div class="mb-4">
                <InputLabel for="list_items" value="List Items (one per line)" />
                <textarea id="list_items" rows="6" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="listItemsInput" placeholder="Enter each list item on a new line"></textarea>
            </div>
            <div class="mb-6">
                <InputLabel for="list_type" value="List Type" />
                <select id="list_type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="listType">
                    <option value="bullet">Bulleted List</option>
                    <option value="numbered">Numbered List</option>
                </select>
            </div>
            <div class="flex justify-end space-x-3">
                <SecondaryButton @click="showInsertListModal = false">Cancel</SecondaryButton>
                <PrimaryButton @click="insertListIntoEditor">Insert List</PrimaryButton>
            </div>
        </div>
    </Modal>
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
