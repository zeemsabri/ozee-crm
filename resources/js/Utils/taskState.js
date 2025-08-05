/**
 * Task State Management Utility
 *
 * This utility provides centralized functions for managing task state transitions
 * and ensures consistent behavior across different components.
 */

import axios from 'axios';
import * as notification from '@/Utils/notification.js';

/**
 * Fetch all tasks assigned to the current user
 *
 * @returns {Promise<Array>} - Array of task objects
 */
export const fetchAssignedTasks = async () => {
    try {
        const response = await axios.get('/api/assigned-tasks');
        return response.data;
    } catch (error) {
        console.error('Error fetching assigned tasks:', error);
        notification.error('Failed to load assigned tasks');
        throw error;
    }
};

/**
 * Fetch tasks for a specific project
 *
 * @param {number} projectId - The project ID
 * @returns {Promise<Array>} - Array of task objects
 */
export const fetchProjectTasks = async (projectId) => {
    try {
        const response = await axios.get(`/api/projects/${projectId}/tasks`);
        return response.data;
    } catch (error) {
        console.error('Error fetching project tasks:', error);
        notification.error('Failed to load project tasks');
        throw error;
    }
};

/**
 * Fetch due and overdue tasks for a specific project
 *
 * @param {number} projectId - The project ID
 * @returns {Promise<Array>} - Array of task objects
 */
export const fetchDueAndOverdueTasks = async (projectId) => {
    try {
        const response = await axios.get(`/api/projects/${projectId}/due-and-overdue-tasks`);
        return response.data;
    } catch (error) {
        console.error('Error fetching due and overdue tasks:', error);
        notification.error('Failed to load due and overdue tasks');
        throw error;
    }
};

/**
 * Fetch task details
 *
 * @param {number} taskId - The task ID
 * @returns {Promise<Object>} - Task object
 */
export const fetchTaskDetails = async (taskId) => {
    try {
        const response = await axios.get(`/api/tasks/${taskId}`);
        return response.data;
    } catch (error) {
        console.error('Error fetching task details:', error);
        notification.error('Failed to load task details');
        throw error;
    }
};

/**
 * Start a task (change status from To Do to In Progress)
 *
 * @param {Object} task - The task object
 * @returns {Promise<Object>} - The updated task object
 */
export const startTask = async (task) => {
    if (!task || task.status === 'In Progress') {
        return task;
    }

    try {
        notification.info('Starting task...');
        const response = await axios.post(`/api/tasks/${task.id}/start`);
        notification.success('Task started successfully');
        return response.data;
    } catch (error) {
        console.error('Error starting task:', error);
        notification.error('Failed to start task. Please try again.');
        throw error;
    }
};

/**
 * Pause a task (change status from In Progress to Paused)
 *
 * @param {Object} task - The task object
 * @returns {Promise<Object>} - The updated task object
 */
export const pauseTask = async (task) => {
    if (!task || task.status !== 'In Progress') {
        return task;
    }

    try {
        notification.info('Pausing task...');
        const response = await axios.post(`/api/tasks/${task.id}/pause`);
        notification.success('Task paused successfully');
        return response.data;
    } catch (error) {
        console.error('Error pausing task:', error);
        notification.error('Failed to pause task. Please try again.');
        throw error;
    }
};

/**
 * Resume a task (change status from Paused to In Progress)
 *
 * @param {Object} task - The task object
 * @returns {Promise<Object>} - The updated task object
 */
export const resumeTask = async (task) => {
    if (!task || task.status !== 'Paused') {
        return task;
    }

    try {
        notification.info('Resuming task...');
        const response = await axios.post(`/api/tasks/${task.id}/resume`);
        notification.success('Task resumed successfully');
        return response.data;
    } catch (error) {
        console.error('Error resuming task:', error);
        notification.error('Failed to resume task. Please try again.');
        throw error;
    }
};

/**
 * Complete a task (change status to Done)
 *
 * @param {Object} task - The task object
 * @returns {Promise<Object>} - The updated task object
 */
export const completeTask = async (task) => {
    if (!task || task.status === 'Done') {
        return task;
    }

    // Prevent completing a task that hasn't been started
    if (task.status !== 'In Progress') {
        notification.warning('Task must be started before it can be completed');
        throw new Error('Task must be started before it can be completed');
    }

    try {
        notification.info('Completing task...');
        const response = await axios.patch(`/api/tasks/${task.id}/complete`);
        notification.success('Task completed successfully');
        return response.data;
    } catch (error) {
        console.error('Error completing task:', error);
        if (error.response && error.response.status === 422) {
            notification.warning(error.response.data.message || 'Task must be started before it can be completed');
        } else {
            notification.error('Failed to complete task. Please try again.');
        }
        throw error;
    }
};

/**
 * Block a task (change status to Blocked)
 *
 * @param {Object} task - The task object
 * @param {string} reason - The reason for blocking the task
 * @returns {Promise<Object>} - The updated task object
 */
