<script setup>
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    allProjects: {
        type: Array,
        default: () => [],
    },
    activeProjectId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['project-selected']);

// Initialize isCollapsed: Default to true (collapsed), unless localStorage explicitly says false
const isCollapsed = ref(localStorage.getItem('leftSidebarCollapsed') === 'false' ? false : true);
const searchTerm = ref('');
const activeCategoryFilter = ref(null); // 'type' or 'department'
const activeCategoryValue = ref(null);

// Save collapse state to localStorage whenever it changes
watch(isCollapsed, (newVal) => {
    localStorage.setItem('leftSidebarCollapsed', newVal);
});

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
};

// Extract unique project types and departments
const uniqueProjectTypes = computed(() => {
    const types = new Set();
    props.allProjects.forEach(p => {
        if (p.project_type) types.add(p.project_type);
    });
    return Array.from(types).sort();
});

const uniqueDepartments = computed(() => {
    const departments = new Set();
    props.allProjects.forEach(p => {
        if (p.department) departments.add(p.department);
    });
    return Array.from(departments).sort();
});

// Filtered and categorized projects
const filteredProjects = computed(() => {
    let projects = props.allProjects;

    // Apply search term
    if (searchTerm.value) {
        projects = projects.filter(p =>
            p.name.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
            p.description?.toLowerCase().includes(searchTerm.value.toLowerCase())
        );
    }

    // Apply category filter
    if (activeCategoryFilter.value && activeCategoryValue.value) {
        projects = projects.filter(p => {
            if (activeCategoryFilter.value === 'type') {
                return p.project_type === activeCategoryValue.value;
            } else if (activeCategoryFilter.value === 'department') {
                return p.department === activeCategoryValue.value;
            }
            return true;
        });
    }

    return projects;
});

const groupedProjects = computed(() => {
    if (!activeCategoryFilter.value) {
        // If no category filter, return all filtered projects in a single group
        return { 'All Projects': filteredProjects.value };
    }

    const groups = {};
    filteredProjects.value.forEach(p => {
        const categoryValue = activeCategoryFilter.value === 'type' ? p.project_type : p.department;
        const groupName = categoryValue || 'Uncategorized';
        if (!groups[groupName]) {
            groups[groupName] = [];
        }
        groups[groupName].push(p);
    });

    // Sort groups by name, and projects within groups by name
    const sortedGroups = Object.keys(groups).sort().reduce((acc, key) => {
        acc[key] = groups[key].sort((a, b) => a.name.localeCompare(b.name));
        return acc;
    }, {});

    return sortedGroups;
});

const selectProject = (projectId) => {
    emit('project-selected', projectId);
};

const clearFilters = () => {
    searchTerm.value = '';
    activeCategoryFilter.value = null;
    activeCategoryValue.value = null;
};
</script>

