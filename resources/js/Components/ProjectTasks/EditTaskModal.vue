<script setup>
import { ref, reactive, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    show: Boolean,
    projectId: Number, // Still needed for context for the API endpoint
    selectedTask: Object, // Required for edit mode
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
    // Tags removed as per original TaskFormModal.vue comment
});

const taskStatuses = ['To Do', 'In Progress', 'Done', 'Blocked', 'Archived'];

// Computed properties for BaseFormModal
const modalTitle = computed(() => 'Edit Task'); // Always 'Edit Task' now
const apiEndpoint = computed(() => `/api/tasks/${props.selectedTask?.id}`);
const httpMethod = 'put'; // Always 'put' for editing
const submitButtonText = 'Update Task';
const successMessage = 'Task updated successfully!';

// Watch for changes in `show` prop and `selectedTask` to initialize form data
// IMPORTANT FIX: Added `= []` to the old values array for immediate:true to prevent TypeError
watch(() => [props.show, props.selectedTask], ([newShow, newTask], [oldShow, oldTask] = []) => {
    if (newShow && newTask) {
        // Edit mode
        let milestoneId = null;
        if (newTask.milestone) {
            const foundMilestone = props.milestones.find(m => m.name === newTask.milestone);
            if (foundMilestone) { milestoneId = foundMilestone.id; }
        } else if (newTask.milestone_id) { // Fallback if milestone is ID directly
            milestoneId = newTask.milestone_id;
        }

        let taskTypeId = null;
        if (newTask.task_type) {
            const foundTaskType = props.taskTypes.find(t => t.name === newTask.task_type);
            if (foundTaskType) { taskTypeId = foundTaskType.id; }
        } else if (newTask.task_type_id) { // Fallback if task_type is ID directly
            taskTypeId = newTask.task_type_id;
        }

        let assignedToUserId = null;
        if (newTask.assigned_to && newTask.assigned_to !== 'Unassigned') {
            const foundUser = props.projectUsers?.find(u => u.name === newTask.assigned_to);
            if (foundUser) { assignedToUserId = foundUser.id; }
        } else if (newTask.assigned_to_id) { // Fallback if assigned_to is ID directly
            assignedToUserId = newTask.assigned_to_id;
        }


        Object.assign(taskForm, {
            name: newTask.title,
            description: newTask.description || '',
            assigned_to_user_id: assignedToUserId,
            due_date: newTask.due_date || null,
            status: newTask.status,
            task_type_id: taskTypeId,
            milestone_id: milestoneId,
            // tags: newTask.tags || [] // If tags are needed, re-enable
        });
    } else if (!newShow) {
        // Reset form when modal closes
        Object.assign(taskForm, {
            name: '', description: '', assigned_to_user_id: null, due_date: null,
            status: 'To Do', task_type_id: null, milestone_id: null, //tags: []
        });
    }
}, { immediate: true });


// Function to handle the successful submission from BaseFormModal
const handleSaved = (responseData) => {
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
            </div>
        </template>
    </BaseFormModal>
</template>
