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
    CheckCircleIcon, HandRaisedIcon, DocumentDuplicateIcon
} from '@heroicons/vue/24/outline';

import TaskDetailSidebar from '@/Components/ProjectTasks/TaskDetailSidebar.vue';

const reportData = ref([]);
const projectsList = ref([]);
const loading = ref(false);
const exportSuccess = ref(false);

const selectedProjectIds = ref([]);
const dateStart = ref('');
const dateEnd = ref('');
const highlightDate = ref('');

// UI State
const expandedProjects = ref({});
const activeTabs = ref({});
const showTaskDetailSidebar = ref(false);
const selectedTaskId = ref(null);
const selectedProjectId = ref(null);
const taskDetailProjectUsers = ref([]);

const toggleProjectExpand = (projectId) => {
    expandedProjects.value[projectId] = !expandedProjects.value[projectId];
};

const setTab = (projectId, tabName) => {
    activeTabs.value[projectId] = tabName;
};

const openTaskDetail = async (task) => {
    if (!task || !task.id) return;
    selectedTaskId.value = task.id;
    selectedProjectId.value = task.project_id || 0;

    // Fetch project users for the sidebar
    if (selectedProjectId.value) {
        try {
            const res = await window.axios.get(`/api/projects/${selectedProjectId.value}/sections/clients-users`);
            taskDetailProjectUsers.value = res.data.users || [];
        } catch (e) {
            console.error('Failed to fetch project users', e);
        }
    }

    showTaskDetailSidebar.value = true;
};

const fetchReport = async () => {
    loading.value = true;
    try {
        const res = await window.axios.get('/api/productivity/project-report', {
            params: {
                project_ids: selectedProjectIds.value.join(','),
                date_start: dateStart.value,
                date_end: dateEnd.value,
            }
        });
        reportData.value = res.data.reportData;
        projectsList.value = res.data.projects;

        // Auto-expand projects if there's only one or if they have activity
        if (reportData.value.length === 1) {
            expandedProjects.value[reportData.value[0].id] = true;
            activeTabs.value[reportData.value[0].id] = 'todo';
        } else {
            reportData.value.forEach(p => {
                if (p.has_activity) {
                    expandedProjects.value[p.id] = true;
                    activeTabs.value[p.id] = 'todo';
                }
            });
        }
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const isHighlighted = (dateString) => {
    if (!highlightDate.value || !dateString) return false;
    const highlight = highlightDate.value.split('T')[0];
    const target = dateString.split('T')[0];
    return highlight === target;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-AU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const exportForAI = () => {
    // Generate clean JSON representation for AI summary
    const cleanData = reportData.value.map(p => {
        return {
            projectName: p.name,
            status: p.status,
            notes: p.project_notes?.filter(n => !n.type || n.type === 'general' || n.type === 'note').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            standups: p.project_notes?.filter(n => n.type === 'standup').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            meetingMinutes: p.project_notes?.filter(n => n.type === 'meeting_minutes').map(n => ({ author: n.creator_name, content: n.content, date: n.created_at })),
            tasksTodo: p.tasks?.filter(t => t.status !== 'Done').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, dueDate: t.due_date, status: t.status })),
            tasksDone: p.tasks?.filter(t => t.status === 'Done').map(t => ({ name: t.name, description: t.description, assignee: t.assigned_to, finishedAt: t.updated_at })),
            communications: p.emails?.map(e => ({ subject: e.subject, sender: e.sender, type: e.type, date: e.created_at, aiContext: e.contexts?.[0]?.summary }))
        };
    });
    
    const jsonString = JSON.stringify(cleanData, null, 2);
    navigator.clipboard.writeText(jsonString).then(() => {
        exportSuccess.value = true;
        setTimeout(() => exportSuccess.value = false, 3000);
    }).catch(err => {
        console.error('Failed to copy text: ', err);
        // Fallback for downloading if clipboard fails
        const element = document.createElement('a');
        element.setAttribute('href', 'data:text/json;charset=utf-8,' + encodeURIComponent(jsonString));
        element.setAttribute('download', 'project_activity_report.json');
        element.style.display = 'none';
        document.body.appendChild(element);
        element.click();
        document.body.removeChild(element);
    });
};

