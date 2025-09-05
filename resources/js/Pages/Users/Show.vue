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
import axios from 'axios';

const props = defineProps({ id: { type: Number, required: true } });

// user details
const loading = ref(false);
const error = ref('');
const user = ref(null);

const fullNameOrEmail = computed(() => user.value?.name || user.value?.email || `User #${props.id}`);

const fetchUser = async () => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(`/api/users/${props.id}`);
    user.value = data;
  } catch (e) {
    console.error('Failed to load user', e);
    error.value = e?.response?.data?.message || 'Failed to load user';
  } finally {
    loading.value = false;
  }
};

// Emails for this user (re-using lead UI structure)
const emails = ref([]);
const emailsLoading = ref(false);
const emailsError = ref('');
const emailPagination = ref({ current_page: 1, last_page: 1, total: 0 });

const fetchUserEmails = async (page = 1) => {
  emailsLoading.value = true;
  emailsError.value = '';
  try {
    const { data } = await axios.get(`/api/users/${props.id}/emails`, { params: { page } });
    emails.value = data.data || [];
    emailPagination.value = {
      current_page: data.current_page || 1,
      last_page: data.last_page || 1,
      total: data.total || (Array.isArray(data.data) ? data.data.length : 0),
    };
  } catch (e) {
    console.error('Failed to load user emails', e);
    emailsError.value = e?.response?.data?.message || 'Failed to load emails';
  } finally {
    emailsLoading.value = false;
  }
};

// Sidebar state
const sidebar = ref({ show: false, mode: null, title: '', data: null, loading: false });

const openCompose = () => {
  sidebar.value = { show: true, mode: 'custom-compose', title: 'Compose Email to User', data: null, loading: false };
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
  fetchUserEmails(emailPagination.value.current_page);
};

const changeEmailPage = (page) => {
  if (page < 1 || page > emailPagination.value.last_page) return;
  fetchUserEmails(page);
};

onMounted(async () => {
  await fetchUser();
  await fetchUserEmails(1);
});
</script>

<template>
  <Head :title="`User: ${fullNameOrEmail}`" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="flex items-center gap-3">
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">User Details</h2>
        </div>
        <div class="flex items-center gap-2">
          <SecondaryButton as="a" href="/users">Back to Users</SecondaryButton>
<!--          <PrimaryButton @click="openCompose">Compose Email</PrimaryButton>-->
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

            <div v-if="user && !loading" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <!-- Details -->
              <section class="lg:col-span-2 space-y-6">
                <div>
                  <h3 class="text-lg font-semibold mb-2">Profile</h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500">Name:</span> <span class="font-medium">{{ user.name }}</span></div>
                    <div v-if="user.email"><span class="text-gray-500">Email:</span> <span class="font-medium">{{ user.email }}</span></div>
                    <div v-if="user.role_data?.name || user.role"><span class="text-gray-500">Role:</span> <span class="font-medium">{{ user.role_data?.name || user.role }}</span></div>
                    <div v-if="user.user_type"><span class="text-gray-500">Type:</span> <span class="font-medium">{{ user.user_type }}</span></div>
                    <div v-if="user.timezone"><span class="text-gray-500">Timezone:</span> <span class="font-medium">{{ user.timezone }}</span></div>
                  </div>
                </div>

                <!-- Emails Section (mirrors LeadDetails) -->
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold">Emails</h3>
<!--                    <PrimaryButton @click="openCompose">Compose Email</PrimaryButton>-->
                  </div>
                  <div v-if="emailsLoading" class="text-gray-500 text-sm">Loading emails...</div>
                  <div v-else-if="emailsError" class="text-red-600 text-sm">{{ emailsError }}</div>
                  <div v-else-if="emails.length === 0" class="text-gray-500 text-sm">No emails found for this user.</div>
                  <div v-else class="overflow-x-auto shadow rounded-md border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
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

                  <!-- Pagination -->
                  <div v-if="emailPagination.last_page > 1" class="mt-3 flex items-center gap-2">
                    <SecondaryButton @click="changeEmailPage(emailPagination.current_page - 1)" :disabled="emailPagination.current_page === 1">Prev</SecondaryButton>
                    <span class="text-sm text-gray-600">Page {{ emailPagination.current_page }} of {{ emailPagination.last_page }}</span>
                    <SecondaryButton @click="changeEmailPage(emailPagination.current_page + 1)" :disabled="emailPagination.current_page === emailPagination.last_page">Next</SecondaryButton>
                  </div>
                </div>
              </section>

              <!-- Right column can be used for notes or other widgets in future -->
              <section class="space-y-6">
                <div class="p-4 bg-gray-50 rounded border border-gray-100 text-sm text-gray-600">Additional user widgets can go here.</div>
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
          <CustomComposeEmailContent :initialRecipientEmail="user?.email || ''" @submitted="handleSidebarSubmitted" />
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
