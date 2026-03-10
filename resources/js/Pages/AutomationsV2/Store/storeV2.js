import { defineStore } from 'pinia';
import * as api from '../Api/automationApi';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

// ==================== Helpers ====================

const unwrap = (r) => (r && r.data) ? r.data : r;

// Flatten a hierarchy of steps to a flat list (for saving to backend)
export function flattenSteps(steps, parentId = null, branch = null) {
    let flat = [];
    steps.forEach((step, index) => {
        const copy = JSON.parse(JSON.stringify(step));
        const if_true = copy.if_true || [];
        const if_false = copy.if_false || [];
        const children = copy.children || [];
        delete copy.if_true;
        delete copy.if_false;
        delete copy.children;

        copy.step_order = index + 1;
        copy.step_config = copy.step_config || {};
        copy.step_config._parent_id = parentId;
        copy.step_config._branch = branch;
        if (copy.delay_minutes == null) copy.delay_minutes = 0;

        // Convert SCHEDULE_TRIGGER → TRIGGER for backend
        if (copy.step_type === 'SCHEDULE_TRIGGER') {
            copy.step_type = 'TRIGGER';
            copy.step_config.trigger_event = 'schedule.run';
        }

        flat.push(copy);

        if (step.step_type === 'CONDITION') {
            flat = [...flat,
            ...flattenSteps(if_true, step.id, 'yes'),
            ...flattenSteps(if_false, step.id, 'no'),
            ];
        }
        if (step.step_type === 'FOR_EACH') {
            flat = [...flat, ...flattenSteps(children, step.id, null)];
        }
    });
    return flat;
}

// Rebuild nested tree from flat DB rows using _parent_id / _branch metadata
export function rebuildTree(flatSteps) {
    const steps = JSON.parse(JSON.stringify(flatSteps));

    // Ensure container placeholders
    steps.forEach(s => {
        if (s.step_type === 'CONDITION' || s.step_type === 'TRIGGER' && s.step_config?.trigger_event === 'schedule.run') {
            if (!Array.isArray(s.if_true)) s.if_true = [];
            if (!Array.isArray(s.if_false)) s.if_false = [];
        }
        if (s.step_type === 'FOR_EACH') {
            if (!Array.isArray(s.children)) s.children = [];
        }
        s.step_config = s.step_config || {};
    });

    const byId = new Map(steps.map(s => [String(s.id), s]));
    const topLevel = [];

    steps.forEach(s => {
        const parentId = s.step_config?._parent_id;
        const branch = s.step_config?._branch;

        // Show SCHEDULE_TRIGGER nicely in UI
        if (s.step_type === 'TRIGGER' && s.step_config?.trigger_event === 'schedule.run') {
            s.step_type = 'SCHEDULE_TRIGGER';
        }

        if (parentId && byId.has(String(parentId))) {
            const parent = byId.get(String(parentId));
            if (parent.step_type === 'CONDITION') {
                const branchLow = String(branch || 'yes').toLowerCase();
                if (branchLow === 'no') { parent.if_false = parent.if_false || []; parent.if_false.push(s); }
                else { parent.if_true = parent.if_true || []; parent.if_true.push(s); }
                return;
            }
            if (parent.step_type === 'FOR_EACH') {
                parent.children = parent.children || [];
                parent.children.push(s);
                return;
            }
        }
        topLevel.push(s);
    });

    const sortRecursive = arr => {
        arr.sort((a, b) => (a.step_order ?? 0) - (b.step_order ?? 0));
        arr.forEach(s => {
            if (s.if_true) sortRecursive(s.if_true);
            if (s.if_false) sortRecursive(s.if_false);
            if (s.children) sortRecursive(s.children);
        });
    };
    sortRecursive(topLevel);
    return topLevel;
}

// ==================== Store ====================

