<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { useAutomationsV2Store } from '../Store/storeV2';
import {
    BoltIcon, CogIcon, ArrowsRightLeftIcon, MagnifyingGlassIcon,
    ArrowPathIcon, SparklesIcon, CodeBracketIcon, CalendarIcon,
    TrashIcon, PlusIcon,
} from '@heroicons/vue/24/solid';

// Props injected by Vue Flow for custom nodes
const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, required: true },
});

const store = useAutomationsV2Store();

const step = computed(() => props.data.step);
const isSelected = computed(() => store.selectedNodeId === String(props.id));

const TYPE_CONFIG = {
    TRIGGER:          { label: 'Trigger',          color: 'from-violet-500 to-purple-600', icon: BoltIcon,               ring: 'ring-violet-400' },
    SCHEDULE_TRIGGER: { label: 'Schedule',          color: 'from-sky-500 to-blue-600',     icon: CalendarIcon,            ring: 'ring-sky-400'    },
    ACTION:           { label: 'Action',            color: 'from-emerald-500 to-green-600', icon: CogIcon,                ring: 'ring-emerald-400'},
    CONDITION:        { label: 'If / Else',         color: 'from-amber-500 to-orange-500', icon: ArrowsRightLeftIcon,     ring: 'ring-amber-400'  },
    FETCH_RECORDS:    { label: 'Fetch Records',     color: 'from-cyan-500 to-teal-600',    icon: MagnifyingGlassIcon,     ring: 'ring-cyan-400'   },
    FOR_EACH:         { label: 'For Each Loop',     color: 'from-indigo-500 to-blue-600',  icon: ArrowPathIcon,           ring: 'ring-indigo-400' },
    AI_PROMPT:        { label: 'AI Prompt',         color: 'from-pink-500 to-rose-600',    icon: SparklesIcon,            ring: 'ring-pink-400'   },
    TRANSFORM:        { label: 'Transform',         color: 'from-slate-500 to-gray-600',   icon: CodeBracketIcon,         ring: 'ring-slate-400'  },
    DEFINE_VARIABLE:  { label: 'Define Variable',   color: 'from-slate-500 to-gray-600',   icon: CodeBracketIcon,         ring: 'ring-slate-400'  },
};

const cfg = computed(() => TYPE_CONFIG[step.value?.step_type] || TYPE_CONFIG.ACTION);

const summary = computed(() => {
    const s = step.value;
    const c = s?.step_config || {};
    switch (s?.step_type) {
        case 'TRIGGER':
        case 'SCHEDULE_TRIGGER':
            return c.trigger_event || c.model ? `${c.model || ''} → ${c.event || c.trigger_event || ''}` : 'Not configured';
        case 'ACTION':
            return c.action_type?.replace(/_/g, ' ') || 'Select an action';
        case 'CONDITION':
            return `${(c.rules?.length || 0)} rule(s), ${c.logic || 'AND'}`;
        case 'FETCH_RECORDS':
            return c.model ? `From: ${c.model}` : 'Select model';
        case 'FOR_EACH':
            return c.sourceArray ? `Over: ${c.sourceArray}` : 'Select data source';
        case 'AI_PROMPT':
            return c.promptRef?.name ? `Prompt: ${c.promptRef.name}` : 'No prompt selected';
        case 'TRANSFORM':
        case 'DEFINE_VARIABLE':
            return c.variable_name || 'Not configured';
        default:
            return '';
    }
});

// Whether this node type is the trigger (first, no incoming edge)
const isTrigger = computed(() =>
    ['TRIGGER', 'SCHEDULE_TRIGGER'].includes(step.value?.step_type)
);

const isCondition = computed(() => step.value?.step_type === 'CONDITION');
const isLoop       = computed(() => step.value?.step_type === 'FOR_EACH');

function select() {
    store.selectNode(props.id.replace('step-', ''));
}

function deleteNode(e) {
    e.stopPropagation();
    store.deleteStep(props.id.replace('step-', ''));
}

function addStepAfter(type, branch) {
    store.addStep(type, props.id.replace('step-', ''), branch || null);
}
</script>

