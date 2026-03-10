<script setup>
/**
 * TokenPickerModalV2.vue
 * A premium modal for selecting data tokens (dynamic variables).
 * Groups tokens by source (Trigger, Previous Steps, Loop, etc).
 */
import { ref, computed, watch } from 'vue';
import { 
    XMarkIcon, 
    MagnifyingGlassIcon, 
    ChevronRightIcon,
    BoltIcon,
    ArrowPathIcon,
    CpuChipIcon,
    FolderIcon,
    VariableIcon,
    LinkIcon,
    Square3Stack3DIcon,
    QueueListIcon,
    CheckIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    sources: { type: Array, default: () => [] }
});

const emit = defineEmits(['close', 'select']);

const searchQuery = ref('');
const activeSourceId = ref('all');

// Categories for the sidebar
const categories = computed(() => {
    const cats = [
        { id: 'all', label: 'All Sources', icon: Square3Stack3DIcon }
    ];
    
    props.sources.forEach(src => {
        cats.push({
            id: src.id,
            label: src.label,
            icon: src.icon || QueueListIcon
        });
    });
    
    return cats;
});

const filteredTokens = computed(() => {
    const q = searchQuery.value.toLowerCase();
    const result = [];
    
    props.sources.forEach(src => {
        // If we are filtered by source and it's not 'all', skip other sources
        if (activeSourceId.value !== 'all' && src.id !== activeSourceId.value) return;
        
        const matchingFields = src.fields.filter(f => f.toLowerCase().includes(q));
        
        matchingFields.forEach(f => {
            result.push({
                sourceId: src.id,
                sourceLabel: src.label,
                icon: src.icon,
                field: f,
                token: `{{${src.id}.${f}}}`
            });
        });
    });
    
    return result;
});

function handleSelect(token) {
    emit('select', token);
    emit('close');
}

watch(() => props.show, (val) => {
    if (val) {
        searchQuery.value = '';
        activeSourceId.value = 'all';
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[500] flex items-center justify-center p-4 md:p-6 cursor-default">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="$emit('close')"></div>

            <!-- Modal Content -->
            <div class="relative w-full max-w-4xl h-[640px] bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col animate-in">
                
                <!-- Header -->
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-xl font-black text-slate-800 tracking-tight">Select Data Field</h3>
                        <p class="text-xs text-slate-500 mt-1 font-medium">Pick a dynamic value from your automation's history to insert into this field.</p>
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
                            placeholder="Search fields (e.g. subject, record id, name)..." 
                            class="w-full pl-12 pr-4 py-3 bg-slate-50 border-2 border-transparent focus:border-indigo-500 focus:bg-white rounded-2xl outline-none text-sm font-bold text-slate-700 transition-all"
                        />
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="flex-1 flex overflow-hidden">
                    
                    <!-- Left Sidebar (Sources) -->
                    <div class="w-64 border-r border-slate-100 bg-slate-50/30 overflow-y-auto pt-4 pb-8">
                        <div class="px-4 mb-2">
                             <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-4 mb-4">Data Sources</p>
                        </div>
                        <div class="space-y-1 px-2 text-slate-600">
                            <button 
                                v-for="cat in categories" 
                                :key="cat.id"
                                @click="activeSourceId = cat.id"
                                class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all text-left"
                                :class="activeSourceId === cat.id ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-100' : 'hover:bg-slate-100/50 hover:text-slate-900'"
                            >
                                <component :is="cat.icon" class="w-5 h-5 shrink-0" :class="activeSourceId === cat.id ? 'text-indigo-500' : 'text-slate-400'" />
                                <span class="truncate">{{ cat.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Right Content (Token List) -->
                    <div class="flex-1 overflow-y-auto p-8">
                        
                        <div v-if="filteredTokens.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <button 
                                v-for="(t, idx) in filteredTokens" 
                                :key="idx"
                                @click="handleSelect(t.token)"
                                class="flex items-center gap-4 p-4 rounded-2xl border-2 border-slate-50 hover:border-indigo-100 hover:shadow-md transition-all group/card text-left bg-white"
                            >
                                <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center shrink-0 transition-colors group-hover/card:bg-indigo-100/50">
                                    <component :is="t.icon || QueueListIcon" class="w-5 h-5 text-slate-400 group-hover/card:text-indigo-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-0.5">{{ t.sourceLabel }}</p>
                                    <p class="text-sm font-black text-slate-800 truncate">{{ t.field }}</p>
                                    <p class="text-[9px] text-indigo-400 font-mono mt-1 opacity-60 group-hover/card:opacity-100 transition-opacity">{{ t.token }}</p>
                                </div>
                                <ChevronRightIcon class="w-4 h-4 text-slate-300 opacity-0 group-hover/card:opacity-100 transition-all -translate-x-2 group-hover/card:translate-x-0" />
                            </button>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="h-full flex flex-col items-center justify-center text-center opacity-60 py-12">
                            <Square3Stack3DIcon class="w-16 h-16 text-slate-100 mb-4" />
                            <p class="text-sm font-bold text-slate-400">No fields found</p>
                            <p class="text-xs text-slate-300 mt-1">Try a different search or select "All Sources".</p>
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