export const useAutomationsV2Store = defineStore('automationsV2', {
    state: () => ({
        // Hub
        workflows: [],
        isLoadingWorkflows: false,
        // Builder
        activeWorkflow: null,
        workflowSteps: [],   // nested tree used by the builder
        workflowName: 'Untitled Automation',
        isSaving: false,
        isLoadingWorkflow: false,
        // Schema
        automationSchema: [],
        campaigns: [],
        morphMap: [],
        prompts: [],
        isLoadingSchema: false,
        // Canvas selection
        selectedNodeId: null,
        // Logs
        logs: [],
        logsMeta: null,
        isLoadingLogs: false,
        showLogs: false,
        logsWorkflowId: null,
        // UI alerts
        alert: null,
    }),

    getters: {
        selectedStep(state) {
            if (!state.selectedNodeId) return null;
            return findStepById(state.workflowSteps, state.selectedNodeId);
        },
        isTriggerConfigured(state) {
            const t = state.workflowSteps[0];
            if (!t) return false;
            if (t.step_type === 'SCHEDULE_TRIGGER') return true;
            return !!(t.step_config?.trigger_event);
        },
        isReadyForSave(state) {
            return state.workflowSteps.length > 0 && state.workflowName.trim().length > 0;
        },
    },

    actions: {
        // ---- Schema ----
        async ensureSchema() {
            if (this.automationSchema.length) return;
            this.isLoadingSchema = true;
            try {
                const resp = await api.fetchAutomationSchema();
                if (Array.isArray(resp)) {
                    this.automationSchema = resp;
                } else if (resp && typeof resp === 'object') {
                    this.automationSchema = Array.isArray(resp.models) ? resp.models : [];
                    this.campaigns = Array.isArray(resp.campaigns) ? resp.campaigns : [];
                    this.morphMap = Array.isArray(resp.morph_map) ? resp.morph_map : [];
                }
            } catch (e) { console.error('Failed to load schema', e); }
            finally { this.isLoadingSchema = false; }
        },

        async ensurePrompts() {
            if (this.prompts.length) return;
            try {
                const r = await api.fetchPrompts({ per_page: 100 });
                const raw = unwrap(r);
                this.prompts = Array.isArray(raw) ? raw : (raw?.data ?? []);
            } catch (e) { console.error('Failed to load prompts', e); }
        },

        // ---- Hub ----
        async fetchWorkflows() {
            this.isLoadingWorkflows = true;
            try {
                const r = await api.fetchWorkflows();
                this.workflows = unwrap(r) || [];
            } catch (e) { console.error('fetchWorkflows failed', e); }
            finally { this.isLoadingWorkflows = false; }
        },

        async deleteWorkflow(id) {
            await api.deleteWorkflow(id);
            this.workflows = this.workflows.filter(w => w.id !== id);
            toast.success('Workflow deleted');
        },

        async toggleActive(workflow) {
            const next = !workflow.is_active;
            const updated = await api.updateWorkflow(workflow.id, { is_active: next });
            const idx = this.workflows.findIndex(w => w.id === workflow.id);
            if (idx !== -1) this.workflows.splice(idx, 1, unwrap(updated));
        },

        // ---- Builder ----
        initNewWorkflow() {
            this.activeWorkflow = null;
            this.workflowName = 'Untitled Automation';
            this.workflowSteps = [];
            this.selectedNodeId = null;
        },

        async loadWorkflow(id) {
            this.isLoadingWorkflow = true;
            this.selectedNodeId = null;
            try {
                const raw = await api.fetchWorkflow(id);
                const wf = unwrap(raw);
                this.activeWorkflow = wf;
                this.workflowName = wf.name || 'Untitled Automation';

                const flatSteps = Array.isArray(wf.steps) ? wf.steps : [];

                // Patch legacy formats
                flatSteps.forEach(s => {
                    if (!s.step_config) s.step_config = {};
                    if (s.step_type === 'CONDITION' && !s.step_config.rules && Array.isArray(s.condition_rules) && s.condition_rules.length > 0) {
                        s.step_config.rules = s.condition_rules.map(r => ({
                            left: { type: 'var', path: `trigger.${r.field || ''}` },
                            operator: r.operator === 'equals' ? '==' : (r.operator || '=='),
                            right: { type: 'literal', value: r.value || '' }
                        }));
                        s.step_config.logic = 'AND';
                    }
                });

                // Check if already nested (has if_true arrays) or flat
                const hasNested = flatSteps.some(s =>
                    (s.step_type === 'CONDITION' && (Array.isArray(s.if_true) || Array.isArray(s.yes_steps))) ||
                    (s.step_type === 'FOR_EACH' && Array.isArray(s.children))
                );
                if (hasNested) {
                    // Normalize yes_steps → if_true etc.
                    this.workflowSteps = normalizeFromServer(flatSteps);
                } else {
                    this.workflowSteps = rebuildTree(flatSteps);
                }
            } catch (e) {
                console.error('loadWorkflow failed', e);
                this.workflowSteps = [];
            } finally {
                this.isLoadingWorkflow = false;
            }
        },

        async saveWorkflow() {
            this.isSaving = true;
            try {
                const trigger = this.workflowSteps[0] || {};
                const triggerEvent = trigger.step_type === 'SCHEDULE_TRIGGER'
                    ? 'schedule.run'
                    : (trigger.step_config?.trigger_event || null);

                const payload = {
                    name: this.workflowName,
                    trigger_event: triggerEvent,
                    is_active: this.activeWorkflow?.is_active ?? true,
                    steps: flattenSteps(this.workflowSteps),
                };

                let result;
                if (this.activeWorkflow?.id) {
                    result = await api.updateWorkflow(this.activeWorkflow.id, payload);
                } else {
                    result = await api.createWorkflow(payload);
                }
                const saved = unwrap(result);
                this.activeWorkflow = saved;
                this.workflowName = saved.name;
                toast.success('Workflow saved!');
                return saved;
            } catch (e) {
                console.error('saveWorkflow failed', e);
                toast.error('Failed to save workflow');
                throw e;
            } finally {
                this.isSaving = false;
            }
        },

        // ---- Step mutations ----
        setSteps(steps) {
            this.workflowSteps = steps;
        },

        selectNode(id) {
            this.selectedNodeId = id ? String(id) : null;
        },

        updateStep(id, newData) {
            this.workflowSteps = findAndReplace(this.workflowSteps, id, newData);
        },

        deleteStep(id) {
            this.workflowSteps = findAndDelete(this.workflowSteps, id);
            if (String(this.selectedNodeId) === String(id)) this.selectedNodeId = null;
        },

        addStep(stepType, parentId = null, branch = null, position = null) {
            const id = `temp_${Date.now()}`;
            const step = {
                id,
                step_type: stepType,
                name: defaultNameFor(stepType),
                step_config: position ? { position, is_unlinked: true } : { is_unlinked: true },
                delay_minutes: 0,
            };
            if (stepType === 'CONDITION') { step.if_true = []; step.if_false = []; }
            if (stepType === 'FOR_EACH') { step.children = []; }

            if (!parentId) {
                this.workflowSteps = [...this.workflowSteps, step];
            } else {
                this.workflowSteps = addNested(this.workflowSteps, parentId, branch, step);
            }
            this.selectedNodeId = id;
        },

        // ---- Logs ----
        async openLogs(workflowId) {
            this.logsWorkflowId = workflowId;
            this.showLogs = true;
            this.logs = [];
            this.logsMeta = null;
            await this.fetchLogs(workflowId, { page: 1 });
        },
        hideLogs() {
            this.showLogs = false;
            this.logs = [];
        },
        async fetchLogs(workflowId, params = {}) {
            this.isLoadingLogs = true;
            try {
                const r = await api.fetchWorkflowLogs(workflowId, { per_page: 30, ...params });
                this.logs = Array.isArray(r.data) ? r.data : (Array.isArray(r) ? r : []);
                this.logsMeta = r.meta || null;
            } catch (e) { console.error('fetchLogs failed', e); }
            finally { this.isLoadingLogs = false; }
        },
    },
});

