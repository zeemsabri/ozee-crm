<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AvailabilityCalendar from '@/Components/Availability/AvailabilityCalendar.vue';

const props = defineProps({
    auth: Object,
});

// Check if user is admin or manager
const isAdmin = props.auth?.user?.role_data?.slug === 'super-admin' || props.auth?.user?.role_data?.slug === 'manager';
</script>

<template>
    <Head title="Weekly Availability" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Weekly Availability
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <p class="mb-6 text-gray-600">
                            View and manage your weekly availability. This helps in planning meetings and work schedules.
                        </p>

                        <AvailabilityCalendar
                            :user-id="props.auth?.user?.id"
                            :is-admin="isAdmin"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
