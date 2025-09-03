<template>
  <div class="border rounded p-4 bg-gray-50">
    <div v-if="!slide"><em>No slide selected</em></div>
    <div v-else>
      <h3 class="text-lg font-semibold mb-2">Preview: {{ slide.title || slide.template_name }}</h3>
      <div v-for="b in (slide.content_blocks||[])" :key="b.id" class="mb-3">
        <component :is="getRenderer(b)"></component>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, h } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';

const store = usePresentationStore();
const slide = computed(()=>store.selectedSlide);

function getRenderer(b){
  const c = b.content_data || {};
  if (b.block_type === 'heading') {
    const Tag = `h${c.level||2}`;
    return { render(){ return h(Tag, { class: 'font-bold text-xl' }, c.text || ''); } };
  }
  if (b.block_type === 'paragraph') {
    return { render(){ return h('p', { class: 'text-gray-700' }, c.text || ''); } };
  }
  if (b.block_type === 'feature_card') {
    return { render(){
      return h('div', { class: 'p-3 border rounded' }, [
        h('div', { class: 'font-semibold' }, c.title || ''),
        h('div', { class: 'text-sm text-gray-600' }, c.description || ''),
      ]);
    }};
  }
  return { render(){ return h('div', {}, `Unsupported: ${b.block_type}`); } };
}
</script>
