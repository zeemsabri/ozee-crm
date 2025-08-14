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

const completedMilestonesCount = computed(() => (props.milestones || []).filter(m => {
  const s = (m?.status || '').toLowerCase();
  return s === 'completed' || s === 'approved';
}).length);

const milestoneProgressPct = computed(() => {
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
</script>

<template>
  <div class="mt-6 space-y-4">
    <!-- Milestone completion progress -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-gray-700">{{ progressLabel }}</span>
        <span class="text-sm text-gray-600">{{ completedMilestonesCount }} / {{ totalMilestonesCount }} milestones ({{ milestoneProgressPct }}%)</span>
      </div>
      <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
        <div class="h-3 bg-indigo-600" :style="{ width: milestoneProgressPct + '%' }"></div>
      </div>
    </div>

    <!-- Timeline based on milestone dates -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-gray-700">{{ timelineLabel }}</span>
        <span class="text-xs text-gray-500" v-if="timelineStartDate && timelineEndDate">
          {{ new Date(timelineStartDate).toLocaleDateString() }} — {{ new Date(timelineEndDate).toLocaleDateString() }}
        </span>
      </div>
      <div class="relative w-full h-3 bg-gray-200 rounded-full overflow-hidden">
        <div class="h-3 bg-emerald-500" :style="{ width: timelinePercent + '%' }"></div>
        <!-- Today marker -->
        <div v-if="timelineStartDate && timelineEndDate" class="absolute top-1/2 -translate-y-1/2" :style="{ left: `calc(${timelinePercent}% - 6px)` }">
          <div class="w-3 h-3 rounded-full bg-emerald-700 border-2 border-white shadow"></div>
        </div>
      </div>
      <!-- Milestone markers (optional, show subtle ticks) -->
      <div v-if="showTicks && orderedNonNullMilestones.length" class="relative mt-2 h-4">
        <div
          v-for="(ms, i) in orderedNonNullMilestones"
          :key="ms.id || i"
          class="absolute top-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-gray-400"
          :style="{ left: `${Math.round(((new Date(ms.completion_date) - new Date(orderedNonNullMilestones[0].completion_date)) / (new Date(orderedNonNullMilestones[orderedNonNullMilestones.length - 1].completion_date) - new Date(orderedNonNullMilestones[0].completion_date) || 1)) * 100)}%` }"
          :title="`${ms.name || 'Milestone'}: ${new Date(ms.completion_date).toLocaleDateString()}`"
        ></div>
      </div>
    </div>
  </div>
</template>
