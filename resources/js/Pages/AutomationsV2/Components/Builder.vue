<script setup>
/**
 * Builder.vue
 * The main automation editor.
 * Contains: Canvas (Vue Flow), Properties Sidebar, and Top Toolbar.
 */
import { computed } from 'vue';
import { useAutomationsV2Store } from '../Store/storeV2';
import { useVueFlow } from '@vue-flow/core';
import Canvas from './Canvas.vue';
import PropertiesSidebar from './PropertiesSidebar.vue';
import { 
    ChevronLeftIcon, 
    CloudArrowUpIcon, 
    PlusIcon,
    BoltIcon,
    CalendarIcon,
    CogIcon,
    ArrowsRightLeftIcon,
    MagnifyingGlassIcon,
    ArrowPathIcon,
    SparklesIcon,
    CodeBracketIcon,
    CommandLineIcon
} from '@heroicons/vue/24/solid';

const emit = defineEmits(['back']);
const store = useAutomationsV2Store();

const isSaving = computed(() => store.isSaving);

const NODE_TYPES = [
    { type: 'TRIGGER',          label: 'Event Trigger',     icon: BoltIcon,               color: 'bg-violet-500' },
    { type: 'SCHEDULE_TRIGGER', label: 'Schedule',          icon: CalendarIcon,            color: 'bg-sky-500'    },
    { type: 'CONDITION',        label: 'If / Else',         icon: ArrowsRightLeftIcon,     color: 'bg-amber-500'  },
    { type: 'ACTION',           label: 'Action',            color: 'bg-emerald-500',       icon: CogIcon          },
    { type: 'FETCH_RECORDS',    label: 'Fetch Records',     icon: MagnifyingGlassIcon,     color: 'bg-cyan-500'   },
    { type: 'FOR_EACH',         label: 'For Each Loop',     icon: ArrowPathIcon,           color: 'bg-indigo-500' },
    { type: 'AI_PROMPT',        label: 'AI Prompt',         icon: SparklesIcon,            color: 'bg-pink-500'   },
    { type: 'TRANSFORM',        label: 'Transform',         icon: CodeBracketIcon,         color: 'bg-slate-500'  },
    { type: 'DEFINE_VARIABLE',  label: 'Define Variable',   icon: CommandLineIcon,         color: 'bg-slate-500'  },
];

function onDragStart(event, type) {
    if (event.dataTransfer) {
        event.dataTransfer.setData('application/vueflow', type);
        event.dataTransfer.effectAllowed = 'move';
    }
}

function addStep(type) {
    let position = { x: 0, y: 0 };
    try {
        const flow = useVueFlow('v2-canvas');
        if (flow && typeof flow.project === 'function') {
            const rect = document.getElementById('v2-canvas')?.getBoundingClientRect();
            if (rect) {
                position = flow.project({
                    x: rect.width / 2,
                    y: rect.height / 2
                });
            }
        }
    } catch(e) {}
    
    // STRICT RULE: New blocks are always unlinked (null parent, null branch)
    store.addStep(type, null, null, position);
}

async function handleSave() {
    try {
        await store.saveWorkflow();
    } catch(e) {}
}
</script>

<template>
    <div class="h-full flex flex-col relative overflow-hidden">
        
        <!-- Header -->
        <header class="sticky top-0 h-16 border-b bg-white flex items-center justify-between px-6 z-20 shadow-sm">
            <div class="flex items-center gap-4">
                <button @click="emit('back')" class="p-2 hover:bg-gray-100 rounded-full transition text-gray-400 hover:text-gray-900">
                    <ChevronLeftIcon class="w-6 h-6" />
                </button>
                <div class="h-8 w-px bg-gray-200"></div>
                <input 
                    v-model="store.workflowName"
                    class="text-lg font-bold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/10 rounded-lg px-2 py-1 -ml-2 w-64 hover:bg-gray-50 transition"
                    placeholder="Automation Name"
                />
            </div>

            <div class="flex items-center gap-3">
                <div v-if="isSaving" class="flex items-center gap-2 text-xs text-gray-400 italic">
                    <div class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></div>
                    Saving...
                </div>
                <button 
                    @click="handleSave"
                    :disabled="isSaving || !store.isReadyForSave"
                    class="v2-btn-primary disabled:opacity-50 disabled:scale-100 shadow-indigo-500/30"
                >
                    <CloudArrowUpIcon class="w-5 h-5" /> Save Workflow
                </button>
            </div>
        </header>

        <!-- Main Workspace -->
        <div class="flex-1 flex relative bg-slate-50 overflow-hidden">
            <!-- Left Sidebar: Block Library -->
            <aside class="w-64 border-r bg-white flex flex-col z-10 shadow-sm">
                <div class="p-4 border-b">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Step Library</h3>
                    <p class="text-[10px] text-gray-400 mt-1 leading-tight">Drag and drop blocks onto the canvas to build your flow.</p>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-2 custom-scrollbar">
                    <div 
                        v-for="btn in NODE_TYPES" 
                        :key="btn.type"
                        draggable="true"
                        @dragstart="onDragStart($event, btn.type)"
                        @click="addStep(btn.type)"
                        class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 bg-white hover:border-indigo-200 hover:shadow-md hover:shadow-indigo-500/5 transition-all cursor-grab active:cursor-grabbing group"
                    >
                        <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-white shadow-sm transition-transform group-hover:scale-110', btn.color]">
                            <component :is="btn.icon" class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-gray-700 truncate">{{ btn.label }}</div>
                            <div class="text-[10px] text-gray-400 truncate">{{ btn.type.replace(/_/g, ' ') }}</div>
                        </div>
                    </div>
                </div>
            </aside>
            <!-- Instructions if empty -->
            <div v-if="store.workflowSteps.length === 0" class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
                <div class="text-center animate-in">
                    <div class="w-20 h-20 bg-indigo-100 rounded-3xl flex items-center justify-center text-indigo-500 mx-auto mb-6 shadow-xl shadow-indigo-500/10">
                        <PlusIcon class="w-10 h-10" />
                    </div>
                    <h3 class="text-xl font-extrabold text-gray-900">Start your automation</h3>
                    <p class="text-gray-400 mt-2 max-w-xs mx-auto">Drag any block from the sidebar on the left to start building your flow.</p>
                </div>
            </div>

            <!-- Vue Flow Canvas -->
            <Canvas />
            
            <!-- Properties Sidebar -->
            <PropertiesSidebar />
        </div>

    </div>
</template>

<style scoped>
header {
  background: linear-gradient(to right, white, #f8fafc);
}
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
