<script setup>
import PromptForm from './PromptForm.vue';

const props = defineProps({
    prompt: {
        type: Object,
        required: true,
    }
});

const emit = defineEmits(['save', 'cancel']);
</script>

<template>
    <PromptForm :prompt="prompt" @save="(p) => $emit('save', p)" @cancel="$emit('cancel')" />
</template>