<template>
    <div
        :class="{
            'w-64': !isCollapsed,
            'w-16': isCollapsed,
            'min-w-16': true, /* ensure min width for collapse button */
            'bg-gray-800 text-white flex flex-col transition-all duration-300 ease-in-out shrink-0 h-screen overflow-hidden': true,
        }"
    >
        <!-- Header & Toggle Button -->
        <div class="flex items-center p-4 h-16 border-b border-gray-700">
            <h2 v-if="!isCollapsed" class="text-xl font-semibold flex-1 truncate">Projects</h2>
            <button
                @click="toggleCollapse"
                class="p-2 rounded-full hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                :title="isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'"
            >
                <svg
                    class="w-6 h-6 text-gray-400 transform transition-transform duration-300"
                    :class="{ 'rotate-180': isCollapsed }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                </svg>
            </button>
        </div>

        <!-- Search & Filters (visible only when not collapsed) -->
        <div v-if="!isCollapsed" class="p-4 border-b border-gray-700">
            <input
                type="text"
                v-model="searchTerm"
                placeholder="Search projects..."
                class="w-full p-2 rounded-md bg-gray-700 border border-gray-600 text-white placeholder-gray-400 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            />
            <div class="mt-3 text-xs text-gray-400">
                Categorize by:
                <button
                    @click="activeCategoryFilter = 'type'; activeCategoryValue = null;"
                    :class="{'bg-indigo-600 text-white': activeCategoryFilter === 'type', 'bg-gray-700 text-gray-300': activeCategoryFilter !== 'type'}"
                    class="ml-2 px-2 py-1 rounded-full hover:bg-indigo-500 transition-colors"
                >
                    Type
                </button>
                <button
                    @click="activeCategoryFilter = 'department'; activeCategoryValue = null;"
                    :class="{'bg-indigo-600 text-white': activeCategoryFilter === 'department', 'bg-gray-700 text-gray-300': activeCategoryFilter !== 'department'}"
                    class="ml-2 px-2 py-1 rounded-full hover:bg-indigo-500 transition-colors"
                >
                    Department
                </button>
                <button
                    v-if="searchTerm || activeCategoryFilter"
                    @click="clearFilters"
                    class="ml-2 px-2 py-1 rounded-full bg-red-600 text-white hover:bg-red-700 transition-colors"
                    title="Clear Filters"
                >
                    Clear
                </button>
            </div>

            <!-- Sub-filters for categories -->
            <div v-if="activeCategoryFilter && !isCollapsed" class="mt-3 max-h-32 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-gray-700">
                <div v-if="activeCategoryFilter === 'type'">
                    <button
                        v-for="type in uniqueProjectTypes"
                        :key="type"
                        @click="activeCategoryValue = type"
                        :class="{'bg-gray-600 text-white': activeCategoryValue === type, 'text-gray-300 hover:bg-gray-700': activeCategoryValue !== type}"
                        class="block w-full text-left px-3 py-1 text-sm rounded-md mb-1"
                    >
                        {{ type }}
                    </button>
                    <button
                        v-if="uniqueProjectTypes.length === 0"
                        class="block w-full text-left px-3 py-1 text-sm text-gray-500"
                    >
                        No project types found.
                    </button>
                </div>
                <div v-if="activeCategoryFilter === 'department'">
                    <button
                        v-for="dept in uniqueDepartments"
                        :key="dept"
                        @click="activeCategoryValue = dept"
                        :class="{'bg-gray-600 text-white': activeCategoryValue === dept, 'text-gray-300 hover:bg-gray-700': activeCategoryValue !== dept}"
                        class="block w-full text-left px-3 py-1 text-sm rounded-md mb-1"
                    >
                        {{ dept }}
                    </button>
                    <button
                        v-if="uniqueDepartments.length === 0"
                        class="block w-full text-left px-3 py-1 text-sm text-gray-500"
                    >
                        No departments found.
                    </button>
                </div>
            </div>
        </div>

        <!-- Project List -->
        <div class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-gray-700">
            <div v-if="!isCollapsed" class="p-4">
                <div v-for="(projectsInGroup, groupName) in groupedProjects" :key="groupName" class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-400 mb-2">{{ groupName }}</h3>
                    <ul class="space-y-1">
                        <li v-for="project in projectsInGroup" :key="project.id">
                            <Link
                                :href="route('projects.show', project.id)"
                                @click="selectProject(project.id)"
                                :class="{
                                    'block w-full text-left px-3 py-2 rounded-md transition-colors': true,
                                    'bg-indigo-600 text-white': project.id === activeProjectId,
                                    'hover:bg-gray-700 text-gray-200': project.id !== activeProjectId,
                                    'font-bold': project.id === activeProjectId,
                                }"
                            >
                                <span class="truncate">{{ project.name }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
                <div v-if="Object.keys(groupedProjects).length === 0 && (searchTerm || activeCategoryFilter)" class="text-gray-400 text-sm text-center py-4">
                    No projects match your filter.
                </div>
                <div v-else-if="Object.keys(groupedProjects).length === 0 && !searchTerm && !activeCategoryFilter" class="text-gray-400 text-sm text-center py-4">
                    No projects available.
                </div>
            </div>
            <div v-else class="flex flex-col items-center pt-4 space-y-4">
                <Link
                    v-for="project in props.allProjects.slice(0, 5)"
                :key="project.id"
                :href="route('projects.show', project.id)"
                @click="selectProject(project.id)"
                :class="{
                'w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-200': true,
                'bg-indigo-600 text-white': project.id === activeProjectId,
                'bg-gray-700 text-gray-300 hover:bg-gray-600': project.id !== activeProjectId,
                }"
                :title="project.name"
                >
                {{ project.name.charAt(0).toUpperCase() }}
                </Link>
                <div v-if="allProjects.length === 0" class="text-gray-400 text-xs text-center px-2">
                    No Projects
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom scrollbar styles */
.scrollbar-thin::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: #4a5568; /* Tailwind gray-700 */
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #a0aec0; /* Tailwind gray-500 */
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #cbd5e0; /* Tailwind gray-400 */
}

/* For Firefox */
.scrollbar-thin {
    scrollbar-width: thin;
    scrollbar-color: #a0aec0 #4a5568; /* thumb and track */
}
</style>
