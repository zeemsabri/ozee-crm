<script setup>
import { defineEmits, onMounted, watch } from 'vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { fetchCurrencyRates, displayCurrency } from '@/Utils/currency';

const props = defineProps({
    activeFilter: String,
});

const emits = defineEmits(['update:filter']);

function setFilter(filter) {
    emits('update:filter', filter);
}

// Currency handling (shared across app via Utils/currency)
const currentDisplayCurrency = displayCurrency;

const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

onMounted(async () => {
    // Load persisted currency if present
    const stored = localStorage.getItem('displayCurrency');
    if (stored) {
        currentDisplayCurrency.value = stored;
    }
    // Ensure rates are available
    await fetchCurrencyRates();
});

watch(currentDisplayCurrency, (val) => {
    if (val) localStorage.setItem('displayCurrency', val);
});
</script>

<template>
    <!-- Currency selector aligned to the extreme right -->
    <div class="ml-auto w-28 sm:w-36 z-[999]">
        <SelectDropdown
            id="workspace-display-currency"
            v-model="currentDisplayCurrency"
            :options="currencyOptions"
            placeholder="Currency"
        />
    </div>
    <div class="flex items-center p-1 bg-gray-200 rounded-xl overflow-x-auto">

        <div class="flex justify-start space-x-2">
            <button
                :class="{'bg-indigo-600 text-white font-semibold': activeFilter === 'all', 'bg-white text-gray-700 font-medium hover:bg-gray-100': activeFilter !== 'all'}"
                class="px-4 py-2 text-sm rounded-lg whitespace-nowrap transition-all-colors"
                @click="setFilter('all')">
                All Projects
            </button>
            <button
                :class="{'bg-indigo-600 text-white font-semibold': activeFilter === 'manager', 'bg-white text-gray-700 font-medium hover:bg-gray-100': activeFilter !== 'manager'}"
                class="px-4 py-2 text-sm rounded-lg whitespace-nowrap transition-all-colors"
                @click="setFilter('manager')">
                Projects I Manage
            </button>
            <button
                :class="{'bg-indigo-600 text-white font-semibold': activeFilter === 'contributor', 'bg-white text-gray-700 font-medium hover:bg-gray-100': activeFilter !== 'contributor'}"
                class="px-4 py-2 text-sm rounded-lg whitespace-nowrap transition-all-colors"
                @click="setFilter('contributor')">
                My Projects
            </button>
        </div>


    </div>
</template>
