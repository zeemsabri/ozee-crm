<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';

const props = defineProps({
    allStepsBefore: { type: Array, default: () => [] },
    // This new prop will be provided when the inserter is inside a loop
    loopContextSchema: { type: Object, default: null },
});

const emit = defineEmits(['insert']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

const dataSources = computed(() => {
    const sources = [];

    // NEW: If we are inside a loop, this is the most relevant context. Add it first.
    if (props.loopContextSchema) {
        sources.push({
            name: 'Loop Item',
            fields: props.loopContextSchema.columns.map(col => ({
                label: col.name,
                value: `{{loop.item.${col.name}}}`
            }))
        });
    }

    const triggerStep = props.allStepsBefore.find(s => s.step_type === 'TRIGGER');

    if (triggerStep && triggerStep.step_config?.model) {
        const modelSchema = automationSchema.value.find(m => m.name === triggerStep.step_config.model);
        if (modelSchema) {
            sources.push({
                name: `Trigger: ${triggerStep.step_config.model}`,
                fields: modelSchema.columns.map(col => ({
                    label: typeof col === 'string' ? col : col.name,
                    value: `{{trigger.${triggerStep.step_config.model.toLowerCase()}.${typeof col === 'string' ? col : col.name}}}`
                }))
            });
        }
    }

    props.allStepsBefore.forEach((step, index) => {
        if (step.step_type === 'AI_PROMPT' && step.step_config?.responseStructure?.length > 0) {
            // Include top-level fields and, for Array of Objects, their sub-fields as indented entries
            const fields = [];
            (step.step_config.responseStructure || []).forEach(field => {
                // Always include the top-level field
                fields.push({
                    label: field.name,
                    value: `{{step_${step.id}.${field.name}}}`
                });
                // If it's an Array of Objects, also list its sub-fields
                if (field.type === 'Array of Objects' && Array.isArray(field.schema)) {
                    field.schema.forEach(sub => {
                        if (!sub?.name) return;
                        fields.push({
                            label: `- ${sub.name}`,
                            value: `{{step_${step.id}.${field.name}.${sub.name}}}`
                        });
                    });
                }
            });
            sources.push({
                name: `Step ${index + 1}: AI Response`,
                fields,
            });
        }
    });

    return sources;
});

function handleInsert(event) {
    const token = event.target.value;
    if (token) {
        emit('insert', token);
        event.target.value = "";
    }
}
</script>

<template>
    <select
        @change="handleInsert"
        class="p-1 border border-gray-300 rounded-md bg-white text-xs focus:ring-indigo-500 focus:border-indigo-500"
    >
        <option value="" disabled selected>+ Insert Data</option>
        <template v-for="source in dataSources" :key="source.name">
            <optgroup :label="source.name">
                <option v-for="field in source.fields" :key="field.value" :value="field.value">
                    {{ field.label }}
                </option>
            </optgroup>
        </template>
    </select>
</template>
