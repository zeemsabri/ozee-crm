<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import TriggerStep from './Steps/TriggerStep.vue';
import ConditionStep from './Steps/ConditionStep.vue';
import ActionStep from './Steps/ActionStep.vue';
import AIStep from './Steps/AIStep.vue';
import ForEachStep from './Steps/ForEachStep.vue';
import FetchRecordsStep from './Steps/FetchRecordsStep.vue';
import ScheduleTriggerStep from './Steps/ScheduleTriggerStep.vue';
import TransformStep from './Steps/TransformStep.vue';
import DefineVariableStep from './Steps/DefineVariableStep.vue';
import AddStepButton from './Steps/AddStepButton.vue';

const props = defineProps({
  id: { type: String, required: true },
  data: { type: Object, required: true },
});

const step = computed(() => props.data.step);
const allStepsBefore = computed(() => props.data.allStepsBefore || []);
const loopContextSchema = computed(() => props.data.loopContextSchema || null);

const stepComponentMap = {
  TRIGGER: TriggerStep,
  SCHEDULE_TRIGGER: ScheduleTriggerStep,
  FETCH_RECORDS: FetchRecordsStep,
  CONDITION: ConditionStep,
  ACTION: ActionStep,
  AI_PROMPT: AIStep,
  FOR_EACH: ForEachStep,
  TRANSFORM_CONTENT: TransformStep,
  DEFINE_VARIABLE: DefineVariableStep,
};

const getStepComponent = (stepType) => stepComponentMap[stepType] || null;

const isTrigger = computed(() => 
  step.value.step_type === 'TRIGGER' || step.value.step_type === 'SCHEDULE_TRIGGER'
);

const isCondition = computed(() => step.value.step_type === 'CONDITION');
const isForEach = computed(() => step.value.step_type === 'FOR_EACH');

const emit = defineEmits(['updateStep', 'deleteStep']);

function handleUpdate(newData) {
  props.data.onUpdate(newData);
}

function handleDelete() {
  props.data.onDelete();
}
</script>

<template>
  <div :id="`step-card-${step.id}`" class="workflow-node relative group">
    <!-- Input Handle (Top) - not for triggers -->
    <Handle
      v-if="!isTrigger"
      type="target"
      :position="Position.Top"
      class="!bg-indigo-500 !w-4 !h-4 !-top-2 !z-50"
    />

    <!-- Main Step Card -->
    <div class="min-w-[400px]">
      <component
        :is="getStepComponent(step.step_type)"
        v-if="getStepComponent(step.step_type)"
        :step="step"
        :all-steps-before="allStepsBefore"
        :loop-context-schema="loopContextSchema"
        :onDelete="isTrigger ? null : handleDelete"
        @update:step="handleUpdate"
      />
    </div>

    <!-- =============================================
         OUTPUT HANDLES (Bottom) — connectors only
         + buttons moved to right side panel below
         ============================================= -->

    <!-- CONDITION node: YES / NO handles at bottom -->
    <template v-if="isCondition">
      <Handle
        type="source"
        id="yes"
        :position="Position.Bottom"
        class="!bg-green-500 !w-4 !h-4 !left-1/4 !z-50"
      />
      <!-- YES label only, no + button here -->
      <div class="absolute -bottom-6 left-1/4 -translate-x-1/2 pointer-events-none">
        <div class="text-[10px] font-bold text-green-600">YES</div>
      </div>

      <Handle
        type="source"
        id="no"
        :position="Position.Bottom"
        class="!bg-red-500 !w-4 !h-4 !left-3/4 !z-50"
      />
      <!-- NO label only, no + button here -->
      <div class="absolute -bottom-6 left-3/4 -translate-x-1/2 pointer-events-none">
        <div class="text-[10px] font-bold text-red-600">NO</div>
      </div>
    </template>

    <!-- FOR_EACH node: LOOP handle at bottom -->
    <template v-else-if="isForEach">
      <Handle
        type="source"
        id="children"
        :position="Position.Bottom"
        class="!bg-purple-500 !w-4 !h-4 !z-50"
      />
      <div class="absolute -bottom-6 left-1/2 -translate-x-1/2 pointer-events-none">
        <div class="text-[10px] font-bold text-purple-600">LOOP</div>
      </div>
    </template>

    <!-- Standard node: plain source handle -->
    <template v-else>
      <Handle
        type="source"
        :position="Position.Bottom"
        class="!bg-indigo-500 !w-4 !h-4 !-bottom-2 !z-50"
      />
    </template>

    <!-- =============================================
         BRANCH ADD BUTTONS — RIGHT SIDE PANEL
         Moved here so they NEVER overlap handles.
         Only visible on hover (group-hover).
         ============================================= -->
    <div class="nodrag nopan absolute top-1/2 -translate-y-1/2 -right-16 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-40">
      <!-- CONDITION: YES / NO add buttons -->
      <template v-if="isCondition">
        <div class="flex flex-col items-center gap-1">
          <span class="text-[9px] font-bold text-green-600 uppercase">Yes</span>
          <AddStepButton @select="(type) => props.data.onAddStep(type, 'yes')" />
        </div>
        <div class="flex flex-col items-center gap-1">
          <span class="text-[9px] font-bold text-red-500 uppercase">No</span>
          <AddStepButton @select="(type) => props.data.onAddStep(type, 'no')" />
        </div>
      </template>

      <!-- FOR_EACH: Loop add button -->
      <template v-else-if="isForEach">
        <div class="flex flex-col items-center gap-1">
          <span class="text-[9px] font-bold text-purple-600 uppercase">Loop</span>
          <AddStepButton @select="(type) => props.data.onAddStep(type, 'children')" />
        </div>
      </template>

      <!-- Standard: single add-after button -->
      <template v-else>
        <AddStepButton @select="(type) => props.data.onAddStep(type, null)" />
      </template>
    </div>
  </div>
</template>

<style scoped>
.workflow-node {
  @apply transition-shadow duration-200;
}
.vue-flow__node-workflow.selected .workflow-node {
  @apply shadow-xl ring-2 ring-indigo-500 ring-offset-2 rounded-lg;
}
</style>
