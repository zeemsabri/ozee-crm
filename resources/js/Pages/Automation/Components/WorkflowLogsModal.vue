<script setup>
import { computed, ref, watch } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';
import { 
  ChevronRightIcon, 
  ChevronDownIcon, 
  ClockIcon, 
  ActivityIcon, 
  AlertCircleIcon, 
  CheckCircle2Icon,
  SearchIcon,
  XIcon,
  ExternalLinkIcon
} from 'lucide-vue-next';
import { Splitpanes, Pane } from 'splitpanes';
import 'splitpanes/dist/splitpanes.css';

const store = useWorkflowStore();
const state = computed(() => store.logsModal);

const close = () => store.hideLogs();
const meta = computed(() => state.value.meta || null);
const canPrev = computed(() => meta.value && meta.value.current_page > 1);
const canNext = computed(() => meta.value && meta.value.current_page < meta.value.last_page);

const goPrev = () => {
  if (canPrev.value) store.goToLogsPage(meta.value.current_page - 1);
};
const goNext = () => {
  if (canNext.value) store.goToLogsPage(meta.value.current_page + 1);
};

const formatDate = (ts) => {
  try { 
    if (!ts) return '';
    const date = new Date(ts);
    return date.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
  } catch { 
    return ts || ''; 
  }
};

const selectedStep = ref(null);
const expandedExecutions = ref(new Set());

// Group logs by execution_id
const groupedExecutions = computed(() => {
  const allLogs = state.value.items;
  if (!allLogs.length) return [];

  // Map for easy lookup and to hold tree structure
  const logsMap = {};
  allLogs.forEach(l => {
      logsMap[l.id] = { ...l, children: [] };
  });

  const processedLogIds = new Set();
  const sessions = [];

  // Helper to find the "Local Root" of a log within the current batch
  const findLocalRoot = (log) => {
      let curr = log;
      while (curr.parent_execution_log_id && logsMap[curr.parent_execution_log_id]) {
          curr = logsMap[curr.parent_execution_log_id];
      }
      return curr;
  };

  // 1. Grouping Pass
  const groupsTemp = {}; // Key: execution_id or 'root-' + rootLogId

  allLogs.forEach(log => {
      const logObj = logsMap[log.id];
      let sessionId;
      let isLegacy = false;

      if (log.execution_id) {
          sessionId = log.execution_id;
      } else {
          const localRoot = findLocalRoot(logObj);
          sessionId = `root-${localRoot.id}`;
          isLegacy = true;
      }

      if (!groupsTemp[sessionId]) {
          groupsTemp[sessionId] = {
              id: sessionId,
              isLegacy,
              startTime: log.executed_at,
              endTime: log.executed_at,
              status: 'success',
              steps: [], // Top-level steps within this session
              allStepsMap: {}, // Helper for tree-ification
              totalDuration: 0,
              totalCost: 0
          };
      }

      const group = groupsTemp[sessionId];
      group.allStepsMap[log.id] = logObj;

      // Update group metadata
      if (new Date(log.executed_at) < new Date(group.startTime)) group.startTime = log.executed_at;
      if (new Date(log.executed_at) > new Date(group.endTime)) group.endTime = log.executed_at;
      if (log.status === 'failed') group.status = 'failed';
      else if (log.status === 'scheduled' && group.status !== 'failed') group.status = 'scheduled';
      
      group.totalDuration += (log.duration_ms || 0);
      group.totalCost += Number(log.cost || 0);
  });

  // 2. Tree-ification Pass (Within each group)
  Object.values(groupsTemp).forEach(group => {
      const logsInGroup = Object.values(group.allStepsMap).sort((a, b) => a.id - b.id);
      logsInGroup.forEach(log => {
          if (log.parent_execution_log_id && group.allStepsMap[log.parent_execution_log_id]) {
              group.allStepsMap[log.parent_execution_log_id].children.push(log);
          } else {
              group.steps.push(log);
          }
      });
      delete group.allStepsMap;
      sessions.push(group);
  });

  // Sort by startTime descending
  return sessions.sort((a, b) => new Date(b.startTime) - new Date(a.startTime));
});

const toggleExecution = (execId) => {
  if (expandedExecutions.value.has(execId)) {
    expandedExecutions.value.delete(execId);
  } else {
    expandedExecutions.value.add(execId);
  }
};

const selectStepLog = (log) => {
  selectedStep.value = log;
};

