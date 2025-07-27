<script setup>
import { defineProps, computed, defineEmits } from 'vue';
import Chart from 'chart.js/auto';
import { onMounted, watch, ref } from 'vue';

const props = defineProps({
    activities: {
        type: Array,
        default: () => []
    },
    tickets: {
        type: Array,
        default: () => []
    },
    approvals: {
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
    }
});

const emits = defineEmits(['open-deliverable-viewer']); // Define the new emit

// Computed properties for dashboard stats
const totalTickets = computed(() => props.tickets.length);
const pendingApprovalsCount = computed(() => {
    return props.deliverables.filter(d =>
        d.status === 'pending_review' &&
        (!d.client_interaction || (!d.client_interaction.approved_at && !d.client_interaction.rejected_at && !d.client_interaction.revisions_requested_at))
    ).length;
});

const newReportsAvailableCount = computed(() => {
    return props.deliverables.filter(d =>
        d.type === 'report' &&
        (!d.client_interaction || !d.client_interaction.read_at)
    ).length;
});

// Filter deliverables that need specific action from the client
const actionRequiredDeliverables = computed(() => {
    return props.deliverables.filter(d =>
        (d.status === 'pending_review' && (!d.client_interaction || (!d.client_interaction.approved_at && !d.client_interaction.rejected_at))) // Not yet approved/rejected by this client
        || (d.status === 'revisions_requested' && d.client_interaction && d.client_interaction.revisions_requested_at) // Or if this client requested revisions on previous version
    );
});

// Get the 5 most recent deliverables submitted
const recentDeliverables = computed(() => {
    return [...props.deliverables]
        .sort((a, b) => new Date(b.submitted_at) - new Date(a.submitted_at))
        .slice(0, 5);
});


// Chart.js setup
const chartCanvas = ref(null);
let myChart = null;

// Computed properties for chart data, ensuring they re-evaluate only when relevant prop data changes
const completedTasks = computed(() => props.tickets.filter(t => t.status === 'completed').length);
const openTasks = computed(() => props.tickets.filter(t => t.status === 'open').length);
const pendingGeneralApprovals = computed(() => props.approvals.filter(a => a.status === 'pending').length);
const approvedGeneralApprovals = computed(() => props.approvals.filter(a => a.status === 'approved').length);


const createOrUpdateChart = () => {
    if (chartCanvas.value) { // Ensure chartCanvas.value is not null
        if (myChart) {
            myChart.destroy(); // Destroy existing chart before creating a new one
        }

        const ctx = chartCanvas.value.getContext('2d');
        myChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Completed Tasks', 'Open Tasks', 'Pending General Approvals', 'Approved General Approvals'],
                datasets: [{
                    data: [completedTasks.value, openTasks.value, pendingGeneralApprovals.value, approvedGeneralApprovals.value],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(153, 102, 255, 0.6)'
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
                    },
                    title: {
                        display: true,
                        text: 'Overall Project Status'
                    }
                }
            }
        });
    }
};

onMounted(() => {
    createOrUpdateChart();
});

// Watch the computed properties that directly provide the chart data
// This is more efficient than a deep watch on the entire 'tickets' or 'approvals' arrays.
watch([completedTasks, openTasks, pendingGeneralApprovals, approvedGeneralApprovals], () => {
    createOrUpdateChart();
});

// Function to open the deliverable viewer modal
const handleOpenDeliverableViewer = (deliverable) => {
    emits('open-deliverable-viewer', deliverable);
};
</script>

