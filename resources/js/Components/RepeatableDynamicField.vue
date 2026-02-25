<script setup>
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { InformationCircleIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  allowLinks: { type: Boolean, default: false },
  placeholderName: { type: String, default: 'item' },
  addButtonText: { type: String, default: 'Add item' },
  labelPlaceholder: { type: String, default: 'Label' },
  urlPlaceholder: { type: String, default: 'https://example.com' },
  itemPlaceholder: { type: String, default: 'Enter value' },
});

const emit = defineEmits(['update:modelValue']);

const localItems = ref([...props.modelValue]);
const showBulkPasteModal = ref(false);
const bulkPasteContent = ref('');

// Helper: shallow equality for arrays of primitives (strings)
const arraysEqual = (a, b) => {
  if (!Array.isArray(a) || !Array.isArray(b)) return false;
  if (a.length !== b.length) return false;
  for (let i = 0; i < a.length; i++) {
    if (a[i] !== b[i]) return false;
  }
  return true;
};

// When parent updates modelValue, sync localItems only if different
watch(
  () => props.modelValue,
  (newVal) => {
    const incoming = Array.isArray(newVal) ? newVal : [];
    if (!arraysEqual(incoming, localItems.value)) {
      localItems.value = [...incoming];
    }
  }
);

// When localItems change, emit update only if different from parent value
watch(
  () => [...localItems.value],
  (newVal) => {
    const parentVal = Array.isArray(props.modelValue) ? props.modelValue : [];
    if (!arraysEqual(newVal, parentVal)) {
      emit('update:modelValue', newVal);
    }
  }
);

const linkRegex = /^\((.*?)\)\[(.*?)\]$/;

const addTextItem = () => {
  localItems.value.push('');
};

const addLinkItem = () => {
  localItems.value.push('(Label)[]');
};

const handleBulkPaste = () => {
    if (!bulkPasteContent.value) return;

    const lines = bulkPasteContent.value
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0);

    if (lines.length > 0) {
        localItems.value.push(...lines);
    }

    closeBulkPasteModal();
};

const closeBulkPasteModal = () => {
    bulkPasteContent.value = '';
    showBulkPasteModal.value = false;
};

const confirmAndRemove = (index) => {
  const ok = window.confirm('Remove this item?');
  if (ok) {
    localItems.value.splice(index, 1);
  }
};

const isLinkItem = (value) => typeof value === 'string' && linkRegex.test(value);

const parseLink = (value) => {
  // Expect format: (Label)[URL]
  const match = typeof value === 'string' ? value.match(linkRegex) : null;
  return {
    label: match ? match[1] : '',
    url: match ? match[2] : '',
  };
};

const buildLink = (label, url) => {
  return `(${label || ''})[${url || ''}]`;
};

const convertToLink = (index) => {
  const current = localItems.value[index] ?? '';
  // Use current text as label, empty URL
  localItems.value[index] = buildLink(current, '');
};

const convertToText = (index) => {
  const { label, url } = parseLink(localItems.value[index]);
  // Prefer label as text; if empty, fallback to URL
  localItems.value[index] = label || url || '';
};
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-start gap-3">
      <div class="flex gap-2">
        <PrimaryButton type="button" @click="addTextItem">
          Add text
        </PrimaryButton>
        <PrimaryButton v-if="allowLinks" type="button" @click="addLinkItem">
          Add link
        </PrimaryButton>
        <PrimaryButton type="button" @click="showBulkPasteModal = true">
          Bulk paste
        </PrimaryButton>
      </div>
      <div v-if="allowLinks" class="flex items-start text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
        <InformationCircleIcon class="h-5 w-5 text-indigo-500 mr-2 mt-0.5" />
        <div>
          You can insert inline links by typing: <code>(Label)[https://example.com]</code> inside any text item. You can also add a dedicated link item with the button. Drag to reorder.
        </div>
      </div>
    </div>

    <draggable
      v-model="localItems"
      item-key="__index"
      handle=".drag-handle"
      class="space-y-2"
    >
      <template #item="{ element, index }">
        <div class="flex items-start gap-2">
          <button type="button" class="drag-handle cursor-move text-gray-400 hover:text-gray-600 p-2" aria-label="Drag to reorder">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path d="M7 4a1 1 0 110-2 1 1 0 010 2zM13 4a1 1 0 110-2 1 1 0 010 2zM7 11a1 1 0 110-2 1 1 0 010 2zM13 11a1 1 0 110-2 1 1 0 010 2zM7 18a1 1 0 110-2 1 1 0 010 2zM13 18a1 1 0 110-2 1 1 0 010 2z" />
            </svg>
          </button>

          <div class="flex-1">
            <div v-if="isLinkItem(element)" class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full">
              <TextInput
                :id="`${placeholderName}-${index}-label`"
                type="text"
                class="block w-full"
                :placeholder="labelPlaceholder"
                :model-value="parseLink(element).label"
                @update:model-value="(val) => { const p = parseLink(localItems[index]); localItems[index] = buildLink(val, p.url); }"
              />
              <TextInput
                :id="`${placeholderName}-${index}-url`"
                type="url"
                class="block w-full"
                :placeholder="urlPlaceholder"
                :model-value="parseLink(element).url"
                @update:model-value="(val) => { const p = parseLink(localItems[index]); localItems[index] = buildLink(p.label, val); }"
              />
            </div>
            <div v-else>
              <TextInput
                :id="`${placeholderName}-${index}`"
                type="text"
                class="block w-full"
                :placeholder="itemPlaceholder"
                v-model="localItems[index]"
              />
            </div>
            <div class="mt-1 flex gap-2">
              <button v-if="allowLinks && !isLinkItem(element)" type="button" class="text-xs text-indigo-600 hover:underline" @click="convertToLink(index)">Convert to link</button>
              <button v-if="allowLinks && isLinkItem(element)" type="button" class="text-xs text-indigo-600 hover:underline" @click="convertToText(index)">Convert to text</button>
            </div>
          </div>

          <button type="button" class="text-red-500 hover:text-red-600 p-2" @click="confirmAndRemove(index)" aria-label="Remove item">
            <TrashIcon class="h-5 w-5" />
          </button>
        </div>
      </template>
    </draggable>

    <Modal :show="showBulkPasteModal" @close="closeBulkPasteModal" max-width="lg">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">
          Bulk Paste Items
        </h3>
        <p class="text-sm text-gray-600 mb-4">
          Paste your multi-line message below. Each line will be added as a separate text item.
        </p>
        <TextareaInput
          v-model="bulkPasteContent"
          class="w-full h-48"
          placeholder="Paste your text here..."
          autofocus
        />
        <div class="mt-6 flex justify-end gap-3">
          <SecondaryButton @click="closeBulkPasteModal">
            Cancel
          </SecondaryButton>
          <PrimaryButton @click="handleBulkPaste">
            Add Lines
          </PrimaryButton>
        </div>
      </div>
    </Modal>
  </div>
</template>
