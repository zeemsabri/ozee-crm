<script setup>
/**
 * StepPickerModalV2.vue
 * A premium modal for selecting automation steps.
 * Groups steps logically and provides search.
 */
import { ref, computed, watch } from 'vue';
import { 
    XMarkIcon, 
    MagnifyingGlassIcon, 
    ChevronRightIcon,
    EnvelopeIcon,
    ArrowPathIcon,
    ArrowsRightLeftIcon,
    SparklesIcon,
    PencilSquareIcon,
    PlusCircleIcon,
    LinkIcon,
    MagnifyingGlassCircleIcon,
    CodeBracketIcon,
    VariableIcon,
    CpuChipIcon,
    BoltIcon,
    CalendarIcon,
    CircleStackIcon,
    GlobeAltIcon,
    CheckCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'select']);

const searchQuery = ref('');
const activeGroup = ref('all');

const steps = [
    // Messaging
    { id: 'ACTION:SEND_EMAIL', label: 'Send Email', description: 'Send a custom email to one or more recipients.', icon: EnvelopeIcon, category: 'messaging', color: 'text-indigo-500', bgColor: 'bg-indigo-50' },
    
    // Logic
    { id: 'CONDITION', label: 'If / Else Condition', description: 'Branch your automation based on rules.', icon: ArrowsRightLeftIcon, category: 'logic', color: 'text-amber-500', bgColor: 'bg-amber-50' },
    { id: 'FOR_EACH', label: 'For Each Loop', description: 'Run steps for every item in a list.', icon: ArrowPathIcon, category: 'logic', color: 'text-indigo-500', bgColor: 'bg-indigo-50' },
    
    // Data Management
    { id: 'FETCH_RECORDS', label: 'Fetch Records', description: 'Find and retrieve data from your database.', icon: MagnifyingGlassCircleIcon, category: 'data', color: 'text-cyan-500', bgColor: 'bg-cyan-50' },
    { id: 'ACTION:CREATE_RECORD', label: 'Create Record', description: 'Add a new entry to any data model.', icon: PlusCircleIcon, category: 'data', color: 'text-emerald-500', bgColor: 'bg-emerald-50' },
    { id: 'ACTION:UPDATE_RECORD', label: 'Update Record', description: 'Modify an existing record in the database.', icon: PencilSquareIcon, category: 'data', color: 'text-sky-500', bgColor: 'bg-sky-50' },
    { id: 'ACTION:SYNC_RELATIONSHIP', label: 'Sync Relationship', description: 'Connect or disconnect related items.', icon: LinkIcon, category: 'data', color: 'text-violet-500', bgColor: 'bg-violet-50' },
    
    // AI
    { id: 'AI_PROMPT', label: 'AI Prompt', description: 'Use AI to analyze data or generate content.', icon: SparklesIcon, category: 'ai', color: 'text-pink-500', bgColor: 'bg-pink-50' },
    { id: 'ACTION:PROCESS_EMAIL', label: 'Process Email', description: 'Automatically parse and handle emails.', icon: CpuChipIcon, category: 'ai', color: 'text-purple-500', bgColor: 'bg-purple-50' },
    
    // Tools
    { id: 'TRANSFORM', label: 'Transform Data', description: 'Clean or modify text and data values.', icon: CodeBracketIcon, category: 'tools', color: 'text-slate-500', bgColor: 'bg-slate-100' },
    { id: 'DEFINE_VARIABLE', label: 'Define Variable', description: 'Store a value for use in later steps.', icon: VariableIcon, category: 'tools', color: 'text-slate-500', bgColor: 'bg-slate-100' },
    { id: 'ACTION:FETCH_API_DATA', label: 'Fetch API Data', description: 'Get data from an external web service.', icon: GlobeAltIcon, category: 'tools', color: 'text-emerald-500', bgColor: 'bg-emerald-50' },
    { id: 'ACTION:CHECK_MILESTONE_COMPLETION', label: 'Check Milestone', description: 'Verify if project milestones are finished.', icon: CheckCircleIcon, category: 'tools', color: 'text-teal-500', bgColor: 'bg-teal-50' }
];

const groups = [
    { id: 'all', label: 'All Blocks', icon: CircleStackIcon },
    { id: 'messaging', label: 'Messaging', icon: EnvelopeIcon },
    { id: 'logic', label: 'Logic & Flow', icon: ArrowsRightLeftIcon },
    { id: 'data', label: 'Data Management', icon: CircleStackIcon },
    { id: 'ai', label: 'Artificial Intelligence', icon: SparklesIcon },
    { id: 'tools', label: 'Tools & API', icon: CodeBracketIcon },
];

