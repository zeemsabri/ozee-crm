import { ref } from 'vue';

// Global state for the task detail sidebar
export const sidebarState = ref({
    show: false,
    taskId: null,
    projectId: null,
});

/**
 * Opens the task detail sidebar with the given taskId and projectId.
 * @param {number} taskId
 * @param {number} projectId
 */
export const openTaskDetailSidebar = (taskId, projectId) => {
    sidebarState.value.taskId = taskId;
    sidebarState.value.projectId = projectId;
    sidebarState.value.show = true;
};

/**
 * Closes the task detail sidebar.
 */
export const closeTaskDetailSidebar = () => {
    sidebarState.value.show = false;
    sidebarState.value.taskId = null;
    sidebarState.value.projectId = null;
};
