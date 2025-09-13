<script setup>
import { ref, computed, onMounted } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useWorkflowStore } from '@/Pages/Automation/Store/workflowStore';
import { confirmPrompt, success, error } from '@/Utils/notification';

const store = useWorkflowStore();
const isLoading = computed(() => store.isLoading);
const prompts = computed(() => store.prompts || []);

const search = ref('');
const filteredPrompts = computed(() => {
  const q = (search.value || '').toLowerCase();
  if (!q) return prompts.value;
  return prompts.value.filter(p =>
    (p.name || '').toLowerCase().includes(q) ||
    (p.category || '').toLowerCase().includes(q) ||
    (p.system_prompt_text || '').toLowerCase().includes(q)
  );
});

const groupedByName = computed(() => {
  const map = new Map();
  for (const p of filteredPrompts.value) {
    const key = p.name || 'Untitled';
    if (!map.has(key)) map.set(key, []);
    map.get(key).push(p);
  }
  for (const arr of map.values()) {
    arr.sort((a, b) => (b.version ?? 0) - (a.version ?? 0));
  }
  return Array.from(map.entries()).map(([name, versions]) => ({ name, versions }));
});

onMounted(async () => {
  await store.fetchPrompts();
});

// Modal state
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const form = ref({
  name: '',
  category: '',
  version: 1,
  system_prompt_text: '',
  model_name: '',
  generation_config: {},
  template_variables: {},
  generation_config_json: '',
  template_variables_json: '',
  status: 'active',
});

const openCreateModal = (prefillName = '') => {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    name: prefillName || '',
    category: '',
    version: 1,
    system_prompt_text: '',
    model_name: '',
    generation_config: {},
    template_variables: {},
    status: 'active',
  };
  showModal.value = true;
};

const openNewVersionModal = (name, latestVersion) => {
  // Prefill with same name and incremented version
  openCreateModal(name);
  form.value.version = (latestVersion ?? 0) + 1;
};

const openEditModal = (prompt) => {
  isEditing.value = true;
  editingId.value = prompt.id;
  form.value = {
    name: prompt.name || '',
    category: prompt.category || '',
    version: prompt.version ?? 1,
    system_prompt_text: prompt.system_prompt_text || '',
    model_name: prompt.model_name || '',
    generation_config: prompt.generation_config || {},
    template_variables: prompt.template_variables || {},
    status: prompt.status || 'active',
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const savePrompt = async () => {
  try {
    if (isEditing.value && editingId.value) {
      const updated = await store.updatePrompt(editingId.value, form.value);
      if (updated) success('Prompt updated');
    } else {
      const created = await store.createPrompt(form.value);
      if (created) success('Prompt created');
    }
    showModal.value = false;
  } catch (e) {
    const msg = e?.response?.data?.message || 'Failed to save prompt';
    error(msg);
  }
};

const deletePrompt = async (prompt) => {
  const ok = await confirmPrompt(`Delete prompt "${prompt.name}" v${prompt.version}?`, { confirmText: 'Delete', variant: 'danger' });
  if (!ok) return;
  try {
    await store.deletePrompt(prompt.id);
    success('Prompt deleted');
  } catch (e) {
    const msg = e?.response?.data?.message || 'Failed to delete prompt';
    error(msg);
  }
};
</script>

<template>
  <div class="p-4 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-semibold">Prompts</h1>
      <PrimaryButton @click="openCreateModal()">New Prompt</PrimaryButton>
    </div>

    <div class="flex items-center gap-2">
      <input v-model="search" type="text" placeholder="Search..." class="border rounded px-2 py-1 text-sm w-full md:w-1/2" />
    </div>

    <div v-if="groupedByName.length === 0" class="text-sm text-gray-500">No prompts found.</div>

    <div v-for="group in groupedByName" :key="group.name" class="border rounded mb-3">
      <div class="flex items-center justify-between px-3 py-2 bg-gray-50 border-b">
        <div class="font-medium text-gray-800">{{ group.name }}</div>
        <div class="flex items-center gap-2">
          <SecondaryButton @click="openNewVersionModal(group.name, group.versions[0]?.version)">New Version</SecondaryButton>
          <PrimaryButton @click="openCreateModal(group.name)">Duplicate</PrimaryButton>
        </div>
      </div>
      <div class="divide-y">
        <div v-for="p in group.versions" :key="p.id" class="px-3 py-2 flex items-center justify-between">
          <div>
            <div class="text-sm font-medium">v{{ p.version }} <span class="text-xs text-gray-500">{{ p.status || 'active' }}</span></div>
            <div class="text-xs text-gray-500 truncate max-w-[60ch]">{{ p.system_prompt_text }}</div>
          </div>
          <div class="flex items-center gap-2">
            <SecondaryButton @click="openEditModal(p)">Edit</SecondaryButton>
            <button @click="deletePrompt(p)" class="text-xs px-2 py-1 rounded-md border text-red-600 hover:bg-red-50">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <Modal :show="showModal" @close="closeModal" maxWidth="3xl">
      <div class="p-4">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-base font-semibold">{{ isEditing ? 'Edit Prompt' : 'New Prompt' }}</h2>
          <button @click="closeModal" class="text-gray-500 hover:text-gray-700">✕</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-gray-600">Name</label>
            <input v-model="form.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" />
          </div>
          <div>
            <label class="text-xs text-gray-600">Category</label>
            <input v-model="form.category" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" />
          </div>
          <div>
            <label class="text-xs text-gray-600">Version</label>
            <input v-model.number="form.version" type="number" min="1" class="mt-1 w-full border rounded px-2 py-1 text-sm" />
          </div>
          <div>
            <label class="text-xs text-gray-600">Model Name</label>
            <input v-model="form.model_name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g., gemini-2.0-pro" />
          </div>
          <div class="md:col-span-2">
            <label class="text-xs text-gray-600">System Prompt</label>
            <textarea v-model="form.system_prompt_text" rows="6" class="mt-1 w-full border rounded px-2 py-1 text-sm"></textarea>
          </div>
          <div>
            <label class="text-xs text-gray-600">Status</label>
            <select v-model="form.status" class="mt-1 w-full border rounded px-2 py-1 text-sm">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="draft">Draft</option>
            </select>
          </div>
          <div>
            <label class="text-xs text-gray-600">Generation Config (JSON)</label>
            <textarea v-model="form.generation_config_json" rows="4" class="mt-1 w-full border rounded px-2 py-1 text-xs font-mono"></textarea>
          </div>
          <div>
            <label class="text-xs text-gray-600">Template Variables (JSON)</label>
            <textarea v-model="form.template_variables_json" rows="4" class="mt-1 w-full border rounded px-2 py-1 text-xs font-mono"></textarea>
          </div>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
          <PrimaryButton @click="() => {
            try {
              form.generation_config = form.generation_config_json ? JSON.parse(form.generation_config_json) : {};
              form.template_variables = form.template_variables_json ? JSON.parse(form.template_variables_json) : {};
            } catch (e) {
              error('Invalid JSON in config fields');
              return;
            }
            savePrompt();
          }">{{ isEditing ? 'Save Changes' : 'Create' }}</PrimaryButton>
        </div>
      </div>
    </Modal>
  </div>
</template>
