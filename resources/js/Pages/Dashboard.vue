<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import NotesModal from '@/Components/NotesModal.vue';
import StandupModal from '@/Components/StandupModal.vue';
import TaskNotificationPrompt from '@/Components/TaskNotificationPrompt.vue';
import AvailabilityPrompt from '@/Components/Availability/AvailabilityPrompt.vue';
import * as taskState from '@/Utils/taskState.js';

// Import Dashboard Components
import WelcomeCard from '@/Components/Dashboard/WelcomeCard.vue';
import PendingTasksCard from '@/Components/Dashboard/PendingTasksCard.vue';
import DashboardOverviewCard from '@/Components/Dashboard/DashboardOverviewCard.vue';
import WeeklyAvailabilityCard from '@/Components/Dashboard/WeeklyAvailabilityCard.vue';
import ProjectsCard from '@/Components/Dashboard/ProjectsCard.vue';
import AssignedTasksCard from '@/Components/Dashboard/AssignedTasksCard.vue';
import UpcomingMeetingsCard from '@/Components/Dashboard/UpcomingMeetingsCard.vue';
import DueTasksBreakdownCard from '@/Components/Dashboard/DueTasksBreakdownCard.vue';
import NoticeBoardCard from '@/Components/Dashboard/NoticeBoardCard.vue';

// Props
const props = defineProps({
    projectCount: Number,
});

// Notes modal state
const showNotesModal = ref(false);
const selectedProjectId = ref(null);

// Standup modal state
const showStandupModal = ref(false);
const selectedProjectIdForStandup = ref(null);

// Reference to the assigned tasks section for scrolling and method calls
const assignedTasksCardRef = ref(null);

// Reference to the upcoming meetings section for scrolling and method calls
const upcomingMeetingsCardRef = ref(null);

// Assigned tasks state (now managed locally in Dashboard from emitted events)
const overdueTasksCount = ref(0);
const dueTodayTasksCount = ref(0);
const totalDueTasksCount = computed(() => overdueTasksCount.value + dueTodayTasksCount.value);

// Handle task count updates from the AssignedTasksCard component
const handleTaskCountsUpdated = ({ overdue, dueToday }) => {
    overdueTasksCount.value = overdue;
    dueTodayTasksCount.value = dueToday;
};

// Fetch data on component mount
onMounted(() => {
    // We no longer need to fetch assigned tasks here, as the child component handles it.
});

// --- Notes Modal Logic ---
const openNotesModal = (projectId) => {
    selectedProjectId.value = projectId;
    showNotesModal.value = true;
};

const handleNoteAdded = () => {
    // This will trigger a re-fetch in the ProjectsCard component
};

// --- Standup Modal Logic ---
const openStandupModal = (projectId) => {
    selectedProjectIdForStandup.value = projectId;
    showStandupModal.value = true;
};

const handleStandupAdded = () => {
    showStandupModal.value = false;
};

// Handle view button click from notification prompt
const handleViewDueAndOverdueTasks = () => {
    // Call the method on the child component to expand the section
    if (assignedTasksCardRef.value) {
        assignedTasksCardRef.value.showDueOverdueTasksAndScroll();
    }
};

// Handle view meetings click
const handleViewMeetingsAndScroll = () => {
    // Call the method on the child component to scroll and expand the section
    if (upcomingMeetingsCardRef.value) {
        upcomingMeetingsCardRef.value.showMeetingsAndScroll();
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Task Notification Prompt -->
                <TaskNotificationPrompt
                    :overdue-tasks="overdueTasksCount"
                    :due-today-tasks="dueTodayTasksCount"
                    @view-tasks="handleViewDueAndOverdueTasks"
                />

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Welcome Card / Quick Stats -->
                    <WelcomeCard :project-count="projectCount" />

                    <!-- Total Due Tasks Card -->
                    <PendingTasksCard :total-due-tasks="totalDueTasksCount" @card-clicked="handleViewDueAndOverdueTasks"/>

                    <!-- Availability Prompt (Conditionally displayed, spans full width) -->
                    <div class="md:col-span-3">
                        <AvailabilityPrompt />
                    </div>

                    <!-- Dashboard Overview Card -->
                    <DashboardOverviewCard
                        :due-today-tasks-count="dueTodayTasksCount"
                        :overdue-tasks-count="overdueTasksCount"
                        @view-tasks="handleViewDueAndOverdueTasks"
                        @view-meetings="handleViewMeetingsAndScroll"
                    />

                    <!-- Weekly Availability Card -->
                    <WeeklyAvailabilityCard />

                    <!-- Assigned Tasks Card -->
                    <AssignedTasksCard
                        ref="assignedTasksCardRef"
                        @task-counts-updated="handleTaskCountsUpdated"
                    />

                    <!-- Projects Card -->
                    <ProjectsCard
                        :project-count="projectCount"
                        @open-notes-modal="openNotesModal"
                        @open-standup-modal="openStandupModal"
                    />

                    <!-- Upcoming Meetings Card -->
                    <UpcomingMeetingsCard
                        ref="upcomingMeetingsCardRef"
                        class="upcoming-meetings-card"
                    />

                    <!-- Due Tasks Breakdown Card -->
                    <DueTasksBreakdownCard />

                    <!-- Notice Board Card -->
                    <NoticeBoardCard />
                </div>
            </div>
        </div>

        <!-- Notes Modal -->
        <NotesModal
            :show="showNotesModal"
            :project-id="selectedProjectId"
            @close="showNotesModal = false"
            @note-added="handleNoteAdded"
        />

        <!-- Standup Modal -->
        <StandupModal
            :show="showStandupModal"
            :project-id="selectedProjectIdForStandup"
            @close="showStandupModal = false"
            @standup-added="handleStandupAdded"
        />
    </AuthenticatedLayout>
</template>
