<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { fetchCurrencyRates, displayCurrency } from '@/Utils/currency';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Filters from '@/Pages/Workspace/components/Filters.vue';
import ProjectCards from '@/Pages/Workspace/components/ProjectCards.vue';
import Sidebar from '@/Pages/Workspace/components/Sidebar.vue';

// Mock data to simulate fetching from a backend
const projects = ref([
    {
        id: 1,
        name: 'Project Phoenix',
        role: 'Manager',
        health: 'at-risk',
        alert: {
            text: 'Client email from Project Phoenix requires a reply.',
            timer: '3h 25m',
            incentive: 'Reply in time to earn 50 points.',
        },
        overview: {
            milestone: '3 of 5 - Design Phase (60% Complete)',
            budget: '$15,000 / $25,000 Used',
            status: 'On Track',
        },
        tasks: {
            today: [
                { name: 'Create new ad copy', status: 'started', assignee: 'Alex Ray' },
                { name: 'Review wireframes', status: 'blocked', assignee: 'Sarah Chen' },
            ],
            tomorrow: [
                { name: 'Client feedback call', status: 'paused', assignee: 'Jane Doe' },
                { name: 'Design review meeting', status: 'complete', assignee: 'Team A' },
            ],
            completed: [
                { name: 'Initial Project Kickoff', status: 'complete' },
            ],
        },
        communication: {
            lastSent: 'Today at 9:15 AM',
            lastReceived: '3 days ago at 4:30 PM',
        }
    },
    {
        id: 2,
        name: 'Project Odyssey',
        role: 'Contributor',
        health: 'needs-attention',
        tasks: {
            today: [
                { name: 'Develop login API endpoint', status: 'blocked' },
                { name: 'Write unit tests for checkout process', status: 'started' },
            ],
            tomorrow: [
                { name: 'Refactor database schema', status: 'paused' },
            ],
            completed: [
                { name: 'Update documentation', status: 'complete' },
            ],
        },
        milestone: {
            name: 'Backend API Development',
            deadline: 'September 5, 2025',
            progress: 75,
            incentive: 'Complete on time to earn a 5% bonus and 200 points!',
        }
    },
    {
        id: 3,
        name: 'Project Gemini',
        role: 'Manager',
        health: 'on-track',
        alert: null,
        overview: {
            milestone: '2 of 4 - Testing Phase (95% Complete)',
            budget: '$8,000 / $10,000 Used',
            status: 'In Progress',
        },
        tasks: {
            today: [
                { name: 'User Acceptance Testing', status: 'complete' },
            ],
            tomorrow: [
                { name: 'Deploy to staging', status: 'started' },
            ],
            completed: [
                { name: 'Initial QA Review', status: 'complete' },
            ],
        },
        communication: {
            lastSent: 'Yesterday at 11:00 AM',
            lastReceived: 'Yesterday at 10:45 AM',
        }
    },
    {
        id: 4,
        name: 'Project Alpha',
        role: 'Contributor',
        health: 'on-track',
        tasks: {
            today: [],
            tomorrow: [],
            completed: [
                { name: 'Initial project setup', status: 'complete' },
                { name: 'Write documentation', status: 'complete' },
            ],
        },
        milestone: {
            name: 'Project Kickoff',
            deadline: 'September 20, 2025',
            progress: 100,
            incentive: 'Complete on time to earn a 5% bonus and 200 points!',
        }
    },
]);

// State for filtering projects
const activeFilter = ref('all');
const searchTerm = ref('');

// Computed property to filter projects based on the active filter
const filteredProjects = computed(() => {
    if (activeFilter.value === 'all') {
        return projects.value;
    }
    if (activeFilter.value === 'manager') {
        return projects.value.filter(p => p.role === 'Manager');
    }
    if (activeFilter.value === 'contributor') {
        return projects.value.filter(p => p.role === 'Contributor');
    }
    // Return an empty array if filter is 'my' (this would be handled with a real user ID)
    return [];
});

// State for the checklist and notes, to be passed to the Sidebar
const checklistItems = ref([
    'Follow up with Alex on Project Phoenix',
    'Prepare for sprint planning',
]);
const notes = ref(localStorage.getItem('my_dashboard_notes') || '');

// Handler for when a new checklist item is added from the Sidebar
function handleAddChecklistItem(newItem) {
    if (newItem) {
        checklistItems.value.push(newItem);
    }
}

// Handler for when a checklist item is deleted from the Sidebar
function handleRemoveChecklistItem(index) {
    checklistItems.value.splice(index, 1);
}

// Handler for when a note is updated from the Sidebar
function handleUpdateNotes(newNotes) {
    notes.value = newNotes;
    localStorage.setItem('my_dashboard_notes', newNotes);
}

// Initialize currency conversion similar to Admin/ProjectExpendables
onMounted(async () => {
    try {
        const stored = localStorage.getItem('displayCurrency');
        if (stored) displayCurrency.value = stored;
        await fetchCurrencyRates();
    } catch (e) {
        console.warn('Currency initialization failed in Workspace/Index.vue:', e);
    }
});

</script>

<template>
    <Head title="My Workspace" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Workspace</h2>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-6">
                    <!-- Filters component emits 'update:filter' to change the active filter -->
                    <Filters @update:filter="activeFilter = $event" :active-filter="activeFilter" :search="searchTerm" @update:search="searchTerm = $event" />
                </div>

                <div class="flex flex-col lg:flex-row gap-8">
                    <div class="lg:w-2/3">
                        <!-- ProjectCards fetches and paginates from API; pass search term -->
                        <ProjectCards :search="searchTerm" />
                    </div>
                    <div class="lg:w-1/3">
                        <!-- Sidebar component receives and emits events for its data -->
                        <Sidebar
                            :checklist-items="checklistItems"
                            :notes="notes"
                            @add-checklist-item="handleAddChecklistItem"
                            @remove-checklist-item="handleRemoveChecklistItem"
                            @update-notes="handleUpdateNotes"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
