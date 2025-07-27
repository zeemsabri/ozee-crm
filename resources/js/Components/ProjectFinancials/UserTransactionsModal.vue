<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import { useAuthUser } from '@/Directives/permissions'; // Assuming useAuthUser is available

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    projectId: {
        type: Number,
        required: true,
    },
    projectName: {
        type: String,
        required: false,
    }
});

const emit = defineEmits(['close']);

const authUser = useAuthUser();
const loading = ref(true);
const error = ref(null);
const userTransactions = ref([]);

// --- Currency Conversion and Formatting Utilities (Copied for consistency) ---
const conversionRatesToUSD = {
    PKR: 0.0034, AUD: 0.65, INR: 0.012, EUR: 1.08, GBP: 1.28, USD: 1.0
};
const displayCurrency = ref('PKR'); // Default display currency for this modal's list

const formatCurrency = (amount, currency = 'USD') => {
    if (isNaN(amount) || amount == null) return '0.00';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
};
const convertCurrency = (amount, fromCurrency, toCurrency) => {
    if (!amount || isNaN(amount) || !fromCurrency || !toCurrency) return 0;
    if (fromCurrency === toCurrency) return Number(amount);
    const fromRate = conversionRatesToUSD[fromCurrency.toUpperCase()];
    const toRate = conversionRatesToUSD[toCurrency.toUpperCase()];
    if (!fromRate || !toRate) {
        console.warn(`Invalid currency conversion rates for: ${fromCurrency} to ${toCurrency}`);
        return 0;
    }
    const amountInUSD = Number(amount) * fromRate;
    const convertedAmount = amountInUSD / toRate;
    return Number(convertedAmount.toFixed(2));
};
// --- End Currency Utilities ---

const fetchUserTransactions = async () => {
    loading.value = true;
    error.value = null;
    userTransactions.value = [];

    if (!authUser.value || !authUser.value.id || !props.projectId) {
        error.value = "User not authenticated or project ID missing.";
        loading.value = false;
        return;
    }

    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/transactions?user_id=${authUser.value.id}`);
        userTransactions.value = response.data;
    } catch (e) {
        console.error('Failed to fetch user-specific transactions for modal:', e);
        error.value = e.response?.data?.message || 'Failed to load transactions.';
    } finally {
        loading.value = false;
    }
};

// Fetch data when modal is shown or projectId/authUser changes
watch(() => props.show, (newValue) => {
    if (newValue) {
        fetchUserTransactions();
    }
}, { immediate: false }); // Do not fetch immediately on initial mount, only when `show` becomes true

// Watch authUser and projectId for changes while modal is open (less common but good for reactivity)
watch([() => authUser.value?.id, () => props.projectId], (newVals, oldVals) => {
    if (props.show) { // Only refetch if modal is currently visible
        fetchUserTransactions();
    }
}, { deep: true });

const modalTitle = computed(() => `Your Transactions for Project ${props.projectId}`);
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="'Transactions for: ' +projectName"
        :show-footer="false"
        @close="$emit('close')"
        :hide-submit-button="true"
        :hide-cancel-button="true"
        :api-endpoint="null"
        form-data="{}"
    >
        <template #default="{ errors }">
            <div class="p-4">
                <div v-if="loading" class="text-center text-gray-600">
                    <div class="space-y-3">
                        <div class="h-4 bg-gray-200 rounded animate-pulse w-full"></div>
                        <div class="h-4 bg-gray-200 rounded animate-pulse w-5/6"></div>
                        <div class="h-4 bg-gray-200 rounded animate-pulse w-full"></div>
                        <div class="h-4 bg-gray-200 rounded animate-pulse w-2/3"></div>
                    </div>
                </div>
                <div v-else-if="error" class="text-red-600 text-sm py-2">
                    <p>{{ error }}</p>
                </div>
                <div v-else-if="userTransactions.length === 0" class="text-gray-500 text-center py-4">
                    No transactions found for you on this project.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="transaction in userTransactions" :key="transaction.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-900">{{ transaction.description }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ transaction.type }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-900">
                                {{ formatCurrency(transaction.amount, transaction.currency) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                    <span :class="{
                                        'px-2 py-1 rounded-full text-xs font-medium': true,
                                        'bg-green-100 text-green-800': transaction.is_paid === 1,
                                        'bg-yellow-100 text-yellow-800': transaction.is_paid === 0
                                    }">
                                        {{ transaction.is_paid === 1 ? 'Paid' : 'Unpaid' }}
                                    </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ new Date(transaction.created_at).toLocaleDateString() }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>
