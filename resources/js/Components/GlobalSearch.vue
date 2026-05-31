<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Search, Loader2 } from 'lucide-vue-next';
import debounce from 'lodash/debounce';
import { openTaskDetailSidebar } from '@/Utils/sidebar';
import { openEmailDetailSidebar } from '@/Utils/email-sidebar';

const query = ref('');
const results = ref({});
const isLoading = ref(false);
const showDropdown = ref(false);
const searchContainer = ref(null);

const performSearch = debounce(async (searchQuery) => {
    if (!searchQuery.trim()) {
        results.value = {};
        isLoading.value = false;
        return;
    }

    try {
        const { data } = await window.axios.get('/api/global-search', {
            params: { q: searchQuery }
        });
        results.value = data;
    } catch (error) {
        console.error('Search failed', error);
        results.value = {};
    } finally {
        isLoading.value = false;
    }
}, 300);

watch(query, (newVal) => {
    isLoading.value = true;
    showDropdown.value = true;
    performSearch(newVal);
});

const closeSearch = (e) => {
    if (searchContainer.value && !searchContainer.value.contains(e.target)) {
        showDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeSearch);
});

onUnmounted(() => {
    document.removeEventListener('click', closeSearch);
});

const hasResults = () => {
    return Object.keys(results.value).some(key => results.value[key] && results.value[key].length > 0);
};

const formatCategoryName = (key) => {
    return key.charAt(0).toUpperCase() + key.slice(1);
};

const handleResultClick = (item, category, event) => {
    if (category === 'tasks') {
        event.preventDefault();

        if (item.project_id) {
            openTaskDetailSidebar(item.id, item.project_id);
            showDropdown.value = false;
            return;
        }

        if (item.url) {
            window.location.href = item.url;
        }
        return;
    }

    if (category === 'emails') {
        event.preventDefault();
        openEmailDetailSidebar(item.id, {
            subject: item.subject,
            type: item.type,
            created_at: item.created_at,
            sender: item.sender,
            recipient_email: item.recipient_email,
        });
        showDropdown.value = false;
    }
};
</script>

<template>
    <div ref="searchContainer" class="relative w-full max-w-lg ml-4">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <Search class="h-4 w-4 text-gray-400" />
            </div>
            <input
                v-model="query"
                type="text"
                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-gray-50 placeholder-gray-500 focus:outline-none focus:bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition duration-150 ease-in-out"
                placeholder="Search tasks, emails, projects, proposals, bills, invoices..."
                @focus="query.length > 0 && (showDropdown = true)"
            />
            <div v-if="isLoading" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <Loader2 class="h-4 w-4 text-gray-400 animate-spin" />
            </div>
        </div>

        <div v-if="showDropdown && query.length > 0" class="absolute z-50 mt-1 w-full bg-white rounded-md shadow-lg border border-gray-200 overflow-hidden max-h-96 overflow-y-auto">
            <div v-if="isLoading && !hasResults()" class="p-4 text-center text-sm text-gray-500">
                Searching...
            </div>
            
            <div v-else-if="!hasResults()" class="p-4 text-center text-sm text-gray-500">
                No results found for "{{ query }}"
            </div>

            <div v-else class="py-2">
                <template v-for="(items, category) in results" :key="category">
                    <div v-if="items && items.length > 0" class="mb-2 last:mb-0">
                        <div class="px-4 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
                            {{ formatCategoryName(category) }}
                        </div>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="item in items" :key="item.id">
                                <a
                                    :href="item.url"
                                    @click="handleResultClick(item, category, $event)"
                                    class="block px-4 py-2 hover:bg-indigo-50 transition duration-150 ease-in-out text-sm text-gray-700"
                                >
                                    {{ item.title }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
