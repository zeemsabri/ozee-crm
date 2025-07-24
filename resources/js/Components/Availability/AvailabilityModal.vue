<script setup>
import { ref, watch, onMounted } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue'; // Still useful for general text inputs if needed
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';

// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        console.log('Auth headers set in AvailabilityModal');
    }
};

const props = defineProps({
    show: Boolean,
    // The 'date' prop is less relevant now as we handle multiple dates,
    // but we'll keep it for potential initial selection or default.
    date: {
        type: String,
        default: () => {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            return tomorrow.toISOString().split('T')[0];
        }
    },
    // This prop can be used to pre-populate the selectable dates,
    // otherwise, we'll generate the next 7 days.
    nextWeekDates: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'availability-saved']);

// State to hold availability for multiple days
// Key: date string (YYYY-MM-DD), Value: { isAvailable: boolean, reason: string, timeSlots: array }
const dailyAvailabilities = ref({});

const errors = ref({});
const isSubmitting = ref(false);
const successMessage = ref('');

// Generate dates for the next week (or use prop if provided)
const generateNextWeekDates = () => {
    const dates = [];
    const today = new Date();
    // Start from tomorrow
    const nextDay = new Date(today);
    nextDay.setDate(today.getDate() + 1);

    for (let i = 0; i < 7; i++) { // Generate for the next 7 days
        const date = new Date(nextDay);
        date.setDate(nextDay.getDate() + i);
        const dateString = date.toISOString().split('T')[0];
        dates.push({
            value: dateString,
            label: date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })
        });
        // Initialize state for each generated date
        dailyAvailabilities.value[dateString] = {
            isSelected: false, // New property to track if this day is being set
            isAvailable: true,
            reason: '',
            timeSlots: [{ start_time: '', end_time: '' }]
        };
    }
    return dates;
};

const nextWeekDatesComputed = ref([]); // This will hold the {value, label} objects

onMounted(() => {
    // Ensure authentication headers are set as early as possible
    ensureAuthHeaders();

    if (props.nextWeekDates.length > 0) {
        nextWeekDatesComputed.value = props.nextWeekDates;
        props.nextWeekDates.forEach(dateObj => {
            dailyAvailabilities.value[dateObj.value] = {
                isSelected: false,
                isAvailable: true,
                reason: '',
                timeSlots: [{ start_time: '', end_time: '' }]
            };
        });
    } else {
        nextWeekDatesComputed.value = generateNextWeekDates();
    }
});

