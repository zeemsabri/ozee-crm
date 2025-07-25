<script setup>
import {computed, onMounted, reactive, ref, watch} from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StandupModal from '@/Components/StandupModal.vue';
import VueCal from 'vue-cal';
import 'vue-cal/dist/vuecal.css';
import moment from 'moment'; // Import moment.js

// Define props
const props = defineProps({
    projectId: {
        type: Number,
        required: true
    },
    users: {
        type: Array,
        default: () => []
    }
});

// Emit events
const emit = defineEmits(['standupAdded']);

// Component state
const standupViewMode = ref('list'); // 'list' or 'calendar'
const showStandupModal = ref(false);
const loading = ref(false);
const error = ref('');
const standupNotes = ref([]);

// Calendar-related state for VueCal display
// This ref now strictly stores the YYYY-MM-DD of the first day of the month being displayed
const calendarDisplayMonth = ref('');

// Standup filters (now primarily for list view)
const standupFilters = reactive({
    startDate: '',
    endDate: '',
    search: '',
    userId: '',
});

// Helper function to get the first and last day of a month using moment.js
const getMonthRange = (dateInput) => {
    const m = moment(dateInput);
    if (!m.isValid()) {
        console.warn("getMonthRange received an invalid input, defaulting to current date:", dateInput);
        return {
            start: moment().startOf('month').format('YYYY-MM-DD'),
            end: moment().endOf('month').format('YYYY-MM-DD')
        };
    }

    return {
        start: m.clone().startOf('month').format('YYYY-MM-DD'),
        end: m.clone().endOf('month').format('YYYY-MM-DD')
    };
};

// Debounce timer for standup search
let standupSearchDebounceTimer = null;

// Debounce standup search to avoid too many API calls
const debounceStandupSearch = () => {
    clearTimeout(standupSearchDebounceTimer);
    standupSearchDebounceTimer = setTimeout(() => {
        applyStandupFilters();
    }, 500);
};

// Fetch standups with filters
const fetchProjectStandups = async () => {
    loading.value = true;
    error.value = '';

    try {
        const params = new URLSearchParams();
        if (standupFilters.startDate) params.append('start_date', standupFilters.startDate);
        if (standupFilters.endDate) params.append('end_date', standupFilters.endDate);
        if (standupFilters.search) params.append('search', standupFilters.search);
        if (standupFilters.userId) params.append('user_id', String(standupFilters.userId));

        const queryString = params.toString();
        const url = `/api/projects/${props.projectId}/sections/notes?type=standup${queryString ? `&${queryString}` : ''}`;

        const response = await window.axios.get(url);
        standupNotes.value = response.data.filter(note => note.type === 'standup');
    } catch (err) {
        console.error('Error fetching project standups:', err);
        error.value = 'Failed to load standups. Please try again.';
    } finally {
        loading.value = false;
    }
};

// Apply standup filters (now only for list view)
const applyStandupFilters = () => {
    fetchProjectStandups();
};

// Reset all standup filters (now only for list view)
const resetStandupFilters = () => {
    standupFilters.search = '';
    standupFilters.userId = '';
    standupFilters.startDate = '';
    standupFilters.endDate = '';
    fetchProjectStandups();
};

// Navigate to previous month in calendar view
const previousMonth = () => {
    calendarDisplayMonth.value = moment(calendarDisplayMonth.value).subtract(1, 'month').format('YYYY-MM-DD');
};

// Navigate to next month in calendar view
const nextMonth = () => {
    calendarDisplayMonth.value = moment(calendarDisplayMonth.value).add(1, 'month').format('YYYY-MM-DD');
};

// This watcher is the single source of truth for fetching data in calendar view.
// It runs whenever the displayed month changes.
watch(() => calendarDisplayMonth.value, (newMonthStartDate) => {
    if (standupViewMode.value === 'calendar' && newMonthStartDate) {
        const range = getMonthRange(newMonthStartDate);
        standupFilters.startDate = range.start;
        standupFilters.endDate = range.end;
        fetchProjectStandups();
    }
});

// This watcher handles the logic for switching between List and Calendar views.
watch(() => standupViewMode.value, (newMode) => {
    if (newMode === 'calendar') {
        // When switching to calendar, clear any list-specific filters.
        standupFilters.search = '';
        standupFilters.userId = '';

        // Set the calendar to display the current month.
        // The `calendarDisplayMonth` watcher will then automatically
        // set the date filters and fetch the data.
        calendarDisplayMonth.value = getMonthRange(new Date()).start;
    } else { // Switching to 'list' view
        // When switching back to list view, clear the date filters
        // that were set by the calendar, allowing the user to set them manually.
        standupFilters.startDate = '';
        standupFilters.endDate = '';

        // Fetch data for the list view (which will now have no date filters).
        fetchProjectStandups();
    }
});

