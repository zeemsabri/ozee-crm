<script setup>
import { ref, reactive, watch, computed, nextTick, onMounted } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    emailId: [Number, String],
    projectId: [Number, String],
});

const emit = defineEmits(['tasks-submitted', 'close']);

const tasks = ref([{ name: '', due_date: '', priority: 'medium', assigned_user_id: '' }]);
const users = ref([]);
const priorities = [
    { value: 'high', label: 'High' },
    { value: 'medium', label: 'Medium' },
    { value: 'low', label: 'Low' },
];

const form = useForm({
    tasks: tasks.value,
    email_id: props.emailId,
});

const fetchProjectUsers = async () => {
    if (props.projectId) {
        try {
            const response = await axios.get(`/api/projects/${props.projectId}/users`);
            users.value = response.data.map(user => ({
                value: user.id,
                label: user.name
            }));
        } catch (error) {
            console.error('Error fetching project users:', error);
        }
    }
};

const addTask = () => {
    tasks.value.push({ name: '', due_date: '', priority: 'medium', assigned_user_id: '' });
};

const removeTask = (index) => {
    tasks.value.splice(index, 1);
};

const submitTasks = async () => {
    try {
        await axios.post(`/api/emails/${props.emailId}/tasks`, form.data());
        emit('tasks-submitted');
    } catch (error) {
        console.error('Error submitting tasks:', error);
    }
};

onMounted(fetchProjectUsers);
</script>

<template>
    <div class="p-4 space-y-6">
        <form @submit.prevent="submitTasks">
            <div v-for="(task, index) in tasks" :key="index" class="space-y-4 border-b pb-4 mb-4">
                <div>
                    <InputLabel :for="`task-name-${index}`" value="Task Name" />
                    <TextInput :id="`task-name-${index}`" v-model="task.name" type="text" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel :for="`due-date-${index}`" value="Due Date" />
                    <TextInput :id="`due-date-${index}`" v-model="task.due_date" type="date" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel :for="`priority-${index}`" value="Priority" />
                    <SelectDropdown
                        :id="`priority-${index}`"
                        v-model="task.priority"
                        :options="priorities"
                        class="mt-1 block w-full"
                    />
                </div>
                <div>
                    <InputLabel :for="`assigned-user-${index}`" value="Assigned User" />
                    <SelectDropdown
                        :id="`assigned-user-${index}`"
                        v-model="task.assigned_user_id"
                        :options="users"
                        placeholder="Select a user"
                        class="mt-1 block w-full"
                    />
                </div>
                <div class="flex justify-end">
                    <SecondaryButton @click="removeTask(index)">Remove Task</SecondaryButton>
                </div>
            </div>

            <div class="flex justify-between items-center mt-4">
                <SecondaryButton type="button" @click="addTask">Add Another Task</SecondaryButton>
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Create Tasks
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
