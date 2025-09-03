<template>
    <AuthenticatedLayout>
        <div class="flex flex-row h-screen bg-gray-50">
            <Toolbar
                :actions="['save', 'export', 'collaborate', 'theme']"
                class="bg-white shadow-sm"
                aria-label="Editor toolbar"
            />
            <Splitpanes class="flex-1 overflow-hidden">
                <Pane :min-size="previewMaximized ? 0 : 10" :size="previewMaximized ? 0 : 20" class="bg-white border-r overflow-hidden">
                    <SlideManager />
                </Pane>
                <Pane :size="previewMaximized ? 0 : 50" class="bg-white overflow-hidden">
                    <SlideEditor />
                </Pane>
                <Pane :size="previewOpen ? (previewMaximized ? 100 : 30) : 0" min-size="0" class="bg-white border-l overflow-hidden transition-all">
                    <div v-if="previewOpen" class="h-full relative">
                        <div class="absolute top-2 right-2 z-10 flex gap-2">
                            <button v-if="!previewMaximized" class="text-xs px-2 py-1 bg-gray-100 rounded" @click="previewMaximized = true">Expand</button>
                            <button v-else class="text-xs px-2 py-1 bg-gray-100 rounded" @click="previewMaximized = false">Restore</button>
                            <button class="text-xs px-2 py-1 bg-gray-100 rounded" @click="() => { previewOpen = false; previewMaximized = false; }">Hide Preview</button>
                        </div>
                        <SlidePreview />
                    </div>
                    <button v-else class="absolute top-2 right-2 text-xs px-2 py-1 bg-gray-100 rounded" @click="previewOpen = true">Show Preview</button>
                </Pane>
            </Splitpanes>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SlideManager from './SlideManager.vue';
import SlideEditor from './SlideEditor.vue';
import SlidePreview from './SlidePreview.vue';
import Toolbar from './Components/Toolbar.vue';
import { Splitpanes, Pane } from 'splitpanes';
import 'splitpanes/dist/splitpanes.css';

const store = usePresentationStore();
const previewOpen = ref(false);
const previewMaximized = ref(false);
const page = usePage();
const props = page?.props || {};
let id = props.presentationId;

// Prefer Inertia-provided URL instead of touching window directly
if (!id && page?.url) {
    const parts = page.url.split('?')[0].split('#')[0].split('/').filter(Boolean);
    const idx = parts.findIndex((p) => p === 'presentations');
    if (idx !== -1 && parts[idx + 1]) {
        id = parts[idx + 1];
    }
}

onMounted(() => {
    if (id) {
        store.load(id);
    }
});
</script>
