// A simple centralized state and utility for managing a global right sidebar.
import { reactive } from 'vue';

const sidebarState = reactive({
    show: false,
    taskId: null,
    projectId: null,
    projectUsers: [],
    // Add other state as needed for different sidebar components, e.g.,
    // deliverableId: null,
});

const openTaskDetailSidebar = (taskId, projectId, projectUsers) => {
    console.log('Opening task sidebar with:', { taskId, projectId, projectUsers: projectUsers?.length || 0 });
    sidebarState.taskId = taskId;
    sidebarState.projectId = projectId;
    sidebarState.projectUsers = projectUsers;
    sidebarState.show = true;
};

const closeTaskDetailSidebar = () => {
    sidebarState.taskId = null;
    sidebarState.projectId = null;
    sidebarState.projectUsers = [];
    sidebarState.show = false;
};

// You can add more functions for other sidebar types, like this:
// const openDeliverableDetailSidebar = (deliverableId) => {
//     sidebarState.deliverableId = deliverableId;
//     sidebarState.show = true;
// };
//
// const closeDeliverableDetailSidebar = () => {
//     sidebarState.deliverableId = null;
//     sidebarState.show = false;
// };

export {
    sidebarState,
    openTaskDetailSidebar,
    closeTaskDetailSidebar,
    // openDeliverableDetailSidebar,
    // closeDeliverableDetailSidebar
};
