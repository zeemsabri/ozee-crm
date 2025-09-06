<template>
  <BaseModal :isOpen="show" title="Collaborate" @close="$emit('close')" :children="BodyComponent" />
</template>

<script setup>
import { ref, defineComponent, onMounted, computed, watch } from 'vue';
import BaseModal from '@/Pages/ClientDashboard/BaseModal.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import api from '@/Services/presentationsApi';
import { success, error } from '@/Utils/notification';

const props = defineProps({
  show: { type: Boolean, default: false },
  presentation: { type: Object, required: true },
});
const emit = defineEmits(['close', 'updated']);

const loadingUsers = ref(false);
const users = ref([]); // [{value:id, label:name/email}]
const selectedUserIds = ref([]);
const role = ref('editor');
const saving = ref(false);

async function fetchUsers() {
  try {
    loadingUsers.value = true;
    // Use global axios to avoid creating a new service
    const http = typeof window !== 'undefined' && window.axios ? window.axios : (await import('axios')).default;
    const res = await http.get('/api/users');
    users.value = (res.data || []).map(u => ({ value: u.id, label: u.name ? `${u.name} <${u.email}>` : u.email }));
  } catch (e) {
    error('Failed to load users');
  } finally {
    loadingUsers.value = false;
  }
}

onMounted(() => {
  fetchUsers();
  prefillFromProps();
});

function prefillFromProps() {
  const collabs = Array.isArray(props.presentation?.users) ? props.presentation.users : [];
  selectedUserIds.value = collabs.map(u => u.id);
}

watch(() => props.presentation?.users, () => {
  prefillFromProps();
});

// Allow save even when selectedUserIds is empty, to support removing everyone
const canSave = computed(() => !saving.value);

async function save() {
  try {
    saving.value = true;
    const res = await api.syncCollaborators(props.presentation.id, selectedUserIds.value, role.value);
    success('Collaborators updated');
    emit('updated', res?.collaborators || []);
    emit('close');
  } catch (e) {
    error('Failed to update collaborators');
  } finally {
    saving.value = false;
  }
}

const BodyComponent = defineComponent({
  name: 'CollaborateBody',
  components: { MultiSelectDropdown },
  template: `
    <div class=\"space-y-4\">
      <div>
        <label class=\"block text-sm font-medium text-gray-700 mb-1\">Select collaborators</label>
        <MultiSelectDropdown
          v-model="selectedUserIds"
          :options="users"
          :isMulti="true"
          placeholder="Search and select users"
        />
        <p class=\"text-xs text-gray-500 mt-1\">You can add multiple users. They will get access to view/edit this presentation.</p>
      </div>

      <div>
        <label class=\"block text-sm font-medium text-gray-700 mb-1\">Role</label>
        <select v-model="role" class=\"border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm mt-1 block w-full px-3 py-2\">
          <option value=\"editor\">Editor</option>
          <option value=\"viewer\">Viewer</option>
        </select>
      </div>

      <div class=\"flex justify-end gap-2\">
        <button @click="closeModal" class=\"px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm\">Cancel</button>
        <button :disabled="!canSave" @click="save" class=\"px-3 py-2 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 text-sm disabled:opacity-50\">
          {{ saving ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </div>
  `,
  setup() {
    return { users, selectedUserIds, role, saving, canSave, save, closeModal: () => emit('close'), loadingUsers };
  }
});
</script>
