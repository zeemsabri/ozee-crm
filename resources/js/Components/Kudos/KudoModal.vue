<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
  show: { type: Boolean, default: false }
});

const emit = defineEmits(['close', 'submitted']);

const projects = ref([]);
const users = ref([]);

const form = reactive({
  recipient_id: '',
  project_id: '',
  comment: ''
});

const errors = ref({});

const loadProjects = async () => {
  try {
    const { data } = await window.axios.get('/api/projects-simplified');
    projects.value = (data || []).map(p => ({ value: p.id, label: p.name }));
  } catch (e) {
    projects.value = [];
  }
};

const loadProjectUsers = async (projectId) => {
  users.value = [];
  if (!projectId) return;
  try {
    const { data } = await window.axios.get(`/api/projects/${projectId}/users`);
    // API returns array of users; map to SelectDropdown format
    users.value = (data || []).map(u => ({ value: u.id, label: `${u.name} (${u.email})` }));
  } catch (e) {
    users.value = [];
  }
};

watch(() => form.project_id, (newVal) => {
  if (newVal) loadProjectUsers(newVal);
});

onMounted(() => {
  loadProjects();
});

const reset = () => {
  form.recipient_id = '';
  form.project_id = '';
  form.comment = '';
  errors.value = {};
};

const handleSubmitted = (payload) => {
  reset();
  emit('submitted', payload);
};

</script>

<template>
  <BaseFormModal
    :show="show"
    title="Give a Kudo ✨"
    api-endpoint="/api/kudos"
    http-method="post"
    :form-data="form"
    submit-button-text="Submit Kudo"
    success-message="Kudo submitted! Pending approval."
    @close="$emit('close')"
    @submitted="handleSubmitted"
  >
    <template #default="{ errors: vErrors }">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Project</label>
          <SelectDropdown
            :options="projects"
            v-model="form.project_id"
            placeholder="Select a project..."
          />
          <p v-if="vErrors.project_id" class="text-red-600 text-xs mt-1">{{ vErrors.project_id[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Recipient</label>
          <SelectDropdown
            :options="users"
            v-model="form.recipient_id"
            placeholder="Select a teammate..."
          />
          <p v-if="vErrors.recipient_id" class="text-red-600 text-xs mt-1">{{ vErrors.recipient_id[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Comment</label>
          <textarea
            v-model="form.comment"
            rows="4"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            placeholder="e.g., Thank you for helping with the last-minute changes!"
          />
          <p v-if="vErrors.comment" class="text-red-600 text-xs mt-1">{{ vErrors.comment[0] }}</p>
        </div>
      </div>
    </template>
  </BaseFormModal>
</template>
