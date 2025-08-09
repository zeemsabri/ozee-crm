<script setup>
import { defineProps, computed, defineEmits, ref, onMounted, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    activities: {
        type: Array,
        default: () => []
    },
    tickets: { // Assuming these are your tasks
        type: Array,
        default: () => []
    },
    approvals: { // General approvals, might be less used if deliverables handle most
        type: Array,
        default: () => []
    },
    documents: {
        type: Array,
        default: () => []
    },
    invoices: {
        type: Array,
        default: () => []
    },
    announcements: {
        type: Array,
        default: () => []
    },
    deliverables: {
        type: Array,
        default: () => []
    },
    shareableResources: {
        type: Array,
        default: () => []
    },
    seoReportsCount: {
        type: Number,
        default: 0
    },
    projectData: {
        type: [Object, null],
        default: () => ({})
    }
});

const emits = defineEmits(['open-deliverable-viewer']);

// --- State for search and filters ---
const globalSearchQuery = ref('');

// --- Computed properties for dashboard stats ---
const totalTickets = computed(() => props.tickets.length);
const pendingApprovalsCount = computed(() => {
    return props.deliverables.filter(d =>
        d.status === 'pending_review' &&
        (!d.client_interaction || (!d.client_interaction.approved_at && !d.client_interaction.rejected_at && !d.client_interaction.revisions_requested_at))
    ).length;
});

const newReportsAvailableCount = computed(() => {
    return props.seoReportsCount;
});

// --- Filtered Data for Sections ---

// Action Required Deliverables
const actionRequiredDeliverables = computed(() => {
    const filtered = [...props.deliverables].filter(d =>
        (d.status === 'pending_review' && (!d.client_interaction || (!d.client_interaction.approved_at && !d.client_interaction.rejected_at)))
        || (d.status === 'revisions_requested' && d.client_interaction && d.client_interaction.revisions_requested_at)
    );
    // Apply global search
    if (globalSearchQuery.value) {
        const query = globalSearchQuery.value.toLowerCase();
        return filtered.filter(d =>
            d.title.toLowerCase().includes(query) ||
            (d.description && d.description.toLowerCase().includes(query)) ||
            (d.team_member && d.team_member.name.toLowerCase().includes(query))
        );
    }
    return filtered.sort((a, b) => new Date(b.submitted_at) - new Date(a.submitted_at));
});

// Upcoming Tasks
const upcomingTasks = computed(() => {
    const now = new Date();
    const sevenDaysLater = new Date();
    sevenDaysLater.setDate(now.getDate() + 7);

    const filtered = [...props.tickets]
        .filter(t => t.due_date && new Date(t.due_date) >= now && new Date(t.due_date) <= sevenDaysLater && t.status !== 'completed');

    // Apply global search
    if (globalSearchQuery.value) {
        const query = globalSearchQuery.value.toLowerCase();
        return filtered.filter(t =>
            t.name.toLowerCase().includes(query) ||
            (t.description && t.description.toLowerCase().includes(query))
        );
    }
    return filtered.sort((a, b) => new Date(a.due_date) - new Date(b.due_date));
});

// Latest 5 Documents
const latestDocuments = computed(() => {
    const sortedDocs = [...props.documents].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    const filtered = sortedDocs.slice(0, 5);

    // Apply global search
    if (globalSearchQuery.value) {
        const query = globalSearchQuery.value.toLowerCase();
        return filtered.filter(item =>
            item.filename.toLowerCase().includes(query) ||
            (item.notes && item.notes.some(note => note.content.toLowerCase().includes(query)))
        );
    }
    return filtered;
});

// Random 5 Resources
const randomResources = computed(() => {
    const shuffled = [...props.shareableResources].sort(() => 0.5 - Math.random());
    const filtered = shuffled.slice(0, 5);

    // Apply global search
    if (globalSearchQuery.value) {
        const query = globalSearchQuery.value.toLowerCase();
        return filtered.filter(item =>
            item.title.toLowerCase().includes(query) ||
            (item.description && item.description.toLowerCase().includes(query)) ||
            item.type.toLowerCase().includes(query) ||
            (item.tags && item.tags.some(tag => tag.name.toLowerCase().includes(query)))
        );
    }
    return filtered;
});


