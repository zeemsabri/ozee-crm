<script setup>
import { ref } from 'vue';
import draggable from 'vuedraggable';
import { useWorkflowStore } from '../Store/workflowStore';

// Import all the block components, including the new ActionBlock
import TriggerBlock from './Blocks/TriggerBlock.vue';
import AiPromptBlock from './Blocks/AiPromptBlock.vue';
import ConditionBlock from './Blocks/ConditionBlock.vue';
import ActionBlock from './Blocks/ActionBlock.vue'; // <-- Import the new block
import { Plus } from 'lucide-vue-next';

// Name the component so it can reference itself recursively in the template.
defineOptions({ name: 'StepRenderer' });

// Define props - this component will render a list of steps passed to it.
const props = defineProps({
    steps: { type: Array, required: true },
    parentStep: { type: Object, required: false, default: null },
    branch: { type: String, required: false, default: null }, // 'yes' | 'no' or null for top-level
});

const store = useWorkflowStore();
const addMenuIndex = ref(null);

const getBlockComponent = (step) => {
    switch (step.step_type?.toUpperCase()) {
        case 'TRIGGER': return TriggerBlock;
        case 'CONDITION': return ConditionBlock;
        case 'ACTION': return ActionBlock; // <-- Add case for the new Action type
        case 'AI_PROMPT':
        case 'ACTION_AI_PROMPT':
        default: return AiPromptBlock;
    }
};

const onSelect = (step) => store.selectStep(step);
const toggleAddMenu = (index) => {
    addMenuIndex.value = addMenuIndex.value === index ? null : index;
};
const addStep = (type, index) => {
    // Pass the specific array (the parent) to the store action, along with parentStep and branch context.
    store.addStep({ type, insertAfter: index, parentArray: props.steps, parentStep: props.parentStep, branch: props.branch });
    addMenuIndex.value = null;
};
const onDragEnd = () => {
    console.log('Workflow structure changed');
    // Future: store.persistWorkflowStructure();
};
</script>

<template>
    <draggable
        :list="steps"
        item-key="id"
        group="workflow"
        handle=".drag-handle"
        :animation="200"
        @end="onDragEnd"
        class="space-y-4"
        :class="{ 'min-h-[80px] bg-gray-100/50 p-2 rounded-lg border-2 border-dashed': !steps?.length }"
    >
        <template #item="{ element: step, index }">
            <div class="step-container">
                <component :is="getBlockComponent(step)" :step="step" @select="onSelect" />

                <div v-if="step.step_type === 'CONDITION'" class="relative flex justify-center mt-4">
                    <div class="absolute -top-4 left-1/2 w-0.5 h-4 bg-gray-300"></div>
                    <div class="absolute top-0 left-1/4 right-1/4 h-0.5 bg-gray-300"></div>
                    <div class="absolute top-0 left-1/4 w-0.5 h-4 bg-gray-300"></div>
                    <div class="absolute top-0 right-1/4 w-0.5 h-4 bg-gray-300"></div>
                    <div class="w-1/2 pr-2">
                        <div class="text-center text-sm font-semibold text-green-600 mb-2">YES</div>
                        <StepRenderer :steps="step.yes_steps" :parentStep="step" branch="yes" />
                    </div>
                    <div class="w-1/2 pl-2">
                        <div class="text-center text-sm font-semibold text-red-600 mb-2">NO</div>
                        <StepRenderer :steps="step.no_steps" :parentStep="step" branch="no" />
                    </div>
                </div>

                <div v-else class="h-12 w-full flex items-center justify-center relative">
                    <div class="h-full w-0.5 bg-gray-300"></div>
                    <button @click="toggleAddMenu(index)" class="absolute z-10 w-8 h-8 flex items-center justify-center bg-white border-2 rounded-full text-gray-400 hover:text-blue-600 hover:border-blue-500 transition-all">
                        <Plus class="w-5 h-5" />
                    </button>
                    <div v-if="addMenuIndex === index" class="absolute z-20 top-10 p-2 bg-white border rounded-lg shadow-lg min-w-[180px]">
                        <div class="text-xs text-gray-400 px-2 pb-1 border-b mb-1">Standard</div>
                        <!-- Add Action to the menu -->
                        <button @click="addStep('ACTION', index)" class="w-full text-left px-2 py-1 text-sm rounded-md text-gray-700 hover:bg-gray-100">Action</button>
                        <button @click="addStep('CONDITION', index)" class="w-full text-left px-2 py-1 text-sm rounded-md text-gray-700 hover:bg-gray-100">Condition</button>
                        <div class="text-xs text-gray-400 px-2 pb-1 border-b my-1">Advanced</div>
                        <button @click="addStep('AI_PROMPT', index)" class="w-full text-left px-2 py-1 text-sm rounded-md text-gray-700 hover:bg-gray-100">AI Prompt</button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Footer for adding steps to empty branches -->
        <template #footer>
            <div v-if="!steps?.length" class="text-center">
                <button @click="toggleAddMenu(-1)" class="w-full text-center py-4 text-xs text-gray-500 hover:text-blue-600">
                    <Plus class="inline w-3 h-3" /> Add Step
                </button>
                <div v-if="addMenuIndex === -1" class="relative mt-2 z-20 inline-block">
                    <div class="absolute p-2 bg-white border rounded-lg shadow-lg min-w-[180px] left-1/2 -translate-x-1/2">
                        <div class="text-xs text-gray-400 px-2 pb-1 border-b mb-1">Standard</div>
                        <button @click="addStep('ACTION', -1)" class="w-full text-left px-2 py-1 text-sm rounded-md text-gray-700 hover:bg-gray-100">Action</button>
                        <button @click="addStep('CONDITION', -1)" class="w-full text-left px-2 py-1 text-sm rounded-md text-gray-700 hover:bg-gray-100">Condition</button>
                        <div class="text-xs text-gray-400 px-2 pb-1 border-b my-1">Advanced</div>
                        <button @click="addStep('AI_PROMPT', -1)" class="w-full text-left px-2 py-1 text-sm rounded-md text-gray-700 hover:bg-gray-100">AI Prompt</button>
                    </div>
                </div>
            </div>
        </template>
    </draggable>
</template>
