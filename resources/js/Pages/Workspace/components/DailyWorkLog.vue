<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/Directives/permissions.js';
import * as taskState from '@/Utils/taskState.js';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TaskNoteModal from '@/Components/ProjectTasks/TaskNoteModal.vue';
import { openTaskDetailSidebar } from '@/Utils/sidebar';

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatDate = (d) => {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};
const parseLocal = (dateStr) => {
    const [y, m, day] = dateStr.split('-');
    return new Date(y, m - 1, day);
};
const displayDate = (dateStr) => {
    return parseLocal(dateStr).toLocaleDateString('en-AU', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    });
};

const todayStr = formatDate(new Date());

// ─── State ────────────────────────────────────────────────────────────────────
const viewDate = ref(todayStr);
const dailyItems = ref([]);
const availableTasks = ref([]);
const historyData = ref({});
const users = ref([]);
const selectedUserId = ref(usePage().props.auth.user.id);

const { canDo } = usePermissions();
const canViewOthers = computed(() => canDo('view_all_projects').value);

const loadingLog = ref(false);
const loadingPool = ref(false);
const savingOrder = ref(false);
const busyTaskIds = ref(new Set()); // tasks currently mid-API call
const showNoteModal = ref(false);
const taskToNote = ref(null);

const searchPool = ref('');
const showHistory = ref(false);
const showTaskPicker = ref(false);
const showTips = ref(localStorage.getItem('dwl_tips_dismissed') !== '1');

// Warning modal state (shown when user tries to complete a non-started task)
const warnModal = ref({ show: false, item: null, mode: '' });

// Drag state
const draggingId = ref(null);
const dragOverId = ref(null);

// ─── Computed ─────────────────────────────────────────────────────────────────
const isToday = computed(() => viewDate.value === todayStr);
const isFuture = computed(() => viewDate.value > todayStr);

const filteredPool = computed(() => {
    const q = searchPool.value.toLowerCase().trim();
    const existingIds = new Set(dailyItems.value.map(d => d.task_id));
    return availableTasks.value.filter(t => {
        if (existingIds.has(t.id)) return false;
        const done = t.status === 'Done' || t.status === 'Archived';
        if (done) return false;
        if (!q) return true;
        return (
            String(t.name || '').toLowerCase().includes(q) ||
            String(t.milestone?.project?.name || '').toLowerCase().includes(q) ||
            String(t.milestone?.name || '').toLowerCase().includes(q)
        );
    });
});

const pendingCount   = computed(() => dailyItems.value.filter(d => d.status === 'pending').length);
const completedCount = computed(() => dailyItems.value.filter(d => d.status === 'completed').length);
const pushedCount    = computed(() => dailyItems.value.filter(d => d.status === 'pushed_to_next_day').length);

const historyDays = computed(() =>
    Object.entries(historyData.value).sort(([a], [b]) => b.localeCompare(a))
);

// ─── Task lifecycle helpers ───────────────────────────────────────────────────
const taskStatus = (item) => item.task?.status ?? 'To Do';

const canStart   = (item) => ['To Do', 'Blocked'].includes(taskStatus(item));
const canResume  = (item) => taskStatus(item) === 'Paused';
const canPause   = (item) => taskStatus(item) === 'In Progress';
const canComplete= (item) => taskStatus(item) === 'In Progress';
const isFinished = (item) => taskStatus(item) === 'Done';

/** What the primary green CTA button should say/do */
const primaryAction = (item) => {
    if (isFinished(item))   return { label: '✓ Done', disabled: true, type: 'done' };
    if (canComplete(item))  return { label: '✓ Complete', type: 'complete' };
    if (canResume(item))    return { label: '▷ Resume', type: 'resume' };
    if (canPause(item))     return { label: '⏸ Pause', type: 'pause' };
    return { label: '▷ Start', type: 'start' };
};

const isBusy = (item) => busyTaskIds.value.has(item.task_id);

const setBusy = (item, val) => {
    const s = new Set(busyTaskIds.value);
    if (val) s.add(item.task_id); else s.delete(item.task_id);
    busyTaskIds.value = s;
};

// ─── Task lifecycle actions ───────────────────────────────────────────────────
const doStart = async (item) => {
    setBusy(item, true);
    try {
        const updated = await taskState.startTask(item.task);
        item.task = { ...item.task, ...updated };
    } catch (_) {}
    setBusy(item, false);
};

const doResume = async (item) => {
    setBusy(item, true);
    try {
        const updated = await taskState.resumeTask(item.task);
        item.task = { ...item.task, ...updated };
    } catch (_) {}
    setBusy(item, false);
};

const doPause = async (item) => {
    setBusy(item, true);
    try {
        const updated = await taskState.pauseTask(item.task);
        item.task = { ...item.task, ...updated };
    } catch (_) {}
    setBusy(item, false);
};

const doComplete = async (item) => {
    // Guard: must be In Progress
    if (!canComplete(item)) {
        warnModal.value = { show: true, item, mode: 'complete' };
        return;
    }
    setBusy(item, true);
    try {
        const updated = await taskState.completeTask(item.task);
        item.task = { ...item.task, ...updated };
        // Sync daily log status
        await window.axios.patch(`/api/daily-tasks/${item.id}`, { status: 'completed' });
        item.status = 'completed';
        if (showHistory.value) await loadHistory();
    } catch (_) {}
    setBusy(item, false);
};

