<template>
  <div class="max-w-5xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">{{ presentation.title }}</h1>
    <div v-for="s in presentation.slides" :key="s.id" class="mb-10">
      <h2 class="text-xl font-semibold mb-2">{{ s.title || s.template_name }}</h2>
      <div v-for="b in s.content_blocks" :key="b.id" class="mb-3">
        <component :is="getRenderer(b)"></component>
      </div>
    </div>
  </div>
</template>
<script setup>
import { toRefs, h } from 'vue';
const props = defineProps({ presentation: { type: Object, required: true }});
const { presentation } = toRefs(props);

function getRenderer(b){
  const c = b.content_data || {};
  if (b.block_type==='heading'){
    const Tag = `h${c.level||2}`;
    return { render(){ return h(Tag, { class: 'font-bold text-xl' }, c.text||''); } };
  }
  if (b.block_type==='paragraph'){
    return { render(){ return h('p', { class: 'text-gray-700' }, c.text||''); } };
  }
  if (b.block_type==='feature_card'){
    return { render(){ return h('div', { class: 'p-3 border rounded' }, [
      h('div', { class: 'font-semibold' }, c.title||''),
      h('div', { class: 'text-sm text-gray-600' }, c.description||''),
    ]);} };
  }
  return { render(){ return h('div', {}, `Unsupported: ${b.block_type}`); } };
}
</script>
