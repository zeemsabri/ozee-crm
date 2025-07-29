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

// Reactive object to store collapse state for each category section
const sectionCollapsedState = ref({});

// --- Computed Properties (Moved to before watch) ---
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

// Filtered projects (only by search term now)
const filteredProjects = computed(() => {
    if (!searchTerm.value) {
        return props.allProjects;
    }
    const lowerCaseQuery = searchTerm.value.toLowerCase();
    return props.allProjects.filter(p =>
        p.name.toLowerCase().includes(lowerCaseQuery) ||
        p.description?.toLowerCase().includes(lowerCaseQuery) ||
        p.project_type?.toLowerCase().includes(lowerCaseQuery) || // Allow searching by type/dept
        p.department?.toLowerCase().includes(lowerCaseQuery)
    );
});

// Categorization logic: Grouping for display in collapsible sections
const getProjectsByType = computed(() => {
    const groups = {};
    filteredProjects.value.forEach(p => {
        if (p.project_type) {
            if (!groups[p.project_type]) {
                groups[p.project_type] = [];
            }
            groups[p.project_type].push(p);
        }
    });
    // Sort projects within each group by name
    for (const type in groups) {
        groups[type].sort((a, b) => a.name.localeCompare(b.name));
    }
    return groups;
});

const getProjectsByDepartment = computed(() => {
    const groups = {};
    filteredProjects.value.forEach(p => {
        if (p.department) {
            if (!groups[p.department]) {
                groups[p.department] = [];
            }
            groups[p.department].push(p);
        }
    });
    // Sort projects within each group by name
    for (const dept in groups) {
        groups[dept].sort((a, b) => a.name.localeCompare(b.name));
    }
    return groups;
});

const getUncategorizedProjects = computed(() => {
    return filteredProjects.value.filter(p => !p.project_type && !p.department)
        .sort((a, b) => a.name.localeCompare(b.name));
});
// --- End Computed Properties ---


// Watch for allProjects changes to initialize/update sectionCollapsedState
watch(() => props.allProjects, () => {
    // Initialize all type sections to collapsed by default if not already set
    uniqueProjectTypes.value.forEach(type => {
        const key = `type-${type}`;
        if (sectionCollapsedState.value[key] === undefined) { // Only set if not already present
            sectionCollapsedState.value[key] = true; // Default to collapsed
        }
    });

    // Initialize all department sections to collapsed by default if not already set
    uniqueDepartments.value.forEach(dept => {
        const key = `department-${dept}`;
        if (sectionCollapsedState.value[key] === undefined) { // Only set if not already present
            sectionCollapsedState.value[key] = true; // Default to collapsed
        }
    });

    // Initialize main category containers to collapsed (these are now collapsible headers)
    if (sectionCollapsedState.value['main-types'] === undefined) {
        sectionCollapsedState.value['main-types'] = false; // Start expanded for visibility
    }
    if (sectionCollapsedState.value['main-departments'] === undefined) {
        sectionCollapsedState.value['main-departments'] = false; // Start expanded for visibility
    }
    if (sectionCollapsedState.value['uncategorized'] === undefined) {
        sectionCollapsedState.value['uncategorized'] = false; // Start expanded for visibility
    }

}, { immediate: true });


// Save collapse state to localStorage whenever it changes
watch(isCollapsed, (newVal) => {
    localStorage.setItem('leftSidebarCollapsed', newVal);
});

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
};

// Toggle individual category sub-sections
const toggleSectionCollapse = (sectionKey) => {
    sectionCollapsedState.value[sectionKey] = !sectionCollapsedState.value[sectionKey];
};

const selectProject = (projectId) => {
    emit('project-selected', projectId);
};

