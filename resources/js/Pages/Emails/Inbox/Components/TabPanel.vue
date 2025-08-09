<script setup>
defineProps({
    active: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <div v-show="active">
        <slot></slot>
    </div>
</template>
