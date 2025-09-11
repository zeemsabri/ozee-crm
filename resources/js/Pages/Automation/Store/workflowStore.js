import { defineStore } from 'pinia';
import * as api from '../Api/automationApi';

// This helper function safely extracts the main data object from a typical Laravel API response.
const unwrapApiResponse = (response) => {
    if (response && response.data) {
        return response.data;
    }
    return response;
};

export const useWorkflowStore = defineStore('workflow', {
    state: () => ({
        workflows: [],
        prompts: [],
        activeWorkflow: null,
        selectedStep: null,
        isLoading: false,
    }),

    actions: {
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
            this.selectedStep = null;
            try {
                const response = await api.fetchWorkflow(id);
                const workflow = unwrapApiResponse(response);

                // This function recursively prepares the steps for our flowchart UI.
                const initializeStepArrays = (steps) => {
                    if (!Array.isArray(steps)) return;
                    steps.forEach(step => {
                        if (step.step_type === 'CONDITION') {
                            if (!Array.isArray(step.yes_steps)) step.yes_steps = [];
                            if (!Array.isArray(step.no_steps)) step.no_steps = [];
                            initializeStepArrays(step.yes_steps);
                            initializeStepArrays(step.no_steps);
                        }
                    });
                };

                // ** THE FIX IS HERE **
                // We now have a much safer check to ensure 'workflow' is a valid object with steps.
                if (workflow && typeof workflow === 'object' && Array.isArray(workflow.steps)) {
                    initializeStepArrays(workflow.steps);
                    this.activeWorkflow = workflow;
                } else {
                    // If the data is invalid, log an error and clear the active workflow.
                    console.error(`Received invalid data structure for workflow ${id}:`, response);
                    this.activeWorkflow = null;
                }

            } catch (error) {
                console.error(`Failed to fetch workflow ${id}:`, error);
                this.activeWorkflow = null;
            } finally {
                this.isLoading = false;
            }
        },

        async createWorkflow(payload) {
            const response = await api.createWorkflow(payload);
            const newWorkflow = unwrapApiResponse(response);
            if (newWorkflow) {
                this.workflows.unshift(newWorkflow);
                await this.fetchWorkflow(newWorkflow.id);
            }
        },

        // --- STEP ACTIONS ---
        addStep({ type, insertAfter }) {
            if (!this.activeWorkflow) return;

            const newStep = {
                id: `temp_${Date.now()}`,
                workflow_id: this.activeWorkflow.id,
                name: `New ${type}`,
                step_type: type,
                step_order: insertAfter + 1,
                step_config: {},
                condition_rules: type === 'CONDITION' ? [] : null,
            };

            this.activeWorkflow.steps.splice(insertAfter + 1, 0, newStep);
            this.selectStep(newStep);
        },

        selectStep(step) {
            this.selectedStep = step;
        },

        async persistStep(stepToSave) {
            if (!stepToSave || !stepToSave.workflow_id) return;

            // ** THIS LOGIC IS NEW **
            // Check if the ID is temporary (meaning it's a new step).
            if (String(stepToSave.id).startsWith('temp_')) {
                const { id, ...creationPayload } = stepToSave; // Omit the temporary ID
                const response = await api.createWorkflowStep(creationPayload);
                const savedStep = unwrapApiResponse(response);

                // Replace the temporary step with the real one from the server.
                const index = this.activeWorkflow.steps.findIndex(s => s.id === stepToSave.id);
                if (index !== -1 && savedStep) {
                    this.activeWorkflow.steps[index] = savedStep;
                    this.selectedStep = savedStep;
                }
            } else {
                // If it's an existing step, update it.
                const response = await api.updateWorkflowStep(stepToSave.id, stepToSave);
                const savedStep = unwrapApiResponse(response);

                const index = this.activeWorkflow.steps.findIndex(s => s.id === stepToSave.id);
                if (index !== -1 && savedStep) {
                    this.activeWorkflow.steps[index] = savedStep;
                }
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

