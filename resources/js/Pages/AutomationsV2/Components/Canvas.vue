<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { VueFlow, useVueFlow, MarkerType } from '@vue-flow/core';
import { Background, BackgroundVariant } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { MiniMap } from '@vue-flow/minimap';
import CustomNode from './CustomNode.vue';
import { useAutomationsV2Store } from '../Store/storeV2';


import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

const store = useAutomationsV2Store();

const {
    nodes, edges,
    setNodes, setEdges,
    onConnect, addEdges, removeEdges,
    onNodeDragStop,
    onEdgesChange,
    fitView,
    dimensions, viewport, project,
} = useVueFlow('v2-canvas');

const nodeTypes = { custom: CustomNode };

// ===== Build VueFlow elements from the step tree =====
const NODE_WIDTH  = 280;
const NODE_HEIGHT = 130;
const V_GAP       = 80;
const H_GAP       = 80;

function buildElements(steps, parentNodeId = null, sourceHandle = null, startX = 0, startY = 0, stepsAbove = []) {
    let localNodes = [];
    let localEdges = [];
    let currentY   = startY;
    let maxY       = startY;

    steps.forEach((step, idx) => {
        const nodeId    = `step-${step.id}`;
        const savedPos  = step.step_config?.position;
        const pos       = savedPos ? { x: savedPos.x, y: savedPos.y } : { x: startX, y: currentY };

        const stepsBeforeThis = [...stepsAbove, ...steps.slice(0, idx)];

        localNodes.push({
            id:       nodeId,
            type:     'custom',
            position: pos,
            data: {
                step,
                allStepsBefore: stepsBeforeThis,
            },
        });

        const isUnlinked = step.step_config?.is_unlinked === true;

        // Edge to connect previous node or parent branch
        if (idx === 0 && parentNodeId) {
            if (!isUnlinked) {
                localEdges.push({
                    id:           `e-${parentNodeId}-${sourceHandle || 'src'}-${nodeId}`,
                    source:       parentNodeId,
                    sourceHandle: sourceHandle,
                    target:       nodeId,
                    targetHandle: 'target',
                    animated:     true,
                    markerEnd:    { type: MarkerType.ArrowClosed, color: '#6366f1' },
                    style:        { stroke: '#6366f1', strokeWidth: 2 },
                    label:        sourceHandle === 'yes' ? 'TRUE' : sourceHandle === 'no' ? 'FALSE' : sourceHandle === 'children' ? 'LOOP' : '',
                    labelStyle:   { fontSize: 10, fontWeight: 700, fill: '#6366f1' },
                    labelBgStyle: { fill: '#ede9fe', rx: 4 },
                });
            }
        } else if (idx > 0) {
            const prevNode = steps[idx - 1];
            const prevHasSource = !['CONDITION', 'FOR_EACH'].includes(prevNode.step_type);

            if (!isUnlinked && prevHasSource) {
                const prevNodeId = `step-${prevNode.id}`;
                localEdges.push({
                    id:       `e-${prevNodeId}-${nodeId}`,
                    source:   prevNodeId,
                    sourceHandle: 'source',
                    target:   nodeId,
                    targetHandle: 'target',
                    animated: true,
                    markerEnd: { type: MarkerType.ArrowClosed, color: '#94a3b8' },
                    style:    { stroke: '#94a3b8', strokeWidth: 2 },
                });
            }
        }

        currentY += NODE_HEIGHT + V_GAP;

        // Recurse into branches
        if (step.step_type === 'CONDITION') {
            const branchY = currentY;
            const branchAbove = [...stepsBeforeThis, step];

            const left  = buildElements(step.if_true  || [], nodeId, 'yes',      startX - (NODE_WIDTH + H_GAP), branchY, branchAbove);
            const right = buildElements(step.if_false || [], nodeId, 'no',       startX + (NODE_WIDTH + H_GAP), branchY, branchAbove);

            localNodes.push(...left.nodes, ...right.nodes);
            localEdges.push(...left.edges, ...right.edges);
            currentY = Math.max(branchY, left.maxY, right.maxY);
        }

        if (step.step_type === 'FOR_EACH') {
            const loopY    = currentY;
            const loopAbove = [...stepsBeforeThis, step];
            const inner    = buildElements(step.children || [], nodeId, 'children', startX, loopY, loopAbove);
            localNodes.push(...inner.nodes);
            localEdges.push(...inner.edges);
            currentY = Math.max(loopY, inner.maxY);
        }

        maxY = Math.max(maxY, currentY);
    });

    return { nodes: localNodes, edges: localEdges, maxY };
}

