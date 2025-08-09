<script setup>
import RightSidebar from '@/Components/RightSidebar.vue';
import TaskDetailSidebar from '@/Components/ProjectTasks/TaskDetailSidebar.vue';
import SecondaryButton from "@/Components/SecondaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import { sidebarState, closeTaskDetailSidebar } from '@/Utils/sidebar';
import { ref } from 'vue';

const taskDetailSidebarRef = ref(null);

defineEmits(['taskUpdated', 'taskDeleted']);
</script>

<template>
    <RightSidebar
        :show="sidebarState.show"
        @update:show="closeTaskDetailSidebar"
        :title="sidebarState.taskId ? 'Task Details' : 'Details'"
        :initialWidth="50"
    >
        <template #content>
            <TaskDetailSidebar
                v-if="sidebarState.taskId"
                :task-id="sidebarState.taskId"
                :project-id="sidebarState.projectId"
                :project-users="sidebarState.projectUsers"
                ref="taskDetailSidebarRef"
                @close="closeTaskDetailSidebar"
                @task-updated="(task) => { $emit('taskUpdated', task); }"
                @task-deleted="(taskId) => { $emit('taskDeleted', taskId); closeTaskDetailSidebar(); }"
            />
            <div v-else-if="sidebarState.show" class="p-4 text-gray-500">
                No task selected or task ID is missing.
            </div>
        </template>

        <template #footer>
            <div v-if="sidebarState.taskId" class="flex justify-end space-x-3">
                <SecondaryButton
                    v-if="taskDetailSidebarRef && taskDetailSidebarRef.task && taskDetailSidebarRef.task.status !== 'In Progress' && taskDetailSidebarRef.task.status !== 'Done'"
                    @click="taskDetailSidebarRef.startTask"
                    class="bg-blue-50 text-blue-700 hover:bg-blue-100"
                >
                    Start Task
                </SecondaryButton>
                <SecondaryButton
                    v-if="taskDetailSidebarRef && taskDetailSidebarRef.task && taskDetailSidebarRef.task.status === 'In Progress'"
                    @click="taskDetailSidebarRef.completeTask"
                    class="bg-green-50 text-green-700 hover:bg-green-100"
                >
                    Complete Task
                </SecondaryButton>
                <DangerButton
                    v-if="taskDetailSidebarRef && taskDetailSidebarRef.task"
                    @click="taskDetailSidebarRef.deleteTask"
                    class="bg-red-50 text-red-700 hover:bg-red-100"
                >
                    Delete Task
                </DangerButton>
            </div>
        </template>
    </RightSidebar>
</template>
