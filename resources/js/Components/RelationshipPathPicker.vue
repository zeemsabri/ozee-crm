<script setup>
import { ref, computed, watch } from 'vue';
import { useWorkflowStore } from '@/Pages/Automation/Store/workflowStore';

const props = defineProps({
  mode: { type: String, default: 'id' }, // 'id' | 'type'
  allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['select']);

const store = useWorkflowStore();
const models = computed(() => store.automationSchema || []);
const morphMap = computed(() => store.morphMap || []);

// --- UI state ---
const isOpen = ref(false);
const start = ref(null); // { id, label, baseToken, modelName }
const path = ref([]); // array of relation names
const chosenMorphAlias = ref('');

const startSources = computed(() => {
  const sources = [];
  // Trigger model
  const triggerStep = props.allStepsBefore.find(s => s.step_type === 'TRIGGER' && s.step_config?.model);
  if (triggerStep?.step_config?.model) {
    const modelName = triggerStep.step_config.model;
    sources.push({
      id: 'trigger',
      label: `Trigger: ${modelName}`,
      modelName,
      baseToken: `trigger.${modelName.toLowerCase()}`,
    });
  }
  // Prior Create Record steps (singletons)
  props.allStepsBefore.forEach((s, idx) => {
    if (s.step_type === 'ACTION' && s.step_config?.action_type === 'CREATE_RECORD' && s.step_config?.target_model) {
      const modelName = s.step_config.target_model;
      sources.push({
        id: `step_${s.id}`,
        label: `Step ${idx + 1}: Created ${modelName}`,
        modelName,
        baseToken: `${modelName.toLowerCase()}`,
      });
    }
  });
  return sources;
});

const currentModel = computed(() => {
  if (!start.value?.modelName) return null;
  return models.value.find(m => m.name === start.value.modelName) || null;
});

const relationships = computed(() => currentModel.value?.relationships || []);

function reset() {
  path.value = [];
  chosenMorphAlias.value = '';
}

function addRelation(relName) {
  if (!relName) return;
  path.value.push(relName);
}

function currentToken() {
  const base = start.value?.baseToken || '';
  const relPath = path.value.length ? `.${path.value.join('.')}` : '';
  const field = props.mode === 'id' ? '.id' : '';
  return `{{${base}${relPath}${field}}}`;
}

function onSelect() {
  if (props.mode === 'type') {
    // For morph type selection, emit alias if chosen, otherwise emit empty to keep UI predictable
    if (chosenMorphAlias.value) emit('select', chosenMorphAlias.value);
    else emit('select', '');
  } else {
    emit('select', currentToken());
  }
  isOpen.value = false;
}

// For simplicity, we detect if the last chosen relation is MorphTo by checking relationships meta
const lastRelMeta = computed(() => {
  if (!currentModel.value || path.value.length === 0) return null;
  let model = currentModel.value;
  let lastMeta = null;
  for (const relName of path.value) {
    const meta = (model.relationships || []).find(r => r.name === relName);
    if (!meta) return null;
    lastMeta = meta;
    // advance model if determinable (skip MorphTo because it has no full_class)
    if (meta.type !== 'MorphTo' && meta.full_class) {
      const next = models.value.find(m => m.full_class === meta.full_class || m.name === meta.model);
      if (next) model = next;
    }
  }
  return lastMeta;
});

const availableRelations = computed(() => {
  // Traverse to the model after the currently selected path
  if (!currentModel.value) return [];
  let model = currentModel.value;
  for (const relName of path.value) {
    const meta = (model.relationships || []).find(r => r.name === relName);
    if (!meta) return [];
    if (meta.type !== 'MorphTo' && meta.full_class) {
      const next = models.value.find(m => m.full_class === meta.full_class || m.name === meta.model);
      if (next) model = next; else return [];
    } else {
      // MorphTo: we can still allow continuing, but structure unknown; stop here for v1
      return [];
    }
  }
  return model.relationships || [];
});

watch(start, () => reset());

</script>

<template>
  <div class="relative inline-block">
    <button type="button" @click="isOpen = !isOpen" class="px-2 py-1 text-xs rounded bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100">Pick via relationships</button>
    <div v-if="isOpen" class="absolute z-50 mt-2 w-96 bg-white border rounded shadow p-3">
      <div class="space-y-2">
        <div>
          <label class="block text-xs text-gray-600 mb-1">Start from</label>
          <div class="flex items-center gap-2">
            <select v-model="start" class="w-full p-2 border rounded text-sm" :class="{'text-gray-400': !start}">
              <option :value="null" disabled>Select a source</option>
              <option v-for="src in startSources" :key="src.id" :value="src">{{ src.label }}</option>
            </select>
            <button v-if="start" type="button" @click="start = null; reset();" class="text-[11px] px-2 py-1 rounded border bg-white hover:bg-gray-50">Clear</button>
          </div>
        </div>

        <div v-if="start && currentModel">
          <label class="block text-xs text-gray-600 mb-1">Traverse relationships</label>
          <div class="flex flex-wrap items-center gap-2 mb-2">
            <template v-for="(seg, idx) in path" :key="idx">
              <span class="inline-flex items-center text-xs bg-gray-100 px-2 py-1 rounded border">
                {{ seg }}
                <button type="button" class="ml-1 text-gray-400 hover:text-red-600" @click="path.splice(idx)">×</button>
              </span>
            </template>
            <button v-if="path.length" type="button" @click="reset()" class="ml-auto text-[11px] px-2 py-1 rounded border bg-white hover:bg-gray-50">Clear path</button>
          </div>
          <div class="flex items-center gap-2">
            <select @change="addRelation($event.target.value); $event.target.value='';" class="w-full p-2 border rounded text-sm">
              <option value="">Add relation...</option>
              <option v-for="rel in availableRelations" :key="rel.name" :value="rel.name">{{ rel.name }} ({{ rel.type }})</option>
            </select>
          </div>
        </div>

        <div v-if="props.mode === 'type'">
          <label class="block text-xs text-gray-600 mb-1">Select type</label>
          <select v-model="chosenMorphAlias" class="w-full p-2 border rounded text-sm">
            <option value="">Choose a type...</option>
            <option v-for="m in morphMap" :key="m.alias" :value="m.alias">{{ m.label }}</option>
          </select>
        </div>

        <div class="flex items-center justify-between mt-3">
          <div v-if="props.mode === 'id'" class="text-[11px] text-gray-500 truncate">Token preview: <span class="font-mono">{{ currentToken() }}</span></div>
          <div class="flex items-center gap-2 ml-auto">
            <button @click="isOpen=false" class="px-2 py-1 text-xs rounded border">Cancel</button>
            <button @click="onSelect" class="px-2 py-1 text-xs rounded bg-indigo-600 text-white">Use</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
