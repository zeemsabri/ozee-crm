<script setup>
import { computed } from 'vue';

const props = defineProps({
    milestones: { type: Array, default: () => [] },
    showTicks: { type: Boolean, default: true },
    title: { type: String, default: 'Project Progress & Timeline' },
    progressLabel: { type: String, default: 'Project Progress' },
    timelineLabel: { type: String, default: 'Timeline' },
});

// Milestone ordering and counts
const nonNullMilestones = computed(() => {
    return (props.milestones || []).filter(m => !!m?.completion_date);
});

const orderedNonNullMilestones = computed(() => {
    return [...nonNullMilestones.value].sort((a, b) => {
        const ad = new Date(a.completion_date);
        const bd = new Date(b.completion_date);
        return ad - bd;
    });
});

const totalMilestonesCount = computed(() => (props.milestones || []).length);

const completedMilestones = computed(() => (props.milestones || []).filter(m => {
    const s = (m?.status || '').toLowerCase();
    return s === 'completed';
}));

const approvedMilestones = computed(() => (props.milestones || []).filter(m => {
    const s = (m?.status || '').toLowerCase();
    return s === 'approved';
}));

const completedMilestonesCount = computed(() => completedMilestones.value.length + approvedMilestones.value.length);

const completedMilestonePct = computed(() => {
    if (!totalMilestonesCount.value) return 0;
    return Math.round((completedMilestones.value.length / totalMilestonesCount.value) * 100);
});

const approvedMilestonePct = computed(() => {
    if (!totalMilestonesCount.value) return 0;
    return Math.round((approvedMilestones.value.length / totalMilestonesCount.value) * 100);
});

const totalMilestoneProgressPct = computed(() => {
    if (!totalMilestonesCount.value) return 0;
    return Math.round((completedMilestonesCount.value / totalMilestonesCount.value) * 100);
});

// Timeline based on earliest and latest non-null completion_date
const timelineStartDate = computed(() => orderedNonNullMilestones.value.length ? new Date(orderedNonNullMilestones.value[0].completion_date) : null);
const timelineEndDate = computed(() => orderedNonNullMilestones.value.length ? new Date(orderedNonNullMilestones.value[orderedNonNullMilestones.value.length - 1].completion_date) : null);

const timelinePercent = computed(() => {
    if (!timelineStartDate.value || !timelineEndDate.value) return 0;
    // Normalize to noon to reduce DST issues
    const toNoon = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
    const start = toNoon(timelineStartDate.value);
    const end = toNoon(timelineEndDate.value);
    const todayD = toNoon(new Date());
    const total = end - start;
    if (total <= 0) return 100;
    let pct = ((todayD - start) / total) * 100;
    if (pct < 0) pct = 0;
    if (pct > 100) pct = 100;
    return Math.round(pct);
});

const getDaysLeft = (date) => {
    const today = new Date();
    const completionDate = new Date(date);
    const diffTime = completionDate - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    if (diffDays === 0) return 'Due Today';
    if (diffDays > 0) return `${diffDays} day${diffDays === 1 ? '' : 's'} left`;
    return `${Math.abs(diffDays)} day${Math.abs(diffDays) === 1 ? '' : 's'} overdue`;
};

const getMilestonePosition = (milestoneDate) => {
    if (!timelineStartDate.value || !timelineEndDate.value) return 0;
    const start = new Date(timelineStartDate.value);
    const end = new Date(timelineEndDate.value);
    const milestoneD = new Date(milestoneDate);
    const total = end - start;
    if (total <= 0) return 100;
    const pct = ((milestoneD - start) / total) * 100;
    return pct;
};

// New computed property to group milestones by date and consolidate them
const consolidatedMilestones = computed(() => {
    const groups = {};
    orderedNonNullMilestones.value.forEach(ms => {
        const dateKey = new Date(ms.completion_date).toLocaleDateString();
        if (!groups[dateKey]) {
            groups[dateKey] = {
                date: ms.completion_date,
                milestones: [],
            };
        }
        groups[dateKey].milestones.push(ms);
    });
    return Object.values(groups);
});

// New function to calculate the dynamic label position
const getLabelStyle = (position) => {
    const adjustedPosition = Math.max(2, Math.min(98, position));
    const translateX = position < 5 ? '0%' : (position > 95 ? '-100%' : '-50%');

    return {
        left: `${adjustedPosition}%`,
        transform: `translateX(${translateX})`,
    };
};
</script>

