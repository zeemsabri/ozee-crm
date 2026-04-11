<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChartComponent from '@/Components/ChartComponent.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const loading = ref(false);
const attendance = ref({
    meta: {
        timezone: null,
        date_start: null,
        date_end: null,
        today: null,
    },
    summary: {
        online_minutes: 0,
        active_minutes: 0,
        idle_minutes: 0,
        unlinked_minutes: 0,
        unlinked_percentage: 0,
        unlinked_needs_attention: false,
        expected_minutes: 0,
        worked_days: 0,
        completion_percentage: null,
        shortfall_minutes: 0,
        surplus_minutes: 0,
    },
    current_week: {
        online_minutes: 0,
        active_minutes: 0,
        idle_minutes: 0,
        unlinked_minutes: 0,
        unlinked_percentage: 0,
        unlinked_needs_attention: false,
        expected_minutes: 0,
        worked_days: 0,
        completion_percentage: null,
        shortfall_minutes: 0,
        surplus_minutes: 0,
    },
    days: [],
    chart: [],
});

const filters = ref({
    date_start: '',
    date_end: '',
});

const formatMinutes = (minutes) => {
    const safeMinutes = Number(minutes || 0);
    const hours = Math.floor(safeMinutes / 60);
    const remainder = Math.round(safeMinutes % 60);

    if (hours <= 0) {
        return `${remainder}m`;
    }

    if (remainder <= 0) {
        return `${hours}h`;
    }

    return `${hours}h ${remainder}m`;
};

const completionTone = computed(() => {
    const percent = attendance.value.summary.completion_percentage ?? 0;

    if (percent >= 100) {
        return 'text-emerald-600';
    }

    if (percent >= 75) {
        return 'text-amber-600';
    }

    return 'text-rose-600';
});

const unlinkedTone = computed(() => {
    const percentage = attendance.value.summary.unlinked_percentage ?? 0;

    if (percentage > 5) {
        return 'text-rose-600';
    }

    if (percentage > 0) {
        return 'text-amber-600';
    }

    return 'text-emerald-600';
});

const chartData = computed(() => ({
    labels: attendance.value.chart.map((day) => day.label),
    datasets: [
        {
            label: 'Online',
            data: attendance.value.chart.map((day) => day.online_minutes),
            backgroundColor: '#0f766e',
            borderRadius: 8,
        },
        {
            label: 'Expected',
            data: attendance.value.chart.map((day) => day.expected_minutes),
            backgroundColor: '#d6d3d1',
            borderRadius: 8,
        },
    ],
}));

const fetchAttendance = async () => {
    loading.value = true;

    try {
        const response = await window.axios.get('/api/me/attendance', {
            params: {
                date_start: filters.value.date_start,
                date_end: filters.value.date_end,
            },
        });

        attendance.value = response.data;
    } catch (error) {
        console.error('Failed to fetch attendance report', error);
    } finally {
        loading.value = false;
    }
};

const setCurrentWeek = () => {
    const today = new Date();
    const day = today.getDay();
    const diffToMonday = (day + 6) % 7;
    const start = new Date(today);
    start.setDate(today.getDate() - diffToMonday);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);

    filters.value.date_start = start.toISOString().split('T')[0];
    filters.value.date_end = end.toISOString().split('T')[0];
};

const applyToday = async () => {
    const today = new Date().toISOString().split('T')[0];
    filters.value.date_start = today;
    filters.value.date_end = today;
    await fetchAttendance();
};

const applyCurrentWeek = async () => {
    setCurrentWeek();
    await fetchAttendance();
};

onMounted(async () => {
    setCurrentWeek();
    await fetchAttendance();
});
</script>

