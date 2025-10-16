<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import { ChevronDownIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';

const props = defineProps({
    selectedCategories: {
        type: Array,
        default: () => []
    },
    filters: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['update:selectedCategories']);

// State
const categories = ref([]);
const loadingCategories = ref(false);
const showDropdown = ref(false);
const multiSelectMode = ref(false);

// Computed properties
const priorityCategories = computed(() => {
    return categories.value.filter(cat => cat.has_unread);
});

const dropdownCategories = computed(() => {
    const filtered = categories.value.filter(cat => !cat.has_unread);
    
    if (filtered.length === 0 && categories.value.length === 0) {
        return [];
    }
    
    // If no categories have unread emails, show up to 5 categories in ascending order
    if (priorityCategories.value.length === 0) {
        return filtered
            .sort((a, b) => a.name.localeCompare(b.name))
            .slice(0, Math.min(5, filtered.length));
    }
    
    return filtered.sort((a, b) => a.name.localeCompare(b.name));
});

const remainingDropdownCategories = computed(() => {
    if (priorityCategories.value.length === 0) {
        // If no unread categories, return remaining after first 5
        const filtered = categories.value.filter(cat => !cat.has_unread);
        return filtered
            .sort((a, b) => a.name.localeCompare(b.name))
            .slice(5);
    }
    
    return dropdownCategories.value;
});

const hasDropdownCategories = computed(() => {
    return remainingDropdownCategories.value.length > 0;
});

// Methods
const fetchCategoryStats = async () => {
    loadingCategories.value = true;
    try {
        const params = new URLSearchParams();
        
        // Add current filters to get accurate category counts
        if (props.filters.type) params.append('type', props.filters.type);
        if (props.filters.status) params.append('status', props.filters.status);
        if (props.filters.startDate) params.append('start_date', props.filters.startDate);
        if (props.filters.endDate) params.append('end_date', props.filters.endDate);
        if (props.filters.search) params.append('search', props.filters.search);
        if (props.filters.projectId) params.append('project_id', props.filters.projectId);
        if (props.filters.senderId) params.append('sender_id', props.filters.senderId);

        const response = await axios.get(`/api/inbox/category-stats?${params.toString()}`);
        categories.value = response.data.categories;
    } catch (error) {
        console.error('Failed to fetch category stats:', error);
        categories.value = [];
    } finally {
        loadingCategories.value = false;
    }
};

const toggleCategory = (categoryId) => {
    const currentSelected = [...props.selectedCategories];
    const index = currentSelected.indexOf(categoryId);
    
    if (multiSelectMode.value) {
        // Multi-select mode: add/remove from array
        if (index > -1) {
            currentSelected.splice(index, 1);
        } else {
            currentSelected.push(categoryId);
        }
        emit('update:selectedCategories', currentSelected);
    } else {
        // Single-select mode: replace array with single item or clear if clicking same item
        if (index > -1) {
            // Clicking the same category - clear selection
            emit('update:selectedCategories', []);
        } else {
            // Clicking a different category - replace selection
            emit('update:selectedCategories', [categoryId]);
        }
    }
};

const toggleMultiSelectMode = () => {
    multiSelectMode.value = !multiSelectMode.value;
    
    // If switching to single-select mode and multiple categories are selected,
    // keep only the first selected category
    if (!multiSelectMode.value && props.selectedCategories.length > 1) {
        emit('update:selectedCategories', [props.selectedCategories[0]]);
    }
};

const clearAllCategories = () => {
    emit('update:selectedCategories', []);
};

const isCategorySelected = (categoryId) => {
    return props.selectedCategories.includes(categoryId);
};

const getCategoryByDropdownId = (categoryId) => {
    return remainingDropdownCategories.value.find(cat => cat.id === categoryId);
};

const handleDropdownCategoryClick = (categoryId) => {
    toggleCategory(categoryId);
    showDropdown.value = false;
};

// Watch for filter changes to refresh category stats
watch(() => props.filters, fetchCategoryStats, { deep: true });

// Lifecycle
onMounted(() => {
    fetchCategoryStats();
});
</script>

<template>
    <div v-permission="'view_all_emails'" class="px-6 pb-4">
        <div v-if="loadingCategories" class="text-sm text-gray-500">
            Loading categories...
        </div>
        
        <div v-else-if="categories.length > 0" class="space-y-3">
            <!-- Control buttons -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button
                        @click="toggleMultiSelectMode"
                        :class="{
                            'px-3 py-1.5 rounded-md text-sm font-medium transition-colors flex items-center space-x-1': true,
                            'bg-blue-600 text-white hover:bg-blue-700': multiSelectMode,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200': !multiSelectMode,
                        }"
                        :title="multiSelectMode ? 'Switch to single selection' : 'Enable multiple selection'"
                    >
                        <Squares2X2Icon class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ multiSelectMode ? 'Multi-Select' : 'Single-Select' }}</span>
                    </button>
                    
                    <span class="text-xs text-gray-500">
                        {{ multiSelectMode ? 'Click multiple categories to combine filters' : 'Click a category to filter, click again to clear' }}
                    </span>
                </div>
                
                <button
                    v-if="props.selectedCategories.length > 0"
                    @click="clearAllCategories"
                    class="px-3 py-1.5 rounded-md text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors"
                >
                    Clear All
                </button>
            </div>
            
            <!-- Category filters -->
            <div class="flex items-center flex-wrap gap-2">
            <!-- Priority Categories (with unread emails) -->
            <button
                v-for="category in priorityCategories"
                :key="category.id"
                @click="toggleCategory(category.id)"
