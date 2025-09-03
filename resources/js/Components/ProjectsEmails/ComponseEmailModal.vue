<script setup>
import { reactive, watch, computed, ref, onMounted } from 'vue';
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

import { useEmailSignature } from '@/Composables/useEmailSignature';
import { useEmailTemplate } from '@/Composables/useEmailTemplate';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    projectId: {
        type: Number,
        required: true,
    },
    userProjectRole: { // Needed for useEmailSignature
        type: Object,
        required: true
    }
});

const emit = defineEmits(['close', 'submitted', 'error']);

const localFormData = reactive({
    project_id: props.projectId,
    client_ids: [],
    subject: '',
    body: '',
    status: 'pending_approval', // Default for new emails
});

const projectClients = ref([]);
const loadingClients = ref(true);
const clientsError = ref('');

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
const { userSignature } = useEmailSignature(computed(() => props.userProjectRole));

// Ref to hold the content directly from the EmailEditor (what the user types)
const editorBodyContent = computed(() => localFormData.body || '');

// Use the useEmailTemplate composable to get the processed HTML fragment
const { processedHtmlBody } = useEmailTemplate(editorBodyContent);

// Computed property for the dynamic greeting (for display in editor only)
const greetingText = computed(() => {
    if (localFormData.client_ids && localFormData.client_ids.length > 0) {
        const firstClientId = localFormData.client_ids[0];
        const firstClient = projectClients.value.find(client => client.id === firstClientId); // Use .value for ref

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

// Watch for changes in the 'show' prop to reset form data when opened
watch(() => props.show, (newValue) => {
    if (newValue) {
        // Reset form data to initial state for a new composition
        Object.assign(localFormData, {
            project_id: props.projectId,
            client_ids: [],
            subject: '',
            body: '',
            status: 'pending_approval',
        });
        // Reset greeting options
        greetingType.value = 'full_name';
        customGreetingName.value = '';
        clientsError.value = ''; // Clear client error on open
        fetchClients(); // Fetch clients every time the modal opens to ensure up-to-date list
    }
}, { immediate: true });

// Custom data formatting for BaseFormModal
const formatDataForApi = (data) => {
    const formattedData = { ...data };

    // Ensure client_ids is an array of { id: value }
    if (formattedData.client_ids && Array.isArray(formattedData.client_ids)) {
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

async function fetchClients() {
    loadingClients.value = true;
    clientsError.value = null;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/clients?type=clients`);
        projectClients.value = response.data;
    } catch (e) {
        console.error('Failed to fetch project clients:', e);
        clientsError.value = e.response?.data?.message || 'Failed to load client data.';
    } finally {
        loadingClients.value = false;
    }
};

onMounted(() => {
    // Clients will be fetched on `show` prop change, no need to fetch on mount unless always visible
});

</script>

<template>
    <BaseFormModal
        :show="show"
        title="Compose New Email"
        api-endpoint="/api/emails"
        http-method="post"
        :form-data="localFormData"
        submit-button-text="Submit for Approval"
        success-message="Email submitted for approval successfully!"
        :format-data-for-api="formatDataForApi"
        @close="handleClose"
        @submitted="handleSubmit"
        @error="handleError"
        max-width="3xl"
    >
        <template #default="{ errors }">
            <div class="mb-4">
                <InputLabel for="client_ids" value="To (Clients)" />
                <div v-if="loadingClients" class="text-gray-500 text-sm">Loading clients...</div>
                <div v-else-if="clientsError" class="text-red-500 text-sm">{{ clientsError }}</div>
                <OZeeMultiSelect
                    v-else
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
                <div class="mb-2 flex justify-end space-x-2">
                    <SecondaryButton type="button" @click="openInsertListModal">
                        Insert List
                    </SecondaryButton>
                    <SecondaryButton type="button" @click="openInsertLinkModal">
                        Insert Link
                    </SecondaryButton>
                </div>

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

                <p class="text-gray-700 text-base mb-2">{{ greetingText }}</p>

                <InputLabel for="body" value="Email Body" class="sr-only" />
                <EmailEditor id="body" v-model="localFormData.body" placeholder="Compose your email here..." height="300px" />
                <InputError :message="errors.body ? errors.body[0] : ''" class="mt-2" />
            </div>
            <div v-if="userSignature" class="unselectable-signature" v-html="userSignature"></div>
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
