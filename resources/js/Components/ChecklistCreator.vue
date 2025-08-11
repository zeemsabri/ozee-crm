<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { TrashIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: {
        type: Array,
        required: true
    },
    placeholder: {
        type: String,
        default: 'e.g., Task 1, Task 2, Task 3'
    },
    label: {
        type: String,
        default: 'Checklist Items'
    },
    density: {
        type: String,
        default: 'spacious', // 'spacious', 'tight' or 'notes'
        validator: (value) => ['spacious', 'tight', 'notes'].includes(value)
    }
});

const emit = defineEmits(['update:modelValue']);

// Local copy of the checklist items
const items = ref([]);
const inputRefs = ref([]);

// Guard to prevent emitting while syncing from props
const isSyncingFromProps = ref(false);

// Watch for changes in props.modelValue and update local items
watch(() => props.modelValue, (newValue) => {
    isSyncingFromProps.value = true;
    // We create a deep copy to prevent direct mutation of the prop
    items.value = JSON.parse(JSON.stringify(newValue));

    // Ensure there's always an empty item at the end for adding new items
    if (items.value.length === 0 || items.value[items.value.length - 1].name.trim() !== '') {
        items.value.push({ name: '', completed: false });
    }
    // Release the guard after the DOM updates to avoid immediate re-emit
    nextTick(() => { isSyncingFromProps.value = false; });
}, { immediate: true, deep: true });

// Watch for changes in local items and emit updates
watch(() => items.value, (newValue) => {
    if (isSyncingFromProps.value) return;
    emit('update:modelValue', newValue);
}, { deep: true });

function removeItem(index) {
    items.value.splice(index, 1);

    // Ensure there's always at least one item
    if (items.value.length === 0) {
        items.value.push({ name: '', completed: false });
    }
}

function handleInput(index) {
    // If the user types in the last empty item, add a new one
    if (index === items.value.length - 1 && items.value[index].name.trim() !== '') {
        items.value.push({ name: '', completed: false });
    }
}

function handleEnterKey(index) {
    // Move focus to the next input field
    if (index < items.value.length - 1) {
        nextTick(() => {
            inputRefs.value[index + 1]?.focus();
        });
    }
}

// Dynamically compute classes based on density prop
const itemClasses = computed(() => {
    if (props.density === 'tight') {
        return {
            listClass: 'space-y-1',
            itemClass: 'flex items-center space-x-1',
            inputClass: 'px-1 py-1 text-sm'
        };
    } else if (props.density === 'notes') {
        return {
            listClass: 'space-y-0.5',
            itemClass: 'flex items-center space-x-1',
            inputClass: 'px-1 py-0.5 text-sm border-transparent focus:border-transparent focus:ring-0 bg-transparent'
        };
    }
    return {
        listClass: 'space-y-2',
        itemClass: 'flex items-center space-x-2',
        inputClass: ''
    };
});
</script>

<template>
    <div class="mt-4">
        <InputLabel v-if="label" :value="label" />
        <ul :class="itemClasses.listClass">
            <li v-for="(item, index) in items" :key="index" :class="itemClasses.itemClass">
                <TrashIcon v-if="density === 'notes'" class="h4 w-4" @click="removeItem(index)" />
                <TextInput
                    v-model="item.name"
                    type="text"
                    class="flex-1"
                    :class="itemClasses.inputClass"
                    :placeholder="index === items.length - 1 ? placeholder : ''"
                    @input="handleInput(index)"
                    @keyup.enter.prevent="handleEnterKey(index)"
                    :ref="el => { if (el) inputRefs[index] = el }"
                />
                <DangerButton

                    type="button"
                    @click="removeItem(index)"
                    v-if="items.length > 1 && density !== 'notes'"
                >
                    <TrashIcon class="h-4 w-4" />
                </DangerButton>

            </li>
        </ul>
    </div>
</template>