<template>
    <!-- Wrapper must be transparent so VueFlow handles sizing -->
    <div
        class="relative select-none group"
        style="width: 280px;"
        @click="select"
    >
        <!-- Glow ring when selected -->
        <div
            v-if="isSelected"
            class="absolute -inset-1 rounded-2xl blur-sm opacity-60 transition-all"
            :class="`bg-gradient-to-br ${cfg.color}`"
        />

        <!-- Main card -->
        <div
            class="relative bg-white rounded-2xl shadow-lg border-2 transition-all duration-200 overflow-hidden cursor-pointer"
            :class="isSelected ? `border-transparent ring-2 ${cfg.ring} ring-offset-1` : 'border-gray-200 hover:border-gray-300 hover:shadow-xl'"
        >
            <!-- Coloured header -->
            <div :class="`bg-gradient-to-r ${cfg.color} px-4 py-3 flex items-center justify-between`">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                        <component :is="cfg.icon" class="w-4 h-4 text-white" />
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold text-white/70 uppercase tracking-widest leading-none">{{ cfg.label }}</p>
                        <p class="text-sm font-bold text-white leading-tight mt-0.5 max-w-[170px] truncate">{{ step.name || cfg.label }}</p>
                    </div>
                </div>
                <!-- Delete button (hidden for first trigger) -->
                <button
                    v-if="!isTrigger"
                    class="opacity-0 group-hover:opacity-100 transition-opacity w-7 h-7 flex items-center justify-center rounded-full bg-white/20 hover:bg-red-500 text-white"
                    title="Delete step"
                    @click.stop="deleteNode"
                >
                    <TrashIcon class="w-3.5 h-3.5" />
                </button>
            </div>

            <!-- Body summary -->
            <div class="px-4 py-3">
                <p class="text-xs text-gray-500 leading-snug line-clamp-2">{{ summary }}</p>
                <p v-if="step.delay_minutes > 0" class="mt-1.5 text-[11px] text-amber-600 font-medium">
                    ⏱ Wait {{ step.delay_minutes }}min first
                </p>
            </div>

            <!-- Condition branch labels at bottom -->
            <div v-if="isCondition" class="flex border-t text-[10px] font-semibold text-center">
                <div class="flex-1 py-2 text-emerald-600 bg-emerald-50 border-r">✓ TRUE</div>
                <div class="flex-1 py-2 text-red-500 bg-red-50">✗ FALSE</div>
            </div>
            <div v-if="isLoop" class="border-t py-2 text-center text-[10px] font-semibold text-indigo-600 bg-indigo-50">
                ↻ LOOP BODY
            </div>
        </div>

        <!-- ===== VUE FLOW HANDLES ===== -->

        <!-- Target (incoming) handle — hidden for the trigger since it has no parent -->
        <Handle
            v-if="!isTrigger"
            id="target"
            type="target"
            :position="Position.Top"
            class="!w-3 !h-3 !bg-gray-400 !border-2 !border-white"
        />

        <!-- Default source (outgoing) — for non-branching nodes -->
        <Handle
            v-if="!isCondition && !isLoop"
            id="source"
            type="source"
            :position="Position.Bottom"
            class="!w-3 !h-3 !bg-gray-400 !border-2 !border-white"
        />

        <!-- Condition TRUE branch (left) -->
        <Handle
            v-if="isCondition"
            id="yes"
            type="source"
            :position="Position.Bottom"
            style="left: 25%"
            class="!w-3 !h-3 !bg-emerald-500 !border-2 !border-white"
        />

        <!-- Condition FALSE branch (right) -->
        <Handle
            v-if="isCondition"
            id="no"
            type="source"
            :position="Position.Bottom"
            style="left: 75%"
            class="!w-3 !h-3 !bg-red-500 !border-2 !border-white"
        />

        <!-- For Each LOOP body handle -->
        <Handle
            v-if="isLoop"
            id="children"
            type="source"
            :position="Position.Bottom"
            class="!w-3 !h-3 !bg-indigo-500 !border-2 !border-white"
        />
    </div>
</template>
