<script setup>
import { ref, watch, onMounted } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';

// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        console.log('Auth headers set in SingleDateAvailabilityModal');
    }
};

const props = defineProps({
    show: Boolean,
    date: {
        type: String,
        required: true
    },
    userId: {
        type: Number,
        default: null
    },
    isAdmin: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'availability-saved']);

// Form state
const isAvailable = ref(true);
const reason = ref('');
const timeSlots = ref([{ start_time: '', end_time: '' }]);
const errors = ref({});
const isSubmitting = ref(false);
const successMessage = ref('');
const formattedDate = ref('');
const existingAvailabilityId = ref(null);

const didNotShowUp = ref(false);
const wasLate = ref(false);
const leftEarly = ref(false);
const didNotShowUpReasonCategoryId = ref(null);
const wasLateReasonCategoryId = ref(null);
const leftEarlyReasonCategoryId = ref(null);
const reasonOptions = ref([]);
const actualStartTime = ref('');
const actualEndTime = ref('');
const adminComments = ref('');

const firstError = (field) => {
    const value = errors.value?.[field];
    if (Array.isArray(value)) return value[0];
    return value || '';
};

const resetAttendanceFields = () => {
    didNotShowUp.value = false;
    wasLate.value = false;
    leftEarly.value = false;
    didNotShowUpReasonCategoryId.value = null;
    wasLateReasonCategoryId.value = null;
    leftEarlyReasonCategoryId.value = null;
    actualStartTime.value = '';
    actualEndTime.value = '';
    adminComments.value = '';
};

const fetchReasonOptions = async () => {
    if (!props.isAdmin) return;

    try {
        ensureAuthHeaders();
        const response = await axios.get('/api/availabilities/reason-options');
        reasonOptions.value = response.data?.reason_options || [];
    } catch (error) {
        console.error('Error fetching attendance reason options:', error);
        reasonOptions.value = [];
    }
};

// Format the date for display
const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
};

// Fetch existing availability for the date
const fetchExistingAvailability = async () => {
    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();

        const response = await axios.get('/api/availabilities', {
            params: {
                start_date: props.date,
                end_date: props.date,
                ...(props.userId ? { user_id: props.userId } : {})
            }
        });

        if (response.data && response.data.availabilities && response.data.availabilities.length > 0) {
            const availability = response.data.availabilities[0];
            existingAvailabilityId.value = availability.id;
            isAvailable.value = availability.is_available;
            didNotShowUp.value = !!availability.did_not_show_up;
            wasLate.value = !!availability.was_late;
            leftEarly.value = !!availability.left_early;
            didNotShowUpReasonCategoryId.value = availability.did_not_show_up_reason_category_id || null;
            wasLateReasonCategoryId.value = availability.was_late_reason_category_id || null;
            leftEarlyReasonCategoryId.value = availability.left_early_reason_category_id || null;
            actualStartTime.value = availability.actual_start_time || '';
            actualEndTime.value = availability.actual_end_time || '';
            adminComments.value = availability.admin_comments || '';

            if (availability.is_available) {
                timeSlots.value = availability.time_slots && availability.time_slots.length > 0
                    ? [...availability.time_slots]
                    : [{ start_time: '', end_time: '' }];
            } else {
                reason.value = availability.reason || '';
            }
        } else {
            // Reset form if no existing availability
            resetForm();
            existingAvailabilityId.value = null;
        }
    } catch (error) {
        console.error('Error fetching existing availability:', error);
        resetForm();
        existingAvailabilityId.value = null;
    }
};

// Reset form
const resetForm = () => {
    isAvailable.value = true;
    reason.value = '';
    timeSlots.value = [{ start_time: '', end_time: '' }];
    resetAttendanceFields();
};

// Reset form when modal is opened/closed
watch(() => props.show, (newValue) => {
    if (newValue) {
        // Modal opened
        errors.value = {};
        successMessage.value = '';
        formattedDate.value = formatDate(props.date);
        fetchReasonOptions();
        fetchExistingAvailability();
    }
});

watch(didNotShowUp, (newValue) => {
    if (newValue) {
        // Keep other flags available; did-not-show-up can coexist in record history workflows.
    }
});

// Add a new time slot
const addTimeSlot = () => {
    timeSlots.value.push({ start_time: '', end_time: '' });
};

// Remove a time slot
const removeTimeSlot = (index) => {
    if (timeSlots.value.length > 1) {
        timeSlots.value.splice(index, 1);
    }
};

