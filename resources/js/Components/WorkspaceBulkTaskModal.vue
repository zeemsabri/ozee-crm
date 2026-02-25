<script setup>
import { ref, reactive, watch, computed, nextTick } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import axios from 'axios';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'tasks-submitted']);

const tasks = reactive([]);
const currentTaskName = ref('');
const messageBox = ref({ show: false, text: '', type: 'success' });

// Selection options
const projects = ref([]);
const milestones = ref([]);
const users = ref([]);
const loadingOptions = ref(false);

// Default values for the bulk set
const defaults = reactive({
    project_id: null,
    milestone_id: null,
    assigned_to_user_id: null,
    due_date: null,
    priority: 'medium',
});

const today = new Date().toISOString().split('T')[0];
const tomorrow = new Date(new Date().setDate(new Date().getDate() + 1)).toISOString().split('T')[0];

const priorityOptions = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
];

const fetchProjects = async () => {
    try {
        const { data } = await axios.get('/api/projects-simplified');
        projects.value = data;
    } catch (e) {
        console.error('Failed to fetch projects', e);
    }
};

const fetchMilestones = async (projectId) => {
    if (!projectId) {
        milestones.value = [];
        return;
    }
    try {
        const { data } = await axios.get(`/api/projects/${projectId}/milestones`);
        milestones.value = data;
        // Auto-select support milestone if available
        const support = data.find(m => m.name.toLowerCase() === 'support');
        if (support) defaults.milestone_id = support.id;
    } catch (e) {
        console.error('Failed to fetch milestones', e);
    }
};

const fetchUsers = async (projectId) => {
    if (!projectId) {
        users.value = [];
        return;
    }
    try {
        const { data } = await axios.get(`/api/projects/${projectId}/users`);
        users.value = data;
    } catch (e) {
        console.error('Failed to fetch users', e);
    }
};

watch(() => defaults.project_id, async (val) => {
    if (val) {
        loadingOptions.value = true;
        await Promise.all([fetchMilestones(val), fetchUsers(val)]);
        loadingOptions.value = false;
    } else {
        milestones.value = [];
        users.value = [];
        defaults.milestone_id = null;
        defaults.assigned_to_user_id = null;
    }
});

watch(() => props.show, (val) => {
    if (val) {
        fetchProjects();
    } else {
        tasks.splice(0, tasks.length);
        currentTaskName.value = '';
        messageBox.value = { show: false, text: '', type: 'success' };
    }
});

const addTaskToList = () => {
    if (currentTaskName.value && currentTaskName.value.trim() !== '') {
        tasks.push({
            name: currentTaskName.value,
            due_date: defaults.due_date || '',
            priority: defaults.priority || 'medium',
            assigned_to_user_id: defaults.assigned_to_user_id || null,
            milestone_id: defaults.milestone_id || null,
            description: '',
            isEditingName: false,
            showDescription: false,
        });
        currentTaskName.value = '';
        messageBox.value.show = false;
        
        // Focus the input again
        nextTick(() => {
            document.getElementById('bulk-task-name-input').focus();
        });
    }
};

const removeTask = (index) => tasks.splice(index, 1);
const toggleDescription = (index) => { tasks[index].showDescription = !tasks[index].showDescription; };
const editTaskName = (index) => {
    tasks[index].isEditingName = true;
    nextTick(() => { document.getElementById(`bulk-task-name-input-${index}`).focus(); });
};
const saveTaskName = (index, val) => {
    if (val.trim()) tasks[index].name = val;
    tasks[index].isEditingName = false;
};

const formatDataForApi = () => {
    return {
        tasks: tasks.map(t => ({
            ...t,
            project_id: defaults.project_id
        }))
    };
};

const isSubmitDisabled = computed(() => {
    return tasks.length === 0 || !defaults.project_id || tasks.some(t => !t.name.trim());
});

const handleSubmit = (close) => {
    messageBox.value = { show: true, text: 'Tasks created successfully!', type: 'success' };
    setTimeout(() => {
        messageBox.value.show = false;
        emit('tasks-submitted');
        close();
    }, 1500);
};
</script>

