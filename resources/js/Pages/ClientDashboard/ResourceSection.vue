<script setup>
import { ref, onMounted, inject, computed, watch } from 'vue';

const props = defineProps({
    initialAuthToken: {
        type: String,
        required: true,
    },
    projectId: {
        type: [String, Number],
        required: true,
    },
    shareableResources: { // Initial data from parent, if available
        type: Array,
        default: () => []
    }
});

const emits = defineEmits(['add-activity']); // For logging activity to dashboard

const isLoading = ref(true);
const resourcesList = ref([]); // Reactive state for resources
const apiError = ref(null); // To store any API errors

const searchQuery = ref(''); // For text search across resources
const selectedTag = ref('All'); // For tag filtering
const selectedType = ref('All'); // For type filtering

// Inject the showModal service from the parent (ClientDashboard.vue)
const { showModal } = inject('modalService');

// --- Computed properties for filters and data ---

// Extracts all unique tag names from shareable resources
const allUniqueTags = computed(() => {
    const tags = new Set();
    props.shareableResources.forEach(resource => {
        resource.tags.forEach(tag => tags.add(tag.name));
    });
    return ['All', ...Array.from(tags).sort()];
});

// Extracts all unique resource types
const allUniqueTypes = computed(() => {
    const types = new Set();
    props.shareableResources.forEach(resource => {
        types.add(resource.type);
    });
    return ['All', ...Array.from(types).sort()];
});

// Filters resources based on search query, selected tag, and selected type
const filteredResources = computed(() => {
    let filtered = [...resourcesList.value];

    // Apply text search
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(resource =>
            resource.title.toLowerCase().includes(query) ||
            (resource.description && resource.description.toLowerCase().includes(query)) ||
            (resource.tags && resource.tags.some(tag => tag.name.toLowerCase().includes(query))) ||
            resource.type.toLowerCase().includes(query)
        );
    }

    // Apply tag filter
    if (selectedTag.value !== 'All') {
        filtered = filtered.filter(resource =>
            resource.tags && resource.tags.some(tag => tag.name === selectedTag.value)
        );
    }

    // Apply type filter
    if (selectedType.value !== 'All') {
        filtered = filtered.filter(resource => resource.type === selectedType.value);
    }

    return filtered;
});

// --- Methods ---

