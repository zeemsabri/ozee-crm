<template>
    <div class="space-y-4">
        <div v-for="(field, index) in schema" :key="index" class="p-4 border rounded-lg bg-gray-50 relative">
            <div class="flex items-center space-x-2">
                <label class="block text-sm font-medium text-gray-700">Field Name</label>
                <input
                    v-model="field.name"
                    type="text"
                    placeholder="Field Name"
                    class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                />
                <label class="block text-sm font-medium text-gray-700">Field Type</label>
                <select
                    v-model="field.type"
                    class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="Text">Text</option>
                    <option value="Number">Number</option>
                    <option value="Boolean">Boolean</option>
                    <option value="Date">Date</option>
                    <option value="Select">Select</option>
                    <option value="File">File</option>
                    <option value="Object">Object</option>
                    <option value="Array">Array</option>
                </select>
                <button @click="removeField(index)" class="text-red-500 hover:text-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div v-if="field.type === 'Select'">
                <label class="block text-sm font-medium text-gray-700 mt-2">Options (comma-separated)</label>
                <input
                    v-model="field.options"
                    type="text"
                    placeholder="Option 1, Option 2"
                    class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                />
            </div>

            <div v-if="field.type === 'Array'">
                <label class="block text-sm font-medium text-gray-700 mt-2">Item Type</label>
                <select
                    v-model="field.itemType"
                    class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="Text">Text</option>
                    <option value="Number">Number</option>
                    <option value="Boolean">Boolean</option>
                    <option value="Date">Date</option>
                    <option value="Object">Object</option>
                </select>
            </div>

            <!-- AI Preview Example Value for this field -->
            <div v-if="field.type !== 'Object' && !(field.type === 'Array' && field.itemType === 'Object')" class="mt-2">
                <label class="block text-sm font-medium text-gray-700">AI Preview Value</label>
                <template v-if="field.type === 'Boolean'">
                    <label class="inline-flex items-center gap-2 mt-1">
                        <input type="checkbox" v-model="field.example" class="h-4 w-4" />
                        <span class="text-sm text-gray-600">True/False</span>
                    </label>
                </template>
                <template v-else-if="field.type === 'Number'">
                    <input
                        v-model="field.example"
                        type="number"
                        placeholder="e.g., 123"
                        class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    />
                </template>
                <template v-else-if="field.type === 'Array'">
                    <input
                        v-model="field.example"
                        type="text"
                        placeholder="Example item value (used for preview)"
                        class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    />
                </template>
                <template v-else>
                    <input
                        v-model="field.example"
                        type="text"
                        placeholder="Example value used in AI JSON Preview"
                        class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    />
                </template>
            </div>

            <!-- Recursive call for nested schema -->
            <div v-if="field.type === 'Object' || (field.type === 'Array' && field.itemType === 'Object')" class="mt-4 p-4 border rounded-lg bg-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nested Schema</label>
                <FieldBuilder v-model:schema="field.schema" />
            </div>
        </div>

        <button @click="addField" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Add Field
        </button>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
    schema: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['update:schema']);

const addField = () => {
    const newSchema = [...props.schema];
    newSchema.push({
        name: '',
        type: 'Text',
        options: '',
        itemType: '',
        schema: [],
        validations: { isRequired: false },
        example: ''
    });
    emit('update:schema', newSchema);
};

const removeField = (index) => {
    const newSchema = [...props.schema];
    newSchema.splice(index, 1);
    emit('update:schema', newSchema);
};
</script>
