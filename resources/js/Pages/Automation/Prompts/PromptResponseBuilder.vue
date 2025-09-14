<script setup>
import { computed, watch, ref } from 'vue';
import { PlusIcon, TrashIcon } from 'lucide-vue-next';

const props = defineProps({
  responseVariables: { type: Array, default: () => [] },
  responseJsonTemplate: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:responseVariables', 'update:responseJsonTemplate']);

// Track where the last change originated to avoid feedback loops
const lastChangeSource = ref(null); // 'json' | 'structure' | null

const responseJsonText = computed({
  get: () => {
    try {
      const obj = props.responseJsonTemplate || {};
      return Object.keys(obj).length ? JSON.stringify(obj, null, 2) : '';
    } catch (e) {
      return '';
    }
  },
  set: (val) => {
    try {
      const parsed = JSON.parse(val);
      if (parsed && typeof parsed === 'object') {
        lastChangeSource.value = 'json';
        emit('update:responseJsonTemplate', parsed);
        emit('update:responseVariables', inferResponseStructureFromJson(parsed));
      }
    } catch (e) {
      // ignore invalid JSON while typing
    }
  }
});

function inferResponseStructureFromJson(obj) {
  const fields = [];
  const objectToFields = (o) => Object.keys(o || {}).map(k => toField(k, o[k]));
  const toField = (name, value) => {
    const id = Date.now() + Math.random();
    if (Array.isArray(value)) {
      if (value.length > 0) {
        const first = value[0];
        if (first && typeof first === 'object' && !Array.isArray(first)) {
          return { id, name, type: 'Array of Objects', schema: objectToFields(first) };
        } else {
          const primitiveType = typeof first;
          const mappedType = primitiveType === 'number' ? 'Number' : (primitiveType === 'boolean' ? 'True/False' : 'Text');
          return { id, name, type: 'Array of Objects', schema: [{ id: id + 1, name: 'text', type: mappedType }] };
        }
      }
      return { id, name, type: 'Array of Objects', schema: [] };
    } else if (value !== null && typeof value === 'object') {
      return { id, name, type: 'Object', schema: objectToFields(value) };
    } else if (typeof value === 'number') {
      return { id, name, type: 'Number' };
    } else if (typeof value === 'boolean') {
      return { id, name, type: 'True/False' };
    }
    return { id, name, type: 'Text' };
  };
  return objectToFields(obj);
}

function buildJsonFromStructure(fields = []) {
  const obj = {};
  (fields || []).forEach(f => {
    if (!f || !f.name) return;
    const type = f.type || 'Text';
    if (type === 'Object') {
      obj[f.name] = buildJsonFromStructure(f.schema || []);
    } else if (type === 'Array of Objects') {
      obj[f.name] = [buildJsonFromStructure(f.schema || [])];
    } else if (type === 'Number') {
      obj[f.name] = 0;
    } else if (type === 'True/False') {
      obj[f.name] = false;
    } else {
      obj[f.name] = '';
    }
  });
  return obj;
}

function deepEqual(a, b) {
  try {
    return JSON.stringify(a) === JSON.stringify(b);
  } catch (e) {
    return false;
  }
}

// Keep Expected Response JSON synced when user edits the structure
watch(() => props.responseVariables, (newVars) => {
  if (lastChangeSource.value === 'structure') {
    const generated = buildJsonFromStructure(newVars || []);
    if (!deepEqual(generated, props.responseJsonTemplate || {})) {
      emit('update:responseJsonTemplate', generated);
    }
  }
  lastChangeSource.value = null;
}, { deep: true });

function handleAddField(parent = null) {
  const newField = { id: Date.now(), name: '', type: 'Text' };
  lastChangeSource.value = 'structure';
  if (parent) {
    parent.schema = Array.isArray(parent.schema) ? [...parent.schema, newField] : [newField];
    emit('update:responseVariables', [...props.responseVariables]);
  } else {
    emit('update:responseVariables', [...(props.responseVariables || []), newField]);
  }
}
function handleDeleteField(fieldId, parent = null) {
  lastChangeSource.value = 'structure';
  if (parent) {
    parent.schema = (parent.schema || []).filter(f => f.id !== fieldId);
    emit('update:responseVariables', [...props.responseVariables]);
  } else {
    const filtered = (props.responseVariables || []).filter(f => f.id !== fieldId);
    emit('update:responseVariables', filtered);
  }
}
function handleUpdateField(fieldId, key, value, parent = null) {
  lastChangeSource.value = 'structure';
  let structure = parent ? (parent.schema || []) : (props.responseVariables || []);
  const newStructure = structure.map(field => {
    if (field.id !== fieldId) return field;
    const updated = { ...field, [key]: value };
    if (key === 'type') {
      if ((value === 'Array of Objects' || value === 'Object') && !updated.schema) updated.schema = [];
      if (value !== 'Array of Objects' && value !== 'Object') delete updated.schema;
    }
    return updated;
  });
  if (parent) {
    parent.schema = newStructure;
    emit('update:responseVariables', [...props.responseVariables]);
  } else {
    emit('update:responseVariables', newStructure);
  }
}
</script>

<template>
  <div class="space-y-3">
    <details class="border rounded-md bg-white" :open="false">
      <summary class="px-3 py-2 cursor-pointer text-sm font-medium text-gray-700 flex items-center justify-between">
        Expected Response JSON (optional)
        <span class="text-xs text-gray-500">Toggle</span>
      </summary>
      <div class="p-3 border-t">
        <textarea rows="10" class="w-full p-2 border border-gray-300 rounded-md font-mono text-sm" placeholder="Paste a sample JSON response here to auto-generate structure" v-model="responseJsonText"></textarea>
      </div>
    </details>
    <div class="mt-2">
      <h4 class="text-sm font-medium text-gray-700">Response Structure</h4>
      <div v-for="field in (responseVariables || [])" :key="field.id" class="p-2 border rounded-md bg-gray-50/50 mt-2">
        <div class="flex items-center space-x-2">
          <input type="text" placeholder="Field Name" :value="field.name" @input="handleUpdateField(field.id, 'name', $event.target.value)" class="p-1.5 border border-gray-300 rounded-md w-full text-sm"/>
          <select :value="field.type" @change="handleUpdateField(field.id, 'type', $event.target.value)" class="p-1.5 border border-gray-300 rounded-md bg-white text-sm">
            <option>Text</option>
            <option>Number</option>
            <option>True/False</option>
            <option>Object</option>
            <option>Array of Objects</option>
          </select>
          <button type="button" @click="handleDeleteField(field.id)" class="text-gray-400 hover:text-red-500 p-1"><TrashIcon class="h-4 w-4" /></button>
        </div>
        <div v-if="field.type === 'Array of Objects' || field.type === 'Object'" class="ml-4 mt-2 pt-2 border-l-2 pl-4 space-y-2">
          <div v-for="sub in (field.schema || [])" :key="sub.id" class="flex items-center space-x-2">
            <input type="text" placeholder="Sub-field Name" :value="sub.name" @input="handleUpdateField(sub.id, 'name', $event.target.value, field)" class="p-1.5 border border-gray-300 rounded-md w-full text-sm"/>
            <select :value="sub.type" @change="handleUpdateField(sub.id, 'type', $event.target.value, field)" class="p-1.5 border border-gray-300 rounded-md bg-white text-sm">
              <option>Text</option><option>Number</option><option>True/False</option><option>Object</option><option>Array of Objects</option>
            </select>
            <button type="button" @click="handleDeleteField(sub.id, field)" class="text-gray-400 hover:text-red-500 p-1"><TrashIcon class="h-4 w-4" /></button>
          </div>
          <button type="button" @click="handleAddField(field)" class="text-xs flex items-center gap-1 px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200"><PlusIcon class="h-3 w-3" /> Add Sub-field</button>
        </div>
      </div>
      <button type="button" @click="handleAddField()" class="mt-2 text-xs flex items-center gap-1 px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200"><PlusIcon class="h-3 w-3" /> Add Field</button>
    </div>
  </div>
</template>
