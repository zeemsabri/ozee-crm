<template>
    <!-- Main container with dynamic theme classes -->
    <div class="md:col-span-3 flex flex-col items-center bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-colors duration-500">

        <!-- Theme Toggle Button -->
        <button @click="toggleTheme" class="fixed top-4 right-4 p-2 rounded-full shadow-lg z-50 transition-all duration-300 transform hover:scale-110"
                :class="isDarkMode ? 'bg-gray-800 text-yellow-400' : 'bg-gray-200 text-gray-700'">
            <!-- Sun icon for light mode -->
            <svg v-if="isDarkMode" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.106a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.804 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V18a.75.75 0 01.75-.75zM4.93 17.804a.75.75 0 001.061 1.06l1.591-1.59a.75.75 0 10-1.06-1.061l-1.591 1.59zM3 12a.75.75 0 01.75-.75h2.25a.75.75 0 010 1.5H3.75A.75.75 0 013 12zM6.106 5.106a.75.75 0 00-1.06 1.06l1.59 1.591a.75.75 0 101.061-1.06l-1.591-1.59z" />
            </svg>
            <!-- Moon icon for dark mode -->
            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                <path fill-rule="evenodd" d="M9.542 2.25a.75.75 0 01.442-.093L13.5 2.582V2.25A.75.75 0 0114.25 1h.5a.75.75 0 01.75.75v.256l1.01.127a.75.75 0 01.621.625l.128 1.011H21a.75.75 0 01.75.75v.5a.75.75 0 01-.75.75h-.256l-.127 1.01a.75.75 0 01-.625.62l-1.011.128V15.75a.75.75 0 01-.75.75h-.5a.75.75 0 01-.75-.75V15.42l-1.01-.129a.75.75 0 01-.62-.624L9.542 5.12V2.25zM12 5.25a.75.75 0 01.75-.75h.5a.75.75 0 01.75.75V12a.75.75 0 01-.75.75h-.5a.75.75 0 01-.75-.75V5.25zM16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zm-8.25-2.25a.75.75 0 01.75-.75h2.25a.75.75 0 010 1.5H9a.75.75 0 01-.75-.75z" clip-rule="evenodd" />
            </svg>
        </button>
        <h1 class="text-5xl sm:text-6xl font-extrabold text-center mb-10 text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 animate-pulse-glow">
            Monthly Leaderboard
        </h1>

        <div v-if="loading" :class="isDarkMode ? 'text-gray-400' : 'text-gray-500'">Loading leaderboard...</div>
        <div v-else class="w-full max-w-2xl relative" :style="{ height: `${displayList.length * ITEM_HEIGHT}px` }">
            <transition-group name="rank" tag="div" class="relative">
                <div v-for="user in displayList" :key="user.id"
                     class="absolute w-full my-1 transition-transform duration-300"
                     :style="{ transform: `translate3d(0, ${(user.rank-1)*ITEM_HEIGHT}px, 0)`, zIndex: zIndexFor(user) }">
                    <div :class="getRankClasses(user)" class="relative flex items-center justify-between p-4 sm:p-6 rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-xl">
                        <div class="flex items-center space-x-4">
              <span class="font-extrabold text-2xl sm:text-3xl" :class="getRankTextColor(user)">
                {{ user.rank }}
                  <!-- Heroicon for first place -->
                <svg v-if="user.rank === 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 ml-2 text-yellow-400">
                  <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.694 21.05c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
                </svg>
              </span>
                            <div class="flex items-center space-x-2">
                                <!-- Heroicon for user type -->
                                <svg v-if="user.userType === 'Employee'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"
                                     :class="isDarkMode ? 'text-gray-400' : 'text-gray-500'">
                                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.001.915 2.25 2.25 0 01-1.615 1.056c-1.334.252-2.33.582-2.651.921l-1.086 1.1c-.241.246-.59.246-.831 0l-1.086-1.1c-.321-.339-1.317-.669-2.651-.92a2.25 2.25 0 01-1.615-1.056.75.75 0 01-.001-.915z" clip-rule="evenodd" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"
                                     :class="isDarkMode ? 'text-gray-400' : 'text-gray-500'">
                                    <path d="M18.375 2.25a.75.75 0 00-.75.75v15.75a.75.75 0 001.5 0V3a.75.75 0 00-.75-.75zM15.75 6.75a.75.75 0 00-.75.75v12.75a.75.75 0 001.5 0V7.5a.75.75 0 00-.75-.75zM13.125 10.5a.75.75 0 00-.75.75v8.25a.75.75 0 001.5 0v-8.25a.75.75 0 00-.75-.75zM10.5 14.25a.75.75 0 00-.75.75v4.5a.75.75 0 001.5 0v-4.5a.75.75 0 00-.75-.75zM7.875 16.5a.75.75 0 00-.75.75v2.25a.75.75 0 001.5 0v-2.25a.75.75 0 00-.75-.75zM4.5 20.25a.75.75 0 00-.75.75v1.5a.75.75 0 001.5 0v-1.5a.75.75 0 00-.75-.75z" />
                                </svg>
                                <span class="text-lg sm:text-xl font-semibold">{{ user.name }}</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xl sm:text-2xl font-bold" :class="getPointTextColor(user)">{{ Math.round(user.points) }}</span>
                            <span :class="isDarkMode ? 'text-gray-400' : 'text-gray-500'">pts</span>
                        </div>
                    </div>
                </div>
            </transition-group>
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
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, computed } from 'vue'

