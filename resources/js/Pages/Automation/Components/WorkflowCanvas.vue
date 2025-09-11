<script setup>
import { computed, ref } from 'vue';
import draggable from 'vuedraggable';
import { useWorkflowStore } from '../Store/workflowStore';

// Blocks
import TriggerBlock from './Blocks/TriggerBlock.vue';
import AiPromptBlock from './Blocks/AiPromptBlock.vue';
import ConditionBlock from './Blocks/ConditionBlock.vue';

const store = useWorkflowStore();

const steps = computed(() => store.activeWorkflow?.steps || []);

const blockFor = (type) => {
  switch ((type || '').toUpperCase()) {
    case 'TRIGGER':
      return TriggerBlock;
    case 'CONDITION':
      return ConditionBlock;
    case 'AI_PROMPT':
    default:
      return AiPromptBlock;
  }
};

const addMenuIndex = ref(null);
const toggleAddMenu = (index) => {
  addMenuIndex.value = addMenuIndex.value === index ? null : index;
};
const addStep = (type, index) => {
  store.addStep(type);
  addMenuIndex.value = null;
};

const onSelect = (step) => store.selectStep(step);
const onDragEnd = () => store.reorderSteps(store.activeWorkflow.steps);
</script>

<template>
  <div class="h-full overflow-y-auto">
    <div v-if="!store.activeWorkflow" class="h-full flex items-center justify-center text-gray-500">
      Select a workflow from the left to begin.
    </div>

    <div v-else class="max-w-3xl mx-auto py-6">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">{{ store.activeWorkflow.name }}</h2>

      <draggable
        v-model="store.activeWorkflow.steps"
        item-key="id"
        handle=".drag-handle"
        class="space-y-3"
        @end="onDragEnd"
      >
        <template #item="{ element, index }">
          <div class="flex items-stretch gap-3">
            <div class="flex flex-col items-center">
              <div class="w-8 text-gray-300 drag-handle cursor-grab select-none">⋮⋮</div>
              <div class="flex-1 w-px bg-gray-200"></div>
            </div>

            <div class="flex-1">
              <component :is="blockFor(element.step_type)" :step="element" @select="onSelect" />

              <!-- Add button below each block -->
              <div class="flex items-center justify-center my-2">
                <button
                  type="button"
                  class="text-xs px-2 py-1 rounded border border-dashed border-gray-300 hover:border-gray-400 text-gray-600"
                  @click="toggleAddMenu(index)"
                >
                  + Add Step
                </button>
              </div>

              <!-- Simple add menu -->
              <div v-if="addMenuIndex === index" class="flex gap-2 text-xs text-gray-700">
                <button class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200" @click="addStep('AI_PROMPT', index)">AI Prompt</button>
                <button class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200" @click="addStep('CONDITION', index)">Condition</button>
                <button class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200" @click="addStep('TRIGGER', index)">Trigger</button>
              </div>
            </div>
          </div>
        </template>
      </draggable>

      <div v-if="steps.length === 0" class="text-center text-gray-500 py-8">
        No steps yet. Use the + button to add your first step.
      </div>
    </div>
  </div>
</template>
