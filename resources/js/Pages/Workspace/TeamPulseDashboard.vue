<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomMultiSelect from '@/Components/CustomMultiSelect.vue';

// --- State ---
const currentView = ref('matrix');
const feedGroupBy = ref('user'); // 'user' (default) or 'project'
const filters = ref({
    range: '7',       // today | yesterday | 7 | 14 | 30 | custom
    startDate: null,  // used when range === 'custom'
    endDate: null,
});
const selectedProjects = ref([]); // array of project ids
const selectedMembers = ref([]); // array of user ids
const toast = ref({ show: false, message: '' });

// Remote + local state
const users = ref([]);
const projects = ref([]);
const standups = ref([]);
const matrixPayload = ref(null);
const statsPayload = ref(null);
const loading = ref({ filters: false, feed: false, matrix: false, stats: false });
const errors = ref({ filters: '', feed: '', matrix: '', stats: '' });
const allUsers = ref([]); // cache of full user list for "All Members"

const rangeOptions = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: '7', label: 'Last 7 Days' },
    { value: '14', label: 'Last 14 Days' },
    { value: '30', label: 'Last 30 Days' },
    { value: 'custom', label: 'Custom range' },
];

const rangeDisplayLabel = computed(() => {
    const mode = filters.value.range;
    const opt = rangeOptions.find(o => o.value === mode);
    if (mode === 'custom') {
        if (filters.value.startDate && filters.value.endDate) {
            return `${filters.value.startDate} → ${filters.value.endDate}`;
        }
        return opt?.label || 'Custom range';
    }
    return opt?.label || 'Last 7 Days';
});

// Options mapped for selects
const projectOptions = computed(() => (projects.value || []).map(p => ({
    id: p.id ?? p.project_id,
    name: p.name ?? p.project_name ?? 'Project',
})));
const userOptions = computed(() => (users.value || []).map(u => ({
    id: u.id ?? u.user_id,
    name: u.name ?? u.user_name ?? 'User',
})));

// --- API helpers ---
const buildParams = () => {
    const params = {};
    if (filters.value.range) params.range = filters.value.range;
    if (filters.value.range === 'custom') {
        if (filters.value.startDate) params.start_date = filters.value.startDate;
        if (filters.value.endDate) params.end_date = filters.value.endDate;
    }
    if (selectedProjects.value.length === 1) {
        params.project_id = selectedProjects.value[0];
    } else if (selectedProjects.value.length > 1) {
        params.project_ids = selectedProjects.value.join(',');
    }
    if (selectedMembers.value.length === 1) {
        params.user_id = selectedMembers.value[0];
    } else if (selectedMembers.value.length > 1) {
        params.user_ids = selectedMembers.value.join(',');
    }
    return params;
};

// Fallback loaders reused from workspace page to populate filters when analytics API omits them
const fetchProjectsFallback = async () => {
    try {
        const { data } = await window.axios.get('/api/projects-simplified');
        projects.value = Array.isArray(data) ? data : [];
    } catch (e) {
        errors.value.filters = errors.value.filters || 'Failed to load projects';
    }
};

const fetchAllUsers = async () => {
    try {
        const { data } = await window.axios.get('/api/users');
        const list = Array.isArray(data) ? data : [];
        allUsers.value = list;
        users.value = list;
    } catch (e) {
        errors.value.filters = errors.value.filters || 'Failed to load users';
    }
};

const fetchUsersForProjects = async (projectIds = []) => {
    if (!projectIds.length) {
        await fetchAllUsers();
        return;
    }
    try {
        const unique = new Map();
        await Promise.all(projectIds.map(async (pid) => {
            const { data } = await window.axios.get(`/api/projects/${pid}/users`);
            (Array.isArray(data) ? data : []).forEach(u => {
                const key = u.id ?? u.user_id;
                if (!unique.has(key)) unique.set(key, u);
            });
        }));
        const list = Array.from(unique.values());
        users.value = list;
        // keep allUsers cache for "All Members" reset
        if (!allUsers.value.length) allUsers.value = list;
    } catch (e) {
        errors.value.filters = errors.value.filters || 'Failed to load project users';
        // fallback to all users if available
        if (allUsers.value.length) {
            users.value = allUsers.value;
        }
    }
};

