<script setup>
const emit = defineEmits(['close', 'select']);

const triggerTypes = [
    {
        type: 'TRIGGER', // This corresponds to the Event-based trigger step
        icon: '⚡',
        title: 'On an Event',
        description: 'Starts when something happens, like a task is created or an email is received.'
    },
    {
        type: 'SCHEDULE_TRIGGER', // This will map to our new ScheduleTriggerStep
        icon: '⏰',
        title: 'On a Schedule',
        description: 'Starts at a specific time or on a recurring basis (e.g., every Monday at 9 AM).'
    }
];
</script>

<template>
    <div @click.self="$emit('close')" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Choose a Trigger</h3>
                <p class="text-sm text-gray-500">How should this automation start?</p>
            </div>
            <div class="p-2">
                <div
                    v-for="trigger in triggerTypes"
                    :key="trigger.type"
                    @click="$emit('select', trigger.type)"
                    class="flex items-start space-x-4 p-3 hover:bg-gray-100 rounded-md cursor-pointer"
                >
                    <span class="text-2xl mt-1">{{ trigger.icon }}</span>
                    <div>
                        <p class="font-semibold text-gray-900">{{ trigger.title }}</p>
                        <p class="text-sm text-gray-600">{{ trigger.description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
