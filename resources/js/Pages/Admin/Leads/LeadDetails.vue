<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import { useLeadDetails } from '@/Composables/useLeadDetails.js';

const props = defineProps({
  id: { type: Number, required: true },
  lead: { type: Object, default: null },
});

// Centralized data fetching via composable
const idRef = ref(props.id);
const { loading, error, lead: leadState, fullName, notes, notesLoading, notesError, savingNote, fetchLead, fetchNotes, addNote } = useLeadDetails(idRef);

// Local UI state
const noteInput = ref('');

// Convert to Client
const showConfirm = ref(false);
const converting = ref(false);
const convertError = ref('');
const convertToClient = async () => {
  if (!leadState.value) return;
  converting.value = true;
  convertError.value = '';
  try {
    const { data } = await window.axios.post(`/api/leads/${leadState.value.id}/convert`);
    const newClientId = data?.client_id || data?.id || data;
    showConfirm.value = false;
    if (newClientId) {
      window.location.href = `/clients/${newClientId}`; // redirect to client profile
    }
  } catch (e) {
    console.error('Conversion failed', e);
    convertError.value = e?.response?.data?.message || 'Failed to convert lead';
  } finally {
    converting.value = false;
  }
};

onMounted(async () => {
  await fetchLead();
  await fetchNotes();
});
</script>

