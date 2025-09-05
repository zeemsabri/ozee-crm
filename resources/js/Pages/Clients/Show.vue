<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import CustomComposeEmailContent from '@/Pages/Emails/Inbox/Components/CustomComposeEmailContent.vue';
import CustomEmailApprovalContent from '@/Pages/Emails/Inbox/Components/CustomEmailApprovalContent.vue';
import EmailDetailsContent from '@/Pages/Emails/Inbox/Components/EmailDetailsContent.vue';
import EmailActionContent from '@/Pages/Emails/Inbox/Components/EmailActionContent.vue';
import ReceivedEmailActionContent from '@/Pages/Emails/Inbox/Components/ReceivedEmailActionContent.vue';

const props = defineProps({ id: { type: Number, required: true } });

const loading = ref(true);
const error = ref('');
const data = ref({ client: null, lead: null, presentations: [] });

// notes state for client
const notes = ref([]);
const notesLoading = ref(false);
const notesError = ref('');
const savingNote = ref(false);
const noteInput = ref('');

// emails paginated
const emails = ref([]);
const emailsLoading = ref(false);
const emailsError = ref('');
const emailPagination = ref({ current_page: 1, last_page: 1, total: 0 });

// sidebar
const sidebar = ref({ show: false, mode: null, title: '', data: null, loading: false });

onMounted(async () => {
  await fetchDetails();
  await fetchClientEmails(1);
  await fetchNotes();
});

const fetchDetails = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await window.axios.get(`/api/clients/${props.id}/details`);
    data.value = res?.data || data.value;
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load client details';
  } finally {
    loading.value = false;
  }
};

const fetchClientEmails = async (page = 1) => {
  emailsLoading.value = true;
  emailsError.value = '';
  try {
    const { data: resp } = await window.axios.get(`/api/clients/${props.id}/emails`, { params: { page } });
    emails.value = resp.data || [];
    emailPagination.value = {
      current_page: resp.current_page || 1,
      last_page: resp.last_page || 1,
      total: resp.total || (Array.isArray(resp.data) ? resp.data.length : 0),
    };
  } catch (e) {
    emailsError.value = e?.response?.data?.message || 'Failed to load emails';
  } finally {
    emailsLoading.value = false;
  }
};

const changeEmailPage = (page) => {
  if (page < 1 || page > emailPagination.value.last_page) return;
  fetchClientEmails(page);
};

const fetchNotes = async () => {
  notesLoading.value = true;
  notesError.value = '';
  try {
    const { data: resp } = await window.axios.get('/api/project_notes', {
      params: { noteable_id: props.id, noteable_type: 'App\\Models\\Client' },
    });
    notes.value = Array.isArray(resp) ? resp : (resp?.data ?? []);
  } catch (e) {
    notesError.value = e?.response?.data?.message || 'Failed to load notes';
  } finally {
    notesLoading.value = false;
  }
};

const addNote = async () => {
  if (!noteInput.value || !noteInput.value.trim()) return;
  try {
    savingNote.value = true;
    await window.axios.post('/api/project_notes', {
      body: noteInput.value,
      noteable_id: props.id,
      noteable_type: 'App\\Models\\Client',
    });
    noteInput.value = '';
    await fetchNotes();
  } catch (e) {
    notesError.value = e?.response?.data?.message || 'Failed to save note';
  } finally {
    savingNote.value = false;
  }
};

const client = computed(() => data.value.client || {});
const lead = computed(() => data.value.lead || null);
const presentations = computed(() => Array.isArray(data.value.presentations) ? data.value.presentations : []);

function fullLeadName(l) {
  if (!l) return '';
  const fn = (l.first_name || '').trim();
  const ln = (l.last_name || '').trim();
  return [fn, ln].filter(Boolean).join(' ');
}

// sidebar actions
const openCompose = () => {
  sidebar.value = { show: true, mode: 'custom-compose', title: 'Compose Email to Client', data: null, loading: false };
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
  fetchClientEmails(emailPagination.value.current_page);
};
</script>

