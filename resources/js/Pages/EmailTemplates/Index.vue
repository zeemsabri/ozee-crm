<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import CreateEditTemplateModal from '@/Components/EmailTemplates/CreateEditTemplateModal.vue'; // <-- Correctly imported
import { success, error } from '@/Utils/notification';
import { usePermissions } from '@/Directives/permissions';

const { canDo } = usePermissions();

// Reactive state for API data
const emailTemplates = ref([]);
const placeholderDefinitions = ref([]);
const loadingTemplates = ref(true);
const loadingPlaceholders = ref(true);
const templatesError = ref('');

// State to control the modal
const isEditing = ref(false);
const showFormModal = ref(false);
const showDeleteModal = ref(false);
const selectedTemplate = ref(null);

/**
 * Fetches all email templates from the API.
 */
const fetchEmailTemplates = async () => {
    loadingTemplates.value = true;
    templatesError.value = '';
    try {
        const response = await window.axios.get('/api/email-templates');
        emailTemplates.value = response.data;
    } catch (err) {
        templatesError.value = 'Failed to load email templates.';
        console.error('Error fetching templates:', err);
    } finally {
        loadingTemplates.value = false;
    }
};

/**
 * Fetches all placeholder definitions from the API.
 */
const fetchPlaceholderDefinitions = async () => {
    loadingPlaceholders.value = true;
    try {
        const response = await window.axios.get('/api/placeholder-definitions');
        placeholderDefinitions.value = response.data;
    } catch (err) {
        console.error('Error fetching placeholder definitions:', err);
    } finally {
        loadingPlaceholders.value = false;
    }
};

/**
 * Opens the modal in 'create' mode.
 */
const openCreateModal = () => {
    isEditing.value = false;
    selectedTemplate.value = null;
    showFormModal.value = true;
};

/**
 * Opens the modal in 'edit' mode for a given template.
 * @param {object} template The template object to edit.
 */
const openEditModal = (template) => {
    isEditing.value = true;
    selectedTemplate.value = template;
    showFormModal.value = true;
};

/**
 * Opens the delete confirmation modal.
 * @param {object} template The template object to delete.
 */
const openDeleteModal = (template) => {
    selectedTemplate.value = template;
    showDeleteModal.value = true;
};

/**
 * Handles the `submitted` event from the modal, closing it and refreshing the data.
 */
const handleFormSubmitted = () => {
    showFormModal.value = false;
    fetchEmailTemplates();
};

/**
 * Sends a DELETE request to remove the selected template.
 */
const deleteTemplate = async () => {
    try {
        await window.axios.delete(`/api/email-templates/${selectedTemplate.value.id}`);
        success('Template deleted successfully!');
        showDeleteModal.value = false;
        await fetchEmailTemplates(); // Re-fetch data to update the table
    } catch (err) {
        console.error('Error deleting template:', err);
        const errorMessage = err.response?.data?.message || 'Failed to delete template.';
        error(errorMessage);
    }
};

// Fetch data on component mount
onMounted(() => {
    fetchEmailTemplates();
    fetchPlaceholderDefinitions();
});
</script>

<template>
    <Head title="Email Templates" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-3xl text-gray-800 leading-tight">Email Templates</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-semibold text-gray-900">Manage Email Templates</h3>
                        <PrimaryButton v-if="canDo('manage_email_templates')" @click="openCreateModal">
                            Create New Template
                        </PrimaryButton>
                    </div>

                    <div v-if="loadingTemplates" class="text-center text-gray-500 py-8">
                        Loading templates...
                    </div>
                    <div v-else-if="templatesError" class="text-center text-red-500 py-8">
                        {{ templatesError }}
                    </div>

                    <div v-else-if="emailTemplates && emailTemplates.length" class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm mt-6">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Slug
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Subject
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Is Default
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="template in emailTemplates" :key="template.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ template.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ template.slug }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ template.subject }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span v-if="template.is_default" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Yes
                                        </span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            No
                                        </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <SecondaryButton @click="openEditModal(template)">Edit</SecondaryButton>
                                        <DangerButton @click="openDeleteModal(template)">Delete</DangerButton>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center text-gray-500 py-8">
                        <p>No email templates found.</p>
                        <PrimaryButton v-if="canDo('manage_email_templates')" @click="openCreateModal" class="mt-4">
                            Create First Template
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Template Modal -->
        <CreateEditTemplateModal
            :show="showFormModal"
            :is-editing="isEditing"
            :template="selectedTemplate"
            :placeholder-definitions="placeholderDefinitions"
            @close="showFormModal = false"
            @submitted="handleFormSubmitted"
        />

        <!-- Delete Template Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="() => showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">Delete Email Template</h3>
                <p>Are you sure you want to delete the template "{{ selectedTemplate?.name }}"? This action cannot be undone.</p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="() => showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton class="ml-3" @click="deleteTemplate">
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
