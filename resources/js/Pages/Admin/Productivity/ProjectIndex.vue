<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import {
    BriefcaseIcon, CalendarIcon, ClockIcon, ChevronDownIcon, ChevronUpIcon,
    PrinterIcon, ChatBubbleLeftRightIcon, EnvelopeIcon, DocumentTextIcon,
    SparklesIcon, UserIcon, ArrowPathIcon, ClipboardDocumentIcon,
    CheckCircleIcon, HandRaisedIcon, DocumentDuplicateIcon, BoltIcon,
    MagnifyingGlassIcon, FunnelIcon
} from '@heroicons/vue/24/outline';

import TaskDetailSidebar from '@/Components/ProjectTasks/TaskDetailSidebar.vue';
import MentionInput from '@/Components/ProjectTasks/MentionInput.vue';

const reportData = ref([]);
const projectsList = ref([]);
const loading = ref(false);
const exportSuccess = ref(false);
const exportProjectSuccess = ref({});

const selectedProjectIds = ref([]);
const dateStart = ref('');
const dateEnd = ref('');
const highlightDate = ref('');

// UI State
const expandedProjects = ref({});
const activeTabs = ref({});
const projectUserFilters = ref({}); // { projectId: 'userName' }
const showTaskDetailSidebar = ref(false);
const selectedTaskId = ref(null);
const selectedProjectId = ref(null);
const taskDetailProjectUsers = ref([]);
const viewMode = ref('summary'); // 'summary' or 'projects'

const toggleProjectExpand = (projectId) => {
    expandedProjects.value[projectId] = !expandedProjects.value[projectId];
};

const setTab = (projectId, tabName) => {
    activeTabs.value[projectId] = tabName;
};

const isHighlighted = (dateString) => {
    if (!highlightDate.value || !dateString) return false;
    const highlight = highlightDate.value.split('T')[0];
    const target = dateString.split('T')[0];
    return highlight === target;
};

const isDueToday = (task) => {
    if (!highlightDate.value || !task.due_date) return false;
    const highlight = highlightDate.value.split('T')[0];
    return task.due_date === highlight;
};

const formatNoteContent = (content) => {
    if (!content) return '';
    // Simple markdown-ish bolding and line breaks
    return content
        .replace(/\*\*(.*?)\*\*/g, '<strong class="font-black text-gray-900">$1</strong>')
        .replace(/\n/g, '<br/>');
};

const dailySummaries = ref({}); // { projectId: content }
const savingSummary = ref({}); // { projectId: boolean }
const actionPointContent = ref({}); // { projectId: string }
const savingActionPoint = ref({}); // { projectId: boolean }
const actionPointMentions = ref({}); // { projectId: User object }

const addActionPoint = async (project) => {
    const content = actionPointContent.value[project.id];
    if (!content) return;
    
    savingActionPoint.value[project.id] = true;
    try {
        const actionPoints = project.data?.action_points || [];
        const mentionedUser = actionPointMentions.value[project.id];
        
        const newPoint = {
            id: Date.now(),
            content: content,
            date: highlightDate.value,
            user_id: mentionedUser ? mentionedUser.id : null,
            user_name: mentionedUser ? mentionedUser.name : null,
            done: false,
            created_at: new Date().toISOString()
        };
        
        const updatedActionPoints = [...actionPoints, newPoint];
        await window.axios.put(`/api/projects/${project.id}/update-data`, {
            key: 'action_points',
            value: updatedActionPoints
        });
        
        actionPointContent.value[project.id] = '';
        actionPointMentions.value[project.id] = null;
        project.data = { ...(project.data || {}), action_points: updatedActionPoints };
    } catch (e) {
        console.error(e);
    } finally {
        savingActionPoint.value[project.id] = false;
    }
};

const setUserMention = (project, user) => {
    actionPointMentions.value[project.id] = user;
};

const toggleActionPoint = async (project, pointId) => {
    try {
        const actionPoints = [...(project.data?.action_points || [])];
        const pointIndex = actionPoints.findIndex(p => p.id === pointId);
        if (pointIndex === -1) return;
        
        actionPoints[pointIndex].done = !actionPoints[pointIndex].done;
        
        await window.axios.put(`/api/projects/${project.id}/update-data`, {
            key: 'action_points',
            value: actionPoints
        });
        
        project.data.action_points = actionPoints;
    } catch (e) {
        console.error(e);
    }
};

const deleteActionPoint = async (project, pointId) => {
    if (!confirm('Are you sure you want to delete this action point?')) return;
    try {
        const actionPoints = (project.data?.action_points || []).filter(p => p.id !== pointId);
        
        await window.axios.put(`/api/projects/${project.id}/update-data`, {
            key: 'action_points',
            value: actionPoints
        });
        
        project.data.action_points = actionPoints;
    } catch (e) {
        console.error(e);
    }
};

const saveDailySummary = async (projectId) => {
    if (!dailySummaries.value[projectId]) return;
    savingSummary.value[projectId] = true;
    try {
        await window.axios.post(`/api/projects/${projectId}/notes`, {
            notes: [{ content: dailySummaries.value[projectId] }],
            type: 'daily_summary'
        });
        dailySummaries.value[projectId] = '';
        fetchReport();
    } catch (e) {
        console.error(e);
    } finally {
        savingSummary.value[projectId] = false;
    }
};

