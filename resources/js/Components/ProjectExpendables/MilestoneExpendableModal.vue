<script setup>
import { ref, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { convertCurrency } from '@/Utils/currency';
import { error } from '@/Utils/notification';

const props = defineProps({
    show: Boolean,
    title: String,
    milestone: Object,
    users: Object,
    currencyOptions: Array,
    isBudgetForm: {
        type: Boolean,
        default: false,
    },
    isUserSelectionRequired: {
        type: Boolean,
        default: false,
    },
    // legacy props (kept for backward compatibility)
    projectTotalBudget: Number,
    projectBudgetCurrency: String,
    milestoneStats: Object,
    // new validation-related props
    availableForNewMilestones: {
        type: Number,
        default: 0,
    },
    budgetBaseCurrency: {
        type: String,
        default: 'AUD',
    },
});

const emit = defineEmits(['close', 'submitted']);

const form = ref({});
const apiEndpoint = ref('');
const httpMethod = ref('post');
const submitButtonText = ref('');
const successMessage = ref('');
const errors = ref({});

const userOptions = computed(() => {
    const list = Array.isArray(props.users?.users) ? props.users.users : props.users;
    return (list || []).map(u => ({ value: u.id, label: u.name }));
});

watch(() => props.show, (newValue) => {
    if (newValue && props.milestone) {
        errors.value = {};
        if (props.isBudgetForm) {
            form.value = props.milestone.budget ? { ...props.milestone.budget, amount: Number(props.milestone.budget.amount) } : { name: 'Milestone Budget', description: '', amount: '', currency: 'PKR' };
            apiEndpoint.value = props.milestone.budget ? `/api/projects/${props.milestone.project_id}/expendables/${props.milestone.budget.id}` : `/api/projects/${props.milestone.project_id}/expendables`;
            httpMethod.value = props.milestone.budget ? 'put' : 'post';
            submitButtonText.value = props.milestone.budget ? 'Update Budget' : 'Create Budget';
            successMessage.value = props.milestone.budget ? 'Milestone budget updated successfully' : 'Milestone budget created successfully';
        } else {
            form.value = { name: '', description: '', amount: '', currency: 'PKR', user_id: null };
            apiEndpoint.value = `/api/projects/${props.milestone.project_id}/expendables`;
            httpMethod.value = 'post';
            submitButtonText.value = 'Create Contract';
            successMessage.value = 'Contract created and sent for approval';
        }
    }
}, { immediate: true, deep: true });

const validateForm = () => {
    errors.value = {};
    let hasError = false;

    if (!form.value.name) {
        errors.value.name = ['Name is required.'];
        hasError = true;
    }
    if (!form.value.amount || Number(form.value.amount) <= 0) {
        errors.value.amount = errors.value.amount || [];
        errors.value.amount.push('Amount must be a positive number.');
        hasError = true;
    }

    // New validation check for user selection
    if (props.isUserSelectionRequired && !form.value.user_id) {
        errors.value.user_id = ['User selection is required for contracts.'];
        hasError = true;
    }

    // Convert entered amount to base currency (backend provides AUD totals)
    const enteredInBase = convertCurrency(Number(form.value.amount || 0), form.value.currency, props.budgetBaseCurrency);

    if (props.isBudgetForm) {
        // Determine existing budget in base currency (if updating)
        const existingBudgetBase = props.milestone?.budget
            ? convertCurrency(Number(props.milestone.budget.amount || 0), props.milestone.budget.currency, props.budgetBaseCurrency)
            : 0;

        // Allowance in base currency:
        // - New budget: up to availableForNewMilestones
        // - Update: up to availableForNewMilestones + existing budget (since existing is already counted)
        const allowedMaxBase = Number(props.availableForNewMilestones || 0) + (props.milestone?.budget ? existingBudgetBase : 0);

        if (enteredInBase > allowedMaxBase + 1e-8) {
            errors.value.amount = [
                `Amount exceeds allowed limit for milestone budget. Max allowed: ${convertCurrency(allowedMaxBase, props.budgetBaseCurrency, form.value.currency).toFixed(2)} ${form.value.currency}`
            ];
            hasError = true;
        }

    } else {
        // For contracts, ensure it does not exceed the remaining milestone budget.
        // Compare in the milestone budget currency context
        const amountInMilestoneBudgetCurrency = convertCurrency(Number(form.value.amount || 0), form.value.currency, props.milestone.budget?.currency || 'PKR');
        if (amountInMilestoneBudgetCurrency > Number(props.milestoneStats?.remaining || 0) + 1e-8) {
            errors.value.amount = [`Cannot add a contract more than the remaining milestone budget.`];
            hasError = true;
        }
    }

    return !hasError;
};

const formatDataForApi = (data) => {
    return {
        ...data,
        amount: Number(data.amount || 0),
        user_id: props.isBudgetForm ? null : data.user_id,
        expendable_type: 'Milestone',
        expendable_id: props.milestone.id,
    };
};

const handleSubmitted = (data) => {
    emit('submitted', data);
};

const handleClose = () => {
    emit('close');
};

// Helper computed values for UI hints
const allowedMaxBase = computed(() => {
    if (!props.isBudgetForm) return 0;
    const existingBudgetBase = props.milestone?.budget
        ? convertCurrency(Number(props.milestone.budget.amount || 0), props.milestone.budget.currency, props.budgetBaseCurrency)
        : 0;
    return Number(props.availableForNewMilestones || 0) + (props.milestone?.budget ? existingBudgetBase : 0);
});

const allowedMaxInSelectedCurrency = computed(() => {
    return convertCurrency(Number(allowedMaxBase.value || 0), props.budgetBaseCurrency, form.value?.currency || 'PKR');
});
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="title"
        :api-endpoint="apiEndpoint"
        :http-method="httpMethod"
        :form-data="form"
        :submit-button-text="submitButtonText"
        :success-message="successMessage"
        :format-data-for-api="formatDataForApi"
        :before-submit="validateForm"
        @submitted="handleSubmitted"
        @close="handleClose"
    >
        <template #default="{ errors: apiErrors }">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" class="mt-1 w-full" placeholder="e.g., Asset Purchase" />
                    <InputError :message="errors.name?.[0] || apiErrors.name?.[0]" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="amount" value="Amount" />
                    <TextInput id="amount" type="number" step="0.01" v-model="form.amount" class="mt-1 w-full" :max="isBudgetForm ? Number(allowedMaxInSelectedCurrency.toFixed ? allowedMaxInSelectedCurrency.toFixed(2) : allowedMaxInSelectedCurrency) : null" />
                    <InputError :message="errors.amount?.[0] || apiErrors.amount?.[0]" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="currency" value="Currency" />
                    <SelectDropdown id="currency" v-model="form.currency" :options="currencyOptions" value-key="value" label-key="label" class="mt-1 w-full" />
                    <InputError :message="errors.currency?.[0] || apiErrors.currency?.[0]" class="mt-1" />
                    <p v-if="isBudgetForm" class="text-xs text-gray-500 mt-1">Max allowed in selected currency: {{ (allowedMaxInSelectedCurrency).toFixed ? allowedMaxInSelectedCurrency.toFixed(2) : allowedMaxInSelectedCurrency }} {{ form.currency }}</p>
                </div>
                <div v-if="!isBudgetForm">
                    <InputLabel for="user" value="User" />
                    <SelectDropdown id="user" v-model="form.user_id" :options="userOptions" value-key="value" label-key="label" class="mt-1 w-full" placeholder="Select user" />
                    <InputError :message="errors.user_id?.[0] || apiErrors.user_id?.[0]" class="mt-1" />
                </div>
                <div class="md:col-span-2">
                    <InputLabel for="description" value="Description (optional)" />
                    <TextInput id="description" v-model="form.description" class="mt-1 w-full" placeholder="Details" />
                    <InputError :message="errors.description?.[0] || apiErrors.description?.[0]" class="mt-1" />
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>
