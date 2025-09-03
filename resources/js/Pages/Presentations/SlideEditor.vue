<template>
  <div>
    <div v-if="!slide">Select a slide</div>
    <div v-else>
      <div class="flex items-center gap-2 mb-3">
        <label class="text-sm">Template</label>
        <select class="border rounded p-1" v-model="slide.template_name" @change="updateSlide">
          <option>Heading</option>
          <option>TwoColumnWithImage</option>
        </select>
        <input class="border rounded p-1 flex-1" v-model="slide.title" placeholder="Slide title" @change="updateSlide" />
        <button class="btn btn-sm" @click="addBlock('heading')">+ Heading</button>
        <button class="btn btn-sm" @click="addBlock('paragraph')">+ Paragraph</button>
        <button class="btn btn-sm" @click="addBlock('feature_card')">+ Feature</button>
      </div>
      <ul>
        <li v-for="b in (slide.content_blocks||[])" :key="b.id" class="border rounded p-2 mb-2">
          <div class="flex justify-between items-center mb-2">
            <div class="font-semibold">{{ b.block_type }}</div>
            <div class="space-x-1">
              <button class="btn btn-xs" @click="moveBlockUp(b)">↑</button>
              <button class="btn btn-xs" @click="moveBlockDown(b)">↓</button>
              <button class="btn btn-xs text-red-600" @click="removeBlock(b)">✕</button>
            </div>
          </div>
          <ContentBlockForm :block="b" @update="onUpdateBlock(b, $event)" />
        </li>
      </ul>
    </div>
  </div>
</template>
<script setup>
import { computed } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';
import ContentBlockForm from './ContentBlockForm.vue';

const store = usePresentationStore();
const slide = computed(()=>store.selectedSlide);

async function updateSlide(){
  if (!slide.value) return;
  await store.updateSlide(slide.value.id, { template_name: slide.value.template_name, title: slide.value.title });
}
async function addBlock(type){
  if (!slide.value) return;
  const payloads = {
    heading: { block_type: 'heading', content_data: { text: 'Heading text', level: 2 } },
    paragraph: { block_type: 'paragraph', content_data: { text: 'Lorem ipsum' } },
    feature_card: { block_type: 'feature_card', content_data: { icon: 'fa-star', title: 'Feature', description: 'Description' } },
  };
  await store.addBlock(slide.value.id, payloads[type]);
}
function onUpdateBlock(block, content){
  store.scheduleSaveBlock(block.id, content);
}
async function removeBlock(block){ await store.deleteBlock(block.id); }
async function moveBlockUp(b){
  const ids = (slide.value.content_blocks||[]).map(x=>x.id);
  const idx = ids.indexOf(b.id); if(idx>0){ [ids[idx-1], ids[idx]] = [ids[idx], ids[idx-1]]; await store.reorderBlocks(slide.value.id, ids); }
}
async function moveBlockDown(b){
  const ids = (slide.value.content_blocks||[]).map(x=>x.id);
  const idx = ids.indexOf(b.id); if(idx<ids.length-1){ [ids[idx+1], ids[idx]] = [ids[idx], ids[idx+1]]; await store.reorderBlocks(slide.value.id, ids); }
}
</script>
<style scoped>
.btn{ @apply px-2 py-1 bg-gray-100 rounded; }
.btn-sm{ @apply text-sm; }
.btn-xs{ @apply text-xs; }
</style>
