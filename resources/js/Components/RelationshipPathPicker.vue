<script setup>
import { ref, computed, watch } from 'vue';
import { useWorkflowStore } from '@/Pages/Automation/Store/workflowStore';

const props = defineProps({
    mode: { type: String, default: 'id' }, // 'id' | 'type' | 'field'
    allStepsBefore: { type: Array, default: () => [] },
    value: { type: String, default: '' },
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
const chosenField = ref(''); // for mode="field"

const startSources = computed(() => {
  const sources = [];
  // Trigger model (supports TRIGGER and SCHEDULE_TRIGGER)
  const triggerStep = props.allStepsBefore.find(s => (s.step_type === 'TRIGGER' || s.step_type === 'SCHEDULE_TRIGGER') && s.step_config?.model);
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

function reset() {
  path.value = [];
  chosenMorphAlias.value = '';
  chosenField.value = '';
}

function addRelation(relName) {
  if (!relName) return;
  path.value.push(relName);
  chosenField.value = '';
}

// Determine meta of last chosen relation while walking forward
function traverseToTerminalModel() {
  if (!currentModel.value) return { model: null, lastRelMeta: null };
  let model = currentModel.value;
  let lastMeta = null;
  for (const relName of path.value) {
    const meta = (model.relationships || []).find(r => r.name === relName);
    if (!meta) return { model: null, lastRelMeta: null };
    lastMeta = meta;
    if (meta.type !== 'MorphTo' && meta.full_class) {
      const next = models.value.find(m => m.full_class === meta.full_class || m.name === meta.model);
      if (!next) return { model, lastRelMeta: meta };
      model = next;
    } else {
      // Stop at MorphTo; subsequent model depends on chosenMorphAlias
      break;
    }
  }
  return { model, lastRelMeta };
}

const lastRelMeta = computed(() => traverseToTerminalModel().lastRelMeta);

// Resolve terminal model schema, respecting MorphTo type selection
const terminalModelSchema = computed(() => {
  const { model, lastRelMeta } = traverseToTerminalModel();
  if (!model) return null;
  if (!lastRelMeta || lastRelMeta.type !== 'MorphTo') {
    return model;
  }
  if (!chosenMorphAlias.value) return null;
  const mm = morphMap.value.find(x => x.alias === chosenMorphAlias.value);
  if (!mm) return null;
  const baseName = (mm.class || '').split('\\').pop();
  return models.value.find(s => s.name === baseName) || null;
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
      // Stop on MorphTo; field selection will follow
      return [];
    }
  }
  return model.relationships || [];
});

const terminalFields = computed(() => {
  const tm = terminalModelSchema.value;
  if (!tm) return [];
  return (tm.columns || []).map(c => {
    const name = typeof c === 'string' ? c : c.name;
    const label = typeof c === 'string' ? c : (c.label || c.name);
    return { value: name, label };
  });
});

function buildToken() {
  const base = start.value?.baseToken || '';
  const relPath = path.value.length ? `.${path.value.join('.')}` : '';
  if (props.mode === 'id') {
    return `{{${base}${relPath}.id}}`;
  }
  if (props.mode === 'field') {
    if (!chosenField.value) return '';
    return `{{${base}${relPath}.${chosenField.value}}}`;
  }
  return '';
}

function onSelect() {
  if (props.mode === 'type') {
    if (chosenMorphAlias.value) emit('select', chosenMorphAlias.value);
    else emit('select', '');
  } else {
    emit('select', buildToken());
  }
  isOpen.value = false;
}

watch(start, () => reset());

// Parse incoming value
function parseToken(token) {
  // Special handling for morph type alias values (not wrapped in {{ }})
  if (props.mode === 'type') {
    if (!token || typeof token !== 'string') {
      chosenMorphAlias.value = '';
      return;
    }
    const morph = morphMap.value.find(m => m.alias === token);
    if (morph) { chosenMorphAlias.value = morph.alias; return; }
    chosenMorphAlias.value = '';
  }

  if (!token || typeof token !== 'string' || !token.startsWith('{{')) {
    start.value = null; path.value = []; chosenField.value = ''; chosenMorphAlias.value='';
    return;
  }

  const segments = token.replace(/[{}]/g, '').trim().split('.');
  if (segments.length < 2) {
    start.value = null; path.value = []; chosenField.value = ''; return;
  }

  const basePart = segments[0];
  const isTrigger = basePart.toLowerCase() === 'trigger';
  const baseSource = startSources.value.find(s => {
    const sBase = s.id === 'trigger' ? 'trigger' : s.modelName.toLowerCase();
    return sBase === basePart.toLowerCase();
  });
  if (!baseSource) { start.value = null; path.value = []; chosenField.value = ''; return; }
  start.value = baseSource;

  const afterBase = isTrigger ? segments.slice(2) : segments.slice(1);
  if (afterBase.length === 0) { path.value = []; chosenField.value = ''; return; }
  // Last is field, rest are relations
  path.value = afterBase.slice(0, -1);
  chosenField.value = afterBase[afterBase.length - 1] || '';
}

watch(() => props.value, (newValue) => { parseToken(newValue); }, { immediate: true });

</script>

<template>
  <div class="relative inline-flex items-center gap-2">
    <button type="button" @click="isOpen = !isOpen" class="px-2 py-1 text-xs rounded bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 shrink-0">Pick via relationships</button>
    <div v-if="isOpen" class="absolute z-50 mt-2 w-[28rem] bg-white border rounded shadow p-3">
      <div class="space-y-3">
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
                <button type="button" class="ml-1 text-gray-400 hover:text-red-600" @click="path.splice(idx); chosenField='';">×</button>
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

        <div v-if="props.mode === 'field' && lastRelMeta && lastRelMeta.type === 'MorphTo'">
          <label class="block text-xs text-gray-600 mb-1">Select type</label>
          <select v-model="chosenMorphAlias" class="w-full p-2 border rounded text-sm">
            <option value="">Choose a type...</option>
            <option v-for="m in morphMap" :key="m.alias" :value="m.alias">{{ m.label }}</option>
          </select>
        </div>

        <div v-if="props.mode === 'field'">
          <label class="block text-xs text-gray-600 mb-1">Select field</label>
          <select v-model="chosenField" class="w-full p-2 border rounded text-sm" :disabled="!terminalModelSchema">
            <option value="" disabled>Select a field...</option>
            <option v-for="f in terminalFields" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
        </div>

        <div class="flex items-center justify-between mt-3">
          <div v-if="props.mode !== 'type'" class="text-[11px] text-gray-500 truncate">Token preview: <span class="font-mono">{{ buildToken() }}</span></div>
          <div class="flex items-center gap-2 ml-auto">
            <button @click="isOpen=false" class="px-2 py-1 text-xs rounded border">Cancel</button>
            <button @click="onSelect" class="px-2 py-1 text-xs rounded bg-indigo-600 text-white" :disabled="props.mode==='field' && !chosenField">Use</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