// --- USER-CENTRIC SUMMARY LOGIC ---
const highlightedActivityByUser = computed(() => {
    const userMap = {};
    const getOrCreateUser = (name) => {
        const userName = name || 'Unassigned';
        if (!userMap[userName]) {
            userMap[userName] = { tasksDone: [], standups: [], meetings: [], emails: [], notes: [], dailySummaries: [] };
        }
        return userMap[userName];
    };

    reportData.value.forEach(project => {
        project.tasks?.forEach(task => {
            if (isHighlighted(task.updated_at) && task.status === 'Done') {
                getOrCreateUser(task.assigned_to).tasksDone.push({ ...task, projectName: project.name });
            }
        });
        project.project_notes?.forEach(note => {
            if (isHighlighted(note.created_at)) {
                const user = getOrCreateUser(note.creator_name);
                if (note.type === 'standup') user.standups.push({ ...note, projectName: project.name });
                else if (note.type === 'meeting_minutes') user.meetings.push({ ...note, projectName: project.name });
                else if (note.type === 'daily_summary') user.dailySummaries.push({ ...note, projectName: project.name });
                else user.notes.push({ ...note, projectName: project.name });
            }
        });
        project.emails?.forEach(email => {
            if (isHighlighted(email.created_at)) {
                getOrCreateUser(email.sender).emails.push({ ...email, projectName: project.name });
            }
        });
    });
    return userMap;
});

const activeUsersCount = computed(() => Object.keys(highlightedActivityByUser.value).length);

// --- PROJECT DATA HELPERS ---
const getProjectUsers = (project) => {
    const users = new Set();
    project.tasks?.forEach(t => t.assigned_to && users.add(t.assigned_to));
    project.project_notes?.forEach(n => n.creator_name && users.add(n.creator_name));
    project.emails?.forEach(e => e.sender && users.add(e.sender));
    return Array.from(users).sort();
};

const filterBySelectedUser = (items, projectId, userKey) => {
    const filter = projectUserFilters.value[projectId];
    if (!filter || filter === 'all') return items;
    return items.filter(item => (item[userKey] === filter));
};

// --- ACTIONS & EXPORTS ---
const openTaskDetail = async (task) => {
    if (!task || !task.id) return;
    selectedTaskId.value = task.id;
    selectedProjectId.value = task.project_id || 0;
    try {
        const res = await window.axios.get(`/api/projects/${selectedProjectId.value}/sections/clients-users`);
        taskDetailProjectUsers.value = res.data.users || [];
    } catch (e) { console.error(e); }
    showTaskDetailSidebar.value = true;
};

const fetchReport = async () => {
    loading.value = true;
    try {
        const res = await window.axios.get('/api/productivity/project-report', {
            params: { project_ids: selectedProjectIds.value.join(','), date_start: dateStart.value, date_end: dateEnd.value }
        });
        reportData.value = res.data.reportData;
        projectsList.value = res.data.projects;
        reportData.value.forEach(p => {
            if (p.has_activity) {
                expandedProjects.value[p.id] = true;
                activeTabs.value[p.id] = 'today';
            }
        });
    } catch (e) { console.error(e); } finally { loading.value = false; }
};

const getHighlightedItems = (items, dateKey) => {
    if (!items) return [];
    return items.filter(item => isHighlighted(item[dateKey]));
};
const getArchivedItems = (items, dateKey) => {
    if (!items) return [];
    return items.filter(item => !isHighlighted(item[dateKey]));
};

const exportForAI = () => {
    const cleanData = reportData.value.map(p => ({
        projectName: p.name,
        status: p.status,
        highlightedActivity: {
            notes: getHighlightedItems(p.project_notes?.filter(n => !n.type || n.type === 'note'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            standups: getHighlightedItems(p.project_notes?.filter(n => n.type === 'standup'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            meetingMinutes: getHighlightedItems(p.project_notes?.filter(n => n.type === 'meeting_minutes'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            dailySummary: getHighlightedItems(p.project_notes?.filter(n => n.type === 'daily_summary'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            tasksTodo: getHighlightedItems(p.tasks?.filter(t => t.status !== 'Done'), 'updated_at').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, status: t.status, latestNotes: t.notes?.[0]?.content })),
            tasksDone: getHighlightedItems(p.tasks?.filter(t => t.status === 'Done'), 'updated_at').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, finishedAt: t.updated_at, latestNotes: t.notes?.[0]?.content })),
            tasksDueToday: p.tasks?.filter(t => isDueToday(t)).map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, status: t.status })),
            communications: getHighlightedItems(p.emails, 'created_at').map(e => ({ subject: e.subject, sender: e.sender, date: e.created_at, aiContext: e.contexts?.[0]?.summary })),
            actionPoints: (p.data?.action_points || []).filter(ap => isHighlighted(ap.date)).map(ap => ({ content: ap.content, done: ap.done }))
        },
        archivedActivity: {
            notes: getArchivedItems(p.project_notes?.filter(n => !n.type || n.type === 'note'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            standups: getArchivedItems(p.project_notes?.filter(n => n.type === 'standup'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            meetingMinutes: getArchivedItems(p.project_notes?.filter(n => n.type === 'meeting_minutes'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            tasksTodo: getArchivedItems(p.tasks?.filter(t => t.status !== 'Done'), 'updated_at').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, status: t.status, latestNotes: t.notes?.[0]?.content })),
            tasksDone: getArchivedItems(p.tasks?.filter(t => t.status === 'Done'), 'updated_at').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, finishedAt: t.updated_at, latestNotes: t.notes?.[0]?.content })),
            communications: getArchivedItems(p.emails, 'created_at').map(e => ({ subject: e.subject, sender: e.sender, date: e.created_at, aiContext: e.contexts?.[0]?.summary }))
        }
    }));
    navigator.clipboard.writeText(JSON.stringify(cleanData, null, 2)).then(() => {
        exportSuccess.value = true;
        setTimeout(() => exportSuccess.value = false, 3000);
    });
};

