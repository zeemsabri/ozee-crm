<script setup>
import { reactive, watch, computed, ref } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import EmailEditor from '@/Components/EmailEditor.vue';
import InputError from '@/Components/InputError.vue';
import OZeeMultiSelect from '@/Components/CustomMultiSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

import { useEmailSignature } from '@/Composables/useEmailSignature'; // Re-import useEmailSignature
import { useEmailTemplate } from '@/Composables/useEmailTemplate';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
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
    projectClients: {
        type: Array,
        default: () => [],
    },
    userProjectRole: { // This prop is still needed for useEmailSignature
        type: Object,
        required: true
    },
    emailId: {
        type: Number,
        required: false
    }
});

const emit = defineEmits(['close', 'submitted', 'error']);

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

// State for greeting customization
const greetingType = ref('full_name'); // 'full_name', 'first_name', 'last_name', 'custom'
const customGreetingName = ref('');

const greetingTypeOptions = ref([
    { value: 'full_name', label: 'Full Name' },
    { value: 'first_name', label: 'First Name' },
    { value: 'last_name', label: 'Last Name' },
    { value: 'custom', label: 'Custom' },
]);


// Use the useEmailSignature composable to get the user's signature HTML
// This is now used for display purposes only within the modal
const { userSignature } = useEmailSignature(computed(() => props.userProjectRole));


// Watch for changes in initialFormData prop to update localFormData
watch(() => props.show, (newValue) => {
    if (newValue) { // Only run when modal is shown
        // Clear existing properties
        for (const key in localFormData) {
            delete localFormData[key];
        }

        // Deep copy initialFormData. No need to strip greeting/signature anymore.
        // The body content will be exactly what was saved in the database.
        Object.assign(localFormData, JSON.parse(JSON.stringify(props.initialFormData)));

        // Reset greeting options when modal opens/data changes
        greetingType.value = 'full_name';
        customGreetingName.value = '';
    }
}, { immediate: true }); // Immediate to set initial value on first load


// Computed ref for subject from localFormData
const emailSubject = computed(() => localFormData.subject || '');

// Ref to hold the content directly from the EmailEditor (what the user types)
// This is the raw HTML fragment that will be processed by useEmailTemplate
const editorBodyContent = computed(() => localFormData.body || '');

// Use the useEmailTemplate composable to get the processed HTML fragment
// Pass only the editorBodyContent
const { processedHtmlBody } = useEmailTemplate(editorBodyContent);


// Computed property for the dynamic greeting (for display in editor only)
const greetingText = computed(() => {
    if (localFormData.client_ids && localFormData.client_ids.length > 0) {
        const firstClientId = localFormData.client_ids[0];
        const firstClient = props.projectClients.find(client => client.id === firstClientId);

        if (firstClient) {
            const nameParts = firstClient.name.split(' ').filter(part => part.length > 0);
            const firstName = nameParts.length > 0 ? nameParts[0] : '';
            const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';

            switch (greetingType.value) {
                case 'full_name':
                    return `Hi ${firstClient.name},`;
                case 'first_name':
                    return `Hi ${firstName},`;
                case 'last_name':
                    return `Hi ${lastName},`;
                case 'custom':
                    return `Hi ${customGreetingName.value.trim() || 'there'},`;
                default:
                    return `Hi ${firstClient.name},`;
            }
        }
    }
    return 'Hi there,'; // Default greeting if no client is selected or found
});