// Re-render whenever steps tree changes
watch(
    () => store.workflowSteps,
    (steps) => {
        const { nodes: n, edges: e } = buildElements(steps, null, null, 0, 0, []);
        setNodes(n);
        setEdges(e);
    },
    { deep: true, immediate: true }
);

// Persist drag-moved positions back to step_config.position
onNodeDragStop(({ node }) => {
    const id = node.id.replace('step-', '');
    const step = findStep(store.workflowSteps, id);
    if (!step) return;
    store.updateStep(id, {
        ...step,
        step_config: { ...step.step_config, position: node.position },
    });
});

// ===== Re-parenting via drag-connect =====
onConnect(params => {
    const srcId    = params.source.replace('step-', '');
    const tgtId    = params.target.replace('step-', '');
    if (srcId === tgtId) return;

    const branch   = params.sourceHandle?.replace('source', '') || null; // 'yes' / 'no' / 'children' / null

    // Remove VueFlow's phantom edge first
    const phantomId = `vueflow__edge-${params.source}${params.sourceHandle ?? ''}-${params.target}${params.targetHandle ?? ''}`;
    removeEdges([phantomId]);

    // Detach target from its current location
    const { steps: without, removed } = removeFromTree([...store.workflowSteps], tgtId);
    if (!removed) return;

    if (removed.step_config) {
        delete removed.step_config.is_unlinked;
    }

    let newSteps;
    if (branch && branch !== '') {
        // Nest into branch (yes/no/children)
        newSteps = insertNested(without, srcId, branch, removed);
    } else {
        // Insert immediately after source in the flat list
        newSteps = insertAfter(without, srcId, removed);
    }
    store.setSteps(newSteps);
});

// Edge deletions → detach node, promote to root
onEdgesChange(changes => {
    const removals = changes.filter(c => c.type === 'remove');
    if (!removals.length) return;
    let steps = [...store.workflowSteps];
    let changed = false;
    removals.forEach(r => {
        const edge = r.edge || edges.value.find(ed => String(ed.id) === String(r.id));
        if (!edge) return;
        const tgtId = (edge.target || '').replace('step-', '');
        if (!tgtId) return;
        const { steps: without, removed } = removeFromTree(steps, tgtId);
        if (removed) { 
            removed.step_config = removed.step_config || {};
            removed.step_config.is_unlinked = true;
            steps = [...without, removed]; 
            changed = true; 
        }
    });
    if (changed) store.setSteps(steps);
});

onMounted(() => setTimeout(() => fitView({ padding: 0.2 }), 200));