<template>
  <Head :title="`Client: ${client.name || '#' + props.id}`" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="flex items-center gap-3">
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">Client Details</h2>
          <span v-if="lead" class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700" title="Linked Lead">
            Linked Lead
          </span>
        </div>
        <div class="flex items-center gap-2">
          <SecondaryButton as="a" :href="'/clients'">Back to Clients</SecondaryButton>
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

            <div v-if="!loading && !error" class="space-y-8">
              <!-- Profile -->
              <section>
                <h3 class="text-lg font-semibold mb-2">Profile</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                  <div><span class="text-gray-500">Name:</span> <span class="font-medium">{{ client.name || '-' }}</span></div>
                  <div v-if="client.email"><span class="text-gray-500">Email:</span> <span class="font-medium">{{ client.email }}</span></div>
                  <div v-if="client.phone"><span class="text-gray-500">Phone:</span> <span class="font-medium">{{ client.phone }}</span></div>
                  <div v-if="client.address"><span class="text-gray-500">Address:</span> <span class="font-medium">{{ client.address }}</span></div>
                  <div v-if="client.notes"><span class="text-gray-500">Notes:</span> <span class="font-medium">{{ client.notes }}</span></div>
                </div>
                <div v-if="lead" class="mt-4 p-4 rounded-md bg-amber-50 border border-amber-100">
                  <div class="text-sm text-amber-800"><strong>Originated from Lead:</strong> {{ fullLeadName(lead) || ('#' + lead.id) }} <span v-if="lead.email">• {{ lead.email }}</span></div>
                </div>
              </section>

              <!-- Presentations -->
              <section>
                <h3 class="text-lg font-semibold mb-2">Presentations</h3>
                <div v-if="presentations.length === 0" class="text-sm text-gray-500">No presentations found.</div>
                <ul v-else class="divide-y divide-gray-200 rounded-md border border-gray-200">
                  <li v-for="p in presentations" :key="p.id" class="p-3 flex items-center justify-between">
                    <div>
                      <div class="font-medium">{{ p.title }}</div>
                      <div class="text-xs text-gray-500">Type: {{ p.type }} • Source: {{ p.source }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                      <PrimaryButton as="a" :href="`/view/${p.share_token}`" target="_blank" title="Open public preview">Open</PrimaryButton>
                    </div>
                  </li>
                </ul>
              </section>

              <!-- Emails -->
              <section>
                <div class="flex items-center justify-between mb-2">
                  <h3 class="text-lg font-semibold">Emails</h3>
                  <PrimaryButton @click="openCompose">Compose Email</PrimaryButton>
                </div>
                <div v-if="emailsLoading" class="text-gray-500 text-sm">Loading emails...</div>
                <div v-else-if="emailsError" class="text-red-600 text-sm">{{ emailsError }}</div>
                <div v-else-if="emails.length === 0" class="text-sm text-gray-500">No emails found.</div>
                <div v-else class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-2 text-left text-gray-500">Subject</th>
                        <th class="px-4 py-2 text-left text-gray-500">Status</th>
                        <th class="px-4 py-2 text-left text-gray-500">Type</th>
                        <th class="px-4 py-2 text-left text-gray-500">Created</th>
                        <th class="px-4 py-2 text-left text-gray-500">Actions</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                      <tr v-for="e in emails" :key="e.id">
                        <td class="px-4 py-2">{{ e.subject || '(no subject)' }}</td>
                        <td class="px-4 py-2">{{ e.status }}</td>
                        <td class="px-4 py-2">{{ e.type }}</td>
                        <td class="px-4 py-2">{{ new Date(e.created_at).toLocaleString() }}</td>
                        <td class="px-4 py-2">
                          <div class="flex items-center gap-2">
                            <PrimaryButton @click="openApproval(e)">View</PrimaryButton>
                            <PrimaryButton @click="handleEditEmail(e)">Edit</PrimaryButton>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-if="emailPagination.last_page > 1" class="mt-3 flex items-center gap-2">
                  <SecondaryButton @click="changeEmailPage(emailPagination.current_page - 1)" :disabled="emailPagination.current_page === 1">Prev</SecondaryButton>
                  <span class="text-sm text-gray-600">Page {{ emailPagination.current_page }} of {{ emailPagination.last_page }}</span>
                  <SecondaryButton @click="changeEmailPage(emailPagination.current_page + 1)" :disabled="emailPagination.current_page === emailPagination.last_page">Next</SecondaryButton>
                </div>
              </section>

              <!-- Notes -->
              <section>
                <div class="flex items-center justify-between mb-2">
                  <h3 class="text-lg font-semibold">Notes</h3>
                </div>
                <div v-if="notesLoading" class="text-gray-500 text-sm">Loading notes...</div>
                <div v-else-if="notesError" class="text-red-600 text-sm">{{ notesError }}</div>
                <div v-else>
                  <ul v-if="notes.length" class="space-y-2 mb-4">
                    <li v-for="n in notes" :key="n.id" class="p-3 bg-gray-50 border border-gray-200 rounded">
                      <div class="text-sm text-gray-800" v-html="n.body"></div>
                      <div class="text-xs text-gray-500 mt-1">{{ new Date(n.created_at).toLocaleString() }}</div>
                    </li>
                  </ul>
                  <div v-else class="text-sm text-gray-500 mb-4">No notes yet.</div>

                  <div>
                    <textarea v-model="noteInput" rows="3" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" placeholder="Add a note..."></textarea>
                    <div class="mt-2 flex justify-end">
                      <PrimaryButton :disabled="savingNote" @click="addNote">{{ savingNote ? 'Saving...' : 'Add Note' }}</PrimaryButton>
                    </div>
                  </div>
                </div>
              </section>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <RightSidebar v-model:show="sidebar.show" :title="sidebar.title">
      <template #content>
        <template v-if="sidebar.mode === 'custom-compose'">
          <CustomComposeEmailContent :initialRecipientEmail="client?.email || ''" @submitted="handleSidebarSubmitted" />
        </template>
        <template v-else-if="sidebar.mode === 'view-email'">
          <EmailDetailsContent :email="sidebar.data" />
        </template>
        <template v-else-if="sidebar.mode === 'edit'">
          <EmailActionContent :email="sidebar.data" mode="edit" @submitted="handleSidebarSubmitted" />
        </template>
        <template v-else-if="sidebar.mode === 'custom-edit'">
          <CustomEmailApprovalContent :email="sidebar.data" mode="edit" @submitted="handleSidebarSubmitted" />
        </template>
        <template v-else-if="sidebar.mode === 'received-edit'">
          <ReceivedEmailActionContent :email="sidebar.data" mode="edit" @submitted="handleSidebarSubmitted" />
        </template>
      </template>
    </RightSidebar>
  </AuthenticatedLayout>
</template>

<style scoped>
</style>
