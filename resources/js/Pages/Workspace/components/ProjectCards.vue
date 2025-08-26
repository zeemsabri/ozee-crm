<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import ProjectCard from './ProjectCard.vue';

const props = defineProps({
    search: { type: String, default: '' },
    activeFilter: { type: String, default: 'all' },
});

const loading = ref(true);
const error = ref(null);
const fetchedProjects = ref([]);

// Apply top-bar filter to fetched projects
const filteredProjects = computed(() => {
    const all = fetchedProjects.value || [];
    const f = (props.activeFilter || 'all').toLowerCase();
    if (f === 'manager') return all.filter(p => p.role === 'Manager');
    if (f === 'contributor') return all.filter(p => p.role === 'Contributor');
    return all; // 'all'
});

// Pagination & infinite scroll state
const page = ref(1);
const lastPage = ref(1);
const perPage = 5;
const loadingMore = ref(false);
const sentinel = ref(null);
let observer = null;

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

const isPaginatedResponse = (resp) => {
    return resp && typeof resp === 'object' && Array.isArray(resp.data) && typeof resp.current_page !== 'undefined';
};

const loadFirstPage = async () => {
    loading.value = true;
    error.value = null;
    page.value = 1;
    lastPage.value = 1;
    fetchedProjects.value = [];
    try {
        const { data } = await window.axios.get('/api/workspace/projects', { params: { page: page.value, per_page: perPage, search: props.search || undefined } });
        const resp = data;
        if (Array.isArray(resp)) {
            // Legacy non-paginated response
            fetchedProjects.value = resp.map(mapApiProjectToCard);
            lastPage.value = 1;
            page.value = 1;
        } else if (isPaginatedResponse(resp)) {
            fetchedProjects.value = (resp.data || []).map(mapApiProjectToCard);
            page.value = resp.current_page || 1;
            lastPage.value = resp.last_page || 1;
        } else {
            fetchedProjects.value = [];
            lastPage.value = 1;
        }
    } catch (e) {
        console.error('Failed to load workspace projects', e);
        error.value = 'Failed to load your projects.';
        fetchedProjects.value = [];
        lastPage.value = 1;
    } finally {
        loading.value = false;
    }
};

const fetchNextPage = async () => {
    if (loading.value || loadingMore.value) return;
    if (page.value >= lastPage.value) return;
    loadingMore.value = true;
    try {
        const next = page.value + 1;
        const { data } = await window.axios.get('/api/workspace/projects', { params: { page: next, per_page: perPage, search: props.search || undefined } });
        const resp = data;
        if (Array.isArray(resp)) {
            // Legacy array response, treat as no more pages
            const items = resp.map(mapApiProjectToCard);
            if (items.length) {
                fetchedProjects.value = fetchedProjects.value.concat(items);
            }
            page.value = next;
            lastPage.value = next; // stop further loads
        } else if (isPaginatedResponse(resp)) {
            const items = (resp.data || []).map(mapApiProjectToCard);
            fetchedProjects.value = fetchedProjects.value.concat(items);
            page.value = resp.current_page || next;
            lastPage.value = resp.last_page || page.value;
        }
    } catch (e) {
        console.error('Failed to load more workspace projects', e);
        // Keep existing items; optionally set error message (not blocking)
    } finally {
        loadingMore.value = false;
    }
};

function setupObserver() {
    if (observer) {
        observer.disconnect();
    }
    observer = new IntersectionObserver((entries) => {
        const entry = entries[0];
        if (entry && entry.isIntersecting) {
            fetchNextPage();
        }
    }, { root: null, rootMargin: '200px', threshold: 0 });

    if (sentinel.value) {
        observer.observe(sentinel.value);
    }
}

// Refetch when search term changes
watch(() => props.search, async () => {
    // Reset observer and reload first page with new search
    if (observer) {
        observer.disconnect();
        observer = null;
    }
    await loadFirstPage();
    await nextTick();
    setupObserver();
});

onMounted(async () => {
    await loadFirstPage();
    await nextTick();
    setupObserver();
});

onUnmounted(() => {
    if (observer) {
        observer.disconnect();
        observer = null;
    }
});
</script>

<template>
    <div class="space-y-6">
        <!-- Initial skeleton screen -->
        <div v-if="loading" class="space-y-6">
            <div v-for="i in 5" :key="'skeleton-'+i" class="bg-white rounded-xl shadow-md p-6">
                <div class="animate-pulse space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="h-6 w-1/3 bg-gray-200 rounded"></div>
                        <div class="h-4 w-24 bg-gray-200 rounded"></div>
                    </div>
                    <div class="h-4 w-1/2 bg-gray-200 rounded"></div>
                    <div class="h-24 w-full bg-gray-200 rounded"></div>
                </div>
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="flex justify-center items-center h-48 bg-white rounded-xl shadow-md p-6 text-red-500">
            <p>{{ error }}</p>
        </div>

        <!-- Empty -->
        <div v-else-if="filteredProjects.length === 0" class="flex justify-center items-center h-48 bg-white rounded-xl shadow-md p-6 text-gray-500">
            <p>No projects to display.</p>
        </div>

        <!-- Project cards -->
        <template v-else>
            <ProjectCard v-for="project in filteredProjects" :key="project.id" :project="project" />

            <!-- Incremental skeleton loader -->
            <div v-if="loadingMore" class="bg-white rounded-xl shadow-md p-6">
                <div class="animate-pulse space-y-4">
                    <div class="h-6 w-1/3 bg-gray-200 rounded"></div>
                    <div class="h-4 w-1/2 bg-gray-200 rounded"></div>
                    <div class="h-24 w-full bg-gray-200 rounded"></div>
                </div>
            </div>

            <!-- Sentinel for infinite scroll -->
            <div v-show="page < lastPage" ref="sentinel" class="h-2"></div>
            <div v-if="page >= lastPage && fetchedProjects.length > 0" class="text-center text-sm text-gray-400 py-2">
                End of list
            </div>
        </template>
    </div>
</template>
