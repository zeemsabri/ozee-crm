<script setup>
import { ref, onMounted, provide, computed } from 'vue';
import BaseModal from './ClientDashboard/BaseModal.vue';
import LoadingOverlay from './ClientDashboard/LoadingOverlay.vue';
import Sidebar from './ClientDashboard/Sidebar.vue';
import HomeSection from './ClientDashboard/HomeSection.vue';
import TicketsSection from './ClientDashboard/TicketsSection.vue';
import ApprovalsSection from './ClientDashboard/ApprovalsSection.vue';
import DocumentsSection from './ClientDashboard/DocumentsSection.vue';
import InvoicesSection from './ClientDashboard/InvoicesSection.vue';
import AnnouncementsSection from './ClientDashboard/AnnouncementsSection.vue';
import DeliverableViewerModal from './ClientDashboard/DeliverablViewerModal.vue'; // Import the new modal component

const props = defineProps({
    initialAuthToken: { // Token from magic link
        type: String,
        default: ''
    },
    projectId: { // Project ID associated with the magic link
        type: [String, Number],
        required: true
    }
});

const isLoading = ref(true);
const userId = ref('client-user'); // This could be derived from API response if a client ID is sent back
const currentSection = ref('home');
const isSidebarExpanded = ref(false); // State for sidebar expansion
const selectedDeliverable = ref(null);
const showDeliverableViewer = ref(false);

// State for BaseModal
const modalOpen = ref(false);
const modalTitle = ref('');
const modalMessage = ref('');
const modalButtons = ref([]);
const modalChildren = ref(null);

// Reactive Data for API Calls
const activities = ref([]);
const tickets = ref([]);
const approvals = ref([]);
const documents = ref([]);
const invoices = ref([]);
const announcements = ref([]);
const deliverables = ref([]); // New reactive data for deliverables
const error = ref(null); // To store any API errors

// Computed property for main content margin based on sidebar state
const mainContentMargin = computed(() => {
    return {
        marginLeft: isSidebarExpanded.value ? '16rem' : '4rem'
    };
});

const handleOpenDeliverableViewer = (deliverable) => {
    selectedDeliverable.value = deliverable;
    showDeliverableViewer.value = true;
};

// Generic function to fetch data from an API endpoint
const fetchClientData = async (endpoint, dataRef) => {
    try {
        const response = await fetch(`/api/client-api/${endpoint}`, {
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json' // Ensure JSON response
            }
        });

        if (!response.ok) {
            const errorBody = await response.json();
            throw new Error(errorBody.message || `Failed to fetch data from ${endpoint}.`);
        }

        const data = await response.json();
        dataRef.value = data;
    } catch (err) {
        console.error(`Error fetching from ${endpoint}:`, err);
        error.value = error.value ? error.value + `\n${err.message}` : err.message;
    }
};

// Modal Functions (provided to child components via `provide`)
const showCustomModal = (title, message, type, onConfirmCallback = null, children = null) => {
    modalTitle.value = title;
    modalMessage.value = message;
    modalChildren.value = children;

    const buttons = [];
    if (type === 'alert') {
        buttons.push({
            label: 'OK',
            className: 'bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700',
            onClick: () => (modalOpen.value = false),
        });
    } else if (type === 'confirm') {
        buttons.push(
            {
                label: 'Cancel',
                className: 'bg-gray-300 text-gray-800 py-2 px-4 rounded-lg hover:bg-gray-400',
                onClick: () => (modalOpen.value = false),
            },
            {
                label: 'Confirm',
                className: 'bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700',
                onClick: () => {
                    onConfirmCallback && onConfirmCallback();
                    modalOpen.value = false;
                },
            }
        );
    }
    modalButtons.value = buttons;
    modalOpen.value = true;
};

const closeCustomModal = () => {
    modalOpen.value = false;
    modalChildren.value = null;
};

// Function to add activities (will call API later)
const addActivity = async (message) => {
    // For now, still simulated, but this is where you'd make an API call
    // to a POST endpoint like /api/client-api/project/{projectId}/activities
    console.log("Simulating adding activity:", message);
    const newActivity = {
        id: Date.now(),
        description: message,
        date: new Date().toISOString()
    };
    activities.value.unshift(newActivity); // Add to beginning
    activities.value = activities.value.slice(0, 10); // Keep only 10 recent
};

// Handlers for child component emitted events (will be updated for API calls)
const handleAddTicket = async (newTicket) => {
    tickets.value.unshift(newTicket);
    tickets.value.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
};

const handleUpdateApproval = async (id, status) => {
    const index = deliverables.value.findIndex(app => app.id === id);
    if (index !== -1) {
        deliverables.value[index].status = status;
    }
};

const handleAddDocument = async (newDoc) => {
    // This would involve a POST request to /api/client-api/project/{projectId}/documents
    console.log("Simulating adding document:", newDoc);
    documents.value.unshift(newDoc);
    documents.value.sort((a, b) => new Date(b.uploadDate).getTime() - new Date(a.uploadDate).getTime());
};