// ==================== Internal Utilities ====================

function findStepById(steps, id) {
    for (const s of steps) {
        if (String(s.id) === String(id)) return s;
        let found = null;
        if (s.if_true) found = findStepById(s.if_true, id);
        if (!found && s.if_false) found = findStepById(s.if_false, id);
        if (!found && s.children) found = findStepById(s.children, id);
        if (found) return found;
    }
    return null;
}

function findAndReplace(steps, id, newData) {
    return steps.map(s => {
        if (String(s.id) === String(id)) return newData;
        const copy = { ...s };
        if (copy.if_true) copy.if_true = findAndReplace(copy.if_true, id, newData);
        if (copy.if_false) copy.if_false = findAndReplace(copy.if_false, id, newData);
        if (copy.children) copy.children = findAndReplace(copy.children, id, newData);
        return copy;
    });
}

function findAndDelete(steps, id) {
    return steps
        .filter(s => String(s.id) !== String(id))
        .map(s => {
            const copy = { ...s };
            if (copy.if_true) copy.if_true = findAndDelete(copy.if_true, id);
            if (copy.if_false) copy.if_false = findAndDelete(copy.if_false, id);
            if (copy.children) copy.children = findAndDelete(copy.children, id);
            return copy;
        });
}

function addNested(steps, parentId, branch, newStep) {
    return steps.map(s => {
        if (String(s.id) === String(parentId)) {
            const copy = { ...s };
            if (branch === 'yes') { copy.if_true = [...(copy.if_true || []), newStep]; }
            else if (branch === 'no') { copy.if_false = [...(copy.if_false || []), newStep]; }
            else { copy.children = [...(copy.children || []), newStep]; }
            return copy;
        }
        const copy = { ...s };
        if (copy.if_true) copy.if_true = addNested(copy.if_true, parentId, branch, newStep);
        if (copy.if_false) copy.if_false = addNested(copy.if_false, parentId, branch, newStep);
        if (copy.children) copy.children = addNested(copy.children, parentId, branch, newStep);
        return copy;
    });
}

