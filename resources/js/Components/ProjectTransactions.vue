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

// Mock conversion rates to USD (simulating database storage)
const conversionRatesToUSD = {
    PKR: 0.0034, // 1 PKR = 0.0034 USD
    AUD: 0.65,   // 1 AUD = 0.65 USD
    INR: 0.012,  // 1 INR = 0.012 USD
    EUR: 1.08,   // 1 EUR = 1.08 USD
    GBP: 1.28,   // 1 GBP = 1.28 USD
    USD: 1.0     // 1 USD = 1.0 USD
};

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    userProjectRole: { type: Object, default: () => ({}) },
});

const { canDo, canManage } = usePermissions(() => props.projectId);

// Permission checks
const canManageProjectExpenses = canManage('project_expenses', props.userProjectRole);
const canManageProjectIncome = canManage('project_income', props.userProjectRole);
const canViewProjectTransactions = canDo('view_project_transactions', props.userProjectRole);

const transactions = ref([]);
const users = ref([]);
const errors = ref({});
const generalError = ref('');
const loading = ref(false);
// Removed showPaymentModal, replaced with activePaymentTransactionId for inline display
const activePaymentTransactionId = ref(null);
const paymentAmount = ref('');
const payInFull = ref(false);
const paymentErrors = ref({});
// selectedTransactionIndex is still useful to get the actual transaction object from the array
const selectedTransactionIndex = ref(null);

const transactionForm = ref({
    description: '',
    amount: '',
    currency: 'PKR',
    user_id: null,
    hours_spent: '',
    type: 'expense',
});

const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

const typeOptions = [
    { value: 'expense', label: 'Expense' },
    { value: 'income', label: 'Income' },
];

const userOptions = computed(() => {
    return users.value.map(user => ({
        value: user.id,
        label: user.name || 'Unknown User'
    }));
});

// Format currency amount with proper decimal places
const formatCurrency = (amount, currency) => {
    if (isNaN(amount) || amount == null) return '0.00';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
};

// Convert amount between currencies using USD as base
const convertCurrency = (amount, fromCurrency, toCurrency) => {
    if (!amount || isNaN(amount) || !fromCurrency || !toCurrency) return 0;
    if (fromCurrency === toCurrency) return Number(amount);

    const fromRate = conversionRatesToUSD[fromCurrency.toUpperCase()];
    const toRate = conversionRatesToUSD[toCurrency.toUpperCase()];

    if (!fromRate || !toRate) {
        console.warn(`Invalid currency: ${fromCurrency} to ${toCurrency}`);
        return 0;
    }

    const amountInUSD = Number(amount) * fromRate;
    const convertedAmount = amountInUSD / toRate;

    return Number(convertedAmount.toFixed(2));
};

// Fetch users
const fetchUsers = async () => {
    if (!props.projectId || !canViewProjectTransactions) return;

    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/clients-users`);
        users.value = response.data.users || [];
    } catch (err) {
        generalError.value = 'Failed to fetch users.';
        console.error('Error fetching users:', err);
    }
};

// Fetch transactions
const fetchTransactions = async () => {
    if (!props.projectId || !canViewProjectTransactions) return;

    loading.value = true;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/transactions`);

        transactions.value = response.data.map(transaction => ({
            ...transaction,
            amount: Number(transaction.amount) || 0,
            amount_usd: convertCurrency(Number(transaction.amount) || 0, transaction.currency, 'USD')
        }));
    } catch (err) {
        generalError.value = 'Failed to fetch transactions.';
        console.error('Error fetching transactions:', err);
    } finally {
        loading.value = false;
    }
};