// Submit the form
const submitForm = async () => {
    isSubmitting.value = true;
    errors.value = {};
    successMessage.value = '';

    try {
        // Validate form
        if (isAvailable.value) {
            // Validate time slots
            if (timeSlots.value.some(slot => !slot.start_time || !slot.end_time)) {
                errors.value.timeSlots = 'Please fill in all time slots or mark yourself as not available.';
                isSubmitting.value = false;
                return;
            }

            // Validate time format and logic (start < end)
            for (const slot of timeSlots.value) {
                const startTime = slot.start_time.split(':').map(Number);
                const endTime = slot.end_time.split(':').map(Number);

                if (startTime.length !== 2 || endTime.length !== 2 ||
                    isNaN(startTime[0]) || isNaN(startTime[1]) || isNaN(endTime[0]) || isNaN(endTime[1])) {
                    errors.value.timeSlots = 'Invalid time format. Please use HH:MM format.';
                    isSubmitting.value = false;
                    return;
                }

                const startMinutes = startTime[0] * 60 + startTime[1];
                const endMinutes = endTime[0] * 60 + endTime[1];

                if (startMinutes >= endMinutes) {
                    errors.value.timeSlots = 'End time must be after start time.';
                    isSubmitting.value = false;
                    return;
                }
            }
        } else {
            // Validate reason if not available
            if (!reason.value.trim()) {
                errors.value.reason = 'Please provide a reason for unavailability.';
                isSubmitting.value = false;
                return;
            }
        }

        // Validate individual attendance reasons
        if (props.isAdmin && didNotShowUp.value && !didNotShowUpReasonCategoryId.value) {
            errors.value.did_not_show_up_reason_category_id = 'Please select a reason for not showing up.';
            isSubmitting.value = false;
            return;
        }

        if (props.isAdmin && wasLate.value && !wasLateReasonCategoryId.value) {
            errors.value.was_late_reason_category_id = 'Please select a reason for being late.';
            isSubmitting.value = false;
            return;
        }

        if (props.isAdmin && leftEarly.value && !leftEarlyReasonCategoryId.value) {
            errors.value.left_early_reason_category_id = 'Please select a reason for leaving early.';
            isSubmitting.value = false;
            return;
        }

        if (props.isAdmin && ((actualStartTime.value && !actualEndTime.value) || (!actualStartTime.value && actualEndTime.value))) {
            errors.value.actual_worked_time = 'Please provide both actual start and end times.';
            isSubmitting.value = false;
            return;
        }

        if (props.isAdmin && actualStartTime.value && actualEndTime.value) {
            const [sHour, sMin] = actualStartTime.value.split(':').map(Number);
            const [eHour, eMin] = actualEndTime.value.split(':').map(Number);
            if ((sHour * 60 + sMin) >= (eHour * 60 + eMin)) {
                errors.value.actual_worked_time = 'Actual end time must be after actual start time.';
                isSubmitting.value = false;
                return;
            }
        }

        // Ensure auth headers are set before making the request
        ensureAuthHeaders();

        const payload = {
            date: props.date,
            is_available: isAvailable.value,
            reason: isAvailable.value ? null : reason.value,
            time_slots: isAvailable.value ? timeSlots.value : null,
            did_not_show_up: props.isAdmin ? didNotShowUp.value : false,
            was_late: props.isAdmin ? wasLate.value : false,
            left_early: props.isAdmin ? leftEarly.value : false,
            did_not_show_up_reason_category_id: props.isAdmin && didNotShowUp.value ? didNotShowUpReasonCategoryId.value : null,
            was_late_reason_category_id: props.isAdmin && wasLate.value ? wasLateReasonCategoryId.value : null,
            left_early_reason_category_id: props.isAdmin && leftEarly.value ? leftEarlyReasonCategoryId.value : null,
            actual_start_time: props.isAdmin ? (actualStartTime.value || null) : null,
            actual_end_time: props.isAdmin ? (actualEndTime.value || null) : null,
            admin_comments: props.isAdmin ? (adminComments.value || null) : null,
            ...(props.userId ? { user_id: props.userId } : {})
        };

        const response = existingAvailabilityId.value
            ? await axios.put(`/api/availabilities/${existingAvailabilityId.value}`, payload)
            : await axios.post('/api/availabilities', payload);

        successMessage.value = existingAvailabilityId.value
            ? 'Availability updated successfully!'
            : 'Availability saved successfully!';

        existingAvailabilityId.value = response.data?.availability?.id || existingAvailabilityId.value;

        // Emit event to parent
        emit('availability-saved', response.data.availability);

        // Close modal after a short delay
        setTimeout(() => {
            emit('close');
        }, 1500);
    } catch (error) {
        console.error('Error saving availability:', error);

        if (error.response && error.response.status === 409 && error.response.data?.availability?.id) {
            existingAvailabilityId.value = error.response.data.availability.id;
            errors.value.general = 'The availability already exists. Please click Save again to update it.';
        } else if (error.response && error.response.data && error.response.data.errors) {
            errors.value = error.response.data.errors;
        } else {
            errors.value.general = 'An error occurred while saving your availability.';
        }
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    // Ensure authentication headers are set as early as possible
    ensureAuthHeaders();
    fetchReasonOptions();
});
</script>