:class="{
                    'px-3 py-1.5 rounded-full text-sm font-medium transition-colors flex items-center space-x-1 border-2': true,
                    'bg-blue-600 text-white hover:bg-blue-700 border-blue-600': isCategorySelected(category.id),
                    'bg-gray-100 text-gray-700 hover:bg-gray-200 border-gray-200 hover:border-gray-300': !isCategorySelected(category.id),
                }"
            >
                <span>{{ category.name }}</span>
                <span 
                    v-if="category.unread_count > 0"
                    :class="{
                        'ml-1 px-1.5 py-0.5 text-xs font-semibold rounded-full': true,
                        'bg-white text-blue-600': isCategorySelected(category.id),
                        'bg-blue-600 text-white': !isCategorySelected(category.id),
                    }"
                >
                    {{ category.unread_count }}
                </span>
            </button>

            <!-- Show first 5 read-only categories if no unread categories exist -->
            <template v-if="priorityCategories.length === 0">
                <button
                    v-for="category in dropdownCategories.slice(0, 5)"
                    :key="category.id"
                    @click="toggleCategory(category.id)"
                    :class="{
                        'px-3 py-1.5 rounded-full text-sm font-medium transition-colors border-2': true,
                        'bg-blue-600 text-white hover:bg-blue-700 border-blue-600': isCategorySelected(category.id),
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 border-gray-200 hover:border-gray-300': !isCategorySelected(category.id),
                    }"
                >
                    <span>{{ category.name }}</span>
                </button>
            </template>

            <!-- "More Categories" Dropdown -->
            <div v-if="hasDropdownCategories" class="relative">
                <button
                    @click="showDropdown = !showDropdown"
                    class="px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors flex items-center space-x-1"
                >
                    <span>More Categories</span>
                    <ChevronDownIcon 
                        :class="{
                            'h-4 w-4 transform transition-transform duration-200': true,
                            'rotate-180': showDropdown
                        }"
                    />
                </button>

                <!-- Dropdown Menu -->
                <div 
                    v-if="showDropdown"
                    class="absolute top-full left-0 mt-1 w-64 bg-white rounded-md shadow-lg border border-gray-200 z-50 max-h-64 overflow-y-auto"
                >
                    <div class="py-1">
                        <button
                            v-for="category in remainingDropdownCategories"
                            :key="category.id"
                            @click="handleDropdownCategoryClick(category.id)"
                            :class="{
                                'w-full text-left px-4 py-2 text-sm transition-colors flex items-center justify-between': true,
                                'bg-blue-50 text-blue-700': isCategorySelected(category.id),
                                'text-gray-700 hover:bg-gray-50': !isCategorySelected(category.id),
                            }"
                        >
                            <span>{{ category.name }}</span>
                            <span 
                                v-if="isCategorySelected(category.id)"
                                class="text-blue-600 text-xs"
                            >
                                ✓
                            </span>
                        </button>
                    </div>
                </div>
            </div>

                <!-- Click outside to close dropdown -->
                <div 
                    v-if="showDropdown"
                    @click="showDropdown = false"
                    class="fixed inset-0 z-40"
                ></div>
            </div>
        </div>
        
        <div v-else-if="!loadingCategories" class="text-sm text-gray-500">
            No categories available
        </div>
    </div>
</template>