const exportSingleProjectForAI = (project, event) => {
    if (event) event.stopPropagation();
    const cleanData = {
        projectName: project.name,
        status: project.status,
        highlightedActivity: {
            notes: getHighlightedItems(project.project_notes?.filter(n => !n.type || n.type === 'note'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            standups: getHighlightedItems(project.project_notes?.filter(n => n.type === 'standup'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            meetingMinutes: getHighlightedItems(project.project_notes?.filter(n => n.type === 'meeting_minutes'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            dailySummary: getHighlightedItems(project.project_notes?.filter(n => n.type === 'daily_summary'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            tasksTodo: getHighlightedItems(project.tasks?.filter(t => t.status !== 'Done'), 'updated_at').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, latestNotes: t.notes?.[0]?.content })),
            tasksDone: getHighlightedItems(project.tasks?.filter(t => t.status === 'Done'), 'updated_at').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, latestNotes: t.notes?.[0]?.content })),
            tasksDueToday: project.tasks?.filter(t => isDueToday(t)).map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, status: t.status })),
            communications: getHighlightedItems(project.emails, 'created_at').map(e => ({ subject: e.subject, aiContext: e.contexts?.[0]?.summary })),
            actionPoints: (project.data?.action_points || []).filter(ap => isHighlighted(ap.date)).map(ap => ({ content: ap.content, done: ap.done }))
        },
        archivedActivity: {
            notes: getArchivedItems(project.project_notes?.filter(n => !n.type || n.type === 'note'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            standups: getArchivedItems(project.project_notes?.filter(n => n.type === 'standup'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            meetingMinutes: getArchivedItems(project.project_notes?.filter(n => n.type === 'meeting_minutes'), 'created_at').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            tasksTodo: getArchivedItems(project.tasks?.filter(t => t.status !== 'Done'), 'updated_at').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to })),
            tasksDone: getArchivedItems(project.tasks?.filter(t => t.status === 'Done'), 'updated_at').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to })),
            communications: getArchivedItems(project.emails, 'created_at').map(e => ({ subject: e.subject, aiContext: e.contexts?.[0]?.summary }))
        }
    };
    navigator.clipboard.writeText(JSON.stringify(cleanData, null, 2)).then(() => {
        exportProjectSuccess.value[project.id] = true;
        setTimeout(() => exportProjectSuccess.value[project.id] = false, 3000);
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-AU', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    const now = new Date();
    highlightDate.value = now.toISOString().split('T')[0];
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    dateStart.value = start.toISOString().split('T')[0];
    dateEnd.value = now.toISOString().split('T')[0];
    fetchReport();
});

</script>

