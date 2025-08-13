<script setup>
import { ref, reactive, watch, computed, nextTick } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';

// Assuming you have these components or can create them
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    contractId: {
        type: Number,
        required: true,
    },
    completionDate: {
        type: String, // YYYY-MM-DD format
        required: false,
        default: null,
    },
});

const emit = defineEmits(['close', 'tasks-submitted']);

const tasks = reactive([]);
const smartInput = ref('');
const messageBox = ref({
    show: false,
    text: '',
    type: 'success', // 'success' or 'error'
});

const today = new Date();
const tomorrow = new Date(today);
tomorrow.setDate(today.getDate() + 1);

// This will hold the task that is currently being created before it's added to the list
const currentTaskName = ref('');

// Watch for the modal to close to reset the state
watch(() => props.show, (newValue) => {
    if (!newValue) {
        // Reset state when the modal closes
        tasks.splice(0, tasks.length);
        smartInput.value = '';
        currentTaskName.value = '';
        messageBox.value = { show: false, text: '', type: 'success' };
    }
});

// Add the current task to the list
const addTaskToList = () => {
    if (currentTaskName.value && currentTaskName.value.trim() !== '') {
        tasks.push({
            name: currentTaskName.value,
            dueDate: '', // No due date set initially
            priority: 'Medium', // Default to Medium priority
            contract_id: props.contractId,
            description: '',
            isEditingName: false,
            showDescription: false,
        });
        currentTaskName.value = '';
        messageBox.value.show = false;
    }
};

// Remove a task from the list
const removeTask = (index) => {
    tasks.splice(index, 1);
};

// Update priority of an existing task
const updateTaskPriority = (index, newPriority) => {
    tasks[index].priority = newPriority;
};

// Update due date of an existing task
const updateTaskDueDate = (index, newDate) => {
    const todayObj = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const completionDateObj = props.completionDate ? new Date(props.completionDate) : null;
    const newDateObj = new Date(newDate);

    // Clear previous message
    messageBox.value.show = false;

    if (completionDateObj && newDateObj > completionDateObj) {
        messageBox.value = {
            show: true,
            text: 'The selected due date is after the milestone completion date.',
            type: 'error',
        };
        tasks[index].dueDate = ''; // Clear the invalid date
        return;
    }

    if (newDateObj < todayObj) {
        messageBox.value = {
            show: true,
            text: 'The selected due date cannot be in the past.',
            type: 'error',
        };
        tasks[index].dueDate = ''; // Clear the invalid date
        return;
    }

    tasks[index].dueDate = newDate;
};

const toggleDescription = (index) => {
    tasks[index].showDescription = !tasks[index].showDescription;
};

const editTaskName = (index) => {
    tasks[index].isEditingName = true;
    nextTick(() => {
        document.getElementById(`task-name-input-${index}`).focus();
    });
};

const saveTaskName = (index, newName) => {
    if (newName.trim()) {
        tasks[index].name = newName;
    }
    tasks[index].isEditingName = false;
};

// Function to format the data for API submission
const formatDataForApi = (data) => {
    return { tasks: data };
};

const isSubmitDisabled = computed(() => {
    // The button is disabled if there are no tasks, or if any task is missing a name, due date, or priority.
    return tasks.length === 0 || tasks.some(task => !task.name.trim() || !task.dueDate || !task.priority);
});


const handleSubmit = (close) => {
    console.log('Submitting tasks to API:', tasks);
    messageBox.value = {
        show: true,
        text: 'Tasks submitted successfully!',
        type: 'success',
    };
    setTimeout(() => {
        messageBox.value.show = false;
        emit('tasks-submitted');
        close();
    }, 1500);
};

const formData = computed(() => tasks);