<template>
    <Modal :show="show" @close="$emit('close')" max-width="md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Availability for {{ formattedDate }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Please provide your availability for this date. Admins can also record no-show, late arrival, early departure, and actual worked time.
            </p>

            <div class="mt-6">
                <form @submit.prevent="submitForm">
                    <!-- Success Message -->
                    <div v-if="successMessage" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        {{ successMessage }}
                    </div>

                    <!-- General Error -->
                    <div v-if="errors.general" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        {{ errors.general }}
                    </div>

                    <!-- Availability Toggle -->
                    <div class="mb-4">
                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                id="isAvailableToggle"
                                v-model="isAvailable"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            />
                            <label for="isAvailableToggle" class="ml-2 block text-sm text-gray-700">
                                I am available on this day
                            </label>
                        </div>
                    </div>

                    <div v-if="props.isAdmin" class="mb-6 rounded-md border border-gray-200 p-4">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3">Admin Attendance Notes</h3>

                        <div class="space-y-4">
                            <!-- Did Not Show Up -->
                            <div>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="didNotShowUp" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Did not show up
                                </label>
                                <div v-if="didNotShowUp" class="pl-6 mt-2">
                                    <InputLabel value="Reason" />
                                    <select
                                        v-model="didNotShowUpReasonCategoryId"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option :value="null">Select reason</option>
                                        <option v-for="option in reasonOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <InputError :message="firstError('did_not_show_up_reason_category_id')" class="mt-2" />
                                </div>
                            </div>

                            <!-- Arrived Late -->
                            <div>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input 
                                        v-model="wasLate" 
                                        type="checkbox" 
                                        :disabled="didNotShowUp"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" 
                                    />
                                    Arrived late
                                </label>
                                <div v-if="wasLate" class="pl-6 mt-2">
                                    <InputLabel value="Reason" />
                                    <select
                                        v-model="wasLateReasonCategoryId"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option :value="null">Select reason</option>
                                        <option v-for="option in reasonOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <InputError :message="firstError('was_late_reason_category_id')" class="mt-2" />
                                </div>
                            </div>

                            <!-- Left Early -->
                            <div>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input 
                                        v-model="leftEarly" 
                                        type="checkbox" 
                                        :disabled="didNotShowUp"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" 
                                    />
                                    Left early
                                </label>
                                <div v-if="leftEarly" class="pl-6 mt-2">
                                    <InputLabel value="Reason" />
                                    <select
                                        v-model="leftEarlyReasonCategoryId"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option :value="null">Select reason</option>
                                        <option v-for="option in reasonOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <InputError :message="firstError('left_early_reason_category_id')" class="mt-2" />
                                </div>
                            </div>

                            <!-- Actual Worked Time -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                                <div>
                                    <InputLabel value="Actual Start Time" />
                                    <input
                                        type="time"
                                        v-model="actualStartTime"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Actual End Time" />
                                    <input
                                        type="time"
                                        v-model="actualEndTime"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                            <InputError :message="firstError('actual_worked_time')" />

                            <!-- Admin Comments -->
                            <div class="pt-2">
                                <InputLabel value="Comments" />
                                <textarea
                                    v-model="adminComments"
                                    rows="3"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    placeholder="Add any additional notes or comments..."
                                ></textarea>
                                <InputError :message="firstError('admin_comments')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- Not Available Reason -->
                    <div v-if="!isAvailable" class="mb-4">
                        <InputLabel for="reason" value="Reason for Not Available" />
                        <textarea
                            id="reason"
                            v-model="reason"
                            rows="3"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            placeholder="e.g., Out of office, Holiday, Meeting all day"
                        ></textarea>
                        <InputError :message="firstError('reason')" class="mt-2" />
                    </div>

                    <!-- Time Slots (if available) -->
                    <div v-if="isAvailable" class="mb-4">
                        <InputLabel value="Available Time Slots" />
                        <InputError :message="firstError('timeSlots')" class="mt-2" />

                        <div v-for="(slot, index) in timeSlots" :key="index" class="flex items-center space-x-2 mt-2">
                            <div class="flex-1">
                                <InputLabel :for="`start_time_${index}`" value="Start Time" class="text-xs" />
                                <input
                                    :id="`start_time_${index}`"
                                    type="time"
                                    v-model="slot.start_time"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                />
                            </div>

                            <div class="flex-1">
                                <InputLabel :for="`end_time_${index}`" value="End Time" class="text-xs" />
                                <input
                                    :id="`end_time_${index}`"
                                    type="time"
                                    v-model="slot.end_time"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                />
                            </div>

                            <div class="flex items-end pb-1" v-if="timeSlots.length > 1">
                                <button
                                    type="button"
                                    @click="removeTimeSlot(index)"
                                    class="p-2 text-red-600 hover:text-red-800"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm6 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="addTimeSlot"
                            class="mt-3 w-full flex items-center justify-center px-4 py-2 border border-dashed border-indigo-300 rounded-lg text-indigo-600 hover:bg-indigo-50 transition duration-150 ease-in-out"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            Add Another Time Slot
                        </button>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="$emit('close')">
                            Cancel
                        </SecondaryButton>

                        <PrimaryButton :disabled="isSubmitting" :class="{ 'opacity-50': isSubmitting }">
                            <span v-if="isSubmitting" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Saving...
                            </span>
                            <span v-else>Save Availability</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </Modal>
</template>