<template>
    <div class="mt-8 p-6 md:p-8 bg-white rounded-2xl shadow-xl space-y-8 font-sans">

        <!-- Top Bar with Comprehensive Information -->
        <header class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b pb-4">
            <div class="flex-1">
                <h2 class="text-xl font-bold text-gray-900">{{ title }}</h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ completedMilestonesCount }} of {{ totalMilestonesCount }} milestones completed.
                    <span v-if="timelineStartDate && timelineEndDate">
            Project timeline: {{ new Date(timelineStartDate).toLocaleDateString() }} — {{ new Date(timelineEndDate).toLocaleDateString() }}.
          </span>
                </p>
            </div>
            <div class="flex items-center gap-4 flex-shrink-0">
                <div class="relative w-28 h-28">
                    <!-- Outer ring for total progress -->
                    <svg class="w-full h-full transform -rotate-90">
                        <circle class="text-gray-200" stroke-width="8" stroke="currentColor" fill="transparent" r="44" cx="56" cy="56"/>
                        <circle
                            class="text-indigo-500"
                            stroke-width="8"
                            :stroke-dasharray="276.46"
                            :stroke-dashoffset="276.46 - (276.46 * totalMilestoneProgressPct) / 100"
                            stroke-linecap="round"
                            stroke="currentColor"
                            fill="transparent"
                            r="44"
                            cx="56"
                            cy="56"
                            style="transition: stroke-dashoffset 0.5s ease-in-out;"
                        />
                        <!-- Inner ring for approved milestones -->
                        <circle class="text-gray-300" stroke-width="8" stroke="currentColor" fill="transparent" r="32" cx="56" cy="56"/>
                        <circle
                            class="text-green-500"
                            stroke-width="8"
                            :stroke-dasharray="201.06"
                            :stroke-dashoffset="201.06 - (201.06 * approvedMilestonePct) / 100"
                            stroke-linecap="round"
                            stroke="currentColor"
                            fill="transparent"
                            r="32"
                            cx="56"
                            cy="56"
                            style="transition: stroke-dashoffset 0.5s ease-in-out;"
                        />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-2xl font-bold text-gray-900">{{ totalMilestoneProgressPct }}%</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Project Timeline Section -->
        <div class="space-y-8 px-4"> <!-- Added horizontal padding here -->
            <div class="relative w-full h-2 rounded-full bg-gray-200">
                <!-- Progress Bar -->
                <div class="h-full bg-indigo-500 rounded-full" :style="{ width: timelinePercent + '%' }"></div>

                <!-- Today marker -->
                <div
                    v-if="timelineStartDate && timelineEndDate"
                    class="absolute top-1/2 -translate-y-1/2"
                    :style="{ left: `calc(${timelinePercent}% - 8px)` }"
                >
                    <div class="absolute -top-6 left-1/2 -translate-x-1/2 text-xs font-medium text-indigo-700 w-max">
                      Today:  {{ new Date().toLocaleDateString() }}
                    </div>
                    <div class="w-4 h-4 rounded-full bg-indigo-700 border-2 border-white shadow-md transform scale-125 transition-transform duration-200" title="Today"></div>
                </div>
            </div>

            <!-- Milestone markers and labels with dates and days left -->
            <div class="relative w-full h-4 mt-4 -top-8">
                <div
                    v-for="(group, i) in consolidatedMilestones"
                    :key="i"
                    class="absolute top-0 flex flex-col items-center text-center"
                    :style="getLabelStyle(getMilestonePosition(group.date))"
                >
                    <!-- The marker dot -->
                    <div class="w-2 h-2 rounded-full bg-gray-400 shadow-sm z-10 group relative">
                        <div v-if="group.milestones.length > 1" class="absolute -top-1 -right-1 w-3 h-3 text-xs bg-gray-500 text-white rounded-full flex items-center justify-center font-bold">
                            {{ group.milestones.length }}
                        </div>
                        <div class="absolute top-full mt-2 w-max p-2 text-xs text-center text-white bg-gray-800 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-20">
                            <p class="font-bold border-b pb-1 mb-1">{{ new Date(group.date).toLocaleDateString() }}</p>
                            <p v-for="ms in group.milestones" :key="ms.id" class="text-left text-gray-300">
                                - {{ ms.name }}
                            </p>
                        </div>
                    </div>
                    <!-- The text label below the marker -->
                    <div class="absolute top-full mt-2 w-max min-w-[70px]">
                        <p class="text-gray-500 text-xs truncate">{{ new Date(group.date).toLocaleDateString() }}</p>
                        <p :class="['text-xs font-medium truncate', {'text-red-500': getDaysLeft(group.date).includes('overdue'), 'text-gray-600': !getDaysLeft(group.date).includes('overdue')}]">
                            {{ getDaysLeft(group.date) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
