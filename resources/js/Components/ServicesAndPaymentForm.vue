<script setup>
import { ref, reactive, computed, watch } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import axios from 'axios';
import { usePermissions } from '@/Directives/permissions';

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    // departmentOptions is removed from props
    paymentTypeOptions: { type: Array, required: true },
    canManageProjectServicesAndPayments: { type: Boolean, required: true },
    canViewProjectServicesAndPayments: { type: Boolean, required: true },
});

const emit = defineEmits(['updated']);

const errors = ref({});
const loading = ref(false);

// Internal base department options
const baseDepartmentOptions = [
    { value: 'Website Designing', label: 'Website Designing' },
    { value: 'SEO', label: 'SEO' },
    { value: 'Social Media', label: 'Social Media' },
    { value: 'Content Writing', label: 'Content Writing' },
    { value: 'Graphic Design', label: 'Graphic Design' },
];

// Reactive variable to hold all department options, including user-added ones
const internalDepartmentOptions = ref([...baseDepartmentOptions]);

// Reactive variables for adding new department
const newDepartmentName = ref('');
const showAddDepartment = ref(false); // Controls visibility of the add department input

// Form data
const formData = reactive({
    services: [],
    service_details: [],
    total_amount: '',
    payment_type: 'one_off',
});

// Computed property to check if all service payment breakdowns total 100%
const allBreakdownsAre100Percent = computed(() => {
    // Filter for services that are selected AND have a payment breakdown section (i.e., not monthly)
    const servicesWithBreakdown = formData.services.filter(serviceId => {
        const detail = getServiceDetail(serviceId);
        return detail && detail.frequency !== 'monthly';
    });

    if (servicesWithBreakdown.length === 0) {
        return true; // If no services with breakdowns, consider it valid
    }

    // Check each selected service's breakdown
    return servicesWithBreakdown.every(serviceId => {
        return totalPercentage(serviceId) === 100;
    });
});

// Computed property to determine if the save button should be disabled
const isSaveDisabled = computed(() => {
    return !props.projectId || !props.canManageProjectServicesAndPayments || !allBreakdownsAre100Percent.value;
});

// Add a new department option
const addNewDepartment = () => {
    const trimmedName = newDepartmentName.value.trim();
    if (trimmedName && !internalDepartmentOptions.value.some(option => option.label.toLowerCase() === trimmedName.toLowerCase())) {
        internalDepartmentOptions.value.push({
            value: trimmedName, // Using the name as value for new custom options
            label: trimmedName
        });
        newDepartmentName.value = ''; // Clear the input
        showAddDepartment.value = false; // Hide the input after adding
    }
};