const pretty = (obj) => {
  try {
    if (obj === null || obj === undefined || obj === '') return '';
    return typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
  } catch (e) {
    return String(obj);
  }
};

// Automatic selection of first log if none selected
watch(() => state.value.items, (newItems) => {
  if (newItems.length > 0 && !selectedStep.value) {
    selectedStep.value = newItems[0];
  }
}, { immediate: true });

const activeTab = ref('output');
</script>

<script>
// Local component for recursive rendering of steps
const StepItem = {
  name: 'StepItem',
  props: ['step', 'idx', 'total', 'selectedStep'],
  emits: ['select'],
  template: `
    <div class="space-y-1">
      <div 
        @click="$emit('select', step)"
        class="group flex items-center gap-3 p-2.5 rounded-lg cursor-pointer transition select-none"
        :class="[
            selectedStep?.id === step.id 
            ? 'bg-indigo-600 text-white shadow-md' 
            : 'hover:bg-white hover:shadow-sm text-slate-600'
        ]"
      >
        <div class="flex-shrink-0 flex flex-col items-center">
            <div :class="[
                'w-2 h-2 rounded-full',
                selectedStep?.id === step.id ? 'bg-white' : (step.status === 'success' ? 'bg-emerald-500' : (step.status === 'failed' ? 'bg-red-500' : 'bg-amber-500'))
            ]"></div>
            <div v-if="idx < total - 1 || step.children?.length" class="w-0.5 h-4 my-1 opacity-20" :class="selectedStep?.id === step.id ? 'bg-white' : 'bg-slate-400'"></div>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-bold truncate">
              {{ step.step?.name || \`Step #\${step.step_id}\` }}
            </p>
            <p v-if="step.duration_ms" class="text-[10px] opacity-70 whitespace-nowrap">{{ step.duration_ms }}ms</p>
          </div>
          <p class="text-[10px] uppercase tracking-wider opacity-60 font-semibold">
            {{ step.step?.step_type || 'Unknown Type' }}
          </p>
        </div>
      </div>
      
      <!-- Nested Children -->
      <div v-if="step.children?.length" class="ml-5 border-l border-slate-200 pl-1 space-y-1">
        <StepItem 
          v-for="(child, cIdx) in step.children" 
          :key="child.id"
          :step="child"
          :idx="cIdx"
          :total="step.children.length"
          :selectedStep="selectedStep"
          @select="$emit('select', $event)"
        />
      </div>
    </div>
  `
};
</script>

