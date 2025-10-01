<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useWorkflowStore } from './Store/workflowStore';
import * as api from './Api/automationApi';

// We will create these two components in the next steps.
import AutomationHub from './Components/AutomationHub.vue';
import AutomationBuilder from './Components/AutomationBuilder.vue';

const store = useWorkflowStore();
const view = ref('hub'); // Can be 'hub' or 'builder'
const selectedAutomationId = ref(null);

// Export/Import helpers
const selectedWorkflowId = ref(null); // used for Export and for Update target during Import
const fileInputRef = ref(null);

// When the component first loads, we need to fetch the list of workflows for the hub.
onMounted(() => {
  store.fetchWorkflows();
});

function showBuilder(automationId = null) {
  selectedAutomationId.value = automationId; // If null, it's a new automation
  view.value = 'builder';
}

function showHub() {
  selectedAutomationId.value = null;
  view.value = 'hub';
  // Re-fetch workflows in case one was just saved
  store.fetchWorkflows();
}

function downloadJson(filename, data) {
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

async function handleExport() {
  try {
    const id = selectedWorkflowId.value;
    if (!id) {
      alert('Please select a workflow to export.');
      return;
    }
    // Fetch full workflow with steps
    const wf = await api.fetchWorkflow(id);
    if (!wf) {
      alert('Could not fetch the selected workflow.');
      return;
    }
    const payload = {
      _meta: {
        type: 'automation_workflow',
        version: 1,
        exported_at: new Date().toISOString(),
        source: window.location.origin,
      },
      name: wf.name,
      description: wf.description || null,
      trigger_event: wf.trigger_event,
      is_active: !!wf.is_active,
      steps: Array.isArray(wf.steps) ? wf.steps : [],
    };
    const safeName = (wf.name || 'workflow').replace(/[^a-z0-9-_]+/gi, '_');
    downloadJson(`${safeName}_export.json`, payload);
  } catch (e) {
    console.error('Export failed', e);
    alert('Export failed. See console for details.');
  }
}

function triggerImport() {
  if (fileInputRef.value) fileInputRef.value.click();
}

async function handleFileChange(event) {
  const file = event.target.files && event.target.files[0];
  if (!file) return;
  try {
    const text = await file.text();
    const data = JSON.parse(text);

    if (!data || typeof data !== 'object') {
      alert('Invalid file format.');
      event.target.value = '';
      return;
    }

    // Basic validation
    if (!data.steps || !Array.isArray(data.steps)) {
      alert('Invalid import: missing steps array.');
      event.target.value = '';
      return;
    }

    // Ask user whether to create a new workflow or update an existing one
    const createNew = window.confirm('Import: Do you want to CREATE a NEW workflow from this file?\n\nPress OK to create new. Press Cancel to update an existing workflow.');

    if (createNew) {
      // Create new workflow from imported payload
      const payload = {
        name: data.name || `Imported Workflow ${new Date().toLocaleString()}`,
        description: data.description || null,
        trigger_event: data.trigger_event || 'manual.run',
        is_active: !!data.is_active,
        steps: Array.isArray(data.steps) ? data.steps : [],
      };
      await store.createWorkflow(payload);
      alert('Workflow imported as new.');
      await store.fetchWorkflows();
    } else {
      // Update existing
      if (!selectedWorkflowId.value) {
        alert('Please select an existing workflow (from the dropdown) to update.');
        event.target.value = '';
        return;
      }
      const proceed = window.confirm('WARNING: This will replace the selected workflow\'s data and all its steps with the imported file. This action cannot be undone.\n\nPress OK to overwrite.');
      if (!proceed) {
        event.target.value = '';
        return;
      }
      const targetId = selectedWorkflowId.value;
      const payload = {
        name: data.name || undefined,
        description: data.description ?? null,
        trigger_event: data.trigger_event || undefined,
        is_active: typeof data.is_active === 'boolean' ? data.is_active : undefined,
        steps: Array.isArray(data.steps) ? data.steps : [],
      };
      await store.updateWorkflow(targetId, payload);
      alert('Workflow updated from import.');
      await store.fetchWorkflows();
    }
  } catch (e) {
    console.error('Import failed', e);
    alert('Import failed. Ensure the file is a valid export JSON.');
  } finally {
    // reset input so same file can be selected again later
    event.target.value = '';
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <!-- The main content area will now dynamically switch between the hub and the builder -->
    <div class="p-4 sm:p-6 lg:p-8 font-sans">
      <template v-if="view === 'hub'">
        <!-- Import/Export Toolbar -->
        <div class="mb-4 p-3 border rounded bg-white/70 flex flex-col sm:flex-row gap-2 sm:items-center">
          <div class="flex-1 flex items-center gap-2">
            <label class="text-sm text-gray-700 whitespace-nowrap">Workflow</label>
            <select v-model="selectedWorkflowId" class="border rounded px-2 py-1 text-sm w-full sm:w-80">
              <option :value="null">-- Select a workflow --</option>
              <option v-for="wf in store.workflows" :key="wf.id" :value="wf.id">{{ wf.name }} (ID: {{ wf.id }})</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <button @click="handleExport" class="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded">Export</button>
            <button @click="triggerImport" class="px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded">Import</button>
            <input ref="fileInputRef" type="file" accept="application/json,.json" class="hidden" @change="handleFileChange" />
          </div>
        </div>
        <AutomationHub @new="showBuilder()" @edit="showBuilder" />
      </template>
      <template v-else-if="view === 'builder'">
        <AutomationBuilder
            :automation-id="selectedAutomationId"
            @back="showHub"
        />
      </template>
    </div>
  </AuthenticatedLayout>
</template>