// Custom data formatting for BaseFormModal
const formatDataForApi = (data) => {
    const formattedData = { ...data };

    // Specifically for client_ids in compose, ensure it's an array of { id: value }
    if (formattedData.client_ids && Array.isArray(formattedData.client_ids) && props.title === 'Compose New Email') {
        formattedData.client_ids = formattedData.client_ids.map(id => ({ id }));
    }

    // The body sent to API is now just the processed HTML fragment from useEmailTemplate
    formattedData.body = processedHtmlBody.value;

    // Also send selected greeting type and custom name for backend templating
    formattedData.greeting_name = greetingText.value;
    formattedData.custom_greeting_name = customGreetingName.value.trim();
    // Send the ID of the first client for greeting purposes on backend
    formattedData.first_client_id = localFormData.client_ids && localFormData.client_ids.length > 0 ? localFormData.client_ids[0] : null;


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
    if (!linkUrl.value.trim()) {
        linkUrl.value = 'http://' + linkUrl.value.trim(); // Default to http if no protocol
    }

    // Basic URL validation
    try {
        new URL(linkUrl.value);
    } catch (e) {
        linkError.value = 'Please enter a valid URL (e.g., https://example.com or www.example.com).';
        return;
    }

    const formattedLink = `[${linkText.value.trim()}] {${linkUrl.value.trim()}}`;

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

const saveEmail = async () => {

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
        await fetchProjectEmails();
        showEmailDetailsModal.value = false;
    } catch (error) {
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
            <!-- Content for Compose Email -->
            <div v-if="title === 'Compose New Email'">
                <div class="mb-4">
                    <InputLabel for="client_ids" value="To (Clients)" />
                    <OZeeMultiSelect
                        v-model="localFormData.client_ids"
                        :options="projectClients"
                        placeholder="Select one or more clients"
                        label-key="name"
                        value-key="id"
                    />
                    <InputError :message="errors.client_ids ? errors.client_ids[0] : ''" class="mt-2" />
                </div>

                <div class="mb-4">
                    <InputLabel for="subject" value="Subject" />
                    <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="localFormData.subject" required />
                    <InputError :message="errors.subject ? errors.subject[0] : ''" class="mt-2" />
                </div>

                <div class="mb-6">
                    <!-- Buttons above the editor -->
                    <div class="mb-2 flex justify-end space-x-2">
                        <SecondaryButton type="button" @click="openInsertListModal">
                            Insert List
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="openInsertLinkModal">
                            Insert Link
                        </SecondaryButton>
                    </div>

                    <!-- Greeting Customization -->
                    <div class="mb-4">
                        <InputLabel for="greeting_type" value="Address Client By" />
                        <SelectDropdown
                            id="greeting_type"
                            v-model="greetingType"
                            :options="greetingTypeOptions"
                            value-key="value"
                            label-key="label"
                            class="mt-1 block w-full"
                        />
                        <div v-if="greetingType === 'custom'" class="mt-2">
                            <InputLabel for="custom_greeting_name" value="Custom Name" />
                            <TextInput
                                id="custom_greeting_name"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="customGreetingName"
                                placeholder="e.g., Azaan"
                            />
                        </div>
                    </div>

                    <!-- Dynamic Greeting Display -->
                    <p class="text-gray-700 text-base mb-2">{{ greetingText }}</p>

                    <InputLabel for="body" value="Email Body" class="sr-only" />
                    <EmailEditor id="body" v-model="localFormData.body" placeholder="Compose your email here..." height="300px" />
                    <InputError :message="errors.body ? errors.body[0] : ''" class="mt-2" />
                </div>
                <!-- Display non-editable signature below the editor -->
                <div v-if="userSignature" class="unselectable-signature" v-html="userSignature"></div>
            </div>

            <!-- Content for Edit and Approve Email -->
            <div v-else-if="title === 'Edit and Approve Email'">
                <div class="mb-4">
                    <InputLabel for="edit_subject" value="Subject" />
                    <TextInput id="edit_subject" type="text" class="mt-1 block w-full" v-model="localFormData.subject" required />
                    <InputError :message="errors.subject ? errors.subject[0] : ''" class="mt-2" />
                </div>

                <div class="mb-6">
                    <!-- Buttons above the editor -->
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
                <!-- Display non-editable signature below the editor -->
<!--                <div v-if="userSignature" class="unselectable-signature" v-html="userSignature"></div>-->

                <!-- Preview Button for Edit and Approve -->
                <div class="mb-2 flex justify-end space-x-2">
                    <PrimaryButton type="button" @click="saveEmail">
                        Save
                    </PrimaryButton>
                    <SecondaryButton type="button" @click="previewEmail">
                        Preview Email
                    </SecondaryButton>

                </div>
            </div>

            <!-- Content for Reject Email -->
            <div v-else-if="title === 'Reject Email'">
                <div class="mb-6">
                    <InputLabel for="rejection_reason" value="Rejection Reason" />
                    <textarea id="rejection_reason" rows="5" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="localFormData.rejection_reason" required placeholder="Please provide a reason for rejecting this email (minimum 10 characters)"></textarea>
                    <InputError :message="errors.rejection_reason ? errors.rejection_reason[0] : ''" class="mt-2" />
                </div>
            </div>
        </template>
    </BaseFormModal>

    <!-- Insert Link Modal -->
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

    <!-- Insert List Modal -->
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
/* Custom style to attempt to make signature non-selectable/non-editable */
.unselectable-signature {
    user-select: none; /* Standard property */
    -webkit-user-select: none; /* Safari */
    -moz-user-select: none; /* Firefox */
    -ms-user-select: none; /* IE/Edge */
    pointer-events: none; /* Prevent clicks/interactions within the div */
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    font-size: 0.875rem;
    color: #6b7280;
}

/* Ensure links within unselectable signature are still clickable */
.unselectable-signature a {
    pointer-events: auto;
    cursor: pointer;
}
</style>
