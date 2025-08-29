<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Points Ledger</h2>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white shadow-xl rounded-2xl p-6">

                    <!-- UI/UX Improvements: Points Summary -->
                    <div class="mb-6 border-b pb-4">
                        <h3 class="text-lg font-bold text-gray-700">Points Summary</h3>
                        <div class="flex flex-col sm:flex-row justify-between items-center mt-3 text-sm font-medium text-gray-500">
                            <div class="flex items-center space-x-2">
                                <span class="text-sm">Total Points for selected month:</span>
                                <span class="text-2xl font-extrabold text-cyan-600">{{ totalPointsForMonth }}</span>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <button @click="refresh" class="inline-flex items-center px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white font-semibold rounded-lg shadow-md transition-transform transform hover:scale-105">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.128a8 8 0 109.11-1.353l-.924-.925a1 1 0 01.707-1.707L16 0h4v4l-2.062 2.062a10.001 10.001 0 01-14.864 1.353V3a1 1 0 011-1zm12 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd" />
                                    </svg>
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-6">
                        <div class="col-span-1">
                            <label for="month-select" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                            <input type="month" id="month-select" v-model="monthInput" @change="handleMonthChange" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-cyan-500 focus:ring-cyan-500" />
                        </div>

                        <div class="col-span-1">
                            <label for="type-select" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <SelectDropdown id="type-select" :options="pointableOptionsWithAll" v-model="filters.pointable_type" placeholder="All Types" @change="applyFilters" class="w-full" />
                        </div>

                        <div class="col-span-1">
                            <label for="status-select" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <SelectDropdown id="status-select" :options="statusOptions" v-model="filters.status" placeholder="All Statuses" @change="applyFilters" class="w-full" />
                        </div>

                        <div class="col-span-1">
                            <label for="project-select" class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                            <SelectDropdown id="project-select" :options="projectOptionsWithAll" v-model="filters.project_id" placeholder="All Projects" @change="applyFilters" class="w-full" />
                        </div>

                        <div v-if="canManageProjects" class="col-span-1">
                            <label for="user-select" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                            <SelectDropdown id="user-select" :options="userOptionsWithAll" v-model="filters.user_id" placeholder="Me" @change="applyFilters" class="w-full" />
                        </div>
                        <div class="col-span-1 flex items-end">
                            <button @click="clearFilters" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
                                Clear Filters
                            </button>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div v-if="state.loading" class="flex justify-center items-center h-48">
                        <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-cyan-500"></div>
                    </div>

                    <!-- List -->
                    <div v-else>
                        <div v-if="items.length === 0" class="text-center py-10 text-gray-500">
                            <p>No ledger entries found for the selected filters.</p>
                        </div>

                        <ul v-else class="divide-y divide-gray-200">
                            <li v-for="entry in items" :key="entry.id" class="py-4 px-3 sm:px-0 flex flex-col sm:flex-row items-start sm:items-center justify-between transition-all duration-200 hover:bg-gray-50 rounded-lg">
                                <div class="flex-1 w-full sm:w-auto">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs text-gray-500 font-mono">{{ formatDate(entry.created_at) }}</span>
                                        <span v-if="entry.project" class="text-xs text-gray-400 font-medium"> • {{ entry.project.name }}</span>
                                    </div>
                                    <div class="mt-1 font-medium text-gray-900 leading-snug">{{ entry.description || '—' }}</div>
                                    <div class="mt-2">
                                        <span :class="badgeClass(entry.status)" class="font-semibold px-2 py-0.5 rounded-full text-xs capitalize">{{ formatStatus(entry.status) }}</span>
                                    </div>
                                </div>
                                <div class="mt-3 sm:mt-0 text-right w-full sm:w-auto">
                                    <div class="text-xl font-extrabold" :class="entry.points_awarded >= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ Math.round(entry.points_awarded) }} pts
                                    </div>
                                </div>
                            </li>
                        </ul>

                        <!-- Pagination -->
                        <div v-if="pagination.total > pagination.per_page" class="mt-6 flex flex-col sm:flex-row items-center justify-between">
                            <div class="text-sm text-gray-600 mb-2 sm:mb-0">Showing {{ pagination.current_page * pagination.per_page - pagination.per_page + 1 }} to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }} entries</div>
                            <div class="flex space-x-2">
                                <button :disabled="pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)" class="px-4 py-2 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100 transition-colors duration-200">
                                    Previous
                                </button>
                                <button :disabled="pagination.current_page >= pagination.last_page" @click="goToPage(pagination.current_page + 1)" class="px-4 py-2 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100 transition-colors duration-200">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SelectDropdown from '@/Components/SelectDropdown.vue'
import { usePermissions } from '@/Directives/permissions.js'
import axios from 'axios'

const state = reactive({
    loading: false,
    error: null,
})

// Default to current year-month in local timezone
const now = new Date()
const pad = (n) => (n < 10 ? '0' + n : '' + n)
const monthInput = ref(`${now.getFullYear()}-${pad(now.getMonth() + 1)}`)

const items = ref([])
const pagination = reactive({ current_page: 1, per_page: 20, total: 0, last_page: 1 })

