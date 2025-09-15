import { defineStore } from 'pinia';
import * as api from '../Api/automationApi';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const unwrapApiResponse = (response) => {
    if (response && response.data) return response.data;
    return response;
};

// This is a new, recursive helper function to safely update the workflow state.
const findAndReplaceStep = (steps, oldId, newStep) => {
    if (!steps) return false;
    const index = steps.findIndex(s => s.id === oldId);
    if (index > -1) {
        // Using splice ensures Vue's reactivity system detects the change.
        steps.splice(index, 1, newStep);
        return true;
    }
    for (const step of steps) {
        if (step.step_type === 'CONDITION') {
            if (findAndReplaceStep(step.yes_steps, oldId, newStep) || findAndReplaceStep(step.no_steps, oldId, newStep)) {
                return true;
            }
        }
    }
    return false;
};


export const useWorkflowStore = defineStore('workflow', {
    state: () => ({
        workflows: [],
        prompts: [],
        automationSchema: [],
        campaigns: [],
        activeWorkflow: null,
        selectedStep: null,
        isLoading: false,
        modalState: {
            show: false,
            title: '',
            message: '',
            onConfirm: null,
            onCancel: null,
            type: 'alert',
        },
    }),
    actions: {
        // --- MODAL ACTIONS ---
        showAlert(title, message) {
            this.modalState = {
                show: true,
                title,
                message,
                onConfirm: null,
                onCancel: null,
                type: 'alert',
            };
        },
        showConfirm(title, message, onConfirm, onCancel) {
            this.modalState = {
                show: true,
                title,
                message,
                onConfirm,
                onCancel,
                type: 'confirm',
            };
        },
        hideModal() {
            this.modalState = {
                show: false,
                title: '',
                message: '',
                onConfirm: null,
                onCancel: null,
                type: 'alert',
            };
        },

        // --- SCHEMA ACTIONS ---
        async fetchAutomationSchema() {
            try {
                const resp = await api.fetchAutomationSchema();
                // Handle both legacy (array) and new (object with models/campaigns) shapes
                if (Array.isArray(resp)) {
                    this.automationSchema = resp;
                    this.campaigns = [];
                } else if (resp && typeof resp === 'object') {
                    // Some backends may still return models as a plain array under `models`,
                    // or return the full array directly. Normalize to an array.
                    const models = Array.isArray(resp.models) ? resp.models : [];
                    this.automationSchema = models;
                    this.campaigns = Array.isArray(resp.campaigns) ? resp.campaigns : [];
                } else {
                    this.automationSchema = [];
                    this.campaigns = [];
                }
            } catch(error) {
                console.error("Failed to fetch automation schema:", error);
                this.automationSchema = [];
                this.campaigns = [];
            }
        },

        // --- WORKFLOW ACTIONS ---
        async fetchWorkflows() {
            this.isLoading = true;
            try {
                const response = await api.fetchWorkflows();
                this.workflows = unwrapApiResponse(response) || [];
            } catch (error) {
                console.error("Failed to fetch workflows:", error);
            } finally {
                this.isLoading = false;
            }
        },

        async fetchWorkflow(id) {
            this.isLoading = true;
            try {
                const response = await api.fetchWorkflow(id);
                const workflowData = unwrapApiResponse(response);

                const initializeStepArrays = (steps) => {
                    if (!Array.isArray(steps)) return;
                    steps.forEach(step => {
                        if (!step.step_config || typeof step.step_config !== 'object' || Array.isArray(step.step_config)) {
                            step.step_config = {};
                        }
                        if (step.step_type === 'CONDITION') {
                            if (!Array.isArray(step.yes_steps)) step.yes_steps = [];
                            if (!Array.isArray(step.no_steps)) step.no_steps = [];
                            initializeStepArrays(step.yes_steps);
                            initializeStepArrays(step.no_steps);
                        }
                        if (step.step_type === 'FOR_EACH') {
                            if (!Array.isArray(step.children)) step.children = [];
                            initializeStepArrays(step.children);
                        }
                    });
                };

                const reconstructTreeIfFlat = (wf) => {
                    const steps = Array.isArray(wf.steps) ? wf.steps : [];
                    if (!steps.length) return;

                    const hasNested = steps.some(s => {
                        if (!s) return false;
                        if (s.step_type === 'CONDITION') {
                            return (Array.isArray(s.yes_steps) && s.yes_steps.length) || (Array.isArray(s.no_steps) && s.no_steps.length);
                        }
                        if (s.step_type === 'FOR_EACH') {
                            return Array.isArray(s.children) && s.children.length > 0;
                        }
                        return false;
                    });

                    steps.forEach(s => {
                        if (!s.step_config || typeof s.step_config !== 'object' || Array.isArray(s.step_config)) {
                            s.step_config = {};
                        }
                    });
                    if (hasNested) {
                        initializeStepArrays(steps);
                        return;
                    }

                    const byId = new Map();
                    steps.forEach(s => byId.set(String(s.id), s));

                    steps.forEach(s => {
                        if (s.step_type === 'CONDITION') {
                            if (!Array.isArray(s.yes_steps)) s.yes_steps = [];
                            if (!Array.isArray(s.no_steps)) s.no_steps = [];
                        }
                        if (s.step_type === 'FOR_EACH') {
                            if (!Array.isArray(s.children)) s.children = [];
                        }
                    });

                    const topLevel = [];
                    steps.forEach(s => {
                        const parentId = s.step_config?._parent_id;
                        const branch = s.step_config?._branch;
                        if (parentId && byId.has(String(parentId))) {
                            const parent = byId.get(String(parentId));
                            if (parent && parent.step_type === 'CONDITION' && branch) {
                                const target = String(branch).toLowerCase() === 'no' ? parent.no_steps : parent.yes_steps;
                                target.push(s);
                                return;
                            }
                            if (parent && parent.step_type === 'FOR_EACH' && (branch === null || branch === undefined || branch === '')) {
                                parent.children.push(s);
                                return;
                            }
                        }
                        topLevel.push(s);
                    });

                    const sortByOrder = (arr) => arr.sort((a, b) => (a.step_order ?? 0) - (b.step_order ?? 0));

                    steps.forEach(s => {
                        if (s.step_type === 'CONDITION') {
                            sortByOrder(s.yes_steps);
                            sortByOrder(s.no_steps);
                        }
                        if (s.step_type === 'FOR_EACH') {
                            sortByOrder(s.children);
                        }
                    });
                    wf.steps = sortByOrder(topLevel);
                };

                if (workflowData && typeof workflowData === 'object' && Array.isArray(workflowData.steps)) {
                    reconstructTreeIfFlat(workflowData);

                    // Normalize for UI expectations: map yes/no branches to if_true/if_false
                    const normalizeForUI = (steps) => {
                        if (!Array.isArray(steps)) return;
                        steps.forEach((s, i) => {
                            if (!s || typeof s !== 'object') return;
                            // Ensure step_config is an object
                            if (!s.step_config || typeof s.step_config !== 'object' || Array.isArray(s.step_config)) {
                                s.step_config = {};
                            }
                            // Show schedule trigger nicely in UI
                            if (i === 0 && s.step_type === 'TRIGGER' && s.step_config?.trigger_event === 'schedule.run') {
                                s.step_type = 'SCHEDULE_TRIGGER';
                            }
                            if (s.step_type === 'CONDITION') {
                                // Map backend yes/no keys to UI keys if missing
                                if (!Array.isArray(s.if_true)) s.if_true = Array.isArray(s.yes_steps) ? s.yes_steps : [];
                                if (!Array.isArray(s.if_false)) s.if_false = Array.isArray(s.no_steps) ? s.no_steps : [];
                                // Keep both in sync for safety
                                s.yes_steps = s.if_true;
                                s.no_steps = s.if_false;
                                normalizeForUI(s.if_true);
                                normalizeForUI(s.if_false);
                            }
                            if (s.step_type === 'FOR_EACH') {
                                if (!Array.isArray(s.children)) s.children = [];
                                normalizeForUI(s.children);
                            }
                        });
                    };
                    normalizeForUI(workflowData.steps);

                    this.activeWorkflow = workflowData;
                } else {
                    console.error(`Received invalid data structure for workflow ${id}:`, response);
                    this.activeWorkflow = null;
                    this.selectedStep = null;
                }
            } catch (error) {
                console.error(`Failed to fetch workflow ${id}:`, error);
                this.activeWorkflow = null;
                this.selectedStep = null;
            } finally {
                this.isLoading = false;
            }
        },

        async createWorkflow(payload) {
            const response = await api.createWorkflow(payload);
            const newWorkflow = unwrapApiResponse(response);
            if (newWorkflow && typeof newWorkflow === 'object' && newWorkflow.id) {
                this.workflows.unshift(newWorkflow);
                await this.fetchWorkflow(newWorkflow.id);
                return newWorkflow;
            } else {
                console.error("Received invalid data structure after creating workflow:", response);
                this.showAlert("Failed to create workflow.", "An unknown error occurred while creating the workflow.");
                return null;
            }
        },

        async updateWorkflow(id, payload) {
            const response = await api.updateWorkflow(id, payload);
            const updated = unwrapApiResponse(response);
            if (updated && updated.id) {
                const idx = this.workflows.findIndex(w => w.id === id);
                if (idx !== -1) this.workflows.splice(idx, 1, updated);
                if (this.activeWorkflow && this.activeWorkflow.id === id) {
                    this.activeWorkflow = { ...this.activeWorkflow, ...updated };
                }
            }
            return updated;
        },

        async toggleWorkflowActive(workflow) {
            if (!workflow) return;
            const id = workflow.id || workflow;
            const current = typeof workflow === 'object' ? workflow : this.workflows.find(w => w.id === id);
            const nextActive = !(current?.is_active ?? true);
            return await this.updateWorkflow(id, { is_active: nextActive });
        },

        async deleteWorkflow(id) {
            try {
                await api.deleteWorkflow(id);
                const idx = this.workflows.findIndex(w => w.id === id);
                if (idx !== -1) this.workflows.splice(idx, 1);
                if (this.activeWorkflow && this.activeWorkflow.id === id) {
                    this.activeWorkflow = null;
                    this.selectedStep = null;
                }
                toast.success('Workflow deleted');
            } catch (e) {
                console.error('Failed to delete workflow', e);
                this.showAlert('Delete failed', 'Could not delete workflow.');
            }
        },

        // --- STEP ACTIONS ---
        addStep({ type, insertAfter, parentArray, parentStep = null, branch = null }) {
            if (!this.activeWorkflow) return;

            const newStep = {
                id: `temp_${Date.now()}`,
                workflow_id: this.activeWorkflow.id,
                name: `New ${type.replace('ACTION_', '')}`,
                step_type: type,
                step_order: insertAfter + 1,
                step_config: {},
                condition_rules: type === 'CONDITION' ? [] : null,
            };

            if (parentStep && branch) {
                newStep.step_config._parent_id = parentStep.id;
                newStep.step_config._branch = branch;
            }

            if (type === 'CONDITION') {
                newStep.yes_steps = [];
                newStep.no_steps = [];
            }

            const targetArray = parentArray || this.activeWorkflow.steps;
            targetArray.splice(insertAfter + 1, 0, newStep);
            this.selectStep(newStep);
        },

        selectStep(step) {
            this.selectedStep = step;
        },

        async deleteStep(stepToDelete) {
            if (!this.activeWorkflow) return;
            const findAndRemove = (steps, id) => {
                const index = steps.findIndex(s => s.id === id);
                if (index > -1) {
                    steps.splice(index, 1);
                    return true;
                }
                for (const step of steps) {
                    if (step.step_type === 'CONDITION') {
                        if (findAndRemove(step.yes_steps, id) || findAndRemove(step.no_steps, id)) {
                            return true;
                        }
                    }
                }
                return false;
            };

            if (findAndRemove(this.activeWorkflow.steps, stepToDelete.id)) {
                if (this.selectedStep && this.selectedStep.id === stepToDelete.id) {
                    this.selectedStep = null;
                }
                if (!String(stepToDelete.id).startsWith('temp_')) {
                    try {
                        await api.deleteWorkflowStep(stepToDelete.id);
                        toast.success("Step deleted!");
                    } catch (error) {
                        console.error("Failed to delete step:", error);
                        this.showAlert("Could not delete step.", "An error occurred while deleting the step.");
                    }
                }
            }
        },

        async persistStep(stepToSave) {
            if (!stepToSave || !stepToSave.workflow_id) return;
            const originalStepId = stepToSave.id;

            try {
                let savedStep;
                const isTemporary = String(originalStepId).startsWith('temp_');

                if (isTemporary) {
                    const { id, ...creationPayload } = stepToSave;
                    const response = await api.createWorkflowStep(creationPayload);
                    savedStep = unwrapApiResponse(response);
                } else {
                    const response = await api.updateWorkflowStep(originalStepId, stepToSave);
                    savedStep = unwrapApiResponse(response);
                }

                if (savedStep) {
                    if (savedStep.step_type === 'CONDITION') {
                        if (!Array.isArray(savedStep.yes_steps)) savedStep.yes_steps = [];
                        if (!Array.isArray(savedStep.no_steps)) savedStep.no_steps = [];
                    }

                    const success = findAndReplaceStep(this.activeWorkflow.steps, originalStepId, savedStep);

                    if (success) {
                        this.selectStep(savedStep);
                    } else {
                        await this.fetchWorkflow(stepToSave.workflow_id);
                    }
                }
            } catch(error) {
                console.error('Failed to save step:', error);
                this.showAlert("Failed to save step.", "An error occurred while saving the step.");
            }
        },

        // --- PROMPT ACTIONS ---
        async fetchPrompts() {
            try {
                const response = await api.fetchPrompts({ per_page: 100 });
                const resp = unwrapApiResponse(response);
                // Normalize to array regardless of backend pagination shape
                if (Array.isArray(resp)) {
                    this.prompts = resp;
                } else if (resp && typeof resp === 'object') {
                    // Laravel paginator returns { data: [...], current_page, ... }
                    const arr = Array.isArray(resp.data) ? resp.data : [];
                    this.prompts = arr;
                } else {
                    this.prompts = [];
                }
            } catch (error) {
                console.error("Failed to fetch prompts:", error);
                this.prompts = [];
            }
        },

        async createPrompt(payload) {
            const response = await api.createPrompt(payload);
            const newPrompt = unwrapApiResponse(response);
            if (!Array.isArray(this.prompts)) this.prompts = [];
            if (newPrompt) {
                this.prompts.unshift(newPrompt);
            }
            return newPrompt;
        },

        async updatePrompt(id, payload) {
            const response = await api.updatePrompt(id, payload);
            const updated = unwrapApiResponse(response);
            if (!Array.isArray(this.prompts)) this.prompts = [];
            const idx = this.prompts.findIndex(p => p.id === id);
            if (idx !== -1 && updated) {
                this.prompts.splice(idx, 1, updated);
            } else if (updated) {
                this.prompts.unshift(updated);
            }
            return updated;
        },

        async deletePrompt(id) {
            await api.deletePrompt(id);
            const idx = this.prompts.findIndex(p => p.id === id);
            if (idx !== -1) this.prompts.splice(idx, 1);
        },
    },
});
