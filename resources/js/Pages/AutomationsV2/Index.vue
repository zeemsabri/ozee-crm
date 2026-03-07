<script setup>
/**
 * AutomationsV2/Index.vue
 * Main entry point for the new Automation Studio.
 * Handles view switching between Hub (list) and Builder (editor).
 */
import { ref, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Hub from './Components/Hub.vue';
import Builder from './Components/Builder.vue';
import { useAutomationsV2Store } from './Store/storeV2';

import './index.css'; // Import the design system

const store = useAutomationsV2Store();
const view = ref('hub'); // 'hub' | 'builder'

onMounted(() => {
    store.ensureSchema();
    store.fetchWorkflows();
});

function createNew() {
    store.initNewWorkflow();
    view.value = 'builder';
}

function openWorkflow(id) {
    store.loadWorkflow(id);
    view.value = 'builder';
}

function goBack() {
    view.value = 'hub';
    store.fetchWorkflows();
}
</script>

<template>
    <Head title="Automations V2" />

    <AuthenticatedLayout>
        <div class="h-[calc(100vh-64px)] overflow-hidden bg-gray-50 flex flex-col">
            <!-- View Switcher -->
            <Transition
                mode="out-in"
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div v-if="view === 'hub'" key="hub" class="flex-1 overflow-y-auto">
                    <Hub @create="createNew" @open="openWorkflow" />
                </div>
                <div v-else key="builder" class="flex-1">
                    <Builder @back="goBack" />
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>