/** Called when user confirms "start then complete" from the warning modal */
const doStartThenComplete = async () => {
    const item = warnModal.value.item;
    warnModal.value = { show: false, item: null, mode: '' };
    if (!item) return;
    setBusy(item, true);
    try {
        // Step 1: start
        const started = await taskState.startTask(item.task);
        item.task = { ...item.task, ...started };
        // Step 2: complete
        const completed = await taskState.completeTask(item.task);
        item.task = { ...item.task, ...completed };
        await window.axios.patch(`/api/daily-tasks/${item.id}`, { status: 'completed' });
        item.status = 'completed';
        if (showHistory.value) await loadHistory();
    } catch (_) {}
    setBusy(item, false);
};

const dismissWarn = () => { warnModal.value = { show: false, item: null, mode: '' }; };

const handlePrimary = (item) => {
    const a = primaryAction(item);
    if (a.disabled) return;
    if (a.type === 'start')    doStart(item);
    if (a.type === 'resume')   doResume(item);
    if (a.type === 'pause')    doPause(item);
    if (a.type === 'complete') doComplete(item);
};

// ─── API helpers ──────────────────────────────────────────────────────────────
const loadDailyLog = async () => {
    loadingLog.value = true;
    try {
        const { data } = await window.axios.get('/api/daily-tasks', { 
            params: { 
                date: viewDate.value,
                user_id: selectedUserId.value
            } 
        });
        dailyItems.value = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error('Failed to load daily log', e);
    } finally {
        loadingLog.value = false;
    }
};

const loadUsers = async () => {
    if (!canViewOthers.value) return;
    try {
        const { data } = await window.axios.get('/api/users');
        users.value = (Array.isArray(data) ? data : []).map(u => ({
            value: u.id,
            label: u.name
        }));
    } catch (e) {
        console.error('Failed to load users', e);
    }
};

const loadAvailablePool = async () => {
    // If viewing someone else's log, we don't necessarily show our task pool for adding
    // but the request said "should be able to see daily log of other users".
    // Usually adding tasks is for yourself.
    if (selectedUserId.value !== usePage().props.auth.user.id) {
        availableTasks.value = [];
        return;
    }
    loadingPool.value = true;
    try {
        const { data } = await window.axios.get('/api/tasks', {
            params: { assigned_to_me: 1, statuses: 'To Do,In Progress,Paused,Blocked', per_page: 200 },
        });
        availableTasks.value = Array.isArray(data) ? data : (data?.data || []);
    } catch (e) {
        console.error('Failed to load task pool', e);
    } finally {
        loadingPool.value = false;
    }
};

const loadHistory = async () => {
    try {
        const { data } = await window.axios.get('/api/daily-tasks/history', { 
            params: { 
                days: 14,
                user_id: selectedUserId.value,
                today: formatDate(new Date())
            } 
        });
        historyData.value = data || {};
    } catch (e) {
        console.error('Failed to load history', e);
    }
};

const addTasksToLog = async (taskIds) => {
    try {
        const { data } = await window.axios.post('/api/daily-tasks', { task_ids: taskIds, date: viewDate.value });
        const newItems = Array.isArray(data) ? data : [];
        const existingIds = new Set(dailyItems.value.map(d => d.id));
        for (const item of newItems) {
            if (!existingIds.has(item.id)) dailyItems.value.push(item);
        }
        showTaskPicker.value = false;
        searchPool.value = '';
        selectedPoolIds.value = new Set();
    } catch (e) {
        console.error('Failed to add tasks', e);
    }
};

const saveOrder = async () => {
    savingOrder.value = true;
    try {
        await window.axios.post('/api/daily-tasks/reorder', {
            date: viewDate.value,
            ordered_ids: dailyItems.value.map(d => d.id),
        });
    } catch (e) {
        console.error('Failed to save order', e);
    } finally {
        savingOrder.value = false;
    }
};

const pushToTomorrow = async (item) => {
    try {
        await window.axios.post(`/api/daily-tasks/${item.id}/push-to-tomorrow`);
        item.status = 'pushed_to_next_day';
        if (showHistory.value) await loadHistory();
    } catch (e) {
        console.error('Failed to push to tomorrow', e);
    }
};

const removeFromLog = async (item) => {
    try {
        await window.axios.delete(`/api/daily-tasks/${item.id}`);
        dailyItems.value = dailyItems.value.filter(d => d.id !== item.id);
    } catch (e) {
        console.error('Failed to remove from log', e);
    }
};

const openSidebar = (task) => {
    if (!task) return;
    openTaskDetailSidebar(task.id, task.milestone?.project_id, []);
};

const openNoteModal = (task) => {
    taskToNote.value = task;
    showNoteModal.value = true;
};

