<script setup>
import { ref, watch, onMounted, computed, provide } from 'vue';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { MiniMap } from '@vue-flow/minimap';
import WorkflowNode from './WorkflowNode.vue';
import AddStepButton from './Steps/AddStepButton.vue';
import { useWorkflowStore } from '../Store/workflowStore';

// Import Vue Flow styles
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

const props = defineProps({
    steps: { type: Array, required: true },
    fullContextSteps: { type: Array, default: () => [] },
    loopContextSchema: { type: Object, default: null },
    allowTrigger: { type: Boolean, default: true },
});

const emit = defineEmits(['update:steps', 'add-trigger']);

const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

const { onConnect, addEdges, removeEdges, nodes, edges, setNodes, setEdges, fitView, onNodeDragStop, findNode } = useVueFlow();

const nodeTypes = {
    workflow: WorkflowNode,
};

// --- Re-parenting Logic for onConnect ---

function removeStepFromHierarchy(steps, targetId) {
    let removedStep = null;
    const newSteps = steps.filter(s => {
        if (String(s.id) === String(targetId)) {
            removedStep = s;
            return false;
        }
        return true;
    }).map(s => {
        const copy = { ...s };
        if (copy.if_true) {
            const result = removeStepFromHierarchy(copy.if_true, targetId);
            copy.if_true = result.steps;
            if (result.removed) removedStep = result.removed;
        }
        if (copy.if_false) {
            const result = removeStepFromHierarchy(copy.if_false, targetId);
            copy.if_false = result.steps;
            if (result.removed) removedStep = result.removed;
        }
        if (copy.children) {
            const result = removeStepFromHierarchy(copy.children, targetId);
            copy.children = result.steps;
            if (result.removed) removedStep = result.removed;
        }
        return copy;
    });
    return { steps: newSteps, removed: removedStep };
}

function addStepToHierarchy(steps, parentId, branch, stepToAdd) {
    return steps.map(s => {
        if (String(s.id) === String(parentId)) {
            const copy = { ...s };
            if (branch === 'yes') copy.if_true = [...(copy.if_true || []), stepToAdd];
            else if (branch === 'no') copy.if_false = [...(copy.if_false || []), stepToAdd];
            else if (branch === 'children') copy.children = [...(copy.children || []), stepToAdd];
            return copy;
        }
        const copy = { ...s };
        if (copy.if_true) copy.if_true = addStepToHierarchy(copy.if_true, parentId, branch, stepToAdd);
        if (copy.if_false) copy.if_false = addStepToHierarchy(copy.if_false, parentId, branch, stepToAdd);
        if (copy.children) copy.children = addStepToHierarchy(copy.children, parentId, branch, stepToAdd);
        return copy;
    });
}

onConnect((params) => {
    const sourceId = params.source.replace('step-', '');
    const targetId = params.target.replace('step-', '');

    // Guard: ignore self-connections
    if (sourceId === targetId) return;

    // The handle the user dragged from: 'yes', 'no', 'children', or null (plain source)
    const branch = params.sourceHandle ?? null;

    // VueFlow automatically adds this edge internally before our callback fires.
    // Since our watch on props.steps fully rebuilds edges from the hierarchy,
    // we must remove this phantom edge to avoid duplicates / stale state.
    const phantomEdgeId = `vueflow__edge-${params.source}${params.sourceHandle ?? ''}-${params.target}${params.targetHandle ?? ''}`;
    removeEdges([phantomEdgeId]);

    const { steps: stepsWithoutTarget, removed } = removeStepFromHierarchy([...props.steps], targetId);
    if (!removed) return;

    let newSteps;
    if (branch) {
        // Dragged from a labelled handle (YES / NO / LOOP) — nest the step inside that branch
        newSteps = addStepToHierarchy(stepsWithoutTarget, sourceId, branch, removed);
    } else {
        // Dragged from a plain source handle — insert the step immediately after the source
        const insertAfter = (steps) => {
            const idx = steps.findIndex(s => String(s.id) === String(sourceId));
            if (idx !== -1) {
                const ns = [...steps];
                ns.splice(idx + 1, 0, removed);
                return ns;
            }
            return steps.map(s => {
                const copy = { ...s };
                if (copy.if_true) copy.if_true = insertAfter(copy.if_true);
                if (copy.if_false) copy.if_false = insertAfter(copy.if_false);
                if (copy.children) copy.children = insertAfter(copy.children);
                return copy;
            });
        };
        newSteps = insertAfter(stepsWithoutTarget);
    }
    emit('update:steps', newSteps);
});

