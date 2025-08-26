<script setup>
import { ref } from 'vue';

const props = defineProps({
    project: Object,
});

// Local state for active tabs within the card
const activeMainTab = ref('overview');
const activeTaskTab = ref('today');

// Local state to manage the visibility of completed tasks
const showCompleted = ref(false);

function toggleCompleted() {
    showCompleted.value = !showCompleted.value;
}
</script>

<template>
    <div class="bg-white rounded-xl shadow-md p-6">
        <!-- Card Header with Project Health Indicator -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-semibold text-gray-900">{{ project.name }}</h2>
                <!-- Role Tag -->
                <span v-if="project.role === 'Manager'" class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full">Manager</span>
                <span v-else class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Contributor</span>
                <!-- Project Health Indicator -->
                <div
                    :class="{
                        'bg-red-500': project.health === 'at-risk',
                        'bg-yellow-500': project.health === 'needs-attention',
                        'bg-green-500': project.health === 'on-track',
                    }"
                    class="w-3 h-3 rounded-full"
                    :title="`Project health status: ${project.health.replace('-', ' ')}`"
                    :aria-label="`Project health status: ${project.health.replace('-', ' ')}`">
                </div>
            </div>
        </div>

        <!-- Priority Alert Section (Manager View Only) -->
        <div v-if="project.role === 'Manager' && project.alert" class="flex items-start gap-4 p-4 mb-6 border-l-4 border-red-500 bg-red-50 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/><path d="M11 12H9V8h2v4zM11 16H9v-2h2v2z"/>
            </svg>
            <div class="flex-grow">
                <p class="font-medium text-sm text-red-800">{{ project.alert.text }}</p>
                <div class="flex justify-between items-center mt-2">
                    <span class="text-xs font-semibold text-red-600">{{ project.alert.timer }} remaining</span>
                    <span class="text-xs text-red-600">Reply in time to earn 50 points.</span>
                </div>
            </div>
        </div>

        <!-- Manager View (with Tabs) -->
        <div v-if="project.role === 'Manager'" class="tabs">
            <div class="flex border-b border-gray-200">
                <button
                    :class="{'active text-gray-900 border-b-2 border-indigo-600': activeMainTab === 'overview', 'text-gray-500 hover:text-gray-900': activeMainTab !== 'overview'}"
                    class="tab-button flex-1 py-2 text-sm font-medium focus:outline-none transition-all-colors"
                    @click="activeMainTab = 'overview'">
                    Overview
                </button>
                <button
                    :class="{'active text-gray-900 border-b-2 border-indigo-600': activeMainTab === 'tasks', 'text-gray-500 hover:text-gray-900': activeMainTab !== 'tasks'}"
                    class="tab-button flex-1 py-2 text-sm font-medium focus:outline-none transition-all-colors"
                    @click="activeMainTab = 'tasks'">
                    Team's Tasks
                </button>
                <button
                    :class="{'active text-gray-900 border-b-2 border-indigo-600': activeMainTab === 'communication', 'text-gray-500 hover:text-gray-900': activeMainTab !== 'communication'}"
                    class="tab-button flex-1 py-2 text-sm font-medium focus:outline-none transition-all-colors"
                    @click="activeMainTab = 'communication'">
                    Communication
                </button>
            </div>
            <!-- Overview Tab Content -->
            <div v-if="activeMainTab === 'overview'" class="tab-content pt-4">
                <div class="space-y-3 text-sm text-gray-700">
                    <p class="flex justify-between items-center">
                        <span class="font-medium">Milestone:</span>
                        <span>{{ project.overview.milestone }}</span>
                    </p>
                    <p class="flex justify-between items-center">
                        <span class="font-medium">Budget:</span>
                        <span>{{ project.overview.budget }}</span>
                    </p>
                    <p class="flex justify-between items-center">
                        <span class="font-medium">Project Status:</span>
                        <span class="px-2 py-0.5 text-xs font-medium text-green-800 bg-green-200 rounded-full">{{ project.overview.status }}</span>
                    </p>
                </div>
            </div>
            <!-- Team's Tasks Tab Content -->
            <div v-else-if="activeMainTab === 'tasks'" class="tab-content pt-4">
                <div>
                    <h4 class="text-base font-semibold text-gray-900 mb-2">Team Tasks</h4>
                    <div class="flex space-x-2 border-b border-gray-200 mb-3">
                        <button class="task-tab-button flex-1 text-sm font-medium py-1 transition-all-colors"
                                :class="{'text-gray-900 border-b-2 border-indigo-600': activeTaskTab === 'today', 'text-gray-500 hover:text-gray-900': activeTaskTab !== 'today'}"
                                @click="activeTaskTab = 'today'">
                            Today <span class="ml-1 px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ project.tasks.today.length }}</span>
                        </button>
                        <button class="task-tab-button flex-1 text-sm font-medium py-1 transition-all-colors"
                                :class="{'text-gray-900 border-b-2 border-indigo-600': activeTaskTab === 'tomorrow', 'text-gray-500 hover:text-gray-900': activeTaskTab !== 'tomorrow'}"
                                @click="activeTaskTab = 'tomorrow'">
                            Tomorrow <span class="ml-1 px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ project.tasks.tomorrow.length }}</span>
                        </button>
                    </div>
                    <!-- Today's Tasks Content -->
                    <div v-if="activeTaskTab === 'today'">
                        <div v-if="project.tasks.today.length" class="space-y-2 text-sm text-gray-700">
                            <div v-for="task in project.tasks.today" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                                <span>{{ task.name }}</span>
                                <div class="flex items-center space-x-2">
                                    <span v-if="task.status === 'started'" class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">Started</span>
                                    <span v-else-if="task.status === 'blocked'" class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Blocked</span>
                                    <span v-else-if="task.status === 'paused'" class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">Paused</span>
                                    <span v-else-if="task.status === 'complete'" class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Complete</span>
                                    <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-4 text-center text-sm text-gray-500">
                            <p>No tasks due today. Nice work!</p>
                        </div>
                    </div>
                    <!-- Tomorrow's Tasks Content -->
                    <div v-else>
                        <div v-if="project.tasks.tomorrow.length" class="space-y-2 text-sm text-gray-700">
                            <div v-for="task in project.tasks.tomorrow" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                                <span>{{ task.name }}</span>
                                <div class="flex items-center space-x-2">
                                    <span v-if="task.status === 'started'" class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">Started</span>
                                    <span v-else-if="task.status === 'blocked'" class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Blocked</span>
                                    <span v-else-if="task.status === 'paused'" class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">Paused</span>
                                    <span v-else-if="task.status === 'complete'" class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Complete</span>
                                    <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-4 text-center text-sm text-gray-500">
                            <p>No tasks due tomorrow. Enjoy your day!</p>
                        </div>
                    </div>

                    <!-- Completed Tasks Section -->
                    <div v-if="project.tasks.completed.length" :class="{'hidden': !showCompleted}" class="mt-4 pt-4 border-t border-gray-200">
                        <h5 class="text-sm font-semibold text-gray-500 mb-2">Completed Tasks</h5>
                        <div class="space-y-2 text-sm text-gray-400">
                            <div v-for="task in project.tasks.completed" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                                <span class="line-through">{{ task.name }}</span>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Complete</span>
                                    <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for completed task: ${task.name}`">View</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Toggle button for completed tasks -->
                    <button v-if="project.tasks.completed.length" @click="toggleCompleted" class="mt-4 text-indigo-600 text-sm font-medium hover:underline transition-all-colors" :aria-expanded="showCompleted">
                        <span v-if="!showCompleted">Show {{ project.tasks.completed.length }} Completed Task{{ project.tasks.completed.length > 1 ? 's' : '' }}</span>
                        <span v-else>Hide Completed ({{ project.tasks.completed.length }})</span>
                    </button>
                </div>
            </div>
            <!-- Communication Tab Content -->
            <div v-else class="tab-content pt-4">
                <div class="space-y-3 text-sm text-gray-700">
                    <p>Last Email Sent: <span class="font-semibold">{{ project.communication.lastSent }}</span></p>
                    <p>Last Email Received: <span class="font-semibold text-yellow-700">{{ project.communication.lastReceived }}</span></p>
                </div>
            </div>
        </div>

        <!-- Contributor View (No Main Tabs) -->
        <div v-else>
            <!-- My Tasks Section -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">My Tasks</h3>
                <!-- Sub-tabs for Today and Tomorrow tasks -->
                <div class="flex space-x-2 border-b border-gray-200 mb-3">
                    <button class="task-tab-button flex-1 text-sm font-medium py-1 transition-all-colors"
                            :class="{'text-gray-900 border-b-2 border-indigo-600': activeTaskTab === 'today', 'text-gray-500 hover:text-gray-900': activeTaskTab !== 'today'}"
                            @click="activeTaskTab = 'today'">
                        Today <span class="ml-1 px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ project.tasks.today.length }}</span>
                    </button>
                    <button class="task-tab-button flex-1 text-sm font-medium py-1 transition-all-colors"
                            :class="{'text-gray-900 border-b-2 border-indigo-600': activeTaskTab === 'tomorrow', 'text-gray-500 hover:text-gray-900': activeTaskTab !== 'tomorrow'}"
                            @click="activeTaskTab = 'tomorrow'">
                        Tomorrow <span class="ml-1 px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ project.tasks.tomorrow.length }}</span>
                    </button>
                </div>
                <!-- Today's Tasks Content -->
                <div v-if="activeTaskTab === 'today'">
                    <div v-if="project.tasks.today.length" class="space-y-2 text-sm text-gray-700">
                        <div v-for="task in project.tasks.today" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                            <span>{{ task.name }}</span>
                            <div class="flex items-center space-x-2">
                                <span v-if="task.status === 'started'" class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">Started</span>
                                <span v-else-if="task.status === 'blocked'" class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Blocked</span>
                                <span v-else-if="task.status === 'paused'" class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">Paused</span>
                                <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-4 text-center text-sm text-gray-500">
                        <p>No tasks due today. Well done!</p>
                    </div>
                </div>
                <!-- Tomorrow's Tasks Content -->
                <div v-else>
                    <div v-if="project.tasks.tomorrow.length" class="space-y-2 text-sm text-gray-700">
                        <div v-for="task in project.tasks.tomorrow" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                            <span>{{ task.name }}</span>
                            <div class="flex items-center space-x-2">
                                <span v-if="task.status === 'started'" class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">Started</span>
                                <span v-else-if="task.status === 'blocked'" class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Blocked</span>
                                <span v-else-if="task.status === 'paused'" class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">Paused</span>
                                <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for task: ${task.name}`">View</a>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-4 text-center text-sm text-gray-500">
                        <p>No tasks due tomorrow. Enjoy your day!</p>
                    </div>
                </div>

                <!-- Completed Tasks Section -->
                <div v-if="project.tasks.completed.length" :class="{'hidden': !showCompleted}" class="mt-4 pt-4 border-t border-gray-200">
                    <h5 class="text-sm font-semibold text-gray-500 mb-2">Completed Tasks</h5>
                    <div class="space-y-2 text-sm text-gray-400">
                        <div v-for="task in project.tasks.completed" :key="task.name" class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                            <span class="line-through">{{ task.name }}</span>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Complete</span>
                                <a href="#" class="text-xs text-indigo-600 font-medium hover:underline transition-all-colors" :aria-label="`View details for completed task: ${task.name}`">View</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toggle button for completed tasks -->
                <button v-if="project.tasks.completed.length" @click="toggleCompleted" class="mt-4 text-indigo-600 text-sm font-medium hover:underline transition-all-colors" :aria-expanded="showCompleted">
                    <span v-if="!showCompleted">Show {{ project.tasks.completed.length }} Completed Task{{ project.tasks.completed.length > 1 ? 's' : '' }}</span>
                    <span v-else>Hide Completed ({{ project.tasks.completed.length }})</span>
                </button>
            </div>
            <!-- Current Milestone Section -->
            <div class="p-4 bg-indigo-50 rounded-lg mt-6">
                <h3 class="text-base font-semibold text-indigo-900 mb-2">Current Milestone: {{ project.milestone.name }}</h3>
                <p class="text-sm text-indigo-700 mb-3">Due: {{ project.milestone.deadline }}</p>
                <!-- Progress bar component -->
                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                    <div class="bg-indigo-600 h-2.5 rounded-full" :style="`width: ${project.milestone.progress}%`"></div>
                </div>
                <p class="text-right text-xs font-medium text-indigo-600">{{ project.milestone.progress }}% Complete</p>
                <p class="text-xs text-indigo-800 italic mt-3">{{ project.milestone.incentive }}</p>
            </div>
        </div>
    </div>
</template>