// ===== Drag & Drop from Library =====
function onDragOver(event) {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onDrop(event) {
    event.preventDefault();
    const type = event.dataTransfer?.getData('application/vueflow');
    if (!type) return;

    // Get the rect of the wrapper div
    const rect = event.currentTarget.getBoundingClientRect();
    
    // Project the coordinates
    const position = project({
        x: event.clientX - rect.left,
        y: event.clientY - rect.top,
    });

    // STRICT RULE: Dropped blocks are always unlinked
    store.addStep(type, null, null, position);
}

// ===== Helpers =====
function findStep(steps, id) {
    for (const s of steps) {
        if (String(s.id) === String(id)) return s;
        let f = null;
        if (s.if_true)  f = findStep(s.if_true,  id);
        if (!f && s.if_false)  f = findStep(s.if_false,  id);
        if (!f && s.children)  f = findStep(s.children,  id);
        if (f) return f;
    }
    return null;
}

function removeFromTree(steps, id) {
    let removed = null;
    const newSteps = steps
        .filter(s => { if (String(s.id) === String(id)) { removed = s; return false; } return true; })
        .map(s => {
            const c = { ...s };
            if (c.if_true)  { const r = removeFromTree(c.if_true,  id); c.if_true  = r.steps; if (r.removed) removed = r.removed; }
            if (c.if_false) { const r = removeFromTree(c.if_false, id); c.if_false = r.steps; if (r.removed) removed = r.removed; }
            if (c.children) { const r = removeFromTree(c.children, id); c.children = r.steps; if (r.removed) removed = r.removed; }
            return c;
        });
    return { steps: newSteps, removed };
}

function insertNested(steps, parentId, branch, step) {
    return steps.map(s => {
        if (String(s.id) === String(parentId)) {
            const c = { ...s };
            if (branch === 'yes')      c.if_true  = [...(c.if_true  || []), step];
            else if (branch === 'no')  c.if_false = [...(c.if_false || []), step];
            else                       c.children = [...(c.children || []), step];
            return c;
        }
        const c = { ...s };
        if (c.if_true)  c.if_true  = insertNested(c.if_true,  parentId, branch, step);
        if (c.if_false) c.if_false = insertNested(c.if_false, parentId, branch, step);
        if (c.children) c.children = insertNested(c.children, parentId, branch, step);
        return c;
    });
}

function insertAfter(steps, afterId, step) {
    const idx = steps.findIndex(s => String(s.id) === String(afterId));
    if (idx !== -1) {
        const ns = [...steps];
        ns.splice(idx + 1, 0, step);
        return ns;
    }
    return steps.map(s => {
        const c = { ...s };
        if (c.if_true)  c.if_true  = insertAfter(c.if_true,  afterId, step);
        if (c.if_false) c.if_false = insertAfter(c.if_false, afterId, step);
        if (c.children) c.children = insertAfter(c.children, afterId, step);
        return c;
    });
}
</script>

<template>
    <div class="h-full w-full relative" @dragover="onDragOver" @drop="onDrop">
        <VueFlow
            id="v2-canvas"
            :node-types="nodeTypes"
            :nodes="nodes"
            :edges="edges"
            :min-zoom="0.15"
            :max-zoom="3"
            fit-view-on-init
            class="bg-gray-50"
        >
            <Background :variant="BackgroundVariant.Dots" :gap="20" :size="1" pattern-color="#cbd5e1" />
            <Controls position="bottom-left" />
            <MiniMap
                position="bottom-right"
                :node-color="(n) => {
                    const t = n.data?.step?.step_type;
                    if (t === 'TRIGGER' || t === 'SCHEDULE_TRIGGER') return '#8b5cf6';
                    if (t === 'CONDITION') return '#f59e0b';
                    if (t === 'ACTION') return '#10b981';
                    if (t === 'FOR_EACH') return '#6366f1';
                    if (t === 'AI_PROMPT') return '#ec4899';
                    return '#64748b';
                }"
                class="!bg-white !rounded-xl !shadow-lg !border !border-gray-200"
            />
        </VueFlow>
    </div>
</template>

<style>
/* Override Vue Flow node style for our custom transparent wrapper */
.vue-flow__node-custom { padding: 0 !important; border: none !important; background: transparent !important; box-shadow: none !important; }
.vue-flow__edge-path { stroke-width: 2; }
.vue-flow__controls { background: white; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.1); border: 1px solid #e2e8f0; }
.vue-flow__controls-button { border: none; }
.vue-flow__minimap { border-radius: 12px; overflow: hidden; }
</style>
