<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';
import { useToast } from 'vue-toast-notification'; // Assuming a toast notification library is available

// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
};

const props = defineProps({
    show: Boolean,
    userId: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['close', 'availability-saved']);
const $toast = useToast();

const dailyAvailabilities = ref({});
const errors = ref({});
const isSubmitting = ref(false);
const selectedDate = ref(null);
const reasonForLateSubmission = ref('');
const isCurrentWeekMode = ref(false);

const weekDates = ref([]);

// Generate a list of dates for the next week
const generateNextWeekDates = () => {
    const dates = [];
    const today = new Date();
    // Start from tomorrow
    const nextDay = new Date(today);
    nextDay.setDate(today.getDate() + 1);

    for (let i = 0; i < 7; i++) {
        const date = new Date(nextDay);
        date.setDate(nextDay.getDate() + i);
        const dateString = date.toISOString().split('T')[0];
        dates.push({
            value: dateString,
            label: date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' }),
            day: date.toLocaleDateString('en-US', { weekday: 'short' }),
        });
        dailyAvailabilities.value[dateString] = {
            isSelected: false,
            isAvailable: true,
            reason: '',
            timeSlots: [{ start_time: '', end_time: '' }]
        };
    }
    return dates;
};

// Generate a list of dates for the current week (Monday to Sunday)
const generateCurrentWeekDates = () => {
    const dates = [];
    const today = new Date();
    const dayOfWeek = today.getDay(); // Sunday - 0, Monday - 1, etc.
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1)); // Adjust to Monday

    for (let i = 0; i < 7; i++) {
        const date = new Date(startOfWeek);
        date.setDate(startOfWeek.getDate() + i);
        const dateString = date.toISOString().split('T')[0];
        dates.push({
            value: dateString,
            label: date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' }),
            day: date.toLocaleDateString('en-US', { weekday: 'short' }),
        });
        dailyAvailabilities.value[dateString] = {
            isSelected: false,
            isAvailable: true,
            reason: '',
            timeSlots: [{ start_time: '', end_time: '' }]
        };
    }
    return dates;
};

const switchToCurrentWeekMode = () => {
    isCurrentWeekMode.value = true;
    reasonForLateSubmission.value = '';
    weekDates.value = generateCurrentWeekDates();
    selectedDate.value = weekDates.value[0].value; // Select Monday of current week
    fetchExistingAvailabilities();
};

onMounted(() => {
    ensureAuthHeaders();
    // Default to next week's dates
    weekDates.value = generateNextWeekDates();
});