onMounted(() => {
    // Set default dates
    const now = new Date();
    highlightDate.value = now.toISOString().split('T')[0];
    
    // Default range (start of month to today)
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    dateStart.value = start.toISOString().split('T')[0];
    dateEnd.value = now.toISOString().split('T')[0];

    fetchReport();
});

const printReport = () => window.print();

const getNotesByType = (notes, typeStr) => {
    if (!notes) return [];
    if (typeStr === 'note') {
        return notes.filter(n => !n.type || n.type === 'note' || n.type === 'general');
    }
    return notes.filter(n => n.type === typeStr);
};

</script>

<template>
    <Head title="Project Activity Report" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Project Activity Report</h2>
                <div class="flex gap-3">
                    <button @click="exportForAI" class="flex items-center px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-lg text-sm font-semibold hover:from-purple-600 hover:to-indigo-700 shadow-sm transition">
                        <SparklesIcon class="h-4 w-4 mr-2" /> {{ exportSuccess ? 'Copied to Clipboard!' : 'Export JSON for AI' }}
                    </button>
                    <button @click="fetchReport" :disabled="loading" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 shadow-sm transition disabled:opacity-50">
                        <ArrowPathIcon class="h-4 w-4 mr-2" :class="{'animate-spin': loading}" />
                        Refresh
                    </button>
                    <button @click="printReport" class="flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 shadow-sm transition">
                        <PrinterIcon class="h-4 w-4 mr-2" /> Export PDF
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen print:bg-white print:py-0">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Filter Panel -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 print:hidden relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-8 pt-0 pointer-events-none opacity-5">
                        <BriefcaseIcon class="h-48 w-48 text-indigo-900" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end relative z-10">
                        <div class="col-span-2">
                            <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">Filter Projects</label>
                            <MultiSelectDropdown v-model="selectedProjectIds" :options="projectsList" :is-multi="true" placeholder="All Projects" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">Range Start</label>
                            <input type="date" v-model="dateStart" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">Range End</label>
                            <input type="date" v-model="dateEnd" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-indigo-600 uppercase mb-2 block flex items-center gap-1"><SparklesIcon class="h-3 w-3" /> Highlight Day</label>
                            <input type="date" v-model="highlightDate" class="w-full rounded-lg border-indigo-300 bg-indigo-50 text-indigo-900 font-bold text-sm focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>

                <!-- Projects Activity List -->
                <div v-if="reportData.length === 0 && !loading" class="bg-white p-12 text-center rounded-xl border border-dashed border-gray-300">
                    <BriefcaseIcon class="h-12 w-12 text-gray-300 mx-auto mb-4" />
                    <p class="text-gray-500 font-medium">No projects found for the selected criteria.</p>
                </div>

                <div v-for="project in reportData" :key="project.id" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6 transition-all duration-300 hover:shadow-md">
                    <!-- Project Header -->
                    <div
                        @click="toggleProjectExpand(project.id)"
                        class="p-6 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition print:cursor-default"
                        :class="{'border-b border-gray-100': expandedProjects[project.id]}"
                    >
                        <div class="flex items-center space-x-4">
                            <div class="h-12 w-12 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600">
                                <BriefcaseIcon class="h-7 w-7" />
                            </div>
                            <div>
                                <h4 class="font-bold text-xl text-gray-900">{{ project.name }}</h4>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase" :class="project.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                                        {{ project.status }}
                                    </span>
                                    <span v-if="project.has_activity" class="text-[10px] text-indigo-600 font-bold uppercase flex items-center">
                                        <div class="h-1.5 w-1.5 rounded-full bg-indigo-600 mr-1.5 animate-pulse"></div>
                                        Recent Activity
                                    </span>
                                    <span v-else class="text-[10px] text-gray-400 font-bold uppercase">No Activity</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-6">
                            <component :is="expandedProjects[project.id] ? ChevronUpIcon : ChevronDownIcon" class="h-5 w-5 text-gray-400 print:hidden" />
                        </div>
                    </div>

                    <!-- Project Content -->
                    <div v-show="expandedProjects[project.id]" class="animate-in fade-in slide-in-from-top-2 duration-300 bg-gray-50/30">
                        
                        <!-- Tabs Header -->
                        <div class="border-b border-gray-200 bg-white px-6 pt-3 print:hidden">
                            <nav class="-mb-px flex space-x-8 overflow-x-auto custom-scrollbar" aria-label="Tabs">
                                <button @click="setTab(project.id, 'todo')" :class="[activeTabs[project.id] === 'todo' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'group whitespace-nowrap border-b-2 py-3 px-1 text-sm font-bold flex items-center']">
                                    <DocumentTextIcon class="h-4 w-4 mr-2" :class="[activeTabs[project.id] === 'todo' ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500']" />
                                    Todo <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-[10px]">{{ project.tasks?.filter(t => t.status !== 'Done').length || 0 }}</span>
                                </button>
                                <button @click="setTab(project.id, 'done')" :class="[activeTabs[project.id] === 'done' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'group whitespace-nowrap border-b-2 py-3 px-1 text-sm font-bold flex items-center']">
                                    <CheckCircleIcon class="h-4 w-4 mr-2" :class="[activeTabs[project.id] === 'done' ? 'text-green-500' : 'text-gray-400 group-hover:text-gray-500']" />
                                    Done <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-[10px]">{{ project.tasks?.filter(t => t.status === 'Done').length || 0 }}</span>
                                </button>
                                <button @click="setTab(project.id, 'notes')" :class="[activeTabs[project.id] === 'notes' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'group whitespace-nowrap border-b-2 py-3 px-1 text-sm font-bold flex items-center']">
                                    <ChatBubbleLeftRightIcon class="h-4 w-4 mr-2" :class="[activeTabs[project.id] === 'notes' ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500']" />
                                    Notes <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-[10px]">{{ getNotesByType(project.project_notes, 'note').length }}</span>
                                </button>
                                <button @click="setTab(project.id, 'standups')" :class="[activeTabs[project.id] === 'standups' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'group whitespace-nowrap border-b-2 py-3 px-1 text-sm font-bold flex items-center']">
                                    <HandRaisedIcon class="h-4 w-4 mr-2" :class="[activeTabs[project.id] === 'standups' ? 'text-orange-500' : 'text-gray-400 group-hover:text-gray-500']" />
                                    Standups <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-[10px]">{{ getNotesByType(project.project_notes, 'standup').length }}</span>
                                </button>
                                <button @click="setTab(project.id, 'meetings')" :class="[activeTabs[project.id] === 'meetings' ? 'border-teal-500 text-teal-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'group whitespace-nowrap border-b-2 py-3 px-1 text-sm font-bold flex items-center']">
                                    <UserIcon class="h-4 w-4 mr-2" :class="[activeTabs[project.id] === 'meetings' ? 'text-teal-500' : 'text-gray-400 group-hover:text-gray-500']" />
                                    Meeting Minutes <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-[10px]">{{ getNotesByType(project.project_notes, 'meeting_minutes').length }}</span>
                                </button>
                                <button @click="setTab(project.id, 'emails')" :class="[activeTabs[project.id] === 'emails' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'group whitespace-nowrap border-b-2 py-3 px-1 text-sm font-bold flex items-center']">
                                    <EnvelopeIcon class="h-4 w-4 mr-2" :class="[activeTabs[project.id] === 'emails' ? 'text-purple-500' : 'text-gray-400 group-hover:text-gray-500']" />
                                    Emails <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-[10px]">{{ project.emails?.length || 0 }}</span>
                                </button>
                            </nav>
                        </div>
                        
                        <div class="p-6">
                            <!-- TODO TAB -->
                            <div v-show="activeTabs[project.id] === 'todo'" class="space-y-4">
                                <div v-for="task in project.tasks?.filter(t => t.status !== 'Done')" :key="task.id" 
                                     class="rounded-xl p-5 bg-white border"
                                     :class="isHighlighted(task.updated_at) ? 'border-indigo-400 ring-2 ring-indigo-100 shadow-md transform scale-[1.01] transition-transform' : 'border-gray-100 shadow-sm'">
                                    
                                    <div v-if="isHighlighted(task.updated_at)" class="text-[10px] font-black tracking-widest text-indigo-500 uppercase mb-2 flex items-center"><SparklesIcon class="h-3 w-3 mr-1" /> Highlighted Activity</div>
                                    
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <h6 @click="openTaskDetail(task)" class="font-bold text-gray-900 hover:text-indigo-600 cursor-pointer transition text-lg">{{ task.name }}</h6>
                                            <div class="flex items-center gap-3 mt-1">
                                                <span class="text-[11px] text-gray-500 font-bold uppercase flex items-center" :class="{'text-red-500': task.due_date && new Date(task.due_date) < new Date()}">
                                                    <CalendarIcon class="h-3 w-3 mr-1" /> {{ task.due_date || 'No Due Date' }}
                                                </span>
                                                <span class="text-[11px] text-gray-500 font-bold uppercase flex items-center">
                                                    <UserIcon class="h-3 w-3 mr-1" /> {{ task.assigned_to || 'Unassigned' }}
                                                </span>
                                            </div>
                                        </div>
                                        <span class="px-2 py-1 rounded text-[10px] font-black uppercase tracking-wider" :class="task.status === 'In Progress' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'">
                                            {{ task.status }}
                                        </span>
                                    </div>
                                    <div v-if="task.description" class="mt-3 text-sm text-gray-600 whitespace-pre-wrap bg-gray-50 rounded p-3 border border-gray-100">{{ task.description }}</div>
                                </div>
                                <div v-if="!project.tasks?.filter(t => t.status !== 'Done').length" class="text-sm text-gray-400 italic py-4 text-center">No Todo tasks.</div>
                            </div>

                            <!-- DONE TAB -->
                            <div v-show="activeTabs[project.id] === 'done'" class="space-y-4">
                                <div v-for="task in project.tasks?.filter(t => t.status === 'Done')" :key="task.id" 
                                     class="rounded-xl p-5 bg-white border"
                                     :class="isHighlighted(task.updated_at) ? 'border-green-400 ring-2 ring-green-100 shadow-md transform scale-[1.01] transition-transform' : 'border-gray-100 shadow-sm'">
                                     
                                    <div v-if="isHighlighted(task.updated_at)" class="text-[10px] font-black tracking-widest text-green-500 uppercase mb-2 flex items-center"><SparklesIcon class="h-3 w-3 mr-1" /> Done on Highlight Date</div>

                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <h6 @click="openTaskDetail(task)" class="font-bold text-gray-900 hover:text-indigo-600 cursor-pointer transition text-lg flex items-center">
                                                <CheckCircleIcon class="h-5 w-5 mr-1.5 text-green-500" /> {{ task.name }}
                                            </h6>
                                            <div class="flex items-center gap-3 mt-1 ml-6.5">
                                                <span class="text-[11px] text-gray-500 font-bold uppercase flex items-center">
                                                    <UserIcon class="h-3 w-3 mr-1" /> {{ task.assigned_to || 'Unassigned' }}
                                                </span>
                                                <span class="text-[11px] text-gray-500 font-bold uppercase flex items-center">
                                                    <ClockIcon class="h-3 w-3 mr-1" /> Done: {{ formatDate(task.updated_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="task.description" class="mt-3 ml-6.5 text-sm text-gray-600 whitespace-pre-wrap bg-gray-50 rounded p-3 border border-gray-100">{{ task.description }}</div>
                                </div>
                                <div v-if="!project.tasks?.filter(t => t.status === 'Done').length" class="text-sm text-gray-400 italic py-4 text-center">No Done tasks.</div>
                            </div>

                            <!-- NOTES TAB -->
                            <div v-show="activeTabs[project.id] === 'notes'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="note in getNotesByType(project.project_notes, 'note')" :key="note.id" 
                                     class="bg-blue-50/70 p-5 rounded-xl border relative"
                                     :class="isHighlighted(note.created_at) ? 'border-blue-400 ring-2 ring-blue-200' : 'border-blue-100'">
                                    <div v-if="isHighlighted(note.created_at)" class="absolute -top-3 -right-2"><span class="flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span></span></div>
                                    <div class="text-gray-800 text-sm mb-4 whitespace-pre-wrap leading-relaxed">"{{ note.content }}"</div>
                                    <div class="flex items-center justify-between border-t border-blue-200/50 pt-3">
                                        <div class="flex items-center">
                                            <div class="h-6 w-6 rounded-full bg-blue-200 flex items-center justify-center text-[10px] font-bold text-blue-700 mr-2 shadow-sm">
                                                {{ note.creator_name ? note.creator_name.substring(0,2).toUpperCase() : '??' }}
                                            </div>
                                            <span class="text-[11px] font-bold text-blue-800 tracking-wide">{{ note.creator_name || 'System' }}</span>
                                        </div>
                                        <span class="text-[10px] text-blue-500 font-bold">{{ formatDate(note.created_at) }}</span>
                                    </div>
                                </div>
                                <div v-if="!getNotesByType(project.project_notes, 'note').length" class="col-span-1 md:col-span-2 text-sm text-gray-400 italic py-4 text-center">No general notes.</div>
                            </div>

                            <!-- STANDUPS TAB -->
                            <div v-show="activeTabs[project.id] === 'standups'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="note in getNotesByType(project.project_notes, 'standup')" :key="note.id" 
                                     class="bg-orange-50/70 p-5 rounded-xl border relative"
                                     :class="isHighlighted(note.created_at) ? 'border-orange-400 ring-2 ring-orange-200' : 'border-orange-100'">
                                    <div v-if="isHighlighted(note.created_at)" class="absolute -top-3 -right-2"><span class="flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-orange-500"></span></span></div>
                                    <div class="text-gray-800 text-sm mb-4 whitespace-pre-wrap leading-relaxed">{{ note.content }}</div>
                                    <div class="flex items-center justify-between border-t border-orange-200/50 pt-3">
                                        <div class="flex items-center">
                                            <div class="h-6 w-6 rounded-full bg-orange-200 flex items-center justify-center text-[10px] font-bold text-orange-700 mr-2 shadow-sm">
                                                {{ note.creator_name ? note.creator_name.substring(0,2).toUpperCase() : '??' }}
                                            </div>
                                            <span class="text-[11px] font-bold text-orange-800 tracking-wide">{{ note.creator_name || 'System' }}</span>
                                        </div>
                                        <span class="text-[10px] text-orange-500 font-bold">{{ formatDate(note.created_at) }}</span>
                                    </div>
                                </div>
                                <div v-if="!getNotesByType(project.project_notes, 'standup').length" class="col-span-1 md:col-span-2 text-sm text-gray-400 italic py-4 text-center">No standups reported.</div>
                            </div>

                            <!-- MEETING MINUTES TAB -->
                            <div v-show="activeTabs[project.id] === 'meetings'" class="grid grid-cols-1 gap-4">
                                <div v-for="note in getNotesByType(project.project_notes, 'meeting_minutes')" :key="note.id" 
                                     class="bg-teal-50/70 p-5 rounded-xl border relative"
                                     :class="isHighlighted(note.created_at) ? 'border-teal-400 ring-2 ring-teal-200' : 'border-teal-100'">
                                    <div v-if="isHighlighted(note.created_at)" class="absolute -top-3 -right-2"><span class="flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-teal-500"></span></span></div>
                                    <div class="text-gray-800 text-sm mb-5 whitespace-pre-wrap leading-relaxed">{{ note.content }}</div>
                                    <div class="flex items-center justify-between border-t border-teal-200/50 pt-3">
                                        <div class="flex items-center">
                                            <div class="h-6 w-6 rounded-full bg-teal-200 flex items-center justify-center text-[10px] font-bold text-teal-700 mr-2 shadow-sm">
                                                {{ note.creator_name ? note.creator_name.substring(0,2).toUpperCase() : '??' }}
                                            </div>
                                            <span class="text-[11px] font-bold text-teal-800 tracking-wide">{{ note.creator_name || 'System' }}</span>
                                        </div>
                                        <span class="text-[10px] text-teal-500 font-bold">{{ formatDate(note.created_at) }}</span>
                                    </div>
                                </div>
                                <div v-if="!getNotesByType(project.project_notes, 'meeting_minutes').length" class="text-sm text-gray-400 italic py-4 text-center">No meeting minutes recorded.</div>
                            </div>

                            <!-- EMAILS TAB -->
                            <div v-show="activeTabs[project.id] === 'emails'" class="space-y-4">
                                <div v-for="email in project.emails" :key="email.id" 
                                     class="bg-white rounded-xl p-5 border shadow-sm relative transition"
                                     :class="isHighlighted(email.created_at) ? 'border-purple-400 ring-2 ring-purple-100' : 'border-gray-100'">
                                    
                                    <div v-if="isHighlighted(email.created_at)" class="absolute -top-3 -right-2"><span class="flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-purple-500"></span></span></div>

                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex items-center">
                                            <div :class="email.type === 'Received' ? 'bg-orange-100 text-orange-600' : 'bg-green-100 text-green-600'" class="p-2 rounded-lg mr-4 shadow-sm">
                                                <EnvelopeIcon class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <h6 class="font-bold text-gray-900 text-lg">{{ email.subject }}</h6>
                                                <p class="text-[11px] text-gray-500 font-bold uppercase mt-1 tracking-wider">{{ email.type }} • {{ email.sender }}</p>
                                            </div>
                                        </div>
                                        <span class="text-[11px] text-gray-400 font-bold bg-gray-50 px-2 py-1 rounded">{{ formatDate(email.created_at) }}</span>
                                    </div>

                                    <!-- AI Contexts -->
                                    <div v-if="email.contexts.length" class="mt-4 pt-4 border-t border-gray-100">
                                        <div v-for="ctx in email.contexts" :key="ctx.id" class="bg-purple-50 p-4 rounded-lg border border-purple-100">
                                            <div class="flex items-center mb-2">
                                                <SparklesIcon class="h-4 w-4 text-purple-600 mr-2" />
                                                <span class="text-[10px] font-black text-purple-600 uppercase tracking-widest">AI Summary</span>
                                            </div>
                                            <div class="text-sm text-gray-700 leading-relaxed font-medium">{{ ctx.summary }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!project.emails?.length" class="text-sm text-gray-400 italic py-4 text-center">No emails found for this period.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <RightSidebar v-model:show="showTaskDetailSidebar" title="Task Details" :initialWidth="45">
            <template #content>
                <TaskDetailSidebar
                    v-if="selectedTaskId"
                    :task-id="selectedTaskId"
                    :project-id="selectedProjectId"
                    :project-users="taskDetailProjectUsers"
                    @close="showTaskDetailSidebar = false"
                />
            </template>
        </RightSidebar>

    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    .print\:hidden { display: none !important; }
    body { background: white; }
    .max-w-7xl { max-width: 100% !important; width: 100% !important; padding: 0 !important; }
    .shadow-sm, .rounded-xl { border: 1px solid #e5e7eb !important; shadow: none !important; }
}

.custom-scrollbar::-webkit-scrollbar {
    height: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #e5e7eb;
    border-radius: 20px;
}

.animate-in {
    animation: fadeInSlide 0.3s ease-out;
}

@keyframes fadeInSlide {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
