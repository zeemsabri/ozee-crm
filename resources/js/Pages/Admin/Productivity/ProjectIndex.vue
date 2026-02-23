<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import {
    BriefcaseIcon, CalendarIcon, ClockIcon, ChevronDownIcon, ChevronUpIcon,
    PrinterIcon, ChatBubbleLeftRightIcon, EnvelopeIcon, DocumentTextIcon,
    SparklesIcon, UserIcon, ArrowPathIcon
} from '@heroicons/vue/24/outline';

import TaskDetailSidebar from '@/Components/ProjectTasks/TaskDetailSidebar.vue';

const reportData = ref([]);
const projectsList = ref([]);
const loading = ref(false);

const selectedProjectIds = ref([]);
const dateStart = ref('');
const dateEnd = ref('');

// UI State
const expandedProjects = ref({});
const showTaskDetailSidebar = ref(false);
const selectedTaskId = ref(null);
const selectedProjectId = ref(null);
const taskDetailProjectUsers = ref([]);

const toggleProjectExpand = (projectId) => {
    expandedProjects.value[projectId] = !expandedProjects.value[projectId];
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
        } else {
            reportData.value.forEach(p => {
                if (p.has_activity) {
                    expandedProjects.value[p.id] = true;
                }
            });
        }
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
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

onMounted(() => {
    // Set default dates to current month
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    dateStart.value = start.toISOString().split('T')[0];
    dateEnd.value = now.toISOString().split('T')[0];

    fetchReport();
});

const printReport = () => window.print();

</script>

<template>
    <Head title="Project Activity Report" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Project Activity Report</h2>
                <div class="flex gap-3">
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
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 print:hidden">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
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
                    </div>
                </div>

                <!-- Projects Activity List -->
                <div v-if="reportData.length === 0 && !loading" class="bg-white p-12 text-center rounded-xl border border-dashed border-gray-300">
                    <BriefcaseIcon class="h-12 w-12 text-gray-300 mx-auto mb-4" />
                    <p class="text-gray-500 font-medium">No projects found for the selected criteria.</p>
                </div>

                <div v-for="project in reportData" :key="project.id" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
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
                            <div class="flex gap-4">
                                <div class="text-center px-4 py-1.5 rounded-lg bg-blue-50">
                                    <div class="text-sm font-black text-blue-700">{{ project.tasks.length }}</div>
                                    <p class="text-[9px] text-blue-500 font-bold uppercase">Tasks</p>
                                </div>
                                <div class="text-center px-4 py-1.5 rounded-lg bg-purple-50">
                                    <div class="text-sm font-black text-purple-700">{{ project.emails.length }}</div>
                                    <p class="text-[9px] text-purple-500 font-bold uppercase">Emails</p>
                                </div>
                            </div>
                            <component :is="expandedProjects[project.id] ? ChevronUpIcon : ChevronDownIcon" class="h-5 w-5 text-gray-400 print:hidden" />
                        </div>
                    </div>

                    <!-- Project Content -->
                    <div v-show="expandedProjects[project.id]" class="p-6 space-y-8 animate-in fade-in slide-in-from-top-2 duration-300">

                        <!-- Project Notes Section -->
                        <div v-if="project.project_notes.length">
                            <h5 class="text-sm font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center">
                                <ChatBubbleLeftRightIcon class="h-4 w-4 mr-2" /> Project Updates & Notes
                            </h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="note in project.project_notes" :key="note.id" class="bg-blue-50/50 p-4 rounded-xl border border-blue-100 relative">
                                    <div class="text-gray-800 text-sm italic mb-3 whitespace-pre-wrap">"{{ note.content }}"</div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="h-6 w-6 rounded-full bg-blue-200 flex items-center justify-center text-[10px] font-bold text-blue-700 mr-2">
                                                {{ note.creator_name ? note.creator_name.substring(0,2).toUpperCase() : '??' }}
                                            </div>
                                            <span class="text-[11px] font-bold text-blue-700">{{ note.creator_name || 'System' }}</span>
                                        </div>
                                        <span class="text-[10px] text-blue-400 font-medium">{{ formatDate(note.created_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tasks Section -->
                        <div v-if="project.tasks.length">
                            <h5 class="text-sm font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center">
                                <DocumentTextIcon class="h-4 w-4 mr-2" /> Key Tasks & Updates
                            </h5>
                            <div class="space-y-4">
                                <div v-for="task in project.tasks" :key="task.id" class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                                    <div class="flex justify-between items-start mb-4">
                                        <div>
                                            <h6 @click="openTaskDetail(task)" class="font-bold text-gray-900 hover:text-indigo-600 cursor-pointer transition">{{ task.name }}</h6>
                                            <p class="text-[10px] text-gray-500 font-medium uppercase mt-1">{{ task.milestone }} • {{ task.assigned_to || 'Unassigned' }}</p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase" :class="task.status === 'Done' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
                                            {{ task.status }}
                                        </span>
                                    </div>

                                    <!-- Task Notes -->
                                    <div v-if="task.notes.length" class="pl-4 border-l-2 border-indigo-200 space-y-3">
                                        <div v-for="tnote in task.notes" :key="tnote.id" class="relative">
                                            <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ tnote.content }}</div>
                                            <div class="flex items-center mt-1 text-[10px] text-gray-400 font-bold">
                                                <span class="text-indigo-500 mr-2">{{ tnote.creator_name }}</span>
                                                <span>{{ formatDate(tnote.created_at) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-gray-400 italic pl-4">No specific updates recorded for this task.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Emails Section -->
                        <div v-if="project.emails.length">
                            <h5 class="text-sm font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center">
                                <EnvelopeIcon class="h-4 w-4 mr-2" /> Communications
                            </h5>
                            <div class="space-y-4">
                                <div v-for="email in project.emails" :key="email.id" class="bg-purple-50/30 rounded-xl p-5 border border-purple-100">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex items-center">
                                            <div :class="email.type === 'Received' ? 'bg-orange-100 text-orange-600' : 'bg-green-100 text-green-600'" class="p-2 rounded-lg mr-3">
                                                <EnvelopeIcon class="h-4 w-4" />
                                            </div>
                                            <div>
                                                <h6 class="font-bold text-gray-900">{{ email.subject }}</h6>
                                                <p class="text-[10px] text-gray-500 font-medium uppercase mt-1">{{ email.type }} • {{ email.sender }}</p>
                                            </div>
                                        </div>
                                        <span class="text-[10px] text-gray-400 font-bold">{{ formatDate(email.created_at) }}</span>
                                    </div>

                                    <!-- AI Contexts -->
                                    <div v-if="email.contexts.length" class="mt-3">
                                        <div v-for="ctx in email.contexts" :key="ctx.id" class="bg-white/80 p-3 rounded-lg border border-purple-200">
                                            <div class="flex items-center mb-1">
                                                <SparklesIcon class="h-3 w-3 text-purple-600 mr-2" />
                                                <span class="text-[10px] font-black text-purple-600 uppercase tracking-tighter">AI Summary</span>
                                            </div>
                                            <div class="text-sm text-gray-800 leading-relaxed">{{ ctx.summary }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State for Project -->
                        <div v-if="!project.has_activity" class="py-12 bg-gray-50 rounded-xl text-center border border-dashed border-gray-200">
                            <ClockIcon class="h-8 w-8 text-gray-300 mx-auto mb-2" />
                            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest">Minimal Status Change in this Period</p>
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

.animate-in {
    animation: fadeInSlide 0.4s ease-out;
}

@keyframes fadeInSlide {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
