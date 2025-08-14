<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import { usePermissions } from '@/Directives/permissions';

const { canDo } = usePermissions();
const canApprove = canDo('approve_kudos');

const loading = ref(true);
const error = ref('');
const successMessage = ref('');

const pendingKudos = ref([]);
const myKudos = ref([]);

// Video modal state
const showVideo = ref(false);
const videoKey = ref(0);
// First-visit attention state
const isFirstVisit = ref(false);
const openVideo = () => { showVideo.value = true; isFirstVisit.value = false; };
const closeVideo = () => { showVideo.value = false; videoKey.value++; };

const fetchData = async () => {
    loading.value = true;
    error.value = '';
    try {
        // Always load my received kudos
        const mineResp = await window.axios.get('/api/kudos/mine');
        myKudos.value = mineResp.data || [];

        // Load pending approvals only if user can approve
        if (canApprove) {
            const pendingResp = await window.axios.get('/api/kudos/pending');
            pendingKudos.value = pendingResp.data || [];
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
        successMessage.value = 'Kudo approved!';
        await fetchData();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to approve kudo';
    }
};

const rejectKudo = async (kudo) => {
    try {
        await window.axios.post(`/api/kudos/${kudo.id}/reject`);
        successMessage.value = 'Kudo rejected.';
        await fetchData();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to reject kudo';
    }
};

// Computed properties for the stats cards
const totalKudosReceived = computed(() => myKudos.value.length);
const approvedKudosCount = computed(() => myKudos.value.filter(k => k.is_approved).length);
const pendingKudosCount = computed(() => myKudos.value.filter(k => !k.is_approved).length);

onMounted(() => {
    try {
        if (!localStorage.getItem('kudos_video_prompted')) {
            isFirstVisit.value = true;
            localStorage.setItem('kudos_video_prompted', '1');
            setTimeout(() => { isFirstVisit.value = false; }, 8000);
        }
    } catch (e) {
        // ignore storage errors
    }
    fetchData();
});
</script>

<template>
    <Head title="Kudos" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kudos</h2>
        </template>

        <div class="py-12 bg-gray-50">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- Success/Error Messages -->
                <div v-if="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
                    <span class="block sm:inline">{{ successMessage }}</span>
                </div>
                <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
                    <span class="block sm:inline">{{ error }}</span>
                </div>

                <!-- The Power of Recognition Section -->
                <div class="bg-white rounded-lg shadow-lg p-8 text-center animate-fade-in-up">
                    <p class="text-4xl font-bold text-gray-800 mb-2">The Power of Recognition!</p>
                    <p class="text-lg text-gray-600">Giving and receiving Kudos builds a culture of appreciation and teamwork.</p>
                    <div class="mt-6">
                        <SecondaryButton
                            @click="openVideo"
                            class="mx-auto block px-6 py-3"
                            :class="isFirstVisit ? 'animate-bounce ring-4 ring-purple-300 shadow-2xl' : ''"
                            aria-label="Watch Kudos Feature Walkthrough"
                        >
                            Watch Video
                        </SecondaryButton>
                    </div>
                </div>

                <div v-if="loading" class="text-center text-gray-600">
                    <svg class="animate-spin h-8 w-8 text-gray-400 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2">Loading kudos...</p>
                </div>

                <template v-else>
                    <!-- Pending Kudos for Manager -->
                    <div v-if="canApprove" class="space-y-4">
                        <h3 class="text-3xl font-extrabold text-gray-800">Kudos Pending Approval ✨</h3>
                        <div v-if="pendingKudos.length === 0" class="p-6 text-gray-600 border border-gray-200 rounded-lg bg-white shadow-sm">
                            No new kudos are waiting for your approval.
                        </div>
                        <div v-else class="grid md:grid-cols-2 gap-4">
                            <div v-for="k in pendingKudos" :key="k.id" class="bg-white rounded-xl shadow-lg p-6 flex flex-col justify-between transition-all duration-300 hover:shadow-xl">
                                <div>
                                    <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                                        <span>From: <strong>{{ k.sender?.name }}</strong></span>
                                        <span>→</span>
                                        <span>To: <strong>{{ k.recipient?.name }}</strong></span>
                                    </div>
                                    <div class="font-semibold text-lg text-gray-900 mb-2">Project: {{ k.project?.name || 'N/A' }}</div>
                                    <p class="text-gray-700 italic border-l-4 border-yellow-400 pl-3">"{{ k.comment }}"</p>
                                </div>
                                <div class="mt-4 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                                    <PrimaryButton @click="approve(k)" class="w-full sm:w-auto">Approve</PrimaryButton>
                                    <DangerButton @click="rejectKudo(k)" class="w-full sm:w-auto">Reject</DangerButton>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- My Kudos Section -->
                    <div class="space-y-4">
                        <h3 class="text-3xl font-extrabold text-gray-800">Kudos I've Received 💖</h3>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white rounded-lg shadow-md p-6 text-center border-b-4 border-purple-400">
                                <p class="text-4xl font-bold text-gray-800">{{ totalKudosReceived }}</p>
                                <p class="text-sm text-gray-500">Total Kudos</p>
                            </div>
                            <div class="bg-white rounded-lg shadow-md p-6 text-center border-b-4 border-green-400">
                                <p class="text-4xl font-bold text-gray-800">{{ approvedKudosCount }}</p>
                                <p class="text-sm text-gray-500">Approved Kudos</p>
                            </div>
                            <div class="bg-white rounded-lg shadow-md p-6 text-center border-b-4 border-yellow-400">
                                <p class="text-4xl font-bold text-gray-800">{{ pendingKudosCount }}</p>
                                <p class="text-sm text-gray-500">Pending Kudos</p>
                            </div>
                        </div>

                        <div v-if="myKudos.length === 0" class="p-6 text-gray-600 border border-gray-200 rounded-lg bg-white shadow-sm">
                            You haven't received any kudos yet. Keep up the great work!
                        </div>
                        <div v-else class="grid md:grid-cols-2 gap-4">
                            <div v-for="k in myKudos" :key="k.id" class="bg-white rounded-xl shadow-lg p-6 transition-all duration-300 hover:shadow-xl">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-3xl">🎉</span>
                                        <div>
                                            <div class="font-semibold text-lg text-gray-900">From: {{ k.sender?.name || 'N/A' }}</div>
                                            <div class="text-sm text-gray-500">Project: {{ k.project?.name || 'N/A' }}</div>
                                        </div>
                                    </div>
                                    <span
                                        class="ml-4 inline-flex items-center rounded-full px-3 py-1 text-sm font-bold"
                                        :class="k.is_approved ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800 animate-pulse'"
                                    >
                    {{ k.is_approved ? 'Approved' : 'Pending Approval' }}
                  </span>
                                </div>
                                <p class="text-gray-700 italic border-l-4 border-purple-400 pl-3">"{{ k.comment }}"</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Video Modal -->
        <Modal :show="showVideo" maxWidth="4xl" @close="closeVideo">
            <div class="flex items-start justify-between mb-4">
                <h3 class="text-xl font-semibold text-gray-900">Kudos Feature Walkthrough</h3>
                <button class="text-gray-500 hover:text-gray-700" @click="closeVideo">✕</button>
            </div>
            <div class="w-full">
                <div class="relative w-full" style="padding-bottom: 56.25%; height: 0;">
                    <iframe
                        :key="videoKey"
                        class="absolute top-0 left-0 w-full h-full rounded-md"
                        src="https://drive.google.com/file/d/1uuXdPbxiBqe19oAyZb7P5Sdq98iY_V2Y/preview"
                        width="640"
                        height="480"
                        allow="autoplay"
                        allowfullscreen
                    ></iframe>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
