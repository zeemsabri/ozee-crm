<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { success, error } from '@/Utils/notification';

const props = defineProps({
    items: {
        type: Array,
        required: true
    },
    apiEndpoint: {
        type: String,
        required: true
    },
    title: {
        type: String,
        default: 'Checklist'
    },
    containerClass: {
        type: String,
        default: 'mt-4 p-3 bg-gray-100 rounded-lg text-sm text-gray-700 border border-gray-200'
    },
    titleClass: {
        type: String,
        default: 'font-semibold text-gray-900 mb-2'
    },
    itemsContainerClass: {
        type: String,
        default: 'space-y-1'
    },
    itemClass: {
        type: String,
        default: 'flex items-center space-x-2'
    },
    checkboxClass: {
        type: String,
        default: 'h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded'
    },
    completedItemClass: {
        type: String,
        default: 'line-through text-gray-500'
    },
    successMessage: {
        type: String,
        default: 'Checklist item updated!'
    },
    errorMessage: {
        type: String,
        default: 'Failed to update checklist item'
    },
    payloadTransformer: {
        type: Function,
        default: null
    },
    checkListStyle: {
        type: String,
        default: 'checklist'
    }
});

const emit = defineEmits(['update:items', 'item-toggled']);

// Create a local copy of the items to work with
const localItems = ref(JSON.parse(JSON.stringify(props.items)));

// Watch for changes in props.items and update localItems
watch(() => props.items, (newItems) => {
    localItems.value = JSON.parse(JSON.stringify(newItems));
}, { deep: true });

async function toggleChecklistItem(index) {
    const item = localItems.value[index];
    const newStatus = !item.completed;

    // Update local state optimistically
    item.completed = newStatus;

    try {
        // Prepare the payload
        let payload;

        if (props.payloadTransformer) {
            // Use custom payload transformer if provided
            payload = props.payloadTransformer(localItems.value, index);
        } else {
            // Default payload is just the items array
            payload = { items: localItems.value };
        }

        // Make API call
        await axios.put(props.apiEndpoint, payload);

        // Emit events
        emit('update:items', localItems.value);
        emit('item-toggled', { index, item, completed: newStatus });

        // Show success notification
        success(props.successMessage);
    } catch (err) {
        console.error('Error updating checklist item:', err);

        // Revert local state on error
        item.completed = !newStatus;

        // Show error notification
        error(props.errorMessage);
    }
}
</script>

<template>
    <div :class="containerClass" v-if="localItems && localItems.length > 0">
        <p :class="titleClass">{{ title }}</p>
        <ul :class="itemsContainerClass">
            <li v-for="(item, index) in localItems" :key="index" :class="itemClass">
                <input
                    v-if="checkListStyle === 'checklist'"
                    type="checkbox"
                    :checked="item.completed"
                    @change="toggleChecklistItem(index)"
                    :class="checkboxClass"
                />
                <span :class="{ [completedItemClass]: item.completed }">
                    {{ item.name }}
                </span>
            </li>
        </ul>
    </div>
</template>
