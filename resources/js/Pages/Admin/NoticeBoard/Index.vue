<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

// Heroicons imports
import { BellAlertIcon, MegaphoneIcon, ArrowTopRightOnSquareIcon, CheckBadgeIcon, XMarkIcon, ExclamationTriangleIcon, MegaphoneIcon as SolidMegaphoneIcon } from '@heroicons/vue/24/solid';
import { PlusCircleIcon, ListBulletIcon, ArrowPathIcon, UsersIcon } from '@heroicons/vue/24/outline';


const activeTab = ref('create'); // 'create' | 'list'

const form = ref({
    title: '',
    description: '',
    url: '',
    type: 'General',
    visible_to_client: false,
    channels: ['push'],
    user_ids: [],
    project_id: null,
    file: null,
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
const authUser = computed(() => usePage().props.auth.user);


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
        let config = {};
        let payload;
        if (form.value.file) {
            // Use multipart when a file is selected
            const fd = new FormData();
            fd.append('title', form.value.title || '');
            if (form.value.description) fd.append('description', form.value.description);
            if (form.value.url) fd.append('url', form.value.url);
            fd.append('type', form.value.type);
            fd.append('visible_to_client', form.value.visible_to_client ? '1' : '0');
            // channels[] for arrays
            (form.value.channels || []).forEach(ch => fd.append('channels[]', ch));
            (form.value.user_ids || []).forEach(uid => fd.append('user_ids[]', uid));
            if (form.value.project_id) fd.append('project_id', form.value.project_id);
            fd.append('file', form.value.file);
            payload = fd;
            config.headers = { 'Content-Type': 'multipart/form-data' };
        } else {
            // JSON when no file
            payload = { ...form.value };
            if (!payload.project_id) delete payload.project_id;
            if (!payload.user_ids || payload.user_ids.length === 0) delete payload.user_ids;
            delete payload.file;
        }
        await axios.post('/api/notices', payload, config);
        message.value = 'Notice created and notifications sent.';
        form.value = { title: '', description: '', url: '', type: 'General', visible_to_client: false, channels: ['push'], user_ids: [], project_id: null, file: null };
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

const getNoticeBadgeColor = (type) => {
    switch (type) {
        case 'Warning': return 'bg-red-100 text-red-800';
        case 'Updates': return 'bg-blue-100 text-blue-800';
        case 'Final Notice': return 'bg-yellow-100 text-yellow-800';
        case 'General':
        default: return 'bg-indigo-100 text-indigo-800';
    }
};

const getReadStatus = (notice) => {
    if (!notice.users_with_interactions || !authUser.value) {
        return { isRead: false, readers: [] };
    }
    const interactions = notice.users_with_interactions.find(i => i.user.id === authUser.value.id);
    const isRead = !!interactions && interactions.interactions.some(int => int.type === 'read');
    const readers = notice.users_with_interactions.map(i => i.user.name);
    return { isRead, readers };
};

const formatDateTime = (value) => {
    if (!value) return '';
    try {
        const d = new Date(value);
        return d.toLocaleString();
    } catch (e) {
        return String(value);
    }
};

const interactionLabel = (type) => {
    switch (type) {
        case 'read': return 'Read';
        case 'click': return 'Click';
        case 'email_open': return 'Email Open';
        default: return type;
    }
};

const getUserOptions = computed(() => {
    return allUsers.value.map(user => ({
        value: user.id,
        label: `${user.name} (${user.email})`
    }));
});

onMounted(async () => {
    await loadUsersAndProjects();
    if (activeTab.value === 'list') await loadNotices();
});
</script>

<template>
    <Head title="Notice Board" />
    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-xl sm:rounded-2xl p-6">
                    <!-- Tab Navigation -->
                    <div class="flex items-center justify-between border-b pb-4 mb-8">
                        <h2 class="text-3xl font-extrabold text-gray-900">Notice Board</h2>
                        <div class="flex space-x-2">
                            <button
                                class="flex items-center px-4 py-2 rounded-xl transition-colors duration-200"
                                :class="activeTab==='create' ? 'bg-indigo-500 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100'"
                                @click="activeTab='create'">
                                <PlusCircleIcon class="h-5 w-5 mr-2" />
                                Create Notice
                            </button>
                            <button
                                class="flex items-center px-4 py-2 rounded-xl transition-colors duration-200"
                                :class="activeTab==='list' ? 'bg-indigo-500 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100'"
                                @click="(activeTab='list', loadNotices())">
                                <ListBulletIcon class="h-5 w-5 mr-2" />
                                Existing Notices
                            </button>
                        </div>
                    </div>

                    <div v-if="message" class="mb-6 px-4 py-3 rounded-xl" :class="message.includes('Failed') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'">
                        {{ message }}
                    </div>

                    <!-- Create Notice Form -->
                    <div v-if="activeTab==='create'" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Title</label>
                            <input v-model="form.title" type="text" class="mt-1 block w-full rounded-xl shadow-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" />
                            <div v-if="errors.title" class="text-red-600 text-sm mt-1">{{ errors.title[0] }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea v-model="form.description" rows="4" class="mt-1 block w-full rounded-xl shadow-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            <div v-if="errors.description" class="text-red-600 text-sm mt-1">{{ errors.description[0] }}</div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Type</label>
                                <select v-model="form.type" class="mt-1 block w-full rounded-xl shadow-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                                </select>
                                <div v-if="errors.type" class="text-red-600 text-sm mt-1">{{ errors.type[0] }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">URL (optional)</label>
                                <input v-model="form.url" type="url" class="mt-1 block w-full rounded-xl shadow-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" />
                                <div v-if="errors.url" class="text-red-600 text-sm mt-1">{{ errors.url[0] }}</div>
                            </div>
                        </div>

                        <!-- Upload (image or document) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Upload Image or Document (optional)</label>
                            <input type="file" accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                   class="mt-1 block w-full text-sm text-gray-700"
                                   @change="e => form.file = e.target.files && e.target.files[0] ? e.target.files[0] : null" />
                            <p class="text-xs text-gray-500 mt-1">If an image is uploaded, a thumbnail will be generated and shown on the notice.</p>
                            <div v-if="errors.file" class="text-red-600 text-sm mt-1">{{ errors.file[0] }}</div>
                        </div>

                        <div class="flex items-center">
                            <input id="visible_to_client" type="checkbox" v-model="form.visible_to_client" class="h-5 w-5 text-indigo-600 rounded-lg border-gray-300 focus:ring-indigo-500" />
                            <label for="visible_to_client" class="ml-2 block text-sm text-gray-700">Visible to Client</label>
                        </div>

                        <!-- Channels -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notification Channels</label>
                            <div class="flex flex-wrap gap-4">
                                <label class="inline-flex items-center gap-2 cursor-pointer transition-transform duration-100 hover:scale-105">
                                    <input type="checkbox" value="push" v-model="form.channels" class="h-5 w-5 rounded-lg border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-gray-700">Push</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer transition-transform duration-100 hover:scale-105">
                                    <input type="checkbox" value="email" v-model="form.channels" class="h-5 w-5 rounded-lg border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-gray-700">Email</span>
                                </label>
<!--                                <label class="inline-flex items-center gap-2 cursor-pointer transition-transform duration-100 hover:scale-105">-->
<!--                                    <input type="checkbox" value="silent" v-model="form.channels" class="h-5 w-5 rounded-lg border-gray-300 text-indigo-600 focus:ring-indigo-500" />-->
<!--                                    <span class="text-gray-700">Silent (DB)</span>-->
<!--                                </label>-->
                            </div>
                            <div v-if="errors.channels" class="text-red-600 text-sm mt-1">{{ errors.channels[0] }}</div>
                        </div>

                        <!-- Recipients -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Select Users (optional)</label>
                                <MultiSelectDropdown
                                    :options="getUserOptions"
                                    :is-multi="true"
                                    v-model="form.user_ids"
                                    placeholder="Select users to notify..."
                                />
                                <p class="text-xs text-gray-500 mt-1">If users are selected, notification will be sent only to them.</p>
                                <div v-if="errors['user_ids.*']" class="text-red-600 text-sm">{{ errors['user_ids.*'][0] }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Select Project (optional)</label>
                                <select v-model="form.project_id" class="mt-1 block w-full rounded-xl shadow-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option :value="null">-- None --</option>
                                    <option v-for="p in allProjects" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">If no users are selected and a project is selected, all users on that project will be notified.</p>
                                <div v-if="errors.project_id" class="text-red-600 text-sm">{{ errors.project_id[0] }}</div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <PrimaryButton @click="submit" :disabled="saving">
                <span v-if="saving" class="flex items-center">
                  <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Saving...
                </span>
                                <span v-else>Create Notice</span>
                            </PrimaryButton>
                        </div>
                    </div>

                    <!-- Existing Notices List -->
                    <div v-else>
                        <div class="flex items-center justify-between gap-2 mb-4">
                            <div class="flex items-center space-x-2">
                                <label class="text-sm font-medium text-gray-700">Filter by Type:</label>
                                <select v-model="selectedTypeFilter" @change="loadNotices" class="border-gray-300 rounded-xl shadow-sm">
                                    <option v-for="t in listTypes" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>
                            <SecondaryButton @click="loadNotices" class="flex items-center gap-1">
                                <ArrowPathIcon class="w-4 h-4" />
                                Refresh
                            </SecondaryButton>
                        </div>
                        <div v-if="noticesLoading" class="text-center p-8">
                            <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-gray-500 text-sm mt-2">Loading notices...</p>
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="n in notices" :key="n.id" class="bg-gray-50 p-6 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                      <span :class="['inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium', getNoticeBadgeColor(n.type)]">
                        <BellAlertIcon v-if="n.type === 'Warning'" class="w-3 h-3 mr-1" />
                        <MegaphoneIcon v-else-if="n.type === 'Updates'" class="w-3 h-3 mr-1" />
                        <CheckBadgeIcon v-else-if="n.type === 'Final Notice'" class="w-3 h-3 mr-1" />
                        <MegaphoneIcon v-else class="w-3 h-3 mr-1" />
                        {{ n.type }}
                      </span>
                                            <h4 class="text-lg font-bold text-gray-900 truncate">{{ n.title }}</h4>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-2">
                                            <span class="mr-2">Posted on: {{ new Date(n.created_at).toLocaleDateString() }} at {{ new Date(n.created_at).toLocaleTimeString() }}</span>
                                        </div>
                                        <div class="text-sm text-gray-700 mt-2 whitespace-pre-line leading-relaxed">{{ n.description || 'No description provided.' }}</div>
                                        <div v-if="n.users_with_interactions && n.users_with_interactions.length" class="mt-4 pt-3 border-t border-gray-200">
                                            <div class="flex items-center gap-2 mb-2 text-xs font-semibold text-gray-600">
                                                <UsersIcon class="w-4 h-4" />
                                                <span>Interactions</span>
                                            </div>
                                            <div class="space-y-2">
                                                <div v-for="u in n.users_with_interactions" :key="u.user.id" class="">
                                                    <div class="text-sm font-medium text-gray-800">{{ u.user.name }}</div>
                                                    <div class="flex flex-wrap gap-2 mt-1">
                                                        <span v-for="(it, idx) in u.interactions" :key="idx" class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">
                                                            {{ interactionLabel(it.type) }} • {{ formatDateTime(it.created_at) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="n.url" class="flex-shrink-0 ml-4 text-right">
                                        <a :href="`/notices/${n.id}/redirect`" target="_blank" class="flex items-center text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                            Open Link
                                            <ArrowTopRightOnSquareIcon class="h-4 w-4 ml-1" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!notices || notices.length===0" class="text-center text-gray-500 p-8">No notices found.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
