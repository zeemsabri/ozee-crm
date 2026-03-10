<script setup>
/**
 * ModelPickerModalV2.vue
 * A premium modal for selecting models from the automation schema.
 * Features: Grouping, Search, Icons.
 */
import { ref, computed, watch } from 'vue';
import { 
    XMarkIcon, 
    MagnifyingGlassIcon, 
    BriefcaseIcon, 
    UsersIcon, 
    EnvelopeIcon, 
    ChartBarIcon, 
    FolderIcon,
    CubeIcon,
    ChevronRightIcon,
    CheckIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    schema: { type: Array, default: () => [] },
    selectedModel: { type: String, default: '' }
});

const emit = defineEmits(['close', 'select']);

const searchQuery = ref('');
const activeGroup = ref('all');

const groups = [
    { id: 'all',     label: 'All Models',     icon: CubeIcon },
    { id: 'core',    label: 'Core Entities',  icon: BriefcaseIcon, models: ['Project', 'Task', 'Subtask', 'Milestone'] },
    { id: 'people',  label: 'People & Leads', icon: UsersIcon,     models: ['User', 'Client', 'Lead'] },
    { id: 'comms',   label: 'Communication', icon: EnvelopeIcon,   models: ['Email', 'ProjectNote', 'Comment'] },
    { id: 'system',  label: 'System & Data',  icon: ChartBarIcon,  models: ['UserProductivity', 'Campaign', 'Category', 'CategorySet'] },
];

const filteredModels = computed(() => {
    let list = props.schema;

    // Filter by Search
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(m => m.name.toLowerCase().includes(q));
    }

    // Filter by Group (only if no search query, or maybe combined)
    if (!searchQuery.value && activeGroup.value !== 'all') {
        const group = groups.find(g => g.id === activeGroup.value);
        if (group && group.models) {
            list = list.filter(m => group.models.includes(m.name));
        } else if (activeGroup.value === 'other') {
            // Find models not in any specific group
            const allGroupedModels = groups.flatMap(g => g.models || []);
            list = list.filter(m => !allGroupedModels.includes(m.name));
        }
    }

    return list;
});

// Group the final list for display if searching
const isSearching = computed(() => searchQuery.value.length > 0);

function handleSelect(modelName) {
    emit('select', modelName);
    emit('close');
}

// Reset state when opening
watch(() => props.show, (val) => {
    if (val) {
        searchQuery.value = '';
        activeGroup.value = 'all';
    }
});

</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[300] flex items-center justify-center p-4 md:p-6 cursor-default">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="$emit('close')"></div>

            <!-- Modal Content -->
            <div class="relative w-full max-w-4xl h-[600px] bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col animate-in">
                
                <!-- Header -->
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-xl font-black text-slate-800 tracking-tight">Select Data Source</h3>
                        <p class="text-xs text-slate-500 mt-1 font-medium">Choose a model to fetch records from for this automation step.</p>
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
                            placeholder="Search models (e.g. Project, Task)..." 
                            class="w-full pl-12 pr-4 py-3 bg-slate-50 border-2 border-transparent focus:border-indigo-500 focus:bg-white rounded-2xl outline-none text-sm font-bold text-slate-700 transition-all"
                        />
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="flex-1 flex overflow-hidden">
                    
                    <!-- Left Sidebar (Groups) -->
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
                            
                            <button 
                                @click="activeGroup = 'other'; searchQuery = ''"
                                class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all"
                                :class="activeGroup === 'other' && !searchQuery ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-100' : 'hover:bg-slate-100/50 hover:text-slate-900'"
                            >
                                <CubeIcon class="w-5 h-5" :class="activeGroup === 'other' && !searchQuery ? 'text-indigo-500' : 'text-slate-400'" />
                                Others
                            </button>
                        </div>
                    </div>

                    <!-- Right Content (Model List) -->
                    <div class="flex-1 overflow-y-auto p-8">
                        
                        <div v-if="filteredModels.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <button 
                                v-for="m in filteredModels" 
                                :key="m.name"
                                @click="handleSelect(m.name)"
                                class="flex items-center gap-4 p-4 rounded-2xl border-2 text-left transition-all group/card"
                                :class="selectedModel === m.name 
                                    ? 'bg-indigo-50 border-indigo-200 ring-4 ring-indigo-50/50' 
                                    : 'bg-white border-slate-50 hover:border-slate-100 hover:shadow-md'"
                            >
                                <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center transition-colors group-hover/card:bg-indigo-100/50 shrink-0">
                                    <CubeIcon class="w-5 h-5 text-slate-400 group-hover/card:text-indigo-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-black text-slate-800 truncate">{{ m.name }}</p>
                                        <CheckIcon v-if="selectedModel === m.name" class="w-4 h-4 text-indigo-600" />
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-0.5 font-bold uppercase tracking-wider">{{ m.columns?.length || 0 }} fields</p>
                                </div>
                                <ChevronRightIcon class="w-4 h-4 text-slate-300 opacity-0 group-hover/card:opacity-100 transition-opacity" />
                            </button>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="h-full flex flex-col items-center justify-center text-center opacity-60 py-12">
                            <CubeIcon class="w-16 h-16 text-slate-100 mb-4" />
                            <p class="text-sm font-bold text-slate-400">No models found</p>
                            <p class="text-xs text-slate-300 mt-1">Try searching for something else or check another category.</p>
                            <button 
                                v-if="searchQuery" 
                                @click="searchQuery = ''; activeGroup = 'all'"
                                class="mt-4 text-xs font-black text-indigo-500 hover:underline"
                            >
                                Clear filters
                            </button>
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