const handleUpdateInvoice = async (id, status) => {
    // This would involve a POST/PATCH request to /api/client-api/project/{projectId}/invoices/{invoiceId}/mark-paid
    console.log(`Simulating updating invoice ${id} to ${status}`);
    const index = invoices.value.findIndex(inv => inv.id === id);
    if (index !== -1) {
        invoices.value[index].status = status;
    }
};

// Initial data loading using the new API endpoints
onMounted(async () => {
    isLoading.value = true;
    error.value = null; // Clear previous errors

    if (!props.initialAuthToken || !props.projectId) {
        error.value = "Authentication token or Project ID missing. Cannot load dashboard.";
        isLoading.value = false;
        return;
    }

    // Fetch all data concurrently, now including deliverables
    await Promise.all([
        // fetchClientData(`project/${props.projectId}/activities`, activities),
        fetchClientData(`project/${props.projectId}/tasks`, tickets), // Assuming tasks are your tickets
        // fetchClientData(`project/${props.projectId}/approvals`, approvals), // Assuming EmailController handles approvals
        // fetchClientData(`project/${props.projectId}/documents`, documents),
        // fetchClientData(`project/${props.projectId}/invoices`, invoices),
        // fetchClientData(`announcements`, announcements), // Assuming announcements are general and not tied to a project path, or you can use project specific announcements: `project/${props.projectId}/announcements`
        fetchClientData(`project/${props.projectId}/deliverables`, deliverables) // New fetch for deliverables
    ]);

    isLoading.value = false;
});

// Provide services to child components
provide('modalService', { showModal: showCustomModal });
provide('activityService', { addActivity: addActivity }); // Provided for child components to call

</script>

<template>
    <div class="bg-gray-100 flex min-h-screen font-sans">
        <LoadingOverlay :isLoading="isLoading" />
        <div v-if="error" class="fixed inset-0 bg-red-100 bg-opacity-90 flex items-center justify-center z-50 p-4">
            <div class="bg-white p-8 rounded-lg shadow-xl text-red-700 max-w-lg w-full text-center">
                <h3 class="text-2xl font-bold mb-4">Error Loading Dashboard</h3>
                <p class="mb-6 whitespace-pre-line">{{ error }}</p>
                <button @click="error = null" class="bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700">Dismiss</button>
            </div>
        </div>

        <!-- Sidebar component - collapsed by default, expands on hover -->
        <Sidebar
            :userId="userId"
            :activeSection="currentSection"
            :isExpanded="isSidebarExpanded"
            @section-change="currentSection = $event"
            @update:isExpanded="isSidebarExpanded = $event"
        />

        <!-- Main content area with dynamic left margin -->
        <div class="flex-1 p-8 overflow-y-auto main-content" :style="mainContentMargin">
            <HomeSection
                v-if="currentSection === 'home'"
                :activities="activities"
                :tickets="tickets"
                :approvals="approvals"
                :documents="documents"
                :invoices="invoices"
                :announcements="announcements"
                :deliverables="deliverables"
                @open-deliverable-viewer="handleOpenDeliverableViewer"
            />
            <TicketsSection v-if="currentSection === 'tickets'" :project-id="projectId" :initial-auth-token="initialAuthToken" :tickets="tickets" @add-ticket="handleAddTicket" @add-activity="addActivity" />
            <ApprovalsSection @open-deliverable-viewer="handleOpenDeliverableViewer" :deliverables="deliverables" :initial-auth-token="initialAuthToken" :project-id="projectId" v-if="currentSection === 'approvals'" :approvals="approvals" @update-approval="handleUpdateApproval" @add-activity="addActivity" />
            <DocumentsSection v-if="currentSection === 'documents'" :documents="documents" @add-document="handleAddDocument" @add-activity="addActivity" />
            <InvoicesSection v-if="currentSection === 'invoices'" :invoices="invoices" @update-invoice="handleUpdateInvoice" @add-activity="addActivity" />
            <AnnouncementsSection v-if="currentSection === 'announcements'" :announcements="announcements" />
        </div>

        <BaseModal
            :isOpen="modalOpen"
            :title="modalTitle"
            :message="modalMessage"
            :buttons="modalButtons"
            :children="modalChildren"
            @close="closeCustomModal"
        />

        <!-- Deliverable Viewer Modal -->
        <DeliverableViewerModal
            v-model:isOpen="showDeliverableViewer"
            :deliverable="selectedDeliverable"
            :initialAuthToken="initialAuthToken"
            :projectId="projectId"
            @deliverable-action-success="handleUpdateApproval"
        />

    </div>
</template>

<style scoped>
/* Main content area styles */
.main-content {
    transition: margin-left 0.3s ease;
}
</style>
