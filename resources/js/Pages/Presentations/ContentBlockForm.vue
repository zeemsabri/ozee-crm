<template>
  <div>
    <div v-if="block.block_type==='heading'" class="space-y-2">
      <input class="border rounded p-2 w-full" v-model="local.text" @input="emitUpdate" placeholder="Heading text" />
      <select class="border rounded p-2" v-model.number="local.level" @change="emitUpdate">
        <option :value="1">H1</option>
        <option :value="2">H2</option>
        <option :value="3">H3</option>
      </select>
    </div>
    <div v-else-if="block.block_type==='paragraph'" class="space-y-2">
      <textarea class="border rounded p-2 w-full" rows="4" v-model="local.text" @input="emitUpdate" placeholder="Paragraph text"></textarea>
    </div>
    <div v-else-if="block.block_type==='feature_card'" class="space-y-2">
      <input class="border rounded p-2 w-full" v-model="local.icon" @input="emitUpdate" placeholder="Icon (e.g., fa-star)" />
      <input class="border rounded p-2 w-full" v-model="local.title" @input="emitUpdate" placeholder="Title" />
      <textarea class="border rounded p-2 w-full" rows="3" v-model="local.description" @input="emitUpdate" placeholder="Description"></textarea>
    </div>
    <div v-else>
      <em>Unsupported block type: {{ block.block_type }}</em>
    </div>
  </div>
</template>
<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({ block: { type: Object, required: true } });
const emit = defineEmits(['update']);

const local = reactive({ ...(props.block.content_data || {}) });

watch(() => props.block.content_data, (v) => {
  Object.assign(local, v || {});
});

function emitUpdate(){
  emit('update', { ...local });
}
</script>
