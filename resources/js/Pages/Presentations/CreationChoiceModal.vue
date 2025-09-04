<template>
  <modal :show="true" @close="$emit('close')">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-lg">
      <h2 class="text-xl font-bold mb-4">How would you like to start?</h2>
      <div class="grid grid-cols-1 gap-3">
        <button class="btn btn-primary" @click="$emit('scratch')">Start from Scratch</button>
        <button class="btn" @click="$emit('choose-template')">Use a Template</button>
      </div>
      <div class="mt-4 text-right">
        <button class="btn" @click="$emit('close')">Close</button>
      </div>
    </div>
  </modal>
</template>

<script setup>
import Modal from './Components/Modal.vue';
</script>

<style scoped>
.btn { @apply px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors; }
.btn-primary { @apply bg-indigo-600 text-white hover:bg-indigo-700; }
</style>