const ITEM_HEIGHT = 88 // CARD_HEIGHT(80)+MARGIN(8) to match original
const loading = ref(true)
const rawData = ref([])
const displayList = ref([])
const currentUserId = ref(null)
const winner = ref(null)
const congratsState = reactive({ show: false, message: { title: '', text: '' } })
let timer = null

const isDarkMode = ref(true)
const isLightMode = computed(() => !isDarkMode.value)

function toggleTheme() {
    isDarkMode.value = !isDarkMode.value
    localStorage.setItem('theme', isDarkMode.value ? 'dark' : 'light')
}

function zIndexFor(user){
    if (!winner.value) return 1
    return user.id === winner.value.id ? 20 : (displayList.value.length - user.rank)
}

function handleCloseCongrats(){
    congratsState.show = false
    congratsState.message = { title: '', text: '' }
}

function getRankClasses(user){
    const isWinner = winner.value && user.id === winner.value.id
    const isTracked = currentUserId.value && (user.id === String(currentUserId.value))
    let classes = ''

    if (isWinner) {
        classes += ' bg-gradient-to-r from-yellow-500 to-orange-500 text-white z-20'
    } else if (isTracked) {
        if (isDarkMode.value) {
            classes += ' bg-gray-800 border-2 border-cyan-400 text-white z-10'
        } else {
            classes += ' bg-gray-200 border-2 border-cyan-500 text-gray-900 z-10'
        }
    } else {
        switch (user.rank){
            case 1:
                if (isDarkMode.value) {
                    classes += ' bg-gray-800 border-2 border-yellow-400 text-white z-10'
                } else {
                    classes += ' bg-gray-200 border-2 border-yellow-500 text-gray-900 z-10'
                }
                break
            case 2:
                if (isDarkMode.value) {
                    classes += ' bg-gray-800 border-2 border-gray-400 text-white'
                } else {
                    classes += ' bg-gray-200 border-2 border-gray-400 text-gray-900'
                }
                break
            case 3:
                if (isDarkMode.value) {
                    classes += ' bg-gray-800 border-2 border-orange-400 text-white'
                } else {
                    classes += ' bg-gray-200 border-2 border-orange-500 text-gray-900'
                }
                break
            default:
                if (isDarkMode.value) {
                    classes += ' bg-gray-800 border border-gray-700 text-white'
                } else {
                    classes += ' bg-white border border-gray-300 text-gray-900'
                }
                break
        }
    }
    return classes
}

