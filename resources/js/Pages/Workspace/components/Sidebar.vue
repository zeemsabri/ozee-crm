<script setup>
import { ref, onMounted } from 'vue';
import ChecklistComponent from "@/Components/ChecklistComponent.vue";
import { Link, usePage } from '@inertiajs/vue3';
import NoticeboardModal from '@/Components/Notices/NoticeboardModal.vue';

const props = defineProps({
    checklistItems: Array,
    notes: String,
});

const emits = defineEmits(['add-checklist-item', 'remove-checklist-item', 'update-notes']);

const newChecklistItemInput = ref(null);

// Leaderboard stats state
const loading = ref(true);
const points = ref(0);
const rank = ref(null);

// User workspace state
const userChecklist = ref([]);
const notesText = ref('');
const notesTimer = ref(null);
const savingNotes = ref(false);

// Company Updates (Notices) state
const notices = ref([]);
const noticesLoading = ref(false);
const noticesError = ref(null);
const showNoticeModal = ref(false);
const modalNotices = ref([]);

onMounted(async () => {
    try {
        const user = usePage().props.auth?.user;
        noticesLoading.value = true;
        const [statsResp, lbResp, wsResp, noticesResp] = await Promise.all([
            window.axios.get('/api/leaderboard/stats'),
            window.axios.get('/api/leaderboard/monthly'),
            window.axios.get('/api/user/workspace'),
            window.axios.get('/api/notices/unread')
        ]);

        // Points from stats
        console.log('fetching points');
        console.log(statsResp);
        points.value = statsResp?.data?.userMonthlyPoints ?? 0;

        // Compute rank from monthly leaderboard
        const list = Array.isArray(lbResp?.data?.leaderboard) ? lbResp.data.leaderboard : [];
        if (user && list.length > 0) {
            const idx = list.findIndex(u => String(u.id) === String(user.id));
            rank.value = idx >= 0 ? (idx + 1) : null;
        } else {
            rank.value = null;
        }

        // Workspace data
        userChecklist.value = Array.isArray(wsResp?.data?.checklist) ? wsResp.data.checklist : [];
        notesText.value = wsResp?.data?.notes ?? '';

        // Notices data
        notices.value = Array.isArray(noticesResp?.data?.data) ? noticesResp.data.data : [];
        noticesError.value = null;
    } catch (e) {
        console.error('Failed to load leaderboard/workspace data', e);
        points.value = 0;
        rank.value = null;
        userChecklist.value = [];
        notesText.value = '';
        notices.value = [];
        noticesError.value = 'Failed to load updates';
    } finally {
        loading.value = false;
        noticesLoading.value = false;
    }
});

async function saveChecklist() {
    try {
        await window.axios.put('/api/user/checklist', { items: userChecklist.value });
    } catch (e) {
        console.error('Failed to save checklist', e);
    }
}

// Notices helpers
function trimText(text, max = 140) {
    if (!text) return '';
    if (text.length <= max) return text;
    return text.slice(0, max).trim() + '…';
}

async function fetchUnreadNotices() {
    try {
        noticesLoading.value = true;
        const { data } = await window.axios.get('/api/notices/unread');
        notices.value = Array.isArray(data?.data) ? data.data : [];
        noticesError.value = null;
    } catch (e) {
        console.error('Failed to load updates', e);
        notices.value = [];
        noticesError.value = 'Failed to load updates';
    } finally {
        noticesLoading.value = false;
    }
}

function openNotice(n) {
    modalNotices.value = [n];
    showNoticeModal.value = true;
}

function openAllNotices() {
    modalNotices.value = notices.value.slice();
    if (modalNotices.value.length) {
        showNoticeModal.value = true;
    }
}

function onCloseNoticeModal() {
    showNoticeModal.value = false;
    fetchUnreadNotices();
}

async function addItem() {
    const text = newChecklistItemInput.value?.value?.trim();
    if (text) {
        const newItems = [...userChecklist.value, { name: text, completed: false }];
        userChecklist.value = newItems;
        emits('add-checklist-item', text);
        try { await saveChecklist(); } catch {}
        newChecklistItemInput.value.value = '';
        newChecklistItemInput.value.focus();
    }
}

