<script setup>
import { ref, computed, watch } from 'vue';
import { 
    ChevronRightIcon, 
    ChevronDownIcon, 
    ClipboardDocumentIcon, 
    CheckIcon,
    MagnifyingGlassIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    data: { type: [Object, Array, String], default: null },
    title: { type: String, default: 'JSON Data' },
    startExpanded: { type: Boolean, default: true }
});

const searchQuery = ref('');
const copied = ref(false);

// Parse if string passed
const parsedData = computed(() => {
    if (typeof props.data === 'string') {
        try { return JSON.parse(props.data); } catch { return props.data; }
    }
    return props.data;
});

const copyJson = async () => {
    try {
        const text = typeof parsedData.value === 'string' 
            ? parsedData.value 
            : JSON.stringify(parsedData.value, null, 2);
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    } catch (e) {
        console.error('Copy failed', e);
    }
};

</script>

<script>
// Recursive JSON Node component
const JsonNode = {
    name: 'JsonNode',
    props: {
        nodeKey: { type: [String, Number], default: '' },
        value: { required: true },
        isLast: { type: Boolean, default: true },
        depth: { type: Number, default: 0 },
        search: { type: String, default: '' },
        forceExpand: { type: Boolean, default: false }
    },
    setup(props) {
        const isExpanded = ref(props.depth < 3 || props.forceExpand);
        
        watch(() => props.forceExpand, (val) => {
            if (val) isExpanded.value = true;
        });

        const toggle = () => isExpanded.value = !isExpanded.value;

        const isObject = computed(() => props.value !== null && typeof props.value === 'object');
        const isArray = computed(() => Array.isArray(props.value));
        
        const typeClass = computed(() => {
            if (props.value === null) return 'text-slate-500';
            if (typeof props.value === 'string') return 'text-emerald-400';
            if (typeof props.value === 'number') return 'text-amber-400';
            if (typeof props.value === 'boolean') return 'text-pink-400';
            return 'text-indigo-300';
        });

        const displayValue = computed(() => {
            if (props.value === null) return 'null';
            if (typeof props.value === 'string') return `"${props.value}"`;
            return String(props.value);
        });

        const hasMatch = computed(() => {
            if (!props.search) return false;
            const s = props.search.toLowerCase();
            if (String(props.nodeKey).toLowerCase().includes(s)) return true;
            if (!isObject.value && displayValue.value.toLowerCase().includes(s)) return true;
            return false;
        });

        const shouldShow = computed(() => {
            if (!props.search) return true;
            if (hasMatch.value) return true;
            if (isObject.value) {
                // If any child matches, show this node
                return Object.entries(props.value).some(([k, v]) => {
                    const s = props.search.toLowerCase();
                    if (k.toLowerCase().includes(s)) return true;
                    if (v !== null && typeof v !== 'object' && String(v).toLowerCase().includes(s)) return true;
                    // Note: deep search is complex recursively in computed, doing shallow hit for now
                    return JSON.stringify(v).toLowerCase().includes(s);
                });
            }
            return false;
        });

        return { isExpanded, toggle, isObject, isArray, typeClass, displayValue, hasMatch, shouldShow };
    },
    template: `
        <div v-show="shouldShow" class="font-mono text-[11px] sm:text-xs leading-relaxed" :class="{'bg-indigo-500/20 rounded px-1': hasMatch && search}">
            <div class="flex items-start group">
                <!-- Indent & Toggle -->
                <div class="flex items-center shrink-0 w-4 -ml-4" @click="isObject && toggle()">
                    <svg v-if="isObject" class="w-3 h-3 text-slate-500 cursor-pointer hover:text-slate-300 transition-transform" :class="{'rotate-90': isExpanded}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>

                <!-- Key -->
                <div class="flex flex-wrap items-baseline min-w-0">
                    <span v-if="nodeKey !== ''" class="text-sky-300 mr-1.5 font-semibold">"{{ nodeKey }}":</span>

                    <!-- Object/Array Start -->
                    <template v-if="isObject">
                        <span class="text-slate-400 cursor-pointer" @click="toggle()">{{ isArray ? '[' : '{' }}</span>
                        
                        <span v-if="!isExpanded" class="text-slate-500 mx-2 cursor-pointer select-none" @click="toggle()">
                            {{ isArray ? \`\${value.length} items\` : '...' }}
                        </span>
                        
                        <span v-if="!isExpanded" class="text-slate-400 cursor-pointer" @click="toggle()">{{ isArray ? ']' : '}' }}<span v-if="!isLast">,</span></span>
                    </template>

                    <!-- Primitive Value -->
                    <template v-else>
                        <span :class="typeClass" class="break-all">{{ displayValue }}</span>
                        <span v-if="!isLast" class="text-slate-400">,</span>
                    </template>
                </div>
            </div>

            <!-- Object/Array Children -->
            <div v-if="isObject && isExpanded" class="ml-4 pl-2 border-l border-slate-700/50">
                <JsonNode 
                    v-for="(val, key, index) in value" 
                    :key="key"
                    :nodeKey="isArray ? '' : key"
                    :value="val"
                    :isLast="index === Object.keys(value).length - 1"
                    :depth="depth + 1"
                    :search="search"
                    :forceExpand="!!search || forceExpand"
                />
            </div>
            
            <!-- Object/Array End -->
            <div v-if="isObject && isExpanded" class="flex items-center">
                <span class="text-slate-400 cursor-pointer" @click="toggle()">{{ isArray ? ']' : '}' }}<span v-if="!isLast">,</span></span>
            </div>
        </div>
    `
};
</script>

<template>
  <div class="flex flex-col bg-slate-900 rounded-xl border border-slate-800 shadow-xl overflow-hidden relative group/json">
      <!-- Toolbar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between px-3 py-2 bg-slate-800/80 border-b border-slate-700/50 gap-2">
          <div class="flex items-center gap-3">
              <span class="text-xs font-bold text-slate-400 uppercase tracking-widest pl-1">{{ title }}</span>
          </div>
          
          <div class="flex items-center justify-end gap-2 w-full sm:w-auto">
              <div class="relative w-full sm:w-64 text-slate-300">
                  <MagnifyingGlassIcon class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 opacity-50" />
                  <input 
                      v-model="searchQuery" 
                      placeholder="Filter keys or values..." 
                      class="w-full bg-slate-900 border border-slate-700 text-xs rounded-lg pl-8 pr-3 py-1.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 placeholder-slate-500 transition-colors" 
                  />
              </div>
              <button @click="copyJson" class="flex items-center shrink-0 justify-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors" :class="copied ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'">
                  <CheckIcon v-if="copied" class="w-3.5 h-3.5" />
                  <ClipboardDocumentIcon v-else class="w-3.5 h-3.5" />
                  <span class="hidden sm:inline">{{ copied ? 'Copied' : 'Copy' }}</span>
              </button>
          </div>
      </div>

      <!-- Body -->
      <div class="p-4 pl-6 overflow-auto max-h-[500px] bg-[#0d1117] text-slate-300 custom-scrollbar">
          <template v-if="parsedData !== null && parsedData !== undefined && parsedData !== ''">
              <JsonNode 
                  :value="parsedData" 
                  :search="searchQuery" 
                  :forceExpand="!!searchQuery || startExpanded"
                  :isLast="true"
              />
          </template>
          <div v-else class="text-slate-500 italic text-xs py-2 text-center">
              No data or empty
          </div>
      </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
</style>
