<script setup>
import { computed } from 'vue';

const props = defineProps({
  columns: { type: Array, required: true }, // [{ key, title }]
  itemsByColumn: { type: Object, required: true }, // { [key]: Array<any> }
  loading: { type: Boolean, default: false },
  isAddTaskDisabled: { type: Boolean, default: false },
  addTaskDisabledTooltip: { type: String, default: '' },
});

const emit = defineEmits(['move', 'drop', 'add-task']);

import { ref } from 'vue';

const activeInputCol = ref(null);
const newTaskName = ref('');

const showInput = (colKey) => {
    activeInputCol.value = colKey;
    newTaskName.value = '';
    setTimeout(() => {
        const input = document.getElementById(`quick-add-${colKey}`);
        if (input) input.focus();
    }, 100);
};

const cancelInput = () => {
    activeInputCol.value = null;
    newTaskName.value = '';
};

const submitNewTask = (colKey) => {
    if (newTaskName.value.trim()) {
        emit('add-task', { columnKey: colKey, taskName: newTaskName.value.trim() });
    }
    cancelInput();
};

const onDragOver = (e) => {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
};

const onDropTo = (e, toKey) => {
  try {
    const text = e.dataTransfer.getData('text/plain');
    const data = JSON.parse(text);
    emit('drop', { data, to: toKey });
    if (data && (data.id != null)) {
      emit('move', { id: data.id, to: toKey });
    }
  } catch (_) {
    // ignore invalid payloads but still emit a generic drop without data
    emit('drop', { data: null, to: toKey });
  }
};

const countIn = (key) => (props.itemsByColumn?.[key]?.length ?? 0);
</script>

<template>
  <div class="flex gap-4 overflow-x-auto">
    <div
      v-for="col in columns"
      :key="col.key"
      class="flex-1 min-w-[260px] bg-gray-50 rounded-lg border border-gray-200 p-3"
      @dragover="onDragOver"
      @drop="(e) => onDropTo(e, col.key)"
    >
      <div class="flex items-center justify-between mb-2">
        <slot name="column-header" :column="col">
          <h3 class="text-sm font-semibold text-gray-700">{{ col.title }}</h3>
        </slot>
        <span class="text-xs text-gray-500">{{ countIn(col.key) }}</span>
      </div>

      <div v-if="loading" class="text-xs text-gray-500 p-2">Loading...</div>
      <div class="space-y-2">
        <template v-for="item in (itemsByColumn[col.key] || [])" :key="item.id ?? JSON.stringify(item)">
          <slot name="item" :item="item" :column-key="col.key" />
        </template>
      </div>

      <!-- Quick Add Input Area -->
      <div class="mt-3">
        <div v-if="activeInputCol !== col.key" class="relative group">
          <button
              type="button"
              @click="isAddTaskDisabled ? null : showInput(col.key)"
              class="w-full text-left text-sm text-gray-500 p-2 rounded flex items-center gap-2 transition-colors"
              :class="isAddTaskDisabled ? 'opacity-50 cursor-not-allowed grayscale' : 'hover:text-gray-700 hover:bg-gray-100'"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add a card
          </button>
          
          <!-- Custom Instant Tooltip -->
          <div 
            v-if="isAddTaskDisabled"
            class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-gray-900 text-white text-[10px] rounded shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-75 z-50 text-center"
          >
            {{ addTaskDisabledTooltip }}
            <!-- Tooltip Arrow -->
            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
          </div>
        </div>

        <div v-if="activeInputCol === col.key" class="mt-2 space-y-2">
          <textarea
              :id="`quick-add-${col.key}`"
              v-model="newTaskName"
              @keydown.enter.prevent="submitNewTask(col.key)"
              @keydown.esc="cancelInput"
              rows="2"
              placeholder="Enter task name..."
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
          ></textarea>
          <div class="flex items-center gap-2">
            <button @click="submitNewTask(col.key)" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-sm hover:bg-indigo-700 font-medium">Add card</button>
            <button @click="cancelInput" class="text-gray-500 hover:text-gray-700 p-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
