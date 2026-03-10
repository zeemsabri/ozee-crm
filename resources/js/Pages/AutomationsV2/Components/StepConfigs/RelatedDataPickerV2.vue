<script setup>
/**
 * RelatedDataPickerV2.vue
 * Tree-view based data picker sidebar.
 * Manages both 'aiInputs' (base fields) and 'relationships' (nested data).
 */
import { computed, ref, watch } from 'vue';
import { useAutomationsV2Store } from '../../Store/storeV2';
import {
    XMarkIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    LinkIcon,
    TableCellsIcon,
    CheckIcon,
    BoltIcon,
} from '@heroicons/vue/24/solid';

const props = defineProps({
    modelValue: { type: Object, default: () => ({ base_model: null, roots: [], nested: {}, fields: {} }) },
    aiInputs: { type: Array, default: () => [] },
    baseModelName: { type: String, default: null },
    baseSource: { type: String, default: 'trigger' }, // 'trigger' or 'loop'
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'update:aiInputs', 'close']);

const store = useAutomationsV2Store();
const automationSchema = computed(() => store.automationSchema || []);

// --- Working state for relationships ---
function makeWorking(seed) {
    const base = {
        base_model: seed?.base_model ?? props.baseModelName ?? null,
        roots: Array.isArray(seed?.roots) ? seed.roots : [],
        nested: (seed?.nested && typeof seed.nested === 'object') ? seed.nested : {},
        fields: (seed?.fields && typeof seed.fields === 'object') ? seed.fields : {},
    };
    try { return JSON.parse(JSON.stringify(base)); }
    catch (e) { return { ...base }; }
}

const working = ref(makeWorking(props.modelValue || {}));
const workingInputs = ref([...props.aiInputs]);

// Track expansion
const expanded = ref({ _base: true }); // Base fields open by default

watch(() => props.show, (open) => {
    if (open) {
        working.value = makeWorking(props.modelValue || {});
        workingInputs.value = [...props.aiInputs];
        
        const exp = { _base: true };
        (working.value.roots || []).forEach(r => { exp[r] = true; });
        expanded.value = exp;
    }
});

// --- Schema helpers ---
function schemaByName(name) {
    return automationSchema.value.find(m => m.name === name) || null;
}
const baseModelSchema = computed(() => schemaByName(working.value.base_model || props.baseModelName));

function relModelForRoot(rootName) {
    const rel = (baseModelSchema.value?.relationships || []).find(r => r.name === rootName);
    return rel ? rel.model : null;
}
function relModelForNested(rootName, childName) {
    const firstModel = relModelForRoot(rootName);
    const schema = schemaByName(firstModel);
    const rel = (schema?.relationships || []).find(r => r.name === childName);
    return rel ? rel.model : null;
}
function columnsForModel(modelName) {
    const schema = schemaByName(modelName);
    return (schema?.columns || []).map(c => ({
        name: typeof c === 'string' ? c : c.name,
        label: typeof c === 'string' ? c : (c.label || c.name),
    }));
}
function nestedRelsForRoot(rootName) {
    const childModel = relModelForRoot(rootName);
    const schema = schemaByName(childModel);
    return (schema?.relationships || []);
}

// --- Base Field toggle (aiInputs) ---
function isBaseFieldSelected(colName) {
    const val = `${props.baseSource}:${colName}`;
    // Support legacy format where 'trigger:' was optional in some contexts
    return workingInputs.value.includes(val) || (props.baseSource === 'trigger' && workingInputs.value.includes(colName));
}
function toggleBaseField(colName) {
    const val = `${props.baseSource}:${colName}`;
    const idx = workingInputs.value.indexOf(val);
    if (idx >= 0) {
        workingInputs.value.splice(idx, 1);
    } else {
        // Also check/remove legacy version if exists
        const legacyIdx = workingInputs.value.indexOf(colName);
        if (legacyIdx >= 0) workingInputs.value.splice(legacyIdx, 1);
        
        workingInputs.value.push(val);
    }
}

// --- Root toggle ---
function toggleRoot(rootName) {
    const roots = [...(working.value.roots || [])];
    const idx = roots.indexOf(rootName);
    if (idx >= 0) {
        roots.splice(idx, 1);
        const nested = { ...working.value.nested };
        const fields = { ...working.value.fields };
        delete nested[rootName];
        Object.keys(fields).forEach(p => { if (p === rootName || p.startsWith(rootName + '.')) delete fields[p]; });
        working.value = { ...working.value, roots, nested, fields };
        delete expanded.value[rootName];
    } else {
        roots.push(rootName);
        working.value = { ...working.value, roots };
        expanded.value[rootName] = true;
    }
}
function isRootSelected(rootName) {
    return (working.value.roots || []).includes(rootName);
}

