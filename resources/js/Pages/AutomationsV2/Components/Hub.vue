<script setup>
/**
 * Hub.vue
 * List view for V2 automations.
 * Features: Search, sorting, status toggles, and deletion.
 */
import { ref, computed } from 'vue';
import { useAutomationsV2Store } from '../Store/storeV2';
import { 
    PlusIcon, 
    MagnifyingGlassIcon, 
    PlayIcon, 
    PauseIcon, 
    TrashIcon, 
    EllipsisHorizontalIcon,
    BoltIcon,
    ClockIcon,
    ClipboardDocumentListIcon
} from '@heroicons/vue/24/outline';
import { BoltIcon as BoltIconSolid } from '@heroicons/vue/24/solid';

const emit = defineEmits(['create', 'open']);
const store = useAutomationsV2Store();

const searchQuery = ref('');

const filteredWorkflows = computed(() => {
    if (!searchQuery.value) return store.workflows;
    const q = searchQuery.value.toLowerCase();
    return store.workflows.filter(w => 
        w.name?.toLowerCase().includes(q) || 
        w.trigger_event?.toLowerCase().includes(q)
    );
});

function formatDate(d) {
    if (!d) return 'Never';
    return new Date(d).toLocaleDateString() + ' ' + new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function getTriggerIcon(wf) {
    return wf.trigger_event === 'schedule.run' ? ClockIcon : BoltIcon;
}
</script>

<template>
    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Automation Studio <span class="text-indigo-600">V2</span></h1>
                <p class="text-gray-500 mt-1">Design powerful, automated workflows with zero code.</p>
            </div>
            <button @click="emit('create')" class="v2-btn-primary self-start">
                <PlusIcon class="w-5 h-5" /> New Automation
            </button>
        </div>

        <!-- Filters & Search -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-col md:flex-row items-center gap-4">
            <div class="relative flex-1 w-full">
                <MagnifyingGlassIcon class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" />
                <input 
                    v-model="searchQuery"
                    type="text" 
                    placeholder="Search automations by name or trigger..." 
                    class="w-full pl-12 pr-4 py-3 bg-gray-50 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm"
                />
            </div>
            <div class="flex items-center gap-2">
                <select class="v2-select w-40 !py-2.5">
                    <option>All Status</option>
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
                <select class="v2-select w-40 !py-2.5">
                    <option>Sort: Recent</option>
                    <option>Sort: Name</option>
                </select>
            </div>
        </div>

        <!-- Grid -->
        <div v-if="store.isLoadingWorkflows" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="i in 6" :key="i" class="h-48 bg-gray-100 animate-pulse rounded-2xl"></div>
        </div>

        <div v-else-if="filteredWorkflows.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div 
                v-for="wf in filteredWorkflows" 
                :key="wf.id" 
                class="group bg-white rounded-2xl shadow-sm border border-gray-200 hover:shadow-xl hover:border-indigo-200 transition-all duration-300 overflow-hidden flex flex-col"
            >
                <div class="p-6 flex-1">
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 group-hover:scale-110 transition-transform duration-300">
                             <component :is="getTriggerIcon(wf)" class="w-6 h-6" />
                        </div>
                        <div class="flex items-center gap-1">
                            <span 
                                :class="wf.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                            >
                                {{ wf.is_active ? 'Active' : 'Draft' }}
                            </span>
                        </div>
                    </div>

                    <h3 @click="emit('open', wf.id)" class="text-lg font-bold text-gray-900 group-hover:text-indigo-600 cursor-pointer transition-colors line-clamp-1">{{ wf.name || 'Untitled' }}</h3>
                    <p class="text-xs text-gray-500 mt-1 uppercase font-semibold tracking-wide">{{ wf.trigger_event || 'No trigger' }}</p>
                    
                    <div class="mt-6 flex items-center gap-4 text-xs text-gray-400">
                        <div class="flex items-center gap-1">
                            <EllipsisHorizontalIcon class="w-4 h-4" />
                            <span>{{ (wf.steps?.length || 0) }} Steps</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <PlayIcon class="w-4 h-4" />
                            <span>v1.0</span>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between">
                    <div class="text-[10px] text-gray-400 font-medium italic">
                        Updated {{ formatDate(wf.updated_at) }}
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            @click="store.openLogs(wf.id)"
                            title="View Execution Logs"
                            class="p-2 rounded-lg hover:bg-white hover:shadow-sm text-indigo-400 hover:text-indigo-600 transition"
                        >
                            <ClipboardDocumentListIcon class="w-5 h-5" />
                        </button>
                        <button 
                            @click="store.toggleActive(wf)"
                            :title="wf.is_active ? 'Deactivate' : 'Activate'"
                            class="p-2 rounded-lg hover:bg-white hover:shadow-sm transition"
                            :class="wf.is_active ? 'text-amber-500' : 'text-emerald-500'"
                        >
                            <PauseIcon v-if="wf.is_active" class="w-5 h-5" />
                            <PlayIcon v-else class="w-5 h-5" />
                        </button>
                        <button @click="store.deleteWorkflow(wf.id)" class="p-2 rounded-lg hover:bg-white hover:shadow-sm hover:text-red-500 text-gray-400 transition" title="Delete">
                            <TrashIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-20 bg-white rounded-3xl border border-dashed border-gray-300">
            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-300">
                <BoltIconSolid class="w-10 h-10" />
            </div>
            <h2 class="text-xl font-bold text-gray-900">No automations found</h2>
            <p class="text-gray-500 mt-1">Get started by creating your first automated workflow.</p>
            <button @click="emit('create')" class="v2-btn-primary mx-auto mt-6">
                <PlusIcon class="w-5 h-5" /> Create New
            </button>
        </div>
    </div>
</template>
