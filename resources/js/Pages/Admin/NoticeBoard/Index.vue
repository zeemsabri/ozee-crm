<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const activeTab = ref('create'); // 'create' | 'list'

const form = ref({
  title: '',
  description: '',
  url: '',
  type: 'General',
  visible_to_client: false,
  channels: ['push','email','silent'],
  user_ids: [],
  project_id: null,
});

const types = ['General','Warning','Updates','Final Notice'];

const saving = ref(false);
const errors = ref({});
const message = ref('');

const allUsers = ref([]);
const allProjects = ref([]);

const selectedTypeFilter = ref('');
const notices = ref([]);
const noticesLoading = ref(false);

const loadUsersAndProjects = async () => {
  try {
    const [usersRes, projectsRes] = await Promise.all([
      axios.get('/api/users', { params: { per_page: 200 } }),
      axios.get('/api/projects-simplified'),
    ]);
    // Normalize users list (supports both paginated and plain arrays)
    const usersData = usersRes.data?.data ?? usersRes.data;
    allUsers.value = Array.isArray(usersData) ? usersData : [];
    allProjects.value = Array.isArray(projectsRes.data) ? projectsRes.data : [];
  } catch (e) {
    console.error('Failed to load users or projects', e);
    allUsers.value = [];
    allProjects.value = [];
  }
};

const submit = async () => {
  errors.value = {};
  message.value = '';
  try {
    saving.value = true;
    const payload = { ...form.value };
    if (!payload.project_id) delete payload.project_id;
    if (!payload.user_ids || payload.user_ids.length === 0) delete payload.user_ids;
    await axios.post('/api/notices', payload);
    message.value = 'Notice created and notifications sent.';
    form.value = { title: '', description: '', url: '', type: 'General', visible_to_client: false, channels: ['push','email','silent'], user_ids: [], project_id: null };
    // Refresh list tab if open
    if (activeTab.value === 'list') await loadNotices();
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {};
    } else {
      message.value = 'Failed to create notice.';
    }
  } finally {
    saving.value = false;
  }
}

const listTypes = computed(() => ['All', ...types]);

const loadNotices = async () => {
  noticesLoading.value = true;
  try {
    const params = {};
    if (selectedTypeFilter.value && selectedTypeFilter.value !== 'All') params.type = selectedTypeFilter.value;
    const res = await axios.get('/api/notices', { params });
    const data = res.data?.data ?? res.data; // supports paginator
    notices.value = Array.isArray(data) ? data : (Array.isArray(res.data) ? res.data : []);
  } catch (e) {
    console.error('Failed to load notices', e);
    notices.value = [];
  } finally {
    noticesLoading.value = false;
  }
};

onMounted(async () => {
  await loadUsersAndProjects();
  if (activeTab.value === 'list') await loadNotices();
});
</script>

<template>
  <Head title="Notice Board" />
  <AuthenticatedLayout>
    <div class="py-12">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow sm:rounded-lg p-6">
          <div class="flex gap-4 border-b mb-4">
            <button class="pb-2 border-b-2" :class="activeTab==='create' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600'" @click="activeTab='create'">Create Notice</button>
            <button class="pb-2 border-b-2" :class="activeTab==='list' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600'" @click="(activeTab='list', loadNotices())">Existing Notices</button>
          </div>

          <div v-if="message" class="mb-4 text-sm" :class="message.includes('Failed') ? 'text-red-600' : 'text-green-600'">{{ message }}</div>

          <div v-if="activeTab==='create'" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Title</label>
              <input v-model="form.title" type="text" class="mt-1 block w-full border-gray-300 rounded-md" />
              <div v-if="errors.title" class="text-red-600 text-sm">{{ errors.title[0] }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <textarea v-model="form.description" rows="4" class="mt-1 block w-full border-gray-300 rounded-md"></textarea>
              <div v-if="errors.description" class="text-red-600 text-sm">{{ errors.description[0] }}</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Type</label>
                <select v-model="form.type" class="mt-1 block w-full border-gray-300 rounded-md">
                  <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                </select>
                <div v-if="errors.type" class="text-red-600 text-sm">{{ errors.type[0] }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">URL (optional)</label>
                <input v-model="form.url" type="url" class="mt-1 block w-full border-gray-300 rounded-md" />
                <div v-if="errors.url" class="text-red-600 text-sm">{{ errors.url[0] }}</div>
              </div>
            </div>

            <div class="flex items-center">
              <input id="visible_to_client" type="checkbox" v-model="form.visible_to_client" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
              <label for="visible_to_client" class="ml-2 block text-sm text-gray-700">Visible to Client</label>
            </div>

            <!-- Channels -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notification Channels</label>
              <div class="flex flex-wrap gap-4">
                <label class="inline-flex items-center gap-2">
                  <input type="checkbox" value="push" v-model="form.channels" class="rounded border-gray-300 text-indigo-600" />
                  <span>Push</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input type="checkbox" value="email" v-model="form.channels" class="rounded border-gray-300 text-indigo-600" />
                  <span>Email</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input type="checkbox" value="silent" v-model="form.channels" class="rounded border-gray-300 text-indigo-600" />
                  <span>Silent (DB)</span>
                </label>
              </div>
              <div v-if="errors.channels" class="text-red-600 text-sm mt-1">{{ errors.channels[0] }}</div>
            </div>

            <!-- Recipients -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Select Users (optional)</label>
                <select multiple v-model="form.user_ids" class="mt-1 block w-full border-gray-300 rounded-md min-h-[120px]">
                  <option v-for="u in allUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">If users are selected, notification will be sent only to them.</p>
                <div v-if="errors['user_ids.*']" class="text-red-600 text-sm">{{ errors['user_ids.*'][0] }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Select Project (optional)</label>
                <select v-model="form.project_id" class="mt-1 block w-full border-gray-300 rounded-md">
                  <option :value="null">-- None --</option>
                  <option v-for="p in allProjects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">If no users are selected and a project is selected, all users on that project will be notified.</p>
                <div v-if="errors.project_id" class="text-red-600 text-sm">{{ errors.project_id[0] }}</div>
              </div>
            </div>

            <div class="mt-6">
              <button @click="submit" :disabled="saving" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                {{ saving ? 'Saving...' : 'Create Notice' }}
              </button>
            </div>
          </div>

          <div v-else>
            <div class="flex items-center gap-2 mb-4">
              <label class="text-sm text-gray-700">Filter by Type:</label>
              <select v-model="selectedTypeFilter" @change="loadNotices" class="border-gray-300 rounded-md">
                <option v-for="t in listTypes" :key="t" :value="t">{{ t }}</option>
              </select>
              <button @click="loadNotices" class="ml-auto text-sm px-3 py-1.5 border rounded">Refresh</button>
            </div>
            <div v-if="noticesLoading" class="text-gray-500 text-sm">Loading...</div>
            <div v-else class="divide-y">
              <div v-for="n in notices" :key="n.id" class="py-3">
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium">{{ n.title }}</div>
                    <div class="text-xs text-gray-500">{{ n.type }} • {{ new Date(n.created_at).toLocaleString() }}</div>
                    <div class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ n.description }}</div>
                  </div>
                  <div v-if="n.url" class="text-right">
                    <a :href="`/notices/${n.id}/redirect`" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm">Open Link</a>
                  </div>
                </div>
              </div>
              <div v-if="!notices || notices.length===0" class="text-sm text-gray-500">No notices found.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