function onChecklistUpdate(items) {
    userChecklist.value = items;
}

function updateNotes(event) {
    const value = event.target.value;
    notesText.value = value;
    emits('update-notes', value);
    if (notesTimer.value) clearTimeout(notesTimer.value);
    notesTimer.value = setTimeout(async () => {
        try {
            savingNotes.value = true;
            await window.axios.put('/api/user/notes', { text: notesText.value });
        } catch (e) {
            console.error('Failed to update notes', e);
        } finally {
            savingNotes.value = false;
        }
    }, 600);
}
</script>

<template>
    <div>
        <!-- My Performance -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">My Performance</h3>
            <div class="flex items-center justify-between mb-4">
                <div class="flex-1">
                    <p class="text-sm text-gray-500">Total Points</p>
                    <span class="text-5xl font-bold text-indigo-600">{{ loading ? '—' : (points ?? 0) }}</span>
                </div>
                <div class="flex-1 text-right">
                    <p class="text-sm text-gray-500">Leaderboard Rank</p>
                    <span class="text-5xl font-bold text-gray-900">{{ loading ? '#' : (rank ? `#${rank}` : '#-') }}</span>
                </div>
            </div>
            <Link :href="route('leaderboard.index')" class="block text-center text-indigo-600 text-sm font-medium mt-4 hover:underline transition-all-colors">View Leaderboard Details →</Link>
        </div>

        <!-- My Checklist -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">My Checklist</h3>

            <div class="flex items-center space-x-2 mb-4">
                <input ref="newChecklistItemInput" type="text" class="flex-1 p-2 border rounded-lg"
                       placeholder="Add a new checklist item..." @keyup.enter="addItem" />
                <button @click="addItem" class="px-3 py-2 bg-indigo-600 text-white rounded-lg">Add</button>
            </div>

            <ChecklistComponent
                :items="userChecklist"
                api-endpoint="/api/user/checklist"
                title=""
                container-class="mt-2"
                :success-message="'Checklist updated'"
                :error-message="'Failed to update checklist'"
                @update:items="onChecklistUpdate"
            />

            <h3 class="text-xl font-semibold text-gray-900 mb-4 mt-6">My Notes</h3>
            <div class="space-y-3">
                <textarea :value="notesText" @input="updateNotes" class="w-full h-32 p-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Write your notes here..."></textarea>
                <p v-if="savingNotes" class="text-xs text-gray-500">Saving...</p>
            </div>
        </div>

        <!-- Company Updates -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">Company Updates</h3>

            <div v-if="noticesLoading" class="text-sm text-gray-500">Loading updates...</div>
            <div v-else-if="noticesError" class="text-sm text-red-600">
                {{ noticesError }}
                <button class="ml-2 text-indigo-600 underline" @click="fetchUnreadNotices">Retry</button>
            </div>
            <div v-else>
                <div v-if="!notices || notices.length === 0" class="text-sm text-gray-500">
                    You're all caught up. No new updates.
                </div>
                <ul v-else class="space-y-3">
                    <li v-for="(n, idx) in notices.slice(0,3)" :key="n.id" class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-800 font-semibold truncate">{{ n.title }}</p>
                        <p class="text-xs text-gray-600 mt-1">{{ trimText(n.description, 120) }}</p>
                        <div class="mt-2">
                            <button class="text-indigo-600 text-xs font-medium hover:underline" @click="openNotice(n)">View</button>
                        </div>
                    </li>
                </ul>
                <div v-if="notices.length > 3" class="mt-3">
                    <button class="text-indigo-600 text-sm font-medium hover:underline" @click="openAllNotices">View all ({{ notices.length }})</button>
                </div>
            </div>
        </div>

        <NoticeboardModal :show="showNoticeModal" :unread-notices="modalNotices" @close="onCloseNoticeModal" />
    </div>
</template>