// Filters
const filters = reactive({ pointable_type: '', project_id: null, user_id: null, status: 'paid' })
const pointableOptions = [
    { value: 'Task', label: 'Task' },
    { value: 'Milestone', label: 'Milestone' },
    { value: 'Kudo', label: 'Kudo' },
    { value: 'ProjectNote', label: 'Project Note' },
    { value: 'Email', label: 'Email' },
    { value: 'WeeklyStreak', label: 'Weekly Standup Streak' },

]
const statusOptions = computed(() => {
    return [
        { value: null, label: 'All Statuses' },
        { value: 'paid', label: 'Awarded' },
        { value: 'pending', label: 'Pending' },
        { value: 'refunded', label: 'Refunded' },
        { value: 'cancelled', label: 'Cancelled' },
        { value: 'rejected', label: 'Rejected' },
        { value: 'consumed', label: 'Consumed' },
        { value: 'denied', label: 'Denied' },
    ];
});

const projects = ref([])
const projectOptionsWithAll = computed(() => [{ value: null, label: 'All Projects' }, ...projects.value])
const users = ref([])
const userOptionsWithAll = computed(() => [{ value: null, label: 'Me' }, { value: 'all', label: 'All Users' }, ...users.value])
const pointableOptionsWithAll = computed(() => [{ value: '', label: 'All Types' }, ...pointableOptions])
const { canDo } = usePermissions()
const canManageProjects = computed(() => canDo('manage_projects').value)

// Total points for the selected month
const totalPointsForMonth = ref(0)
const totalPointsForAllUsers = ref(0)
const viewAllUsers = ref(false)

const fetchTotalPoints = async () => {
    const { year, month } = parseYearMonth()
    try {
        const params = { year, month }
        const endpoint = filters.user_id === 'all' ? '/api/points-ledger/total' : '/api/points-ledger/total';
        if (filters.user_id !== 'all') {
            params.user_id = filters.user_id;
        }

        const resp = await axios.get(endpoint, { params })
        totalPointsForMonth.value = resp.data.total_points || 0
    } catch (e) {
        console.error('Failed to fetch total points', e)
        totalPointsForMonth.value = 0
    }
}

const fetchFilterOptions = async () => {
    try {
        const [projRes, userRes] = await Promise.all([
            axios.get('/api/projects-simplified'),
            canManageProjects.value ? axios.get('/api/users') : Promise.resolve({ data: [] })
        ])
        projects.value = (projRes.data || []).map(p => ({ value: p.id, label: p.name }))
        users.value = (userRes.data || []).map(u => ({ value: u.id, label: u.name }))
    } catch (e) {
        console.error('Failed to fetch filter options', e)
    }
}

const parseYearMonth = () => {
    const [y, m] = monthInput.value.split('-')
    return { year: parseInt(y, 10), month: parseInt(m, 10) }
}

const fetchLedger = async () => {
    const { year, month } = parseYearMonth()
    state.loading = true
    state.error = null
    try {
        const params = { year, month, page: pagination.current_page, per_page: pagination.per_page }
        if (filters.pointable_type) params.pointable_type = filters.pointable_type
        if (filters.project_id) params.project_id = filters.project_id
        if (canManageProjects.value && filters.user_id !== null) params.user_id = filters.user_id
        if (filters.status) params.status = filters.status
        const resp = await axios.get('/api/points-ledger', { params })
        items.value = Array.isArray(resp.data.data) ? resp.data.data : []
        Object.assign(pagination, resp.data.pagination || {})
    } catch (e) {
        console.error('Failed to fetch points ledger', e)
        state.error = 'Failed to load ledger.'
        items.value = []
    } finally {
        state.loading = false
    }
}

const handleMonthChange = () => {
    pagination.current_page = 1
    fetchTotalPoints()
    fetchLedger()
}

const applyFilters = () => {
    pagination.current_page = 1
    fetchLedger()
    fetchTotalPoints()
}

const clearFilters = () => {
    // Clear all filter values and reset pagination
    filters.pointable_type = ''
    filters.project_id = null
    filters.user_id = null
    filters.status = 'paid'
    pagination.current_page = 1
    fetchLedger()
    fetchTotalPoints()
}

const goToPage = (p) => {
    pagination.current_page = p
    fetchLedger()
}

const refresh = () => {
    pagination.current_page = 1
    fetchLedger()
    fetchTotalPoints()
}

const formatDate = (iso) => new Date(iso).toLocaleString()

const formatStatus = (status) => {
    switch (status) {
        case 'paid': return 'Awarded';
        case 'denied': return 'Denied';
        default: return status;
    }
}

const badgeClass = (status) => {
    const base = 'inline-block px-2 py-0.5 rounded-full text-xs capitalize '
    switch (status) {
        case 'paid': return base + 'bg-green-100 text-green-700'
        case 'pending': return base + 'bg-yellow-100 text-yellow-700'
        case 'rejected':
        case 'cancelled':
        case 'denied': return base + 'bg-red-100 text-red-700'
        default: return base + 'bg-gray-100 text-gray-700'
    }
}

onMounted(async () => {
    await fetchFilterOptions()
    await fetchLedger()
    await fetchTotalPoints()
})
watch(monthInput, () => { /* keeping manual change handler */ })
</script>

<style scoped>
</style>
