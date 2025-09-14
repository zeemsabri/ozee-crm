<script setup>
import { ref, watch, computed } from 'vue';
import { FilePlusIcon, XCircleIcon, CodeIcon, ChevronDownIcon } from 'lucide-vue-next';

const props = defineProps({
    prompt: { type: Object, required: true },
});

const emit = defineEmits(['save', 'cancel']);

const editedPrompt = ref(JSON.parse(JSON.stringify(props.prompt)));
// Ensure defaults for nested structures
if (!Array.isArray(editedPrompt.value.template_variables)) {
    editedPrompt.value.template_variables = [];
}
if (!editedPrompt.value.generation_config || typeof editedPrompt.value.generation_config !== 'object') {
    editedPrompt.value.generation_config = {};
}
// Apply defaults for generation config fields
if (editedPrompt.value.generation_config.responseMimeType == null) {
    editedPrompt.value.generation_config.responseMimeType = 'application/json';
}
if (editedPrompt.value.generation_config.maxOutputTokens == null) {
    editedPrompt.value.generation_config.maxOutputTokens = 2048;
}

const newVariable = ref('');
const showJsonEditor = ref(false);

watch(() => props.prompt, (newPrompt) => {
    editedPrompt.value = JSON.parse(JSON.stringify(newPrompt));
    // Re-apply safe defaults when the prop changes
    if (!Array.isArray(editedPrompt.value.template_variables)) {
        editedPrompt.value.template_variables = [];
    }
    if (!editedPrompt.value.generation_config || typeof editedPrompt.value.generation_config !== 'object') {
        editedPrompt.value.generation_config = {};
    }
    // Apply defaults when prompt changes
    if (editedPrompt.value.generation_config.responseMimeType == null) {
        editedPrompt.value.generation_config.responseMimeType = 'application/json';
    }
    if (editedPrompt.value.generation_config.maxOutputTokens == null) {
        editedPrompt.value.generation_config.maxOutputTokens = 2048;
    }
}, { deep: true });

function handleVariableAdd() {
    const variable = newVariable.value.trim().replace(/[^a-zA-Z0-9_]/g, ''); // Sanitize
    if (!variable) {
        newVariable.value = '';
        return;
    }
    if (!Array.isArray(editedPrompt.value.template_variables)) {
        editedPrompt.value.template_variables = [];
    }
    if (!editedPrompt.value.template_variables.includes(variable)) {
        editedPrompt.value.template_variables.push(variable);
    }
    newVariable.value = '';
}

function handleVariableRemove(variableToRemove) {
    editedPrompt.value.template_variables = editedPrompt.value.template_variables.filter(v => v !== variableToRemove);
}

function handleGenConfigChange(field, value) {
    const base = (editedPrompt.value.generation_config && typeof editedPrompt.value.generation_config === 'object')
        ? editedPrompt.value.generation_config
        : {};
    const newConfig = { ...base, [field]: value };
    if (value === '' || value === null) {
        delete newConfig[field];
    }
    editedPrompt.value.generation_config = newConfig;
}

const generationConfigJson = computed({
    get: () => JSON.stringify(editedPrompt.value.generation_config || {}, null, 2),
    set: (value) => {
        try {
            editedPrompt.value.generation_config = JSON.parse(value);
        } catch (e) {
            // Silently ignore invalid JSON to prevent crashes
        }
    }
});

function save(isNewVersion = false) {
    const payload = { ...editedPrompt.value };
    if (isNewVersion) {
        payload.isNewVersion = true;
        payload.version = (payload.version || 1) + 1;
    }
    emit('save', payload);
}

