<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SelectDropdown from "@/Components/SelectDropdown.vue";
import { success } from '@/Utils/notification'; // Assuming this is available for notifications
import { usePermissions } from '@/Directives/permissions'; // Assuming this is available for permissions

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    paymentTypeOptions: { type: Array, required: true },
    canManageProjectServicesAndPayments: { type: Boolean, required: true },
    canViewProjectServicesAndPayments: { type: Boolean, required: true },
});

const emit = defineEmits(['updated']);

const errors = ref({});
const loading = ref(false);

// Currency options for the dropdown (can be fetched from an API or defined here)
const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

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
const showAddDepartmentInput = ref(false); // Controls visibility of the add department input

// Form data
const formData = reactive({
    services: [], // Array of selected service IDs
    service_details: [], // Array of objects, each containing details for a selected service
    payment_type: 'one_off',
    currency: 'AUD' // Default currency for overall project, though services have their own
});

// State for collapsible service cards
const expandedServices = reactive({});

// Computed property to calculate total amount from selected services
const calculatedTotalAmount = computed(() => {
    let total = 0;
    formData.services.forEach(serviceId => {
        const detail = getServiceDetail(serviceId);
        if (detail && detail.amount) {
            total += parseFloat(detail.amount);
        }
    });
    return total.toFixed(2); // Format to 2 decimal places
});

