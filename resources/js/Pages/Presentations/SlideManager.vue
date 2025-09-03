<template>
  <div>
    <div class="flex justify-between items-center mb-2">
      <h3 class="font-semibold">Slides</h3>
      <button class="btn btn-sm" @click="add">+ Add</button>
    </div>
    <ul>
      <li v-for="s in slides" :key="s.id" class="flex items-center justify-between p-2 rounded cursor-pointer"
          :class="{ 'bg-indigo-50': s.id===selectedId }"
          @click="select(s.id)">
        <div class="truncate">#{{ s.display_order }} · {{ s.title || s.template_name }}</div>
        <div class="space-x-1">
          <button class="btn btn-xs" @click.stop="moveUp(s)">↑</button>
          <button class="btn btn-xs" @click.stop="moveDown(s)">↓</button>
          <button class="btn btn-xs text-red-600" @click.stop="remove(s)">✕</button>
        </div>
      </li>
    </ul>
  </div>
</template>
<script setup>
import { computed } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';

const store = usePresentationStore();
const slides = computed(() => store.slides);
const selectedId = computed(() => store.selectedSlideId);

function select(id){ store.selectSlide(id); }
async function add(){ await store.addSlide(); }
async function remove(s){ if(confirm('Delete slide?')) await store.deleteSlide(s.id); }
async function moveUp(s){
  const ids = slides.value.map(x=>x.id);
  const idx = ids.indexOf(s.id); if (idx>0){ [ids[idx-1], ids[idx]] = [ids[idx], ids[idx-1]]; await store.reorderSlides(ids); }
}
async function moveDown(s){
  const ids = slides.value.map(x=>x.id);
  const idx = ids.indexOf(s.id); if (idx<ids.length-1){ [ids[idx+1], ids[idx]] = [ids[idx], ids[idx+1]]; await store.reorderSlides(ids); }
}
</script>
<style scoped>
.btn{ @apply px-2 py-1 bg-gray-100 rounded; }
.btn-sm{ @apply text-sm; }
.btn-xs{ @apply text-xs; }
</style>
