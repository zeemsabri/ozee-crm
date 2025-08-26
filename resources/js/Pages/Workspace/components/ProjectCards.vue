<script setup>
import { ref, computed, onMounted } from 'vue';
import ProjectCard from './ProjectCard.vue';

const loading = ref(true);
const error = ref(null);
const fetchedProjects = ref([]);

const fmtDateTime = (s) => {
    try { if (!s) return null; return new Date(s).toLocaleString(); } catch (e) { return null; }
};

const FOUR_HOURS_MS = 4 * 60 * 60 * 1000;
const formatRemaining = (ms) => {
    if (ms <= 0 || !isFinite(ms)) return null;
    const h = Math.floor(ms / (60 * 60 * 1000));
    const m = Math.floor((ms % (60 * 60 * 1000)) / (60 * 1000));
    if (h > 0 && m > 0) return `${h}h ${m}m`;
    if (h > 0) return `${h}h`;
    return `${m}m`;
};

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
const buildManagerCardFromApi = (p) => {
    const cm = p.current_milestone || null;
    const progressStr = cm ? `${cm.progress_percent ?? 0}% Complete` : 'No active milestone';
    const milestoneText = cm ? `${cm.name} (${progressStr})` : 'No active milestone';

    let budgetText = '$8,000 / $10,000 Used';
    if (cm && cm.budget && cm.budget.amount != null) {
        const cur = cm.budget.currency || '';
        const approved = cm.budget.approved_amount ?? 0;
        const total = cm.budget.amount ?? 0;
        budgetText = `${approved}${cur ? ' ' + cur : ''} / ${total}${cur ? ' ' + cur : ''} Used`;
    }
    const card = {
        id: p.id,
        name: p.name,
        role: 'Manager',
        health: mapStatusToHealth(p.status),
        alert: null,
        overview: {
            milestone: milestoneText,
            budget: budgetText,
            status: 'In Progress',
        },
        tasks: {
            today: (p.tasks?.today || []).map(t => ({ id: t.id, name: t.name, status: t.status })),
            tomorrow: (p.tasks?.tomorrow || []).map(t => ({ id: t.id, name: t.name, status: t.status })),
            completed: [],
        },
        communication: {
            lastSent: fmtDateTime(p.last_email_sent) || '—',
            lastReceived: fmtDateTime(p.last_email_received) || '—',
        },
    };

    // Compute alert based on last received email within last 4 hours
    if (p.last_email_received) {
        const receivedAt = new Date(p.last_email_received);
        const ts = receivedAt.getTime();
        if (!isNaN(ts)) {
            const elapsed = Date.now() - ts;
            const remaining = FOUR_HOURS_MS - elapsed;
            const timer = formatRemaining(remaining);
            if (timer) {
                card.alert = {
                    text: `Client email from ${card.name} requires a reply.`,
                    timer,
                    incentive: 'Reply in time to earn 50 points.',
                };
            }
        }
    }

    return card;
};

const buildContributorCardFromApi = (p) => {
    const cm = p.current_milestone || null;
    const card = {
        id: p.id,
        name: p.name,
        role: 'Contributor',
        health: mapStatusToHealth(p.status),
        tasks: {
            today: (p.tasks?.today || []).map(t => ({ id: t.id, name: t.name, status: t.status })),
            tomorrow: (p.tasks?.tomorrow || []).map(t => ({ id: t.id, name: t.name, status: t.status })),
            completed: [],
        },
        milestone: {
            name: cm?.name || 'Current Milestone',
            deadline: cm?.deadline || '',
            progress: cm?.progress_percent ?? 0,
            completed: typeof cm?.tasks_done === 'number' ? cm.tasks_done : null,
            left: (typeof cm?.tasks_total === 'number' && typeof cm?.tasks_done === 'number') ? Math.max(0, cm.tasks_total - cm.tasks_done) : null,
            incentive: '',
        },
    };

    // Compute alert based on last received email within last 4 hours
    if (p.last_email_received) {
        const receivedAt = new Date(p.last_email_received);
        const ts = receivedAt.getTime();
        if (!isNaN(ts)) {
            const elapsed = Date.now() - ts;
            const remaining = FOUR_HOURS_MS - elapsed;
            const timer = formatRemaining(remaining);
            if (timer) {
                card.alert = {
                    text: `Client email from ${card.name} requires a reply.`,
                    timer,
                    incentive: 'Reply in time to earn 50 points.',
                };
            }
        }
    }

    return card;
};

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