export const blockTask = async (task, reason) => {
    if (!task || task.status === 'Blocked') {
        return task;
    }

    if (!reason) {
        notification.warning('Please provide a reason for blocking the task');
        throw new Error('Blocking reason is required');
    }

    try {
        notification.info('Blocking task...');
        const response = await axios.post(`/api/tasks/${task.id}/block`, { reason });
        notification.success('Task blocked successfully');
        return response.data;
    } catch (error) {
        console.error('Error blocking task:', error);
        notification.error('Failed to block task. Please try again.');
        throw error;
    }
};

/**
 * Unblock a task (change status from Blocked back to previous status or To Do)
 *
 * @param {Object} task - The task object
 * @returns {Promise<Object>} - The updated task object
 */
export const unblockTask = async (task) => {
    if (!task || task.status !== 'Blocked') {
        return task;
    }

    try {
        notification.info('Unblocking task...');
        const response = await axios.post(`/api/tasks/${task.id}/unblock`);
        notification.success('Task unblocked successfully');
        return response.data;
    } catch (error) {
        console.error('Error unblocking task:', error);
        notification.error('Failed to unblock task. Please try again.');
        throw error;
    }
};

/**
 * Revise a task (change status from Done back to To Do)
 *
 * @param {Object} task - The task object
 * @returns {Promise<Object>} - The updated task object
 */
export const reviseTask = async (task) => {
    if (!task || task.status !== 'Done') {
        return task;
    }

    try {
        notification.info('Revising task...');
        const response = await axios.post(`/api/tasks/${task.id}/revise`);
        notification.success('Task revised successfully');
        return response.data;
    } catch (error) {
        console.error('Error revising task:', error);
        if (error.response && error.response.status === 422) {
            notification.warning(error.response.data.message || 'Only completed tasks can be revised');
        } else {
            notification.error('Failed to revise task. Please try again.');
        }
        throw error;
    }
};

/**
 * Delete a task
 *
 * @param {Object} task - The task object
 * @returns {Promise<boolean>} - True if deletion was successful
 */
export const deleteTask = async (task) => {
    if (!task) {
        return false;
    }

    // Check if task can be deleted (only To Do tasks can be deleted)
    if (task.status !== 'To Do') {
        notification.warning('Only tasks in To Do status can be deleted');
        throw new Error('Only tasks in To Do status can be deleted');
    }

    if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        return false;
    }

    try {
        notification.info('Deleting task...');
        await axios.delete(`/api/tasks/${task.id}`);
        notification.success('Task deleted successfully');
        return true;
    } catch (error) {
        console.error('Error deleting task:', error);
        notification.error('Failed to delete task. Please try again.');
        throw error;
    }
};

/**
 * Get the appropriate action button based on task status
 *
 * @param {Object} task - The task object
 * @returns {Object} - Button configuration { type, label, action, disabled }
 */
export const getTaskActionButton = (task) => {
    if (!task) {
        return { type: 'primary', label: 'Start', action: 'start', disabled: true };
    }

    switch (task.status) {
        case 'To Do':
            return { type: 'primary', label: 'Start', action: 'start', disabled: false };
        case 'In Progress':
            return { type: 'warning', label: 'Pause', action: 'pause', disabled: false };
        case 'Paused':
            return { type: 'primary', label: 'Resume', action: 'resume', disabled: false };
        case 'Blocked':
            return { type: 'danger', label: 'Unblock', action: 'unblock', disabled: false };
        case 'Done':
            return { type: 'success', label: 'Completed', action: null, disabled: true };
        default:
            return { type: 'primary', label: 'Start', action: 'start', disabled: true };
    }
};

/**
 * Get the CSS classes for task status badge
 *
 * @param {string} status - The task status
 * @returns {Object} - CSS classes object
 */
export const getTaskStatusClasses = (status) => {
    switch (status) {
        case 'To Do':
            return 'bg-yellow-100 text-yellow-800';
        case 'In Progress':
            return 'bg-blue-100 text-blue-800';
        case 'Paused':
            return 'bg-orange-100 text-orange-800';
        case 'Blocked':
            return 'bg-red-100 text-red-800';
        case 'Done':
            return 'bg-green-100 text-green-800';
        case 'Archived':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

/**
 * Get the CSS classes for task priority badge
 *
 * @param {string} priority - The task priority
 * @returns {Object} - CSS classes object
 */
export const getTaskPriorityClasses = (priority) => {
    switch (priority) {
        case 'high':
            return 'bg-red-100 text-red-800';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800';
        case 'low':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

/**
 * Format priority for display (capitalize first letter)
 *
 * @param {string} priority - The task priority
 * @returns {string} - Formatted priority
 */
export const formatPriority = (priority) => {
    if (!priority) return 'Medium';
    return priority.charAt(0).toUpperCase() + priority.slice(1);
};
