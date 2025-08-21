<script setup>
import { ref, reactive, watch, computed, nextTick } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    emailId: { type: Number, required: true },
    projectId: { type: Number, required: true },
    users: { type: Array, default: () => [] },
    usersLoading: { type: Boolean, default: false },
    usersError: { type: String, default: null },
    completionDate: { type: String, required: false, default: null },
});

const emit = defineEmits(['tasks-submitted']);

const tasks = reactive([]);
const messageBox = ref({ show: false, text: '', type: 'success' });
const today = new Date();
const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
const currentTaskName = ref('');

const addTaskToList = () => {
    if (currentTaskName.value && currentTaskName.value.trim() !== '') {
        tasks.push({
            name: currentTaskName.value,
            dueDate: '',
            priority: 'Medium',
            description: '',
            isEditingName: false,
            showDescription: false,
            assigned_to_user_id: null,
        });
        currentTaskName.value = '';
        messageBox.value.show = false;
    }
};

const removeTask = (index) => tasks.splice(index, 1);
const updateTaskPriority = (idx, val) => { tasks[idx].priority = val; };
const updateTaskDueDate = (idx, newDate) => {
    const todayObj = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const completionDateObj = props.completionDate ? new Date(props.completionDate) : null;
    const newDateObj = new Date(newDate);
    messageBox.value.show = false;
    if (completionDateObj && newDateObj > completionDateObj) {
        messageBox.value = { show: true, text: 'The selected due date is after the milestone completion date.', type: 'error' };
        tasks[idx].dueDate = '';
        return;
    }
    if (newDateObj < todayObj) {
        messageBox.value = { show: true, text: 'The selected due date cannot be in the past.', type: 'error' };
        tasks[idx].dueDate = '';
        return;
    }
    tasks[idx].dueDate = newDate;
};

const toggleDescription = (idx) => { tasks[idx].showDescription = !tasks[idx].showDescription; };
const editTaskName = (idx) => { tasks[idx].isEditingName = true; nextTick(() => document.getElementById(`email-task-name-input-${idx}`).focus()); };
const saveTaskName = (idx, val) => { if (val.trim()) tasks[idx].name = val; tasks[idx].isEditingName = false; };

const isSubmitDisabled = computed(() => tasks.length === 0 || tasks.some(t => !t.name.trim() || !t.dueDate || !t.priority));
const isValidDate = (date) => {
    const completionDateObj = props.completionDate ? new Date(props.completionDate) : null;
    const todayObj = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const dateObj = new Date(date);
    return dateObj >= todayObj && (!completionDateObj || dateObj <= completionDateObj);
};

const handleSubmit = async () => {
    try {
        const payload = {
            tasks: tasks.map(t => ({
                ...t,
                assigned_to_user_id: t.assigned_to_user_id || null
            })),
            email_id: props.emailId
        };
        await axios.post(`/api/emails/${props.emailId}/tasks/bulk`, payload);
        tasks.splice(0, tasks.length);
        currentTaskName.value = '';
        emit('tasks-submitted');
    } catch (e) {
        messageBox.value = { show: true, text: 'Failed to create tasks.', type: 'error' };
        console.error(e);
    }
};

</script>

