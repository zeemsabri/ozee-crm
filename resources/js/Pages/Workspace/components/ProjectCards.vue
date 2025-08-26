<script setup>
import { ref, computed, onMounted } from 'vue';
import ProjectCard from './ProjectCard.vue';

const loading = ref(true);
const error = ref(null);
const fetchedProjects = ref([]);

const mapApiRoleToUi = (role) => {
    // Both manager and admin are treated as Manager view for now; others as Contributor
    if (!role) return 'Contributor';
    return (role === 'manager' || role === 'admin') ? 'Manager' : 'Contributor';
};

const mapStatusToHealth = (status) => {
    if (status === 'active') return 'on-track';
    if (status === 'on_hold') return 'needs-attention';
    if (status === 'completed') return 'on-track'; // Assuming completed projects are "on-track"
    return 'at-risk';
};

// These functions create a rich, static data structure based on the simple API response.
// In a production app, the API would return this full data.
const buildStaticManagerCard = (base) => ({
    id: base.id,
    name: base.name,
    role: 'Manager',
    health: mapStatusToHealth(base.status),
    alert: null, // keep null unless we want to show a static alert
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
    },
});

const buildStaticContributorCard = (base) => ({
    id: base.id,
    name: base.name,
    role: 'Contributor',
    health: mapStatusToHealth(base.status),
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
    },
});

const mapApiProjectToCard = (p) => {
    // The API role is "doer", but our UI expects "Manager" or "Contributor"
    // For this example, we'll assign the role randomly for demonstration
    // In a real application, this would be based on user-specific data from the API
    const roles = ['Manager', 'Contributor'];
    const randomRole = roles[Math.floor(Math.random() * roles.length)];
    const base = {
        id: p.id,
        name: p.name,
        status: p.status,
        project_type: p.project_type,
        tags: p.tags || [],
    };
    return randomRole === 'Manager' ? buildStaticManagerCard(base) : buildStaticContributorCard(base);
};

const loadProjects = async () => {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await window.axios.get('/api/workspace/projects');

        fetchedProjects.value = (Array.isArray(data) ? data : []).map(mapApiProjectToCard);
    } catch (e) {
        console.error('Failed to load workspace projects', e);
        error.value = 'Failed to load your projects.';
        fetchedProjects.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadProjects();
});
</script>

<template>
    <div class="space-y-6">
        <div v-if="loading" class="flex justify-center items-center h-48 bg-white rounded-xl shadow-md p-6 text-gray-500">
            <p>Loading your projects...</p>
        </div>
        <div v-else-if="error" class="flex justify-center items-center h-48 bg-white rounded-xl shadow-md p-6 text-red-500">
            <p>{{ error }}</p>
        </div>
        <div v-else-if="fetchedProjects.length === 0" class="flex justify-center items-center h-48 bg-white rounded-xl shadow-md p-6 text-gray-500">
            <p>No projects to display.</p>
        </div>
        <ProjectCard v-for="project in fetchedProjects" :key="project.id" :project="project" />
    </div>
</template>
