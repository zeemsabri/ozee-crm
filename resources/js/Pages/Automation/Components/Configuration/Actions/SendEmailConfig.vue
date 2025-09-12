<script setup>
import { computed, ref, watch } from 'vue';
import { useWorkflowStore } from '../../../Store/workflowStore';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);

// Local state for the email fields
const emailConfig = ref({
    to: '',
    subject: '',
    body: ''
});

// When the step changes, load the existing configuration
watch(step, (newStep) => {
    if (newStep && newStep.step_config) {
        emailConfig.value.to = newStep.step_config.to || '';
        emailConfig.value.subject = newStep.step_config.subject || '';
        emailConfig.value.body = newStep.step_config.body || '';
    } else {
        emailConfig.value.to = '';
        emailConfig.value.subject = '';
        emailConfig.value.body = '';
    }
}, { immediate: true });

// When the local state changes, sync it back to the step config
watch(emailConfig, (newConfig) => {
    if (step.value) {
        if (!step.value.step_config || typeof step.value.step_config !== 'object' || Array.isArray(step.value.step_config)) {
            step.value.step_config = {};
        }
        step.value.step_config.to = newConfig.to;
        step.value.step_config.subject = newConfig.subject;
        step.value.step_config.body = newConfig.body;
    }
}, { deep: true });
</script>

<template>
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-gray-700">To</label>
            <input v-model="emailConfig.to" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g., {{ lead.email }}" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Subject</label>
            <input v-model="emailConfig.subject" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g., Welcome to our service!" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Body</label>
            <textarea v-model="emailConfig.body" rows="8" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="Enter the email body here. You can use variables like {{ lead.name }}"></textarea>
        </div>
    </div>
</template>