// Function to fetch resources from the API
const fetchResources = async () => {
    isLoading.value = true;
    apiError.value = null;
    try {
        const response = await fetch(`/api/client-api/project/${props.projectId}/shareable-resources`, {
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMessage = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Failed to fetch resources.');
            throw new Error(errorMessage);
        }

        resourcesList.value = data; // Update reactive list
    } catch (err) {
        console.error("Error fetching resources:", err);
        apiError.value = err.message || 'An unexpected error occurred while fetching resources.';
        showModal('Error', apiError.value, 'alert');
    } finally {
        isLoading.value = false;
    }
};

// Handles clicking on a resource card to open its URL
const handleViewResource = (url) => {
    if (url) {
        window.open(url, '_blank');
    } else {
        showModal('No Link', 'This resource does not have an associated link.', 'alert');
    }
};

// Helper function to get icon for resource type
const getResourceIcon = (type) => {
    switch (type) {
        case 'youtube': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-youtube text-red-500 w-6 h-6"><path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0 2 2 0 0 1 1.4 1.4 24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.56 49.56 0 0 1-16.2 0 2 2 0 0 1-1.4-1.4Z"/><path d="m10 15 5-3-5-3z"/></svg>`;
        case 'website': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-globe text-blue-500 w-6 h-6"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>`;
        case 'document': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file text-green-500 w-6 h-6"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>`;
        case 'image': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image text-purple-500 w-6 h-6"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`;
        case 'pdf': return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text text-red-500 w-6 h-6"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`;
        default: return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link text-gray-500 w-6 h-6"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07L9.4 6.6A2 2 0 0 1 8.07 8z"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.41-1.41A2 2 0 0 1 15.93 16z"/></svg>`;
    }
};

// Initial data load when the component is mounted
onMounted(() => {
    // Prioritize prop data if available, otherwise fetch
    if (props.shareableResources && props.shareableResources.length > 0) {
        resourcesList.value = [...props.shareableResources];
        isLoading.value = false;
    } else {
        fetchResources();
    }
});

// Watch for changes in the parent's shareableResources prop
// This is useful if the parent fetches data asynchronously after this component mounts
watch(() => props.shareableResources, (newResources) => {
    if (newResources && newResources.length > 0) {
        resourcesList.value = [...newResources];
        isLoading.value = false;
    }
});
</script>

<template>
    <div class="p-6 bg-gray-100 min-h-screen font-inter text-gray-800">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open-text mr-2 w-6 h-6"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h6z"/><path d="M10 12H6"/><path d="M14 12h4"/></svg>
                Knowledge Base & Resources
            </h2>

            <!-- Search and Filters Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="relative md:col-span-2">
                    <input
                        type="text"
                        v-model="searchQuery"
                        placeholder="Search resources by title, description, or tag..."
                        class="w-full p-3 pl-10 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-200"
                        aria-label="Search Resources"
                    >
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search text-gray-400 w-5 h-5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </div>
                </div>

                <!-- Tag Filter -->
                <div>
                    <label for="tag-filter" class="sr-only">Filter by Tag:</label>
                    <select id="tag-filter" v-model="selectedTag"
                            class="w-full p-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-200 bg-white"
                    >
                        <option value="All">All Tags</option>
                        <option v-for="tag in allUniqueTags" :key="tag" :value="tag">{{ tag }}</option>
                    </select>
                </div>

                <!-- Type Filter -->
                <div>
                    <label for="type-filter" class="sr-only">Filter by Type:</label>
                    <select id="type-filter" v-model="selectedType"
                            class="w-full p-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-200 bg-white"
                    >
                        <option value="All">All Types</option>
                        <option v-for="type in allUniqueTypes" :key="type" :value="type">{{ type.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()) }}</option>
                    </select>
                </div>
            </div>

            <!-- Resources List -->
            <div v-if="isLoading" class="text-center text-gray-600 py-12">
                <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p>Loading resources...</p>
            </div>
            <div v-else-if="apiError" class="text-center text-red-600 py-12">
                <p class="font-semibold mb-2">Error loading resources:</p>
                <p>{{ apiError }}</p>
            </div>
            <div v-else-if="filteredResources.length === 0 && (searchQuery || selectedTag !== 'All' || selectedType !== 'All')" class="text-center text-gray-500 py-12">
                <p class="text-lg mb-2">No resources match your current filters or search query.</p>
                <p>Try adjusting your filters or clearing the search.</p>
            </div>
            <div v-else-if="resourcesList.length === 0" class="text-center text-gray-500 py-12">
                <p class="text-lg mb-2">No resources available for this project yet.</p>
                <p>Check back later or contact your project manager.</p>
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div v-for="resource in filteredResources" :key="resource.id"
                     class="bg-gray-50 rounded-lg shadow-sm border border-gray-200 flex flex-col overflow-hidden cursor-pointer hover:shadow-md transition-shadow duration-200"
                     @click="handleViewResource(resource.url)"
                >
                    <div class="p-5 flex-grow">
                        <div class="flex items-center mb-3">
                            <div v-html="getResourceIcon(resource.type)" class="flex-shrink-0 mr-3"></div>
                            <h3 class="text-lg font-semibold text-gray-900 line-clamp-2">{{ resource.title }}</h3>
                        </div>
                        <p class="text-sm text-gray-700 line-clamp-3 mb-3">{{ resource.description || 'No description provided.' }}</p>
                        <div class="flex flex-wrap gap-2 mt-auto">
                            <span v-for="tag in resource.tags" :key="tag.id"
                                  class="px-3 py-1 bg-indigo-100 text-indigo-700 text-xs font-medium rounded-full">
                                {{ tag.name }}
                            </span>
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-200 bg-white flex justify-end">
                        <button class="bg-blue-600 text-white py-2 px-4 rounded-lg font-semibold text-sm hover:bg-blue-700 transition-colors duration-200 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-external-link mr-1"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                            View Resource
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}
/* Specific styling for search input to place icon inside */
.relative input[type="text"] {
    padding-left: 2.5rem; /* Adjust padding to make space for the icon */
}
</style>
