<script setup>
import ProjectCard from './ProjectCard.vue';

defineProps({
    projects: Array,
});
</script>

<template>
    <div class="space-y-6">
        <div v-if="!projects || projects.length === 0" class="flex justify-center items-center h-48 bg-white rounded-xl shadow-md p-6 text-gray-500">
            <p>No projects to display for this filter.</p>
        </div>
        <ProjectCard v-for="project in projects" :key="project.id" :project="project" />
    </div>
</template>
