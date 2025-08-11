<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePermissions } from '@/Directives/permissions';

const { canDo } = usePermissions();
const canApprove = canDo('approve_kudos');

const loading = ref(true);
const error = ref('');
const successMessage = ref('');

const pendingKudos = ref([]);
const myKudos = ref([]);

const fetchData = async () => {
  loading.value = true;
  error.value = '';
  try {
    if (canApprove) {
      const { data } = await window.axios.get('/api/kudos/pending');
      pendingKudos.value = data || [];
    } else {
      const { data } = await window.axios.get('/api/kudos/mine');
      myKudos.value = data || [];
    }
  } catch (e) {
    error.value = 'Failed to load kudos.';
  } finally {
    loading.value = false;
  }
};

const approve = async (kudo) => {
  try {
    await window.axios.post(`/api/kudos/${kudo.id}/approve`);
    successMessage.value = 'Kudo approved';
    await fetchData();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to approve kudo';
  }
};

const rejectKudo = async (kudo) => {
  try {
    await window.axios.post(`/api/kudos/${kudo.id}/reject`);
    successMessage.value = 'Kudo rejected';
    await fetchData();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to reject kudo';
  }
};

onMounted(fetchData);
</script>

<template>
  <Head title="Kudos" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kudos</h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <div v-if="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
              <span class="block sm:inline">{{ successMessage }}</span>
            </div>
            <div v-if="error" class="text-red-600 mb-4">{{ error }}</div>

            <div v-if="loading">Loading...</div>

            <template v-else>
              <div v-if="canApprove">
                <h3 class="text-2xl font-bold mb-4">Kudos Pending Approval</h3>
                <div v-if="pendingKudos.length === 0" class="text-gray-600">No pending kudos found.</div>
                <div v-else class="space-y-4">
                  <div v-for="k in pendingKudos" :key="k.id" class="border rounded p-4 flex justify-between items-start">
                    <div>
                      <div class="font-semibold">Project: {{ k.project?.name || 'N/A' }}</div>
                      <div class="text-sm text-gray-600">From: {{ k.sender?.name }} → To: {{ k.recipient?.name }}</div>
                      <div class="mt-2">{{ k.comment }}</div>
                    </div>
                    <div class="flex-shrink-0 space-x-2">
                      <PrimaryButton class="text-xs" @click="approve(k)">Approve</PrimaryButton>
                      <SecondaryButton class="text-xs" @click="rejectKudo(k)">Reject</SecondaryButton>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else>
                <h3 class="text-2xl font-bold mb-4">My Approved Kudos</h3>
                <div v-if="myKudos.length === 0" class="text-gray-600">No kudos yet.</div>
                <div v-else class="space-y-4">
                  <div v-for="k in myKudos" :key="k.id" class="border rounded p-4">
                    <div class="font-semibold">From: {{ k.sender?.name }} on {{ k.project?.name || 'N/A' }}</div>
                    <div class="mt-2">{{ k.comment }}</div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