// Computed property to find the next upcoming payment due date
const nextPaymentDueDate = computed(() => {
    let upcomingPayment = null;
    let upcomingServiceLabel = null;
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Normalize today's date to start of day

    formData.service_details.forEach(serviceDetail => {
        if (serviceDetail.frequency === 'one_off' && serviceDetail.payment_breakdown) {
            serviceDetail.payment_breakdown.forEach(payment => {
                if (payment.due_date) {
                    const paymentDate = new Date(payment.due_date);
                    paymentDate.setHours(0, 0, 0, 0); // Normalize payment date

                    // Check if the payment date is today or in the future
                    if (paymentDate >= today) {
                        if (!upcomingPayment || paymentDate < upcomingPayment.date) {
                            upcomingPayment = {
                                date: paymentDate,
                                serviceId: serviceDetail.service_id,
                                paymentLabel: payment.label
                            };
                            upcomingServiceLabel = internalDepartmentOptions.value.find(
                                opt => opt.value === serviceDetail.service_id
                            )?.label || serviceDetail.service_id;
                        }
                    }
                }
            });
        }
    });

    if (upcomingPayment) {
        return {
            date: upcomingPayment.date.toLocaleDateString(),
            serviceLabel: upcomingServiceLabel,
            paymentLabel: upcomingPayment.paymentLabel
        };
    }
    return null;
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
        showAddDepartmentInput.value = false; // Hide the input after adding
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
        formData.payment_type = data.payment_type || 'one_off';
        formData.currency = data.currency || 'AUD'; // Ensure overall currency is set

        // Add any previously saved custom services to internalDepartmentOptions
        formData.services.forEach(serviceId => {
            if (!internalDepartmentOptions.value.some(option => option.value === serviceId)) {
                // Assuming serviceId is the label for custom services when fetched
                internalDepartmentOptions.value.push({ value: serviceId, label: serviceId });
            }
            // Initialize expanded state for selected services
            expandedServices[serviceId] = false;
        });

        // Ensure payment_breakdown is an array and convert legacy format, add due_date
        formData.service_details.forEach(detail => {
            if (detail.payment_breakdown && !Array.isArray(detail.payment_breakdown)) {
                const legacyBreakdown = detail.payment_breakdown;
                detail.payment_breakdown = [
                    { label: 'First', percentage: parseInt(legacyBreakdown.first) || 30, due_date: null },
                    { label: 'Second', percentage: parseInt(legacyBreakdown.second) || 30, due_date: null },
                    { label: 'Third', percentage: parseInt(legacyBreakdown.third) || 40, due_date: null }
                ];
            } else if (!detail.payment_breakdown || detail.payment_breakdown.length === 0) {
                // Ensure there's at least one payment breakdown field for new/empty cases
                detail.payment_breakdown = [
                    { label: 'Payment 1', percentage: 100, due_date: null }
                ];
            } else {
                // Ensure existing payment breakdowns have a due_date property
                detail.payment_breakdown.forEach(pb => {
                    if (pb.due_date === undefined) {
                        pb.due_date = null;
                    }
                });
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
            total_amount: parseFloat(calculatedTotalAmount.value), // Use calculated total amount
            payment_type: formData.payment_type,
            currency: formData.currency,
        };

        // Update the project
        const response = await window.axios.put(`/api/projects/${props.projectId}/sections/services-payment`, payload);

        // Show success message
        success('Services and payment information updated successfully!');

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
                    { label: 'Payment 1', percentage: 100, due_date: null } // Default to 100% for new service
                ],
                currency: formData.currency // Default to overall project currency
            });
        }
        // Expand the newly selected service
        expandedServices[serviceId] = true;
    } else {
        // Remove service from service_details
        formData.service_details = formData.service_details.filter(
            detail => detail.service_id !== serviceId
        );
        // Collapse the unselected service
        expandedServices[serviceId] = false;
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
                { label: 'Payment 1', percentage: 100, due_date: null } // Default for new detail
            ],
            currency: formData.currency // Default to overall project currency
        };
        formData.service_details.push(detail);
    } else if (detail.payment_breakdown && !Array.isArray(detail.payment_breakdown)) {
        // Convert legacy format to new array format
        const legacyBreakdown = detail.payment_breakdown;
        detail.payment_breakdown = [
            { label: 'First', percentage: parseInt(legacyBreakdown.first) || 30, due_date: null },
            { label: 'Second', percentage: parseInt(legacyBreakdown.second) || 30, due_date: null },
            { label: 'Third', percentage: parseInt(legacyBreakdown.third) || 40, due_date: null }
        ];

    } else if (!detail.payment_breakdown || detail.payment_breakdown.length === 0) {
        // Ensure there's at least one payment breakdown field if none exists
        detail.payment_breakdown = [{ label: 'Payment 1', percentage: 100, due_date: null }];
    } else {
        // Ensure existing payment breakdowns have a due_date property
        detail.payment_breakdown.forEach(pb => {
            if (pb.due_date === undefined) {
                pb.due_date = null;
            }
        });
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
            { label: 'First', percentage: parseInt(legacyBreakdown.first) || 30, due_date: null },
            { label: 'Second', percentage: parseInt(legacyBreakdown.second) || 30, due_date: null },
            { label: 'Third', percentage: parseInt(legacyBreakdown.third) || 40, due_date: null }
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

    // Add new payment with calculated percentage and null due date
    detail.payment_breakdown.push({
        label: `Payment ${currentLength + 1}`,
        percentage: newPercentage,
        due_date: null
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

// Toggle service card expansion
const toggleServiceExpansion = (serviceId) => {
    expandedServices[serviceId] = !expandedServices[serviceId];
};

// Fetch data when component is mounted or projectId changes
watch(() => props.projectId, (newProjectId) => {
    if (newProjectId) {
        fetchServicesAndPaymentData();
    }
}, { immediate: true });
</script>

<template>
    <div class="p-6 bg-white rounded-lg font-inter">

        <div v-if="errors.general" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline ml-2">{{ errors.general }}</span>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-8">
            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-600">Loading services and payment data...</span>
        </div>

        <div v-else>
            <!-- Next Payment Due Date Card -->
            <div v-if="nextPaymentDueDate" class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-6 rounded-lg shadow-md mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Next Payment Due</p>
                        <p class="text-3xl font-bold mt-1">{{ nextPaymentDueDate.date }}</p>
                        <p class="text-sm opacity-90 mt-2">
                            For: <span class="font-semibold">{{ nextPaymentDueDate.serviceLabel }}</span> -
                            <span class="font-semibold">{{ nextPaymentDueDate.paymentLabel }}</span>
                        </p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
            <div v-else class="bg-gray-100 text-gray-600 p-4 rounded-lg shadow-sm mb-6 text-center">
                No upcoming payment due dates found for one-off services.
            </div>

            <!-- Overall Project Payments Section -->
            <div class="bg-gray-50 p-6 rounded-lg shadow-sm mb-6 border border-gray-200">
                <h4 class="text-xl font-semibold text-gray-700 mb-4">Overall Project Payment Details</h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <InputLabel for="calculated_total_amount" value="Calculated Total Amount" />
                        <TextInput
                            id="calculated_total_amount"
                            type="text"
                            class="mt-1 block w-full bg-gray-100 cursor-not-allowed"
                            :value="calculatedTotalAmount"
                            disabled
                        />
                        <p class="text-sm text-gray-500 mt-1">This amount is automatically calculated from selected services.</p>
                    </div>

                    <div>
                        <InputLabel for="payment_type" value="Payment Type" />
                        <select
                            id="payment_type"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full p-2.5"
                            v-model="formData.payment_type"
                            required
                            :disabled="!canManageProjectServicesAndPayments"
                        >
                            <option v-for="option in paymentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="errors.payment_type ? errors.payment_type[0] : ''" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="overall_currency" value="Overall Project Currency" />
                        <SelectDropdown
                            id="overall_currency"
                            v-model="formData.currency"
                            :options="currencyOptions"
                            value-key="value"
                            label-key="label"
                            class="w-full mt-1"
                            :disabled="!canManageProjectServicesAndPayments"
                        />
                        <InputError :message="errors.currency ? errors.currency[0] : ''" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Services Selection and Details Section -->
            <div class="bg-gray-50 p-6 rounded-lg shadow-sm mb-6 border border-gray-200">
                <h4 class="text-xl font-semibold text-gray-700 mb-4">Manage Services</h4>

                <div class="mb-4">
                    <InputLabel for="services" value="Select Services" class="mb-2" />
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-4">
                        <div v-for="option in internalDepartmentOptions" :key="option.value" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                            <div class="flex items-center justify-between">
                                <label :for="`service_${option.value}`" class="flex items-center cursor-pointer">
                                    <Checkbox
                                        :id="`service_${option.value}`"
                                        :value="option.value"
                                        v-model:checked="formData.services"
                                        @update:checked="value => handleServiceSelection(option.value, value)"
                                        :disabled="!canManageProjectServicesAndPayments"
                                    />
                                    <span class="ms-2 text-base font-medium text-gray-800">{{ option.label }}</span>
                                </label>
                                <button
                                    v-if="(formData.services || []).includes(option.value)"
                                    @click="toggleServiceExpansion(option.value)"
                                    type="button"
                                    class="text-gray-500 hover:text-gray-700 transition-transform duration-200"
                                    :class="{ 'rotate-180': expandedServices[option.value] }"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>

                            <div v-if="(formData.services || []).includes(option.value) && expandedServices[option.value]" class="mt-4 pt-4 border-t border-gray-200">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <InputLabel :for="`service_amount_${option.value}`" value="Amount" />
                                        <TextInput
                                            :id="`service_amount_${option.value}`"
                                            type="number"
                                            step="0.01"
                                            placeholder="Service Amount"
                                            class="w-full mt-1"
                                            v-model="getServiceDetail(option.value).amount"
                                            :disabled="!canManageProjectServicesAndPayments"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel :for="`service_currency_${option.value}`" value="Currency" />
                                        <SelectDropdown
                                            :id="`service_currency_${option.value}`"
                                            v-model="getServiceDetail(option.value).currency"
                                            :options="currencyOptions"
                                            value-key="value"
                                            label-key="label"
                                            class="w-full mt-1"
                                            :disabled="!canManageProjectServicesAndPayments"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <InputLabel :for="`service_frequency_${option.value}`" value="Frequency" />
                                        <select
                                            :id="`service_frequency_${option.value}`"
                                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full p-2.5"
                                            v-model="getServiceDetail(option.value).frequency"
                                            :disabled="!canManageProjectServicesAndPayments"
                                        >
                                            <option value="monthly">Monthly</option>
                                            <option value="one_off">One off</option>
                                        </select>
                                    </div>

                                    <div>
                                        <InputLabel :for="`service_start_date_${option.value}`" value="Start Date" />
                                        <TextInput
                                            :id="`service_start_date_${option.value}`"
                                            type="date"
                                            class="w-full mt-1"
                                            v-model="getServiceDetail(option.value).start_date"
                                            :disabled="!canManageProjectServicesAndPayments"
                                        />
                                    </div>
                                </div>

                                <div v-if="getServiceDetail(option.value).frequency !== 'monthly'" class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                    <div class="flex justify-between items-center mb-3">
                                        <InputLabel value="Payment Breakdown (%)" class="text-base font-medium text-blue-800" />
                                        <PrimaryButton
                                            v-if="canManageProjectServicesAndPayments"
                                            type="button"
                                            @click="addPaymentField(option.value)"
                                            class="px-4 py-2 text-sm"
                                        >
                                            + Add Payment Step
                                        </PrimaryButton>
                                    </div>

                                    <div class="space-y-4">
                                        <div v-for="(payment, index) in getServiceDetail(option.value).payment_breakdown" :key="index" class="bg-white p-4 rounded-md shadow-sm border border-blue-100">
                                            <div class="flex justify-between items-center mb-2">
                                                <InputLabel :for="`payment_label_${index}_${option.value}`" :value="payment.label" class="font-semibold" />
                                                <button
                                                    v-if="canManageProjectServicesAndPayments && getServiceDetail(option.value).payment_breakdown.length > 1"
                                                    type="button"
                                                    class="text-red-500 hover:text-red-700 p-1 rounded-full hover:bg-red-100 transition-colors"
                                                    @click="removePaymentField(option.value, index)"
                                                    title="Remove payment step"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm-1 3a1 1 0 100 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <InputLabel :for="`payment_percentage_${index}_${option.value}`" value="Percentage (%)" class="text-sm" />
                                                    <TextInput
                                                        :id="`payment_percentage_${index}_${option.value}`"
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        class="w-full mt-1"
                                                        v-model.number="payment.percentage"
                                                        @input="e => {
                                                            payment.percentage = Math.max(0, Math.min(100, parseInt(e.target.value) || 0));
                                                        }"
                                                        :disabled="!canManageProjectServicesAndPayments"
                                                    />
                                                </div>
                                                <div>
                                                    <InputLabel :for="`payment_due_date_${index}_${option.value}`" value="Due Date (Optional)" class="text-sm" />
                                                    <TextInput
                                                        :id="`payment_due_date_${index}_${option.value}`"
                                                        type="date"
                                                        class="w-full mt-1"
                                                        v-model="payment.due_date"
                                                        :disabled="!canManageProjectServicesAndPayments"
                                                    />
                                                    <button
                                                        v-if="canManageProjectServicesAndPayments && payment.due_date"
                                                        type="button"
                                                        class="text-xs text-red-500 hover:text-red-700 mt-1"
                                                        @click="payment.due_date = null"
                                                    >
                                                        Clear Date
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-sm mt-4 p-2 rounded-md" :class="{
                                        'bg-green-100 text-green-700': totalPercentage(option.value) === 100,
                                        'bg-red-100 text-red-700 font-bold': totalPercentage(option.value) !== 100
                                    }">
                                        Total Percentage: {{ totalPercentage(option.value) }}%
                                        <span v-if="totalPercentage(option.value) !== 100">
                                            (Total must be 100%)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200">
                    <button
                        v-if="canManageProjectServicesAndPayments && !showAddDepartmentInput"
                        type="button"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-md"
                        @click="showAddDepartmentInput = true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New Service/Department
                    </button>
                    <div v-if="canManageProjectServicesAndPayments && showAddDepartmentInput" class="mt-4 flex items-center">
                        <TextInput
                            type="text"
                            class="flex-grow mr-2 border-gray-300 rounded-md shadow-sm p-2.5"
                            v-model="newDepartmentName"
                            placeholder="Enter new service/department name"
                            @keyup.enter="addNewDepartment"
                        />
                        <PrimaryButton @click="addNewDepartment">Add</PrimaryButton>
                        <button
                            type="button"
                            class="ml-2 text-sm text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md transition-colors"
                            @click="showAddDepartmentInput = false; newDepartmentName = ''"
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
                    :class="{ 'opacity-50 cursor-not-allowed': isSaveDisabled }"
                    class="px-6 py-3 text-base"
                >
                    Update Services & Payment
                </PrimaryButton>
            </div>
        </div>
    </div>
</template>

<style>
/* Add any custom styles here if needed, though Tailwind should handle most */
.font-inter {
    font-family: 'Inter', sans-serif;
}
</style>