// Recent Activity (Combined Feed - now including deliverables, documents, resources)
const recentActivity = computed(() => {
    const combined = [
        ...props.deliverables.map(d => ({
            id: d.id,
            title: d.title,
            description: d.description || `Status: ${d.status.replace(/_/g, ' ')}`,
            activityDate: d.submitted_at,
            type: 'deliverable',
            link: null, // Deliverables open in modal
            originalItem: d // Keep reference to original deliverable object
        })),
        ...props.documents.map(d => ({
            id: d.id,
            title: d.filename,
            description: d.notes && d.notes.length > 0 ? d.notes[0].content : 'No description',
            activityDate: d.created_at,
            type: 'document',
            mime_type: d.mime_type,
            link: d.path,
            originalItem: d
        })),
        ...props.shareableResources.map(r => ({
            id: r.id,
            title: r.title,
            description: r.description || 'No description',
            activityDate: r.created_at,
            type: 'resource',
            mime_type: r.type, // Using 'type' from shareableResources as mime_type for icon
            link: r.url,
            originalItem: r
        }))
    ];

    const sortedAndSliced = combined.sort((a, b) => new Date(b.activityDate) - new Date(a.activityDate)).slice(0, 5);

    // Apply global search
    if (globalSearchQuery.value) {
        const query = globalSearchQuery.value.toLowerCase();
        return sortedAndSliced.filter(item =>
            item.title.toLowerCase().includes(query) ||
            (item.description && item.description.toLowerCase().includes(query))
        );
    }
    return sortedAndSliced;
});


// --- Chart.js setup ---
const chartCanvas = ref(null);
let myChart = null;

const completedTasksCount = computed(() => props.tickets.filter(t => t.status === 'completed').length);
const openTasksCount = computed(() => props.tickets.filter(t => t.status === 'To Do' || t.status === 'In Progress').length);
const pendingGeneralApprovalsCount = computed(() => props.approvals.filter(a => a.status === 'pending').length);
const approvedGeneralApprovalsCount = computed(() => props.approvals.filter(a => a.status === 'approved').length);


const createOrUpdateChart = () => {
    if (chartCanvas.value) {
        if (myChart) {
            myChart.destroy();
        }

        const ctx = chartCanvas.value.getContext('2d');
        myChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Completed Tasks', 'Open Tasks', 'Pending General Approvals', 'Approved General Approvals'],
                datasets: [{
                    data: [completedTasksCount.value, openTasksCount.value, pendingGeneralApprovalsCount.value, approvedGeneralApprovalsCount.value],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.8)', // Green-ish for completed
                        'rgba(255, 159, 64, 0.8)', // Orange for open
                        'rgba(54, 162, 235, 0.8)', // Blue for pending
                        'rgba(153, 102, 255, 0.8)' // Purple for approved
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 12, /* Smaller font for legend */
                                family: 'Inter, sans-serif'
                            },
                            color: '#4B5563'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Overall Project Status',
                        font: {
                            size: 16, /* Slightly smaller title */
                            weight: 'bold',
                            family: 'Inter, sans-serif'
                        },
                        color: '#1F2937'
                    }
                }
            }
        });
    }
};

onMounted(() => {
    createOrUpdateChart();
});

watch([completedTasksCount, openTasksCount, pendingGeneralApprovalsCount, approvedGeneralApprovalsCount], () => {
    createOrUpdateChart();
});

// Function to handle item click in Recent Activity or other sections
const handleItemClick = (item) => {
    if (item.type === 'deliverable') {
        emits('open-deliverable-viewer', item.originalItem);
    } else if (item.link) {
        window.open(item.link, '_blank');
    }
};

// Helper function to get icon for resource type
const getResourceIcon = (item) => {
    if (item.type === 'resource') {
        switch (item.mime_type) {
            case 'youtube': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-youtube text-red-500 w-5 h-5"><path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0 2 2 0 0 1 1.4 1.4 24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.56 49.56 0 0 1-16.2 0 2 2 0 0 1-1.4-1.4Z"/><path d="m10 15 5-3-5-3z"/></svg>`;
            case 'website': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-globe text-blue-500 w-5 h-5"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>`;
            case 'document': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file text-green-500 w-5 h-5"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>`;
            case 'image': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image text-purple-500 w-5 h-5"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`;
            case 'pdf': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text text-red-500 w-5 h-5"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`;
            default: return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link text-gray-500 w-5 h-5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07L9.4 6.6A2 2 0 0 1 8.07 8z"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.41-1.41A2 2 0 0 1 15.93 16z"/></svg>`;
        }
    } else if (item.type === 'document') {
        if (item.mime_type && item.mime_type.includes('pdf')) {
            return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text text-red-500 w-5 h-5"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`; // PDF icon
        } else if (item.mime_type && item.mime_type.includes('image')) {
            return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image text-purple-500 w-5 h-5"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`; // Image icon
        } else if (item.mime_type && item.mime_type.includes('wordprocessingml')) {
            return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-type-doc text-blue-500 w-5 h-5"><path d="M14.5 22H18a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8.5"/><path d="M14 2v6a2 2 0 0 0 2 2h6"/><path d="M8 12h4"/><path d="M8 16h4"/><path d="M8 20h4"/></svg>`; // Word icon
        }
    } else if (item.type === 'deliverable') {
        return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clipboard-check text-green-500 w-5 h-5"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>`; // Deliverable icon
    }
    return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file text-gray-500 w-5 h-5"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>`; // Generic file icon
};

const formatDueDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const projectInitials = computed(() => {
    if (props.projectData.name) {
        return props.projectData.name
            .split(' ')
            .map(word => word[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    }
    return 'CD'; // Default initials if project name is also missing
});

</script>

<template>
    <div class="min-h-screen bg-gray-100 font-inter text-gray-800 p-4 sm:p-6 lg:p-8">
        <!-- Header Section -->
        <header class="bg-white rounded-xl shadow-lg p-4 mb-6 flex flex-col sm:flex-row items-center justify-between">
            <div class="flex items-center mb-4 sm:mb-0">
                <img
                    v-if="projectData.logo"
                    :src="projectData.logo"
                    alt="Project Logo"
                    class="w-12 h-12 rounded-full mr-3 border-2 border-indigo-500 p-0.5"
                >
                <div
                    v-else
                    class="w-12 h-12 rounded-full mr-3 border-2 border-indigo-500 p-0.5 flex items-center justify-center bg-gray-200 text-indigo-700 font-bold text-lg"
                >
                    {{ projectInitials }}
                </div>

                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ projectData.name || 'Client Dashboard' }}</h1>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 w-full sm:w-auto">
                <div class="bg-indigo-500 text-white p-3 rounded-lg shadow-md flex items-center justify-center text-center">
                    <span class="text-xl font-bold">{{ totalTickets }}</span>
                    <span class="ml-2 text-sm">Total Tasks</span>
                </div>
                <div class="bg-yellow-500 text-white p-3 rounded-lg shadow-md flex items-center justify-center text-center">
                    <span class="text-xl font-bold">{{ pendingApprovalsCount }}</span>
                    <span class="ml-2 text-sm">Pending Approvals</span>
                </div>
                <div class="bg-green-500 text-white p-3 rounded-lg shadow-md flex items-center justify-center text-center">
                    <span class="text-xl font-bold">{{ newReportsAvailableCount }}</span>
                    <span class="ml-2 text-sm">SEO Reports</span>
                </div>
            </div>
        </header>

        <!-- Global Search Bar -->
        <div class="relative mb-6">
            <input
                type="text"
                v-model="globalSearchQuery"
                placeholder="Search all tasks, deliverables, and resources..."
                class="w-full p-3 pl-10 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-200"
                aria-label="Global Search"
            >
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search text-gray-400 w-5 h-5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
        </div>


        <main class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Action Required Section -->
                <section v-if="actionRequiredDeliverables.length > 0" class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
                    <h2 class="text-xl font-semibold text-red-700 mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell-ring mr-2 w-6 h-6"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M2.2 13.7A2 2 0 0 1 4 11h16a2 2 0 0 1 1.8 2.7L19 16H5Z"/></svg>
                        Action Required
                    </h2>
                    <ul class="space-y-3">
                        <li v-for="deliverable in actionRequiredDeliverables" :key="deliverable.id" class="flex items-center justify-between bg-gray-50 p-3 rounded-lg shadow-sm">
                            <div>
                                <p class="font-medium text-gray-900">{{ deliverable.title }}</p>
                                <p class="text-sm text-gray-600">
                                    <span class="capitalize">{{ deliverable.type.replace('_', ' ') }}</span> submitted by {{ deliverable.team_member?.name || 'N/A' }} on {{ formatDate(deliverable.submitted_at) }}
                                </p>
                            </div>
                            <button @click="handleOpenDeliverableViewer(deliverable)" class="ml-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition duration-200">
                                View & Approve
                            </button>
                        </li>
                    </ul>
                    <p v-if="actionRequiredDeliverables.length === 0 && !globalSearchQuery" class="text-gray-500 italic">No actions currently required.</p>
                    <p v-else-if="actionRequiredDeliverables.length === 0 && globalSearchQuery" class="text-gray-500 italic">No matching actions found.</p>
                </section>

                <!-- Project Status Chart -->
                <section class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart-2 mr-2 w-6 h-6"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
                        Project Status
                    </h2>
                    <div class="chart-container h-64">
                        <canvas ref="chartCanvas"></canvas>
                    </div>
                </section>

                <!-- Upcoming Tasks Section -->
                <section class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar-check mr-2 w-6 h-6"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
                        Upcoming Tasks
                    </h2>
                    <ul class="space-y-3">
                        <li v-for="task in upcomingTasks" :key="task.id" class="flex items-center justify-between bg-gray-50 p-3 rounded-lg shadow-sm">
                            <div>
                                <p class="font-medium text-gray-900">{{ task.name }}</p>
                                <p class="text-sm text-gray-600">Due: {{ formatDueDate(task.due_date) }}</p>
                            </div>
                            <span :class="{'bg-yellow-100 text-yellow-800': task.status === 'To Do', 'bg-blue-100 text-blue-800': task.status === 'In Progress'}" class="px-3 py-1 text-xs font-semibold rounded-full capitalize">
                                {{ task.status }}
                            </span>
                        </li>
                    </ul>
                    <p v-if="upcomingTasks.length === 0 && !globalSearchQuery" class="text-gray-500 italic">No upcoming tasks.</p>
                    <p v-else-if="upcomingTasks.length === 0 && globalSearchQuery" class="text-gray-500 italic">No matching tasks found.</p>
                </section>
            </div>

            <!-- Right Column -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Recent Activity Section -->
                <section class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity mr-2 w-6 h-6"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        Recent Activity
                    </h2>
                    <ul class="space-y-3">
                        <li v-for="item in recentActivity" :key="item.id" @click="handleItemClick(item)"
                            class="flex items-center bg-gray-50 p-3 rounded-lg shadow-sm cursor-pointer hover:bg-gray-100 transition-colors duration-200">
                            <div v-html="getResourceIcon(item)" class="flex-shrink-0 mr-3"></div>
                            <div>
                                <p class="font-medium text-gray-900">{{ item.title || item.filename }}</p>
                                <p class="text-sm text-gray-600">Added on {{ formatDate(item.activityDate) }}</p>
                            </div>
                        </li>
                    </ul>
                    <p v-if="recentActivity.length === 0 && !globalSearchQuery" class="text-gray-500 italic">No recent activity.</p>
                    <p v-else-if="recentActivity.length === 0 && globalSearchQuery" class="text-gray-500 italic">No matching activity found.</p>
                </section>

                <!-- Latest Documents Section -->
                <section class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text mr-2 w-6 h-6"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                        Latest Documents
                    </h2>
                    <ul class="space-y-3">
                        <li v-for="doc in latestDocuments" :key="doc.id" @click="handleItemClick({ type: 'document', link: doc.path })"
                            class="flex items-center bg-gray-50 p-3 rounded-lg shadow-sm cursor-pointer hover:bg-gray-100 transition-colors duration-200">
                            <div v-html="getResourceIcon({ type: 'document', mime_type: doc.mime_type })" class="flex-shrink-0 mr-3"></div>
                            <div>
                                <p class="font-medium text-gray-900">{{ doc.filename }}</p>
                                <p class="text-sm text-gray-600">Uploaded on {{ formatDate(doc.created_at) }}</p>
                            </div>
                        </li>
                    </ul>
                    <p v-if="latestDocuments.length === 0 && !globalSearchQuery" class="text-gray-500 italic">No recent documents.</p>
                    <p v-else-if="latestDocuments.length === 0 && globalSearchQuery" class="text-gray-500 italic">No matching documents found.</p>
                </section>

                <!-- Random Resources Section -->
                <section class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link-2 mr-2 w-6 h-6"><path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 0 1 0 10h-2"/><line x1="8" x2="16" y1="12" y2="12"/></svg>
                        Explore Resources
                    </h2>
                    <ul class="space-y-3">
                        <li v-for="resource in randomResources" :key="resource.id" @click="handleItemClick({ type: 'resource', link: resource.url })"
                            class="flex items-center bg-gray-50 p-3 rounded-lg shadow-sm cursor-pointer hover:bg-gray-100 transition-colors duration-200">
                            <div v-html="getResourceIcon({ type: 'resource', mime_type: resource.type })" class="flex-shrink-0 mr-3"></div>
                            <div>
                                <p class="font-medium text-gray-900">{{ resource.title }}</p>
                                <p v-if="resource.description" class="text-sm text-gray-600 truncate">{{ resource.description }}</p>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <span v-for="tag in resource.tags" :key="tag.id" class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded-full">
                                        {{ tag.name }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <p v-if="randomResources.length === 0 && !globalSearchQuery" class="text-gray-500 italic">No resources available.</p>
                    <p v-else-if="randomResources.length === 0 && globalSearchQuery" class="text-gray-500 italic">No matching resources found.</p>
                </section>
            </div>
        </main>
    </div>
</template>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}
.chart-container {
    position: relative;
    width: 100%;
    height: 100%;
    max-height: 256px; /* Reduced chart height */
}

/* Specific styling for search input to place icon inside */
.relative input[type="text"] {
    padding-left: 2.5rem; /* Adjust padding to make space for the icon */
}
</style>