const isNew = computed(() => !props.prompt.id);
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-7xl mx-auto">
        <!-- Left Column: Main Editor -->
        <div class="md:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">{{ isNew ? 'Create New Prompt' : `Editing: ${editedPrompt.name || props.prompt.name}` }}</h2>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">System Prompt Text</label>
                <textarea
                    rows="20"
                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                    placeholder="You are a helpful AI assistant..."
                    v-model="editedPrompt.system_prompt_text"
                />
            </div>
        </div>

        <!-- Right Column: Configuration -->
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex flex-col gap-2">
                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="$emit('cancel')" class="px-4 py-2 text-sm font-semibold text-gray-800 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="button" @click="save(false)" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ isNew ? 'Create Prompt' : 'Save Changes' }}</button>
                    </div>
                    <button type="button" v-if="!isNew" @click="save(true)" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-gray-800 bg-gray-200 rounded-md hover:bg-gray-300">
                        <FilePlusIcon class="h-4 w-4" />
                        Save as New Version (v{{ (editedPrompt.version || 1) + 1 }})
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 space-y-4">
                <h3 class="font-bold text-gray-700">Configuration</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Prompt Name</label>
                    <input type="text" v-model="editedPrompt.name" class="w-full p-2 mt-1 border border-gray-300 rounded-md text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <input type="text" v-model="editedPrompt.category" class="w-full p-2 mt-1 border border-gray-300 rounded-md text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select v-model="editedPrompt.status" class="w-full p-2 mt-1 border border-gray-300 rounded-md bg-white text-sm">
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 space-y-4">
                <h3 class="font-bold text-gray-700">Template Variables</h3>
                <div class="flex flex-wrap gap-2" v-if="editedPrompt.template_variables?.length > 0">
                    <div v-for="v in editedPrompt.template_variables" :key="v" class="flex items-center bg-indigo-100 text-indigo-800 text-sm font-medium pl-2 pr-1 py-1 rounded-full">
                        <span>{{ `${v}` }}</span>
                        <button type="button" @click="handleVariableRemove(v)" class="ml-1.5 p-0.5 rounded-full hover:bg-indigo-200"><XCircleIcon class="h-4 w-4"/></button>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <input
                        type="text"
                        v-model="newVariable"
                        @keydown.enter.prevent="handleVariableAdd"
                        placeholder="Add variable name..."
                        class="w-full p-2 border border-gray-300 rounded-md text-sm"
                    />
                    <button type="button" @click="handleVariableAdd" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Add</button>
                </div>
            </div>

            <details class="bg-white rounded-lg shadow-sm border border-gray-200" open>
                <summary class="p-4 font-bold text-gray-700 list-none flex justify-between items-center cursor-pointer group">
                    Advanced Settings
                    <ChevronDownIcon class="group-open:rotate-180 transition-transform"/>
                </summary>
                <div class="p-4 border-t space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Model Name</label>
                        <input type="text" v-model="editedPrompt.model_name" class="w-full p-2 mt-1 border border-gray-300 rounded-md font-mono text-sm" />
                    </div>
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="font-medium text-gray-700 text-sm">Generation Config</h4>
                            <button type="button" @click="showJsonEditor = !showJsonEditor" class="inline-flex items-center gap-1.5 px-2 py-1 text-xs text-gray-600 rounded-md hover:bg-gray-100">
                                <CodeIcon class="h-4 w-4" />
                                {{ showJsonEditor ? 'Use Form' : 'Edit as JSON' }}
                            </button>
                        </div>
                        <textarea v-if="showJsonEditor" rows="6" class="w-full p-2 mt-1 border border-gray-300 rounded-md font-mono text-sm" v-model="generationConfigJson"></textarea>
                        <div v-else class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Temperature</label>
                                <input type="range" min="0" max="1" step="0.1" :value="editedPrompt.generation_config?.temperature || 0.7" @input="handleGenConfigChange('temperature', parseFloat($event.target.value))" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer" />
                                <span class="text-xs text-gray-500 text-center block">{{ editedPrompt.generation_config?.temperature || 0.7 }} (Creativity)</span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Maximum Output</label>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    :value="editedPrompt.generation_config?.maxOutputTokens || 2048"
                                    @input="handleGenConfigChange('maxOutputTokens', $event.target.value === '' ? '' : parseInt($event.target.value))"
                                    class="w-full p-2 mt-1 border border-gray-300 rounded-md text-sm"
                                />
                                <span class="text-xs text-gray-500 text-center block">Tokens</span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Response Format</label>
                                <select :value="editedPrompt.generation_config?.responseMimeType || 'application/json'" @change="handleGenConfigChange('responseMimeType', $event.target.value)" class="w-full p-2 mt-1 border border-gray-300 rounded-md bg-white text-sm">
                                    <option value="text/plain">Text</option>
                                    <option value="application/json">JSON</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        </div>
    </div>
</template>
