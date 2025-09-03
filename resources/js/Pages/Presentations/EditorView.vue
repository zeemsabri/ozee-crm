<template>
  <AuthenticatedLayout>
    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-4">
          <h1 class="text-2xl font-semibold">Edit Presentation</h1>
        </div>
        <div class="flex min-h-[60vh]">
          <aside class="w-64 border-r p-4 bg-white rounded-l">
            <SlideManager />
          </aside>
          <main class="flex-1 p-4 grid grid-cols-2 gap-6 bg-white rounded-r border-l">
            <div>
              <SlideEditor />
            </div>
            <div>
              <SlidePreview />
            </div>
          </main>
        </div>
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

const store = usePresentationStore();
const page = usePage();
const props = page?.props || {};
const providedId = props.presentationId;
let id = providedId;
if (!id) {
  const parts = window.location.pathname.split('/').filter(Boolean);
  // .../presentations/{id}/edit => pick the second segment's value
  const idx = parts.findIndex(p => p === 'presentations');
  if (idx !== -1 && parts[idx+1]) {
    id = parts[idx+1];
  }
}

onMounted(() => {
  if (id) {
    store.load(id);
  }
});
</script>
