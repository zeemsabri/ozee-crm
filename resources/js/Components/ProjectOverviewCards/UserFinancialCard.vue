<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useAuthUser } from '@/Directives/permissions';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatCurrency, convertCurrency, conversionRatesToUSD, displayCurrency } from '@/Utils/currency';
import { CurrencyDollarIcon } from '@heroicons/vue/20/solid';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import {UsersIcon} from "@heroicons/vue/20/solid/index.js";


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
const transactions = ref([]);
const currentDisplayCurrency = displayCurrency;

const fetchUserTransactions = async () => {
    loading.value = true;
    error.value = null;

    if (!authUser.value || !authUser.value.id || !props.projectId) {
        transactions.value = [];
        loading.value = false;
        return;
    }

    if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        error.value = "Currency rates not loaded. Please try again.";
        loading.value = false;
        return;
    }

    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/transactions?user_id=${authUser.value.id}`);
        transactions.value = response.data;
    } catch (e) {
        console.error('Failed to fetch user transactions for UserFinancialsCard:', e);
        error.value = e.response?.data?.message || 'Failed to load your financial data.';
        transactions.value = [];
    } finally {
        loading.value = false;
    }
};

const agreedAmountForUser = computed(() => {
    if (!authUser.value || !authUser.value.id || !transactions.value.length || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        return 0;
    }
    const totalAgreed = transactions.value
        .filter(t => t.type === 'expense')
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);

    return totalAgreed;
});

const bonusAmountForUser = computed(() => {
    if (!authUser.value || !authUser.value.id || !transactions.value.length || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        return 0;
    }
    const totalBonus = transactions.value
        .filter(t => t.type === 'bonus')
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);

    return totalBonus;
});

const paidAmountForUser = computed(() => {
    if (!authUser.value || !authUser.value.id || !transactions.value.length || !conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
        return 0;
    }
    const totalPaid = transactions.value
        .filter(t => (t.type === 'expense' || t.type === 'bonus') && t.is_paid === 1)
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);

    return totalPaid;
});

const shouldShowUserFinancials = computed(() => {
    return authUser.value && (agreedAmountForUser.value > 0 || bonusAmountForUser.value > 0 || paidAmountForUser.value > 0);
});

const paidPercentage = computed(() => {
    const totalEarnings = agreedAmountForUser.value + bonusAmountForUser.value;
    if (totalEarnings === 0) return 0;
    return Math.round((paidAmountForUser.value / totalEarnings) * 100);
});

onMounted(() => {
    fetchUserTransactions();
});

watch([
    () => authUser.value,
    () => props.projectId,
    currentDisplayCurrency,
    conversionRatesToUSD
], () => {
    fetchUserTransactions();
}, { deep: true });

const openTransactionsModal = () => {
    emit('viewUserTransactions');
};
</script>

<template>
    <div class="bg-white p-4 rounded-xl shadow-md transition-shadow hover:shadow-lg flex flex-col h-full">
        <div class="flex items-center space-x-2 mb-3">
            <CurrencyDollarIcon class="h-5 w-5 text-gray-500" />
            <h4 class="text-sm font-semibold text-gray-900">Your Financials</h4>
        </div>

        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <div class="space-y-2 w-full">
                <div class="h-3 bg-gray-200 rounded animate-pulse w-full"></div>
                <div class="h-3 bg-gray-200 rounded animate-pulse w-5/6"></div>
            </div>
        </div>

        <div v-else-if="error" class="flex-1 flex items-center justify-center text-red-600 text-xs text-center">
            <p>{{ error }}</p>
        </div>

        <div v-else class="flex-1 flex flex-col overflow-hidden">
            <div v-if="shouldShowUserFinancials" class="space-y-2 text-xs">
                <div class="flex items-center space-x-2 text-gray-500 mb-3">
                    <UsersIcon class="h-5 w-5" />
                    <span class="text-xs font-semibold">{{ transactions.length }} Transactions(s)</span>
                </div>
                <div class="flex flex-col p-2 bg-green-50 rounded-lg">
                    <span class="font-medium text-green-700">Agreed Amount</span>
                    <span class="text-gray-900 font-bold mt-1">{{ formatCurrency(agreedAmountForUser, currentDisplayCurrency) }}</span>
                </div>
                <div class="flex flex-col p-2 bg-blue-50 rounded-lg">
                    <span class="font-medium text-blue-700">Bonus Amount</span>
                    <span class="text-gray-900 font-bold mt-1">{{ formatCurrency(bonusAmountForUser, currentDisplayCurrency) }}</span>
                </div>
                <div class="flex flex-col p-2 bg-indigo-50 rounded-lg">
                    <span class="font-medium text-indigo-700">Received Amount</span>
                    <span class="text-gray-900 font-bold mt-1">{{ formatCurrency(paidAmountForUser, currentDisplayCurrency) }}</span>
                </div>

                <div class="mt-4 pt-2 border-t border-gray-200">
                    <div class="flex justify-between items-center text-xs text-gray-500 font-medium mb-1">
                        <span>Paid Progress</span>
                        <span>{{ paidPercentage }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="bg-indigo-600 h-1.5 rounded-full" :style="{ width: paidPercentage + '%' }"></div>
                    </div>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-gray-400 text-xs text-center">
                <p v-if="!authUser">
                    Sign in to view your financials.
                </p>
                <p v-else>
                    No financial data available for you on this project.
                </p>
            </div>

            <div v-if="authUser" class="mt-4 text-right">
                <PrimaryButton @click="openTransactionsModal" class="text-xs">
                    View Transactions
                </PrimaryButton>
            </div>
        </div>
    </div>
</template>
