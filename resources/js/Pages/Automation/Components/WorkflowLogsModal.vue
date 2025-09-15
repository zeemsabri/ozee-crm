<script setup>
import { computed, ref } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';

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
  try { return ts ? new Date(ts).toLocaleString() : ''; } catch { return ts || ''; }
};

const expanded = ref(new Set());
const toggle = (id) => {
  const s = new Set(expanded.value);
  if (s.has(id)) s.delete(id); else s.add(id);
  expanded.value = s;
};
const isOpen = (id) => expanded.value.has(id);

const pretty = (obj) => {
  try {
    if (obj === null || obj === undefined || obj === '') return '';
    return typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
  } catch (e) {
    return String(obj);
  }
};
</script>

<template>
  <div v-if="state.show" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[85vh] flex flex-col">
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <div>
          <h3 class="text-lg font-semibold text-gray-800">Execution Logs</h3>
          <p v-if="state.workflow" class="text-xs text-gray-500">Workflow: {{ state.workflow.name }}</p>
        </div>
        <button @click="close" class="text-gray-500 hover:text-gray-700">✕</button>
      </div>

      <div class="p-4 overflow-auto flex-1">
        <div v-if="state.isLoading" class="text-sm text-gray-500">Loading logs...</div>
        <div v-else>
          <div v-if="!state.items.length" class="text-sm text-gray-500">No logs found.</div>
          <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-700 w-24">Time</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700">Step</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700">Status</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700">Error</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700 w-20">Details</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <template v-for="log in state.items" :key="log.id">
                <tr>
                  <td class="px-3 py-2 align-top">{{ formatDate(log.executed_at) }}</td>
                  <td class="px-3 py-2 align-top">
                    <div class="font-medium text-gray-900 flex items-center gap-2">
                      <span>{{ log.step?.name || `Step #${log.step_id}` }}</span>
                      <span v-if="log.step?.step_type" class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-100 text-gray-700">{{ log.step.step_type }}</span>
                    </div>
                    <div class="text-xs text-gray-500 flex gap-2 flex-wrap">
                      <span>ID: {{ log.id }}</span>
                      <span v-if="log.step_id">Step ID: {{ log.step_id }}</span>
                      <span v-if="log.triggering_object_id">Object: {{ log.triggering_object_id }}</span>
                    </div>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <span :class="[
                      'px-2 py-0.5 rounded-full text-xs',
                      log.status === 'success' ? 'bg-green-100 text-green-800' :
                      log.status === 'failed' ? 'bg-red-100 text-red-800' :
                      log.status === 'scheduled' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-gray-100 text-gray-800'
                    ]">{{ log.status }}</span>
                    <div class="text-xs text-gray-500 mt-0.5 flex gap-3">
                      <span v-if="log.duration_ms">{{ log.duration_ms }} ms</span>
                      <span v-if="log.token_usage">tokens: {{ log.token_usage }}</span>
                      <span v-if="log.cost">cost: ${{ Number(log.cost).toFixed(4) }}</span>
                    </div>
                  </td>
                  <td class="px-3 py-2 max-w-[320px] align-top">
                    <div class="truncate text-red-700" :title="log.error_message || ''">{{ log.error_message }}</div>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <button @click="toggle(log.id)" class="px-2 py-1 rounded border hover:bg-gray-50">
                      {{ isOpen(log.id) ? 'Hide' : 'View' }}
                    </button>
                  </td>
                </tr>
                <tr v-if="isOpen(log.id)" class="bg-gray-50/50">
                  <td colspan="5" class="px-3 py-3">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3 text-xs text-gray-600">
                      <div>
                        <div class="font-semibold text-gray-700 mb-1">Step Info</div>
                        <div>Name: <span class="text-gray-800">{{ log.step?.name || '-' }}</span></div>
                        <div>Type: <span class="text-gray-800 uppercase">{{ log.step?.step_type || '-' }}</span></div>
                        <div>Order: <span class="text-gray-800">{{ log.step?.step_order ?? '-' }}</span></div>
                      </div>
                      <div>
                        <div class="font-semibold text-gray-700 mb-1">Execution</div>
                        <div>Status: <span class="text-gray-800">{{ log.status }}</span></div>
                        <div>Executed At: <span class="text-gray-800">{{ formatDate(log.executed_at) }}</span></div>
                        <div v-if="log.duration_ms">Duration: <span class="text-gray-800">{{ log.duration_ms }} ms</span></div>
                      </div>
                      <div>
                        <div class="font-semibold text-gray-700 mb-1">Metrics</div>
                        <div v-if="log.token_usage">Token Usage: <span class="text-gray-800">{{ log.token_usage }}</span></div>
                        <div v-if="log.cost">Cost: <span class="text-gray-800">${{ Number(log.cost).toFixed(6) }}</span></div>
                        <div v-if="log.triggering_object_id">Object ID: <span class="text-gray-800">{{ log.triggering_object_id }}</span></div>
                      </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                      <div>
                        <div class="text-xs font-semibold text-gray-700 mb-1">Input Context</div>
                        <pre class="bg-white border rounded p-2 max-h-48 overflow-auto text-xs whitespace-pre-wrap">{{ pretty(log.input_context) }}</pre>
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-gray-700 mb-1">Raw Output</div>
                        <pre class="bg-white border rounded p-2 max-h-48 overflow-auto text-xs whitespace-pre-wrap">{{ pretty(log.raw_output) }}</pre>
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-gray-700 mb-1">Parsed Output</div>
                        <pre class="bg-white border rounded p-2 max-h-48 overflow-auto text-xs whitespace-pre-wrap">{{ pretty(log.parsed_output) }}</pre>
                      </div>
                    </div>
                    <div v-if="log.error_message" class="mt-3 text-xs">
                      <div class="font-semibold text-red-700 mb-1">Error Message</div>
                      <pre class="bg-white border border-red-200 rounded p-2 max-h-48 overflow-auto text-xs whitespace-pre-wrap text-red-800">{{ log.error_message }}</pre>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <div class="px-4 py-3 border-t flex items-center justify-between text-sm">
        <div>
          <span v-if="meta">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        </div>
        <div class="space-x-2">
          <button @click="goPrev" :disabled="!canPrev" class="px-3 py-1 rounded border disabled:opacity-50">Prev</button>
          <button @click="goNext" :disabled="!canNext" class="px-3 py-1 rounded border disabled:opacity-50">Next</button>
        </div>
      </div>
    </div>
  </div>
</template>
