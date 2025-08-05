<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const authUser = computed(() => usePage().props.auth.user);

// Props
const props = defineProps({
    projectCount: Number,
});
</script>

<template>
    <div class="md:col-span-2 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg shadow-xl p-8 text-white flex flex-col sm:flex-row items-center justify-between transition-all duration-300 hover:shadow-2xl">
        <div>
            <h3 class="text-3xl font-bold mb-2">Hello, {{ authUser.name }}!</h3>
            <p class="text-md opacity-90">Here's a quick overview of your activities and progress.</p>
        </div>
        <div class="mt-6 sm:mt-0 sm:ml-8 text-center sm:text-right">
            <p class="text-5xl font-extrabold">{{ props.projectCount }}</p>
            <p class="text-sm opacity-90">Total Projects</p>
        </div>
    </div>
</template>