<template>
    <Head title="Attendance" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-stone-50 py-10">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-[28px] border border-stone-200 bg-[radial-gradient(circle_at_top_left,_#fef3c7,_transparent_40%),linear-gradient(135deg,_#0f172a,_#1f2937)] px-6 py-8 text-white shadow-xl sm:px-8">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-200">Attendance log</p>
                            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Track daily hours without admin noise.</h1>
                            <p class="mt-3 text-sm text-slate-200 sm:text-base">
                                Review completed hours, active versus idle time, and how your worked time compares with the availability you committed.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[360px]">
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">Selected range</p>
                                <p class="mt-2 text-2xl font-black">{{ formatMinutes(attendance.summary.online_minutes) }}</p>
                                <p class="mt-1 text-xs text-slate-300">Online time</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">Current week</p>
                                <p class="mt-2 text-2xl font-black">{{ formatMinutes(attendance.current_week.online_minutes) }}</p>
                                <p class="mt-1 text-xs text-slate-300">Worked this week</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[24px] border border-stone-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-stone-900">Filter attendance</h2>
                            <p class="text-sm text-stone-500">Default view is the current week. Switch to a day or any date range when you need to check make-up hours.</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[auto_auto_auto_auto]">
                            <label class="text-sm font-medium text-stone-700">
                                <span class="mb-2 block text-xs uppercase tracking-[0.18em] text-stone-400">From</span>
                                <input v-model="filters.date_start" type="date" class="w-full rounded-2xl border-stone-300 text-sm focus:border-teal-600 focus:ring-teal-600" />
                            </label>
                            <label class="text-sm font-medium text-stone-700">
                                <span class="mb-2 block text-xs uppercase tracking-[0.18em] text-stone-400">To</span>
                                <input v-model="filters.date_end" type="date" class="w-full rounded-2xl border-stone-300 text-sm focus:border-teal-600 focus:ring-teal-600" />
                            </label>
                            <button type="button" @click="applyCurrentWeek" class="rounded-2xl bg-stone-100 px-4 py-3 text-sm font-semibold text-stone-700 transition hover:bg-stone-200">
                                Current week
                            </button>
                            <button type="button" @click="applyToday" class="rounded-2xl bg-stone-100 px-4 py-3 text-sm font-semibold text-stone-700 transition hover:bg-stone-200">
                                Today
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="fetchAttendance" :disabled="loading" class="rounded-full bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:opacity-60">
                            {{ loading ? 'Refreshing...' : 'Apply range' }}
                        </button>
                    </div>
                </div>

                <div
                    class="rounded-[24px] border px-6 py-5 shadow-sm"
                    :class="attendance.summary.unlinked_needs_attention ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50'"
                >
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em]" :class="attendance.summary.unlinked_needs_attention ? 'text-rose-500' : 'text-amber-600'">Unlinked activity</p>
                            <h2 class="mt-1 text-lg font-bold text-stone-900">
                                {{ formatMinutes(attendance.summary.unlinked_minutes) }} was tracked without an active task.
                            </h2>
                            <p class="mt-1 text-sm text-stone-600">
                                Up to 5% is acceptable. Anything above that usually means you forgot to start or switch tasks.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-white/80 px-5 py-4 text-right shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-400">Selected range</div>
                            <div class="mt-2 text-3xl font-black" :class="unlinkedTone">{{ attendance.summary.unlinked_percentage }}%</div>
                            <div class="mt-1 text-xs text-stone-500">of total online time</div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-5">
                    <div class="rounded-[24px] border border-stone-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-stone-400">Completed time</p>
                        <div class="mt-3 text-3xl font-black text-stone-900">{{ formatMinutes(attendance.summary.online_minutes) }}</div>
                        <p class="mt-2 text-sm text-stone-500">Primary total based on online time: active plus idle.</p>
                    </div>
                    <div class="rounded-[24px] border border-stone-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-stone-400">Expected time</p>
                        <div class="mt-3 text-3xl font-black text-stone-900">{{ formatMinutes(attendance.summary.expected_minutes) }}</div>
                        <p class="mt-2 text-sm text-stone-500">Derived from your availability slots in the selected period.</p>
                    </div>
                    <div class="rounded-[24px] border border-stone-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-stone-400">Active vs idle</p>
                        <div class="mt-3 flex items-end gap-3">
                            <div>
                                <div class="text-2xl font-black text-teal-700">{{ formatMinutes(attendance.summary.active_minutes) }}</div>
                                <div class="text-xs uppercase tracking-[0.18em] text-stone-400">Active</div>
                            </div>
                            <div>
                                <div class="text-2xl font-black text-amber-600">{{ formatMinutes(attendance.summary.idle_minutes) }}</div>
                                <div class="text-xs uppercase tracking-[0.18em] text-stone-400">Idle</div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-[24px] border border-stone-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-stone-400">Unlinked time</p>
                        <div class="mt-3 text-3xl font-black" :class="unlinkedTone">{{ formatMinutes(attendance.summary.unlinked_minutes) }}</div>
                        <p class="mt-2 text-sm text-stone-500">{{ attendance.summary.unlinked_percentage }}% of your tracked time is not linked to a task.</p>
                    </div>
                    <div class="rounded-[24px] border border-stone-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-stone-400">Completion</p>
                        <div class="mt-3 text-3xl font-black" :class="completionTone">{{ attendance.summary.completion_percentage ?? 0 }}%</div>
                        <p class="mt-2 text-sm text-stone-500">
                            <span v-if="attendance.summary.shortfall_minutes > 0">{{ formatMinutes(attendance.summary.shortfall_minutes) }} short of your planned time.</span>
                            <span v-else-if="attendance.summary.surplus_minutes > 0">{{ formatMinutes(attendance.summary.surplus_minutes) }} above planned time.</span>
                            <span v-else>On track for the selected range.</span>
                        </p>
                    </div>
                </div>

                <div class="rounded-[24px] border border-stone-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-stone-900">Daily progress</h2>
                            <p class="text-sm text-stone-500">A simple view of daily hours so weekend catch-up and shortfalls are visible immediately.</p>
                        </div>
                        <div class="text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Timezone: {{ attendance.meta.timezone || '-' }}</div>
                    </div>
                    <div class="mt-6 h-[260px]">
                        <ChartComponent :data="chartData" type="bar" :options="{ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom' } }, scales: { x: { stacked: false, grid: { display: false } }, y: { beginAtZero: true } } }" />
                    </div>
                </div>

                <div class="overflow-hidden rounded-[24px] border border-stone-200 bg-white shadow-sm">
                    <div class="border-b border-stone-200 px-6 py-4">
                        <h2 class="text-lg font-bold text-stone-900">Daily attendance log</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-stone-50 text-xs uppercase tracking-[0.18em] text-stone-500">
                                <tr>
                                    <th class="px-6 py-4">Day</th>
                                    <th class="px-6 py-4">Worked</th>
                                    <th class="px-6 py-4">Expected</th>
                                    <th class="px-6 py-4">Active</th>
                                    <th class="px-6 py-4">Idle</th>
                                    <th class="px-6 py-4">Unlinked</th>
                                    <th class="px-6 py-4">Window</th>
                                    <th class="px-6 py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                <tr v-for="day in attendance.days" :key="day.date" class="hover:bg-stone-50/80">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-stone-900">{{ day.label }}</div>
                                        <div class="text-xs text-stone-500">{{ day.date }}</div>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-stone-900">{{ formatMinutes(day.online_minutes) }}</td>
                                    <td class="px-6 py-4 text-stone-600">{{ formatMinutes(day.expected_minutes) }}</td>
                                    <td class="px-6 py-4 text-teal-700">{{ formatMinutes(day.active_minutes) }}</td>
                                    <td class="px-6 py-4 text-amber-600">{{ formatMinutes(day.idle_minutes) }}</td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold" :class="day.unlinked_percentage > 5 ? 'text-rose-600' : day.unlinked_minutes > 0 ? 'text-amber-600' : 'text-stone-400'">
                                            {{ formatMinutes(day.unlinked_minutes) }}
                                        </div>
                                        <div v-if="day.unlinked_windows.length > 0" class="mt-1 flex flex-wrap gap-1">
                                            <span v-for="window in day.unlinked_windows" :key="`${day.date}-${window.label}`" class="inline-flex rounded-full bg-stone-100 px-2 py-1 text-[11px] text-stone-600">
                                                {{ window.label }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-stone-500">{{ day.first_seen || '-' }} to {{ day.last_seen || '-' }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]"
                                            :class="{
                                                'bg-emerald-100 text-emerald-700': day.status === 'complete' || day.status === 'extra',
                                                'bg-amber-100 text-amber-700': day.status === 'short',
                                                'bg-rose-100 text-rose-700': day.status === 'missed',
                                                'bg-stone-100 text-stone-600': day.status === 'no-plan'
                                            }"
                                        >
                                            {{ day.status.replace('-', ' ') }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="attendance.days.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center text-sm text-stone-500">No attendance data found for the selected dates.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>