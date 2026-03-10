<script setup>
/**
 * TokenInputField.vue
 * A premium replacement for V2 inputs that require dynamic token insertion.
 * Integrates the DataTokenInserter directly into the input field.
 */
import { ref, watch } from 'vue';
import DataTokenInserter from './DataTokenInserter.vue';

const props = defineProps({
    modelValue:     { type: [String, Number], default: '' },
    placeholder:    { type: String, default: '' },
    allStepsBefore: { type: Array, default: () => [] },
    textarea:       { type: Boolean, default: false },
    rows:           { type: Number, default: 3 },
});

const emit = defineEmits(['update:modelValue']);
const inputRef = ref(null);

function onInsert(token) {
    const el = inputRef.value;
    if (!el) {
        emit('update:modelValue', (props.modelValue || '') + token);
        return;
    }

    const start = el.selectionStart;
    const end = el.selectionEnd;
    const text = props.modelValue || '';
    const before = text.substring(0, start);
    const after = text.substring(end);
    
    const newVal = before + token + after;
    emit('update:modelValue', newVal);

    // Reposition cursor after the inserted token
    setTimeout(() => {
        el.focus();
        const cursor = start + token.length;
        el.setSelectionRange(cursor, cursor);
    }, 0);
}
</script>

<template>
    <div class="relative group w-full">
        <textarea 
            v-if="textarea"
            ref="inputRef"
            :value="modelValue"
            @input="emit('update:modelValue', $event.target.value)"
            class="v2-input w-full pr-10 resize-none block min-h-[40px]"
            :placeholder="placeholder"
            :rows="rows"
        />
        <input 
            v-else
            ref="inputRef"
            :value="modelValue"
            @input="emit('update:modelValue', $event.target.value)"
            class="v2-input w-full pr-10 block"
            :placeholder="placeholder"
        />
        
        <div 
            class="absolute right-1 transition-all z-10"
            :class="textarea ? 'top-1.5' : 'top-1/2 -translate-y-1/2'"
        >
            <DataTokenInserter :all-steps-before="allStepsBefore" @insert="onInsert" />
        </div>
    </div>
</template>