<template>
    <Head title="Morning Briefing Report" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <div>
                    <h2 class="font-black text-2xl text-gray-900 tracking-tight">Morning Briefing</h2>
                    <p class="text-sm text-gray-500 font-medium">Daily action point & productivity generator</p>
                </div>
                <div class="flex gap-3">
                    <button @click="exportForAI" class="flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl text-sm font-bold hover:shadow-lg transition">
                        <SparklesIcon class="h-4 w-4 mr-2" /> {{ exportSuccess ? 'Copied JSON!' : 'AI Export (Global)' }}
                    </button>
                    <button @click="fetchReport" :disabled="loading" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition disabled:opacity-50">
                        <ArrowPathIcon class="h-4 w-4 mr-2" :class="{'animate-spin': loading}" /> Refresh
                    </button>
                    <button @click="window.print()" class="flex items-center px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
                        <PrinterIcon class="h-4 w-4 mr-2" /> PDF
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 bg-gray-50 min-h-screen print:bg-white print:py-0">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- 1. FILTERS -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 print:hidden">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end">
                        <div class="md:col-span-2">
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-2 block tracking-widest">Selected Projects</label>
                            <MultiSelectDropdown v-model="selectedProjectIds" :options="projectsList" :is-multi="true" placeholder="Analyze All Projects" />
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-2 block tracking-widest">Range Start</label>
                            <input type="date" v-model="dateStart" class="w-full rounded-xl border-gray-200 text-sm focus:ring-indigo-500 font-bold text-gray-700" />
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-2 block tracking-widest">Range End</label>
                            <input type="date" v-model="dateEnd" class="w-full rounded-xl border-gray-200 text-sm focus:ring-indigo-500 font-bold text-gray-700" />
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-indigo-600 uppercase mb-2 block flex items-center gap-1"><SparklesIcon class="h-3 w-3" /> Focus Date</label>
                            <input type="date" v-model="highlightDate" class="w-full rounded-xl border-indigo-200 bg-indigo-50 text-indigo-900 font-bold text-sm focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div class="mt-6 flex bg-gray-100 p-1 rounded-xl w-full max-w-md mx-auto">
                        <button @click="viewMode = 'summary'" :class="viewMode === 'summary' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-xs font-black uppercase transition">User Summary</button>
                        <button @click="viewMode = 'projects'" :class="viewMode === 'projects' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-xs font-black uppercase transition">Portfolio View</button>
                    </div>
                </div>

                <!-- 2. USER SUMMARY (THE MORNING BRIEFING) -->
                <section v-show="viewMode === 'summary'" class="animate-in fade-in duration-500 space-y-6">
                    <div class="flex items-center justify-between px-2">
                        <h3 class="text-lg font-black text-gray-800 flex items-center gap-2"><BoltIcon class="h-5 w-5 text-amber-500" /> Daily Activity Stream</h3>
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ activeUsersCount }} Contributors on {{ highlightDate }}</span>
                    </div>

                    <div v-if="activeUsersCount === 0" class="bg-white p-16 rounded-[2.5rem] border-2 border-dashed border-gray-200 text-center">
                        <MagnifyingGlassIcon class="h-12 w-12 text-gray-300 mx-auto mb-4" />
                        <p class="text-gray-600 font-bold text-xl">No specific logs found for this focus date.</p>
                        <p class="text-sm text-gray-400 mt-1">Try selecting a different date or checking the detailed portfolio view.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="(data, userName) in highlightedActivityByUser" :key="userName" class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden flex flex-col transition hover:shadow-lg">
                            <div class="p-6 bg-gray-50/50 border-b border-gray-100 flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white font-black text-lg shadow-sm">{{ userName.substring(0,2).toUpperCase() }}</div>
                                <div>
                                    <h4 class="font-black text-gray-900 leading-tight text-lg">{{ userName }}</h4>
                                    <p class="text-[10px] text-indigo-500 font-bold uppercase tracking-tighter">Daily Impact</p>
                                </div>
                            </div>
                            <div class="p-6 space-y-6 flex-1">
                                <div v-if="data.dailySummaries.length > 0">
                                    <h5 class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-3 flex items-center gap-1"><SparklesIcon class="h-3 w-3" /> Daily Impact Summary</h5>
                                    <div v-for="s in data.dailySummaries" :key="s.id" class="text-xs text-indigo-700 bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100 mb-2 font-medium" v-html="formatNoteContent(s.content)"></div>
                                </div>
                                <div v-if="data.standups.length > 0">
                                    <h5 class="text-[10px] font-black text-orange-500 uppercase tracking-widest mb-3 flex items-center gap-1"><HandRaisedIcon class="h-3 w-3" /> Standup Context</h5>
                                    <div v-for="s in data.standups" :key="s.id" class="text-xs text-gray-600 bg-orange-50/50 p-4 rounded-2xl border border-orange-100 mb-2 italic">
                                        "{{ s.content }}"
                                        <p class="text-[9px] font-black mt-2 text-orange-400 uppercase">{{ s.projectName }}</p>
                                    </div>
                                </div>
                                <div v-if="data.tasksDone.length > 0">
                                    <h5 class="text-[10px] font-black text-green-600 uppercase tracking-widest mb-3 flex items-center gap-1"><CheckCircleIcon class="h-3 w-3" /> Accomplishments</h5>
                                    <ul class="space-y-2">
                                        <li v-for="t in data.tasksDone" :key="t.id" @click="openTaskDetail(t)" class="p-3 bg-green-50/30 rounded-xl border border-green-100 cursor-pointer hover:bg-white transition">
                                            <p class="text-xs font-bold text-gray-800">{{ t.name }}</p>
                                            <p v-if="t.description" class="text-[10px] text-gray-400 line-clamp-1 mb-1">{{ t.description }}</p>
                                            <p class="text-[9px] text-gray-400 font-bold uppercase tracking-tight">{{ t.projectName }}</p>
                                        </li>
                                    </ul>
                                </div>
                                <div v-if="data.notes.length > 0">
                                    <h5 class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-3 flex items-center gap-1"><DocumentTextIcon class="h-3 w-3" /> Field Notes & Updates</h5>
                                    <div v-for="n in data.notes" :key="n.id" class="p-3 bg-blue-50/30 rounded-xl border border-blue-100 mb-2">
                                        <div class="text-xs text-gray-700 mb-2" v-html="formatNoteContent(n.content)"></div>
                                        <p class="text-[9px] text-blue-400 font-black uppercase">{{ n.projectName }}</p>
                                    </div>
                                </div>
                                <div v-if="data.emails.length > 0">
                                    <h5 class="text-[10px] font-black text-purple-600 uppercase tracking-widest mb-3 flex items-center gap-1"><EnvelopeIcon class="h-3 w-3" /> Communications</h5>
                                    <div v-for="e in data.emails" :key="e.id" class="p-3 bg-purple-50/30 rounded-xl border border-purple-100 mb-2">
                                        <p class="text-xs font-bold text-gray-800 truncate">{{ e.subject }}</p>
                                        <p v-if="e.contexts?.[0]?.summary" class="text-[10px] text-purple-600 italic mt-1 font-medium line-clamp-2">AI Context: {{ e.contexts[0].summary }}</p>
                                        <p class="text-[9px] text-purple-400 font-black uppercase mt-1">{{ e.projectName }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 3. PROJECT-BY-PROJECT DEEP DIVE -->
                <section v-show="viewMode === 'projects'" class="animate-in fade-in duration-500 space-y-6">
                    <div class="flex items-center justify-between px-2">
                        <h3 class="text-sm font-black text-gray-400 uppercase tracking-[0.2em]">Portfolio Deep Dive</h3>
                    </div>

                    <div v-for="project in reportData" :key="project.id" class="bg-white rounded-[2rem] border border-gray-200 shadow-sm overflow-hidden mb-8 transition hover:shadow-md">
                        <!-- Project Header -->
                        <div @click="toggleProjectExpand(project.id)" class="p-6 flex flex-col md:flex-row md:items-center justify-between cursor-pointer hover:bg-gray-50 transition gap-4">
                            <div class="flex items-center space-x-5">
                                <div class="h-14 w-14 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 shadow-inner"><BriefcaseIcon class="h-8 w-8" /></div>
                                <div>
                                    <h4 class="font-black text-xl text-gray-900 leading-none">{{ project.name }}</h4>
                                    <div class="flex items-center gap-3 mt-2">
                                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest bg-gray-100 text-gray-600">{{ project.status }}</span>
                                        <span v-if="project.has_activity" class="flex items-center text-[10px] text-indigo-500 font-bold uppercase"><div class="h-1.5 w-1.5 bg-indigo-500 rounded-full mr-1.5 animate-pulse"></div> Recent Activity</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="exportSingleProjectForAI(project, $event)" class="px-4 py-2 border border-indigo-200 text-indigo-600 rounded-xl text-xs font-bold hover:bg-indigo-50 transition flex items-center gap-2">
                                    <SparklesIcon class="h-3.5 w-3.5" /> {{ exportProjectSuccess[project.id] ? 'Copied!' : 'Export JSON' }}
                                </button>
                                <component :is="expandedProjects[project.id] ? ChevronUpIcon : ChevronDownIcon" class="h-6 w-6 text-gray-300" />
                            </div>
                        </div>

                        <!-- Project View -->
                        <div v-show="expandedProjects[project.id]" class="border-t border-gray-100 bg-gray-50/10">
                            <!-- In-Project User Filter -->
                            <div class="px-8 py-4 bg-white border-b border-gray-100 flex flex-col md:flex-row items-center justify-between gap-4">
                                <div class="flex items-center gap-3 w-full md:w-auto">
                                    <FunnelIcon class="h-4 w-4 text-gray-400" />
                                    <select v-model="projectUserFilters[project.id]" class="rounded-xl border-gray-200 text-xs font-bold text-gray-600 focus:ring-indigo-500 py-1.5 pr-8 pl-3">
                                        <option value="all">Show All Contributors</option>
                                        <option v-for="user in getProjectUsers(project)" :key="user" :value="user">{{ user }}</option>
                                    </select>
                                </div>
                                <nav class="flex space-x-1 p-1 bg-gray-100 rounded-xl overflow-x-auto scrollbar-hide">
                                    <button v-for="tab in ['today', 'todo', 'done', 'notes', 'standups', 'meetings', 'summary', 'emails']" :key="tab" @click="setTab(project.id, tab)"
                                            :class="activeTabs[project.id] === tab ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'"
                                            class="py-1.5 px-4 rounded-lg text-[10px] font-black uppercase transition whitespace-nowrap">
                                        {{ tab }}
                                        <span v-if="tab === 'today' && project.tasks?.filter(t => isDueToday(t)).length" class="ml-1 px-1.5 py-0.5 bg-indigo-500 text-white rounded-md text-[8px]">{{ project.tasks?.filter(t => isDueToday(t)).length }}</span>
                                    </button>
                                </nav>
                            </div>

                            <div class="p-8">
                                <!-- TODAY TAB (NEW) -->
                                <div v-show="activeTabs[project.id] === 'today'" class="space-y-6">
                                    <!-- ACTION POINTS (NEW) -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-[10px] font-black tracking-widest text-indigo-600 uppercase flex items-center gap-1"><ClipboardDocumentIcon class="h-3 w-3" /> Quick Action Points</h5>
                                            <span class="text-[9px] font-bold text-gray-400">Save notes for AI task creation</span>
                                        </div>
                                        
                                        <div class="flex gap-2">
                                            <MentionInput 
                                                :project-id="project.id"
                                                v-model="actionPointContent[project.id]"
                                                @submit="addActionPoint(project)"
                                                @user-selected="setUserMention(project, $event)"
                                                placeholder="Add quick action point (type @ for users)..."
                                            />
                                            <button 
                                                @click="addActionPoint(project)" 
                                                :disabled="savingActionPoint[project.id] || !actionPointContent[project.id]"
                                                class="bg-indigo-600 text-white rounded-xl px-4 py-2 font-black text-xs hover:bg-indigo-700 transition flex items-center gap-2 h-10 disabled:opacity-50"
                                            >
                                                <SparklesIcon class="h-3.5 w-3.5" /> {{ savingActionPoint[project.id] ? 'Saving...' : 'Add' }}
                                            </button>
                                        </div>

                                        <div v-if="(project.data?.action_points || []).filter(p => isHighlighted(p.date)).length" class="space-y-2">
                                            <div v-for="point in (project.data?.action_points || []).filter(p => isHighlighted(p.date))" :key="point.id" class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-xl group hover:shadow-sm transition">
                                                <input 
                                                    type="checkbox" 
                                                    :checked="point.done" 
                                                    @change="toggleActionPoint(project, point.id)" 
                                                    class="h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 border-gray-300" 
                                                />
                                                <div class="flex-1 min-w-0">
                                                    <span :class="{'line-through text-gray-400': point.done, 'text-gray-700': !point.done}" class="text-xs font-bold block truncate">{{ point.content }}</span>
                                                    <span v-if="point.user_name" class="text-[9px] font-black text-indigo-500 uppercase flex items-center gap-1 mt-0.5"><UserIcon class="h-2.5 w-2.5" /> Mentioned: {{ point.user_name }}</span>
                                                </div>
                                                <button @click="deleteActionPoint(project, point.id)" class="text-gray-300 hover:text-red-500 transition opacity-0 group-hover:opacity-100">
                                                    <ArrowPathIcon class="h-3.5 w-3.5 stroke-2" />
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Yesterday's pending action points -->
                                        <div v-if="(project.data?.action_points || []).filter(p => !isHighlighted(p.date) && !p.done).length" class="mt-4 pt-4 border-t border-dashed border-gray-100">
                                            <h6 class="text-[9px] font-black text-amber-500 uppercase tracking-widest mb-3 flex items-center gap-1">Yesterday's Pending</h6>
                                            <div class="space-y-2 opacity-75">
                                                <div v-for="point in (project.data?.action_points || []).filter(p => !isHighlighted(p.date) && !p.done)" :key="point.id" class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-xl">
                                                    <input 
                                                        type="checkbox" 
                                                        :checked="point.done" 
                                                        @change="toggleActionPoint(project, point.id)" 
                                                        class="h-4 w-4 rounded text-indigo-600 border-gray-300" 
                                                    />
                                                    <div class="flex-1 min-w-0">
                                                        <span class="text-xs font-medium text-gray-600 block truncate">{{ point.content }}</span>
                                                        <span v-if="point.user_name" class="text-[8px] font-black text-indigo-400 uppercase flex items-center gap-0.5 mt-0.5"><UserIcon class="h-2 w-2" /> {{ point.user_name }}</span>
                                                    </div>
                                                    <span class="text-[8px] font-black text-gray-300 ml-auto uppercase">{{ point.date }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="project.tasks?.filter(t => isDueToday(t)).length || getHighlightedItems(project.tasks?.filter(t => t.status !== 'Done'), 'updated_at').length || getHighlightedItems(project.tasks?.filter(t => t.status === 'Done'), 'updated_at').length || getHighlightedItems(project.project_notes, 'created_at').length || getHighlightedItems(project.emails, 'created_at').length" class="space-y-6">
                                        
                                        <!-- DUE TODAY -->
                                        <div v-if="project.tasks?.filter(t => isDueToday(t)).length" class="space-y-3">
                                            <h5 class="text-[10px] font-black tracking-widest text-red-500 uppercase flex items-center gap-1"><ClockIcon class="h-3 w-3" /> Due Today (Deadline)</h5>
                                            <div v-for="task in project.tasks?.filter(t => isDueToday(t))" :key="task.id" @click="openTaskDetail(task)" class="p-4 bg-red-50/50 border border-red-100 rounded-xl cursor-pointer hover:bg-white transition">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <h6 class="font-bold text-gray-900">{{ task.name }}</h6>
                                                        <div v-if="task.description" class="mt-2 text-[11px] text-gray-500 line-clamp-2">{{ task.description }}</div>
                                                    </div>
                                                    <span class="text-[10px] font-bold text-gray-500 flex items-center gap-1"><UserIcon class="h-3 w-3" /> {{ task.assigned_to || 'Unassigned' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Highlighted Tasks (Todo) -->
                                        <div v-if="getHighlightedItems(project.tasks?.filter(t => t.status !== 'Done'), 'updated_at').length" class="space-y-3">
                                            <h5 class="text-[10px] font-black tracking-widest text-indigo-500 uppercase flex items-center gap-1"><SparklesIcon class="h-3 w-3" /> Focus Tasks (Active Today)</h5>
                                            <div v-for="task in getHighlightedItems(project.tasks?.filter(t => t.status !== 'Done'), 'updated_at')" :key="task.id" @click="openTaskDetail(task)" class="p-4 bg-indigo-50/50 border border-indigo-100 rounded-xl cursor-pointer hover:bg-white transition">
                                                <div class="flex justify-between items-start">
                                                    <div class="flex-1">
                                                        <h6 class="font-bold text-gray-900">{{ task.name }}</h6>
                                                        <div v-if="task.description" class="mt-2 text-[11px] text-gray-500 line-clamp-2">{{ task.description }}</div>
                                                        <div v-if="task.notes?.length" class="mt-3 p-2 bg-white/50 rounded-lg border border-indigo-50 text-[10px] italic text-gray-600">
                                                            "{{ task.notes[0].content }}"
                                                        </div>
                                                    </div>
                                                    <span class="text-[10px] font-bold text-gray-500 flex items-center gap-1 ml-4"><UserIcon class="h-3 w-3" /> {{ task.assigned_to || 'Unassigned' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Accomplished Today -->
                                        <div v-if="getHighlightedItems(project.tasks?.filter(t => t.status === 'Done'), 'updated_at').length" class="space-y-3">
                                            <h5 class="text-[10px] font-black tracking-widest text-green-500 uppercase flex items-center gap-1"><CheckCircleIcon class="h-3 w-3" /> Accomplished Today</h5>
                                            <div v-for="task in getHighlightedItems(project.tasks?.filter(t => t.status === 'Done'), 'updated_at')" :key="task.id" @click="openTaskDetail(task)" class="p-4 bg-green-50/50 border border-green-100 rounded-xl">
                                                <div class="flex items-start gap-3">
                                                    <CheckCircleIcon class="h-5 w-5 text-green-500 mt-0.5" />
                                                    <div class="flex-1">
                                                        <h6 class="font-bold text-gray-900 text-sm">{{ task.name }}</h6>
                                                        <div v-if="task.description" class="mt-1 text-[11px] text-gray-500">{{ task.description }}</div>
                                                    </div>
                                                    <span class="ml-auto text-[10px] text-gray-400 font-bold uppercase">{{ task.assigned_to }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Highlighted Notes -->
                                        <div v-if="getHighlightedItems(project.project_notes, 'created_at').length" class="space-y-3">
                                            <h5 class="text-[10px] font-black tracking-widest text-blue-500 uppercase flex items-center gap-1"><DocumentTextIcon class="h-3 w-3" /> Today's Logs</h5>
                                            <div v-for="note in getHighlightedItems(project.project_notes, 'created_at')" :key="note.id" class="p-4 bg-gray-50 border border-gray-100 rounded-xl">
                                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-2 block">{{ note.type || 'Note' }}</span>
                                                <div class="text-sm text-gray-700 italic mb-3 whitespace-pre-wrap" v-html="formatNoteContent(note.content)"></div>
                                                <div class="flex items-center gap-2 text-[10px] font-bold text-gray-500"><UserIcon class="h-3 w-3" /> {{ note.creator_name }}</div>
                                            </div>
                                        </div>

                                        <!-- Highlighted Emails -->
                                        <div v-if="getHighlightedItems(project.emails, 'created_at').length" class="space-y-3">
                                            <h5 class="text-[10px] font-black tracking-widest text-purple-500 uppercase flex items-center gap-1"><EnvelopeIcon class="h-3 w-3" /> Today's Comms</h5>
                                            <div v-for="email in getHighlightedItems(project.emails, 'created_at')" :key="email.id" class="p-4 bg-purple-50/30 border border-purple-100 rounded-xl">
                                                <div class="flex items-center justify-between mb-2">
                                                    <p class="text-sm font-bold text-gray-800">{{ email.subject }}</p>
                                                    <p class="text-[10px] text-gray-500 font-bold uppercase">{{ email.sender }}</p>
                                                </div>
                                                <div v-if="email.contexts?.length" class="p-3 bg-white/60 rounded-lg border border-purple-50 text-[11px] text-purple-700 font-medium">
                                                    <SparklesIcon class="h-3 w-3 inline mr-1" /> {{ email.contexts[0].summary }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-center py-8">
                                        <p class="text-gray-400 text-sm font-bold">No activity recorded for this focus date.</p>
                                    </div>
                                </div>

                                <!-- TODO TAB -->
                                <div v-show="activeTabs[project.id] === 'todo'" class="space-y-8">
                                    <div v-for="(group, title) in {'Highlighted (Focus Date)': getHighlightedItems(filterBySelectedUser(project.tasks?.filter(t => t.status !== 'Done'), project.id, 'assigned_to'), 'updated_at'), 'Archive': getArchivedItems(filterBySelectedUser(project.tasks?.filter(t => t.status !== 'Done'), project.id, 'assigned_to'), 'updated_at')}" :key="title">
                                        <div v-if="group.length > 0" class="space-y-4">
                                            <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 border-b border-gray-100 pb-2">{{ title }}</h5>
                                            <div v-for="task in group" :key="task.id"
                                         @click="openTaskDetail(task)"
                                         class="group p-5 bg-white border rounded-[1.5rem] cursor-pointer hover:border-indigo-400 transition"
                                         :class="isHighlighted(task.updated_at) ? 'border-indigo-300 ring-2 ring-indigo-50 shadow-md' : 'border-gray-100'">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h6 class="font-black text-gray-900 group-hover:text-indigo-600 transition">{{ task.name }}</h6>
                                                <div class="flex items-center gap-4 mt-2">
                                                    <span class="text-[10px] font-bold text-gray-400 flex items-center gap-1 uppercase tracking-tighter"><UserIcon class="h-3 w-3" /> {{ task.assigned_to || 'Unassigned' }}</span>
                                                    <span class="text-[10px] font-bold text-gray-400 flex items-center gap-1 uppercase tracking-tighter"><CalendarIcon class="h-3 w-3" /> {{ task.due_date || 'No Date' }}</span>
                                                </div>
                                            </div>
                                            <span class="bg-amber-50 text-amber-600 px-2 py-1 rounded-lg text-[10px] font-black uppercase">{{ task.status }}</span>
                                        </div>
                                        <div v-if="task.description" class="mt-4 p-4 bg-gray-50 rounded-2xl text-xs text-gray-600 whitespace-pre-wrap border border-gray-100">{{ task.description }}</div>
                                    </div>
                                        </div>
                                    </div>
                                    <p v-if="!project.tasks?.filter(t => t.status !== 'Done').length" class="text-center text-gray-400 text-xs italic">No active tasks found.</p>
                                </div>

                                <!-- DONE TAB -->
                                <div v-show="activeTabs[project.id] === 'done'" class="space-y-8">
                                    <div v-for="(group, title) in {'Highlighted (Focus Date)': getHighlightedItems(filterBySelectedUser(project.tasks?.filter(t => t.status === 'Done'), project.id, 'assigned_to'), 'updated_at'), 'Archive': getArchivedItems(filterBySelectedUser(project.tasks?.filter(t => t.status === 'Done'), project.id, 'assigned_to'), 'updated_at')}" :key="title">
                                        <div v-if="group.length > 0" class="space-y-4">
                                            <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 border-b border-gray-100 pb-2">{{ title }}</h5>
                                            <div v-for="task in group" :key="task.id"
                                                 class="p-5 bg-white border border-gray-100 rounded-[1.5rem]" :class="isHighlighted(task.updated_at) ? 'ring-2 ring-green-100 border-green-200' : ''">
                                                <div class="flex items-start gap-4">
                                                    <CheckCircleIcon class="h-6 w-6 text-green-500 mt-1" />
                                                    <div>
                                                        <h6 class="font-black text-gray-900">{{ task.name }}</h6>
                                                        <p class="text-[10px] font-bold text-gray-400 mt-1 uppercase tracking-tighter">{{ task.assigned_to }} • Done {{ formatDate(task.updated_at) }}</p>
                                                        <div v-if="task.description" class="mt-3 p-3 bg-gray-50 rounded-xl text-xs text-gray-500 border border-gray-100">{{ task.description }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-if="!project.tasks?.filter(t => t.status === 'Done').length" class="text-center text-gray-400 text-xs italic">No done tasks found.</p>
                                </div>

                                <!-- NOTES, STANDUPS, MEETINGS, SUMMARY (GROUPED BY RENDERER) -->
                                <div v-show="['notes', 'standups', 'meetings', 'summary'].includes(activeTabs[project.id])" class="space-y-8">
                                    <div v-for="(group, title) in {'Highlighted (Focus Date)': getHighlightedItems(filterBySelectedUser(project.project_notes?.filter(n => (activeTabs[project.id] === 'notes' && (!n.type || n.type === 'note')) || (activeTabs[project.id] === 'standups' && n.type === 'standup') || (activeTabs[project.id] === 'meetings' && n.type === 'meeting_minutes') || (activeTabs[project.id] === 'summary' && n.type === 'daily_summary')), project.id, 'creator_name'), 'created_at'), 'Archive': getArchivedItems(filterBySelectedUser(project.project_notes?.filter(n => (activeTabs[project.id] === 'notes' && (!n.type || n.type === 'note')) || (activeTabs[project.id] === 'standups' && n.type === 'standup') || (activeTabs[project.id] === 'meetings' && n.type === 'meeting_minutes') || (activeTabs[project.id] === 'summary' && n.type === 'daily_summary')), project.id, 'creator_name'), 'created_at')}" :key="title">
                                        <div v-if="group.length > 0">
                                            <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 border-b border-gray-100 pb-2">{{ title }}</h5>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div v-for="note in group" :key="note.id"
                                          class="p-6 bg-white border border-gray-100 rounded-[2rem] shadow-sm flex flex-col relative" :class="isHighlighted(note.created_at) ? 'ring-2 ring-indigo-50 border-indigo-200' : ''">
                                        <div v-if="isHighlighted(note.created_at)" class="absolute -top-2 -right-2 bg-indigo-600 text-white text-[8px] font-black uppercase px-2 py-1 rounded-full shadow-lg">New Log</div>
                                        <div class="text-sm text-gray-700 leading-relaxed mb-6 whitespace-pre-wrap" v-html="formatNoteContent(note.content)"></div>
                                        <div class="mt-auto pt-4 border-t border-gray-50 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div class="h-8 w-8 rounded-xl bg-gray-100 flex items-center justify-center text-[10px] font-black">{{ note.creator_name?.substring(0,2) }}</div>
                                                <span class="text-xs font-bold text-gray-600">{{ note.creator_name }}</span>
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-bold">{{ formatDate(note.created_at) }}</span>
                                        </div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>

                                    <!-- Add Summary Input (only in summary tab) -->
                                    <div v-if="activeTabs[project.id] === 'summary'" class="mt-8 bg-indigo-50/30 p-8 rounded-[2rem] border border-indigo-100">
                                        <h5 class="text-sm font-black text-indigo-900 mb-4 flex items-center gap-2"><SparklesIcon class="h-5 w-5" /> Daily Impact Summary</h5>
                                        <p class="text-xs text-indigo-600 mb-4 font-medium italic">Summarize the overall progress, blockers, and next steps for today.</p>
                                        <textarea v-model="dailySummaries[project.id]" rows="4" class="w-full rounded-2xl border-indigo-100 focus:ring-indigo-500 text-sm p-4 placeholder:text-indigo-200" placeholder="Paste your AI-generated summary or write one manually..."></textarea>
                                        <div class="flex justify-end mt-4">
                                            <button @click="saveDailySummary(project.id)" :disabled="savingSummary[project.id] || !dailySummaries[project.id]" class="px-6 py-2 bg-indigo-600 text-white rounded-xl text-sm font-black hover:bg-indigo-700 transition disabled:opacity-50">
                                                {{ savingSummary[project.id] ? 'Saving...' : 'Post Summary' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- EMAILS -->
                                <div v-show="activeTabs[project.id] === 'emails'" class="space-y-8">
                                    <div v-for="(group, title) in {'Highlighted (Focus Date)': getHighlightedItems(filterBySelectedUser(project.emails, project.id, 'sender'), 'created_at'), 'Archive': getArchivedItems(filterBySelectedUser(project.emails, project.id, 'sender'), 'created_at')}" :key="title">
                                        <div v-if="group.length > 0" class="space-y-6">
                                            <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 border-b border-gray-100 pb-2">{{ title }}</h5>
                                            <div v-for="email in group" :key="email.id"
                                         class="bg-white rounded-[2rem] p-6 border border-gray-100 shadow-sm" :class="isHighlighted(email.created_at) ? 'ring-2 ring-purple-100 border-purple-200' : ''">
                                        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6">
                                            <div class="flex items-center gap-4">
                                                <div :class="email.type === 'Received' ? 'bg-orange-100 text-orange-600' : 'bg-green-100 text-green-600'" class="p-3 rounded-2xl shadow-inner"><EnvelopeIcon class="h-6 w-6" /></div>
                                                <div>
                                                    <h6 class="font-black text-gray-900 text-lg leading-none">{{ email.subject }}</h6>
                                                    <p class="text-[10px] font-bold text-gray-400 uppercase mt-2 tracking-widest">{{ email.type }} • {{ email.sender }}</p>
                                                </div>
                                            </div>
                                            <span class="text-[11px] font-bold text-gray-400 bg-gray-50 px-3 py-1.5 rounded-xl">{{ formatDate(email.created_at) }}</span>
                                        </div>
                                        <div v-if="email.contexts?.length" class="space-y-4">
                                            <div v-for="ctx in email.contexts" :key="ctx.id" class="p-5 bg-purple-50/50 rounded-[1.5rem] border border-purple-100">
                                                <div class="flex items-center gap-2 mb-3 text-purple-600"><SparklesIcon class="h-4 w-4" /><span class="text-[10px] font-black uppercase tracking-widest">AI Summary</span></div>
                                                <p class="text-sm text-gray-700 leading-relaxed font-medium">{{ ctx.summary }}</p>
                                            </div>
                                        </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Task Sidebar -->
        <RightSidebar v-model:show="showTaskDetailSidebar" title="Morning Review Detail" :initialWidth="40">
            <template #content>
                <TaskDetailSidebar v-if="selectedTaskId" :task-id="selectedTaskId" :project-id="selectedProjectId" :project-users="taskDetailProjectUsers" @close="showTaskDetailSidebar = false" />
            </template>
        </RightSidebar>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    .print\:hidden { display: none !important; }
    .bg-gray-50 { background: white !important; }
    .shadow-sm, .rounded-xl, .rounded-\[2rem\], .rounded-\[2\.5rem\] { box-shadow: none !important; border: 1px solid #eee !important; border-radius: 1rem !important; }
}
.scrollbar-hide::-webkit-scrollbar { display: none; }
.animate-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