// --- Nested toggle ---
function toggleNested(rootName, childName) {
    const nested = { ...working.value.nested };
    const arr = Array.isArray(nested[rootName]) ? [...nested[rootName]] : [];
    const idx = arr.indexOf(childName);
    if (idx >= 0) {
        arr.splice(idx, 1);
        const full = `${rootName}.${childName}`;
        const fields = { ...working.value.fields };
        Object.keys(fields).forEach(p => { if (p === full || p.startsWith(full + '.')) delete fields[p]; });
        nested[rootName] = arr;
        working.value = { ...working.value, nested, fields };
    } else {
        arr.push(childName);
        nested[rootName] = arr;
        working.value = { ...working.value, nested };
    }
}
function isNestedSelected(rootName, childName) {
    return (working.value.nested?.[rootName] || []).includes(childName);
}

// --- Field toggle (Relationships) ---
function toggleField(path, fieldName) {
    const fields = { ...working.value.fields };
    const current = Array.isArray(fields[path]) ? fields[path].filter(f => f !== '*') : [];
    const idx = current.indexOf(fieldName);
    if (idx >= 0) { current.splice(idx, 1); }
    else { current.push(fieldName); }
    if (current.length === 0) delete fields[path];
    else fields[path] = current;
    working.value = { ...working.value, fields };
}
function isFieldSelected(path, fieldName) {
    const f = working.value.fields?.[path] || [];
    return f.includes('*') || f.includes(fieldName);
}

function isAllSelected(path) {
    return (working.value.fields?.[path] || []).includes('*');
}
function toggleSelectAll(path) {
    const fields = { ...working.value.fields };
    if (isAllSelected(path)) { delete fields[path]; }
    else { fields[path] = ['*']; }
    working.value = { ...working.value, fields };
}

