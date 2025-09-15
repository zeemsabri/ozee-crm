<template>
    <div class="font-sans">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Left Panel: Form View -->
            <div class="p-0 md:pr-4">
                <h2 class="text-3xl font-bold text-gray-800 mb-6">Response Form Builder</h2>
                <p class="text-gray-600 mb-8">
                    Manually create the response structure for the AI. Changes here will update the JSON output.
                </p>

                <FieldBuilder v-model:schema="localSchema" />

                <div class="mt-4">
                    <button type="button" @click="showImport = !showImport" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                        {{ showImport ? 'Hide Import JSON' : 'Import JSON' }}
                    </button>
                    <div v-if="showImport" class="mt-2">
                        <textarea
                            id="jsonImport"
                            v-model="jsonImport"
                            rows="5"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 resize-none font-mono"
                            placeholder="Paste your JSON schema here to import..."
                        ></textarea>
                        <div class="mt-2 flex justify-end">
                            <button @click="importJson" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Import
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Final JSON Output -->
            <div class="p-0 md:pl-4">
                <details>
                    <summary class="text-base font-semibold text-gray-800 cursor-pointer">Schema JSON (Raw)</summary>
                    <p class="text-gray-600 mb-2 mt-2">This is the full schema definition that will be saved with the prompt.</p>
                    <textarea
                        v-model="jsonOutput"
                        rows="16"
                        readonly
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none resize-none font-mono"
                    ></textarea>
                </details>

                <div class="mt-4">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">AI JSON Preview</h2>
                    <p class="text-gray-600 mb-4">Preview of the generated JSON that the AI is expected to return. You can customize example values per field on the left; this preview updates live.</p>
                    <textarea
                        :value="aiPreviewText"
                        rows="20"
                        readonly
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none resize-none font-mono"
                    ></textarea>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import FieldBuilder from './FieldBuilder.vue';

// v-model support from parent
const props = defineProps({
    responseVariables: { type: Array, default: () => [] },
    // We store the complete schema JSON in response_json_template as an array
    responseJsonTemplate: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:responseVariables', 'update:responseJsonTemplate']);

const localSchema = ref([]);
const jsonOutput = ref('');
const aiPreviewText = ref('');
const jsonImport = ref('');
const showImport = ref(false);

const transformJsonToForm = (json) => {
    if (!Array.isArray(json)) return [];
    return json.map(field => {
        const newField = { ...field };
        if (Array.isArray(field.options)) {
            newField.options = field.options.join(', ');
        }
        if (field.schema) {
            newField.schema = transformJsonToForm(field.schema);
        }
        if (Object.prototype.hasOwnProperty.call(field, 'example')) {
            newField.example = field.example;
        }
        return newField;
    });
};

const transformFormToJson = (form) => {
    return (form || []).map(field => {
        const newField = {
            name: field.name,
            type: field.type,
            validations: field.validations || {}
        };
        if (field.options) {
            newField.options = typeof field.options === 'string' ? field.options.split(',').map(s => s.trim()) : field.options;
        }
        if (field.itemType) {
            newField.itemType = field.itemType;
        }
        if (field.schema) {
            newField.schema = transformFormToJson(field.schema);
        }
        if (field.example !== undefined) {
            newField.example = field.example;
        }
        return newField;
    });
};

// Helper to normalize options to an array
const getOptionsArray = (opts) => Array.isArray(opts) ? opts : (typeof opts === 'string' ? opts.split(',').map(s => s.trim()).filter(Boolean) : []);

// Build AI JSON Preview using example overrides where provided
const buildAiPreview = (form = []) => {
    const output = {};
    (form || []).forEach(field => {
        if (!field || !field.name) return;
        const name = field.name;
        const type = field.type;
        const ex = field.example;
        switch (type) {
            case 'Text':
            case 'Markdown':
            case 'File':
            case 'User':
            case 'Date':
                output[name] = (ex !== undefined && ex !== null && ex !== '') ? ex : `Enter your ${name} here.`;
                break;
            case 'Number':
                output[name] = (ex !== undefined && ex !== null && ex !== '') ? Number(ex) : 0;
                break;
            case 'Boolean':
                if (typeof ex === 'boolean') {
                    output[name] = ex;
                } else if (typeof ex === 'string') {
                    output[name] = ex.toLowerCase() === 'true';
                } else {
                    output[name] = true;
                }
                break;
            case 'Select':
            case 'MultiSelect': {
                const opts = getOptionsArray(field.options);
                output[name] = (ex !== undefined && ex !== null && ex !== '') ? ex : opts;
                break;
            }
            case 'Object':
                output[name] = buildAiPreview(field.schema);
                break;
            case 'Array':
                if (field.itemType === 'Object') {
                    output[name] = [buildAiPreview(field.schema)];
                } else {
                    let val;
                    const itemType = field.itemType || 'Text';
                    if (ex !== undefined && ex !== null && ex !== '') {
                        if (itemType === 'Number') {
                            val = Number(ex);
                        } else if (itemType === 'Boolean') {
                            val = typeof ex === 'boolean' ? ex : String(ex).toLowerCase() === 'true';
                        } else {
                            val = ex;
                        }
                    } else {
                        if (itemType === 'Number') val = 0;
                        else if (itemType === 'Boolean') val = true;
                        else val = `Placeholder for ${itemType}`;
                    }
                    output[name] = [val];
                }
                break;
            default:
                output[name] = ex ?? null;
        }
    });
    return output;
};

// Initialize from incoming props
onMounted(() => {
    if (Array.isArray(props.responseVariables) && props.responseVariables.length > 0) {
        // Use structure directly from parent
        localSchema.value = JSON.parse(JSON.stringify(props.responseVariables));
    } else if (Array.isArray(props.responseJsonTemplate) && props.responseJsonTemplate.length > 0) {
        // Load full schema from response_json_template
        localSchema.value = transformJsonToForm(props.responseJsonTemplate);
    } else {
        localSchema.value = [];
    }
    // Initialize output and emit to parent
    const fullSchema = transformFormToJson(localSchema.value);
    jsonOutput.value = JSON.stringify(fullSchema, null, 2);
    aiPreviewText.value = JSON.stringify(buildAiPreview(localSchema.value), null, 2);
    emit('update:responseVariables', JSON.parse(JSON.stringify(localSchema.value)));
    emit('update:responseJsonTemplate', fullSchema);
});

const importJson = () => {
    try {
        const json = JSON.parse(jsonImport.value);
        if (!Array.isArray(json)) {
            throw new Error('Invalid JSON format. Please provide a top-level JSON array.');
        }
        localSchema.value = transformJsonToForm(json);
    } catch (e) {
        alert(`Error importing JSON: ${e.message}`);
    }
};

// Watch for changes in the form and update the JSON output, AI preview, and parent
watch(localSchema, () => {
    const fullSchema = transformFormToJson(localSchema.value);
    jsonOutput.value = JSON.stringify(fullSchema, null, 2);
    aiPreviewText.value = JSON.stringify(buildAiPreview(localSchema.value), null, 2);
    emit('update:responseVariables', JSON.parse(JSON.stringify(localSchema.value)));
    emit('update:responseJsonTemplate', fullSchema);
}, { deep: true });
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
:root {
    font-family: 'Inter', sans-serif;
}
</style>