onNodeDragStop(({ node }) => {
    const stepId = String(node.data.step.id);
    const newPosition = node.position;
    
    const updatePosition = (steps) => {
        return steps.map(s => {
            if (String(s.id) === stepId) {
                return { ...s, step_config: { ...(s.step_config || {}), position: newPosition } };
            }
            const copy = { ...s };
            if (copy.if_true) copy.if_true = updatePosition(copy.if_true);
            if (copy.if_false) copy.if_false = updatePosition(copy.if_false);
            if (copy.children) copy.children = updatePosition(copy.children);
            return copy;
        });
    };
    emit('update:steps', updatePosition([...props.steps]));
});

// --- Helpers ---

function getLoopContextSchema(forEachStep, allStepsBefore) {
    const sourcePath = forEachStep.step_config?.sourceArray;
    if (!sourcePath) return null;
    const match = sourcePath.match(/{{step_(\w+)\.(.+)}}/);
    if (!match) return null;
    const sourceStepId = match[1];
    const sourceFieldName = match[2];
    const sourceStep = allStepsBefore.find(s => String(s.id) === String(sourceStepId));
    const isArrayOfObjects = (field) => {
        const t = String(field?.type || '').toLowerCase();
        const it = String(field?.itemType || '').toLowerCase();
        return t === 'array of objects' || (t === 'array' && it === 'object');
    };
    if (sourceStep?.step_type === 'AI_PROMPT' || (sourceStep?.step_type === 'ACTION' && sourceStep?.step_config?.action_type === 'FETCH_API_DATA')) {
        const cleanFieldName = sourceFieldName.replace(/^parsed\./, '');
        const sourceField = sourceStep.step_config?.responseStructure?.find(f => f.name === cleanFieldName);
        if (!isArrayOfObjects(sourceField)) return null;
        return { name: 'Loop Item', columns: sourceField.schema || [] };
    }
    if (sourceStep?.step_type === 'FETCH_RECORDS' && sourceFieldName === 'records') {
        const modelName = sourceStep.step_config?.model;
        if (!modelName) return null;
        const model = automationSchema.value.find(m => m.name === modelName);
        if (!model) return null;
        const cols = (model.columns || []).map(col => typeof col === 'string' ? { name: col } : col);
        return { name: 'Loop Item', columns: cols };
    }
    return null;
}

function createElements(steps, parentId = null, branch = null, depth = 0, offset = { x: 0, y: 0 }, allStepsBefore = [], currentLoopSchema = null) {
    let localNodes = [];
    let localEdges = [];
    let currentY = offset.y;

    steps.forEach((step, index) => {
        const nodeId = `step-${step.id}`;
        const nodePosition = (step.step_config && step.step_config.position) ? step.step_config.position : { x: offset.x, y: currentY };
        const currentStepsBefore = [...allStepsBefore, ...steps.slice(0, index)];

        localNodes.push({
            id: nodeId,
            type: 'workflow',
            position: nodePosition,
            data: {
                step,
                allStepsBefore: [...props.fullContextSteps, ...currentStepsBefore],
                loopContextSchema: currentLoopSchema || props.loopContextSchema,
                onUpdate: (newData) => handleUpdateStep(step.id, newData),
                onDelete: () => handleDeleteStep(step.id),
                onAddStep: (type, branch) => handleAddStep(type, step.id, branch),
            },
        });

        if (index > 0) {
            localEdges.push({ id: `e-step-${steps[index - 1].id}-${step.id}`, source: `step-${steps[index - 1].id}`, target: nodeId, animated: true });
        } else if (parentId) {
            localEdges.push({ id: `e-${parentId}-${nodeId}`, source: parentId, target: nodeId, sourceHandle: branch, animated: true, label: branch ? branch.toUpperCase() : '' });
        }

        currentY += 450;

        if (step.step_type === 'CONDITION') {
            const yes = createElements(step.if_true || [], nodeId, 'yes', depth + 1, { x: nodePosition.x - 300, y: currentY }, [...currentStepsBefore, step], currentLoopSchema || props.loopContextSchema);
            const no = createElements(step.if_false || [], nodeId, 'no', depth + 1, { x: nodePosition.x + 300, y: currentY }, [...currentStepsBefore, step], currentLoopSchema || props.loopContextSchema);
            localNodes.push(...yes.nodes, ...no.nodes);
            localEdges.push(...yes.edges, ...no.edges);
            currentY = Math.max(currentY, yes.maxY, no.maxY);
        }

        if (step.step_type === 'FOR_EACH') {
            const schema = getLoopContextSchema(step, [...props.fullContextSteps, ...currentStepsBefore]);
            const children = createElements(step.children || [], nodeId, 'children', depth + 1, { x: nodePosition.x, y: currentY }, [...currentStepsBefore, step], schema);
            localNodes.push(...children.nodes);
            localEdges.push(...children.edges);
            currentY = Math.max(currentY, children.maxY);
        }
    });

    return { nodes: localNodes, edges: localEdges, maxY: currentY };
}