<template>
  <Head :title="`Lead: ${fullName}`" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Lead Details</h2>
            <span v-if="leadState" class="text-xs px-2 py-0.5 rounded-full capitalize"
                  :class="{
                    'bg-gray-100 text-gray-700': !leadState.status || leadState.status.toLowerCase() === 'new',
                    'bg-blue-100 text-blue-700': leadState.status && leadState.status.toLowerCase() === 'contacted',
                    'bg-amber-100 text-amber-700': leadState.status && leadState.status.toLowerCase() === 'qualified',
                    'bg-emerald-100 text-emerald-700': leadState.status && leadState.status.toLowerCase() === 'converted',
                    'bg-rose-100 text-rose-700': leadState.status && leadState.status.toLowerCase() === 'lost'
                  }">{{ leadState.status || 'new' }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <PrimaryButton v-if="(leadState?.status || '').toLowerCase() !== 'converted'" @click="showConfirm = true">Convert to Client</PrimaryButton>
                    <span v-else class="text-sm text-gray-500">Already converted</span>
        </div>
      </div>
    </template>

    <div class="py-6 min-h-screen w-full">
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <div v-if="error" class="mb-4 text-red-600">{{ error }}</div>
            <div v-if="loading" class="animate-pulse space-y-3">
                          <div class="h-5 bg-gray-200 rounded w-1/3"></div>
                          <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                          <div class="h-32 bg-gray-100 rounded"></div>
                        </div>

            <div v-if="leadState && !loading" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <!-- Details -->
              <section class="lg:col-span-2 space-y-4">
                <div>
                  <h3 class="text-lg font-semibold mb-2">Profile</h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500">Name:</span> <span class="font-medium">{{ fullName }}</span></div>
                    <div v-if="leadState.email"><span class="text-gray-500">Email:</span> <span class="font-medium">{{ leadState.email }}</span></div>
                    <div v-if="leadState.phone"><span class="text-gray-500">Phone:</span> <span class="font-medium">{{ leadState.phone }}</span></div>
                    <div v-if="leadState.company"><span class="text-gray-500">Company:</span> <span class="font-medium">{{ leadState.company }}</span></div>
                    <div v-if="leadState.title"><span class="text-gray-500">Title:</span> <span class="font-medium">{{ leadState.title }}</span></div>
                    <div v-if="leadState.source"><span class="text-gray-500">Source:</span> <span class="font-medium">{{ leadState.source }}</span></div>
                    <div v-if="leadState.pipeline_stage"><span class="text-gray-500">Pipeline Stage:</span> <span class="font-medium">{{ leadState.pipeline_stage }}</span></div>
                    <div v-if="leadState.estimated_value"><span class="text-gray-500">Estimated Value:</span> <span class="font-medium">{{ leadState.estimated_value }} {{ leadState.currency || 'USD' }}</span></div>
                    <div v-if="leadState.website"><span class="text-gray-500">Website:</span> <span class="font-medium">{{ leadState.website }}</span></div>
                    <div v-if="leadState.address || leadState.city || leadState.state || leadState.zip || leadState.country">
                      <span class="text-gray-500">Address:</span>
                      <div class="font-medium">
                        {{ leadState.address }}
                        <template v-if="leadState.city">, {{ leadState.city }}</template>
                        <template v-if="leadState.state">, {{ leadState.state }}</template>
                        <template v-if="leadState.zip"> {{ leadState.zip }}</template>
                        <template v-if="leadState.country">, {{ leadState.country }}</template>
                      </div>
                    </div>
                    <div v-if="leadState.assigned_to?.name || leadState.assigned_to_id">
                      <span class="text-gray-500">Assigned:</span>
                      <span class="font-medium">{{ leadState.assigned_to?.name || `User #${leadState.assigned_to_id}` }}</span>
                    </div>
                    <div v-if="leadState.tags"><span class="text-gray-500">Tags:</span> <span class="font-medium">{{ leadState.tags }}</span></div>
                    <div v-if="leadState.notes"><span class="text-gray-500">Notes:</span> <span class="font-medium">{{ leadState.notes }}</span></div>
                  </div>
                </div>
              </section>

              <!-- Notes / Activity -->
              <aside class="lg:col-span-1">
                <h3 class="text-lg font-semibold mb-2">Notes</h3>
                <div class="mb-3 flex gap-2">
                  <input
                    v-model="noteInput"
                    type="text"
                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Add a note..."
                  />
                  <PrimaryButton :disabled="savingNote || !noteInput.trim()" @click="async () => { await addNote(noteInput); noteInput = ''; }">{{ savingNote ? 'Saving...' : 'Save' }}</PrimaryButton>
                </div>
                <div v-if="notesError" class="text-red-600 text-sm mb-2">{{ notesError }}</div>
                <div v-if="notesLoading" class="text-gray-500 text-sm">Loading notes...</div>
                <div class="relative">
                  <div class="absolute left-3 top-0 bottom-0 w-px bg-gray-200" aria-hidden="true"></div>
                  <ul class="space-y-4">
                    <li v-for="n in notes" :key="n.id" class="relative pl-8">
                      <div class="absolute left-0 top-1.5 w-2 h-2 bg-indigo-400 rounded-full"></div>
                      <div class="border rounded-md p-3 bg-white">
                        <div class="text-sm whitespace-pre-wrap">{{ n.body || n.content || n.note || '' }}</div>
                        <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                          <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-600" v-if="n.type">{{ n.type }}</span>
                          <span>By {{ n.user?.name || 'User' }} • {{ new Date(n.created_at).toLocaleString() }}</span>
                        </div>
                      </div>
                    </li>
                    <li v-if="!notesLoading && notes.length === 0" class="text-sm text-gray-500">No notes have been added yet.</li>
                  </ul>
                </div>
              </aside>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <Modal :show="showConfirm" @close="showConfirm = false">
      <div class="p-6">
        <h3 class="text-lg font-semibold mb-2">Convert to Client</h3>
        <p>Are you sure you want to convert this lead into a client?</p>
        <div v-if="convertError" class="text-red-600 text-sm mt-2">{{ convertError }}</div>
        <div class="mt-6 flex justify-end gap-2">
          <SecondaryButton :disabled="converting" @click="showConfirm = false">Cancel</SecondaryButton>
          <PrimaryButton :disabled="converting" @click="convertToClient">{{ converting ? 'Converting...' : 'Convert' }}</PrimaryButton>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>
