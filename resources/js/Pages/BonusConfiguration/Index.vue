<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BonusConfiguration from '@/Components/BonusConfiguration/BonusConfiguration.vue';

</script>

<template>
    <Head title="Bonus Configuration" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Bonus Configuration
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <BonusConfiguration />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
