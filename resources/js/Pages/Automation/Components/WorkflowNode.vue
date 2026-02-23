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

// Emits for updating the step data back to the parent
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
      class="!bg-indigo-500 !w-3 !h-3 !-top-1.5"
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

    <!-- Output Handles (Bottom) -->
    <template v-if="isCondition">
      <!-- Yes Branch -->
      <Handle
        type="source"
        id="yes"
        :position="Position.Bottom"
        class="!bg-green-500 !w-3 !h-3 !left-1/4"
      />
      <div class="absolute -bottom-10 left-1/4 -translate-x-1/2 flex flex-col items-center">
        <div class="text-[10px] font-bold text-green-600 mb-1">YES</div>
        <AddStepButton @select="(type) => props.data.onAddStep(type, 'yes')" />
      </div>
      
      <!-- No Branch -->
      <Handle
        type="source"
        id="no"
        :position="Position.Bottom"
        class="!bg-red-500 !w-3 !h-3 !left-3/4"
      />
      <div class="absolute -bottom-10 left-3/4 -translate-x-1/2 flex flex-col items-center">
        <div class="text-[10px] font-bold text-red-600 mb-1">NO</div>
        <AddStepButton @select="(type) => props.data.onAddStep(type, 'no')" />
      </div>
    </template>

    <template v-else-if="isForEach">
      <!-- Children Branch -->
      <Handle
        type="source"
        id="children"
        :position="Position.Bottom"
        class="!bg-purple-500 !w-3 !h-3"
      />
      <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 flex flex-col items-center">
        <div class="text-[10px] font-bold text-purple-600 mb-1">LOOP</div>
        <AddStepButton @select="(type) => props.data.onAddStep(type, 'children')" />
      </div>
    </template>

    <template v-else>
      <Handle
        type="source"
        :position="Position.Bottom"
        class="!bg-indigo-500 !w-3 !h-3 !-bottom-1.5"
      />
      <div class="absolute -bottom-10 left-1/2 -translate-x-1/2">
         <AddStepButton @select="(type) => props.data.onAddStep(type, null)" />
      </div>
    </template>
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