const fetchFilters = async () => {
    loading.value.filters = true;
    errors.value.filters = '';
    try {
        const { data } = await window.axios.get('/api/standups/analytics/filters');
        users.value = Array.isArray(data?.users) ? data.users : [];
        projects.value = Array.isArray(data?.projects) ? data.projects : [];
        if (data?.defaultRange) filters.value.range = String(data.defaultRange);

        // If API doesn't return users/projects, fall back to existing endpoints
        if (!projects.value.length) {
            await fetchProjectsFallback();
        }
        // Always ensure we have all users for "All Members"
        await fetchAllUsers();
    } catch (e) {
        errors.value.filters = 'Failed to load filters';
    } finally {
        loading.value.filters = false;
    }
};

const fetchFeed = async () => {
    loading.value.feed = true;
    errors.value.feed = '';
    try {
        const { data } = await window.axios.get('/api/standups/analytics/feed', { params: buildParams() });
        const list = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : [];
        standups.value = list.map((n, idx) => ({
            id: n.id ?? `feed-${idx}`,
            userId: n.user_id ?? n.userId ?? n.user?.id ?? null,
            projectId: n.project_id ?? n.projectId ?? n.project?.id ?? null,
            createdAt: n.created_at ?? n.createdAt ?? new Date().toISOString(),
            content: n.content ?? '',
            hasBlocker: Boolean(n.has_blocker ?? n.hasBlocker ?? n.blocker),
            taskId: n.task_id ?? n.taskId ?? null,
        }));
    } catch (e) {
        errors.value.feed = 'Failed to load feed';
    } finally {
        loading.value.feed = false;
    }
};

const fetchMatrix = async () => {
    loading.value.matrix = true;
    errors.value.matrix = '';
    try {
        const { data } = await window.axios.get('/api/standups/analytics/matrix', { params: buildParams() });
        matrixPayload.value = data || null;
        // If matrix response also carries users/projects, use them
        if (Array.isArray(data?.users) && !users.value.length) users.value = data.users;
        if (Array.isArray(data?.projects) && !projects.value.length) projects.value = data.projects;
    } catch (e) {
        errors.value.matrix = 'Failed to load matrix';
    } finally {
        loading.value.matrix = false;
    }
};

const fetchStats = async () => {
    loading.value.stats = true;
    errors.value.stats = '';
    try {
        const { data } = await window.axios.get('/api/standups/analytics/stats', { params: buildParams() });
        statsPayload.value = data || null;
    } catch (e) {
        errors.value.stats = 'Failed to load stats';
    } finally {
        loading.value.stats = false;
    }
};

const fetchAll = async () => {
    await Promise.all([fetchFilters(), fetchFeed(), fetchMatrix(), fetchStats()]);
};

onMounted(() => {
    fetchAll();
});

const refetchAllData = () => {
    fetchFeed();
    fetchMatrix();
    fetchStats();
};

watch(filters, refetchAllData, { deep: true });
watch([selectedProjects, selectedMembers], refetchAllData, { deep: true });
watch(selectedProjects, async (ids) => {
    // Update available users based on selected projects; empty = all users
    await fetchUsersForProjects(ids);
}, { deep: true });