// ─── Drag & Drop ──────────────────────────────────────────────────────────────
const onDragStart = (item) => { draggingId.value = item.id; };
const onDragOver  = (item, e) => { e.preventDefault(); dragOverId.value = item.id; };
const onDrop = async (targetItem, e) => {
    e.preventDefault();
    const fromId = draggingId.value;
    const toId   = targetItem.id;
    draggingId.value = null; dragOverId.value = null;
    if (!fromId || fromId === toId) return;
    const fromIdx = dailyItems.value.findIndex(d => d.id === fromId);
    const toIdx   = dailyItems.value.findIndex(d => d.id === toId);
    if (fromIdx === -1 || toIdx === -1) return;
    const items = [...dailyItems.value];
    const [moved] = items.splice(fromIdx, 1);
    items.splice(toIdx, 0, moved);
    dailyItems.value = items;
    await saveOrder();
};
const onDragEnd = () => { draggingId.value = null; dragOverId.value = null; };

// ─── Date navigation ──────────────────────────────────────────────────────────
const goToDate = (offset) => {
    const d = parseLocal(viewDate.value);
    d.setDate(d.getDate() + offset);
    viewDate.value = formatDate(d);
};

// ─── UI helpers ───────────────────────────────────────────────────────────────
const taskStatusStyle = (status) => {
    const map = {
        'To Do':       { bg: '#eff6ff', color: '#1d4ed8', border: '#bfdbfe' },
        'In Progress': { bg: '#ecfdf5', color: '#065f46', border: '#6ee7b7' },
        'Paused':      { bg: '#fffbeb', color: '#92400e', border: '#fcd34d' },
        'Blocked':     { bg: '#fef2f2', color: '#991b1b', border: '#fca5a5' },
        'Done':        { bg: '#ede9fe', color: '#5b21b6', border: '#c4b5fd' },
    };
    return map[status] || { bg: '#f3f4f6', color: '#374151', border: '#e5e7eb' };
};

const logStatusLabel = (status) => ({
    pending: 'Pending',
    completed: 'Completed',
    pushed_to_next_day: 'Pushed',
}[status] || status);

const primaryBtnStyle = (item) => {
    const a = primaryAction(item);
    if (a.disabled) return { background: '#d1fae5', color: '#065f46', border: '#6ee7b7' };
    if (a.type === 'complete') return { background: '#6366f1', color: '#fff', border: '#818cf8' };
    if (a.type === 'pause')    return { background: '#fef3c7', color: '#78350f', border: '#fcd34d' };
    return { background: '#3b82f6', color: '#fff', border: '#60a5fa' };
};

const dismissTips = () => {
    showTips.value = false;
    try { localStorage.setItem('dwl_tips_dismissed', '1'); } catch (_) {}
};

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
    await loadUsers();
    await loadDailyLog();
    await loadAvailablePool();
});

watch(selectedUserId, async () => {
    await loadDailyLog();
    await loadAvailablePool();
    if (showHistory.value) await loadHistory();
});

watch(viewDate, async () => {
    await loadDailyLog();
    if (showHistory.value) await loadHistory();
});

watch(showHistory, async (val) => {
    if (val && Object.keys(historyData.value).length === 0) await loadHistory();
});

// Multi-select pool
const selectedPoolIds = ref(new Set());
const togglePoolTask = (taskId) => {
    const s = new Set(selectedPoolIds.value);
    if (s.has(taskId)) s.delete(taskId); else s.add(taskId);
    selectedPoolIds.value = s;
};
const addSelected = () => {
    const ids = Array.from(selectedPoolIds.value);
    if (ids.length) addTasksToLog(ids);
};
</script>

