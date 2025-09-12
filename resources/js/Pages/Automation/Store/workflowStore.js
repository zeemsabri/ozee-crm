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
        automationSchema: {},
        activeWorkflow: null,
        selectedStep: null,
        isLoading: false,
        // New state for the custom modal
        modalState: {
            show: false,
            title: '',
            message: '',
            onConfirm: null,
            onCancel: null,
            type: 'alert', // 'alert' or 'confirm'
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
                const schemaArray = await api.fetchAutomationSchema();
                this.automationSchema = { models: schemaArray || [] };
            } catch(error) {
                console.error("Failed to fetch automation schema:", error);
                this.automationSchema = { models: [] };
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

                // Ensure nested arrays exist and, if necessary, reconstruct the tree from flat data using step_config._parent_id/_branch
                const initializeStepArrays = (steps) => {
                    if (!Array.isArray(steps)) return;
                    steps.forEach(step => {
                        // Normalize step_config to an object
                        if (!step.step_config || typeof step.step_config !== 'object' || Array.isArray(step.step_config)) {
                            step.step_config = {};
                        }
                        if (step.step_type === 'CONDITION') {
                            if (!Array.isArray(step.yes_steps)) step.yes_steps = [];
                            if (!Array.isArray(step.no_steps)) step.no_steps = [];
                            initializeStepArrays(step.yes_steps);
                            initializeStepArrays(step.no_steps);
                        }
                    });
                };

                const reconstructTreeIfFlat = (wf) => {
                    const steps = Array.isArray(wf.steps) ? wf.steps : [];
                    if (!steps.length) return;

                    // Detect if already nested
                    const hasNested = steps.some(s => s && s.step_type === 'CONDITION' && ((Array.isArray(s.yes_steps) && s.yes_steps.length) || (Array.isArray(s.no_steps) && s.no_steps.length)));
                    // Always normalize step_config first
                    steps.forEach(s => {
                        if (!s.step_config || typeof s.step_config !== 'object' || Array.isArray(s.step_config)) {
                            s.step_config = {};
                        }
                    });
                    if (hasNested) {
                        initializeStepArrays(steps);
                        return; // Nothing to reconstruct
                    }

                    const byId = new Map();
                    steps.forEach(s => byId.set(String(s.id), s));

                    // Prepare condition containers
                    steps.forEach(s => {
                        if (s.step_type === 'CONDITION') {
                            if (!Array.isArray(s.yes_steps)) s.yes_steps = [];
                            if (!Array.isArray(s.no_steps)) s.no_steps = [];
                        }
                    });

                    const topLevel = [];
                    steps.forEach(s => {
                        const parentId = s.step_config?._parent_id;
                        const branch = s.step_config?._branch;
                        if (parentId && branch && byId.has(String(parentId))) {
                            const parent = byId.get(String(parentId));
                            if (parent && parent.step_type === 'CONDITION') {
                                const target = String(branch).toLowerCase() === 'no' ? parent.no_steps : parent.yes_steps;
                                target.push(s);
                                return;
                            }
                        }
                        topLevel.push(s);
                    });

                    const sortByOrder = (arr) => arr.sort((a, b) => (a.step_order ?? 0) - (b.step_order ?? 0));
                    // Sort branches
                    steps.forEach(s => {
                        if (s.step_type === 'CONDITION') {
                            sortByOrder(s.yes_steps);
                            sortByOrder(s.no_steps);
                        }
                    });
                    wf.steps = sortByOrder(topLevel);
                };

                if (workflowData && typeof workflowData === 'object' && Array.isArray(workflowData.steps)) {
                    reconstructTreeIfFlat(workflowData);
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
            } else {
                console.error("Received invalid data structure after creating workflow:", response);
                this.showAlert("Failed to create workflow.", "An unknown error occurred while creating the workflow.");
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

            // If adding inside a condition branch, tag the child with its parent and branch so it can be rebuilt on reload
            if (parentStep && branch) {
                newStep.step_config._parent_id = parentStep.id;
                newStep.step_config._branch = branch; // 'yes' | 'no'
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
                    // Defensively ensure the saved step has the required arrays for conditions.
                    if (savedStep.step_type === 'CONDITION') {
                        if (!Array.isArray(savedStep.yes_steps)) savedStep.yes_steps = [];
                        if (!Array.isArray(savedStep.no_steps)) savedStep.no_steps = [];
                    }

                    // Use the new recursive helper to patch the local state.
                    const success = findAndReplaceStep(this.activeWorkflow.steps, originalStepId, savedStep);

                    if (success) {
                        // Reselect the new step object to keep the sidebar in sync.
                        this.selectStep(savedStep);
                    } else {
                        // As a fallback, reload the whole workflow if the patch fails.
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
                this.prompts = unwrapApiResponse(response) || [];
            } catch (error) {
                console.error("Failed to fetch prompts:", error);
            }
        },

        async createPrompt(payload) {
            const response = await api.createPrompt(payload);
            const newPrompt = unwrapApiResponse(response);
            if (newPrompt) {
                this.prompts.unshift(newPrompt);
            }
            return newPrompt;
        },
    },
});