// --- Date Helpers ---
const formatTime = (iso) => {
    const d = new Date(iso);
    const diff = Math.floor((new Date() - d) / (1000 * 60 * 60));
    if (diff < 24) {
        return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    } else if (diff < 48) {
        return 'Yesterday';
    }
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const isLate = (iso) => {
    const d = new Date(iso);
    return d.getHours() >= 10;
};

const getRelativeTime = (dateStr) => {
    const d = new Date(dateStr);
    const now = new Date();
    const diffMs = now - d;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'today';
    if (diffDays === 1) return 'yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
    return `${Math.floor(diffDays / 30)} months ago`;
};

// --- Computed Logic ---

// 1. Filtered Feed
const filteredFeed = computed(() => {
    return standups.value.filter(note => {
        // Project Filter
        if (selectedProjects.value.length && !selectedProjects.value.includes(note.projectId)) return false;
        // Member Filter
        if (selectedMembers.value.length && !selectedMembers.value.includes(note.userId)) return false;
        return true;
    });
});

// 2. Feed By Project (New Grouping)
const feedByProject = computed(() => {
    const groups = {};

    // Group filtered items by project
    filteredFeed.value.forEach(note => {
        if (!groups[note.projectId]) {
            groups[note.projectId] = {
                projectId: note.projectId,
                projectName: getProject(note.projectId).name,
                items: []
            };
        }
        groups[note.projectId].items.push(note);
    });

    // Convert to array and sort by latest update
    return Object.values(groups).sort((a, b) => {
        const dateA = a.items[0]?.createdAt || 0;
        const dateB = b.items[0]?.createdAt || 0;
        return new Date(dateB) - new Date(dateA);
    });
});

// 3. Matrix Headers (prefer API payload)
const dateHeaders = computed(() => {
    if (Array.isArray(matrixPayload.value?.headers) && matrixPayload.value.headers.length) {
        return matrixPayload.value.headers.map(h => ({
            name: h.name ?? h.label ?? h.date ?? '',
            date: h.display ?? h.pretty ?? h.date ?? '',
            full: h.full ?? h.value ?? h.date ?? '',
            isWeekend: Boolean(h.isWeekend ?? h.is_weekend),
        }));
    }
    const days = parseInt(filters.value.range || '7');
    const headers = [];
    for(let i = 0; i < days; i++) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        headers.push({
            name: d.toLocaleDateString('en-US', { weekday: 'short' }),
            date: d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
            full: d.toISOString().split('T')[0],
            isWeekend: d.getDay() === 0 || d.getDay() === 6
        });
    }
    return headers.reverse(); // Show Left=Today
});

// 4. Filtered Matrix Users (prefer API rows)
const filteredMatrixUsers = computed(() => {
    const base = Array.isArray(matrixPayload.value?.rows) && matrixPayload.value.rows.length
        ? matrixPayload.value.rows
        : users.value;
    if (selectedMembers.value.length) {
        return base.filter(u => selectedMembers.value.includes(u.id ?? u.user_id));
    }
    return base;
});

// 5. Ghost Projects
const ghostProjects = computed(() => {
    return (projects.value || []).filter(p => p.is_ghost || p.ghost || p.id === 103).map(p => ({
        ...p,
        lastActivity: p.last_standup ? getRelativeTime(p.last_standup) : getRelativeTime(p.lastStandup || new Date().toISOString())
    }));
});

// 6. Leaderboard
const sortedByStreak = computed(() => {
    return [...(users.value || [])]
        .map(u => ({ ...u, streak: u.streak ?? u.streak_days ?? 0 }))
        .sort((a,b) => (b.streak ?? 0) - (a.streak ?? 0))
        .slice(0,5);
});

// 7. Stats
const stats = computed(() => {
    if (statsPayload.value) {
        return {
            completion: Math.round(statsPayload.value.completion ?? 0),
            onTime: Math.round(statsPayload.value.on_time ?? statsPayload.value.onTime ?? 0),
            blockerRatio: Math.round(statsPayload.value.blocker_ratio ?? statsPayload.value.blockerRatio ?? 0),
        };
    }
    const total = filteredFeed.value.length;
    if (total === 0) return { completion: 0, onTime: 0, blockerRatio: 0 };

    const lateCount = filteredFeed.value.filter(n => isLate(n.createdAt)).length;
    const blockedCount = filteredFeed.value.filter(n => n.hasBlocker).length;

    return {
        completion: 85, // Mock static for demo
        onTime: Math.round(((total - lateCount) / total) * 100),
        blockerRatio: Math.round((blockedCount / total) * 100)
    };
});

// --- Helpers ---

