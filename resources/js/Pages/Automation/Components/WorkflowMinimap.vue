<script setup>
/**
 * WorkflowMinimap
 * A compact, read-only tree view of all workflow steps.
 * Displays step types with icons and structural nesting (IF/YES/NO, FOR EACH).
 * This is a pure display component — it emits 'jump' when a step is clicked
 * so the parent can scroll the main canvas to that step's card.
 */

const props = defineProps({
    steps: { type: Array, default: () => [] },
    /** Depth used internally for recursive rendering */
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['jump']);

const STEP_META = {
    TRIGGER:           { icon: '⚡', label: 'Trigger',           color: 'indigo' },
    SCHEDULE_TRIGGER:  { icon: '⏰', label: 'Schedule Trigger',   color: 'indigo' },
    CONDITION:         { icon: '🔀', label: 'If / Else',          color: 'orange' },
    ACTION:            { icon: '⚙️', label: 'Action',             color: 'blue'   },
    AI_PROMPT:         { icon: '🤖', label: 'AI Prompt',          color: 'violet' },
    FOR_EACH:          { icon: '🔁', label: 'For Each',           color: 'purple' },
    FETCH_RECORDS:     { icon: '📋', label: 'Fetch Records',      color: 'teal'   },
    TRANSFORM_CONTENT: { icon: '✨', label: 'Transform',          color: 'pink'   },
    DEFINE_VARIABLE:   { icon: '📦', label: 'Define Variable',    color: 'gray'   },
};

const COLOR_CLASSES = {
    indigo: 'bg-indigo-50 border-indigo-300 text-indigo-700',
    orange: 'bg-orange-50 border-orange-300 text-orange-700',
    blue:   'bg-blue-50   border-blue-300   text-blue-700',
    violet: 'bg-violet-50 border-violet-300 text-violet-700',
    purple: 'bg-purple-50 border-purple-300 text-purple-700',
    teal:   'bg-teal-50   border-teal-300   text-teal-700',
    pink:   'bg-pink-50   border-pink-300   text-pink-700',
    gray:   'bg-gray-50   border-gray-300   text-gray-700',
};

function meta(stepType) {
    return STEP_META[stepType] || { icon: '📌', label: stepType, color: 'gray' };
}

function colorClass(stepType) {
    return COLOR_CLASSES[meta(stepType).color] || COLOR_CLASSES.gray;
}

function stepLabel(step) {
    const m = meta(step.step_type);
    // Show a more descriptive label when data is available
    if (step.step_type === 'ACTION' && step.step_config?.action_type) {
        const nice = step.step_config.action_type.replace(/_/g, ' ');
        return `${m.icon} ${nice}`;
    }
    if (step.step_type === 'TRIGGER' && step.step_config?.trigger_event) {
        return `${m.icon} ${step.step_config.trigger_event}`;
    }
    if (step.step_type === 'FETCH_RECORDS' && step.step_config?.model) {
        return `${m.icon} Fetch ${step.step_config.model}`;
    }
    if (step.step_type === 'FOR_EACH' && step.step_config?.sourceArray) {
        return `${m.icon} ${m.label}: ${step.step_config.sourceArray}`;
    }
    return `${m.icon} ${m.label}`;
}

function onJump(step) {
    // scroll the main canvas card into view by its id
    emit('jump', step.id);
}
</script>

<template>
    <div :class="depth > 0 ? 'ml-3 pl-2 border-l-2 border-gray-200' : ''">
        <template v-for="(step, index) in steps" :key="step.id">
            <!-- Step pill -->
            <button
                type="button"
                class="w-full text-left group flex items-start gap-2 rounded-md px-2 py-1.5 mb-1 border text-xs font-medium transition-all hover:shadow-sm hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="colorClass(step.step_type)"
                @click="onJump(step)"
                :title="`Jump to: ${step.name || stepLabel(step)}`"
            >
                <span class="flex-shrink-0 mt-px">{{ meta(step.step_type).icon }}</span>
                <span class="leading-snug truncate">{{ step.name && step.name !== `New ${step.step_type.replace('_', ' ')} Step` ? step.name : stepLabel(step) }}</span>
            </button>

            <!-- IF/ELSE branches (recursive) -->
            <template v-if="step.step_type === 'CONDITION'">
                <!-- IF YES branch -->
                <div class="ml-3 pl-2 border-l-2 border-green-300 mb-1">
                    <p class="text-[10px] font-bold text-green-600 uppercase tracking-wider mb-1 px-1">✅ If Yes</p>
                    <WorkflowMinimap
                        v-if="step.if_true && step.if_true.length"
                        :steps="step.if_true"
                        :depth="depth + 1"
                        @jump="$emit('jump', $event)"
                    />
                    <p v-else class="text-[10px] text-gray-400 italic px-1 mb-1">Empty branch</p>
                </div>
                <!-- IF NO branch -->
                <div class="ml-3 pl-2 border-l-2 border-red-300 mb-1">
                    <p class="text-[10px] font-bold text-red-500 uppercase tracking-wider mb-1 px-1">❌ If No</p>
                    <WorkflowMinimap
                        v-if="step.if_false && step.if_false.length"
                        :steps="step.if_false"
                        :depth="depth + 1"
                        @jump="$emit('jump', $event)"
                    />
                    <p v-else class="text-[10px] text-gray-400 italic px-1 mb-1">Empty branch</p>
                </div>
            </template>

            <!-- FOR EACH children (recursive) -->
            <div v-if="step.step_type === 'FOR_EACH'" class="ml-3 pl-2 border-l-2 border-purple-300 mb-1">
                <p class="text-[10px] font-bold text-purple-600 uppercase tracking-wider mb-1 px-1">🔁 Loop Body</p>
                <WorkflowMinimap
                    v-if="step.children && step.children.length"
                    :steps="step.children"
                    :depth="depth + 1"
                    @jump="$emit('jump', $event)"
                />
                <p v-else class="text-[10px] text-gray-400 italic px-1 mb-1">Empty loop</p>
            </div>

            <!-- Connector line (not after last item) -->
            <div v-if="index < steps.length - 1" class="flex justify-center mb-1">
                <div class="w-px h-3 bg-gray-300"></div>
            </div>
        </template>
    </div>
</template>
