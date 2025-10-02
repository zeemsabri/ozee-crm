<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Block Task' },
  confirmText: { type: String, default: 'Block Task' },
  placeholder: { type: String, default: 'Enter reason for blocking...' },
  initialReason: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const reason = ref('');

watch(
  () => props.show,
  (show) => {
    if (show) {
      reason.value = props.initialReason || '';
    }
  },
  { immediate: true }
);

const onCancel = () => emit('close');
const onConfirm = () => {
  const r = (reason.value || '').trim();
  if (!r) return; // guard, should be disabled via button too
  emit('confirm', r);
};
</script>

<template>
  <Modal :show="show" @close="onCancel">
    <div class="p-6">
      <h2 class="text-lg font-medium text-gray-900 mb-4">{{ title }}</h2>

      <div class="mb-4">
        <p class="text-sm text-gray-600 mb-2">Please provide a reason:</p>
        <textarea
          v-model="reason"
          class="w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          rows="3"
          :placeholder="placeholder"
        ></textarea>
      </div>

      <div class="flex justify-end space-x-3">
        <SecondaryButton @click="onCancel">Cancel</SecondaryButton>
        <PrimaryButton
          @click="onConfirm"
          :disabled="disabled || !reason.trim()"
          class="bg-red-600 hover:bg-red-700"
        >
          {{ confirmText }}
        </PrimaryButton>
      </div>
    </div>
  </Modal>
</template>
