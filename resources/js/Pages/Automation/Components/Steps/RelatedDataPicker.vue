<script setup>
import { computed, ref, watch } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import OverlayMultiSelect from '@/Components/OverlayMultiSelect.vue';

const props = defineProps({
  modelValue: { type: Object, default: () => ({ base_model: null, roots: [], nested: {}, fields: {} }) },
  baseModelName: { type: String, default: null },
  show: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'close']);

const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

function makeWorking(seed) {
  const base = {
    base_model: seed?.base_model ?? props.baseModelName ?? null,
    roots: Array.isArray(seed?.roots) ? seed.roots : [],
    nested: (seed?.nested && typeof seed.nested === 'object') ? seed.nested : {},
    fields: (seed?.fields && typeof seed.fields === 'object') ? seed.fields : {},
  };
  try {
    return JSON.parse(JSON.stringify(base));
  } catch (e) {
    // Fallback to shallow copy if JSON cloning fails for some edge case
    return { ...base, roots: [...(base.roots || [])], nested: { ...(base.nested || {}) }, fields: { ...(base.fields || {}) } };
  }
}

const working = ref(makeWorking(props.modelValue || { base_model: props.baseModelName, roots: [], nested: {}, fields: {} }));

watch(() => props.show, (open) => {
  if (open) {
    working.value = makeWorking(props.modelValue || {});
  }
});

const baseModelSchema = computed(() => automationSchema.value.find(m => m.name === (working.value.base_model || props.baseModelName)) || null);

const rootRelationshipOptions = computed(() => (baseModelSchema.value?.relationships || []).map(r => ({ value: r.name, label: `${r.name} → ${r.model}` })));

function onRootsChange(newRoots) {
  const set = new Set(newRoots);
  // prune nested and fields for deselected roots
  const nextNested = { ...working.value.nested };
  const nextFields = { ...working.value.fields };
  Object.keys(nextNested).forEach(root => { if (!set.has(root)) delete nextNested[root]; });
  Object.keys(nextFields).forEach(path => { if (!set.has(path.split('.')[0])) delete nextFields[path]; });
  working.value.roots = [...set];
  working.value.nested = nextNested;
  working.value.fields = nextFields;
}

function modelSchemaByName(name) {
  return automationSchema.value.find(m => m.name === name) || null;
}

function relatedModelNameForRoot(rootName) {
  const rel = (baseModelSchema.value?.relationships || []).find(r => r.name === rootName);
  return rel ? rel.model : null;
}

function nestedModelName(rootName, childName) {
  const firstModel = relatedModelNameForRoot(rootName);
  const schema = modelSchemaByName(firstModel);
  const rel = schema?.relationships?.find(r => r.name === childName);
  return rel ? rel.model : null;
}

function fieldOptionsForModel(modelName) {
  const schema = modelSchemaByName(modelName);
  const cols = schema?.columns || [];
  return cols.map(c => ({ value: (typeof c === 'string' ? c : c.name), label: (typeof c === 'string' ? c : (c.label || c.name)) }));
}

function isAllSelected(path) {
  const arr = working.value.fields?.[path] || [];
  return Array.isArray(arr) && arr.includes('*');
}
function toggleSelectAll(path) {
  const next = { ...(working.value.fields || {}) };
  if (isAllSelected(path)) { delete next[path]; } else { next[path] = ['*']; }
  working.value.fields = next;
}

function onFieldsChange(path, arr) {
  const next = { ...(working.value.fields || {}) };
  if (!arr || arr.length === 0) { delete next[path]; }
  else { next[path] = arr.filter(v => v !== '*'); }
  working.value.fields = next;
}

function nestedOptionsForRoot(rootName) {
  const childModel = relatedModelNameForRoot(rootName);
  const m = modelSchemaByName(childModel);
  return (m?.relationships || []).map(r => ({ value: r.name, label: `${rootName}.${r.name} → ${r.model}` }));
}

function onNestedChange(rootName, arr) {
  const next = { ...(working.value.nested || {}) };
  next[rootName] = arr;
  working.value.nested = next;
}

function save() {
  const payload = {
    base_model: working.value.base_model || props.baseModelName || null,
    roots: Array.isArray(working.value.roots) ? working.value.roots : [],
    nested: working.value.nested || {},
    fields: working.value.fields || {},
  };
  emit('update:modelValue', payload);
  emit('close');
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/40" @click="$emit('close')"></div>
    <!-- Panel -->
    <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-white shadow-xl flex flex-col">
      <div class="p-4 border-b flex items-center justify-between">
        <h3 class="text-base font-semibold">Include Related Data</h3>
        <button class="text-gray-500 hover:text-gray-700" @click="$emit('close')">✕</button>
      </div>
      <div class="p-4 space-y-4 overflow-y-auto">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Base Model</label>
          <input type="text" :value="working.base_model || baseModelName || ''" disabled class="w-full border rounded px-2 py-1.5 bg-gray-50 text-sm" />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Top-level Relationships</label>
          <OverlayMultiSelect
            :options="rootRelationshipOptions"
            :model-value="working.roots"
            placeholder="Select relationships (e.g., campaign, contexts)"
            @update:modelValue="onRootsChange"
          />
          <p class="text-xs text-gray-500 mt-1">Choose one or more relationships to include. You can then pick fields and add nested relationships.</p>
        </div>

        <div v-for="root in (working.roots || [])" :key="root" class="p-3 border rounded-md bg-gray-50/60 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium">Fields in {{ relatedModelNameForRoot(root) }}</p>
            <label class="text-xs inline-flex items-center gap-1">
              <input type="checkbox" :checked="isAllSelected(root)" @change="toggleSelectAll(root)" class="rounded border-gray-300" />
              Select all
            </label>
          </div>
          <div>
            <OverlayMultiSelect
              :options="fieldOptionsForModel(relatedModelNameForRoot(root))"
              :model-value="(working.fields?.[root] || [])"
              placeholder="Select fields..."
              @update:modelValue="(arr) => onFieldsChange(root, arr)"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Also include...</label>
            <OverlayMultiSelect
              :options="nestedOptionsForRoot(root)"
              :model-value="(working.nested?.[root] || [])"
              placeholder="Select nested relationships..."
              @update:modelValue="(arr) => onNestedChange(root, arr)"
            />
          </div>

          <div v-for="child in (working.nested?.[root] || [])" :key="`${root}.${child}`" class="ml-2 p-2 border rounded bg-white">
            <div class="flex items-center justify-between">
              <p class="text-xs font-medium">Fields in {{ nestedModelName(root, child) || (root + '.' + child) }}</p>
              <label class="text-xs inline-flex items-center gap-1">
                <input type="checkbox" :checked="isAllSelected(root + '.' + child)" @change="toggleSelectAll(root + '.' + child)" class="rounded border-gray-300" />
                Select all
              </label>
            </div>
            <OverlayMultiSelect
              :options="fieldOptionsForModel((automationSchema.find(m => m.name === relatedModelNameForRoot(root))?.relationships || []).find(r => r.name === child)?.model)"
              :model-value="(working.fields?.[root + '.' + child] || [])"
              placeholder="Select fields..."
              @update:modelValue="(arr) => onFieldsChange(root + '.' + child, arr)"
            />
          </div>
        </div>
      </div>

      <div class="p-3 border-t flex justify-end gap-2">
        <button class="px-3 py-1.5 rounded-md bg-white ring-1 ring-gray-300 text-sm" @click="$emit('close')">Cancel</button>
        <button class="px-3 py-1.5 rounded-md bg-indigo-600 text-white text-sm" @click="save">Save</button>
      </div>
    </div>
  </div>
</template>