// Transform standupNotes into vue-cal event format
const vueCalEvents = computed(() => {
    return (standupNotes.value || []).map(standup => {
        if (!standup?.created_at) {
            return null;
        }
        const createdAt = moment(standup.created_at);
        if (!createdAt.isValid()) {
            return null;
        }
        const parsedContent = parseStandupContent(standup.content);
        return {
            start: createdAt.format('YYYY-MM-DD HH:mm'),
            end: createdAt.format('YYYY-MM-DD HH:mm'),
            title: `${standup.user?.name || 'Unknown'} - Standup`,
            content: `Yesterday: ${parsedContent.yesterday}\nToday: ${parsedContent.today}\nBlockers: ${parsedContent.blockers}`,
            class: 'standup-event',
            standupData: standup
        };
    }).filter(Boolean);
});

// Initialize component
onMounted(() => {
    // Initial fetch for the default 'list' view
    fetchProjectStandups();
});

// Sorted standups (for list view)
const sortedStandups = computed(() => {
    if (!standupNotes.value) return [];
    let filteredList = [...standupNotes.value];

    // Client-side filtering for list view
    if (standupFilters.startDate) {
        filteredList = filteredList.filter(s => s.created_at && moment(s.created_at).isSameOrAfter(standupFilters.startDate, 'day'));
    }
    if (standupFilters.endDate) {
        filteredList = filteredList.filter(s => s.created_at && moment(s.created_at).isSameOrBefore(standupFilters.endDate, 'day'));
    }
    if (standupFilters.userId) {
        filteredList = filteredList.filter(s => s.user_id === parseInt(standupFilters.userId));
    }
    if (standupFilters.search) {
        const searchTerm = standupFilters.search.toLowerCase();
        filteredList = filteredList.filter(s => s.content && s.content !== '[Encrypted content could not be decrypted]' && s.content.toLowerCase().includes(searchTerm));
    }

    return filteredList.sort((a, b) => moment(b.created_at).valueOf() - moment(a.created_at).valueOf());
});

// Helper functions using moment
const isWeekday = (dateString) => {
    const day = moment(dateString).day(); // 0=Sun, 6=Sat
    return day >= 1 && day <= 5;
};

const isPastDay = (dateString) => {
    return moment(dateString).isBefore(moment(), 'day');
};

const isSameDay = (date1, date2) => {
    return moment(date1).isSame(date2, 'day');
};

const getUserName = (userId) => {
    if (!userId || !props.users) return 'Selected User';
    const user = props.users.find(u => u.id === parseInt(userId));
    return user ? user.name : 'Selected User';
};

const parseStandupContent = (content) => {
    if (!content || typeof content !== 'string' || content === '[Encrypted content could not be decrypted]' || content.trim() === '') {
        return { yesterday: 'N/A', today: 'N/A', blockers: 'N/A', date: 'N/A' };
    }
    const yesterday = content.match(/\*\*Yesterday:\*\* ([^\n]+)/)?.[1].trim() || 'Nothing';
    const today = content.match(/\*\*Today:\*\* ([^\n]+)/)?.[1].trim() || 'Nothing';
    const blockers = content.match(/\*\*Blockers:\*\* ([^\n]+)/)?.[1].trim() || 'None';
    const date = content.match(/\*\*Daily Standup - ([^*]+)\*\*/)?.[1].trim() || moment().format('L');
    return { yesterday, today, blockers, date };
};

const allFetchedStandupsByDate = computed(() => {
    const grouped = {};
    (standupNotes.value || []).forEach(standup => {
        if (!standup?.created_at) return;
        const dateString = moment(standup.created_at).format('YYYY-MM-DD');
        if (!grouped[dateString]) grouped[dateString] = [];
        grouped[dateString].push(standup);
    });
    return grouped;
});
</script>

