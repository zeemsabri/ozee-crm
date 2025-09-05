<script setup>
const props = defineProps({
  as: { type: String, default: 'button' }, // 'button' | 'a'
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
});
</script>

<template>
  <component
    :is="props.as === 'a' ? 'a' : 'button'"
    v-bind="$attrs"
    :type="props.as === 'a' ? undefined : props.type"
    :disabled="props.as === 'a' ? undefined : props.disabled"
    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
  >
    <slot />
  </component>
</template>
