<script setup>
import { computed, ref, h } from 'vue';
import draggable from 'vuedraggable';
import { useWorkflowStore } from '../Store/workflowStore';

// Blocks
import TriggerBlock from './Blocks/TriggerBlock.vue';
import AiPromptBlock from './Blocks/AiPromptBlock.vue';
import ConditionBlock from './Blocks/ConditionBlock.vue';
import { Plus, Loader2 } from 'lucide-vue-next';

const store = useWorkflowStore();
const activeWorkflow = computed(() => store.activeWorkflow);

// This recursive component renders a sequence of steps.
const StepRenderer = {
    props: ['steps'],
    setup(props) {
        const addMenuIndex = ref(null);

        const onSelect = (step) => store.selectStep(step);
        const toggleAddMenu = (index) => {
            addMenuIndex.value = addMenuIndex.value === index ? null : index;
        };
        const addStep = (type, index) => {
            store.addStep({ type, insertAfter: index });
            addMenuIndex.value = null;
        };
        const onDragEnd = () => {
            console.log('Workflow structure changed', store.activeWorkflow);
            // Future: store.persistWorkflowStructure();
        };
        const getBlockComponent = (step) => {
            switch (step.step_type?.toUpperCase()) {
                case 'TRIGGER': return TriggerBlock;
                case 'CONDITION': return ConditionBlock;
                case 'AI_PROMPT': default: return AiPromptBlock;
            }
        };

        return () => h(draggable, {
            list: props.steps,
            itemKey: 'id',
            group: 'workflow',
            handle: '.drag-handle',
            animation: 200,
            class: ['space-y-4', { 'min-h-[80px] bg-gray-100/50 p-2 rounded-lg border-2 border-dashed': !props.steps?.length }],
            onEnd: onDragEnd,
        }, {
            item: ({ element: step, index }) => h('div', { class: 'step-container' }, [
                h(getBlockComponent(step), { step, onSelect }),
                step.step_type === 'CONDITION'
                    ? h('div', { class: 'relative flex justify-center mt-4 pl-4' }, [
                        h('div', { class: 'absolute -top-4 left-1/2 w-0.5 h-4 bg-gray-300' }),
                        h('div', { class: 'absolute top-0 left-[25%] right-[25%] h-0.5 bg-gray-300' }),
                        h('div', { class: 'absolute top-0 left-1/4 w-0.5 h-4 bg-gray-300' }),
                        h('div', { class: 'absolute top-0 right-3/4 w-0.5 h-4 bg-gray-300' }),
                        h('div', { class: 'w-1/2 pr-2' }, [
                            h('div', { class: 'text-center text-sm font-semibold text-green-600 mb-2' }, 'YES'),
                            h(StepRenderer, { steps: step.yes_steps || [] })
                        ]),
                        h('div', { class: 'w-1/2 pl-2' }, [
                            h('div', { class: 'text-center text-sm font-semibold text-red-600 mb-2' }, 'NO'),
                            h(StepRenderer, { steps: step.no_steps || [] })
                        ])
                    ])
                    : h('div', { class: 'h-12 w-full flex items-center justify-center relative' }, [
                        h('div', { class: 'h-full w-0.5 bg-gray-300' }),
                        h('button', {
                            class: 'absolute z-10 w-8 h-8 flex items-center justify-center bg-white border-2 rounded-full text-gray-400 hover:text-blue-600 hover:border-blue-500 transition-all',
                            onClick: () => toggleAddMenu(index)
                        }, [ h(Plus, { class: 'w-5 h-5' }) ]),
                        addMenuIndex.value === index ? h('div', { class: 'absolute z-20 top-10 flex gap-2 p-2 bg-white border rounded-lg shadow-lg' }, [
                            h('button', { class: 'px-3 py-1 text-xs font-semibold rounded-md bg-purple-100 text-purple-700 hover:bg-purple-200', onClick: () => addStep('AI_PROMPT', index) }, 'AI Prompt'),
                            h('button', { class: 'px-3 py-1 text-xs font-semibold rounded-md bg-green-100 text-green-700 hover:bg-green-200', onClick: () => addStep('CONDITION', index) }, 'Condition')
                        ]) : null
                    ])
            ]),
            footer: () => !props.steps?.length ? h('p', { class: 'text-xs text-center text-gray-400 p-4' }, 'Drop steps here') : null
        });
    }
};
</script>

<template>
    <div class="h-full overflow-y-auto bg-gray-50 p-8">
        <div v-if="store.isLoading" class="h-full flex items-center justify-center text-gray-500">
            <Loader2 class="w-6 h-6 animate-spin mr-2" />
            <p>Loading Workflow...</p>
        </div>

        <div v-else-if="!activeWorkflow" class="h-full flex items-center justify-center text-gray-500">
            <p>Select a workflow from the left to begin building.</p>
        </div>

        <div v-else class="max-w-md mx-auto">
            <!-- We call our recursive renderer for the top-level steps -->
            <StepRenderer :steps="activeWorkflow.steps" />

            <!-- Add first step button -->
            <div v-if="!activeWorkflow.steps || activeWorkflow.steps.length === 0" class="text-center p-6 border-2 border-dashed rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700">Empty Workflow</h3>
                <p class="text-xs text-gray-500 mt-1 mb-3">Add the first step to get started.</p>
                <div class="flex items-center justify-center gap-2">
                    <button @click="store.addStep({ type: 'TRIGGER', insertAfter: -1 })" class="px-3 py-1 text-xs font-semibold rounded-md bg-amber-100 text-amber-700 hover:bg-amber-200">Add Trigger</button>
                </div>
            </div>
        </div>
    </div>
</template>

