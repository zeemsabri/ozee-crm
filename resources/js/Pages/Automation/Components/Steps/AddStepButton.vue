<script setup>
import { ref } from 'vue';
import { PlusIcon } from 'lucide-vue-next';

const emit = defineEmits(['select']);

const isOpen = ref(false);

const stepTypes = {
    'ACTION': { name: 'Perform an Action', icon: '⚙️', description: 'Send an email, create a task, etc.' },
    'CONDITION': { name: 'If/Else Condition', icon: '🔀', description: 'Split the workflow based on a rule.' },
    'AI_PROMPT': { name: 'Analyze with AI', icon: '🧠', description: 'Make a decision or extract information.' },
};

function handleSelect(type) {
    emit('select', type);
    isOpen.value = false;
}

// Close the menu if the user clicks outside of it
function closeMenu() {
    isOpen.value = false;
}
</script>

<template>
    <div class="relative h-8">
        <!-- Vertical line connecting steps -->
        <div class="h-full w-0.5 bg-gray-200 absolute left-1/2 -translate-x-1/2"></div>

        <div v-if="isOpen" @click="closeMenu" class="fixed inset-0 z-20"></div>

        <div class="relative z-30 flex justify-center">
            <button
                type="button"
                @click="isOpen = !isOpen"
                class="inline-flex items-center gap-x-1.5 rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-transform"
                :class="{'scale-110 ring-indigo-500': isOpen}"
            >
                <PlusIcon class="h-4 w-4" />
            </button>
            <div
                v-if="isOpen"
                class="absolute z-40 top-full mt-2 w-72 origin-top rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
            >
                <div class="py-1">
                    <a
                        v-for="(type, key) in stepTypes"
                        :key="key"
                        href="#"
                        @click.prevent="handleSelect(key)"
                        class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100"
                    >
                        <div class="flex items-start space-x-3">
                            <span class="text-xl mt-1">{{ type.icon }}</span>
                            <div>
                                <p class="font-semibold">{{ type.name }}</p>
                                <p class="text-xs text-gray-500">{{ type.description }}</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
