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
const buildManagerCardFromApi = (p) => ({
    id: p.id,
    name: p.name,
    role: 'Manager',
    health: mapStatusToHealth(p.status),
    alert: null,
    overview: {
        milestone: 'Milestones updated',
        budget: '$8,000 / $10,000 Used',
        status: 'In Progress',
    },
    tasks: {
        today: (p.tasks?.today || []).map(t => ({ name: t.name, status: t.status })),
        tomorrow: (p.tasks?.tomorrow || []).map(t => ({ name: t.name, status: t.status })),
        completed: [],
    },
    communication: {
        lastSent: 'Yesterday at 11:00 AM',
        lastReceived: 'Yesterday at 10:45 AM',
    },
});

const buildContributorCardFromApi = (p) => ({
    id: p.id,
    name: p.name,
    role: 'Contributor',
    health: mapStatusToHealth(p.status),
    tasks: {
        today: (p.tasks?.today || []).map(t => ({ name: t.name, status: t.status })),
        tomorrow: (p.tasks?.tomorrow || []).map(t => ({ name: t.name, status: t.status })),
        completed: [],
    },
    milestone: {
        name: 'Current Milestone',
        deadline: '',
        progress: 0,
        incentive: '',
    },
});

const mapApiProjectToCard = (p) => {
    const uiRole = mapApiRoleToUi(p.role);
    // Use API-provided tasks directly
    return uiRole === 'Manager' ? buildManagerCardFromApi(p) : buildContributorCardFromApi(p);
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