// Fetch existing availabilities for the date range
const fetchExistingAvailabilities = async () => {
    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();

        // Get the start and end dates from nextWeekDatesComputed
        if (nextWeekDatesComputed.value.length === 0) return;

        const startDate = nextWeekDatesComputed.value[0].value;
        const endDate = nextWeekDatesComputed.value[nextWeekDatesComputed.value.length - 1].value;

        // Fetch existing availabilities for the date range
        const response = await axios.get('/api/availabilities', {
            params: {
                start_date: startDate,
                end_date: endDate
            }
        });

        // Process the response
        if (response.data && response.data.availabilities) {
            const existingAvailabilities = response.data.availabilities;

            // Initialize dailyAvailabilities with default values
            nextWeekDatesComputed.value.forEach(dateObj => {
                dailyAvailabilities.value[dateObj.value] = {
                    isSelected: false,
                    isAvailable: true,
                    reason: '',
                    timeSlots: [{ start_time: '', end_time: '' }]
                };
            });

            // Update dailyAvailabilities with existing data
            existingAvailabilities.forEach(availability => {
                // Extract date part from ISO date string
                const dateString = availability.date.split('T')[0];

                if (dailyAvailabilities.value[dateString]) {
                    // Mark as selected since it already exists
                    dailyAvailabilities.value[dateString].isSelected = true;
                    dailyAvailabilities.value[dateString].isAvailable = availability.is_available;

                    if (availability.is_available) {
                        // Copy time slots
                        dailyAvailabilities.value[dateString].timeSlots =
                            availability.time_slots && availability.time_slots.length > 0
                                ? [...availability.time_slots]
                                : [{ start_time: '', end_time: '' }];
                    } else {
                        // Copy reason
                        dailyAvailabilities.value[dateString].reason = availability.reason || '';
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error fetching existing availabilities:', error);
        // Initialize with default values if there's an error
        nextWeekDatesComputed.value.forEach(dateObj => {
            dailyAvailabilities.value[dateObj.value] = {
                isSelected: false,
                isAvailable: true,
                reason: '',
                timeSlots: [{ start_time: '', end_time: '' }]
            };
        });
    }
};

// Reset form when modal is opened/closed
watch(() => props.show, async (newValue) => {
    if (newValue) {
        // Modal opened - reset errors and success message
        errors.value = {};
        successMessage.value = '';

        // Fetch existing availabilities
        await fetchExistingAvailabilities();
    }
}, { immediate: true }); // Run immediately on component mount

// Add a new time slot for a specific date
const addTimeSlot = (date) => {
    dailyAvailabilities.value[date].timeSlots.push({ start_time: '', end_time: '' });
};

// Remove a time slot for a specific date
const removeTimeSlot = (date, index) => {
    if (dailyAvailabilities.value[date].timeSlots.length > 1) {
        dailyAvailabilities.value[date].timeSlots.splice(index, 1);
    }
};

// Handle the toggle of isAvailable for a specific date
const handleIsAvailableToggle = (date, isChecked) => {
    dailyAvailabilities.value[date].isAvailable = isChecked;
    if (!isChecked) { // If not available, clear time slots
        dailyAvailabilities.value[date].timeSlots = [{ start_time: '', end_time: '' }];
    } else { // If available, clear reason
        dailyAvailabilities.value[date].reason = '';
    }
};

// Submit the form
const submitForm = async () => {
    isSubmitting.value = true;
    errors.value = {};
    successMessage.value = '';

    const availabilitiesToSubmit = [];
    let hasError = false;

    for (const dateObj of nextWeekDatesComputed.value) {
        const date = dateObj.value;
        const dailyData = dailyAvailabilities.value[date];

        if (dailyData.isSelected) { // Only process dates that the user has actively selected
            let dateErrors = {};

            if (dailyData.isAvailable) {
                // Validate time slots
                if (dailyData.timeSlots.some(slot => !slot.start_time || !slot.end_time)) {
                    dateErrors.timeSlots = 'Please fill in all time slots.';
                    hasError = true;
                } else {
                    // Validate time format and logic (start < end)
                    for (const slot of dailyData.timeSlots) {
                        const startTime = slot.start_time.split(':').map(Number);
                        const endTime = slot.end_time.split(':').map(Number);

                        if (startTime.length !== 2 || endTime.length !== 2 ||
                            isNaN(startTime[0]) || isNaN(startTime[1]) || isNaN(endTime[0]) || isNaN(endTime[1])) {
                            dateErrors.timeSlots = 'Invalid time format. Please use HH:MM format.';
                            hasError = true;
                            break;
                        }

                        const startMinutes = startTime[0] * 60 + startTime[1];
                        const endMinutes = endTime[0] * 60 + endTime[1];

                        if (startMinutes >= endMinutes) {
                            dateErrors.timeSlots = 'End time must be after start time.';
                            hasError = true;
                            break;
                        }
                    }
                }
            } else {
                // Validate reason if not available
                if (!dailyData.reason.trim()) {
                    dateErrors.reason = 'Please provide a reason for unavailability.';
                    hasError = true;
                }
            }

            if (Object.keys(dateErrors).length > 0) {
                errors.value[date] = dateErrors; // Store errors per date
            }

            // Add to submission array if valid for this day
            if (Object.keys(dateErrors).length === 0) {
                availabilitiesToSubmit.push({
                    date: date,
                    is_available: dailyData.isAvailable,
                    reason: dailyData.isAvailable ? null : dailyData.reason,
                    time_slots: dailyData.isAvailable ? dailyData.timeSlots : null
                });
            }
        }
    }

    if (hasError) {
        isSubmitting.value = false;
        errors.value.general = 'Please correct the errors in the highlighted dates.';
        return;
    }

    if (availabilitiesToSubmit.length === 0) {
        errors.value.general = 'Please select at least one day to set availability for.';
        isSubmitting.value = false;
        return;
    }

    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();

        // Submit to API - using the batch endpoint we added to the controller
        const response = await axios.post('/api/availabilities/batch', {
            availabilities: availabilitiesToSubmit
        });

        successMessage.value = 'Availability saved successfully!';

        // Emit event to parent, potentially with all saved availabilities
        emit('availability-saved', response.data.availabilities);

        // Close modal after a short delay
        setTimeout(() => {
            emit('close');
        }, 1500);
    } catch (error) {
        console.error('Error saving availability:', error);

        if (error.response && error.response.data && error.response.data.errors) {
            // Assuming backend returns errors structured by date or general
            errors.value.general = error.response.data.message || 'An error occurred while saving your availability.';
            // You might need more sophisticated error mapping here if backend returns per-date errors
        } else if (error.response && error.response.status === 409) {
            successMessage.value = error.response.data.message; // Conflict message
            setTimeout(() => {
                emit('close');
            }, 1500);
        } else {
            errors.value.general = 'An unexpected error occurred while saving your availability.';
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Modal :show="show" @close="$emit('close')" max-width="xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Submit Your Weekly Availability
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Please select the dates you wish to set availability for and provide your details for each.
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

                    <!-- Loop through each date for availability setting -->
                    <div
                        v-for="dateObj in nextWeekDatesComputed"
                        :key="dateObj.value"
                        class="mb-6 p-4 border rounded-lg shadow-sm"
                        :class="[
                            dailyAvailabilities[dateObj.value].isSelected ?
                                'border-indigo-300 bg-indigo-50' : 'border-gray-200',
                            // Add a special class for dates that were pre-filled from existing data
                            dailyAvailabilities[dateObj.value].isSelected &&
                            !errors[dateObj.value] ?
                                'border-l-4 border-l-indigo-500' : ''
                        ]"
                    >
                        <div class="flex items-center mb-3">
                            <input
                                type="checkbox"
                                :id="`select_date_${dateObj.value}`"
                                v-model="dailyAvailabilities[dateObj.value].isSelected"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            />
                            <label :for="`select_date_${dateObj.value}`" class="ml-2 block text-base font-medium text-gray-800">
                                {{ dateObj.label }}
                                <!-- Badge for dates with existing data -->
                                <span
                                    v-if="dailyAvailabilities[dateObj.value].isSelected"
                                    class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800"
                                >
                                    Saved
                                </span>
                            </label>
                        </div>

                        <div v-if="dailyAvailabilities[dateObj.value].isSelected">
                            <!-- Availability Toggle for this specific date -->
                            <div class="mb-4">
                                <div class="flex items-center">
                                    <input
                                        type="checkbox"
                                        :id="`isAvailableToggle_${dateObj.value}`"
                                        :checked="dailyAvailabilities[dateObj.value].isAvailable"
                                        @change="handleIsAvailableToggle(dateObj.value, $event.target.checked)"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    />
                                    <label :for="`isAvailableToggle_${dateObj.value}`" class="ml-2 block text-sm text-gray-700">
                                        I am available on this day
                                    </label>
                                </div>
                            </div>

                            <!-- Not Available Reason for this specific date -->
                            <div v-if="!dailyAvailabilities[dateObj.value].isAvailable" class="mb-4">
                                <InputLabel :for="`reason_${dateObj.value}`" value="Reason for Not Available" />
                                <textarea
                                    :id="`reason_${dateObj.value}`"
                                    v-model="dailyAvailabilities[dateObj.value].reason"
                                    rows="2"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    placeholder="e.g., Out of office, Holiday, Meeting all day"
                                ></textarea>
                                <InputError :message="errors[dateObj.value]?.reason" class="mt-2" />
                            </div>

                            <!-- Time Slots (if available) for this specific date -->
                            <div v-if="dailyAvailabilities[dateObj.value].isAvailable" class="mb-4">
                                <InputLabel value="Available Time Slots" />
                                <InputError :message="errors[dateObj.value]?.timeSlots" class="mt-2" />

                                <div v-for="(slot, index) in dailyAvailabilities[dateObj.value].timeSlots" :key="index" class="flex items-center space-x-2 mt-2">
                                    <div class="flex-1">
                                        <InputLabel :for="`start_time_${dateObj.value}_${index}`" value="Start Time" class="text-xs" />
                                        <input
                                            :id="`start_time_${dateObj.value}_${index}`"
                                            type="time"
                                            v-model="slot.start_time"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        />
                                    </div>

                                    <div class="flex-1">
                                        <InputLabel :for="`end_time_${dateObj.value}_${index}`" value="End Time" class="text-xs" />
                                        <input
                                            :id="`end_time_${dateObj.value}_${index}`"
                                            type="time"
                                            v-model="slot.end_time"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        />
                                    </div>

                                    <div class="flex items-end pb-1" v-if="dailyAvailabilities[dateObj.value].timeSlots.length > 1">
                                        <button
                                            type="button"
                                            @click="removeTimeSlot(dateObj.value, index)"
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
                                    @click="addTimeSlot(dateObj.value)"
                                    class="mt-3 w-full flex items-center justify-center px-4 py-2 border border-dashed border-indigo-300 rounded-lg text-indigo-600 hover:bg-indigo-50 transition duration-150 ease-in-out"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Add Another Time Slot
                                </button>
                            </div>
                        </div>
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
