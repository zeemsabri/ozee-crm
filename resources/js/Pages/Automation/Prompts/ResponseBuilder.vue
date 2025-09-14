<template>
    <div class="p-8 bg-gray-100 min-h-screen font-sans">
        <div class="max-w mx-auto bg-white shadow-xl rounded-2xl overflow-hidden md:flex">
            <!-- Left Panel: Form View -->
            <div class="w-full md:w-2/3 p-6 md:p-8 border-r border-gray-200">
                <h2 class="text-3xl font-bold text-gray-800 mb-6">Response Form Builder</h2>
                <p class="text-gray-600 mb-8">
                    Manually create the response structure for the AI. Changes here will update the JSON output.
                </p>

                <FieldBuilder v-model:schema="localSchema" />

                <div class="mt-8">
                    <label for="jsonImport" class="block text-sm font-medium text-gray-700 mb-2">Import JSON</label>
                    <textarea
                        id="jsonImport"
                        v-model="jsonImport"
                        rows="5"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 resize-none font-mono"
                        placeholder="Paste your JSON schema here to import..."
                    ></textarea>
                    <button @click="importJson" class="mt-2 w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Import from JSON
                    </button>
                </div>
            </div>

            <!-- Right Panel: Final JSON Output -->
            <div class="w-full md:w-1/2 p-6 md:p-8">
                <h2 class="text-3xl font-bold text-gray-800 mb-6">JSON Output</h2>
                <p class="text-gray-600 mb-8">
                    This is the final JSON object that will be sent to the AI. Any changes you make in the form will appear here.
                </p>

                <textarea
                    v-model="jsonOutput"
                    rows="20"
                    readonly
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none resize-none font-mono"
                ></textarea>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import FieldBuilder from './FieldBuilder.vue';

const localSchema = ref([]);
const jsonOutput = ref('');
const jsonImport = ref('');

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
        return newField;
    });
};

const transformFormToJson = (form) => {
    return form.map(field => {
        const newField = {
            name: field.name,
            type: field.type,
            validations: field.validations || {}
        };
        if (field.options) {
            newField.options = field.options.split(',').map(s => s.trim());
        }
        if (field.itemType) {
            newField.itemType = field.itemType;
        }
        if (field.schema) {
            newField.schema = transformFormToJson(field.schema);
        }
        return newField;
    });
};

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

// Watch for changes in the form and update the JSON output
watch(localSchema, () => {
    jsonOutput.value = JSON.stringify(transformFormToJson(localSchema.value), null, 2);
}, { deep: true });

// Initial population of the JSON output
jsonOutput.value = JSON.stringify([], null, 2);
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
:root {
    font-family: 'Inter', sans-serif;
}
</style>