const fetchExistingAvailabilities = async () => {
    try {
        ensureAuthHeaders();
        if (weekDates.value.length === 0) return;

        const startDate = weekDates.value[0].value;
        const endDate = weekDates.value[weekDates.value.length - 1].value;

        const response = await axios.get('/api/availabilities', {
            params: { start_date: startDate, end_date: endDate, user_id: props.userId }
        });

        if (response.data && response.data.availabilities) {
            // Reset to default before populating
            weekDates.value.forEach(dateObj => {
                dailyAvailabilities.value[dateObj.value] = {
                    isSelected: false,
                    isAvailable: true,
                    reason: '',
                    timeSlots: [{ start_time: '', end_time: '' }]
                };
            });

            response.data.availabilities.forEach(availability => {
                const dateString = availability.date.split('T')[0];
                if (dailyAvailabilities.value[dateString]) {
                    dailyAvailabilities.value[dateString].isSelected = true;
                    dailyAvailabilities.value[dateString].isAvailable = availability.is_available;
                    if (availability.is_available) {
                        dailyAvailabilities.value[dateString].timeSlots =
                            availability.time_slots && availability.time_slots.length > 0
                                ? [...availability.time_slots]
                                : [{ start_time: '', end_time: '' }];
                    } else {
                        dailyAvailabilities.value[dateString].reason = availability.reason || '';
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error fetching existing availabilities:', error);
    }
};

watch(() => props.show, async (newValue) => {
    if (newValue) {
        errors.value = {};
        reasonForLateSubmission.value = '';
        isCurrentWeekMode.value = false; // Reset to default mode
        weekDates.value = generateNextWeekDates();
        await fetchExistingAvailabilities();
        if (weekDates.value.length > 0) {
            selectedDate.value = weekDates.value[0].value;
        }
    } else {
        selectedDate.value = null;
    }
}, { immediate: true });

const selectedDayData = computed(() => {
    return dailyAvailabilities.value[selectedDate.value] || {
        isSelected: false,
        isAvailable: true,
        reason: '',
        timeSlots: [{ start_time: '', end_time: '' }]
    };
});

const toggleDaySelection = (date) => {
    dailyAvailabilities.value[date].isSelected = !dailyAvailabilities.value[date].isSelected;
    selectedDate.value = date;
};

const addTimeSlot = () => {
    const timeSlots = selectedDayData.value.timeSlots;
    if (timeSlots) {
        timeSlots.push({ start_time: '', end_time: '' });
    }
};

const removeTimeSlot = (index) => {
    const timeSlots = selectedDayData.value.timeSlots;
    if (timeSlots && timeSlots.length > 1) {
        timeSlots.splice(index, 1);
    }
};

const handleIsAvailableToggle = (isChecked) => {
    selectedDayData.value.isAvailable = isChecked;
    if (!isChecked) {
        selectedDayData.value.timeSlots = [{ start_time: '', end_time: '' }];
    } else {
        selectedDayData.value.reason = '';
    }
};

const applyToAll = () => {
    console.log('hit');
    const currentDayData = selectedDayData.value;
    for (const dateObj of weekDates.value) {
        const date = dateObj.value;
        if (date !== selectedDate.value) {
            dailyAvailabilities.value[date] = JSON.parse(JSON.stringify(currentDayData));
            dailyAvailabilities.value[date].isSelected = true;
        }
    }
    $toast.success('Availability applied to all days!');
};

const submitForm = async () => {
    isSubmitting.value = true;
    errors.value = {};

    if (isCurrentWeekMode.value && !reasonForLateSubmission.value.trim()) {
        errors.value.lateReason = 'Please provide a reason for this late submission.';
        isSubmitting.value = false;
        return;
    }

    const availabilitiesToSubmit = [];
    let hasGeneralError = false;

    for (const dateObj of weekDates.value) {
        const date = dateObj.value;
        const dailyData = dailyAvailabilities.value[date];
        let dateErrors = {};

        if (dailyData.isSelected) {
            if (dailyData.isAvailable) {
                if (dailyData.timeSlots.some(slot => !slot.start_time || !slot.end_time)) {
                    dateErrors.timeSlots = 'Please fill in all time slots.';
                    hasGeneralError = true;
                } else {
                    for (const slot of dailyData.timeSlots) {
                        const startMinutes = slot.start_time.split(':').map(Number);
                        const endMinutes = slot.end_time.split(':').map(Number);
                        if (startMinutes[0] * 60 + startMinutes[1] >= endMinutes[0] * 60 + endMinutes[1]) {
                            dateErrors.timeSlots = 'End time must be after start time.';
                            hasGeneralError = true;
                            break;
                        }
                    }
                }
            } else {
                if (!dailyData.reason.trim()) {
                    dateErrors.reason = 'Please provide a reason for unavailability.';
                    hasGeneralError = true;
                }
            }
            if (Object.keys(dateErrors).length > 0) {
                errors.value[date] = dateErrors;
            }

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

    if (availabilitiesToSubmit.length === 0) {
        errors.value.general = 'Please select and set availability for at least one day.';
        isSubmitting.value = false;
        return;
    }

    try {
        ensureAuthHeaders();
        const payload = {
            availabilities: availabilitiesToSubmit,
            reason_for_late_submission: isCurrentWeekMode.value ? reasonForLateSubmission.value : null
        };
        await axios.post('/api/availabilities/batch', payload);
        $toast.success('Availability saved successfully!');
        emit('availability-saved');
        setTimeout(() => emit('close'), 1500);
    } catch (error) {
        console.error('Error saving availability:', error);
        $toast.error('An error occurred while saving your availability.');
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Modal :show="show" @close="$emit('close')" max-width="4xl">
        <div class="p-8">
            <h2 class="text-2xl font-bold text-gray-900">
                Set Your Weekly Availability
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Select a day to quickly set your schedule for the upcoming week.
                <span v-if="!isCurrentWeekMode" class="text-indigo-600 hover:text-indigo-800 transition duration-150 ease-in-out cursor-pointer" @click="switchToCurrentWeekMode">
                    Missed the deadline? Submit for this week.
                </span>
            </p>

            <!-- Late Submission Warning and Reason Field -->
            <div v-if="isCurrentWeekMode" class="mt-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 rounded-lg">
                <div class="flex items-center">
                    <svg class="h-5 w-5 text-yellow-500 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.257 3.344a1.5 1.5 0 012.486 0l5.446 9.176A1.5 1.5 0 0114.246 15H5.754a1.5 1.5 0 01-1.238-2.48zM9 11a1 1 0 102 0V7a1 1 0 10-2 0v4zm1-3a1 1 0 100 2h.01a1 1 0 100-2H10z" clip-rule="evenodd" />
                    </svg>
                    <p class="font-semibold">Late Submission for Current Week</p>
                </div>
                <p class="mt-2 text-sm">
                    Please provide a reason for submitting late. We acknowledge that availability submissions are due by Thursday evening.
                </p>
                <div class="mt-4">
                    <InputLabel value="Reason for late submission" />
                    <textarea
                        v-model="reasonForLateSubmission"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                        placeholder="e.g., I was out sick, I forgot, I had no access to the system"
                    ></textarea>
                    <InputError :message="errors.lateReason" class="mt-2" />
                </div>
            </div>

            <!-- Main two-column layout -->
            <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Weekly Calendar View -->
                <div class="lg:col-span-1 space-y-3">
                    <div
                        v-for="dateObj in weekDates"
                        :key="dateObj.value"
                        class="p-4 rounded-xl border cursor-pointer transition-all duration-200 ease-in-out"
                        :class="{
                            'bg-indigo-50 border-indigo-500 shadow-lg': selectedDate === dateObj.value,
                            'bg-white border-gray-200 hover:border-indigo-400 hover:shadow-md': selectedDate !== dateObj.value,
                            'border-red-400 bg-red-50': errors[dateObj.value],
                            'opacity-70': dateObj.isPast,
                        }"
                        @click="selectedDate = dateObj.value"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="text-lg font-bold" :class="{'text-indigo-600': selectedDate === dateObj.value, 'text-gray-800': selectedDate !== dateObj.value}">
                                    {{ dateObj.day }}
                                </div>
                                <div class="ml-2 text-sm text-gray-500">
                                    {{ dateObj.label.split(', ')[1] }}
                                </div>
                            </div>

                            <!-- Availability status badge -->
                            <span
                                v-if="dailyAvailabilities[dateObj.value]?.isSelected"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="{
                                    'bg-emerald-100 text-emerald-800': dailyAvailabilities[dateObj.value].isAvailable,
                                    'bg-amber-100 text-amber-800': !dailyAvailabilities[dateObj.value].isAvailable
                                }"
                            >
                                <span v-if="dailyAvailabilities[dateObj.value].isAvailable">Available</span>
                                <span v-else>Unavailable</span>
                            </span>
                            <span v-else class="text-xs text-gray-400">
                                Not Set
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Detail Form for Selected Day -->
                <div v-if="selectedDate" class="lg:col-span-2 p-6 bg-gray-50 rounded-xl shadow-inner">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">
                        Availability for {{ weekDates.find(d => d.value === selectedDate)?.label }}
                    </h3>

                    <!-- Bulk action button -->
                    <div class="mb-4 text-sm text-right">
                        <button type="button" @click="applyToAll" class="text-indigo-600 hover:text-indigo-800 transition duration-150 ease-in-out">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z" />
                                <path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z" />
                            </svg>
                            Apply to all days
                        </button>
                    </div>

                    <!-- Availability toggle -->
                    <div class="flex items-center justify-between mb-6 p-4 rounded-lg bg-white shadow-sm border border-gray-200">
                        <span class="text-sm font-medium text-gray-700">I am available on this day</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                :checked="selectedDayData.isAvailable"
                                @change="handleIsAvailableToggle($event.target.checked)"
                                class="sr-only peer"
                            >
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>
                    <InputError :message="errors[selectedDate]?.general" class="mt-2" />

                    <div v-if="selectedDayData.isAvailable">
                        <InputLabel value="Available Time Slots" class="text-gray-800 mb-2" />
                        <InputError :message="errors[selectedDate]?.timeSlots" class="mt-2 mb-4" />

                        <div v-for="(slot, index) in selectedDayData.timeSlots" :key="index" class="flex items-center space-x-2 mb-4">
                            <div class="flex-1">
                                <input
                                    type="time"
                                    v-model="slot.start_time"
                                    class="block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                />
                            </div>

                            <span class="text-gray-500">-</span>

                            <div class="flex-1">
                                <input
                                    type="time"
                                    v-model="slot.end_time"
                                    class="block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                />
                            </div>

                            <button
                                type="button"
                                @click="removeTimeSlot(index)"
                                v-if="selectedDayData.timeSlots.length > 1"
                                class="p-2 text-red-600 hover:text-red-800 transition duration-150 ease-in-out"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm6 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        <button
                            type="button"
                            @click="addTimeSlot"
                            class="mt-2 w-full flex items-center justify-center px-4 py-2 border border-dashed border-indigo-300 rounded-lg text-indigo-600 hover:bg-indigo-50 transition duration-150 ease-in-out"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            Add Another Time Slot
                        </button>
                    </div>

                    <div v-else>
                        <InputLabel value="Reason for Not Available" class="text-gray-800 mb-2" />
                        <textarea
                            v-model="selectedDayData.reason"
                            rows="3"
                            class="block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                            placeholder="e.g., Out of office, Holiday, Meeting all day"
                        ></textarea>
                        <InputError :message="errors[selectedDate]?.reason" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3">
                <SecondaryButton @click="$emit('close')">
                    Cancel
                </SecondaryButton>

                <PrimaryButton @click="submitForm" :disabled="isSubmitting || !selectedDate" :class="{ 'opacity-50': isSubmitting || !selectedDate }">
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
        </div>
    </Modal>
</template>