function getRankTextColor(user){
    const isWinner = winner.value && user.id === winner.value.id
    const isTracked = currentUserId.value && (user.id === String(currentUserId.value))
    if (isWinner) return 'text-white'
    if (isTracked) return isDarkMode.value ? 'text-cyan-400' : 'text-cyan-500'

    switch(user.rank){
        case 1: return isDarkMode.value ? 'text-yellow-400' : 'text-yellow-600'
        case 2: return 'text-gray-400'
        case 3: return isDarkMode.value ? 'text-orange-400' : 'text-orange-600'
        default: return 'text-gray-500'
    }
}

function getPointTextColor(user){
    const isWinner = winner.value && user.id === winner.value.id
    return isWinner ? 'text-white' : (isDarkMode.value ? 'text-green-400' : 'text-green-600')
}

function startAnimation(){
    const maxFinal = rawData.value.reduce((m,u)=>Math.max(m,u.finalPoints||0), 0)
    const maxIncrement = maxFinal > 1000 ? 100 : (maxFinal > 200 ? 25 : 10)
    timer = setInterval(()=>{
        let allFinal = true
        // increment points
        displayList.value.forEach(u=>{
            if (u.points < u.finalPoints){
                const inc = Math.floor(Math.random()*maxIncrement)+1
                u.points = Math.min(u.points + inc, u.finalPoints)
                allFinal = false
            }
        })
        // sort by current points desc and reassign ranks
        displayList.value.sort((a,b)=> (b.points - a.points))
        displayList.value = displayList.value.map((u, idx)=> ({...u, rank: idx+1}))

        if (allFinal){
            clearInterval(timer)
            timer = null
            const tracked = displayList.value.find(u => String(u.id) === String(currentUserId.value))
            if (tracked){
                if (tracked.rank === 1){
                    congratsState.show = true
                    congratsState.message = { title: 'Congratulations!', text: `${tracked.name}, you are the winner!` }
                } else if (tracked.rank <= 3){
                    congratsState.show = true
                    congratsState.message = { title: 'Well done!', text: `${tracked.name}, you made it to the top 3!` }
                } else {
                    congratsState.show = true
                    congratsState.message = { title: 'Final Standings!', text: `Hey ${tracked.name}, you finished at rank ${tracked.rank}! Keep submitting your standups on time and completing tasks to climb the ranks next month. You're doing great!` }
                }
            }
        }
    }, 200)
}

async function loadData(){
    try {
        const themeFromStorage = localStorage.getItem('theme')
        if (themeFromStorage) {
            isDarkMode.value = themeFromStorage === 'dark'
        } else {
            isDarkMode.value = false // Default to light mode if not set
        }

        const me = await window.axios.get('/api/user')
        currentUserId.value = me.data.id

        const resp = await window.axios.get('/api/leaderboard/monthly')
        const list = Array.isArray(resp.data.leaderboard) ? resp.data.leaderboard : []

        winner.value = list.reduce((prev, curr)=> (prev && prev.finalPoints > curr.finalPoints) ? prev : curr, null)

        const others = list.filter(u => u.id !== (winner.value?.id ?? ''))
        const shuffled = others.sort(()=> Math.random()-0.5)
        const initialOrder = [...shuffled, winner.value].filter(Boolean)

        rawData.value = list
        displayList.value = initialOrder.map((u, idx)=> ({
            ...u,
            points: 0,
            rank: idx+1,
        }))

        loading.value = false
        startAnimation()
    } catch (e) {
        console.error('Failed to load leaderboard', e)
        loading.value = false
    }
}

onMounted(()=>{
    loadData()
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');

.font-inter { font-family: 'Inter', sans-serif; }

@keyframes pulse-glow {
    0%, 100% { text-shadow: 0 0 5px #fde047; }
    50% { text-shadow: 0 0 15px #fde047, 0 0 25px #fde047; }
}
.animate-pulse-glow { animation: pulse-glow 2s ease-in-out infinite; }
.congrats-overlay { background: rgba(0,0,0,0.7); }

.rank-move, .rank-enter-active, .rank-leave-active { transition: all 0.3s ease; }
.rank-enter-from, .rank-leave-to { opacity: 0; transform: translateY(10px); }
.rank-leave-active { position: absolute; }
</style>