const filteredSteps = computed(() => {
    let list = steps;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(item => item.label.toLowerCase().includes(q) || item.description.toLowerCase().includes(q));
    } else if (activeGroup.value !== 'all') {
        list = list.filter(item => item.category === activeGroup.value);
    }
    return list;
});

function handleSelect(fullId) {
    // If it's an action, we might need to split it
    if (fullId.startsWith('ACTION:')) {
        const type = fullId.split(':')[1];
        emit('select', { type: 'ACTION', actionType: type });
    } else {
        emit('select', { type: fullId });
    }
    emit('close');
}

watch(() => props.show, (val) => {
    if (val) {
        searchQuery.value = '';
        activeGroup.value = 'all';
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[400] flex items-center justify-center p-4 md:p-6 cursor-default">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="$emit('close')"></div>

            <!-- Modal Content -->
            <div class="relative w-full max-w-4xl h-[640px] bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col animate-in">
                
                <!-- Header -->
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-xl font-black text-slate-800 tracking-tight">Add Automation Step</h3>
                        <p class="text-xs text-slate-500 mt-1 font-medium">Select a block to add to your workflow. These blocks can be linked together to create logic.</p>
                    </div>
                    <button @click="$emit('close')" class="p-2 rounded-full hover:bg-slate-200/50 transition-colors">
                        <XMarkIcon class="w-6 h-6 text-slate-400" />
                    </button>
                </div>

                <!-- Search Bar -->
                <div class="px-8 py-4 bg-white border-b border-slate-100">
                    <div class="relative group">
                        <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Search steps (e.g. Email, Condition, AI)..." 
                            class="w-full pl-12 pr-4 py-3 bg-slate-50 border-2 border-transparent focus:border-indigo-500 focus:bg-white rounded-2xl outline-none text-sm font-bold text-slate-700 transition-all"
                        />
                    </div>
                </div>

                <!-- Main Content -->
                <div class="flex-1 flex overflow-hidden">
                    
                    <!-- Sidebar -->
                    <div class="w-64 border-r border-slate-100 bg-slate-50/30 overflow-y-auto pt-4 pb-8">
                        <div class="px-4 mb-2">
                             <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-4 mb-4">Categories</p>
                        </div>
                        <div class="space-y-1 px-2 text-slate-600">
                            <button 
                                v-for="g in groups" 
                                :key="g.id"
                                @click="activeGroup = g.id; searchQuery = ''"
                                class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all"
                                :class="activeGroup === g.id && !searchQuery ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-100' : 'hover:bg-slate-100/50 hover:text-slate-900'"
                            >
                                <component :is="g.icon" class="w-5 h-5" :class="activeGroup === g.id && !searchQuery ? 'text-indigo-500' : 'text-slate-400'" />
                                {{ g.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Steps Grid -->
                    <div class="flex-1 overflow-y-auto p-8">
                        <div v-if="filteredSteps.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <button 
                                v-for="s in filteredSteps" 
                                :key="s.id"
                                @click="handleSelect(s.id)"
                                class="flex items-start gap-4 p-4 rounded-2xl border-2 border-slate-50 hover:border-indigo-100 hover:shadow-md transition-all group/card text-left bg-white"
                            >
                                <div :class="['w-12 h-12 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover/card:scale-110', s.bgColor]">
                                    <component :is="s.icon" :class="['w-6 h-6', s.color]" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-black text-slate-800">{{ s.label }}</p>
                                    <p class="text-[11px] text-slate-400 mt-1 leading-relaxed font-medium line-clamp-2 italic">{{ s.description }}</p>
                                </div>
                            </button>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="h-full flex flex-col items-center justify-center text-center opacity-60">
                            <CpuChipIcon class="w-16 h-16 text-slate-100 mb-4" />
                            <p class="text-sm font-bold text-slate-400">No steps found</p>
                            <p class="text-xs text-slate-300 mt-1">Try a different search term or category.</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button 
                        @click="$emit('close')"
                        class="px-6 py-2 text-xs font-black text-slate-400 hover:text-slate-600 transition-colors"
                    >
                        ESC to Close
                    </button>
                </div>

            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.animate-in {
    animation: modal-pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modal-pop {
    0% { opacity: 0; transform: scale(0.95); }
    100% { opacity: 1; transform: scale(1); }
}
</style>
