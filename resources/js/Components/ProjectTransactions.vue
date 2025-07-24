<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';
import { usePermissions } from '@/Directives/permissions';
import { success, error } from '@/Utils/notification';

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    userProjectRole: { type: Object, default: null },
});

// Set up permission checking functions with project ID
const { canDo, canView, canManage } = usePermissions(computed(() => props.projectId));

// Permission-based checks using the permission utilities
const canManageProjectExpenses = canManage('project_expenses', computed(() => props.userProjectRole));
const canManageProjectIncome = canManage('project_income', computed(() => props.userProjectRole));
const canViewProjectTransactions = canView('project_transactions', computed(() => props.userProjectRole));

// Internal state
const transactions = ref([]);
const users = ref([]);
const clients = ref([]);
const errors = ref({});
const loading = ref(false);

// Function to fetch transactions data
const fetchTransactionsData = async () => {
    if (!props.projectId) return;

    loading.value = true;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/transactions`);
        const transactionsData = response.data;

        // Update transactions
        transactions.value = transactionsData.map(transaction => ({
            description: transaction.description,
            amount: transaction.amount,
            currency: transaction.currency || 'USD',
            user_id: transaction.user_id,
            hours_spent: transaction.hours_spent,
            type: transaction.type || 'expense',
        }));

        return transactionsData;
    } catch (error) {
        console.error('Error fetching transactions data:', error);
        errors.value.general = 'Failed to fetch transactions data.';
        return null;
    } finally {
        loading.value = false;
    }
};

// Function to fetch users and clients data
const fetchUsersAndClientsData = async () => {
    if (!props.projectId) return;

    loading.value = true;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/clients-users`);
        const data = response.data;

        // Update users and clients arrays
        if (data.users && data.users.length > 0) {
            users.value = data.users;
        }

        if (data.clients && data.clients.length > 0) {
            clients.value = data.clients;
        }

        return data;
    } catch (error) {
        console.error('Error fetching users and clients data:', error);
        errors.value.general = 'Failed to fetch users and clients data.';
        return null;
    } finally {
        loading.value = false;
    }
};

// Function to update transactions
const updateTransactions = async () => {
    if (!props.projectId) return;

    errors.value = {};
    try {
        // Create a clean copy of the form data with only transactions
        const formData = {
            transactions: transactions.value,
        };

        // Filter transactions based on permissions
        if (!canManageProjectExpenses.value) {
            formData.transactions = formData.transactions.filter(t => t.type !== 'expense');
        }
        if (!canManageProjectIncome.value) {
            formData.transactions = formData.transactions.filter(t => t.type !== 'income');
        }

        // Update the project
        const response = await window.axios.put(`/api/projects/${props.projectId}/sections/transactions`, formData);

        // Show success message
        success('Transactions updated successfully!');
    } catch (error) {
        handleError(error, 'Failed to update transactions.');
    }
};

// Helper function to handle errors
const handleError = (error, defaultMessage) => {
    if (error.response && error.response.status === 422) {
        errors.value = error.response.data.errors;
    } else if (error.response && error.response.data.message) {
        errors.value.general = error.response.data.message;
    } else {
        errors.value.general = defaultMessage;
        console.error('Error:', error);
    }
};

// Add a new transaction
const addTransaction = () => {
    transactions.value.push({
        description: '',
        amount: '',
        currency: 'USD',
        user_id: null,
        hours_spent: '',
        type: 'expense'
    });
};

// Remove a transaction
const removeTransaction = (index) => {
    transactions.value.splice(index, 1);
};

// Watch for changes to projectId and fetch data
watch(() => props.projectId, (newProjectId, oldProjectId) => {
    if (newProjectId && newProjectId !== oldProjectId) {
        fetchTransactionsData();
        fetchUsersAndClientsData();
    }
}, { immediate: true });

// Fetch data when component is mounted
onMounted(() => {
    if (props.projectId) {
        fetchTransactionsData();
        fetchUsersAndClientsData();
    }
});

// Expose methods to parent component
defineExpose({
    fetchTransactionsData,
    updateTransactions
});
</script>

<template>
    <div>
        <div class="mb-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Transactions</h3>
                <div class="text-right">
                    <!-- Group transactions by currency -->
                    <template v-for="currency in [...new Set(transactions.map(t => t.currency || 'USD'))]" :key="currency">
                        <div class="text-lg font-medium">
                            Total Income ({{ currency }}): {{ currency }} {{
                                transactions
                                    .filter(t => t.type === 'income' && (t.currency || 'USD') === currency)
                                    .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0)
                                    .toFixed(2)
                            }}
                        </div>
                        <div class="text-lg font-medium">
                            Total Expenses ({{ currency }}): {{ currency }} {{
                                transactions
                                    .filter(t => t.type === 'expense' && (t.currency || 'USD') === currency)
                                    .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0)
                                    .toFixed(2)
                            }}
                        </div>
                        <div class="text-xl font-bold mt-1 mb-2">
                            Net ({{ currency }}): {{ currency }} {{
                                (
                                    transactions
                                        .filter(t => t.type === 'income' && (t.currency || 'USD') === currency)
                                        .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0) -
                                    transactions
                                        .filter(t => t.type === 'expense' && (t.currency || 'USD') === currency)
                                        .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0)
                                ).toFixed(2)
                            }}
                        </div>
                    </template>
                </div>
            </div>

            <div v-if="errors.general" class="text-red-600 text-sm mb-4">{{ errors.general }}</div>

            <div class="mt-4" v-if="canViewProjectTransactions">
                <div v-for="(transaction, index) in transactions" :key="index" class="flex items-center mb-2 p-2 border rounded">
                    <select v-model="transaction.type" class="mr-2 border-gray-300 rounded-md">
                        <option v-if="canManageProjectIncome" value="income">Income</option>
                        <option v-if="canManageProjectExpenses" value="expense">Expense</option>
                    </select>
                    <TextInput v-model="transaction.description" placeholder="Description" class="mr-2 flex-grow" />
                    <div class="flex items-center mr-2">
                        <TextInput v-model.number="transaction.amount" type="number" step="0.01" placeholder="Amount" class="w-24" />
                        <select v-model="transaction.currency" class="ml-1 border-gray-300 rounded-md">
                            <option value="INR">INR</option>
                            <option value="PKR">PKR</option>
                            <option value="AUD">AUD</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <select v-if="transaction.type === 'expense'" v-model="transaction.user_id" class="mr-2 border-gray-300 rounded-md">
                        <option value="" disabled>Select User (Optional)</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                    <TextInput v-if="transaction.type === 'expense'" v-model.number="transaction.hours_spent" type="number" step="0.01" placeholder="Hours" class="mr-2 w-20" />
                    <button type="button" class="text-red-600" @click="removeTransaction(index)">
                        Remove
                    </button>
                </div>
                <div class="flex mt-2">
                    <SecondaryButton @click="addTransaction">
                        Add Transaction
                    </SecondaryButton>
                </div>
            </div>
            <InputError :message="errors.transactions ? errors.transactions[0] : ''" class="mt-2" />
        </div>

        <div v-if="canManageProjectExpenses || canManageProjectIncome" class="mt-6 flex justify-end">
            <PrimaryButton
                @click="updateTransactions"
                :disabled="!props.projectId || (!canManageProjectExpenses && !canManageProjectIncome)"
            >
                Update Transactions
            </PrimaryButton>
        </div>
    </div>
</template>
