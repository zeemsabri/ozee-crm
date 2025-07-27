<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useAuthUser, usePermissions } from '@/Directives/permissions';
// Import formatCurrency, convertCurrency, and the shared displayCurrency and conversionRatesToUSD
import { formatCurrency, convertCurrency, conversionRatesToUSD, displayCurrency } from '@/Utils/currency'; // Import displayCurrency here

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    canViewProjectServicesAndPayments: {
        type: Boolean,
        default: false,
    },
    canViewProjectTransactions: {
        type: Boolean,
        default: false,
    },
    // Remove displayCurrency from props, as we will use the shared reactive reference directly
    // displayCurrency: {
    //     type: String,
    //     default: 'USD',
    // },
});

const financials = ref(null); // Holds data from /sections/services-payment
const rawTransactions = ref([]); // Now holds raw transactions from backend
const loading = ref(true);
const error = ref(null);

// Use the imported reactive displayCurrency directly
const currentDisplayCurrency = displayCurrency;

// Computed properties for financial summary (all converted to displayCurrency on frontend)
const contractValue = computed(() => {
    // Ensure rates are loaded before attempting conversion
    if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        return 0; // Return 0 or handle as an error state if rates aren't ready
    }
    const value = parseFloat(financials.value?.total_amount || 0);
    // Use currentDisplayCurrency.value for conversion
    return convertCurrency(value, 'USD', currentDisplayCurrency.value);
});

const spent = computed(() => {
    if (!rawTransactions.value.length || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        return 0;
    }
    return rawTransactions.value.filter(t => t.type === 'expense')
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);
});

const income = computed(() => {
    if (!rawTransactions.value.length || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        return 0;
    }
    return rawTransactions.value.filter(t => t.type === 'income')
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);
});

const profitLoss = computed(() => {
    return income.value - spent.value;
});

const fetchFinancialsData = async () => {
    loading.value = true;
    error.value = null;
    rawTransactions.value = []; // Reset raw transactions on new fetch

    if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        error.value = "Currency rates not loaded. Please try again.";
        loading.value = false;
        return;
    }

    if (!props.canViewProjectServicesAndPayments && !props.canViewProjectTransactions) {
        error.value = "You don't have permission to view project financials.";
        loading.value = false;
        return;
    }

    try {
        if (props.canViewProjectServicesAndPayments) {
            const servicesAndPaymentsResponse = await window.axios.get(`/api/projects/${props.projectId}/sections/services-payment`);
            financials.value = servicesAndPaymentsResponse.data;
        } else {
            financials.value = null;
        }

        if (props.canViewProjectTransactions) {
            const transactionsResponse = await window.axios.get(`/api/projects/${props.projectId}/sections/transactions`);
            rawTransactions.value = transactionsResponse.data;
        } else {
            rawTransactions.value = [];
        }

    } catch (e) {
        console.error('Failed to fetch project financials:', e);
        error.value = e.response?.data?.message || 'Failed to load financial data.';
    } finally {
        loading.value = false;
    }
};

// Watch for changes in permission props, and directly watch the imported displayCurrency and conversionRatesToUSD
watch([
    () => props.canViewProjectServicesAndPayments,
    () => props.canViewProjectTransactions,
    currentDisplayCurrency, // Watch the reactive ref directly from currency.js
    conversionRatesToUSD // Watch the reactive ref directly
], (newValues, oldValues) => {
    if (props.projectId && conversionRatesToUSD.value && Object.keys(conversionRatesToUSD.value).length > 0) {
        fetchFinancialsData();
    } else if (!props.projectId) {
        loading.value = true;
        error.value = null;
    }
}, { immediate: true, deep: true });

</script>

<template>
    <div class="w-full">
        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="i in 4" :key="i" class="p-4 bg-gray-100 rounded-lg animate-pulse h-24">
                <div class="h-4 bg-gray-200 rounded w-1/2 mb-3"></div>
                <div class="h-6 bg-gray-200 rounded w-3/4"></div>
            </div>
        </div>

        <div v-else-if="error" class="text-red-600 text-sm py-2 text-center">
            <p>{{ error }}</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 bg-blue-100 rounded-lg shadow-sm transition-shadow hover:shadow-lg">
                <p class="text-sm font-medium text-blue-800">Contract Value</p>
                <p class="text-2xl font-bold text-blue-900 mt-1">
                    {{ formatCurrency(contractValue, currentDisplayCurrency.value) }}
                </p>
            </div>

            <div class="p-4 bg-green-100 rounded-lg shadow-sm transition-shadow hover:shadow-lg">
                <p class="text-sm font-medium text-green-800">Total Income</p>
                <p class="text-2xl font-bold text-green-900 mt-1">
                    {{ formatCurrency(income, currentDisplayCurrency) }}
                </p>
            </div>

            <div class="p-4 bg-red-100 rounded-lg shadow-sm transition-shadow hover:shadow-lg">
                <p class="text-sm font-medium text-red-800">Total Expenses</p>
                <p class="text-2xl font-bold mt-1">
                    {{ formatCurrency(spent, currentDisplayCurrency) }}
                </p>
            </div>

            <div class="p-4 rounded-lg shadow-sm transition-shadow hover:shadow-lg" :class="{
                'bg-green-100': profitLoss >= 0,
                'bg-red-100': profitLoss < 0
            }">
                <p class="text-sm font-medium" :class="{
                    'text-green-800': profitLoss >= 0,
                    'text-red-800': profitLoss < 0
                }">Profit/Loss</p>
                <p class="text-2xl font-bold mt-1" :class="{
                    'text-green-900': profitLoss >= 0,
                    'text-red-900': profitLoss < 0
                }">
                    {{ formatCurrency(profitLoss, currentDisplayCurrency) }}
                </p>
            </div>
        </div>

        <dl v-if="!loading && !error && financials?.contract_details"
            class="space-y-2 text-sm text-gray-700 mt-6 pt-4 border-t border-gray-200"
        >
            <div v-if="financials?.contract_details">
                <dt class="font-medium w-full block mt-2">Contract Details:</dt>
                <dd class="text-gray-900 whitespace-pre-wrap">{{ financials.contract_details }}</dd>
            </div>
        </dl>
        <p v-if="!loading && !error && !financials && !rawTransactions.length" class="text-gray-400 text-sm mt-4 text-center">
            No financial information available.
        </p>
    </div>
</template>