<template>
    <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-gray-900">Daily Standups</h4>
            <div class="flex gap-3">
                <!-- View Toggle Buttons -->
                <div class="flex rounded-md shadow-sm mr-3">
                    <button
                        type="button"
                        @click="standupViewMode = 'list'"
                        :class="[
                            standupViewMode === 'list'
                                ? 'bg-indigo-100 text-indigo-700'
                                : 'bg-white text-gray-700 hover:text-gray-500',
                            'px-4 py-2 text-sm font-medium rounded-l-md border border-gray-300 focus:z-10 focus:outline-none focus:ring-1 focus:ring-indigo-500'
                        ]"
                    >
                        <span class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                            List
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="standupViewMode = 'calendar'"
                        :class="[
                            standupViewMode === 'calendar'
                                ? 'bg-indigo-100 text-indigo-700'
                                : 'bg-white text-gray-700 hover:text-gray-500',
                            'px-4 py-2 text-sm font-medium rounded-r-md border border-gray-300 focus:z-10 focus:outline-none focus:ring-1 focus:ring-indigo-500'
                        ]"
                    >
                        <span class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            Calendar
                        </span>
                    </button>
                </div>

                <PrimaryButton class="bg-blue-600 hover:bg-blue-700 transition-colors" @click="showStandupModal = true">
                    Submit Standup
                </PrimaryButton>
            </div>
        </div>

        <!-- Standup Filters (Only in List View) -->
        <div v-if="standupViewMode === 'list'" class="mb-4 bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Date Range Filter -->
                <div>
                    <label for="standupStartDate" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" id="standupStartDate" v-model="standupFilters.startDate" class="mt-1 block w-full border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" @change="applyStandupFilters"/>
                </div>
                <div>
                    <label for="standupEndDate" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" id="standupEndDate" v-model="standupFilters.endDate" class="mt-1 block w-full border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" @change="applyStandupFilters"/>
                </div>
                <!-- User Filter -->
                <div>
                    <label for="standupUserFilter" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                    <select id="standupUserFilter" v-model="standupFilters.userId" class="mt-1 block w-full border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" @change="applyStandupFilters">
                        <option value="">All Users</option>
                        <option v-for="user in props.users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <!-- Search Filter -->
                <div>
                    <label for="standupSearchFilter" class="block text-sm font-medium text-gray-700 mb-1">Search Content</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <input type="text" id="standupSearchFilter" v-model="standupFilters.search" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-10 sm:text-sm border-gray-300 rounded-md" placeholder="Search in standup content..." @input="debounceStandupSearch"/>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Reset Filters Button -->
            <div class="mt-3 flex justify-end">
                <button type="button" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" @click="resetStandupFilters">
                    Reset Filters
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center text-gray-600 text-sm animate-pulse py-4">Loading standups...</div>
        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">{{ error }}</div>

        <!-- List View -->
        <div v-else-if="standupViewMode === 'list'">
            <div v-if="sortedStandups.length" class="space-y-4">
                <div v-for="standup in sortedStandups" :key="standup.id" class="p-4 bg-blue-50 rounded-md shadow-sm hover:bg-blue-100 transition-colors border-l-4 border-blue-500">
                    <div v-if="standup.content === '[Encrypted content could not be decrypted]'" class="flex justify-between">
                        <div class="flex-grow">
                            <p class="text-sm text-red-500 italic">{{ standup.content }}<span class="text-xs text-red-400 block mt-1">(There was an issue decrypting this standup. Please contact an administrator.)</span></p>
                            <div class="flex items-center mt-1"><p class="text-xs text-gray-500">Submitted by {{ standup.user?.name || 'Unknown' }} on {{ moment(standup.created_at).format('L') }}</p></div>
                        </div>
                    </div>
                    <div v-else>
                        <div class="flex-grow">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-md font-bold text-gray-800">Daily Standup</h3>
                                <span class="text-sm text-gray-600 bg-blue-100 px-2 py-1 rounded-full">{{ parseStandupContent(standup.content).date }}</span>
                            </div>
                            <div class="mb-3">
                                <div class="flex items-center mb-1"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span class="font-semibold text-sm text-gray-700">Yesterday:</span></div>
                                <p class="text-sm text-gray-700 ml-6">{{ parseStandupContent(standup.content).yesterday }}</p>
                            </div>
                            <div class="mb-3">
                                <div class="flex items-center mb-1"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg><span class="font-semibold text-sm text-gray-700">Today:</span></div>
                                <p class="text-sm text-gray-700 ml-6">{{ parseStandupContent(standup.content).today }}</p>
                            </div>
                            <div class="mb-2">
                                <div class="flex items-center mb-1"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg><span class="font-semibold text-sm text-gray-700">Blockers:</span></div>
                                <p class="text-sm text-gray-700 ml-6">{{ parseStandupContent(standup.content).blockers }}</p>
                            </div>
                            <div class="flex items-center mt-3 pt-2 border-t border-blue-200"><p class="text-xs text-gray-500">Submitted by {{ standup.user?.name || 'Unknown' }} on {{ moment(standup.created_at).format('L') }}</p></div>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="text-gray-400 text-sm">No daily standups found for the selected criteria.</p>
        </div>

        <!-- Calendar View with VueCal -->
        <div v-else-if="standupViewMode === 'calendar'" class="mt-4">
            <div class="flex justify-between items-center mb-6">
                <button @click="previousMonth" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></button>
                <h3 class="text-lg font-medium text-gray-900">{{ calendarDisplayMonth ? moment(calendarDisplayMonth).format('MMMM YYYY') : 'Loading...' }}</h3>
                <button @click="nextMonth" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></button>
            </div>

            <vue-cal
                class="vuecal--blue-theme"
                :active-view="'month'"
                :events="vueCalEvents"
                :selected-date="calendarDisplayMonth"
                hide-weekends
                hide-title-bar
                :disable-views="['week', 'day', 'years', 'year']"
                :twelveHour="true"
                :time-from="8 * 60"
                :time-to="20 * 60"
                :time-step="30"
                events-on-month-view="short"
            >
                <template #event="{ event }">
                    <div class="vuecal__event-title" v-html="event.title"></div>
                    <div class="vuecal__event-content" v-html="event.content"></div>
                </template>
                <template #cell-content="{ view, date }">
                    <template v-if="view.id === 'month'">
                        <div v-if="date && isWeekday(date) && isPastDay(date) && !vueCalEvents.some(e => isSameDay(e.start, date))" class="mt-1 text-xs p-1 rounded bg-red-50 border-l-2 border-red-500 text-red-700">
                            No standups
                        </div>
                    </template>
                </template>
            </vue-cal>

            <div class="mt-4 flex items-center space-x-4 text-sm">
                <div class="flex items-center"><span class="inline-block w-3 h-3 bg-blue-50 border-l-2 border-blue-500 mr-1"></span><span>Standup submitted</span></div>
                <div class="flex items-center"><span class="inline-block w-3 h-3 bg-red-50 border-l-2 border-red-500 mr-1"></span><span>Missing standup</span></div>
            </div>

            <div class="mt-6 bg-white rounded-lg shadow p-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Standups Outside Current Month View</h3>
                <div v-if="Object.entries(allFetchedStandupsByDate).some(([date]) => !moment(date).isSame(calendarDisplayMonth, 'month'))">
                    <div v-for="[date, standups] in Object.entries(allFetchedStandupsByDate).filter(([date]) => !moment(date).isSame(calendarDisplayMonth, 'month')).sort((a,b) => moment(a[0]).valueOf() - moment(b[0]).valueOf())" :key="date" class="mb-4">
                        <div class="font-medium text-gray-800 mb-2">{{ moment(date).format('dddd, MMMM Do YYYY') }}</div>
                        <div v-for="standup in standups" :key="standup.id" class="p-2 mb-2 rounded bg-blue-50 border-l-2 border-blue-500">
                            <div class="font-medium text-blue-800">{{ standup.user?.name || 'Unknown' }}</div>
                            <div class="text-sm text-gray-600 mt-1">{{ moment(standup.created_at).format('LT') }}</div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-gray-500 text-sm">No standups outside the current month view.</div>
            </div>
        </div>

        <StandupModal :show="showStandupModal" @close="showStandupModal = false" @standupAdded="fetchProjectStandups" :projectId="projectId"/>
    </div>