<template>
  <div v-if="state.show" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-[95vw] h-[90vh] flex flex-col overflow-hidden border border-slate-200">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b bg-slate-50">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-indigo-100 rounded-lg text-indigo-600">
            <ActivityIcon class="w-5 h-5" />
          </div>
          <div>
            <h3 class="text-xl font-bold text-slate-800">Workflow Executions</h3>
            <p v-if="state.workflow" class="text-sm font-medium text-slate-500">
              Tracing logic for <span class="text-indigo-600">{{ state.workflow.name }}</span>
            </p>
          </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex items-center bg-white border border-slate-200 rounded-lg px-2 py-1 space-x-2 shadow-sm">
                <button @click="goPrev" :disabled="!canPrev" class="p-1 hover:bg-slate-100 disabled:opacity-30 rounded transition">
                    <ChevronRightIcon class="w-5 h-5 rotate-180" />
                </button>
                <span class="text-sm font-medium text-slate-600 min-w-[80px] text-center" v-if="meta">
                    Page {{ meta.current_page }} / {{ meta.last_page }}
                </span>
                <button @click="goNext" :disabled="!canNext" class="p-1 hover:bg-slate-100 disabled:opacity-30 rounded transition">
                    <ChevronRightIcon class="w-5 h-5" />
                </button>
            </div>
            <button @click="close" class="p-2 hover:bg-slate-100 rounded-full text-slate-400 hover:text-slate-600 transition">
                <XIcon class="w-6 h-6" />
            </button>
        </div>
      </div>

      <!-- Main Body -->
      <div class="flex-1 overflow-hidden relative">
        <div v-if="state.isLoading" class="absolute inset-0 z-10 bg-white/80 flex items-center justify-center">
            <div class="flex flex-col items-center gap-2">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div>
                <p class="text-slate-500 font-medium">Fetching logs...</p>
            </div>
        </div>

        <splitpanes class="default-theme h-full">
          <!-- Left Pane: Timeline -->
          <pane min-size="25" size="35" class="bg-slate-50 border-r border-slate-200 overflow-y-auto">
            <div class="p-4 space-y-4">
              <div v-if="!groupedExecutions.length && !state.isLoading" class="text-center py-20">
                <ClockIcon class="w-12 h-12 text-slate-200 mx-auto mb-3" />
                <p class="text-slate-400">No execution logs found yet.</p>
              </div>

              <div v-for="exec in groupedExecutions" :key="exec.id" 
                class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm transition hover:shadow-md"
                :class="{'ring-2 ring-indigo-500 ring-offset-0': selectedStep && exec.steps.some(s => s.id === selectedStep.id)}"
              >
                <!-- Group Header -->
                <div 
                  @click="toggleExecution(exec.id)"
                  class="px-4 py-3 flex items-center justify-between cursor-pointer hover:bg-slate-50 transition select-none border-b border-transparent"
                  :class="{'border-slate-100': expandedExecutions.has(exec.id)}"
                >
                  <div class="flex items-center gap-3">
                    <div :class="[
                      'w-2.5 h-2.5 rounded-full',
                      exec.status === 'success' ? 'bg-emerald-500' : 
                      exec.status === 'failed' ? 'bg-red-500' : 'bg-amber-500 shadow-sm shadow-amber-200'
                    ]"></div>
                    <div>
                      <div class="flex items-center gap-2">
                         <p class="text-sm font-bold text-slate-700">
                           {{ exec.isLegacy ? 'Standalone Step' : `Run #${exec.id.substring(0, 8)}` }}
                         </p>
                         <span v-if="exec.totalCost > 0" class="text-[10px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-bold">
                            ${{ exec.totalCost.toFixed(4) }}
                         </span>
                      </div>
                      <p class="text-xs text-slate-500 flex items-center gap-1">
                        <ClockIcon class="w-3 h-3" />
                        {{ formatDate(exec.startTime) }}
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-400">{{ exec.steps.length }} steps</span>
                    <ChevronDownIcon 
                        class="w-5 h-5 text-slate-400 transition-transform duration-200"
                        :class="{'rotate-180': expandedExecutions.has(exec.id)}"
                    />
                  </div>
                </div>

                <!-- Steps List -->
                <div v-if="expandedExecutions.has(exec.id)" class="bg-slate-50/50 p-2 space-y-1">
                  <StepItem 
                    v-for="(step, idx) in exec.steps" 
                    :key="step.id"
                    :step="step"
                    :idx="idx"
                    :total="exec.steps.length"
                    :selectedStep="selectedStep"
                    @select="selectStepLog"
                  />
                </div>
              </div>
            </div>
          </pane>

          <!-- Right Pane: Detail View -->
          <pane class="bg-white flex flex-col overflow-hidden">
            <div v-if="!selectedStep" class="flex-1 flex flex-col items-center justify-center p-10 text-center opacity-40">
                <SearchIcon class="w-16 h-16 mb-4" />
                <h4 class="text-lg font-bold">Select a step to investigate</h4>
                <p>Execution details, inputs, and outputs will appear here.</p>
            </div>

            <template v-else>
                <!-- Detail Header -->
                <div class="px-6 py-5 border-b flex items-start justify-between bg-white sticky top-0 z-20">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="text-xl font-extrabold text-slate-800">
                                {{ selectedStep.step?.name || `Step Execution #${selectedStep.id}` }}
                            </h4>
                            <span :class="[
                                'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                selectedStep.status === 'success' ? 'bg-emerald-100 text-emerald-700' : 
                                selectedStep.status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'
                            ]">{{ selectedStep.status }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-xs font-medium text-slate-500">
                             <span class="flex items-center gap-1.5"><ClockIcon class="w-3.5 h-3.5" /> {{ formatDate(selectedStep.executed_at) }}</span>
                             <span v-if="selectedStep.duration_ms" class="px-2 border-l border-slate-200">Duration: {{ selectedStep.duration_ms }}ms</span>
                             <span v-if="selectedStep.token_usage" class="px-2 border-l border-slate-200">Tokens: {{ selectedStep.token_usage }}</span>
                        </div>
                    </div>
                    <div>
                        <button class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-bold transition">
                           <ExternalLinkIcon class="w-3.5 h-3.5" /> Raw Log Info
                        </button>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="flex items-center px-6 border-b bg-slate-50/50">
                    <button 
                        v-for="tab in ['output', 'input', 'config', 'error']" 
                        :key="tab"
                        @click="activeTab = tab"
                        class="px-4 py-3 text-xs font-bold uppercase tracking-widest transition-all border-b-2 -mb-px"
                        :class="[
                            activeTab === tab 
                            ? 'border-indigo-600 text-indigo-600 bg-white' 
                            : 'border-transparent text-slate-400 hover:text-slate-600'
                        ]"
                    >
                        {{ tab }}
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="flex-1 overflow-auto p-6 bg-slate-50/30">
                    <!-- Output Tab -->
                    <div v-show="activeTab === 'output'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                        <div>
                            <p class="text-[11px] font-black uppercase text-slate-400 tracking-wider mb-2">Parsed Output</p>
                            <div class="relative group">
                                <pre class="p-4 bg-slate-900 text-indigo-300 rounded-xl overflow-x-auto text-sm font-mono shadow-lg max-h-[400px]">{{ pretty(selectedStep.parsed_output) || 'No parsed output available' }}</pre>
                                <button class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 bg-slate-700 text-white p-1 rounded text-xs transition">Copy</button>
                            </div>
                        </div>
                        <div>
                            <p class="text-[11px] font-black uppercase text-slate-400 tracking-wider mb-2">Raw Data</p>
                            <pre class="p-4 bg-white border border-slate-200 text-slate-700 rounded-xl overflow-x-auto text-xs font-mono shadow-sm">{{ pretty(selectedStep.raw_output) || '-' }}</pre>
                        </div>
                    </div>

                    <!-- Input Tab -->
                    <div v-show="activeTab === 'input'" class="animate-in fade-in slide-in-from-bottom-2 duration-300">
                        <p class="text-[11px] font-black uppercase text-slate-400 tracking-wider mb-2">Input Context (Variables)</p>
                        <pre class="p-4 bg-slate-100 border border-slate-200 text-slate-800 rounded-xl overflow-x-auto text-sm font-mono">{{ pretty(selectedStep.input_context) || 'No input context found' }}</pre>
                    </div>

                    <!-- Config Tab -->
                    <div v-show="activeTab === 'config'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                         <div>
                            <p class="text-[11px] font-black uppercase text-slate-400 tracking-wider mb-2">Step Configuration</p>
                            <pre class="p-4 bg-white border border-slate-200 text-slate-700 rounded-xl overflow-x-auto text-sm font-mono shadow-sm">{{ pretty(selectedStep.step?.step_config) || '-' }}</pre>
                        </div>
                        <div v-if="selectedStep.step?.condition_rules">
                             <p class="text-[11px] font-black uppercase text-slate-400 tracking-wider mb-2">Logic Rules</p>
                             <pre class="p-4 bg-white border border-slate-200 text-slate-700 rounded-xl overflow-x-auto text-sm font-mono shadow-sm">{{ pretty(selectedStep.step.condition_rules) }}</pre>
                        </div>
                    </div>

                    <!-- Error Tab -->
                    <div v-show="activeTab === 'error'" class="animate-in fade-in slide-in-from-bottom-2 duration-300">
                        <div v-if="selectedStep.error_message" class="bg-red-50 border border-red-200 rounded-xl p-5">
                            <div class="flex items-center gap-2 mb-3 text-red-600">
                                <AlertCircleIcon class="w-5 h-5" />
                                <h5 class="font-bold">Execution Error</h5>
                            </div>
                            <p class="text-sm text-red-800 font-mono leading-relaxed bg-white/50 p-4 rounded-lg border border-red-100 break-words">
                                {{ selectedStep.error_message }}
                            </p>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-20 opacity-30">
                            <CheckCircle2Icon class="w-12 h-12 text-emerald-500 mb-3" />
                            <p class="font-bold">No errors reported for this step</p>
                        </div>
                    </div>
                </div>
            </template>
          </pane>
        </splitpanes>
      </div>
    </div>
  </div>
</template>

<style>
.splitpanes.default-theme .splitpanes__splitter {
  background-color: transparent !important;
  width: 8px !important;
  border-left: 1px solid #e2e8f0;
  transition: all 0.2s;
}
.splitpanes.default-theme .splitpanes__splitter:hover {
  background-color: #f1f5f9 !important;
}
.splitpanes.default-theme .splitpanes__splitter:before,
.splitpanes.default-theme .splitpanes__splitter:after {
  background-color: #cbd5e1 !important;
}

::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
  background: #cbd5e1;
}

.animate-in {
    animation: animate-in 0.3s ease-out;
}

@keyframes animate-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
