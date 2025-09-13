<script setup>
import { computed, ref, watch } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps({
  // Optional base scope: e.g., 'trigger.task' to prefilter list
  baseScope: { type: String, default: '' },
  // Optional placeholder text for button
  label: { type: String, default: 'Insert variable' },
});
const emit = defineEmits(['select']);

const store = useWorkflowStore();
const open = ref(false);

const triggerModel = computed(() => {
  const wf = store.activeWorkflow;
  if (!wf) return null;
  const trigStep = Array.isArray(wf.steps) ? wf.steps.find(s => (s.step_type || '').toUpperCase() === 'TRIGGER') : null;
  let ev = trigStep?.step_config?.trigger_event || wf.trigger_event || '';
  if (typeof ev !== 'string') return null;
  const [model] = ev.split('.');
  return model || null;
});

const schemaModels = computed(() => store.automationSchema?.models || []);
const triggerModelSchema = computed(() => schemaModels.value.find(m => String(m.name).toLowerCase() === String(triggerModel.value || '').toLowerCase()));

function listTriggerVars() {
  const result = [];
  const base = triggerModel.value ? `trigger.${triggerModel.value}` : 'trigger';
  const m = triggerModelSchema.value;
  if (!m) return result;
  // direct columns
  (m.columns || []).forEach(col => {
    result.push({ path: `${base}.${col}`, label: `${base}.${col}` });
  });
  // one-level relationships
  (m.relationships || []).forEach(rel => {
    const relBase = `${base}.${rel.name}`;
    (rel.columns || []).forEach(c => {
      result.push({ path: `${relBase}.${c}`, label: `${relBase}.${c}` });
    });
  });
  return result;
}

function flattenSteps(steps, out = []) {
  if (!Array.isArray(steps)) return out;
  for (const s of steps) {
    if (!s) continue;
    out.push(s);
    if (s.step_type === 'CONDITION') {
      flattenSteps(s.yes_steps, out);
      flattenSteps(s.no_steps, out);
    }
  }
  return out;
}

const stepVars = computed(() => {
  const wf = store.activeWorkflow;
  if (!wf) return [];
  const all = flattenSteps(wf.steps || []);
  const items = [];
  all.forEach(s => {
    const id = s.id;
    if (!id) return;
    const base = `step_${id}`;
    // We cannot know parsed keys statically; provide generic suggestions
    items.push({ path: `${base}.text`, label: `${base}.text` });
    items.push({ path: `${base}.count`, label: `${base}.count` });
    items.push({ path: `${base}.records`, label: `${base}.records` });
  });
  // Also add ai.last_output
  items.push({ path: 'ai.last_output', label: 'ai.last_output' });
  return items;
});

const filtered = computed(() => {
  const base = (props.baseScope || '').toLowerCase();
  const arr = [...listTriggerVars(), ...stepVars.value];
  if (!base) return arr;
  return arr.filter(x => x.path.toLowerCase().startsWith(base));
});

function select(path) {
  emit('select', `{{ ${path} }}`);
  open.value = false;
}
</script>

<template>
  <div class="relative inline-block text-left">
    <button type="button" class="inline-flex items-center gap-1 px-2 py-1 text-xs border rounded-md bg-white hover:bg-gray-50"
            @click="open = !open">
      { } {{ label }}
      <ChevronDown class="w-3 h-3" />
    </button>
    <div v-if="open" class="absolute right-0 z-30 mt-1 w-72 max-h-64 overflow-auto bg-white border rounded-md shadow">
      <div class="p-2 sticky top-0 bg-white border-b text-xs text-gray-500">Variables</div>
      <ul class="text-sm divide-y">
        <li v-for="item in filtered" :key="item.path" class="px-3 py-1 hover:bg-gray-50 cursor-pointer" @click="select(item.path)">
          {{ item.label }}
        </li>
      </ul>
      <div v-if="filtered.length === 0" class="p-3 text-xs text-gray-500">No variables available.</div>
    </div>
  </div>
</template>
