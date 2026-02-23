<script setup>
import { ref } from 'vue';
import { TrashIcon, GripVerticalIcon, CheckIcon, XIcon } from 'lucide-vue-next';

const props = defineProps({
    icon: String,
    title: String,
    // The presence of the onDelete function determines if the delete button is shown.
    onDelete: Function,
    // Set to true to hide the drag handle (e.g. for Trigger steps)
    disableDrag: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['delete', 'update:title']);

const isEditingTitle = ref(false);
const editTitleValue = ref(props.title);

function startEditingTitle() {
    editTitleValue.value = props.title;
    isEditingTitle.value = true;
}

function saveTitle() {
    emit('update:title', editTitleValue.value);
    isEditingTitle.value = false;
}

function cancelEditTitle() {
    isEditingTitle.value = false;
}
</script>

<template>
    <div class="bg-white rounded-lg shadow-md border border-gray-200 w-full max-w-md">
        <div class="flex items-center justify-between p-3 bg-gray-50 border-b rounded-t-lg">
            <div class="flex items-center space-x-2 flex-1">
                <!-- Drag handle: visible only when dragging is enabled -->
                <span
                    v-if="!disableDrag"
                    class="drag-handle cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 flex-shrink-0"
                    title="Drag to reorder"
                >
                    <GripVerticalIcon class="h-4 w-4" />
                </span>
                <span class="text-xl">{{ icon }}</span>
                
                <div v-if="isEditingTitle" class="flex items-center gap-1 flex-1">
                    <input 
                        v-model="editTitleValue" 
                        class="px-2 py-0.5 text-sm border rounded w-full focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        @keyup.enter="saveTitle"
                        @keyup.esc="cancelEditTitle"
                        autoFocus
                    />
                    <button @click="saveTitle" class="text-green-600 hover:text-green-700 p-0.5">
                        <CheckIcon class="h-4 w-4" />
                    </button>
                    <button @click="cancelEditTitle" class="text-red-600 hover:text-red-700 p-0.5">
                        <XIcon class="h-4 w-4" />
                    </button>
                </div>
                <h3 v-else @click="startEditingTitle" class="font-bold text-gray-700 cursor-pointer hover:bg-gray-100 px-1 rounded transition-colors truncate" title="Click to rename">
                    {{ title }}
                </h3>
            </div>
            <button v-if="props.onDelete" @click="$emit('delete')" class="text-gray-400 hover:text-red-500 p-1 rounded-md ml-2 flex-shrink-0">
                <TrashIcon class="h-4 w-4" />
            </button>
        </div>
        <div class="p-4 space-y-4">
            <slot />
        </div>
    </div>
</template>
