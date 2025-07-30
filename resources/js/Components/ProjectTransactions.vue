<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { success, error } from '@/Utils/notification';
import { usePermissions, useProjectRole } from '@/Directives/permissions';
// Import formatCurrency, convertCurrency, conversionRatesToUSD, fetchCurrencyRates, and displayCurrency
import { formatCurrency, convertCurrency, conversionRatesToUSD, fetchCurrencyRates, displayCurrency } from '@/Utils/currency';

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    userProjectRole: { type: Object, default: () => ({}) },
});

const { canDo, canManage } = usePermissions(() => props.projectId);

// Permission checks
const canManageProjectExpenses = canManage('project_expenses', props.userProjectRole);
const canManageProjectIncome = canManage('project_income', props.userProjectRole);
const canViewProjectTransactions = canDo('view_project_transactions', props.userProjectRole);

const transactions = ref([]); // Now holds raw transactions from backend
const users = ref([]);
const errors = ref({});
const generalError = ref('');
const loading = ref(false); // Overall loading state for the component
const activePaymentTransactionId = ref(null);
const paymentAmount = ref('');
const payInFull = ref(false);
const paymentErrors = ref({});
const selectedTransactionIndex = ref(null);
const paymentDate = ref(''); // New reactive state for payment date

const transactionForm = ref({
    description: '',
    amount: '',
    currency: 'PKR', // Default currency for new transactions
    user_id: null,
    hours_spent: '',
    type: 'expense',
});

// Use the imported reactive displayCurrency directly
const currentDisplayCurrency = displayCurrency;

// Currency options for the dropdown (can be fetched from an API or defined here)
const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

// Updated typeOptions to include 'bonus'
const typeOptions = [
    { value: 'expense', label: 'Expense' },
    { value: 'income', label: 'Income' },
    { value: 'bonus', label: 'Bonus' }, // New Bonus type
];

const userOptions = computed(() => {
    return users.value.map(user => ({
        value: user.id,
        label: user.name || 'Unknown User'
    }));
});

// Helper to get today's date in YYYY-MM-DD format
const getTodayDate = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Fetch users
const fetchUsers = async () => {
    if (!props.projectId || !canViewProjectTransactions.value) return;

    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/clients-users`);
        users.value = response.data.users || [];
    } catch (err) {
        generalError.value = 'Failed to fetch users.';
        console.error('Error fetching users:', err);
    }
};

// Fetch transactions (now fetches raw transactions)
const fetchTransactions = async () => {
    if (!props.projectId || !canViewProjectTransactions.value) return;

    loading.value = true;
    try {
        // Ensure conversion rates are loaded before proceeding with fetching transactions
        // This is crucial for computed properties to work correctly immediately after fetch
        if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) {
            await fetchCurrencyRates(); // Ensure rates are fetched if not already
        }

        // No display_currency parameter needed here, backend returns raw transactions
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/transactions`);
        transactions.value = response.data; // Store raw transactions
    } catch (err) {
        generalError.value = 'Failed to fetch transactions.';
        console.error('Error fetching transactions:', err);
    } finally {
        loading.value = false;
    }
};

// Add or update transaction
const saveTransaction = async () => {
    if (!canManageProjectExpenses.value && !canManageProjectIncome.value) {
        error('You do not have permission to manage transactions.');
        return;
    }

    if (!transactionForm.value.amount || isNaN(transactionForm.value.amount)) {
        errors.value.amount = ['Please enter a valid amount'];
        return;
    }

    // Permission check based on selected type
    if (transactionForm.value.type === 'expense' && !canManageProjectExpenses.value) {
        error('You do not have permission to add expenses.');
        return;
    }
    if (transactionForm.value.type === 'income' && !canManageProjectIncome.value) {
        error('You do not have permission to add income.');
        return;
    }
    if (transactionForm.value.type === 'bonus' && !canManageProjectExpenses.value) { // Assuming bonus falls under expenses or its own permission
        error('You do not have permission to add bonuses.');
        return;
    }


    errors.value = {};
    generalError.value = '';
    loading.value = true;

    try {
        const formData = {
            ...transactionForm.value,
            amount: Number(transactionForm.value.amount),
        };
        const response = await window.axios.post(
            `/api/projects/${props.projectId}/transactions`,
            formData
        );
        // After saving, re-fetch all transactions to ensure correct stats and display
        await fetchTransactions(); // Refetch all to update list and totals
        resetTransactionForm();
        success('Transaction saved successfully!');
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors;
        } else {
            generalError.value = 'Failed to save transaction.';
            console.error('Error saving transaction:', err);
        }
    } finally {
        loading.value = false;
    }
};