<template>
    <div class="daily-work-log">

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  ONBOARDING TIPS BANNER                                           -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <transition name="slide-down">
            <div v-if="showTips" class="tips-banner">
                <button class="tips-close" @click="dismissTips" title="Dismiss tips">✕</button>
                <div class="tips-heading">
                    <span class="tips-icon">💡</span>
                    <strong>How the Daily Work Log works</strong>
                </div>
                <div class="tips-grid">
                    <div class="tip-card">
                        <div class="tip-step">1</div>
                        <div class="tip-body">
                            <strong>Plan your day</strong> — Pick tasks from your assigned list and add them here. Drag to set the order you'll work on them.
                        </div>
                    </div>
                    <div class="tip-card">
                        <div class="tip-step">2</div>
                        <div class="tip-body">
                            <strong>Start each task</strong> — Hit <em>▷ Start</em> when you begin. This logs your time and signals to the team you're on it.
                        </div>
                    </div>
                    <div class="tip-card">
                        <div class="tip-step">3</div>
                        <div class="tip-body">
                            <strong>Pause when you stop</strong> — If you need to switch or take a break, hit <em>⏸ Pause</em>. Resume later when you're back.
                        </div>
                    </div>
                    <div class="tip-card">
                        <div class="tip-step">4</div>
                        <div class="tip-body">
                            <strong>Complete to finish</strong> — Only available once the task is <em>In Progress</em>. This marks the task Done and logs your session.
                        </div>
                    </div>
                    <div class="tip-card tip-card-warn">
                        <div class="tip-step warn">⚠</div>
                        <div class="tip-body">
                            <strong>Don't skip steps</strong> — Avoid starting and immediately completing without actually working on it. Accurate time tracking helps the whole team.
                        </div>
                    </div>
                    <div class="tip-card">
                        <div class="tip-step">5</div>
                        <div class="tip-body">
                            <strong>Push unfinished tasks</strong> — Hit <em>→ Tomorrow</em> to move anything you didn't finish to the next day. It stays in history so nothing gets lost.
                        </div>
                    </div>
                </div>
                <button class="tips-got-it" @click="dismissTips">Got it, let's go →</button>
            </div>
        </transition>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  HEADER                                                            -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div class="dwl-header">
            <div class="dwl-date-nav">
                <button class="nav-btn" @click="goToDate(-1)" title="Previous day">‹</button>
                <div class="dwl-date-label">
                    <div class="dwl-date-main">{{ displayDate(viewDate) }}</div>
                    <div v-if="isToday" class="chip chip-today">Today</div>
                    <div v-else-if="isFuture" class="chip chip-future">Upcoming</div>
                    <div v-else class="chip chip-past">Past</div>
                </div>
                <button class="nav-btn" @click="goToDate(1)" title="Next day">›</button>
            </div>

            <div v-if="canViewOthers" class="dwl-user-switcher">
                <SelectDropdown
                    id="dwl-user-select"
                    v-model="selectedUserId"
                    :options="users"
                    placeholder="Viewing log for..."
                />
            </div>

            <div class="dwl-header-right">
                <div class="progress-pills" v-if="dailyItems.length">
                    <span class="pill p-pending">{{ pendingCount }} Pending</span>
                    <span class="pill p-done">{{ completedCount }} Done</span>
                    <span v-if="pushedCount" class="pill p-pushed">{{ pushedCount }} Pushed</span>
                </div>
                <button class="btn-ghost-sm" @click="showTips = !showTips" title="Show tips">💡 Tips</button>
                <button class="btn-outline" @click="showHistory = !showHistory">
                    {{ showHistory ? 'Hide History' : '📅 History' }}
                </button>
                <button class="btn-primary" @click="showTaskPicker = !showTaskPicker">
                    + Add Tasks
                </button>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  TASK PICKER                                                       -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <transition name="slide-down">
            <div v-if="showTaskPicker" class="task-picker">
                <div class="picker-header">
                    <input v-model="searchPool" class="picker-search" placeholder="Search your tasks…" autofocus />
                    <span class="picker-hint">Click to select, then Add</span>
                    <button class="btn-primary btn-sm" :disabled="selectedPoolIds.size === 0" @click="addSelected">
                        Add{{ selectedPoolIds.size > 0 ? ` (${selectedPoolIds.size})` : '' }}
                    </button>
                    <button class="btn-ghost btn-sm" @click="showTaskPicker = false; selectedPoolIds = new Set()">✕</button>
                </div>

                <div v-if="loadingPool" class="picker-empty">Loading your tasks…</div>
                <div v-else-if="filteredPool.length === 0" class="picker-empty">
                    <span v-if="searchPool">No tasks match "{{ searchPool }}"</span>
                    <span v-else>All your active tasks are already in today's log! 🎉</span>
                </div>
                <div v-else class="picker-grid">
                    <div
                        v-for="task in filteredPool" :key="task.id"
                        class="picker-task" :class="{ selected: selectedPoolIds.has(task.id) }"
                        @click="togglePoolTask(task.id)"
                    >
                        <div class="picker-check" :class="{ checked: selectedPoolIds.has(task.id) }">
                            <svg v-if="selectedPoolIds.has(task.id)" viewBox="0 0 20 20" fill="currentColor" width="12" height="12">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="picker-task-body">
                            <div class="picker-task-name">{{ task.name }}</div>
                            <div class="picker-task-meta">
                                <span v-if="task.milestone?.project?.name" class="tag tag-proj">{{ task.milestone.project.name }}</span>
                                <span v-if="task.priority" class="tag" :class="`prio-${task.priority}`">{{ task.priority }}</span>
                                <span class="tag"
                                    :style="{
                                        background: taskStatusStyle(task.status).bg,
                                        color: taskStatusStyle(task.status).color,
                                        border: `1px solid ${taskStatusStyle(task.status).border}`,
                                    }">
                                    {{ task.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  DAILY TASK LIST                                                   -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div class="dwl-body">
            <!-- Loading skeleton -->
            <div v-if="loadingLog" class="skeleton-wrap">
                <div class="skeleton" v-for="i in 3" :key="i"></div>
            </div>

            <!-- Empty state -->
            <div v-else-if="dailyItems.length === 0" class="empty-state">
                <div class="empty-icon">📋</div>
                <h3>No tasks planned for this day</h3>
                <p>Add tasks from your assigned list and drag them into your preferred work order.</p>
                <button class="btn-primary" @click="showTaskPicker = true">+ Plan Your Day</button>
            </div>

            <!-- Task cards -->
            <ul v-else class="task-list">
                <li
                    v-for="item in dailyItems" :key="item.id"
                    class="task-card"
                    :class="{
                        'is-done':    item.status === 'completed',
                        'is-pushed':  item.status === 'pushed_to_next_day',
                        'is-active':  taskStatus(item) === 'In Progress',
                        dragging:     draggingId === item.id,
                        'drag-over':  dragOverId === item.id,
                    }"
                    draggable="true"
                    @dragstart="onDragStart(item)"
                    @dragover="onDragOver(item, $event)"
                    @drop="onDrop(item, $event)"
                    @dragend="onDragEnd"
                >
                    <!-- Drag handle -->
                    <div class="drag-handle">
                        <svg viewBox="0 0 16 24" fill="currentColor" width="10" height="16">
                            <circle cx="5" cy="4"  r="2"/><circle cx="11" cy="4"  r="2"/>
                            <circle cx="5" cy="12" r="2"/><circle cx="11" cy="12" r="2"/>
                            <circle cx="5" cy="20" r="2"/><circle cx="11" cy="20" r="2"/>
                        </svg>
                    </div>

                    <!-- Active pulse ring -->
                    <div v-if="taskStatus(item) === 'In Progress'" class="active-ring">
                        <span class="pulse-dot"></span>
                    </div>

                    <!-- Task info -->
                    <div class="task-info">
                        <div 
                            class="task-name cursor-pointer hover:text-indigo-600 transition-colors" 
                            :class="{ 'line-through': isFinished(item) }"
                            @click="openSidebar(item.task)"
                        >
                            {{ item.task?.name }}
                        </div>
                        <div class="task-meta">
                            <span v-if="item.task?.milestone?.project?.name" class="tag tag-proj">
                                {{ item.task.milestone.project.name }}
                            </span>
                            <span v-if="item.task?.milestone?.name" class="tag tag-milestone">
                                {{ item.task.milestone.name }}
                            </span>
                            <span v-if="item.task?.priority" class="tag" :class="`prio-${item.task.priority}`">
                                {{ item.task.priority }}
                            </span>
                            <!-- Real task status badge -->
                            <span class="tag task-status-tag"
                                :style="{
                                    background: taskStatusStyle(taskStatus(item)).bg,
                                    color:      taskStatusStyle(taskStatus(item)).color,
                                    border:     `1px solid ${taskStatusStyle(taskStatus(item)).border}`,
                                }">
                                {{ taskStatus(item) }}
                            </span>
                            <!-- Daily log status badge -->
                            <span class="tag" :class="`log-${item.status}`">
                                {{ logStatusLabel(item.status) }}
                            </span>
                        </div>

                        <!-- Contextual status hint -->
                        <div v-if="item.status !== 'completed' && item.status !== 'pushed_to_next_day'" class="status-hint">
                            <template v-if="taskStatus(item) === 'To Do'">
                                ℹ️ Hit <strong>Start</strong> to begin tracking time on this task.
                            </template>
                            <template v-else-if="taskStatus(item) === 'In Progress'">
                                🟢 Task is running — hit <strong>Pause</strong> if you're stepping away, or <strong>Complete</strong> when done.
                            </template>
                            <template v-else-if="taskStatus(item) === 'Paused'">
                                ⏸ Paused — hit <strong>Resume</strong> when you're ready to continue.
                            </template>
                            <template v-else-if="taskStatus(item) === 'Blocked'">
                                🔴 This task is blocked. Unblock it in the task detail before starting.
                            </template>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="task-actions">
                        <!-- Loading spinner -->
                        <div v-if="isBusy(item)" class="spinner"></div>
                        <template v-else>
                            <!-- Primary lifecycle button -->
                            <button
                                class="btn-lifecycle"
                                :disabled="primaryAction(item).disabled || taskStatus(item) === 'Blocked'"
                                :style="primaryBtnStyle(item)"
                                @click="handlePrimary(item)"
                                :title="primaryAction(item).label"
                            >
                                {{ primaryAction(item).label }}
                            </button>

                            <!-- Push to tomorrow (only for pending/in-progress items) -->
                            <button
                                v-if="item.status === 'pending'"
                                class="btn-icon push-btn"
                                title="Push to tomorrow"
                                @click="pushToTomorrow(item)"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>

                             <!-- Add Note -->
                             <button
                                 class="btn-icon note-btn"
                                 title="Add Note"
                                 @click="openNoteModal(item.task)"
                             >
                                 <span style="font-size: 14px;">📝</span>
                             </button>

                             <!-- Remove from log -->
                             <button class="btn-icon remove-btn" title="Remove from today's log" @click="removeFromLog(item)">✕</button>
                        </template>
                    </div>
                </li>
            </ul>

            <div v-if="dailyItems.length > 0" class="dwl-foot">
                <span v-if="savingOrder" class="foot-note">Saving order…</span>
                <span v-else class="foot-note dim">Drag to reorder • Changes are saved automatically</span>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  WARNING MODAL — task not started                                  -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <transition name="fade">
            <div v-if="warnModal.show" class="modal-overlay" @click.self="dismissWarn">
                <div class="warn-modal">
                    <div class="warn-modal-icon">⚠️</div>
                    <h3 class="warn-modal-title">Task hasn't been started yet</h3>
                    <p class="warn-modal-body">
                        <strong>{{ warnModal.item?.task?.name }}</strong> is currently
                        <em>{{ taskStatus(warnModal.item) }}</em>.
                    </p>
                    <p class="warn-modal-body">
                        Our workflow requires you to <strong>start</strong> a task before marking it complete —
                        this helps track time accurately and keeps the team informed.
                    </p>
                    <div class="warn-tips">
                        <div class="warn-tip">✅ <strong>Best practice:</strong> Start → work on the task → Pause if interrupted → Complete when done.</div>
                        <div class="warn-tip">⚡ <strong>Quick option:</strong> If you've already finished this task, we can start & complete it now — but please use proper start/pause/complete going forward.</div>
                    </div>
                    <div class="warn-modal-actions">
                        <button class="btn-outline" @click="dismissWarn">Go back — I'll start it first</button>
                        <button class="btn-warn-ok" @click="doStartThenComplete">Mark as complete anyway</button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  HISTORY PANEL                                                     -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <transition name="slide-down">
            <div v-if="showHistory" class="history-panel">
                <h3 class="history-title">📅 Past 14 Days — Task Log</h3>
                <p class="history-sub">Track patterns: tasks pushed repeatedly may need re-prioritisation or support.</p>
                <div v-if="historyDays.length === 0" class="picker-empty">No history yet — come back after your first day!</div>
                <div v-for="[date, items] in historyDays" :key="date" class="history-day">
                    <div class="history-day-header">
                        <span class="history-date">{{ displayDate(date) }}</span>
                        <div class="history-day-pills">
                            <span class="pill p-done">{{ items.filter(i => i.status === 'completed').length }} done</span>
                            <span v-if="items.filter(i => i.status === 'pending').length" class="pill p-pending">
                                {{ items.filter(i => i.status === 'pending').length }} unfinished
                            </span>
                            <span v-if="items.filter(i => i.status === 'pushed_to_next_day').length" class="pill p-pushed">
                                {{ items.filter(i => i.status === 'pushed_to_next_day').length }} pushed
                            </span>
                        </div>
                    </div>
                    <ul class="history-list">
                        <li v-for="item in items" :key="item.id" class="history-item" :class="`his-${item.status}`">
                            <span class="h-dot" :class="`hdot-${item.status}`"></span>
                            <span class="history-task-name">{{ item.task?.name }}</span>
                            <span v-if="item.task?.milestone?.project?.name" class="tag tag-proj sm">{{ item.task.milestone.project.name }}</span>
                            <span class="tag" :class="`log-${item.status}`" style="font-size:.65rem">{{ logStatusLabel(item.status) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </transition>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  TASK NOTE MODAL                                                   -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <TaskNoteModal
            v-if="showNoteModal"
            :show="showNoteModal"
            :task-for-note="taskToNote"
            @close="showNoteModal = false; taskToNote = null"
            @note-added="loadDailyLog"
        />

    </div>
</template>

<style scoped>
/* ── Base ── */
.daily-work-log { font-family: 'Inter', system-ui, sans-serif; max-width: 860px; margin: 0 auto; }

/* ── Tips banner ── */
.tips-banner {
    position: relative;
    background: linear-gradient(135deg,#ede9fe 0%,#e0f2fe 100%);
    border: 1.5px solid #c4b5fd;
    border-radius: 16px; padding: 20px 24px; margin-bottom: 24px;
}
.tips-close {
    position: absolute; top: 12px; right: 14px;
    border: none; background: none; color: #7c3aed; font-size: 1rem;
    cursor: pointer; opacity: .6;
}
.tips-close:hover { opacity: 1; }
.tips-heading { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.tips-icon { font-size: 1.25rem; }
.tips-heading strong { font-size: 1rem; color: #4c1d95; }
.tips-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 10px; margin-bottom: 16px;
}
.tip-card {
    display: flex; gap: 10px; align-items: flex-start;
    background: rgba(255,255,255,.65); border-radius: 10px; padding: 10px 12px;
    border: 1px solid #ddd6fe;
}
.tip-card-warn { border-color: #fca5a5; background: rgba(255,241,241,.7); }
.tip-step {
    min-width: 24px; height: 24px; border-radius: 50%;
    background: #6366f1; color: #fff; font-size: .72rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.tip-step.warn { background: #ef4444; }
.tip-body { font-size: .8rem; color: #374151; line-height: 1.5; }
.tip-body strong { color: #1e293b; }
.tip-body em { font-style: normal; font-weight: 600; color: #6366f1; }
.tips-got-it {
    background: #6366f1; color: #fff; border: none; border-radius: 8px;
    padding: 8px 20px; font-size: .85rem; font-weight: 600; cursor: pointer;
    transition: opacity .15s;
}
.tips-got-it:hover { opacity: .88; }

/* ── Header ── */
.dwl-header {
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; flex-wrap: wrap; margin-bottom: 20px;
}
.dwl-date-nav { display: flex; align-items: center; gap: 12px; }
.nav-btn {
    width: 32px; height: 32px; border: 1px solid #e2e8f0; border-radius: 8px;
    background: #fff; font-size: 18px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #475569; transition: background .15s;
}
.nav-btn:hover { background: #f1f5f9; }
.dwl-date-label { text-align: center; }
.dwl-date-main { font-size: .95rem; font-weight: 700; color: #1e293b; }
.chip { display: inline-block; margin-top: 3px; padding: 1px 9px; border-radius: 999px; font-size: .68rem; font-weight: 700; }
.chip-today  { background: #6366f1; color: #fff; }
.chip-future { background: #e0f2fe; color: #0369a1; }
.chip-past   { background: #f1f5f9; color: #64748b; }
.dwl-user-switcher { min-width: 200px; }
.dwl-header-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.progress-pills { display: flex; gap: 6px; }
.pill { padding: 3px 10px; border-radius: 999px; font-size: .7rem; font-weight: 600; }
.p-pending { background: #ede9fe; color: #4c1d95; }
.p-done    { background: #d1fae5; color: #065f46; }
.p-pushed  { background: #fef3c7; color: #78350f; }

/* ── Buttons ── */
.btn-primary {
    padding: 7px 16px; border-radius: 8px;
    background: linear-gradient(135deg,#6366f1,#818cf8);
    color: #fff; border: none; font-size: .84rem; font-weight: 600; cursor: pointer; transition: opacity .15s;
}
.btn-primary:hover { opacity: .88; }
.btn-primary:disabled { opacity: .4; cursor: not-allowed; }
.btn-outline {
    padding: 7px 14px; border-radius: 8px; border: 1px solid #c7d2fe;
    background: #f5f3ff; color: #4c1d95; font-size: .82rem; font-weight: 500; cursor: pointer;
}
.btn-outline:hover { background: #ede9fe; }
.btn-ghost { padding: 4px 10px; border-radius: 6px; border: none; background: transparent; color: #94a3b8; font-size: .85rem; cursor: pointer; }
.btn-ghost:hover { background: #f1f5f9; }
.btn-ghost-sm { padding: 5px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; font-size: .78rem; cursor: pointer; color: #475569; }
.btn-ghost-sm:hover { background: #f8fafc; }
.btn-sm { padding: 5px 12px; font-size: .78rem; }

/* lifecycle button */
.btn-lifecycle {
    padding: 5px 12px; border-radius: 7px; border: 1.5px solid;
    font-size: .78rem; font-weight: 600; cursor: pointer; white-space: nowrap;
    transition: opacity .15s, transform .1s;
}
.btn-lifecycle:hover:not(:disabled) { opacity: .85; transform: scale(1.03); }
.btn-lifecycle:disabled { opacity: .5; cursor: default; }

/* icon buttons */
.btn-icon {
    width: 28px; height: 28px; border-radius: 7px;
    border: 1px solid #e2e8f0; background: #fff; font-size: .85rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.push-btn:hover   { background: #fef3c7; border-color: #fcd34d; color: #78350f; }
.note-btn:hover   { background: #f5f3ff; border-color: #c7d2fe; }
.remove-btn:hover { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }

/* ── Picker ── */
.task-picker {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 14px; padding: 16px; margin-bottom: 20px;
    box-shadow: 0 4px 24px rgba(99,102,241,.09);
}
.picker-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.picker-search {
    flex: 1; min-width: 180px; padding: 8px 12px;
    border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: .85rem; outline: none;
}
.picker-search:focus { border-color: #818cf8; }
.picker-hint { font-size: .73rem; color: #94a3b8; white-space: nowrap; }
.picker-empty { text-align: center; color: #94a3b8; padding: 24px 0; font-size: .85rem; }
.picker-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(260px,1fr)); gap: 8px; max-height: 320px; overflow-y: auto; }
.picker-task {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 12px; border-radius: 8px; border: 1.5px solid #e2e8f0;
    cursor: pointer; transition: border-color .15s, background .15s;
}
.picker-task:hover { background: #f8f7ff; border-color: #a5b4fc; }
.picker-task.selected { background: #ede9fe; border-color: #6366f1; }
.picker-check {
    width: 18px; height: 18px; flex-shrink: 0; border-radius: 4px;
    border: 1.5px solid #c7d2fe; background: #fff;
    display: flex; align-items: center; justify-content: center;
}
.picker-check.checked { background: #6366f1; border-color: #6366f1; color: #fff; }
.picker-task-name { font-size: .83rem; font-weight: 500; color: #1e293b; margin-bottom: 4px; }
.picker-task-meta { display: flex; gap: 4px; flex-wrap: wrap; }

/* ── Tags ── */
.tag {
    display: inline-flex; align-items: center;
    padding: 1px 7px; border-radius: 999px; font-size: .68rem; font-weight: 500;
}
.tag.sm { font-size: .63rem; padding: 1px 6px; }
.tag-proj      { background: #dbeafe; color: #1e40af; }
.tag-milestone { background: #f0fdf4; color: #166534; }
.prio-high   { background: #fee2e2; color: #991b1b; }
.prio-medium { background: #fef3c7; color: #92400e; }
.prio-low    { background: #d1fae5; color: #065f46; }
.task-status-tag { font-weight: 600; }
.log-pending            { background: #ede9fe; color: #4c1d95; }
.log-completed          { background: #d1fae5; color: #065f46; }
.log-pushed_to_next_day { background: #fef3c7; color: #78350f; }

/* ── Task list ── */
.dwl-body { min-height: 80px; }
.task-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.task-card {
    display: flex; align-items: center; gap: 12px;
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 14px;
    padding: 14px 16px; position: relative;
    transition: box-shadow .2s, border-color .2s;
}
.task-card:hover { box-shadow: 0 4px 18px rgba(99,102,241,.1); }
.task-card.is-done   { background: #f0fdf4; border-color: #bbf7d0; opacity: .82; }
.task-card.is-pushed { background: #fffbeb; border-color: #fde68a; }
.task-card.is-active { border-color: #6ee7b7; box-shadow: 0 0 0 2px rgba(110,231,183,.2); }
.task-card.dragging  { opacity: .35; }
.task-card.drag-over { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,.2); }

.drag-handle { cursor: grab; color: #cbd5e1; flex-shrink: 0; padding: 2px; }
.drag-handle:hover { color: #94a3b8; }
.drag-handle:active { cursor: grabbing; }

/* active pulse indicator */
.active-ring { flex-shrink: 0; position: relative; width: 12px; height: 12px; }
.pulse-dot {
    display: block; width: 12px; height: 12px; border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 0 0 rgba(34,197,94,.4);
    animation: pulse 1.6s infinite;
}
@keyframes pulse {
    0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.4); }
    70%  { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
    100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
}

.task-info { flex: 1; min-width: 0; }
.task-name { font-size: .9rem; font-weight: 600; color: #1e293b; margin-bottom: 5px; }
.task-name.line-through { text-decoration: line-through; opacity: .6; }
.task-meta { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; margin-bottom: 4px; }
.status-hint { font-size: .73rem; color: #64748b; margin-top: 5px; line-height: 1.4; }
.status-hint strong { color: #334155; }

.task-actions { display: flex; gap: 6px; flex-shrink: 0; align-items: center; }

/* loading spinner */
.spinner {
    width: 20px; height: 20px; border: 2px solid #e2e8f0;
    border-top-color: #6366f1; border-radius: 50%; animation: spin .6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.dwl-foot { margin-top: 14px; text-align: center; }
.foot-note { font-size: .73rem; color: #94a3b8; }
.foot-note.dim { opacity: .65; }

/* ── Skeletons ── */
.skeleton-wrap { display: flex; flex-direction: column; gap: 10px; }
.skeleton {
    height: 72px; border-radius: 14px;
    background: linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
    background-size: 200% 100%; animation: shimmer 1.4s infinite;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ── Empty state ── */
.empty-state {
    text-align: center; padding: 52px 16px;
    background: #fafbff; border: 2px dashed #c7d2fe; border-radius: 18px;
}
.empty-icon { font-size: 2.8rem; margin-bottom: 10px; }
.empty-state h3 { font-size: .95rem; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
.empty-state p  { font-size: .82rem; color: #64748b; margin-bottom: 16px; }

/* ── Warning modal ── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; padding: 16px;
}
.warn-modal {
    background: #fff; border-radius: 18px; padding: 28px 32px;
    max-width: 480px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.warn-modal-icon { font-size: 2.2rem; margin-bottom: 10px; }
.warn-modal-title { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 10px; }
.warn-modal-body { font-size: .85rem; color: #475569; margin-bottom: 8px; line-height: 1.6; }
.warn-modal-body strong { color: #1e293b; }
.warn-modal-body em { font-style: normal; font-weight: 600; color: #6366f1; }
.warn-tips { display: flex; flex-direction: column; gap: 8px; margin: 14px 0 20px; }
.warn-tip {
    font-size: .8rem; color: #334155; line-height: 1.5;
    padding: 10px 12px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0;
}
.warn-tip strong { color: #1e293b; }
.warn-modal-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-warn-ok {
    padding: 8px 18px; background: #f59e0b; color: #fff;
    border: none; border-radius: 8px; font-size: .85rem; font-weight: 600;
    cursor: pointer; transition: opacity .15s;
}
.btn-warn-ok:hover { opacity: .88; }

/* ── History ── */
.history-panel { margin-top: 28px; border-top: 1.5px solid #e2e8f0; padding-top: 22px; }
.history-title { font-size: .95rem; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
.history-sub { font-size: .77rem; color: #94a3b8; margin-bottom: 18px; }
.history-day { margin-bottom: 18px; }
.history-day-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 8px; flex-wrap: wrap; }
.history-date { font-size: .82rem; font-weight: 600; color: #475569; }
.history-day-pills { display: flex; gap: 5px; }
.history-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }
.history-item {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 12px; border-radius: 8px; background: #fafbff; border: 1px solid #f1f5f9;
}
.his-completed { opacity: .75; }
.h-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.hdot-completed          { background: #22c55e; }
.hdot-pending            { background: #6366f1; }
.hdot-pushed_to_next_day { background: #f59e0b; }
.history-task-name { flex: 1; font-size: .8rem; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Transitions ── */
.slide-down-enter-active, .slide-down-leave-active { transition: all .25s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-10px); }
.fade-enter-active, .fade-leave-active { transition: opacity .2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