// --- Save ---
function save() {
    emit('update:modelValue', {
        base_model: working.value.base_model || props.baseModelName || null,
        roots: working.value.roots || [],
        nested: working.value.nested || {},
        fields: working.value.fields || {},
    });
    emit('update:aiInputs', workingInputs.value);
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[300]">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="$emit('close')"></div>

            <!-- Sidebar Panel -->
            <div class="absolute left-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl flex flex-col animate-in slide-in-from-left duration-300">

                <!-- Header -->
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <LinkIcon class="w-4 h-4 text-indigo-500" />
                            Step Data Payload
                        </h3>
                        <p class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-widest font-bold">
                            Source: <span class="text-indigo-600">{{ props.baseSource }} ({{ props.baseModelName }})</span>
                        </p>
                    </div>
                    <button class="p-1.5 hover:bg-slate-200 rounded-lg transition-colors text-slate-400" @click="$emit('close')">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Tree Body -->
                <div class="flex-1 overflow-y-auto custom-scrollbar">

                    <!-- 1. BASE MODEL SECTION -->
                    <div class="border-b border-slate-100">
                        <div 
                            class="flex items-center gap-3 px-5 py-4 cursor-pointer hover:bg-slate-50 transition-colors group"
                            @click="expanded._base = !expanded._base"
                        >
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500 flex-shrink-0">
                                <BoltIcon class="w-4 h-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-slate-800 leading-none">Primary Content</p>
                                <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-tight font-medium">Fields from {{ props.baseModelName }}</p>
                            </div>
                            <ChevronDownIcon 
                                class="w-4 h-4 text-slate-300 transition-transform duration-200"
                                :class="{ '-rotate-90': !expanded._base }"
                            />
                        </div>

                        <div v-if="expanded._base" class="px-6 pb-6 pt-1 bg-slate-50/30">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Select fields to send to AI</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="col in columnsForModel(props.baseModelName)"
                                    :key="col.name"
                                    type="button"
                                    @click="toggleBaseField(col.name)"
                                    class="px-2.5 py-1.5 rounded-xl text-[11px] font-bold border transition-all"
                                    :class="isBaseFieldSelected(col.name)
                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-200'
                                        : 'bg-white text-slate-500 border-slate-200 hover:border-indigo-300 hover:text-indigo-600'"
                                >
                                    {{ col.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 2. RELATIONSHIPS SECTION HEADER -->
                    <div class="px-5 pt-6 pb-2">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Relationships & Nested Data</label>
                    </div>

                    <!-- Root relationships list -->
                    <div v-for="rel in (baseModelSchema?.relationships || [])" :key="rel.name" class="border-b border-slate-100 last:border-0">

                        <!-- Root row -->
                        <div
                            class="flex items-center gap-3 px-5 py-3.5 cursor-pointer hover:bg-slate-50 transition-colors group"
                            @click="toggleRoot(rel.name)"
                        >
                            <div
                                class="w-5 h-5 rounded-md border-2 flex-shrink-0 flex items-center justify-center transition-all"
                                :class="isRootSelected(rel.name)
                                    ? 'bg-indigo-600 border-indigo-600 shadow-sm shadow-indigo-100'
                                    : 'border-slate-300 group-hover:border-indigo-400'"
                            >
                                <CheckIcon v-if="isRootSelected(rel.name)" class="w-3 h-3 text-white" />
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-700 leading-none">{{ rel.name }}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5 font-medium">Model: {{ rel.model }}</p>
                            </div>

                            <button
                                v-if="isRootSelected(rel.name)"
                                class="p-1 rounded hover:bg-white transition-colors"
                                @click.stop="expanded[rel.name] = !expanded[rel.name]"
                            >
                                <ChevronDownIcon
                                    class="w-4 h-4 text-slate-400 transition-transform duration-200"
                                    :class="{ '-rotate-90': !expanded[rel.name] }"
                                />
                            </button>
                            <ChevronRightIcon v-else class="w-4 h-4 text-slate-200 flex-shrink-0" />
                        </div>

                        <!-- Expanded content -->
                        <div v-if="isRootSelected(rel.name) && expanded[rel.name]" class="bg-indigo-50/20 border-y border-slate-100">
                            <div class="px-6 py-4">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">Fields from {{ rel.model }}</p>
                                    <button
                                        type="button"
                                        @click="toggleSelectAll(rel.name)"
                                        class="text-[10px] font-bold uppercase tracking-wider transition-colors"
                                        :class="isAllSelected(rel.name) ? 'text-indigo-600' : 'text-slate-400 hover:text-indigo-500'"
                                    >
                                        {{ isAllSelected(rel.name) ? 'Selected All' : 'Select All' }}
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="col in columnsForModel(relModelForRoot(rel.name))"
                                        :key="col.name"
                                        type="button"
                                        @click="isAllSelected(rel.name) ? null : toggleField(rel.name, col.name)"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-all"
                                        :class="isFieldSelected(rel.name, col.name)
                                            ? 'bg-indigo-500 text-white border-indigo-500'
                                            : 'bg-white text-slate-500 border-slate-200 hover:border-indigo-300 hover:text-indigo-600'"
                                    >
                                        {{ col.label }}
                                    </button>
                                </div>
                            </div>

                            <!-- Nested Relationships -->
                            <div v-if="nestedRelsForRoot(rel.name).length" class="border-t border-slate-100 px-6 py-4 space-y-3">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nested Connections</p>
                                <div
                                    v-for="childRel in nestedRelsForRoot(rel.name)"
                                    :key="childRel.name"
                                    class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm"
                                >
                                    <div
                                        class="flex items-center gap-2.5 px-4 py-3 cursor-pointer hover:bg-slate-50 transition-colors group"
                                        @click="toggleNested(rel.name, childRel.name)"
                                    >
                                        <div
                                            class="w-4 h-4 rounded-md border-2 flex items-center justify-center flex-shrink-0 transition-all"
                                            :class="isNestedSelected(rel.name, childRel.name)
                                                ? 'bg-indigo-500 border-indigo-500 shadow-sm'
                                                : 'border-slate-300 group-hover:border-indigo-400'"
                                        >
                                            <CheckIcon v-if="isNestedSelected(rel.name, childRel.name)" class="w-2.5 h-2.5 text-white" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold text-slate-700 leading-none">{{ rel.name }}.{{ childRel.name }}</p>
                                            <p class="text-[10px] text-slate-400 mt-1 font-medium">Model: {{ childRel.model }}</p>
                                        </div>
                                    </div>

                                    <div v-if="isNestedSelected(rel.name, childRel.name)" class="px-4 pb-4 pt-1 border-t border-slate-50 bg-slate-50/30">
                                        <div class="flex items-center justify-between mb-3">
                                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Fields</p>
                                            <button 
                                                @click="toggleSelectAll(`${rel.name}.${childRel.name}`)"
                                                class="text-[9px] font-black uppercase text-indigo-400 hover:text-indigo-600"
                                            >
                                                {{ isAllSelected(`${rel.name}.${childRel.name}`) ? 'ALL' : 'SELECT ALL' }}
                                            </button>
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            <button
                                                v-for="col in columnsForModel(relModelForNested(rel.name, childRel.name))"
                                                :key="col.name"
                                                type="button"
                                                @click="toggleField(`${rel.name}.${childRel.name}`, col.name)"
                                                class="px-2 py-1 rounded-md text-[11px] font-semibold border transition-all"
                                                :class="isFieldSelected(`${rel.name}.${childRel.name}`, col.name)
                                                    ? 'bg-indigo-500 text-white border-indigo-500'
                                                    : 'bg-white text-slate-500 border-slate-200 hover:border-indigo-300 hover:text-indigo-600'"
                                            >
                                                {{ col.label }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between gap-3">
                    <div class="flex flex-col">
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Selection Status</p>
                        <p class="text-xs font-bold text-slate-600">
                            {{ workingInputs.length }} fields · {{ (working.roots || []).length }} relationships
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button class="v2-btn-secondary !py-2 !px-5 !text-xs" @click="$emit('close')">Cancel</button>
                        <button class="v2-btn-primary !py-2 !px-5 !text-xs !bg-indigo-600 hover:!bg-indigo-700" @click="save">Apply Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
