import { defineStore } from 'pinia';
import {
  fetchWorkflows as apiFetchWorkflows,
  fetchWorkflow as apiFetchWorkflow,
  createWorkflowStep as apiCreateStep,
  updateWorkflowStep as apiUpdateStep,
  deleteWorkflowStep as apiDeleteStep,
} from '../Api/automationApi';

export const useWorkflowStore = defineStore('automation-workflow', {
  state: () => ({
    workflows: [],
    workflowsMeta: null,
    activeWorkflow: null,
    selectedStep: null,
    isLoading: false,
  }),

  getters: {
    getStepById: (state) => (id) => {
      if (!state.activeWorkflow?.steps) return null;
      return state.activeWorkflow.steps.find((s) => String(s.id) === String(id));
    },
  },

  actions: {
    async fetchWorkflows(params = {}) {
      this.isLoading = true;
      try {
        const page = await apiFetchWorkflows(params);
        this.workflows = page.data || [];
        this.workflowsMeta = { meta: page.meta, links: page.links };
      } finally {
        this.isLoading = false;
      }
    },

    async fetchWorkflow(id) {
      this.isLoading = true;
      try {
        const wf = await apiFetchWorkflow(id);
        // Ensure steps array exists
        wf.steps = Array.isArray(wf.steps) ? wf.steps : [];
        // Normalize step_order
        wf.steps.forEach((s, idx) => {
          if (typeof s.step_order !== 'number') s.step_order = idx;
        });
        this.activeWorkflow = wf;
        this.selectedStep = null;
      } finally {
        this.isLoading = false;
      }
    },

    selectStep(step) {
      this.selectedStep = step;
    },

    addStep(stepType) {
      if (!this.activeWorkflow) return;
      const steps = this.activeWorkflow.steps || (this.activeWorkflow.steps = []);
      const nextOrder = steps.length;
      const step = {
        id: `temp-${Date.now()}`,
        workflow_id: this.activeWorkflow.id,
        step_order: nextOrder,
        name: stepType === 'TRIGGER' ? 'Trigger' : stepType === 'CONDITION' ? 'Condition' : 'AI Prompt',
        step_type: stepType,
        prompt_id: null,
        step_config: {},
        condition_rules: stepType === 'CONDITION' ? [] : null,
        delay_minutes: 0,
      };
      steps.push(step);
      this.selectedStep = step;
    },

    reorderSteps(newOrder) {
      if (!this.activeWorkflow) return;
      this.activeWorkflow.steps = newOrder.map((s, idx) => ({ ...s, step_order: idx }));
    },

    async persistStep(step) {
      // optional helper to persist to API later
      if (String(step.id).startsWith('temp-')) {
        const created = await apiCreateStep({
          workflow_id: step.workflow_id,
          step_order: step.step_order,
          name: step.name,
          step_type: step.step_type,
          prompt_id: step.prompt_id,
          step_config: step.step_config,
          condition_rules: step.condition_rules,
          delay_minutes: step.delay_minutes,
        });
        // replace temp with created
        const idx = this.activeWorkflow.steps.findIndex((s) => s.id === step.id);
        if (idx !== -1) this.activeWorkflow.steps[idx] = created;
        if (this.selectedStep?.id === step.id) this.selectedStep = created;
      } else {
        const updated = await apiUpdateStep(step.id, step);
        const idx = this.activeWorkflow.steps.findIndex((s) => String(s.id) === String(step.id));
        if (idx !== -1) this.activeWorkflow.steps[idx] = updated;
        if (this.selectedStep?.id === step.id) this.selectedStep = updated;
      }
    },

    async deleteStep(stepId) {
      const idx = this.activeWorkflow?.steps?.findIndex((s) => String(s.id) === String(stepId));
      if (idx === undefined || idx < 0) return;
      const step = this.activeWorkflow.steps[idx];
      if (!String(step.id).startsWith('temp-')) {
        await apiDeleteStep(step.id);
      }
      this.activeWorkflow.steps.splice(idx, 1);
      // reindex orders
      this.activeWorkflow.steps.forEach((s, i) => (s.step_order = i));
      if (this.selectedStep?.id === stepId) this.selectedStep = null;
    },
  },
});
