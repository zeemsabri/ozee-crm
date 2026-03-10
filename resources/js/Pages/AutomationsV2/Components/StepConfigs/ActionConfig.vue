<script setup>
/**
 * ActionConfig.vue
 * Configuration panel for ACTION step type.
 * Handles: SEND_EMAIL, PROCESS_EMAIL, CREATE_RECORD, UPDATE_RECORD, SYNC_RELATIONSHIP, FETCH_API_DATA, CHECK_MILESTONE_COMPLETION
 */
import { computed, ref, watch, onMounted } from 'vue';
import { useAutomationsV2Store } from '../../Store/storeV2';
import { PlusIcon, TrashIcon, ClockIcon } from '@heroicons/vue/24/solid';
import axios from 'axios';
import DataTokenInserter from '../DataTokenInserter.vue';
import TokenInputField from '../TokenInputField.vue';

const props = defineProps({
    step:           { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step']);
const store = useAutomationsV2Store();
const schema = computed(() => store.automationSchema || []);
const dictCache = ref({});

const ACTION_TYPES = [
    { value: 'SEND_EMAIL',                   label: '📧 Send Email' },
    { value: 'PROCESS_EMAIL',                label: '⚙️ Process Email' },
    { value: 'CREATE_RECORD',                label: '➕ Create Record' },
    { value: 'UPDATE_RECORD',                label: '✏️ Update Record' },
    { value: 'SYNC_RELATIONSHIP',            label: '🔗 Sync Relationship' },
    { value: 'FETCH_API_DATA',               label: '🌐 Fetch API Data' },
    { value: 'CHECK_MILESTONE_COMPLETION',   label: '✅ Check Milestone Completion' },
];

const config = computed({
    get: () => props.step.step_config || {},
    set: (v) => emit('update:step', { ...props.step, step_config: v }),
});

function set(key, val) { config.value = { ...config.value, [key]: val }; }
function setType(t)    { config.value = { action_type: t }; }

// --- Model helpers ---
const modelOptions = computed(() => schema.value.map(m => ({ label: m.name, value: m.name })));

const selectedModelObj = computed(() => {
    const tm = config.value.target_model;
    if (!tm) return null;
    return schema.value.find(m => m.name === tm || m.full_class === tm) || null;
});

const columns = computed(() => (selectedModelObj.value?.columns || []).map(c => {
    if (typeof c === 'string') return { name: c, label: humanize(c) };
    return { ...c, label: c.label || humanize(c.name) };
}));

const requiredFields = computed(() => selectedModelObj.value?.required_on_create || []);

function humanize(n) {
    if (!n) return '';
    let s = n.toLowerCase().replace(/_id$/, '').replace(/[_-]+/g, ' ');
    return s.replace(/\b\w/g, c => c.toUpperCase());
}

// Field rows
function addField() { set('fields', [...(config.value.fields || []), { column: '', value: '' }]); }
function removeField(i) { set('fields', (config.value.fields || []).filter((_, idx) => idx !== i)); }
function updateField(i, key, val) {
    const f = [...(config.value.fields || [])];
    f[i] = { ...f[i], [key]: val };
    if (key === 'column') delete f[i].field; // remove legacy key if changing
    set('fields', f);
}

// Allowed values dictionary
async function fetchDict(model, field) {
    const key = `${model}:${field}`;
    if (dictCache.value[key]) return;
    try {
        const { data } = await axios.get(`/api/value-dictionaries/${encodeURIComponent(model)}/${encodeURIComponent(field)}`);
        const opts = Array.isArray(data) ? data : (data?.values || []);
        if (opts.length) dictCache.value = { ...dictCache.value, [key]: opts };
    } catch (e) { /* silent */ }
}

function getAllowedOptions(field) {
    const col = columns.value.find(c => c.name === (field.column || field.field));
    if (col?.allowed_values?.length) return col.allowed_values;
    const key = `${config.value.target_model}:${field.column || field.field}`;
    return dictCache.value[key] || null;
}

// Seed required fields on model select
function onModelChange(val) {
    set('target_model', val);
    const req = schema.value.find(m => m.name === val)?.required_on_create || [];
    const existing = new Set((config.value.fields || []).map(f => f.column));
    const toAdd = req.filter(r => !existing.has(r)).map(r => ({ column: r, value: '' }));
    if (toAdd.length) set('fields', [...(config.value.fields || []), ...toAdd]);
}

watch(() => config.value.target_model, (m) => {
    if (m) {
        (config.value.fields || []).forEach(f => {
            const name = f.column || f.field;
            if (name) fetchDict(m, name);
        });
    }
}, { immediate: true });

// API field helpers
function addApiField(parent = null) {
    const f = { id: Date.now(), name: '', type: 'Text' };
    if (parent) { parent.schema = [...(parent.schema || []), f]; set('responseStructure', [...(config.value.responseStructure || [])]); }
    else        { set('responseStructure', [...(config.value.responseStructure || []), f]); }
}
function removeApiField(id, parent = null) {
    if (parent) { parent.schema = (parent.schema || []).filter(f => f.id !== id); set('responseStructure', [...(config.value.responseStructure || [])]); }
    else        { set('responseStructure', (config.value.responseStructure || []).filter(f => f.id !== id)); }
}
function updateApiField(id, key, val, parent = null) {
    const list = parent ? parent.schema || [] : config.value.responseStructure || [];
    const updated = list.map(f => f.id !== id ? f : { ...f, [key]: val });
    if (parent) { parent.schema = updated; set('responseStructure', [...(config.value.responseStructure || [])]); }
    else        { set('responseStructure', updated); }
}
</script>

<template>
    <div class="space-y-4">
        <!-- Action type selector -->
        <div>
            <label class="field-label">Action Type</label>
            <select :value="config.action_type || ''" @change="setType($event.target.value)" class="v2-select">
                <option value="" disabled>— Select action —</option>
                <option v-for="a in ACTION_TYPES" :key="a.value" :value="a.value">{{ a.label }}</option>
            </select>
        </div>

        <!-- ---- SEND EMAIL ---- -->
        <template v-if="config.action_type === 'SEND_EMAIL'">
            <div v-for="field in ['to', 'subject']" :key="field">
                <label class="field-label capitalize">{{ field }}</label>
                <TokenInputField 
                    v-model="config[field]" 
                    :all-steps-before="allStepsBefore" 
                    :placeholder="`e.g. {{trigger.email.${field}}}`" 
                />
            </div>
            <div>
                <label class="field-label">Body</label>
                <TokenInputField 
                    v-model="config.body" 
                    :all-steps-before="allStepsBefore" 
                    textarea 
                    :rows="6" 
                    placeholder="Write your email body here..." 
                />
            </div>
        </template>

        <!-- ---- PROCESS EMAIL ---- -->
        <template v-if="config.action_type === 'PROCESS_EMAIL'">
            <div>
                <label class="field-label">Email ID</label>
                <TokenInputField 
                    v-model="config.email_id" 
                    :all-steps-before="allStepsBefore" 
                    placeholder="e.g. {{email.id}}" 
                />
            </div>
            <div>
                <label class="field-label">Queue (optional)</label>
                <input :value="config.on_queue || ''" @input="set('on_queue', $event.target.value)" class="v2-input" placeholder="e.g. emails" />
            </div>
        </template>


        <!-- ---- CREATE / UPDATE RECORD ---- -->
        <template v-if="config.action_type === 'CREATE_RECORD' || config.action_type === 'UPDATE_RECORD'">
            <div>
                <label class="field-label">{{ config.action_type === 'CREATE_RECORD' ? 'Model to Create' : 'Model to Update' }}</label>
                <select :value="config.target_model || ''" @change="onModelChange($event.target.value)" class="v2-select">
                    <option value="">— Select model —</option>
                    <option v-for="m in modelOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
            </div>
            <template v-if="config.action_type === 'UPDATE_RECORD'">
                <div>
                    <label class="field-label">Record ID</label>
                    <TokenInputField 
                        v-model="config.record_id" 
                        :all-steps-before="allStepsBefore" 
                        placeholder="e.g. {{trigger.task.id}}" 
                    />
                </div>
            </template>
            <div v-if="config.target_model">
                <label class="field-label">Fields to set</label>
                <div class="space-y-2 mt-1">
                    <div v-for="(field, idx) in (config.fields || [])" :key="idx" class="p-2 bg-gray-50 rounded-lg border space-y-2">
                        <div class="flex gap-2 items-center">
                            <select :value="field.column || field.field" @change="updateField(idx, 'column', $event.target.value)" class="v2-select flex-1">
                                <option value="" disabled>Field…</option>
                                <option v-for="col in columns" :key="col.name" :value="col.name">{{ col.label }}</option>
                            </select>
                            <button @click="removeField(idx)" class="text-gray-400 hover:text-red-500 p-1"><TrashIcon class="w-4 h-4" /></button>
                        </div>
                        <div class="flex gap-2 items-center">
                            <template v-if="getAllowedOptions(field) && !((field.value||'').includes('{{'))">
                                <select :value="field.value || ''" @change="updateField(idx, 'value', $event.target.value)" class="v2-select flex-1">
                                    <option value="">Select…</option>
                                    <option v-for="opt in getAllowedOptions(field)" :key="opt.value" :value="opt.value">{{ opt.label ?? opt.value }}</option>
                                </select>
                            </template>
                            <template v-else>
                                <TokenInputField 
                                    v-model="field.value" 
                                    :all-steps-before="allStepsBefore" 
                                    placeholder="Value…" 
                                />
                            </template>
                        </div>
                    </div>
                    <button @click="addField" class="v2-btn-outline w-full"><PlusIcon class="w-4 h-4" /> Add Field</button>
                </div>
            </div>
        </template>

        <!-- ---- SYNC RELATIONSHIP ---- -->
        <template v-if="config.action_type === 'SYNC_RELATIONSHIP'">
            <div>
                <label class="field-label">Target Model</label>
                <select :value="config.target_model || ''" @change="onModelChange($event.target.value)" class="v2-select">
                    <option value="">— Select model —</option>
                    <option v-for="m in modelOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
            </div>
            <div>
                <label class="field-label">Record ID</label>
                <TokenInputField 
                    v-model="config.record_id" 
                    :all-steps-before="allStepsBefore" 
                    placeholder="e.g. {{trigger.task.id}}" 
                />
            </div>
            <div v-if="selectedModelObj">
                <label class="field-label">Relationship</label>
                <select :value="config.relationship || ''" @change="set('relationship', $event.target.value)" class="v2-select">
                    <option value="">— Select —</option>
                    <option v-for="r in (selectedModelObj?.relationships || []).filter(r => ['BelongsToMany','MorphToMany'].includes(r.type))" :key="r.name" :value="r.name">
                        {{ r.name }} ({{ r.type }})
                    </option>
                </select>
            </div>
            <div v-if="config.relationship">
                <label class="field-label">Sync Mode</label>
                <select :value="config.sync_mode || 'sync'" @change="set('sync_mode', $event.target.value)" class="v2-select">
                    <option value="sync">Sync (replace all)</option>
                    <option value="attach">Attach (add)</option>
                    <option value="detach">Detach (remove)</option>
                </select>
                <label class="field-label mt-2">Related IDs</label>
                <TokenInputField 
                    v-model="config.related_ids" 
                    :all-steps-before="allStepsBefore" 
                    placeholder="e.g. {{step_1.ids}} or 1,2,3" 
                />
            </div>
        </template>

        <!-- ---- FETCH API DATA ---- -->
        <template v-if="config.action_type === 'FETCH_API_DATA'">
            <div>
                <label class="field-label">API URL</label>
                <TokenInputField 
                    v-model="config.api_url" 
                    :all-steps-before="allStepsBefore" 
                    placeholder="https://api.example.com/v1/..." 
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="field-label">Method</label>
                    <select :value="config.api_method || 'GET'" @change="set('api_method', $event.target.value)" class="v2-select">
                        <option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Auth</label>
                    <select :value="config.api_auth_type || 'NONE'" @change="set('api_auth_type', $event.target.value)" class="v2-select">
                        <option value="NONE">None</option><option value="BEARER">Bearer Token</option><option value="BASIC">Basic Auth</option><option value="CUSTOM_HEADER">Custom Header</option>
                    </select>
                </div>
            </div>
            <div v-if="config.api_auth_type === 'BEARER'">
                <label class="field-label">Bearer Token</label>
                <TokenInputField 
                    v-model="config.api_auth_token" 
                    :all-steps-before="allStepsBefore" 
                    placeholder="Token…" 
                />
            </div>
            <div v-if="config.api_auth_type === 'BASIC'" class="grid grid-cols-2 gap-3">
                <div>
                    <label class="field-label">Username</label>
                    <input :value="config.api_auth_username || ''" @input="set('api_auth_username', $event.target.value)" class="v2-input" />
                </div>
                <div>
                    <label class="field-label">Password</label>
                    <input type="password" :value="config.api_auth_password || ''" @input="set('api_auth_password', $event.target.value)" class="v2-input" />
                </div>
            </div>
            <div v-if="config.api_auth_type === 'CUSTOM_HEADER'" class="grid grid-cols-2 gap-3">
                <div>
                    <label class="field-label">Header Name</label>
                    <input :value="config.api_auth_header_name || ''" @input="set('api_auth_header_name', $event.target.value)" class="v2-input" placeholder="X-Api-Key" />
                </div>
                <div>
                    <label class="field-label">Header Value</label>
                    <TokenInputField 
                        v-model="config.api_auth_header_value" 
                        :all-steps-before="allStepsBefore" 
                    />
                </div>
            </div>
            <div>
                <label class="field-label">Payload / Params (JSON)</label>
                <TokenInputField 
                    v-model="config.api_payload" 
                    :all-steps-before="allStepsBefore" 
                    textarea 
                    :rows="4" 
                    placeholder='{"key": "{{trigger.value}}"}' 
                />
                <p class="text-[11px] text-gray-400 mt-1">For GET/DELETE → query params. For POST/PUT/PATCH → JSON body.</p>
            </div>

            <div>
                <label class="field-label">Response Data Key (optional)</label>
                <input :value="config.api_response_key || ''" @input="set('api_response_key', $event.target.value)" class="v2-input" placeholder="e.g. data.items" />
            </div>
            <!-- Response structure -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="field-label mb-0">Response Structure</label>
                    <button @click="addApiField()" class="text-xs text-indigo-600 font-semibold hover:underline">+ Add Field</button>
                </div>
                <div class="space-y-2">
                    <div v-for="field in (config.responseStructure || [])" :key="field.id" class="p-2 bg-gray-50 rounded-lg border">
                        <div class="flex gap-2 items-center">
                            <input :value="field.name" @input="updateApiField(field.id, 'name', $event.target.value)" class="v2-input flex-1 text-xs" placeholder="Field name" />
                            <select :value="field.type" @change="updateApiField(field.id, 'type', $event.target.value)" class="v2-select text-xs w-36">
                                <option>Text</option><option>Number</option><option>Boolean</option><option>Object</option><option>Array of Objects</option>
                            </select>
                            <button @click="removeApiField(field.id)" class="text-gray-400 hover:text-red-500"><TrashIcon class="w-3.5 h-3.5" /></button>
                        </div>
                        <div v-if="field.type === 'Object' || field.type === 'Array of Objects'" class="mt-2 ml-4 border-l-2 border-gray-200 pl-2 space-y-1">
                            <div v-for="sub in (field.schema || [])" :key="sub.id" class="flex gap-2 items-center">
                                <input :value="sub.name" @input="updateApiField(sub.id, 'name', $event.target.value, field)" class="v2-input flex-1 text-xs" placeholder="Sub-field" />
                                <select :value="sub.type" @change="updateApiField(sub.id, 'type', $event.target.value, field)" class="v2-select text-xs w-24">
                                    <option>Text</option><option>Number</option><option>Boolean</option>
                                </select>
                                <button @click="removeApiField(sub.id, field)" class="text-gray-400 hover:text-red-500"><TrashIcon class="w-3 h-3" /></button>
                            </div>
                            <button @click="addApiField(field)" class="text-[11px] text-indigo-600 hover:underline">+ sub-field</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ---- CHECK MILESTONE COMPLETION ---- -->
        <template v-if="config.action_type === 'CHECK_MILESTONE_COMPLETION'">
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                ✅ This action checks if all tasks in a milestone are complete and updates the milestone status automatically. No additional configuration required.
            </div>
            <div>
                <label class="field-label">Milestone ID (optional override)</label>
                <TokenInputField 
                    v-model="config.milestone_id" 
                    :all-steps-before="allStepsBefore" 
                    placeholder="Leave blank to auto-detect from trigger" 
                />
            </div>
        </template>
    </div>
</template>