// Fetch services and payment data
const fetchServicesAndPaymentData = async () => {
    if (!props.projectId) return;

    loading.value = true;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/services-payment`);
        const data = response.data;

        // Update services and payment information
        formData.services = data.services || [];
        formData.service_details = data.service_details || [];
        formData.total_amount = data.total_amount || '';
        formData.payment_type = data.payment_type || 'one_off';

        // Add any previously saved custom services to internalDepartmentOptions
        formData.services.forEach(serviceId => {
            if (!internalDepartmentOptions.value.some(option => option.value === serviceId)) {
                // Assuming serviceId is the label for custom services when fetched
                internalDepartmentOptions.value.push({ value: serviceId, label: serviceId });
            }
        });


        // Ensure payment_breakdown is an array and convert legacy format
        formData.service_details.forEach(detail => {
            if (detail.payment_breakdown && !Array.isArray(detail.payment_breakdown)) {
                const legacyBreakdown = detail.payment_breakdown;
                detail.payment_breakdown = [
                    { label: 'First', percentage: parseInt(legacyBreakdown.first) || 30 },
                    { label: 'Second', percentage: parseInt(legacyBreakdown.second) || 30 },
                    { label: 'Third', percentage: parseInt(legacyBreakdown.third) || 40 }
                ];
            } else if (!detail.payment_breakdown || detail.payment_breakdown.length === 0) {
                // Ensure there's at least one payment breakdown field for new/empty cases
                detail.payment_breakdown = [
                    { label: 'Payment 1', percentage: 100 }
                ];
            }
        });

        return data;
    } catch (error) {
        console.error('Error fetching services and payment data:', error);
        errors.value.general = 'Failed to fetch services and payment data.';
        return null;
    } finally {
        loading.value = false;
    }
};

// Update services and payment
const updateServicesAndPayment = async () => {
    errors.value = {};
    loading.value = true;

    try {
        // Create a clean copy of the form data
        const payload = {
            services: formData.services,
            service_details: formData.service_details,
            total_amount: formData.total_amount,
            payment_type: formData.payment_type,
        };

        // Update the project
        const response = await window.axios.put(`/api/projects/${props.projectId}/sections/services-payment`, payload);

        // Show success message
        alert('Services and payment information updated successfully!');

        // Emit updated event
        emit('updated');
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            errors.value.general = error.response.data.message;
        } else {
            errors.value.general = 'Failed to update services and payment information.';
            console.error('Error:', error);
        }
    } finally {
        loading.value = false;
    }
};

// Handle service selection
const handleServiceSelection = (serviceId, isSelected) => {
    if (isSelected) {
        // Add service to service_details if it doesn't exist
        if (!formData.service_details.some(detail => detail.service_id === serviceId)) {
            formData.service_details.push({
                service_id: serviceId,
                amount: '',
                frequency: 'one_off',
                start_date: '',
                payment_breakdown: [
                    { label: 'Payment 1', percentage: 100 } // Default to 100% for new service
                ]
            });
        }
    } else {
        // Remove service from service_details
        formData.service_details = formData.service_details.filter(
            detail => detail.service_id !== serviceId
        );
    }
};

// Get service detail
const getServiceDetail = (serviceId) => {
    // Find existing detail or create a new one
    let detail = formData.service_details.find(detail => detail.service_id === serviceId);
    if (!detail) {
        detail = {
            service_id: serviceId,
            amount: '',
            frequency: 'one_off',
            start_date: '',
            payment_breakdown: [
                { label: 'Payment 1', percentage: 100 } // Default for new detail
            ]
        };
        formData.service_details.push(detail);
    } else if (detail.payment_breakdown && !Array.isArray(detail.payment_breakdown)) {
        // Convert legacy format to new array format
        const legacyBreakdown = detail.payment_breakdown;
        detail.payment_breakdown = [
            { label: 'First', percentage: parseInt(legacyBreakdown.first) || 30 },
            { label: 'Second', percentage: parseInt(legacyBreakdown.second) || 30 },
            { label: 'Third', percentage: parseInt(legacyBreakdown.third) || 40 }
        ];
    } else if (!detail.payment_breakdown || detail.payment_breakdown.length === 0) {
        // Ensure there's at least one payment breakdown field if none exists
        detail.payment_breakdown = [{ label: 'Payment 1', percentage: 100 }];
    }
    return detail;
};

// Calculate total percentage for a service's payment breakdown
const totalPercentage = (serviceId) => {
    const detail = getServiceDetail(serviceId);
    if (!detail.payment_breakdown || !Array.isArray(detail.payment_breakdown)) return 0;

    return detail.payment_breakdown.reduce((sum, payment) => {
        return sum + (parseInt(payment.percentage) || 0);
    }, 0);
};

// Add a new payment field to a service
const addPaymentField = (serviceId) => {
    const detail = getServiceDetail(serviceId);
    if (!detail.payment_breakdown) {
        detail.payment_breakdown = [];
    }

    if (!Array.isArray(detail.payment_breakdown)) {
        // Convert legacy format to new array format if necessary
        const legacyBreakdown = detail.payment_breakdown;
        detail.payment_breakdown = [
            { label: 'First', percentage: parseInt(legacyBreakdown.first) || 30 },
            { label: 'Second', percentage: parseInt(legacyBreakdown.second) || 30 },
            { label: 'Third', percentage: parseInt(legacyBreakdown.third) || 40 }
        ];
    }

    const currentLength = detail.payment_breakdown.length;
    let newPercentage = 0;

    // If there's an existing last payment, take its percentage for distribution
    if (currentLength > 0) {
        const lastPayment = detail.payment_breakdown[currentLength - 1];
        newPercentage = Math.floor(lastPayment.percentage / 2); // Split it
        lastPayment.percentage -= newPercentage; // Update the last one
    } else {
        newPercentage = 100; // If no payments, new one gets 100%
    }

    // Add new payment with calculated percentage
    detail.payment_breakdown.push({
        label: `Payment ${currentLength + 1}`,
        percentage: newPercentage
    });
};


// Remove a payment field from a service
const removePaymentField = (serviceId, index) => {
    const detail = getServiceDetail(serviceId);
    if (!detail.payment_breakdown || !Array.isArray(detail.payment_breakdown)) return;

    // Don't remove if only one payment field remains
    if (detail.payment_breakdown.length <= 1) return;

    const removedPercentage = parseInt(detail.payment_breakdown[index].percentage) || 0;

    // Remove the payment field at the specified index
    detail.payment_breakdown.splice(index, 1);

    // Distribute the removed percentage
    if (removedPercentage > 0) {
        if (detail.payment_breakdown.length > 0) {
            // Add the removed percentage to the previous field if available, otherwise to the first field.
            const targetIndex = (index > 0 && index <= detail.payment_breakdown.length) ? index - 1 : 0;
            detail.payment_breakdown[targetIndex].percentage =
                (parseInt(detail.payment_breakdown[targetIndex].percentage) || 0) + removedPercentage;
        }
    }

    // Update labels after removal
    detail.payment_breakdown.forEach((payment, i) => {
        payment.label = `Payment ${i + 1}`;
    });
};


// Fetch data when component is mounted
watch(() => props.projectId, (newProjectId) => {
    if (newProjectId) {
        fetchServicesAndPaymentData();
    }
}, { immediate: true });
</script>

<template>
    <div>
        <div v-if="errors.general" class="text-red-600 text-sm mb-4">{{ errors.general }}</div>

        <div v-if="loading" class="text-gray-500 text-sm mb-4">Loading...</div>

        <div v-else>
            <div class="mb-4">
                <InputLabel for="total_amount" value="Total Amount" />
                <TextInput
                    id="total_amount"
                    type="number"
                    step="0.01"
                    class="mt-1 block w-full"
                    v-model="formData.total_amount"
                    :disabled="!canManageProjectServicesAndPayments"
                />
                <InputError :message="errors.total_amount ? errors.total_amount[0] : ''" class="mt-2" />
            </div>

            <div class="mb-4">
                <InputLabel for="payment_type" value="Payment Type" />
                <select
                    id="payment_type"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                    v-model="formData.payment_type"
                    required
                    :disabled="!canManageProjectServicesAndPayments"
                >
                    <option v-for="option in paymentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <InputError :message="errors.payment_type ? errors.payment_type[0] : ''" class="mt-2" />
            </div>

            <div class="mb-4">
                <InputLabel for="services" value="Services" />
                <div class="mt-2">
                    <div v-for="option in internalDepartmentOptions" :key="option.value" class="border p-3 mb-3 rounded">
                        <div class="flex items-center mb-2">
                            <Checkbox
                                :id="`service_${option.value}`"
                                :value="option.value"
                                v-model:checked="formData.services"
                                @update:checked="value => handleServiceSelection(option.value, value)"
                                :disabled="!canManageProjectServicesAndPayments"
                            />
                            <label :for="`service_${option.value}`" class="ms-2 text-sm font-medium text-gray-700">{{ option.label }}</label>
                        </div>

                        <div v-if="(formData.services || []).includes(option.value)" class="pl-6">
                            <div class="grid grid-cols-2 gap-4 mb-2">
                                <div>
                                    <InputLabel :for="`service_amount_${option.value}`" value="Amount" class="text-xs" />
                                    <TextInput
                                        :id="`service_amount_${option.value}`"
                                        type="number"
                                        step="0.01"
                                        placeholder="Amount"
                                        class="w-full"
                                        v-model="getServiceDetail(option.value).amount"
                                        :disabled="!canManageProjectServicesAndPayments"
                                    />
                                </div>
                                <div>
                                    <InputLabel :for="`service_frequency_${option.value}`" value="Frequency" class="text-xs" />
                                    <select
                                        :id="`service_frequency_${option.value}`"
                                        class="border-gray-300 rounded-md w-full"
                                        v-model="getServiceDetail(option.value).frequency"
                                        :disabled="!canManageProjectServicesAndPayments"
                                    >
                                        <option value="monthly">Monthly</option>
                                        <option value="one_off">One off</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-2">
                                <InputLabel :for="`service_start_date_${option.value}`" value="Start Date" class="text-xs" />
                                <TextInput
                                    :id="`service_start_date_${option.value}`"
                                    type="date"
                                    class="w-full"
                                    v-model="getServiceDetail(option.value).start_date"
                                    :disabled="!canManageProjectServicesAndPayments"
                                />
                            </div>
                            <div v-if="getServiceDetail(option.value).frequency !== 'monthly'">
                                <div class="flex justify-between items-center mb-1">
                                    <InputLabel value="Payment Breakdown (%)" class="text-xs" />
                                    <button
                                        v-if="canManageProjectServicesAndPayments"
                                        type="button"
                                        class="text-xs text-blue-600 hover:text-blue-800"
                                        @click="addPaymentField(option.value)"
                                    >
                                        + Add Payment
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                                    <div v-for="(payment, index) in getServiceDetail(option.value).payment_breakdown" :key="index" class="relative">
                                        <InputLabel :for="`service_payment_${index}_${option.value}`" :value="payment.label" class="text-xs" />
                                        <div class="flex">
                                            <TextInput
                                                :id="`service_payment_${index}_${option.value}`"
                                                type="text"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                min="0"
                                                max="100"
                                                class="w-full"
                                                v-model.number="payment.percentage"
                                                @input="e => {
                                                    // Ensure percentage is a number and within 0-100
                                                    payment.percentage = Math.max(0, Math.min(100, parseInt(e.target.value) || 0));
                                                }"
                                                :disabled="!canManageProjectServicesAndPayments"
                                            />
                                            <button
                                                v-if="canManageProjectServicesAndPayments && getServiceDetail(option.value).payment_breakdown.length > 1"
                                                type="button"
                                                class="ml-1 text-red-500 hover:text-red-700"
                                                @click="removePaymentField(option.value, index)"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-xs mt-2" :class="{
                                    'text-gray-500': totalPercentage(option.value) === 100,
                                    'text-red-500 font-bold': totalPercentage(option.value) !== 100
                                }">
                                    Total: {{ totalPercentage(option.value) }}%
                                    <span v-if="totalPercentage(option.value) !== 100">
                                        (Total must be 100%)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <button
                        v-if="canManageProjectServicesAndPayments && !showAddDepartment"
                        type="button"
                        class="text-sm text-blue-600 hover:text-blue-800"
                        @click="showAddDepartment = true"
                    >
                        + Add New Service/Department
                    </button>
                    <div v-if="canManageProjectServicesAndPayments && showAddDepartment" class="mt-2 flex items-center">
                        <TextInput
                            type="text"
                            class="flex-grow mr-2"
                            v-model="newDepartmentName"
                            placeholder="New service/department name"
                            @keyup.enter="addNewDepartment"
                        />
                        <PrimaryButton @click="addNewDepartment">Add</PrimaryButton>
                        <button
                            type="button"
                            class="ml-2 text-sm text-gray-500 hover:text-gray-700"
                            @click="showAddDepartment = false; newDepartmentName = ''"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
                <InputError :message="errors.services ? errors.services[0] : ''" class="mt-2" />
            </div>

            <div v-if="canManageProjectServicesAndPayments" class="mt-6 flex justify-end">
                <PrimaryButton
                    @click="updateServicesAndPayment"
                    :disabled="isSaveDisabled"
                    :class="{ 'opacity-25': isSaveDisabled }"
                >
                    Update Services & Payment
                </PrimaryButton>
            </div>
        </div>
    </div>
</template>