const escapeHtml = (str) => {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const parseStandupContent = (content) => {
    if (!content || typeof content !== 'string') {
        return { date: '', yesterday: '', today: '', blockers: '' };
    }

    // If it already looks like HTML, just return it as-is via highlightKeywords
    const looksLikeHtml = /<\/p>|<br\s*\/?>/i.test(content);
    if (!looksLikeHtml) {
        const date = content.match(/\*\*Daily Standup - ([^*]+)\*\*/) ||
            content.match(/\*\*Daily Standup\s*-\s*([^\n*]+)\s*\*\*/) ||
            content.match(/\*\*Daily Standup\*\*\s*-?\s*([^\n*]+)/);

        const yesterday = content.match(/\*\*Yesterday:\*\*\s*([^*]+?)(?=\s*\*\*Today:|\s*\*\*Blockers:|$)/i);
        const today = content.match(/\*\*Today:\*\*\s*([^*]+?)(?=\s*\*\*Blockers:|$)/i);
        const blockers = content.match(/\*\*Blockers:\*\*\s*([^*]+)$/i);

        return {
            date: (date && date[1]?.trim()) || '',
            yesterday: (yesterday && yesterday[1]?.trim()) || '',
            today: (today && today[1]?.trim()) || '',
            blockers: (blockers && blockers[1]?.trim()) || '',
        };
    }

    return { date: '', yesterday: '', today: '', blockers: '' };
};

const getUser = (id) => {
    const list = Array.isArray(matrixPayload.value?.rows) && matrixPayload.value.rows.length ? matrixPayload.value.rows : users.value;
    const match = list.find(u => (u.id ?? u.user_id) === id);
    if (match) {
        return {
            id: match.id ?? match.user_id,
            name: match.name ?? match.user_name ?? 'Unknown',
            avatar: match.avatar ?? match.photo ?? '',
            streak: match.streak ?? match.streak_days ?? 0,
        };
    }
    return { name: 'Unknown', avatar: '' };
};
const getProject = (id) => {
    const match = projects.value.find(p => (p.id ?? p.project_id) === id);
    return match || { name: 'General' };
};

const highlightKeywords = (content) => {
    if (!content) return '';

    // If already HTML, show as-is
    if (/<[a-z][\s\S]*>/i.test(content)) {
        return content;
    }

    const parsed = parseStandupContent(content);
    if (!parsed.yesterday && !parsed.today && !parsed.blockers) {
        // Fallback: strip markdown ** and return simple text
        const plain = content.replace(/\*\*(.*?)\*\*/g, '$1');
        return `<p>${escapeHtml(plain)}</p>`;
    }

    const parts = [];
    if (parsed.date) {
        parts.push(
            `<p class="text-xs text-gray-500 font-medium mb-1">${escapeHtml(parsed.date)}</p>`
        );
    }
    parts.push(
        `<p class="mb-1"><strong>Yesterday:</strong> ${escapeHtml(parsed.yesterday || 'Nothing')}</p>`,
        `<p class="mb-1"><strong>Today:</strong> ${escapeHtml(parsed.today || 'Nothing')}</p>`,
        `<p class="mb-1"><strong>Blockers:</strong> ${escapeHtml(parsed.blockers || 'None')}</p>`,
    );

    return parts.join('');
};

// Matrix Logic: Returns HTML for the status icon
const getStatusIcon = (userId, dateStr) => {
    const d = new Date(dateStr);
    const isWeekend = d.getDay() === 0 || d.getDay() === 6;

    // Prefer matrix payload
    const row = (matrixPayload.value?.rows || []).find(r => (r.id ?? r.user_id) === userId);
    if (row && Array.isArray(row.statuses)) {
        const status = row.statuses.find(s => (s.date ?? s.full ?? s.day) === dateStr);
        if (status) {
            const label = String(status.status ?? status.value ?? '').toLowerCase();
            const submittedAt = status.submitted_at ?? status.time ?? null;
            const timeStr = submittedAt ? new Date(submittedAt).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : '';
            if (label === 'on_time' || label === 'ontime' || label === 'present') {
                return `<div class="mx-auto w-6 h-6 rounded-full bg-green-100 text-green-600 flex items-center justify-center cursor-help text-xs" title="${timeStr ? `Submitted at ${timeStr}` : 'On time'}">✓</div>`;
            }
            if (label === 'late') {
                return `<div class="mx-auto w-6 h-6 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center cursor-help text-xs" title="${timeStr ? `Late: ${timeStr}` : 'Late'}">⚠️</div>`;
            }
            if (label === 'off' || label === 'leave') {
                return `<div class="mx-auto w-6 h-6 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-[10px]" title="On Leave">OFF</div>`;
            }
            if (label === 'missed' || label === 'absent') {
                return `<div class="mx-auto w-6 h-6 rounded-full bg-red-100 text-red-600 flex items-center justify-center cursor-help text-xs" title="Missed Standup">✕</div>`;
            }
        }
    }

    // Fallback to feed-derived logic
    const note = standups.value.find(n => {
        const noteDate = new Date(n.createdAt).toISOString().split('T')[0];
        return n.userId === userId && noteDate === dateStr;
    });

    if (note) {
        const noteDate = new Date(note.createdAt);
        const timeStr = noteDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        if (isLate(note.createdAt)) {
            return `<div class="mx-auto w-6 h-6 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center cursor-help text-xs" title="Late: Submitted at ${timeStr}">⚠️</div>`;
        }
        return `<div class="mx-auto w-6 h-6 rounded-full bg-green-100 text-green-600 flex items-center justify-center cursor-help text-xs" title="Submitted at ${timeStr}">✓</div>`;
    }

    if (isWeekend) return `<span class="text-gray-300 text-xs">-</span>`;
    return `<div class="mx-auto w-6 h-6 rounded-full bg-red-100 text-red-600 flex items-center justify-center cursor-help text-xs" title="Missed Standup">✕</div>`;
};

// --- Actions ---

const showToast = (msg) => {
    toast.value = { show: true, message: msg };
    setTimeout(() => toast.value.show = false, 3000);
};

const sendKudo = (user) => {
    showToast(`Kudo sent to ${user.name} for their ${user.streak} day streak!`);
};

const copyAllStandups = () => {
    const text = filteredFeed.value.map(n => {
        const dateStr = new Date(n.createdAt).toLocaleDateString('en-US');
        const contentText = n.content.replace(/<[^>]*>?/gm, '');
        return `${getUser(n.userId).name} (${dateStr}): \n${contentText}`;
    }).join('\n---\n');

    navigator.clipboard.writeText(text).then(() => {
        showToast(`Copied ${filteredFeed.value.length} standups to clipboard`);
    });
};

const resetFilters = () => {
    filters.value = { range: '7', project: 'all', member: 'all' };
};
</script>

<template>
    <Head title="Team Pulse Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Team Pulse</h2>
                <div class="flex items-center gap-4">
                    <div class="text-sm text-gray-500 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        Live Data
                    </div>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Toast Notification -->
                <transition name="fade">
                    <div v-if="toast.show" class="fixed top-4 right-4 z-50 transform transition-all duration-300">
                        <div class="bg-gray-900 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3">
                            <span class="text-green-400 text-xl">✓</span>
                            <div>
                                <h4 class="font-bold text-sm">Success</h4>
                                <p class="text-xs text-gray-300">{{ toast.message }}</p>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- Controls & Filters -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <!-- Filter Bar -->
                    <div class="flex items-center bg-white p-1 rounded-lg shadow-sm border border-gray-200 overflow-visible max-w-full gap-2">
                        <!-- Date Range Filter -->
                        <div class="flex items-center gap-2">
                            <select v-model="filters.range" class="text-sm border-none focus:ring-0 text-gray-600 font-medium bg-transparent py-2 pl-3 pr-2 cursor-pointer hover:text-indigo-600">
                                <option v-for="opt in rangeOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <div v-if="filters.range === 'custom'" class="flex items-center gap-2 text-xs text-gray-600">
                                <input
                                    type="date"
                                    v-model="filters.startDate"
                                    class="border-gray-300 rounded-md text-xs px-2 py-1 focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <span>to</span>
                                <input
                                    type="date"
                                    v-model="filters.endDate"
                                    class="border-gray-300 rounded-md text-xs px-2 py-1 focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>

                        <div class="w-px h-6 bg-gray-200 mx-2"></div>

                        <!-- Project Filter -->
                        <div class="min-w-[220px]">
                            <CustomMultiSelect
                                v-model="selectedProjects"
                                :options="projectOptions"
                                placeholder="All Projects (searchable)"
                                label-key="name"
                                track-by="id"
                            />
                        </div>

                        <div class="w-px h-6 bg-gray-200 mx-2"></div>

                        <!-- Member Filter -->
                        <div class="min-w-[220px]">
                            <CustomMultiSelect
                                v-model="selectedMembers"
                                :options="userOptions"
                                placeholder="All Members (searchable)"
                                label-key="name"
                                track-by="id"
                            />
                        </div>
                    </div>

                    <!-- View Toggles -->
                    <div class="flex bg-gray-200 p-1 rounded-lg">
                        <button @click="currentView = 'matrix'"
                                :class="currentView === 'matrix' ? 'bg-white text-gray-800 shadow' : 'text-gray-600 hover:text-gray-800'"
                                class="px-4 py-2 text-sm font-semibold rounded-md transition-all duration-200">
                            Compliance Matrix
                        </button>
                        <button @click="currentView = 'feed'"
                                :class="currentView === 'feed' ? 'bg-white text-gray-800 shadow' : 'text-gray-600 hover:text-gray-800'"
                                class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200">
                            Daily Feed
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column: Main View Area -->
                    <div class="lg:col-span-2">
                        <!-- VIEW 1: Compliance Matrix -->
                        <div v-if="currentView === 'matrix'" class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
                            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <h3 class="text-lg font-semibold text-gray-800">
                                    Attendance: {{ rangeDisplayLabel }}
                                </h3>
                                <div class="flex gap-4 text-xs text-gray-500 font-medium">
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> On Time</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-500"></span> Late (>10am)</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Missed</span>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider border-b border-gray-100">
                                        <th class="px-6 py-4 font-semibold sticky left-0 bg-gray-50 z-10 shadow-sm">Team Member</th>
                                        <th v-for="day in dateHeaders" :key="day.full" class="px-4 py-3 text-center min-w-[80px]">
                                            {{ day.name }} <span class="block text-[10px] text-gray-400 font-normal">{{ day.date }}</span>
                                        </th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm">
                                    <tr v-for="user in filteredMatrixUsers" :key="user.id" class="hover:bg-gray-50 transition-colors group">
                                        <td class="px-6 py-4 font-medium text-gray-900 flex items-center gap-3 sticky left-0 bg-white group-hover:bg-gray-50 transition-colors z-10 border-r border-gray-100">
                                            <div class="relative">
                                                <img :src="user.avatar" class="w-8 h-8 rounded-full border border-gray-100">
                                                <div v-if="user.streak > 5" class="absolute -top-1 -right-1 flex h-3 w-3">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-orange-500"></span>
                                                </div>
                                            </div>
                                            {{ user.name }}
                                        </td>
                                        <td v-for="day in dateHeaders" :key="day.full" class="px-4 py-3 text-center">
                                            <div class="flex justify-center">
                                                <span v-html="getStatusIcon(user.id, day.full)"></span>
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- VIEW 2: Daily Feed -->
                        <div v-else class="space-y-6">
                            <!-- Feed Controls -->
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-2 px-1 gap-4">
                                <div class="flex items-center gap-2 text-sm text-gray-600 bg-white p-1.5 rounded-lg border border-gray-200 shadow-sm">
                                    <span class="font-medium px-2">Group by:</span>
                                    <button @click="feedGroupBy = 'user'"
                                            :class="feedGroupBy === 'user' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-800'"
                                            class="px-3 py-1 rounded-md transition-colors">
                                        Individual
                                    </button>
                                    <span class="text-gray-200">|</span>
                                    <button @click="feedGroupBy = 'project'"
                                            :class="feedGroupBy === 'project' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-800'"
                                            class="px-3 py-1 rounded-md transition-colors">
                                        Project
                                    </button>
                                </div>
                                <button @click="copyAllStandups" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-md transition-colors flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                    Copy visible
                                </button>
                            </div>

                            <!-- Empty State -->
                            <div v-if="filteredFeed.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-100">
                                <div class="bg-gray-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900">No standups found</h3>
                                <p class="text-gray-500 mt-1">Try adjusting your filters to see more data.</p>
                                <button @click="resetFilters" class="mt-4 text-indigo-600 font-medium hover:underline">Clear Filters</button>
                            </div>

                            <!-- MODE: Group By Project -->
                            <div v-if="feedGroupBy === 'project'" class="space-y-6">
                                <div v-for="group in feedByProject" :key="group.projectId"
                                     class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

                                    <!-- Project Header -->
                                    <div class="bg-gray-50 px-5 py-3 border-b border-gray-200 flex justify-between items-center">
                                        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                                            {{ group.projectName }}
                                            <span class="text-xs font-normal text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded-full">
                                                {{ group.items.length }} Updates
                                            </span>
                                        </h3>
                                        <div class="text-xs text-gray-500">
                                            {{ rangeDisplayLabel }}
                                        </div>
                                    </div>

                                    <!-- List of Member Updates within Project -->
                                    <div class="divide-y divide-gray-100">
                                        <div v-for="note in group.items" :key="note.id" class="p-5 hover:bg-gray-50/50 transition-colors">
                                            <div class="flex items-start gap-4">
                                                <!-- User Avatar -->
                                                <img :src="getUser(note.userId).avatar" class="w-10 h-10 rounded-full border border-gray-200 flex-shrink-0 mt-1">

                                                <div class="flex-grow">
                                                    <div class="flex justify-between items-center mb-2">
                                                        <div class="flex items-center gap-2">
                                                            <h4 class="font-bold text-gray-900 text-sm">{{ getUser(note.userId).name }}</h4>
                                                            <span class="text-gray-300">•</span>
                                                            <span class="text-xs text-gray-500">{{ formatTime(note.createdAt) }}</span>
                                                            <span v-if="isLate(note.createdAt)" class="text-yellow-600 bg-yellow-50 px-1.5 rounded font-bold text-[10px]">LATE</span>
                                                        </div>
                                                        <!-- Actions -->
                                                        <button class="text-gray-400 hover:text-indigo-600" title="View in Google Chat">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                                        </button>
                                                    </div>

                                                    <!-- Content -->
                                                    <div class="prose prose-sm max-w-none text-gray-700" v-html="highlightKeywords(note.content)"></div>

                                                    <!-- Linked Task -->
                                                    <div v-if="note.taskId" class="mt-3">
                                                        <a href="#" class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-gray-50 border border-gray-200 text-[11px] font-medium text-gray-600 hover:border-indigo-300 hover:text-indigo-600 transition-colors">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                                            Task #{{ note.taskId }}
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MODE: Individual Feed (Default) -->
                            <div v-else class="space-y-4">
                                <div v-for="note in filteredFeed" :key="note.id"
                                     class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 transition hover:shadow-md group relative overflow-hidden"
                                     :class="note.hasBlocker ? 'border-l-4 border-l-red-500' : 'border-l-4 border-l-green-500'">

                                    <!-- Header -->
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex items-center gap-3">
                                            <img :src="getUser(note.userId).avatar" class="w-10 h-10 rounded-full border border-gray-100">
                                            <div>
                                                <h4 class="font-bold text-gray-900">{{ getUser(note.userId).name }}</h4>
                                                <div class="text-xs text-gray-500 flex items-center gap-2 mt-0.5">
                                                    <span class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 font-medium">{{ getProject(note.projectId).name }}</span>
                                                    <span class="text-gray-300">•</span>
                                                    <span>{{ formatTime(note.createdAt) }}</span>
                                                    <span v-if="isLate(note.createdAt)" class="text-yellow-600 bg-yellow-50 px-1.5 rounded font-bold text-[10px]">LATE</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button class="text-gray-400 hover:text-indigo-600 p-1 hover:bg-gray-50 rounded" title="View in Google Chat">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="prose prose-sm max-w-none text-gray-700 ml-13 pl-10 border-l border-gray-100" v-html="highlightKeywords(note.content)"></div>

                                    <!-- Linked Task -->
                                    <div v-if="note.taskId" class="mt-4 pl-10 ml-3">
                                        <a href="#" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-gray-50 border border-gray-200 text-xs text-gray-600 hover:border-indigo-300 hover:text-indigo-600 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                            Linked to Task #{{ note.taskId }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Analytics & Widgets -->
                    <div class="space-y-6">
                        <!-- Widget 1: Streak Leaderboard -->
                        <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-800">🔥 Top Streaks</h3>
                            </div>
                            <ul class="space-y-4">
                                <li v-for="(user, idx) in sortedByStreak" :key="user.id" class="flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-6 text-center font-bold" :class="idx === 0 ? 'text-yellow-500 text-lg' : (idx === 1 ? 'text-gray-400 text-lg' : 'text-gray-300 text-sm')">
                                            {{ idx + 1 }}
                                        </div>
                                        <img :src="user.avatar" class="w-8 h-8 rounded-full border border-gray-100">
                                        <div class="text-sm">
                                            <p class="font-semibold text-gray-900">{{ user.name }}</p>
                                            <p class="text-xs text-gray-500">{{ user.streak }} Day Streak</p>
                                        </div>
                                    </div>
                                    <button @click="sendKudo(user)" class="opacity-0 group-hover:opacity-100 text-xs font-medium text-indigo-600 hover:bg-indigo-50 px-2 py-1 rounded transition-all">
                                        Send Kudo
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Widget 2: Ghost Projects Alert -->
                        <div v-if="ghostProjects.length > 0" class="bg-red-50 rounded-xl shadow-sm p-5 border border-red-100">
                            <div class="flex items-start gap-3">
                                <div class="bg-red-100 p-2 rounded-full text-red-600 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </div>
                                <div class="w-full">
                                    <h4 class="font-bold text-red-900 text-sm">Ghost Project Alert</h4>
                                    <p class="text-xs text-red-700 mt-1 mb-3">Projects with active tasks but 0 standups in 48h.</p>
                                    <div class="space-y-2">
                                        <div v-for="gp in ghostProjects" :key="gp.id" class="bg-white rounded px-3 py-2 border border-red-200 shadow-sm flex justify-between items-center">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-800">{{ gp.name }}</p>
                                                <p class="text-[10px] text-gray-500">Last standup: {{ gp.lastActivity }}</p>
                                            </div>
                                            <button class="text-xs text-red-600 hover:underline">Nudge Team</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Widget 3: Engagement Stats -->
                        <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Engagement Pulse</h3>
                            <div class="space-y-5">
                                <div>
                                    <div class="flex justify-between text-xs mb-1.5">
                                        <span class="text-gray-600 font-medium">Standup Completion</span>
                                        <span class="font-bold text-gray-900">{{ stats.completion }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-2">
                                        <div class="bg-indigo-600 h-2 rounded-full transition-all duration-1000" :style="{ width: stats.completion + '%' }"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs mb-1.5">
                                        <span class="text-gray-600 font-medium">On-Time Rate</span>
                                        <span class="font-bold text-gray-900">{{ stats.onTime }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-2">
                                        <div class="bg-yellow-500 h-2 rounded-full transition-all duration-1000" :style="{ width: stats.onTime + '%' }"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs mb-1.5">
                                        <span class="text-gray-600 font-medium">Blocker Ratio</span>
                                        <span class="font-bold text-gray-900">{{ stats.blockerRatio }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-2">
                                        <div class="bg-red-400 h-2 rounded-full transition-all duration-1000" :style="{ width: stats.blockerRatio + '%' }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>