<template>
    <BaseFormModal
        :show="show"
        title="Create Tasks in Bulk"
        api-endpoint="/api/tasks/bulk-workspace"
        http-method="post"
        :form-data="{}"
        :format-data-for-api="formatDataForApi"
        @close="$emit('close')"
        @submitted="handleSubmit"
    >
        <div class="space-y-6">
            <!-- Defaults Selection -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-200">
                <div class="space-y-1">
                    <InputLabel for="project_id" value="Project (Required)" />
                    <SelectDropdown
                        id="project_id"
                        v-model="defaults.project_id"
                        :options="projects"
                        value-key="id"
                        label-key="name"
                        placeholder="Select Project"
                        required
                    />
                </div>

                <div class="space-y-1">
                    <InputLabel for="milestone_id" value="Default Milestone" />
                    <SelectDropdown
                        id="milestone_id"
                        v-model="defaults.milestone_id"
                        :options="milestones"
                        value-key="id"
                        label-key="name"
                        placeholder="No Milestone (Use Support)"
                        :disabled="!defaults.project_id"
                    />
                </div>

                <div class="space-y-1">
                    <InputLabel for="assigned_to_user_id" value="Default Assignee" />
                    <SelectDropdown
                        id="assigned_to_user_id"
                        v-model="defaults.assigned_to_user_id"
                        :options="users"
                        value-key="id"
                        label-key="name"
                        placeholder="Unassigned"
                        :disabled="!defaults.project_id"
                    />
                </div>

                <div class="space-y-1">
                    <InputLabel for="due_date" value="Default Due Date" />
                    <input
                        type="date"
                        id="due_date"
                        v-model="defaults.due_date"
                        class="w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    />
                </div>

                <div class="space-y-1">
                    <InputLabel for="default_priority" value="Default Priority" />
                    <SelectDropdown
                        id="default_priority"
                        v-model="defaults.priority"
                        :options="priorityOptions"
                        placeholder="Select Priority"
                    />
                </div>
            </div>

            <!-- Smart Input -->
            <form @submit.prevent="addTaskToList" class="relative">
                <TextInput
                    v-model="currentTaskName"
                    id="bulk-task-name-input"
                    placeholder="Type task name and press Enter..."
                    class="w-full p-4 pr-12 text-lg border border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition-all"
                    :disabled="!defaults.project_id"
                />
                <button
                    type="submit"
                    class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-indigo-600 hover:text-indigo-800 disabled:opacity-50"
                    :disabled="!defaults.project_id || !currentTaskName"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </button>
                <p v-if="!defaults.project_id" class="text-xs text-red-500 mt-1">Please select a project first.</p>
            </form>

            <!-- Task List -->
            <div v-if="tasks.length > 0" class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center justify-between">
                    Tasks to Create ({{ tasks.length }})
                </h2>
                <div v-for="(task, index) in tasks" :key="index" class="p-4 bg-white rounded-xl border border-gray-200 group transition-all hover:border-indigo-300 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <template v-if="!task.isEditingName">
                                <p @click="editTaskName(index)" class="text-sm font-semibold text-gray-800 cursor-pointer hover:underline">{{ task.name }}</p>
                            </template>
                            <template v-else>
                                <input
                                    :id="`bulk-task-name-input-${index}`"
                                    type="text"
                                    v-model="task.name"
                                    @blur="saveTaskName(index, task.name)"
                                    @keyup.enter="saveTaskName(index, task.name)"
                                    class="text-sm font-semibold text-gray-800 w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </template>
                        </div>
                        <div class="flex items-center space-x-2 ml-4">
                            <button @click="toggleDescription(index)" type="button" class="p-1 text-gray-400 hover:text-indigo-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <button @click="removeTask(index)" type="button" class="p-1 text-red-400 hover:text-red-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-if="task.showDescription" class="mt-2">
                        <textarea v-model="task.description" rows="2" placeholder="Task description..." class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>

                    <!-- Per-task overrides -->
                    <div class="flex flex-wrap items-center gap-3 mt-2 text-[11px]">
                        <div class="flex items-center gap-1">
                            <span class="text-gray-500 font-medium">Due:</span>
                            <input type="date" v-model="task.due_date" class="p-1 border-gray-200 rounded text-[10px]" />
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="text-gray-500 font-medium">Assign:</span>
                            <SelectDropdown
                                v-model="task.assigned_to_user_id"
                                :options="users"
                                value-key="id"
                                label-key="name"
                                placeholder="Unassigned"
                                width="auto"
                            />
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="text-gray-500 font-medium">Priority:</span>
                            <SelectDropdown
                                v-model="task.priority"
                                :options="priorityOptions"
                                placeholder="Select Priority"
                                width="auto"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer="{ close }">
            <div class="flex justify-end space-x-3">
                <SecondaryButton @click="close">Cancel</SecondaryButton>
                <PrimaryButton
                    @click="$emit('submit')"
                    :disabled="isSubmitDisabled"
                    :class="{ 'opacity-50 cursor-not-allowed': isSubmitDisabled }"
                >
                    Create {{ tasks.length }} Tasks
                </PrimaryButton>
            </div>
        </template>
    </BaseFormModal>
</template>
