<template>
    <Head title="Leaderboard" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Monthly Leaderboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Main container for the leaderboard content -->
                <div class="flex flex-col items-center bg-white overflow-hidden shadow-xl sm:rounded-lg p-8">

                    <!-- Leaderboard Title -->
                    <h1 class="text-5xl sm:text-6xl font-extrabold text-center mb-10 text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 animate-pulse-glow">
                        Monthly Leaderboard
                    </h1>

                    <!-- Stats summary -->
                    <div class="w-full grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                            <div class="text-sm text-yellow-700">Days left in month</div>
                            <div class="text-2xl font-bold text-yellow-800">{{ stats.daysLeftInMonth }}</div>
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                            <div class="text-sm text-green-700">Your points (this month)</div>
                            <div class="text-2xl font-bold text-green-800">{{ stats.userMonthlyPoints }}</div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <div class="text-sm text-blue-700">Pending tasks (accessible)</div>
                            <div class="text-2xl font-bold text-blue-800">{{ stats.pendingTasksAcrossAccessibleProjects }}</div>
                        </div>
                        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
                            <div class="text-sm text-indigo-700">Standups submitted (this month)</div>
                            <div class="text-2xl font-bold text-indigo-800">{{ stats.standupsThisMonth }}</div>
                        </div>
                        <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
                            <div class="text-sm text-purple-700">Tasks completed (this month)</div>
                            <div class="text-2xl font-bold text-purple-800">{{ stats.tasksCompletedThisMonthByUser }}</div>
                        </div>
                        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
                            <div class="text-sm text-orange-700">Points needed to reach Top</div>
                            <div class="text-2xl font-bold text-orange-800">{{ stats.pointsNeededForTop }}</div>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="w-full max-w-2xl mb-4">
                        <input v-model="searchTerm" type="text" placeholder="Search by name..." class="w-full border border-gray-300 rounded-xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-500" />
                    </div>

                    <!-- Loading state -->
                    <div v-if="loading" class="text-gray-500">Loading leaderboard...</div>

                    <!-- Leaderboard list -->
                    <div v-else class="w-full max-w-2xl relative mx-auto overflow-hidden" :style="{ height: `${filteredList.length * ITEM_HEIGHT}px` }">
                        <transition-group name="rank" tag="div" class="relative">
                            <div v-for="(user, idx) in filteredList" :key="user.id"
                                 class="absolute w-full transition-transform duration-300"
                                 :style="{ transform: `translate3d(0, ${idx*ITEM_HEIGHT}px, 0)`, zIndex: zIndexFor(user) }">
                                <div :class="getRankClasses(user)" class="relative flex items-center justify-between p-4 sm:p-6 rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-xl my-2">
                                    <div class="flex items-center space-x-4">
                                        <span class="font-extrabold" :class="[getRankTextColor(user), getRankSizeClass(user)]">
                                            {{ user.rank }}
                                            <!-- Heroicon for first place -->
                                            <svg v-if="user.rank === 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 ml-2 text-yellow-400 inline-block">
                                                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.694 21.05c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                        <div class="flex items-center space-x-2">
                                            <!-- Heroicon for user type -->
                                            <svg v-if="user.userType === 'Employee'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-gray-500">
                                                <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.001.915 2.25 2.25 0 01-1.615 1.056c-1.334.252-2.33.582-2.651.921l-1.086 1.1c-.241.246-.59.246-.831 0l-1.086-1.1c-.321-.339-1.317-.669-2.651-.92a2.25 2.25 0 01-1.615-1.056.75.75 0 01-.001-.915z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-gray-500">
                                                <path d="M18.375 2.25a.75.75 0 00-.75.75v15.75a.75.75 0 001.5 0V3a.75.75 0 00-.75-.75zM15.75 6.75a.75.75 0 00-.75.75v12.75a.75.75 0 001.5 0V7.5a.75.75 0 00-.75-.75zM13.125 10.5a.75.75 0 00-.75.75v8.25a.75.75 0 001.5 0v-8.25a.75.75 0 00-.75-.75zM10.5 14.25a.75.75 0 00-.75.75v4.5a.75.75 0 001.5 0v-4.5a.75.75 0 00-.75-.75zM7.875 16.5a.75.75 0 00-.75.75v2.25a.75.75 0 001.5 0v-2.25a.75.75 0 00-.75-.75zM4.5 20.25a.75.75 0 00-.75.75v1.5a.75.75 0 001.5 0v-1.5a.75.75 0 00-.75-.75z" />
                                            </svg>
                                            <span class="text-lg sm:text-xl font-semibold text-gray-900">{{ user.name }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xl sm:text-2xl font-bold" :class="getPointTextColor(user)">{{ Math.round(user.points) }}</span>
                                        <span class="text-gray-500">pts</span>
                                    </div>
                                </div>
                            </div>
                        </transition-group>
                    </div>
                </div>
            </div>
        </div>

        <!-- Congrats Modal -->
        <div v-if="congratsState.show" class="congrats-overlay fixed inset-0 flex items-center justify-center z-50 p-4">
            <div class="bg-white p-8 sm:p-12 rounded-3xl shadow-2xl text-gray-900 text-center border-4 border-yellow-400 relative">
                <button @click="handleCloseCongrats" class="absolute top-4 right-4 text-gray-400 hover:text-gray-900 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <h2 class="text-4xl sm:text-5xl font-extrabold mb-4">{{ congratsState.message.title }}</h2>
                <p class="text-xl sm:text-2xl font-semibold">{{ congratsState.message.text }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'

const ITEM_HEIGHT = 96;
const loading = ref(true);
const rawData = ref([]);
const displayList = ref([]);
const currentUserId = ref(null);
const winner = ref(null);
const congratsState = reactive({ show: false, message: { title: '', text: '' } });
const stats = reactive({
    daysLeftInMonth: 0,
    userMonthlyPoints: 0,
    pendingTasksAcrossAccessibleProjects: 0,
    standupsThisMonth: 0,
    tasksCompletedThisMonthByUser: 0,
    pointsNeededForTop: 0,
});
const searchTerm = ref('');
const filteredList = computed(() => {
    if (!searchTerm.value) return displayList.value;
    const q = searchTerm.value.toLowerCase();
    return displayList.value.filter(u => (u.name || '').toLowerCase().includes(q));
});
let timer = null;

function zIndexFor(user) {
    if (!winner.value) return 1;
    return user.id === winner.value.id ? 20 : (displayList.value.length - user.rank);
}

function handleCloseCongrats() {
    congratsState.show = false;
    congratsState.message = { title: '', text: '' };
}

function getRankClasses(user) {
    const isWinner = winner.value && user.id === winner.value.id;
    const isTracked = currentUserId.value && (user.id === String(currentUserId.value));
    let classes = '';

    if (isWinner) {
        classes += ' bg-gradient-to-r from-yellow-500 to-orange-500 text-white z-20';
    } else if (isTracked) {
        classes += ' bg-cyan-50 border-2 border-cyan-600 text-gray-900 shadow-2xl z-10';
    } else {
        switch (user.rank) {
            case 1:
                classes += ' bg-gray-200 border-2 border-yellow-500 text-gray-900 z-10';
                break;
            case 2:
                classes += ' bg-gray-200 border-2 border-gray-400 text-gray-900';
                break;
            case 3:
                classes += ' bg-gray-200 border-2 border-orange-500 text-gray-900';
                break;
            default:
                classes += ' bg-white border border-gray-300 text-gray-900';
                break;
        }
    }
    return classes;
}

function getRankTextColor(user) {
    const isWinner = winner.value && user.id === winner.value.id;
    const isTracked = currentUserId.value && (user.id === String(currentUserId.value));
    if (isWinner) return 'text-white';
    if (isTracked) return 'text-cyan-600';

    switch (user.rank) {
        case 1: return 'text-yellow-600';
        case 2: return 'text-gray-400';
        case 3: return 'text-orange-600';
        default: return 'text-gray-500';
    }
}

function getRankSizeClass(user) {
    const isTracked = currentUserId.value && (user.id === String(currentUserId.value));
    return isTracked ? 'text-3xl sm:text-4xl' : 'text-2xl sm:text-3xl';
}

function getPointTextColor(user) {
    const isWinner = winner.value && user.id === winner.value.id;
    return isWinner ? 'text-white' : 'text-green-600';
}

function startAnimation() {
    const maxFinal = rawData.value.reduce((m, u) => Math.max(m, u.finalPoints || 0), 0);
    const maxIncrement = maxFinal > 1000 ? 100 : (maxFinal > 200 ? 25 : 10);
    timer = setInterval(() => {
        let allFinal = true;
        displayList.value.forEach(u => {
            if (u.points < u.finalPoints) {
                const inc = Math.floor(Math.random() * maxIncrement) + 1;
                u.points = Math.min(u.points + inc, u.finalPoints);
                allFinal = false;
            }
        });
        displayList.value.sort((a, b) => (b.points - a.points));
        displayList.value = displayList.value.map((u, idx) => ({ ...u, rank: idx + 1 }));

        if (allFinal) {
            clearInterval(timer);
            timer = null;
            const tracked = displayList.value.find(u => String(u.id) === String(currentUserId.value));
            if (tracked) {
                if (tracked.rank === 1) {
                    congratsState.show = true;
                    congratsState.message = { title: 'Congratulations!', text: `${tracked.name}, you are on the top of the leaderboard!` };
                } else if (tracked.rank <= 3) {
                    congratsState.show = true;
                    congratsState.message = { title: 'Well done!', text: `${tracked.name}, you made it to the top 3!` };
                } else {
                    congratsState.show = true;
                    congratsState.message = { title: 'Final Standings!', text: `Hey ${tracked.name}, you finished at rank ${tracked.rank}! Keep submitting your standups on time and completing tasks to climb the ranks. You're doing great!` };
                }
            }
        }
    }, 200);
}

async function loadData() {
    try {
        const me = await window.axios.get('/api/user');
        currentUserId.value = me.data.id;

        // Load stats in parallel with leaderboard
        const [statsResp, lbResp] = await Promise.all([
            window.axios.get('/api/leaderboard/stats'),
            window.axios.get('/api/leaderboard/monthly'),
        ]);

        Object.assign(stats, statsResp.data || {});

        const list = Array.isArray(lbResp.data.leaderboard) ? lbResp.data.leaderboard : [];

        winner.value = list.reduce((prev, curr) => (prev && prev.finalPoints > curr.finalPoints) ? prev : curr, null);

        const others = list.filter(u => u.id !== (winner.value?.id ?? ''));
        const shuffled = others.sort(() => Math.random() - 0.5);
        const initialOrder = [...shuffled, winner.value].filter(Boolean);

        rawData.value = list;
        displayList.value = initialOrder.map((u, idx) => ({
            ...u,
            points: 0,
            rank: idx + 1,
        }));

        loading.value = false;
        startAnimation();
    } catch (e) {
        console.error('Failed to load leaderboard', e);
        loading.value = false;
    }
}

onMounted(() => {
    loadData();
});
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');

.font-inter {
    font-family: 'Inter', sans-serif;
}

@keyframes pulse-glow {
    0%, 100% {
        text-shadow: 0 0 5px #fde047;
    }
    50% {
        text-shadow: 0 0 15px #fde047, 0 0 25px #fde047;
    }
}
.animate-pulse-glow {
    animation: pulse-glow 2s ease-in-out infinite;
}

.congrats-overlay {
    background: rgba(0, 0, 0, 0.7);
}

.rank-move,
.rank-enter-active,
.rank-leave-active {
    transition: all 0.3s ease;
}

.rank-enter-from,
.rank-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

.rank-leave-active {
    position: absolute;
}
</style>