// Reset transaction form
const resetTransactionForm = () => {
    transactionForm.value = {
        description: '',
        amount: '',
        currency: 'PKR',
        user_id: null,
        hours_spent: '',
        type: 'expense',
    };
};

// Delete transaction
const deleteTransaction = async (index) => {
    if (!canManageProjectExpenses.value && !canManageProjectIncome.value) {
        error('You do not have permission to delete transactions.');
        return;
    }

    // Replace confirm() with a custom modal for better UX
    if (!window.confirm('Are you sure you want to delete this transaction?')) return; // Using window.confirm for now as per instructions, replace with custom modal later

    try {
        const transactionId = transactions.value[index].id;
        await window.axios.delete(`/api/projects/${props.projectId}/transactions/${transactionId}`);
        await fetchTransactions(); // Refetch all to update list and totals
        success('Transaction deleted successfully!');
    }
    catch (err) {
        generalError.value = 'Failed to delete transaction.';
        console.error('Error deleting transaction:', err);
    }
};

// Open payment section inline
const togglePaymentSection = (index) => {
    if (!canManageProjectExpenses.value && !canManageProjectIncome.value) {
        error('You do not have permission to manage transactions.');
        return;
    }

    const transaction = transactions.value[index];
    if (activePaymentTransactionId.value === transaction.id) {
        // If already open for this transaction, close it
        activePaymentTransactionId.value = null;
        selectedTransactionIndex.value = null;
        paymentDate.value = ''; // Clear date on close
    } else {
        // Open for this transaction
        selectedTransactionIndex.value = index;
        activePaymentTransactionId.value = transaction.id;
        // Pre-fill payment amount with the original transaction amount
        paymentAmount.value = transaction.amount; // Use original amount, not converted
        payInFull.value = false; // Reset payInFull
        paymentErrors.value = {};
        paymentDate.value = getTodayDate(); // Prefill with today's date

        // Auto-select "Pay in Full" if the transaction amount is 0 (e.g., for a fully paid transaction being viewed)
        // or if the paymentAmount (which is now transaction.amount) matches the original amount.
        if (Number(transaction.amount) === 0 || Math.abs(Number(paymentAmount.value) - Number(transaction.amount)) < 0.01) {
            payInFull.value = true;
        }
    }
};

// Watch paymentAmount to auto-select "Pay in Full"
watch(paymentAmount, (newAmount) => {
    if (selectedTransactionIndex.value !== null) {
        const transaction = transactions.value[selectedTransactionIndex.value];
        // Compare newAmount directly with the original transaction amount
        payInFull.value = Math.abs(Number(newAmount) - Number(transaction.amount)) < 0.01;
    }
});


// Cancel inline payment
const cancelPayment = () => {
    activePaymentTransactionId.value = null;
    selectedTransactionIndex.value = null;
    paymentAmount.value = '';
    payInFull.value = false;
    paymentErrors.value = {};
    paymentDate.value = ''; // Clear date on cancel
};

// Handle payment submission
const submitPayment = async () => {
    if (!canManageProjectExpenses.value && !canManageProjectIncome.value) {
        error('You do not have permission to manage transactions.');
        return;
    }

    const index = selectedTransactionIndex.value;
    const transaction = transactions.value[index];
    const payment = Number(paymentAmount.value); // payment is now in original currency

    // Validate payment amount against original transaction amount
    if (!payInFull.value && (!paymentAmount.value || isNaN(payment) || payment <= 0 || payment > Number(transaction.amount))) {
        paymentErrors.value.amount = ['Please enter a valid amount between 0 and ' + formatCurrency(Number(transaction.amount), transaction.currency)];
        return;
    }

    if (!paymentDate.value) {
        paymentErrors.value.date = ['Please select a payment date.'];
        return;
    }

    paymentErrors.value = {};
    loading.value = true;

    try {
        const transactionId = transaction.id;
        // Determine if it's a full payment based on the checkbox or exact amount match
        const isFullPayment = payInFull.value || (Math.abs(payment - Number(transaction.amount)) < 0.01);

        const payload = {
            payment_amount: isFullPayment ? Number(transaction.amount) : payment, // Send original amount if full, else the partial payment amount
            pay_in_full: isFullPayment, // Send the determined full payment status
            transaction_currency: transaction.currency, // Original currency
            transaction_type: transaction.type,
            original_description: transaction.description,
            payment_date: paymentDate.value, // Include payment date in payload
        };

        await window.axios.patch(
            `/api/projects/${props.projectId}/transactions/${transactionId}/process-payment`,
            payload
        );

        await fetchTransactions(); // Refetch all to update list and totals
        success('Payment processed successfully!');
        cancelPayment();
    } catch (err) {
        if (err.response?.status === 422) {
            paymentErrors.value = err.response.data.errors;
        } else {
            generalError.value = 'Failed to process payment.';
            console.error('Error processing payment:', err);
        }
    } finally {
        loading.value = false;
    }
};

