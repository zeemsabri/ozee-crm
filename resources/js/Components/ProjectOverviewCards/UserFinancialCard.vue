<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useAuthUser } from '@/Directives/permissions';
import PrimaryButton from '@/Components/PrimaryButton.vue';
// Import shared currency utilities
import { formatCurrency, convertCurrency, conversionRatesToUSD, displayCurrency } from '@/Utils/currency';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['viewUserTransactions']);

const authUser = useAuthUser();
const loading = ref(true);
const error = ref(null);
const transactions = ref([]); // To hold user-specific transactions

// Use the imported reactive displayCurrency directly
const currentDisplayCurrency = displayCurrency;

const fetchUserTransactions = async () => {
    loading.value = true;
    error.value = null;

    if (!authUser.value || !authUser.value.id || !props.projectId) {
        transactions.value = [];
        loading.value = false;
        return;
    }

    // Ensure currency rates are loaded before fetching transactions that need conversion
    if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        error.value = "Currency rates not loaded. Please try again.";
        loading.value = false;
        return;
    }

    try {
        // API call to fetch transactions assigned to the specific user for this project
        // Assuming the backend handles filtering by user_id and only returns expense/bonus types
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/transactions?user_id=${authUser.value.id}`);
        transactions.value = response.data;
        console.log('User-specific transactions:', transactions.value); // For debugging
    } catch (e) {
        console.error('Failed to fetch user transactions for UserFinancialsCard:', e);
        error.value = e.response?.data?.message || 'Failed to load your financial data.';
        transactions.value = [];
    } finally {
        loading.value = false;
    }
};

// Agreed Amount for User (their base income from app's expenses)
const agreedAmountForUser = computed(() => {
    if (!authUser.value || !authUser.value.id || !transactions.value.length || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        return 0;
    }
    // Sum only 'expense' type transactions (user's base income)
    const totalAgreed = transactions.value
        .filter(t => t.type === 'expense')
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);

    return totalAgreed;
});

// Bonus Amount for User (their bonus income from app's bonuses)
const bonusAmountForUser = computed(() => {
    if (!authUser.value || !authUser.value.id || !transactions.value.length || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        return 0;
    }
    // Sum only 'bonus' type transactions
    const totalBonus = transactions.value
        .filter(t => t.type === 'bonus')
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);

    return totalBonus;
});

// Paid Amount for User (their received amount from app's payments)
const paidAmountForUser = computed(() => {
    if (!authUser.value || !authUser.value.id || !transactions.value.length || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        return 0;
    }
    // Sum only transactions that are marked as paid and are user's income types (expense or bonus)
    const totalPaid = transactions.value
        .filter(t => (t.type === 'expense' || t.type === 'bonus') && t.is_paid === 1)
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);

    return totalPaid;
});

const shouldShowUserFinancials = computed(() => {
    // Show if authUser exists AND there's any agreed, bonus, or paid amount
    return authUser.value && (agreedAmountForUser.value > 0 || bonusAmountForUser.value > 0 || paidAmountForUser.value > 0);
});

onMounted(() => {
    fetchUserTransactions();
});

// Watch for changes in authUser, projectId, and the shared displayCurrency or conversionRatesToUSD
watch([
    () => authUser.value,
    () => props.projectId,
    currentDisplayCurrency, // React to changes in the global display currency
    conversionRatesToUSD // React to changes in currency rates
], () => {
    fetchUserTransactions();
}, { deep: true }); // Deep watch for authUser changes (e.g., authUser.value.id)

const openTransactionsModal = () => {
    emit('viewUserTransactions');
};
</script>

<template>
    <div class="bg-white p-6 rounded-xl shadow-md transition-shadow hover:shadow-lg">
        <h4 class="text-lg font-semibold text-gray-900 mb-4">Your Financials</h4>

        <div v-if="loading" class="space-y-3">
            <div class="h-4 bg-gray-200 rounded animate-pulse w-full"></div>
            <div class="h-4 bg-gray-200 rounded animate-pulse w-5/6"></div>
            <div class="h-4 bg-gray-200 rounded animate-pulse w-3/4"></div>
        </div>

        <div v-else-if="error" class="text-red-600 text-sm py-2 text-center">
            <p>{{ error }}</p>
        </div>

        <div v-else>
            <dl class="space-y-1 text-sm text-gray-700">
                <div v-if="shouldShowUserFinancials">
                    <div>
                        <dt class="font-medium inline-block w-32">Agreed Amount:</dt>
                        <dd class="inline-block text-gray-900">
                            {{ formatCurrency(agreedAmountForUser, currentDisplayCurrency) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium inline-block w-32">Bonus Amount:</dt>
                        <dd class="inline-block text-gray-900">
                            {{ formatCurrency(bonusAmountForUser, currentDisplayCurrency) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium inline-block w-32">Received Amount:</dt>
                        <dd class="inline-block text-gray-900">
                            {{ formatCurrency(paidAmountForUser, currentDisplayCurrency) }}
                        </dd>
                    </div>
                </div>
                <div v-else>
                    <p v-if="!authUser" class="text-gray-400 text-sm">
                        Sign in to view your specific financials.
                    </p>
                    <p v-else class="text-gray-400 text-sm">
                        Your financials for this project are not available or you're not assigned.
                    </p>
                </div>
            </dl>
            <div v-if="authUser" class="mt-4 text-right">
                <PrimaryButton @click="openTransactionsModal">
                    View Transactions
                </PrimaryButton>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Scoped styles specific to this component can go here if needed */
</style>