</template>

<style>
/* VueCal styling adjustments for better fit and appearance */
.vuecal__event {
    background-color: #e0f2fe; /* Light blue, similar to your existing standup cards */
    border-color: #3b82f6; /* Blue border */
    color: #1e3a8a; /* Darker blue text */
    border-radius: 0.25rem; /* rounded-md */
    padding: 0.25rem;
    font-size: 0.75rem; /* text-xs */
    line-height: 1rem;
    overflow: hidden; /* Hide overflowing text */
}
.vuecal__event-title {
    font-weight: 500; /* font-medium */
    margin-bottom: 0.125rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.vuecal__event-content {
    font-size: 0.75rem; /* text-xs */
    color: #4b5563; /* text-gray-600 */
    display: -webkit-box;
    -webkit-line-clamp: 2; /* Limit to 2 lines */
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.vuecal__cell--out-of-scope {
    background-color: #f3f4f6; /* bg-gray-50 */
    color: #9ca3af; /* text-gray-500 */
}
.vuecal__cell--today {
    background-color: #eff6ff !important; /* bg-blue-50 */
}
.vuecal__cell--has-events .vuecal__cell-date {
    background-color: #d1fae5; /* Light green for days with events */
    border-radius: 9999px; /* rounded-full */
    padding: 0.25rem 0.5rem;
    font-weight: 600;
    color: #065f46; /* text-green-800 */
}
.vuecal__cell-content {
    display: flex;
    flex-direction: column;
}
.vuecal__cell-date {
    margin-bottom: 0.5rem; /* Add some space between date and events/indicators */
}
.vuecal__arrow-btn {
    display: none; /* Hide default vue-cal arrows as we have custom ones */
}
.vuecal {
    height: 500px; /* Adjust as needed */
    max-width: 100%;
}
@media (max-width: 768px) {
    .vuecal {
        height: auto; /* Allow height to adjust on smaller screens */
    }
}
</style>
