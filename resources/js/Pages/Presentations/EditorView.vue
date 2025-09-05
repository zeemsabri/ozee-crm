<template>
    <AuthenticatedLayout>
        <div class="flex flex-row h-full bg-gray-50">
            <Toolbar
                :actions="['save', 'share', 'collaborate', 'saveTemplate']"
                class=""
                aria-label="Editor toolbar"
            />
            <div class="flex-1 flex flex-col min-w-0">
                <div class="h-8 px-4 border-b bg-white flex items-center text-sm text-slate-700">
                    <span class="font-medium truncate" :title="store.presentation?.title || 'Untitled Presentation'">{{ store.presentation?.title || 'Untitled Presentation' }}</span>
                </div>
                <Splitpanes class="flex-1 overflow-hidden">
                    <Pane :min-size="store.previewMaximized ? 0 : 20" :size="store.previewMaximized ? 0 : 10" class="bg-white border-r overflow-hidden">
                        <SlideManager />
                    </Pane>
                    <Pane :size="store.previewMaximized ? 0 : 80" class="bg-white overflow-hidden">
                        <SlideEditor />
                    </Pane>
                    <Pane :size="store.previewOpen ? (store.previewMaximized ? 100 : 30) : 0" min-size="0" class="bg-white border-l overflow-hidden transition-all">
                        <div v-if="store.previewOpen" class="h-full relative">
                            <SlidePreview />
                        </div>
                    </Pane>
                </Splitpanes>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { onMounted } from 'vue';
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
