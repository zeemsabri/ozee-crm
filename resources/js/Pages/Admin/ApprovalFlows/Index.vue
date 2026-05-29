<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  flows: {
    type: Array,
    default: () => [],
  },
});

const deletingFlow = ref(null);
const showDeleteModal = ref(false);

const deleteForm = useForm({});

const sortedFlows = computed(() => {
  return [...props.flows].sort((a, b) => b.id - a.id);
});

const formatType = (type) => {
  if (!type) {
    return '-';
  }

  const parts = String(type).split('\\\\');

  return parts[parts.length - 1] || type;
};

const openDeleteModal = (flow) => {
  deletingFlow.value = flow;
  showDeleteModal.value = true;
};

const closeDeleteModal = () => {
  showDeleteModal.value = false;
  deletingFlow.value = null;
};

const deleteFlow = () => {
  if (!deletingFlow.value) {
    return;
  }

  deleteForm.delete(route('admin.approval-flows.destroy', deletingFlow.value.id), {
    onSuccess: () => closeDeleteModal(),
  });
};
</script>

<template>
  <Head title="Approval Flows" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Approval Flows</h2>
        <Link :href="route('admin.approval-flows.create')">
          <PrimaryButton>Create New Flow</PrimaryButton>
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <div v-if="sortedFlows.length === 0" class="text-center py-8 text-gray-500">
              <h3 class="text-lg font-medium">No approval flows found</h3>
              <p class="mt-1 text-sm">Create your first approval flow for bills or other models.</p>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approvable Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Steps</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="flow in sortedFlows" :key="flow.id">
                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">{{ flow.name }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ formatType(flow.approvable_type) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ flow.project?.name || 'Global' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                      <span class="px-2 py-1 text-xs rounded-full" :class="flow.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'">
                        {{ flow.is_active ? 'Active' : 'Inactive' }}
                      </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                      <span class="px-2 py-1 text-xs rounded-full" :class="flow.is_default ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'">
                        {{ flow.is_default ? 'Default' : 'No' }}
                      </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ flow.steps?.length || 0 }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-right">
                      <div class="inline-flex items-center gap-2">
                        <Link :href="route('admin.approval-flows.edit', flow.id)">
                          <SecondaryButton>Edit</SecondaryButton>
                        </Link>
                        <DangerButton @click="openDeleteModal(flow)">Delete</DangerButton>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Modal :show="showDeleteModal" @close="closeDeleteModal">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900">Delete approval flow?</h2>
        <p class="mt-1 text-sm text-gray-600">
          This will remove the flow and all of its step definitions.
        </p>
        <div v-if="deletingFlow" class="mt-3 rounded-md bg-gray-50 p-3 text-sm text-gray-700">
          <div><strong>Name:</strong> {{ deletingFlow.name }}</div>
          <div><strong>Type:</strong> {{ formatType(deletingFlow.approvable_type) }}</div>
        </div>

        <div class="mt-6 flex justify-end">
          <SecondaryButton @click="closeDeleteModal">Cancel</SecondaryButton>
          <DangerButton class="ms-3" :disabled="deleteForm.processing" @click="deleteFlow">
            Delete
          </DangerButton>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>
