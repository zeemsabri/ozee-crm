<script setup>
import { ref, reactive, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue'; // Assuming SelectDropdown can be used here
import CustomMultiSelect from '@/Components/CustomMultiSelect.vue'; // Assuming CustomMultiSelect can be used for tags

const props = defineProps({
    show: Boolean,
    projectId: Number,
    selectedTask: Object, // Will be null for add, task object for edit
    projectUsers: Array,
    taskTypes: Array,
    milestones: Array,
    loadingTaskTypes: Boolean,
    loadingMilestones: Boolean,
});

const emit = defineEmits(['close', 'saved', 'open-add-milestone-modal']);

const taskForm = reactive({
    name: '',
    description: '',
    assigned_to_user_id: null,
    due_date: null,
    status: 'To Do',
    task_type_id: null,
    milestone_id: null,
    tags: []
});

const taskStatuses = ['To Do', 'In Progress', 'Done', 'Blocked', 'Archived'];

// Computed properties for BaseFormModal
const modalTitle = computed(() => props.selectedTask ? 'Edit Task' : 'Add New Task');
const apiEndpoint = computed(() => props.selectedTask ? `/api/tasks/${props.selectedTask.id}` : '/api/tasks');
const httpMethod = computed(() => props.selectedTask ? 'put' : 'post');
const submitButtonText = computed(() => props.selectedTask ? 'Update Task' : 'Create Task');
const successMessage = computed(() => props.selectedTask ? 'Task updated successfully!' : 'Task created successfully!');

// Watch for changes in `show` prop to initialize form data
watch(() => props.show, (newValue) => {
    if (newValue) {
        if (props.selectedTask) {
            // Edit mode
            let milestoneId = null;
            if (props.selectedTask.milestone) {
                const foundMilestone = props.milestones.find(m => m.name === props.selectedTask.milestone);
                if (foundMilestone) { milestoneId = foundMilestone.id; }
            }
            let taskTypeId = null;
            if (props.selectedTask.task_type) {
                const foundTaskType = props.taskTypes.find(t => t.name === props.selectedTask.task_type);
                if (foundTaskType) { taskTypeId = foundTaskType.id; }
            }
            let assignedToUserId = null;
            if (props.selectedTask.assigned_to && props.selectedTask.assigned_to !== 'Unassigned') {
                const foundUser = props.projectUsers?.find(u => u.name === props.selectedTask.assigned_to);
                if (foundUser) { assignedToUserId = foundUser.id; }
            }

            Object.assign(taskForm, {
                name: props.selectedTask.title,
                description: props.selectedTask.description || '',
                assigned_to_user_id: assignedToUserId || props.selectedTask.assigned_to_id || null,
                due_date: props.selectedTask.due_date || null,
                status: props.selectedTask.status,
                task_type_id: taskTypeId || props.selectedTask.task_type_id || null,
                milestone_id: milestoneId,
                tags: props.selectedTask.tags || []
            });
        } else {
            // Add mode
            Object.assign(taskForm, {
                name: '',
                description: '',
                assigned_to_user_id: null,
                due_date: null,
                status: 'To Do',
                task_type_id: null,
                milestone_id: null,
                tags: []
            });
        }
    }
}, { immediate: true });

// Function to handle the successful submission from BaseFormModal
const handleSaved = (responseData) => {
    // The response data here will be the new/updated task
    emit('saved', responseData);
    emit('close');
};

// Function to open the Add Milestone modal (passthrough from BaseFormModal's slot)
const openAddMilestoneModal = () => {
    emit('open-add-milestone-modal');
};

// Pass through the close event
const closeModal = () => {
    emit('close');
};

const formatDueDateForInput = (dateString) => {
    if (!dateString) return null;
    try {
        const date = new Date(dateString);
        return date.toISOString().split('T')[0];
    } catch (e) {
        console.error("Error formatting date for input:", e);
        return null;
    }
};

const assignedToOptions = computed(() => {
    return props.projectUsers.map(user => ({
        value: user.id,
        label: user.name
    }));
});

const taskTypeOptions = computed(() => {
    return props.taskTypes.map(type => ({
        value: type.id,
        label: type.name
    }));
});

const milestoneOptions = computed(() => {
    return props.milestones.map(milestone => ({
        value: milestone.id,
        label: milestone.name
    }));
});

// A dummy tags array for CustomMultiSelect as we don't have an API for tags specifically
// In a real app, you'd fetch available tags from an API
const availableTags = ref([
    { id: 1, name: 'Frontend' },
    { id: 2, name: 'Backend' },
    { id: 3, name: 'Urgent' },
    { id: 4, name: 'Bug' },
    { id: 5, name: 'Feature' },
    { id: 6, name: 'Testing' },
]);
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="modalTitle"
        :api-endpoint="apiEndpoint"
        :http-method="httpMethod"
        :form-data="taskForm"
        :submit-button-text="submitButtonText"
        :success-message="successMessage"
        @close="closeModal"
        @submitted="handleSaved"
    >
        <template #default="{ errors }">
            <div class="space-y-4">
                <!-- Task Name -->
                <div>
                    <InputLabel for="task-name" value="Task Name" />
                    <TextInput
                        id="task-name"
                        v-model="taskForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Enter task name"
                        required
                    />
                    <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
                </div>

                <!-- Task Description -->
                <div>
                    <InputLabel for="task-description" value="Description" />
                    <textarea
                        id="task-description"
                        v-model="taskForm.description"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows="3"
                        placeholder="Enter task description"
                    ></textarea>
                    <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
                </div>

                <!-- Task Status -->
                <div>
                    <InputLabel for="task-status" value="Status" />
                    <SelectDropdown
                        id="task-status"
                        v-model="taskForm.status"
                        :options="taskStatuses.map(s => ({ value: s, label: s }))"
                        value-key="value"
                        label-key="label"
                        placeholder="Select status"
                        class="mt-1"
                    />
                    <InputError :message="errors.status ? errors.status[0] : ''" class="mt-2" />
                </div>

                <!-- Due Date -->
                <div>
                    <InputLabel for="task-due-date" value="Due Date" />
                    <TextInput
                        id="task-due-date"
                        v-model="taskForm.due_date"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="errors.due_date ? errors.due_date[0] : ''" class="mt-2" />
                </div>

                <!-- Assigned To -->
                <div>
                    <InputLabel for="task-assigned-to" value="Assigned To" />
                    <SelectDropdown
                        id="task-assigned-to"
                        v-model="taskForm.assigned_to_user_id"
                        :options="assignedToOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="Unassigned"
                        class="mt-1"
                        :allow-empty="true"
                    />
                    <InputError :message="errors.assigned_to_user_id ? errors.assigned_to_user_id[0] : ''" class="mt-2" />
                </div>

                <!-- Task Type -->
                <div>
                    <InputLabel for="task-type" value="Task Type" />
                    <div v-if="loadingTaskTypes" class="text-sm text-gray-500">Loading task types...</div>
                    <SelectDropdown
                        v-else
                        id="task-type"
                        v-model="taskForm.task_type_id"
                        :options="taskTypeOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="Select a task type"
                        class="mt-1"
                        :allow-empty="true"
                    />
                    <InputError :message="errors.task_type_id ? errors.task_type_id[0] : ''" class="mt-2" />
                </div>

                <!-- Milestone -->
                <div>
                    <div class="flex justify-between items-center">
                        <InputLabel for="task-milestone" value="Milestone" />
                        <button
                            type="button"
                            @click="openAddMilestoneModal"
                            class="text-sm text-indigo-600 hover:text-indigo-800"
                        >
                            + Create New Milestone
                        </button>
                    </div>
                    <div v-if="loadingMilestones" class="text-sm text-gray-500">Loading milestones...</div>
                    <SelectDropdown
                        v-else
                        id="task-milestone"
                        v-model="taskForm.milestone_id"
                        :options="milestoneOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="No milestone"
                        class="mt-1"
                        :allow-empty="true"
                    />
                    <InputError :message="errors.milestone_id ? errors.milestone_id[0] : ''" class="mt-2" />
                </div>

                <!-- Tags (using CustomMultiSelect) -->
<!--                <div>-->
<!--                    <InputLabel for="task-tags" value="Tags" />-->
<!--                    <CustomMultiSelect-->
<!--                        id="task-tags"-->
<!--                        v-model="taskForm.tags"-->
<!--                        :options="availableTags"-->
<!--                        label-key="name"-->
<!--                        track-by="name"-->
<!--                        placeholder="Add tags"-->
<!--                        class="mt-1"-->
<!--                    />-->
<!--                    <InputError :message="errors.tags ? errors.tags[0] : ''" class="mt-2" />-->
<!--                </div>-->
            </div>
        </template>
    </BaseFormModal>
</template>