const clearSearch = () => {
    searchTerm.value = '';
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

        <!-- Search (visible only when not collapsed) -->
        <div v-if="!isCollapsed" class="p-4 border-b border-gray-700">
            <div class="relative">
                <input
                    type="text"
                    v-model="searchTerm"
                    placeholder="Search all projects..."
                    class="w-full p-2 rounded-md bg-gray-700 border border-gray-600 text-white placeholder-gray-400 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                />
                <button
                    v-if="searchTerm"
                    @click="clearSearch"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-200"
                    title="Clear search"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Project List - Expanded View (categorized and collapsible) -->
        <div v-if="!isCollapsed" class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-gray-700 p-4">
            <!-- Projects by Type Category -->
            <div v-if="Object.keys(getProjectsByType).length > 0" class="mb-4">
                <div class="flex items-center justify-between cursor-pointer py-2 hover:bg-gray-700 rounded-md px-2" @click="toggleSectionCollapse('main-types')">
                    <h3 class="text-sm font-semibold text-gray-400">By Type</h3>
                    <svg class="w-4 h-4 text-gray-400 transform transition-transform duration-200" :class="{ 'rotate-90': !sectionCollapsedState['main-types'] }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>
                <div v-show="!sectionCollapsedState['main-types']" class="ml-2 mt-2 space-y-1">
                    <div v-for="(projectsInType, typeName) in getProjectsByType" :key="typeName">
                        <div class="flex items-center justify-between cursor-pointer py-1 hover:bg-gray-700 rounded-md px-2" @click="toggleSectionCollapse(`type-${typeName}`)">
                            <h4 class="text-sm font-medium text-gray-300 truncate">{{ typeName }}</h4>
                            <svg class="w-4 h-4 text-gray-400 transform transition-transform duration-200" :class="{ 'rotate-90': !sectionCollapsedState[`type-${typeName}`] }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                        <ul v-show="!sectionCollapsedState[`type-${typeName}`]" class="ml-4 mt-1 space-y-1">
                            <li v-for="project in projectsInType" :key="project.id">
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
                </div>
            </div>

            <!-- Projects by Department Category -->
            <div v-if="Object.keys(getProjectsByDepartment).length > 0" class="mb-4">
                <div class="flex items-center justify-between cursor-pointer py-2 hover:bg-gray-700 rounded-md px-2" @click="toggleSectionCollapse('main-departments')">
                    <h3 class="text-sm font-semibold text-gray-400">By Department</h3>
                    <svg class="w-4 h-4 text-gray-400 transform transition-transform duration-200" :class="{ 'rotate-90': !sectionCollapsedState['main-departments'] }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>
                <div v-show="!sectionCollapsedState['main-departments']" class="ml-2 mt-2 space-y-1">
                    <div v-for="(projectsInDept, deptName) in getProjectsByDepartment" :key="deptName">
                        <div class="flex items-center justify-between cursor-pointer py-1 hover:bg-gray-700 rounded-md px-2" @click="toggleSectionCollapse(`department-${deptName}`)">
                            <h4 class="text-sm font-medium text-gray-300 truncate">{{ deptName }}</h4>
                            <svg class="w-4 h-4 text-gray-400 transform transition-transform duration-200" :class="{ 'rotate-90': !sectionCollapsedState[`department-${deptName}`] }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                        <ul v-show="!sectionCollapsedState[`department-${deptName}`]" class="ml-4 mt-1 space-y-1">
                            <li v-for="project in projectsInDept" :key="project.id">
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
                </div>
            </div>

            <div v-if="getUncategorizedProjects.length > 0" class="mb-4">
                <div class="flex items-center justify-between cursor-pointer py-2 hover:bg-gray-700 rounded-md px-2" @click="toggleSectionCollapse('uncategorized')">
                    <h3 class="text-sm font-semibold text-gray-400">Uncategorized</h3>
                    <svg class="w-4 h-4 text-gray-400 transform transition-transform duration-200" :class="{ 'rotate-90': !sectionCollapsedState['uncategorized'] }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>
                <ul v-show="!sectionCollapsedState['uncategorized']" class="ml-2 mt-2 space-y-1">
                    <li v-for="project in getUncategorizedProjects" :key="project.id">
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

            <div v-if="filteredProjects.length === 0" class="text-gray-400 text-sm text-center py-4">
                No projects found matching your search.
            </div>
            <div v-else-if="allProjects.length === 0 && !searchTerm" class="text-gray-400 text-sm text-center py-4">
                No projects available.
            </div>
        </div>

        <div v-else class="flex flex-col items-center pt-4 space-y-4">
            <Link
                v-for="project in props.allProjects" :key="project.id"
                :href="route('projects.show', project.id)"
                @click="selectProject(project.id)"
                :class="{
                    'w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-200': true,
                    'bg-indigo-600 text-white': project.id === activeProjectId,
                    'bg-gray-700 text-gray-300 hover:bg-gray-600': project.id !== activeProjectId,
                }"
                :title="project.name" >
                {{ project.name.charAt(0).toUpperCase() }}
            </Link>
            <div v-if="allProjects.length === 0" class="text-gray-400 text-xs text-center px-2">
                No Projects
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
