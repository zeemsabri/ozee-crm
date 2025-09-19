<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { Plus, Trash2 } from 'lucide-vue-next';
import VariablePicker from '../VariablePicker.vue';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);
const schema = computed(() => store.automationSchema);
const models = computed(() => schema.value?.models || []);

const selectedModel = ref(null);
const conditions = ref([]); // { field: '', op: '=', value: '' }
const limit = ref(50);
const order = ref([]); // [{ field: 'created_at', dir: 'desc' }]
const outputKey = ref('');

const columnsForModel = computed(() => {
  if (!selectedModel.value) return [];
  const m = models.value.find(m => m.name === selectedModel.value);
  if (!m) return [];
  const cols = (m.columns || []).map(c => ({ value: c, label: c }));
  const relCols = (m.relationships || []).flatMap(rel => (rel.columns || []).map(c => ({ value: `${rel.name}.${c}`, label: `${rel.name}.${c}` })));
  return [...cols, ...relCols];
});

onMounted(() => {
  if (!Object.keys(store.automationSchema).length) {
    store.fetchAutomationSchema();
  }
});

watch(step, (s) => {
  if (!s) return;
  const cfg = s.step_config || {};
  selectedModel.value = cfg.model || cfg.target_model || null;
  conditions.value = Array.isArray(cfg.conditions) ? cfg.conditions.map(c => ({ field: c.field || '', op: c.op || c.operator || '=', value: c.value ?? '' })) : [];
  limit.value = cfg.limit ?? 50;
  order.value = Array.isArray(cfg.order) ? cfg.order.map(o => ({ field: o.field || '', dir: (o.dir || 'asc').toLowerCase() })) : [];
  outputKey.value = cfg.output_key || '';
}, { immediate: true });

watch([selectedModel, conditions, limit, order, outputKey], ([m, conds, lim, ord, outKey]) => {
  if (!step.value) return;
  if (!step.value.step_config || typeof step.value.step_config !== 'object' || Array.isArray(step.value.step_config)) {
    step.value.step_config = {};
  }
  step.value.step_config.model = m;
  step.value.step_config.conditions = conds.map(c => ({ field: c.field, op: c.op, value: c.value }));
  step.value.step_config.limit = lim;
  step.value.step_config.order = ord.map(o => ({ field: o.field, dir: o.dir }));
  step.value.step_config.output_key = outKey;
}, { deep: true });

function addCondition() {
  conditions.value.push({ field: '', op: '=', value: '' });
}
function removeCondition(i) {
  conditions.value.splice(i, 1);
}
function addOrder() {
  order.value.push({ field: '', dir: 'asc' });
}
function removeOrder(i) {
  order.value.splice(i, 1);
}
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-xs font-medium text-gray-700">Model</label>
      <SelectDropdown v-model="selectedModel" :options="models" valueKey="name" labelKey="name" placeholder="Select a model..." class="w-full mt-1" />
    </div>

    <div>
      <div class="flex items-center justify-between">
        <label class="text-xs font-medium text-gray-700">Conditions</label>
        <button @click="addCondition" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200">
          <Plus class="w-3 h-3" /> Add Condition
        </button>
      </div>
      <div v-if="conditions.length" class="mt-2 space-y-2">
        <div v-for="(c, i) in conditions" :key="i" class="p-2 border rounded-md bg-gray-50/50 space-y-2">
          <div class="flex items-center gap-2">
            <SelectDropdown v-model="c.field" :options="columnsForModel" placeholder="Field..." class="w-1/3" :disabled="!selectedModel" />
            <SelectDropdown v-model="c.op" :options="[
                { value: '=', label: '=' },
                { value: '!=', label: '!=' },
                { value: '>', label: '>' },
                { value: '>=', label: '>=' },
                { value: '<', label: '<' },
                { value: '<=', label: '<=' },
                { value: 'in', label: 'in' },
                { value: 'not in', label: 'not in' },
                { value: 'null', label: 'is null' },
                { value: 'not null', label: 'is not null' }
              ]" class="w-1/6" />
            <div class="w-1/2 flex items-center gap-2">
              <input v-model="c.value" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="Value or {{ variable }}" />
              <VariablePicker @select="val => c.value = (c.value || '') + (c.value ? ' ' : '') + val" />
            </div>
            <button @click="removeCondition(i)" class="text-red-500 hover:text-red-700 p-1" title="Remove">
              <Trash2 class="w-3 h-3" />
            </button>
          </div>
        </div>
      </div>
      <div v-else class="text-center text-xs text-gray-500 py-4 border-2 border-dashed rounded-lg mt-2">No conditions defined.</div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-gray-700">Limit</label>
        <input v-model.number="limit" type="number" min="1" class="mt-1 w-full border rounded px-2 py-1 text-sm" />
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-700">Output key (optional)</label>
        <input v-model="outputKey" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g., open_tasks" />
      </div>
    </div>

    <div>
      <div class="flex items-center justify-between">
        <label class="text-xs font-medium text-gray-700">Order (optional)</label>
        <button @click="addOrder" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200">
          <Plus class="w-3 h-3" /> Add Order
        </button>
      </div>
      <div v-if="order.length" class="mt-2 space-y-2">
        <div v-for="(o, i) in order" :key="i" class="p-2 border rounded-md bg-gray-50/50">
          <div class="flex items-center gap-2">
            <SelectDropdown v-model="o.field" :options="columnsForModel" placeholder="Field..." class="w-2/3" :disabled="!selectedModel" />
            <SelectDropdown v-model="o.dir" :options="[{ value: 'asc', label: 'ASC' }, { value: 'desc', label: 'DESC' }]" class="w-1/3" />
            <button @click="removeOrder(i)" class="text-red-500 hover:text-red-700 p-1" title="Remove">
              <Trash2 class="w-3 h-3" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