<template>
    <div class="p-6 bg-gray-100 min-h-full">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Client Dashboard</h1>

        <!-- Quick Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Tickets Card -->
            <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between transition-transform transform hover:scale-105 cursor-pointer">
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Total Tasks</h2>
                    <p class="text-4xl font-bold text-blue-600">{{ totalTickets }}</p>
                </div>
                <svg class="w-12 h-12 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            </div>

            <!-- Pending Approvals Card -->
            <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between transition-transform transform hover:scale-105 cursor-pointer">
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Action Needed (Deliverables)</h2>
                    <p class="text-4xl font-bold text-yellow-600">{{ actionRequiredDeliverables.length }}</p>
                </div>
                <svg class="w-12 h-12 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>

            <!-- New Reports Available Card -->
            <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between transition-transform transform hover:scale-105 cursor-pointer">
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">New Reports</h2>
                    <p class="text-4xl font-bold text-green-600">{{ newReportsAvailableCount }}</p>
                </div>
                <svg class="w-12 h-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>

            <!-- Other relevant metrics could go here -->
            <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between transition-transform transform hover:scale-105 cursor-pointer">
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Overall Progress</h2>
                    <p class="text-4xl font-bold text-purple-600">75%</p> <!-- Placeholder -->
                </div>
                <svg class="w-12 h-12 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
        </div>

        <!-- Main Content Area: Action Required, Recent Announcements, Activity/Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Section for Items Requiring Client Attention -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Action Required
                </h2>
                <div v-if="actionRequiredDeliverables.length > 0" class="space-y-4">
                    <div v-for="deliverable in actionRequiredDeliverables" :key="deliverable.id"
                         class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex justify-between items-center"
                    >
                        <div>
                            <p class="font-semibold text-yellow-800">{{ deliverable.title }}</p>
                            <p class="text-sm text-yellow-700">Type: {{ deliverable.type.replace(/_/g, ' ') }}</p>
                            <p class="text-sm text-yellow-700">Submitted by: {{ deliverable.team_member?.name || 'N/A' }}</p>
                        </div>
                        <button @click="handleOpenDeliverableViewer(deliverable)"
                                class="bg-yellow-600 text-white text-sm py-2 px-4 rounded-lg hover:bg-yellow-700 transition-colors"
                        >
                            Review Now
                        </button>
                    </div>
                </div>
                <div v-else class="text-gray-600 py-4 text-center">
                    <p>No immediate actions required. Great job!</p>
                </div>
            </div>

            <!-- Recent Announcements Section -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592L6 18V6l3-3 2 2zm6-3v14.485c0 .085.033.166.098.221l.685.56a1.76 1.76 0 002.417-.592L21 6V3l-3 3-2-2z"></path></svg>
                    Recent Announcements
                </h2>
                <div v-if="announcements.length > 0" class="space-y-3">
                    <div v-for="announcement in announcements" :key="announcement.id" class="border-b pb-3 last:border-b-0">
                        <p class="font-semibold text-gray-700">{{ announcement.title }}</p>
                        <p class="text-sm text-gray-600">{{ announcement.content.substring(0, 70) }}...</p>
                        <p class="text-xs text-gray-500 mt-1">{{ new Date(announcement.date).toLocaleDateString() }}</p>
                    </div>
                </div>
                <div v-else class="text-gray-600 py-4 text-center">
                    <p>No new announcements at this time.</p>
                </div>
            </div>

            <!-- Recent Activity and Charts Section -->
<!--            <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-md">-->
<!--                <h2 class="text-2xl font-bold text-gray-800 mb-4">Task & Approval Status</h2>-->
<!--                <div class="chart-container h-64 mb-6">-->
<!--                    <canvas ref="chartCanvas"></canvas>-->
<!--                </div>-->

<!--                <h2 class="text-2xl font-bold text-gray-800 mb-4 mt-8">Recent Activity</h2>-->
<!--                <div v-if="activities.length > 0" class="space-y-3">-->
<!--                    <div v-for="activity in activities" :key="activity.id" class="border-b pb-3 last:border-b-0">-->
<!--                        <p class="text-gray-700">{{ activity.description }}</p>-->
<!--                        <p class="text-sm text-gray-500 mt-1">{{ new Date(activity.date).toLocaleString() }}</p>-->
<!--                    </div>-->
<!--                </div>-->
<!--                <div v-else class="text-gray-600 py-4 text-center">-->
<!--                    <p>No recent activity to display.</p>-->
<!--                </div>-->
<!--            </div>-->
        </div>
    </div>
</template>

<style scoped>
.chart-container {
    position: relative;
    width: 100%;
    height: 100%; /* Adjust height as needed */
}
</style>