watch(() => props.steps, (newSteps) => {
    const { nodes: newNodes, edges: newEdges } = createElements(newSteps);
    setNodes(newNodes);
    setEdges(newEdges);
}, { deep: true, immediate: true });

// --- CRUD ---

function findAndReplaceStep(steps, targetId, newData) {
    return steps.map(s => {
        if (s.id === targetId) return newData;
        const copy = { ...s };
        if (copy.if_true) copy.if_true = findAndReplaceStep(copy.if_true, targetId, newData);
        if (copy.if_false) copy.if_false = findAndReplaceStep(copy.if_false, targetId, newData);
        if (copy.children) copy.children = findAndReplaceStep(copy.children, targetId, newData);
        return copy;
    });
}

function handleUpdateStep(id, newData) {
    emit('update:steps', findAndReplaceStep([...props.steps], id, newData));
}

function findAndDeleteStep(steps, targetId) {
    return steps.filter(s => s.id !== targetId).map(s => {
        const copy = { ...s };
        if (copy.if_true) copy.if_true = findAndDeleteStep(copy.if_true, targetId);
        if (copy.if_false) copy.if_false = findAndDeleteStep(copy.if_false, targetId);
        if (copy.children) copy.children = findAndDeleteStep(copy.children, targetId);
        return copy;
    });
}

function handleDeleteStep(id) {
    emit('update:steps', findAndDeleteStep([...props.steps], id));
}

function handleAddStep(type, parentId = null, branch = null) {
    const newStep = { id: `temp_${Date.now()}`, step_type: type, name: `New ${type.replace('_', ' ')} Step`, step_config: {} };
    if (type === 'CONDITION') { newStep.if_true = []; newStep.if_false = []; }
    if (type === 'FOR_EACH') { newStep.children = []; }

    if (!parentId) {
        emit('update:steps', [...props.steps, newStep]);
    } else {
        const addNested = (steps) => {
            return steps.map(s => {
                if (s.id === parentId) {
                    const copy = { ...s };
                    if (branch === 'yes') copy.if_true = [...(copy.if_true || []), newStep];
                    else if (branch === 'no') copy.if_false = [...(copy.if_false || []), newStep];
                    else if (branch === 'children') copy.children = [...(copy.children || []), newStep];
                    return copy;
                }
                const copy = { ...s };
                if (copy.if_true) copy.if_true = addNested(copy.if_true);
                if (copy.if_false) copy.if_false = addNested(copy.if_false);
                if (copy.children) copy.children = addNested(copy.children);
                return copy;
            });
        };
        emit('update:steps', addNested([...props.steps]));
    }
}

onMounted(() => { setTimeout(() => fitView(), 100); });
</script>

<template>
    <div class="h-full w-full border-2 border-dashed border-gray-300 rounded-xl overflow-hidden bg-gray-50 relative">
        <VueFlow :nodes="nodes" :edges="edges" :node-types="nodeTypes" :min-zoom="0.2" :max-zoom="4" fit-view-on-init>
            <Background pattern-color="#aaa" :gap="16" />
            <Controls />
            <div class="absolute top-4 left-4 z-50 flex items-center space-x-4 bg-white p-2 rounded-full shadow-lg border">
                <span class="text-xs font-bold text-gray-500 ml-4">ADD STEP:</span>
                <AddStepButton @select="(type) => handleAddStep(type)" />
            </div>
            <div v-if="steps.length === 0" class="absolute inset-0 flex items-center justify-center p-8 z-10 pointer-events-none">
                <div class="text-center p-8 border-2 border-dashed rounded-lg bg-white/80 backdrop-blur-sm pointer-events-auto">
                    <template v-if="allowTrigger">
                        <h3 class="text-lg font-semibold text-gray-700">Start your Automation</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-4">Every workflow starts with a trigger. How should this one begin?</p>
                        <button @click="$emit('add-trigger')" class="px-6 py-2 text-sm font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 shadow-md transition-all">
                            Add Trigger
                        </button>
                    </template>
                </div>
            </div>
        </VueFlow>
    </div>
</template>

<style>
.vue-flow__node-workflow { @apply p-0 border-none bg-transparent; }
.vue-flow__handle { @apply !ring-2 !ring-white; }
.vue-flow__edge-path { @apply stroke-indigo-400 stroke-2; }
.vue-flow__controls { @apply !bg-white !border-gray-200 !shadow-md; }
</style>
