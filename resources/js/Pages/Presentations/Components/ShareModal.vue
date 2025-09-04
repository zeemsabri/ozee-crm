<template>
  <BaseModal
    :isOpen="show"
    title="Share Presentation"
    @close="$emit('close')"
  >
    <template #default>
      <div class="space-y-4">
        <p class="text-sm text-gray-600">Anyone with this link can view the presentation.</p>
        <div class="flex items-center gap-2">
          <input
            :value="fullUrl"
            class="flex-1 border border-gray-200 rounded-lg p-2 bg-gray-50 text-gray-800 select-all"
            readonly
            aria-label="Shareable URL"
          />
          <button
            @click="copy"
            class="px-3 py-2 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 text-sm whitespace-nowrap"
          >
            {{ copied ? 'Copied ✓' : 'Copy Link' }}
          </button>
        </div>
        <div class="flex justify-end">
          <button @click="$emit('close')" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">Close</button>
        </div>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, ref, watchEffect } from 'vue';
import BaseModal from '@/Pages/ClientDashboard/BaseModal.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  presentation: { type: Object, default: null },
  shareUrl: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const copied = ref(false);

const fullUrl = computed(() => {
  if (props.shareUrl) return props.shareUrl;
  const token = props.presentation?.share_token || '';
  const origin = typeof window !== 'undefined' ? window.location.origin : '';
  return token ? `${origin}/view/${token}` : `${origin}/view`;
});

function copy() {
  if (!fullUrl.value) return;
  navigator.clipboard.writeText(fullUrl.value).then(() => {
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  });
}

watchEffect(() => {
  if (!props.show) copied.value = false;
});
</script>