// Check if a given date is valid for quick select buttons
const isValidDate = (date) => {
    const completionDateObj = props.completionDate ? new Date(props.completionDate) : null;
    const todayObj = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const dateObj = new Date(date);

    const isValid = dateObj >= todayObj && (!completionDateObj || dateObj <= completionDateObj);
    return isValid;
};
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="'Create Tasks in Bulk'"
        :api-endpoint="'/api/tasks/bulk'"
        :http-method="'post'"
        :form-data="formData"
        :format-data-for-api="formatDataForApi"
        :submit-button-text="'Create Tasks'"
        @close="$emit('close')"
        @submitted="handleSubmit"
    >
        <template #default="{ errors }">
            <div class="space-y-4">
                <!-- Message box -->
                <div v-if="messageBox.show" class="mb-4 p-4 rounded-lg transition-all duration-300 ease-in-out" :class="{ 'bg-green-100 text-green-700 border border-green-200': messageBox.type === 'success', 'bg-red-100 text-red-700 border border-red-200': messageBox.type === 'error' }">
                    {{ messageBox.text }}
                </div>

                <!-- Smart Input Section -->
                <form @submit.prevent="addTaskToList" class="relative">
                    <TextInput
                        v-model="currentTaskName"
                        id="task-name-input"
                        placeholder="e.g., 'Finish project report'"
                        class="w-full p-4 pr-12 text-lg border border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition-all duration-200 ease-in-out"
                    />
                    <button type="submit" class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-indigo-600 hover:text-indigo-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                    </button>
                </form>

                <hr class="my-6 border-gray-200">

                <!-- Final Task List -->
                <div v-if="tasks.length > 0" id="final-task-list" class="space-y-3">
                    <h2 class="text-lg font-semibold text-gray-800">Tasks to Create:</h2>
                    <div v-for="(task, index) in tasks" :key="index" class="flex flex-col p-4 bg-gray-50 rounded-xl border border-gray-200 group transition-all duration-200 ease-in-out">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <template v-if="!task.isEditingName">
                                    <p @click="editTaskName(index)" class="text-sm font-semibold text-gray-800 truncate cursor-pointer hover:underline">{{ task.name }}</p>
                                    <p v-if="task.description && task.description.trim().length" class="mt-0.5 text-xs text-gray-600 truncate">{{ task.description }}</p>
                                </template>
                                <template v-else>
                                    <input
                                        :id="`task-name-input-${index}`"
                                        type="text"
                                        :value="task.name"
                                        @blur="saveTaskName(index, $event.target.value)"
                                        @keyup.enter="saveTaskName(index, $event.target.value)"
                                        class="text-sm font-semibold text-gray-800 w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
                                    />
                                </template>
                            </div>
                            <div class="flex items-center space-x-2 ml-4">
                                <button
                                    type="button"
                                    @click="toggleDescription(index)"
                                    class="p-1 rounded-full transition-all duration-200 ease-in-out"
                                    :class="[
                                        (task.showDescription || (task.description && task.description.trim().length)) ? 'text-indigo-600 hover:text-indigo-700' : 'text-gray-400 hover:text-indigo-500'
                                    ]"
                                    title="Add description"
                                >
                                    <!-- Heroicons: Document Text -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                        <path d="M19.5 14.25h-15m15-5.25h-15M9 19.5H4.5A1.5 1.5 0 013 18V6a1.5 1.5 0 011.5-1.5H15L21 9v9a1.5 1.5 0 01-1.5 1.5H15"/>
                                    </svg>
                                </button>
                                <button type="button" @click="removeTask(index)" class="remove-final-task p-1 rounded-full text-red-500 opacity-0 group-hover:opacity-100 transition-all duration-200 ease-in-out">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div v-if="task.showDescription" class="mt-4">
                            <textarea v-model="task.description" rows="2" placeholder="Add an optional description..." class="w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-sm"></textarea>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4 text-xs text-gray-500 mt-2">
                            <!-- Date Selection -->
                            <div class="flex items-center space-x-2">
                                <span class="font-medium">Due:</span>
                                <template v-if="isValidDate(today.toISOString().split('T')[0])">
                                    <button type="button" @click="updateTaskDueDate(index, today.toISOString().split('T')[0])" class="px-2 py-0.5 rounded-full text-indigo-600 bg-indigo-100 text-xs hover:bg-indigo-200" :class="{ 'bg-indigo-300 font-semibold': task.dueDate === today.toISOString().split('T')[0] }">Today</button>
                                </template>
                                <template v-if="isValidDate(tomorrow.toISOString().split('T')[0])">
                                    <button type="button" @click="updateTaskDueDate(index, tomorrow.toISOString().split('T')[0])" class="px-2 py-0.5 rounded-full text-indigo-600 bg-indigo-100 text-xs hover:bg-indigo-200" :class="{ 'bg-indigo-300 font-semibold': task.dueDate === tomorrow.toISOString().split('T')[0] }">Tomorrow</button>
                                </template>
                                <input type="date" :value="task.dueDate" @change="updateTaskDueDate(index, $event.target.value)" :min="today.toISOString().split('T')[0]" :max="completionDate" required :class="[task.dueDate ? 'border-gray-300' : 'border-red-300', 'p-1 rounded-lg border text-xs focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition-all duration-200 ease-in-out']">
                            </div>

                            <!-- Priority Selection -->
                            <div class="flex items-center space-x-2">
                                <span class="font-medium">Priority:</span>
                                <button
                                    type="button"
                                    @click="updateTaskPriority(index, 'Low')"
                                    class="px-2 py-0.5 rounded-full text-gray-500 bg-gray-100 text-xs hover:bg-gray-200"
                                    :class="{ 'bg-gray-200 font-semibold': task.priority === 'Low' }">Low</button>
                                <button
                                    type="button"
                                    @click="updateTaskPriority(index, 'Medium')"
                                    class="px-2 py-0.5 rounded-full text-blue-600 bg-blue-100 text-xs hover:bg-blue-200"
                                    :class="{ 'bg-blue-200 font-semibold': task.priority === 'Medium' }">Medium</button>
                                <button
                                    type="button"
                                    @click="updateTaskPriority(index, 'High')"
                                    class="px-2 py-0.5 rounded-full text-red-600 bg-red-100 text-xs hover:bg-red-200"
                                    :class="{ 'bg-red-200 font-semibold': task.priority === 'High' }">High</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        <template #footer="{ close }">
            <div class="flex justify-end space-x-3">
                <SecondaryButton @click="close" type="button">
                    Cancel
                </SecondaryButton>
                <PrimaryButton
                    type="submit"
                    :disabled="isSubmitDisabled"
                    :class="{ 'opacity-50 cursor-not-allowed': isSubmitDisabled }"
                >
                    Create Tasks
                </PrimaryButton>
            </div>
        </template>
    </BaseFormModal>
</template>