// --- Computed properties for summary totals (raw numeric values for calculations) ---
const rawTotalIncome = computed(() => {
    if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) return 0;
    return transactions.value
        .filter(t => t.type === 'income')
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);
});

const rawTotalExpenses = computed(() => {
    if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) return 0;
    return transactions.value
        .filter(t => t.type === 'expense')
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);
});

const rawTotalBonuses = computed(() => {
    if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) return 0;
    return transactions.value
        .filter(t => t.type === 'bonus')
        .reduce((sum, t) => sum + convertCurrency(parseFloat(t.amount || 0), t.currency, currentDisplayCurrency.value), 0);
});

// --- Formatted values for display ---
const totalIncome = computed(() => formatCurrency(rawTotalIncome.value, currentDisplayCurrency.value));
const totalExpenses = computed(() => formatCurrency(rawTotalExpenses.value, currentDisplayCurrency.value));
const totalBonuses = computed(() => formatCurrency(rawTotalBonuses.value, currentDisplayCurrency.value));

const balance = computed(() => {
    if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) return formatCurrency(0, currentDisplayCurrency.value);
    const expenseBonusTotal = rawTotalExpenses.value + rawTotalBonuses.value;
    return formatCurrency(rawTotalIncome.value - expenseBonusTotal, currentDisplayCurrency.value);
});

// --- New Computed properties for percentages in Summary ---
const percentageExpenseOfIncome = computed(() => {
    if (rawTotalIncome.value === 0) return '0.00%';
    const percentage = (rawTotalExpenses.value / rawTotalIncome.value) * 100;
    return `${percentage.toFixed(2)}%`;
});

const percentageExpenseBonusOfIncome = computed(() => {
    if (rawTotalIncome.value === 0) return '0.00%';
    const total = rawTotalExpenses.value + rawTotalBonuses.value;
    const percentage = (total / rawTotalIncome.value) * 100;
    return `${percentage.toFixed(2)}%`;
});

// --- Australian Income Tax Estimation (Placeholder) ---
// This is a simplified estimation. Real tax calculation is complex and depends on many factors.
const AUSTRALIAN_TAX_RATE = 0.25; // Example: 32.5% for a common income bracket (adjust as needed)

const estimatedIncomeTax = computed(() => {
    if (rawTotalIncome.value <= 0) return formatCurrency(0, currentDisplayCurrency.value);
    const taxAmount = rawTotalIncome.value * AUSTRALIAN_TAX_RATE;
    return formatCurrency(taxAmount, currentDisplayCurrency.value);
});

// New computed properties for last payment/income dates
const lastPaymentDate = computed(() => {
    const paidTransactions = transactions.value.filter(t => (t.type === 'expense' || t.type === 'bonus') && t.is_paid && t.payment_date);
    if (paidTransactions.length === 0) return 'N/A';
    const latestPayment = paidTransactions.reduce((latest, current) => {
        return new Date(current.payment_date) > new Date(latest.payment_date) ? current : latest;
    });
    return new Date(latestPayment.payment_date).toLocaleDateString();
});

const lastIncomeDate = computed(() => {
    const incomeTransactions = transactions.value.filter(t => t.type === 'income' && t.payment_date);
    if (incomeTransactions.length === 0) return 'N/A';
    const latestIncome = incomeTransactions.reduce((latest, current) => {
        return new Date(current.payment_date) > new Date(latest.payment_date) ? current : latest;
    });
    return new Date(latestIncome.payment_date).toLocaleDateString();
});

