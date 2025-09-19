<script setup>
import StepCard from './StepCard.vue';
</script>

<template>
    <StepCard icon="⏰" title="Starts on a Schedule">
        <div class="text-sm text-gray-600">
            <p>This workflow is triggered by a schedule.</p>
            <p class="mt-2 text-xs">
                To run this automation, go to the <a href="/schedules" class="text-indigo-600 hover:underline">Schedules</a> page and attach this workflow to a new or existing schedule.
            </p>
        </div>
    </StepCard>
</template>
