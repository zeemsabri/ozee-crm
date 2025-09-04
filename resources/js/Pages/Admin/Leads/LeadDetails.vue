<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import CustomComposeEmailContent from '@/Pages/Emails/Inbox/Components/CustomComposeEmailContent.vue';
import CustomEmailApprovalContent from '@/Pages/Emails/Inbox/Components/CustomEmailApprovalContent.vue';
import EmailDetailsContent from '@/Pages/Emails/Inbox/Components/EmailDetailsContent.vue';
import EmailActionContent from '@/Pages/Emails/Inbox/Components/EmailActionContent.vue';
import ReceivedEmailActionContent from '@/Pages/Emails/Inbox/Components/ReceivedEmailActionContent.vue';
import { useLeadDetails } from '@/Composables/useLeadDetails.js';
import axios from 'axios';

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

// Emails for this lead
const emails = ref([]);
const emailsLoading = ref(false);
const emailsError = ref('');
const emailPagination = ref({ current_page: 1, last_page: 1, total: 0 });

const fetchLeadEmails = async (page = 1) => {
  emailsLoading.value = true;
  emailsError.value = '';
  try {
    const { data } = await axios.get(`/api/leads/${idRef.value}/emails`, { params: { page } });
    emails.value = data.data || [];
    emailPagination.value = {
      current_page: data.current_page || 1,
      last_page: data.last_page || 1,
      total: data.total || (Array.isArray(data.data) ? data.data.length : 0),
    };
  } catch (e) {
    console.error('Failed to load lead emails', e);
    emailsError.value = e?.response?.data?.message || 'Failed to load emails';
  } finally {
    emailsLoading.value = false;
  }
};

// Sidebar state
const sidebar = ref({ show: false, mode: null, title: '', data: null, loading: false });

const openCompose = () => {
  sidebar.value = { show: true, mode: 'custom-compose', title: 'Compose Email to Lead', data: null, loading: false };
};

const openApproval = (email) => {
  sidebar.value = { show: true, mode: 'view-email', title: 'Email Details', data: email, loading: false };
};

const handleEditEmail = (email) => {
  sidebar.value.show = true;
  sidebar.value.data = email;
  if (email.type === 'received' && (email.status === 'pending_approval_received' || email.status === 'received')) {
    sidebar.value.mode = 'received-edit';
    sidebar.value.title = 'Approve Received Email';
  } else {
    if (!email.template_id) {
      sidebar.value.mode = 'custom-edit';
      sidebar.value.title = 'Edit & Approve Custom Email';
    } else {
      sidebar.value.mode = 'edit';
      sidebar.value.title = 'Edit & Approve Template Email';
    }
  }
};

const handleSidebarSubmitted = () => {
  sidebar.value.show = false;
  fetchLeadEmails(emailPagination.value.current_page);
};

const changeEmailPage = (page) => {
  if (page < 1 || page > emailPagination.value.last_page) return;
  fetchLeadEmails(page);
};

onMounted(async () => {
  await fetchLead();
  await fetchNotes();
  await fetchLeadEmails(1);
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
          <PrimaryButton @click="openCompose">Compose Email</PrimaryButton>
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
              <section class="lg:col-span-2 space-y-6">
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

                <!-- Emails Section -->
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold">Emails</h3>
                    <PrimaryButton @click="openCompose">Compose Email</PrimaryButton>
                  </div>
                  <div v-if="emailsLoading" class="text-gray-500 text-sm">Loading emails...</div>
                  <div v-else-if="emailsError" class="text-red-600 text-sm">{{ emailsError }}</div>
                  <div v-else-if="emails.length === 0" class="text-gray-500 text-sm">No emails found for this lead.</div>
                  <div v-else class="overflow-x-auto shadow rounded-md border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                      </thead>
                      <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="e in emails" :key="e.id" class="hover:bg-gray-50 cursor-pointer" @click="openApproval(e)">
                          <td class="px-4 py-2 text-sm text-gray-900">{{ e.subject }}</td>
                          <td class="px-4 py-2 text-sm">
                            <span :class="{
                              'px-2 py-1 rounded-full text-xs font-medium': true,
                              'bg-green-100 text-green-800': e.status === 'sent',
                              'bg-yellow-100 text-yellow-800': e.status === 'pending_approval' || e.status === 'pending_approval_received',
                              'bg-blue-100 text-blue-800': e.status === 'received'
                            }">{{ (e.status || 'n/a').replace(/_/g, ' ').toUpperCase() }}</span>
                          </td>
                          <td class="px-4 py-2 text-sm text-gray-500">{{ e.type }}</td>
                          <td class="px-4 py-2 text-sm text-gray-500">{{ new Date(e.created_at).toLocaleString() }}</td>
                        </tr>
                      </tbody>
                    </table>
                    <div v-if="emailPagination.last_page > 1" class="p-2 flex justify-end gap-1">
                      <button class="px-2 py-1 text-sm border rounded" :disabled="emailPagination.current_page === 1" @click="changeEmailPage(emailPagination.current_page - 1)">Prev</button>
                      <span class="px-2 py-1 text-sm">Page {{ emailPagination.current_page }} of {{ emailPagination.last_page }}</span>
                      <button class="px-2 py-1 text-sm border rounded" :disabled="emailPagination.current_page === emailPagination.last_page" @click="changeEmailPage(emailPagination.current_page + 1)">Next</button>
                    </div>
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
    <!-- Right Sidebar for Compose / Approve -->
    <RightSidebar :show="sidebar.show" :title="sidebar.title" @close="sidebar.show = false">
      <template #content>
        <div v-if="sidebar.mode === 'custom-compose'">
          <CustomComposeEmailContent
            :project-id="null"
            :user-project-role="{}"
            force-recipient-mode="leads"
            :preset-lead-ids="[idRef]"
            :hide-recipient-controls="true"
            @submitted="handleSidebarSubmitted"
            @error="() => {}"
          />
        </div>
        <div v-else-if="sidebar.mode === 'view-email'">
          <EmailDetailsContent
            :email="sidebar.data"
            :can-approve-emails="sidebar.data?.can_approve"
            @edit="handleEditEmail"
            @reject="(email) => { sidebar.mode = 'reject'; sidebar.data = email; sidebar.title = 'Reject Email'; }"
          />
        </div>
        <div v-else-if="sidebar.mode === 'edit' || sidebar.mode === 'reject'">
          <EmailActionContent
            :email="sidebar.data"
            :mode="sidebar.mode"
            @submitted="handleSidebarSubmitted"
          />
        </div>
        <div v-else-if="sidebar.mode === 'received-edit'">
          <ReceivedEmailActionContent
            :email="sidebar.data"
            @submitted="handleSidebarSubmitted"
            @error="() => {}"
          />
        </div>
        <div v-else-if="sidebar.mode === 'custom-edit'">
          <CustomEmailApprovalContent
            :email="sidebar.data"
            @submitted="handleSidebarSubmitted"
            @error="() => {}"
          />
        </div>
      </template>
    </RightSidebar>
  </AuthenticatedLayout>
</template>