// Fetch data when component mounts or projectId changes
onMounted(async () => {
    // Ensure currency rates are fetched globally on mount
    // Set initial display currency from local storage if available
    const storedCurrency = localStorage.getItem('displayCurrency');
    if (storedCurrency) currentDisplayCurrency.value = storedCurrency;
    await fetchCurrencyRates();
    // Then fetch transactions and users
    fetchTransactions();
    fetchUsers();
});

// Watch for changes in projectId, the global displayCurrency, and conversionRatesToUSD
watch([
    () => props.projectId,
    currentDisplayCurrency, // Watch the reactive ref directly
    conversionRatesToUSD // Watch the reactive ref directly
], () => {
    // Only re-fetch if currency rates are available and projectId is valid
    if (props.projectId && conversionRatesToUSD.value && Object.keys(conversionRatesToUSD.value).length > 0) {
        fetchTransactions();
        fetchUsers();
    }
}, { deep: true }); // Deep watch for changes within objects if needed

watch(currentDisplayCurrency, (newCurrency) => {
    if (newCurrency) {
        localStorage.setItem('displayCurrency', newCurrency);
    }
}, { deep: true }); // Deep watch for changes within objects if needed

</script>

<template>
    <div class="p-6 bg-white rounded-lg">

        <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>

        <div v-if="canManageProjectExpenses || canManageProjectIncome" class="mb-6 p-4 bg-gray-50 rounded-md">
            <div class="mb-6">

                <!-- Currency Switcher for this component -->
                <div class="flex justify-end items-center mb-4">
                    <span class="text-sm font-medium text-gray-700 mr-2">Currency:</span>
                    <SelectDropdown
                        id="transactions-display-currency-switcher"
                        v-model="currentDisplayCurrency"
                        :options="currencyOptions"
                        value-key="value"
                        label-key="label"
                        class="w-24"
                    />
                </div>

                <h4 class="text-md font-medium text-gray-700 mb-2">Summary</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-3 bg-blue-100 rounded-md">
                        <p class="text-sm text-gray-600">Total Income</p>
                        <p class="text-lg font-bold text-blue-800">{{ totalIncome }}</p>
                    </div>
                    <div class="p-3 bg-red-100 rounded-md">
                        <p class="text-sm text-gray-600">Total Expenses</p>
                        <p class="text-lg font-bold text-red-800">{{ totalExpenses }}</p>
                        <p class="text-xs text-gray-500">({{ percentageExpenseOfIncome }} of Income)</p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-md">
                        <p class="text-sm text-gray-600">Total Bonuses</p>
                        <p class="text-lg font-bold text-purple-800">{{ totalBonuses }}</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-md">
                        <p class="text-sm text-gray-600">Balance</p>
                        <p class="text-lg font-bold text-green-800">{{ balance }}</p>
                    </div>
                    <!-- New card for combined Expense + Bonus percentage -->
                    <div class="p-3 bg-yellow-100 rounded-md col-span-full md:col-span-1">
                        <p class="text-sm text-gray-600">Expense + Bonus</p>
                        <p class="text-lg font-bold text-yellow-800">{{ formatCurrency(rawTotalExpenses + rawTotalBonuses, currentDisplayCurrency) }}</p>
                        <p class="text-xs text-gray-500">({{ percentageExpenseBonusOfIncome }} of Income)</p>
                    </div>
                    <!-- New card for Estimated Income Tax -->
                    <div class="p-3 bg-orange-100 rounded-md col-span-full md:col-span-1">
                        <p class="text-sm font-medium text-orange-800">Estimated Income Tax (AUD)</p>
                        <p class="text-lg font-bold text-orange-900 mt-1">
                            {{ estimatedIncomeTax }}
                        </p>
                        <p class="text-xs text-gray-500">
                            (Based on {{ (AUSTRALIAN_TAX_RATE * 100).toFixed(1) }}% of Total Income. This is an estimation.)
                        </p>
                    </div>

                    <!-- New card for Last Income Date -->
                    <div class="p-3 bg-gray-100 rounded-md col-span-full md:col-span-1">
                        <p class="text-sm font-medium text-gray-800">Last Payment Received Date</p>
                        <p class="text-lg font-bold mt-1 text-green-400">
                            {{ lastIncomeDate }}
                        </p>
                    </div>

                    <!-- New card for Last Payment Date -->
                    <div class="p-3 bg-gray-100 rounded-md col-span-full md:col-span-1">
                        <p class="text-sm font-medium text-gray-800">Last Payment Sent Date</p>
                        <p class="text-lg font-bold text-red-400 mt-1">
                            {{ lastPaymentDate }}
                        </p>
                    </div>

                </div>
            </div>



            <h4 class="text-md font-medium text-gray-700 mb-4">Add Transaction</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <InputLabel for="description" value="Description" />
                    <TextInput
                        id="description"
                        v-model="transactionForm.description"
                        class="mt-1 block w-full"
                        :disabled="loading"
                    />
                    <InputError :message="errors.description?.[0]" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="amount" value="Amount" />
                    <TextInput
                        id="amount"
                        type="number"
                        v-model="transactionForm.amount"
                        class="mt-1 block w-full"
                        :disabled="loading"
                    />
                    <InputError :message="errors.amount?.[0]" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="currency" value="Currency" />
                    <SelectDropdown
                        id="currency"
                        v-model="transactionForm.currency"
                        :options="currencyOptions"
                        :disabled="loading"
                    />
                    <InputError :message="errors.currency?.[0]" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="type" value="Type" />
                    <SelectDropdown
                        id="type"
                        v-model="transactionForm.type"
                        :options="typeOptions"
                        :disabled="loading || (!canManageProjectExpenses && transactionForm.type === 'expense' && transactionForm.type !== 'bonus') || (!canManageProjectIncome && transactionForm.type === 'income')"
                    />
                    <InputError :message="errors.type?.[0]" class="mt-2" />
                </div>
                <div v-if="transactionForm.type === 'expense' || transactionForm.type === 'bonus'">
                    <InputLabel for="user_id" value="User" />
                    <SelectDropdown
                        id="user_id"
                        v-model="transactionForm.user_id"
                        :options="userOptions"
                        :disabled="loading || !users.length"
                        placeholder="Select a user"
                    />
                    <InputError :message="errors.user_id?.[0]" class="mt-2" />
                </div>
                <div v-if="transactionForm.type === 'expense' || transactionForm.type === 'bonus'">
                    <InputLabel for="hours_spent" value="Hours Spent (Optional)" />
                    <TextInput
                        id="hours_spent"
                        type="number"
                        v-model="transactionForm.hours_spent"
                        class="mt-1 block w-full"
                        :disabled="loading"
                    />
                    <InputError :message="errors.hours_spent?.[0]" class="mt-2" />
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <PrimaryButton @click="saveTransaction" :disabled="loading">
                    {{ loading ? 'Saving...' : 'Save Transaction' }}
                </PrimaryButton>
            </div>
        </div>

        <div v-if="canViewProjectTransactions">
            <h4 class="text-md font-medium text-gray-700 mb-4">Transaction History</h4>
            <div v-if="loading" class="space-y-3">
                <div class="h-4 bg-gray-200 rounded animate-pulse w-full"></div>
                <div class="h-4 bg-gray-200 rounded animate-pulse w-5/6"></div>
                <div class="h-4 bg-gray-200 rounded animate-pulse w-3/4"></div>
            </div>
            <div v-else-if="transactions.length === 0" class="p-4 bg-gray-50 rounded-md text-gray-600 text-center">
                No transactions found for this project.
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="(transaction, index) in transactions"
                    :key="transaction.id"
                    :class="{
                        'bg-green-50 border-green-200': transaction.type === 'income',
                        'bg-red-50 border-red-200': transaction.type === 'expense',
                        'bg-purple-50 border-purple-200': transaction.type === 'bonus',
                        'border-l-4': true, // Add a left border for color emphasis
                    }"
                    class="p-4 rounded-md shadow-sm transition-all duration-200 ease-in-out hover:shadow-md"
                >
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-grow">
                            <p class="font-medium text-gray-900 text-lg">{{ transaction.description }}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ transaction.type === 'income' ? 'Income' : (transaction.type === 'expense' ? 'Expense' : 'Bonus') }}
                                on {{ new Date(transaction.created_at).toLocaleDateString() }}
                            </p>
                        </div>
                        <div class="flex-shrink-0 text-right">
                            <p class="text-lg font-bold"
                               :class="{
                                   'text-green-700': transaction.type === 'income',
                                   'text-red-700': transaction.type === 'expense',
                                   'text-purple-700': transaction.type === 'bonus',
                               }"
                            >
                                {{ formatCurrency(transaction.amount, transaction.currency) }}
                            </p>
                            <p v-if="transaction.currency.toUpperCase() !== currentDisplayCurrency.toUpperCase()" class="text-sm text-gray-600">
                                ({{ formatCurrency(convertCurrency(transaction.amount, transaction.currency, currentDisplayCurrency), currentDisplayCurrency) }})
                            </p>
                            <span class="block text-xs mt-1" :class="transaction.is_paid ? 'text-green-600' : 'text-red-600'">
                                {{ transaction.is_paid ? 'Paid' : 'Unpaid' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-between items-end text-xs text-gray-600 mt-2 border-t border-gray-200 pt-2">
                        <div>
                            <span v-if="transaction.user_id" class="block">User: {{ users.find(u => u.id === transaction.user_id)?.name || 'Unknown' }}</span>
                            <span v-if="transaction.hours_spent" class="block">Hours: {{ transaction.hours_spent }}</span>
                            <span v-if="(transaction.type === 'expense' || transaction.type === 'bonus') && rawTotalIncome > 0" class="block mt-1 text-gray-500">
                                {{ ((convertCurrency(transaction.amount, transaction.currency, currentDisplayCurrency) / rawTotalIncome) * 100).toFixed(2) }}% of Income
                            </span>
                        </div>
                        <div class="flex space-x-2 mt-2">
                            <SecondaryButton
                                v-if="!transaction.is_paid && (canManageProjectExpenses || canManageProjectIncome)"
                                @click="togglePaymentSection(index)"
                                :class="{ 'text-green-600': transaction.type !== 'income', 'text-blue-600': transaction.type === 'income' }"
                            >
                                {{ transaction.type === 'income' ? 'Receive' : 'Pay' }}
                            </SecondaryButton>
                            <SecondaryButton
                                v-if="canManageProjectExpenses || canManageProjectIncome"
                                @click="deleteTransaction(index)"
                                class="text-red-600"
                            >
                                Delete
                            </SecondaryButton>
                        </div>
                    </div>

                    <div v-if="activePaymentTransactionId === transaction.id" class="mt-4 p-4 bg-blue-50 rounded-md border border-blue-200">
                        <h4 class="text-md font-medium text-gray-900 mb-4">{{ transaction.type === 'income' ? 'Record Receipt' : 'Make Payment' }}</h4>

                        <div class="mb-4">
                            <InputLabel for="payment_date" value="Date" />
                            <TextInput
                                id="payment_date"
                                type="date"
                                v-model="paymentDate"
                                class="mt-1 block w-full"
                                :disabled="loading"
                            />
                            <InputError :message="paymentErrors.date?.[0]" class="mt-2" />
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center">
                                <input
                                    type="checkbox"
                                    v-model="payInFull"
                                    class="mr-2 rounded text-blue-600 focus:ring-blue-500"
                                    :disabled="loading"
                                />
                                <span class="text-sm text-gray-700">{{ transaction.type === 'income' ? 'Receive in Full' : 'Pay in Full' }}</span>
                            </label>
                        </div>
                        <div v-if="!payInFull" class="mb-4">
                            <InputLabel for="payment_amount" value="Amount" />
                            <TextInput
                                id="payment_amount"
                                type="number"
                                v-model="paymentAmount"
                                class="mt-1 block w-full"
                                :disabled="loading"
                                :placeholder="'Enter partial ' + (transaction.type === 'income' ? 'receipt' : 'payment') + ' amount (Max: ' + formatCurrency(transaction.amount, transaction.currency) + ')'"
                            />
                            <InputError :message="paymentErrors.amount?.[0]" class="mt-2" />
                        </div>
                        <div class="flex justify-end space-x-2">
                            <SecondaryButton @click="cancelPayment" :disabled="loading">
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton @click="submitPayment" :disabled="loading">
                                {{ loading ? 'Processing...' : (transaction.type === 'income' ? 'Record Receipt' : 'Submit Payment') }}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* No specific modal-related CSS needed anymore, as it's inline */
/* Existing styles for the component's overall layout remain relevant */
</style>