// Add or update transaction
const saveTransaction = async () => {
    if (!canManageProjectExpenses && !canManageProjectIncome) {
        error('You do not have permission to manage transactions.');
        return;
    }

    if (!transactionForm.value.amount || isNaN(transactionForm.value.amount)) {
        errors.value.amount = ['Please enter a valid amount'];
        return;
    }

    errors.value = {};
    generalError.value = '';
    loading.value = true;

    try {
        const formData = {
            ...transactionForm.value,
            amount: Number(transactionForm.value.amount),
            amount_usd: convertCurrency(Number(transactionForm.value.amount), transactionForm.value.currency, 'USD')
        };
        const response = await window.axios.post(
            `/api/projects/${props.projectId}/transactions`,
            formData
        );
        transactions.value.unshift({
            ...response.data,
            amount: Number(response.data.amount) || 0,
            amount_usd: convertCurrency(Number(response.data.amount) || 0, response.data.currency, 'USD')
        });
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
    if (!canManageProjectExpenses && !canManageProjectIncome) {
        error('You do not have permission to delete transactions.');
        return;
    }

    // IMPORTANT: For production, replace `confirm` with a custom modal UI.
    if (!confirm('Are you sure you want to delete this transaction?')) return;

    try {
        const transactionId = transactions.value[index].id;
        await window.axios.delete(`/api/projects/${props.projectId}/transactions/${transactionId}`);
        transactions.value.splice(index, 1);
        success('Transaction deleted successfully!');
    } catch (err) {
        generalError.value = 'Failed to delete transaction.';
        console.error('Error deleting transaction:', err);
    }
};

// Open payment section inline (replaces openPaymentModal)
const togglePaymentSection = (index) => {
    if (!canManageProjectExpenses && !canManageProjectIncome) {
        error('You do not have permission to manage transactions.');
        return;
    }

    const transaction = transactions.value[index];
    if (activePaymentTransactionId.value === transaction.id) {
        // If already open for this transaction, close it
        activePaymentTransactionId.value = null;
        selectedTransactionIndex.value = null;
    } else {
        // Open for this transaction
        selectedTransactionIndex.value = index;
        activePaymentTransactionId.value = transaction.id;
        paymentAmount.value = transaction.amount; // Pre-fill with remaining amount
        payInFull.value = false;
        paymentErrors.value = {};
    }
};

// Cancel inline payment
const cancelPayment = () => {
    activePaymentTransactionId.value = null;
    selectedTransactionIndex.value = null;
    paymentAmount.value = '';
    payInFull.value = false;
    paymentErrors.value = {};
};

// Handle payment submission
const submitPayment = async () => {
    if (!canManageProjectExpenses && !canManageProjectIncome) {
        error('You do not have permission to manage transactions.');
        return;
    }

    const index = selectedTransactionIndex.value;
    const transaction = transactions.value[index];
    const payment = Number(paymentAmount.value);
    const remainingAmount = Number(transaction.amount);

    if (!payInFull.value && (!paymentAmount.value || isNaN(payment) || payment <= 0 || payment > remainingAmount)) {
        paymentErrors.value.amount = ['Please enter a valid amount between 0 and ' + formatCurrency(remainingAmount, transaction.currency)];
        return;
    }

    paymentErrors.value = {};
    loading.value = true;

    try {
        const transactionId = transaction.id;
        const isFullPayment = payInFull.value;

        // Unified payload for the backend
        const payload = {
            payment_amount: isFullPayment ? remainingAmount : payment, // Send the full amount if paying in full, else the partial amount
            pay_in_full: isFullPayment,
            // Include transaction currency and type if the backend needs them to validate or create new transactions
            transaction_currency: transaction.currency,
            transaction_type: transaction.type,
            // You might need to send original description if the backend needs to create a new partial payment transaction with it.
            original_description: transaction.description
        };

        // Send a single PATCH request to a dedicated payment processing endpoint on the backend.
        // This endpoint will handle all the logic for marking paid, splitting transactions, etc.
        await window.axios.patch(
            `/api/projects/${props.projectId}/transactions/${transactionId}/process-payment`, // Assuming this new backend endpoint
            payload
        );

        // After successful backend processing, re-fetch all transactions
        // to ensure the UI is in sync with the latest backend state.
        await fetchTransactions();
        success('Payment processed successfully!');

        cancelPayment(); // Use the new cancel function to hide the inline form
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

const displayCurrency = ref('PKR');

const totalIncome = computed(() => {
    const sum = transactions.value
        .filter(t => t.type === 'income')
        .reduce((sum, t) => sum + convertCurrency(Number(t.amount) || 0, t.currency, displayCurrency.value), 0);
    return formatCurrency(sum, displayCurrency.value);
});

const totalExpenses = computed(() => {
    const sum = transactions.value
        .filter(t => t.type === 'expense')
        .reduce((sum, t) => sum + convertCurrency(Number(t.amount) || 0, t.currency, displayCurrency.value), 0);
    return formatCurrency(sum, displayCurrency.value);
});

const balance = computed(() => {
    const income = transactions.value
        .filter(t => t.type === 'income')
        .reduce((sum, t) => sum + convertCurrency(Number(t.amount) || 0, t.currency, displayCurrency.value), 0);
    const expenses = transactions.value
        .filter(t => t.type === 'expense')
        .reduce((sum, t) => sum + convertCurrency(Number(t.amount) || 0, t.currency, displayCurrency.value), 0);
    return formatCurrency(income - expenses, displayCurrency.value);
});

// Fetch data when component mounts or projectId changes
onMounted(() => {
    fetchTransactions();
    fetchUsers();
});
watch(() => props.projectId, () => {
    fetchTransactions();
    fetchUsers();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-xl">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Project Transactions</h3>

        <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>

        <!-- Transaction Form -->
        <div v-if="canManageProjectExpenses || canManageProjectIncome" class="mb-6 p-4 bg-gray-50 rounded-md">
            <div class="mb-6">
                <h4 class="text-md font-medium text-gray-700 mb-2">Summary</h4>
                <div class="mb-4">
                    <InputLabel for="display_currency" value="Display Currency" class="sr-only" />
                    <SelectDropdown
                        id="display_currency"
                        v-model="displayCurrency"
                        :options="currencyOptions"
                        class="w-32"
                    />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-3 bg-blue-100 rounded-md">
                        <p class="text-sm text-gray-600">Total Income</p>
                        <p class="text-lg font-bold text-blue-800">{{ totalIncome }}</p>
                    </div>
                    <div class="p-3 bg-red-100 rounded-md">
                        <p class="text-sm text-gray-600">Total Expenses</p>
                        <p class="text-lg font-bold text-red-800">{{ totalExpenses }}</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-md">
                        <p class="text-sm text-gray-600">Balance</p>
                        <p class="text-lg font-bold text-green-800">{{ balance }}</p>
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
                        :disabled="loading || (!canManageProjectExpenses && transactionForm.type === 'expense') || (!canManageProjectIncome && transactionForm.type === 'income')"
                    />
                    <InputError :message="errors.type?.[0]" class="mt-2" />
                </div>
                <div v-if="transactionForm.type === 'expense'">
                    <InputLabel for="user_id" value="User" />
                    <SelectDropdown
                        id="user_id"
                        v-model="transactionForm.user_id"
                        :options="userOptions"
                        :disabled="loading || !users.length"
                        placeholder="Select a user"
                    />
                    <InputError v-if="transactionForm.type === 'expense'" :message="errors.user_id?.[0]" class="mt-2" />
                </div>
                <div v-if="transactionForm.type === 'expense'">
                    <InputLabel for="hours_spent" value="Hours Spent (Optional)" />
                    <TextInput
                        id="hours_spent"
                        type="number"
                        v-model="transactionForm.hours_spent"
                        class="mt-1 block w-full"
                        :disabled="loading"
                    />
                    <InputError v-if="transactionForm.type === 'expense'" :message="errors.hours_spent?.[0]" class="mt-2" />
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <PrimaryButton @click="saveTransaction" :disabled="loading">
                    {{ loading ? 'Saving...' : 'Save Transaction' }}
                </PrimaryButton>
            </div>
        </div>

        <!-- Transactions List -->
        <div v-if="canViewProjectTransactions">
            <h4 class="text-md font-medium text-gray-700 mb-4">Transaction History</h4>
            <div v-if="loading" class="text-gray-600">Loading transactions...</div>
            <div v-else-if="transactions.length === 0" class="p-4 bg-gray-50 rounded-md text-gray-600 text-center">
                No transactions found for this project.
            </div>
            <div v-else class="space-y-4">
                <div v-for="(transaction, index) in transactions" :key="transaction.id" class="p-4 bg-gray-50 rounded-md shadow-sm">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-medium">{{ transaction.description }}</p>
                            <p class="text-sm text-gray-600">
                                {{ transaction.type === 'income' ? 'Income' : 'Expense' }}:
                                {{ formatCurrency(transaction.amount, transaction.currency) }}
                                <span v-if="displayCurrency !== transaction.currency">
                                    ({{ formatCurrency(convertCurrency(transaction.amount, transaction.currency, displayCurrency), displayCurrency) }})
                                </span>
                                <span v-if="transaction.user_id"> (User: {{ users.find(u => u.id === transaction.user_id)?.name || 'Unknown' }})</span>
                                <span v-if="transaction.hours_spent"> ({{ transaction.hours_spent }} hours)</span>
                                <span class="ml-2" :class="transaction.is_paid ? 'text-green-600' : 'text-red-600'">
                                    {{ transaction.is_paid ? '(Paid)' : '(Unpaid)' }}
                                </span>
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <SecondaryButton
                                v-if="!transaction.is_paid && (canManageProjectExpenses || canManageProjectIncome)"
                                @click="togglePaymentSection(index)"
                                class="text-green-600"
                            >
                                Pay
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

                    <!-- Inline Payment Section -->
                    <div v-if="activePaymentTransactionId === transaction.id" class="mt-4 p-4 bg-blue-50 rounded-md border border-blue-200">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Make Payment</h4>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input
                                    type="checkbox"
                                    v-model="payInFull"
                                    class="mr-2 rounded text-blue-600 focus:ring-blue-500"
                                    :disabled="loading"
                                />
                                <span class="text-sm text-gray-700">Pay in Full</span>
                            </label>
                        </div>
                        <div v-if="!payInFull" class="mb-4">
                            <InputLabel for="payment_amount" value="Payment Amount" />
                            <TextInput
                                id="payment_amount"
                                type="number"
                                v-model="paymentAmount"
                                class="mt-1 block w-full"
                                :disabled="loading"
                                :placeholder="'Enter partial payment amount (Max: ' + formatCurrency(transaction.amount, transaction.currency) + ')'"
                            />
                            <InputError :message="paymentErrors.amount?.[0]" class="mt-2" />
                        </div>
                        <div class="flex justify-end space-x-2">
                            <SecondaryButton @click="cancelPayment" :disabled="loading">
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton @click="submitPayment" :disabled="loading">
                                {{ loading ? 'Processing...' : 'Submit Payment' }}
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
