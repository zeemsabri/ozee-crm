<script setup>
/**
 * PropertiesSidebar.vue
 * Slide-over panel that shows the configuration form for the currently selected step.
 * It dynamically renders one of the config panel components based on step_type.
 */
import { computed, watch } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/solid';
import { useAutomationsV2Store } from '../Store/storeV2';

// Step-specific config sub-panels (one per type)
import TriggerConfig      from './StepConfigs/TriggerConfig.vue';
import ActionConfig       from './StepConfigs/ActionConfig.vue';
import ConditionConfig    from './StepConfigs/ConditionConfig.vue';
import FetchRecordsConfig from './StepConfigs/FetchRecordsConfig.vue';
import ForEachConfig      from './StepConfigs/ForEachConfig.vue';
import AIConfig           from './StepConfigs/AIConfig.vue';
import TransformConfig    from './StepConfigs/TransformConfig.vue';

const store = useAutomationsV2Store();

const step = computed(() => store.selectedStep);

const configComponent = computed(() => {
    switch (step.value?.step_type) {
        case 'TRIGGER':
        case 'SCHEDULE_TRIGGER':
            return TriggerConfig;
        case 'ACTION':
            return ActionConfig;
        case 'CONDITION':
            return ConditionConfig;
        case 'FETCH_RECORDS':
            return FetchRecordsConfig;
        case 'FOR_EACH':
            return ForEachConfig;
        case 'AI_PROMPT':
            return AIConfig;
        case 'TRANSFORM':
        case 'DEFINE_VARIABLE':
            return TransformConfig;
        default:
            return null;
    }
});

// Collect all steps before the selected one (flattened, for token context)
function flatAll(steps, result = []) {
    steps.forEach(s => {
        result.push(s);
        if (s.if_true)  flatAll(s.if_true,  result);
        if (s.if_false) flatAll(s.if_false, result);
        if (s.children) flatAll(s.children, result);
    });
    return result;
}

function collectBefore(steps, targetId, acc = [], found = { v: false }) {
    for (const s of steps) {
        if (String(s.id) === String(targetId)) { found.v = true; return acc; }
        acc.push(s);
        if (s.if_true)  collectBefore(s.if_true,  targetId, acc, found);
        if (s.if_false) collectBefore(s.if_false, targetId, acc, found);
        if (s.children) collectBefore(s.children, targetId, acc, found);
        if (found.v) return acc;
    }
    return acc;
}

const allStepsBefore = computed(() => {
    if (!step.value) return [];
    return collectBefore(store.workflowSteps, step.value.id);
});

function close() {
    store.selectNode(null);
}

function onUpdate(newStep) {
    store.updateStep(step.value.id, newStep);
}
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0"
    >
        <div
            v-if="step"
            class="absolute right-0 top-0 h-full w-[420px] bg-white border-l border-gray-200 shadow-2xl z-30 flex flex-col"
        >
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b bg-gray-50">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Configure Step</p>
                    <h2 class="text-base font-bold text-gray-800 mt-0.5">{{ step.name || step.step_type }}</h2>
                </div>
                <button
                    @click="close"
                    class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 text-gray-500 transition"
                >
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>

            <!-- Step name editor -->
            <div class="px-5 pt-4 pb-2 border-b">
                <label class="block text-xs font-medium text-gray-500 mb-1">Step Name</label>
                <input
                    type="text"
                    :value="step.name"
                    @input="onUpdate({ ...step, name: $event.target.value })"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium focus:outline-none focus:ring-2 focus:ring-indigo-400 transition"
                    placeholder="Label this step..."
                />
                <!-- Delay for non-triggers -->
                <div
                    v-if="step.step_type !== 'TRIGGER' && step.step_type !== 'SCHEDULE_TRIGGER'"
                    class="mt-2 flex items-center gap-2"
                >
                    <label class="text-xs text-gray-500 whitespace-nowrap">Delay (min)</label>
                    <input
                        type="number"
                        min="0"
                        :value="step.delay_minutes || 0"
                        @input="onUpdate({ ...step, delay_minutes: Math.max(0, +$event.target.value || 0) })"
                        class="w-20 px-2 py-1 border border-gray-200 rounded text-sm text-center"
                    />
                    <span class="text-xs text-gray-400">before executing</span>
                </div>
            </div>

            <!-- Config body: dynamically rendered per step type -->
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <component
                    :is="configComponent"
                    v-if="configComponent"
                    :step="step"
                    :all-steps-before="allStepsBefore"
                    @update:step="onUpdate"
                />
                <div v-else class="text-sm text-gray-500 italic text-center mt-8">
                    No configuration available for this step type.
                </div>
            </div>
        </div>
    </Transition>
</template>