<template>
    <div class="space-y-4">
        <div v-if="messageBox.show" class="mb-4 p-4 rounded-lg" :class="{ 'bg-green-100 text-green-700 border border-green-200': messageBox.type === 'success', 'bg-red-100 text-red-700 border border-red-200': messageBox.type === 'error' }">
            {{ messageBox.text }}
        </div>

        <form @submit.prevent="addTaskToList" class="relative">
            <TextInput v-model="currentTaskName" id="email-task-name-input" placeholder="e.g., 'Follow up with client'" class="w-full p-4 pr-12 text-lg border border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />
            <button type="submit" class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-indigo-600 hover:text-indigo-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
            </button>
        </form>
        <hr class="my-6 border-gray-200" />
        <div v-if="tasks.length > 0" class="space-y-3">
            <h2 class="text-lg font-semibold text-gray-800">Tasks to Create:</h2>
            <div v-for="(task, index) in tasks" :key="index" class="flex flex-col p-4 bg-gray-50 rounded-xl border border-gray-200 group">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <template v-if="!task.isEditingName">
                            <p @click="editTaskName(index)" class="text-sm font-semibold text-gray-800 truncate cursor-pointer hover:underline">{{ task.name }}</p>
                            <p v-if="task.description && task.description.trim().length" class="mt-0.5 text-xs text-gray-600 truncate">{{ task.description }}</p>
                        </template>
                        <template v-else>
                            <input :id="`email-task-name-input-${index}`" type="text" :value="task.name" @blur="saveTaskName(index, $event.target.value)" @keyup.enter="saveTaskName(index, $event.target.value)" class="text-sm font-semibold text-gray-800 w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />
                        </template>
                    </div>
                    <div class="flex items-center space-x-2 ml-4">
                        <button type="button" @click="toggleDescription(index)" class="p-1 rounded-full" :class="[(task.showDescription || (task.description && task.description.trim().length)) ? 'text-indigo-600 hover:text-indigo-700' : 'text-gray-400 hover:text-indigo-500']" title="Add description">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M19.5 14.25h-15m15-5.25h-15M9 19.5H4.5A1.5 1.5 0 013 18V6a1.5 1.5 0 011.5-1.5H15L21 9v9a1.5 1.5 0 01-1.5 1.5H15"/></svg>
                        </button>
                        <button type="button" @click="removeTask(index)" class="p-1 rounded-full text-red-500 opacity-0 group-hover:opacity-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </div>
                </div>
                <div v-if="task.showDescription" class="mt-4">
                    <textarea v-model="task.description" rows="2" placeholder="Add an optional description..." class="w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-sm"></textarea>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-gray-600 mt-2 gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">Due:</span>
                        <template v-if="isValidDate(today.toISOString().split('T')[0])">
                            <button type="button" @click="updateTaskDueDate(index, today.toISOString().split('T')[0])" class="px-2 py-0.5 rounded-full text-indigo-600 bg-indigo-100 text-xs hover:bg-indigo-200" :class="{ 'bg-indigo-300 font-semibold': task.dueDate === today.toISOString().split('T')[0] }">Today</button>
                        </template>
                        <template v-if="isValidDate(tomorrow.toISOString().split('T')[0])">
                            <button type="button" @click="updateTaskDueDate(index, tomorrow.toISOString().split('T')[0])" class="px-2 py-0.5 rounded-full text-indigo-600 bg-indigo-100 text-xs hover:bg-indigo-200" :class="{ 'bg-indigo-300 font-semibold': task.dueDate === tomorrow.toISOString().split('T')[0] }">Tomorrow</button>
                        </template>
                        <input type="date" :value="task.dueDate" @change="updateTaskDueDate(index, $event.target.value)" :min="today.toISOString().split('T')[0]" :max="completionDate" required class="p-1 rounded-lg border text-xs focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" :class="[task.dueDate ? 'border-gray-300' : 'border-red-300']">
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-medium">Priority:</span>
                        <button type="button" @click="updateTaskPriority(index, 'Low')" class="px-2 py-0.5 rounded-full text-gray-500 bg-gray-100 text-xs hover:bg-gray-200" :class="{ 'bg-gray-200 font-semibold': task.priority === 'Low' }">Low</button>
                        <button type="button" @click="updateTaskPriority(index, 'Medium')" class="px-2 py-0.5 rounded-full text-blue-600 bg-blue-100 text-xs hover:bg-blue-200" :class="{ 'bg-blue-200 font-semibold': task.priority === 'Medium' }">Medium</button>
                        <button type="button" @click="updateTaskPriority(index, 'High')" class="px-2 py-0.5 rounded-full text-red-600 bg-red-100 text-xs hover:bg-red-200" :class="{ 'bg-red-200 font-semibold': task.priority === 'High' }">High</button>
                    </div>
                    <div class="flex items-center gap-2 min-w-[180px]">
                        <span class="font-medium">Assign:</span>
                        <select v-model="task.assigned_to_user_id" class="flex-1 rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-xs py-1">
                            <option :value="null">Unassigned</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                </div>
                <p v-if="loadingUsers" class="text-[11px] text-gray-400 mt-1">Loading users...</p>
                <p v-if="usersError" class="text-[11px] text-red-500 mt-1">{{ usersError }}</p>
            </div>
        </div>
        <div class="flex justify-end mt-4 space-x-3">
            <PrimaryButton type="submit" @click.prevent="handleSubmit" :disabled="isSubmitDisabled" :class="{ 'opacity-50 cursor-not-allowed': isSubmitDisabled }">Create Tasks</PrimaryButton>
        </div>
    </div>
</template>