function normalizeFromServer(steps) {
    return steps.map(s => {
        if (!s) return s;
        s.step_config = s.step_config || {};

        // Normalize triggers that might have objects instead of strings
        if (s.step_type === 'TRIGGER' || s.step_type === 'SCHEDULE_TRIGGER') {
            if (s.step_config.event && typeof s.step_config.event === 'object') {
                s.step_config.event = s.step_config.event.value;
            }
            // Fix trigger_event if it contains "[object Object]"
            if (typeof s.step_config.trigger_event === 'string' && s.step_config.trigger_event.includes('[object Object]')) {
                const model = s.step_config.model;
                const event = s.step_config.event;
                if (model && event) {
                    s.step_config.trigger_event = `${model.toLowerCase()}.${event}`;
                }
            }
        }

        if (s.step_type === 'TRIGGER' && s.step_config.trigger_event === 'schedule.run') {
            s.step_type = 'SCHEDULE_TRIGGER';
        }
        if (s.step_type === 'CONDITION') {
            s.if_true = normalizeFromServer(s.if_true || s.yes_steps || []);
            s.if_false = normalizeFromServer(s.if_false || s.no_steps || []);
        }
        if (s.step_type === 'FOR_EACH') {
            s.children = normalizeFromServer(s.children || []);
        }
        return s;
    });
}

function defaultNameFor(type) {
    const map = {
        TRIGGER: 'Trigger',
        SCHEDULE_TRIGGER: 'Scheduled Trigger',
        ACTION: 'Action',
        CONDITION: 'If / Else',
        FETCH_RECORDS: 'Fetch Records',
        FOR_EACH: 'For Each',
        AI_PROMPT: 'AI Prompt',
        TRANSFORM: 'Transform',
        DEFINE_VARIABLE: 'Define Variable',
    };
    return map[type] || type.replace(/_/g, ' ');
}
