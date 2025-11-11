<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { success, error } from '@/Utils/notification';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    milestone: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['submitted', 'close']);

const formData = ref({
    newDueDate: '',
    reason: '',
});

const errors = ref({});
const submitting = ref(false);

const currentDueDate = ref('');

watch(() => props.show, (newValue) => {
    if (newValue && props.milestone) {
        currentDueDate.value = props.milestone.completion_date
            ? new Date(props.milestone.completion_date).toISOString().split('T')[0]
            : '';
        formData.value = { newDueDate: '', reason: '' };
        errors.value = {};
    }
});

const validateForm = () => {
    errors.value = {};

    if (!formData.value.newDueDate) {
        errors.value.newDueDate = ['New due date is required'];
        return false;
    }

    if (!formData.value.reason || !formData.value.reason.trim()) {
        errors.value.reason = ['Reason for change is required'];
        return false;
    }

    if (formData.value.reason.trim().length < 10) {
        errors.value.reason = ['Reason must be at least 10 characters'];
        return false;
    }

    // Validate that date is not in the past
    const selectedDate = new Date(formData.value.newDueDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (selectedDate < today) {
        errors.value.newDueDate = ['Due date cannot be in the past'];
        return false;
    }

    return true;
};

const handleSubmit = async () => {
    if (!validateForm()) {
        return;
    }

    submitting.value = true;
    try {
        const response = await window.axios.post(
            `/api/milestones/${props.milestone.id}/update-due-date`,
            {
                completion_date: formData.value.newDueDate,
                reason: formData.value.reason,
            }
        );

        success('Due date updated successfully!');
        emit('submitted');
        handleClose();
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {};
        } else if (e.response?.data?.message) {
            error(e.response.data.message);
        } else {
            error('Failed to update due date');
        }
        console.error(e);
    } finally {
        submitting.value = false;
    }
};

const handleClose = () => {
    formData.value = { newDueDate: '', reason: '' };
    errors.value = {};
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="handleClose" max-width="md">
        <div class="p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Update Milestone Due Date</h3>
                <p class="text-sm text-gray-600 mt-1">
                    <span v-if="milestone">Milestone: <strong>{{ milestone.name }}</strong></span>
                </p>
            </div>

            <div class="space-y-4">
                <!-- Current Due Date Display -->
                <div v-if="currentDueDate" class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-gray-600">
                        Current due date: <strong class="text-blue-900">{{ new Date(currentDueDate).toLocaleDateString() }}</strong>
                    </p>
                </div>

                <!-- New Due Date Input -->
                <div>
                    <InputLabel for="new-due-date" value="New Due Date" />
                    <TextInput
                        id="new-due-date"
                        v-model="formData.newDueDate"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="errors.newDueDate?.[0]" class="mt-1" />
                </div>

                <!-- Reason for Change -->
                <div>
                    <InputLabel for="reason" value="Reason for Change" />
                    <textarea
                        id="reason"
                        v-model="formData.reason"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                        rows="3"
                        placeholder="Please explain why the due date is being changed..."
                    ></textarea>
                    <InputError :message="errors.reason?.[0]" class="mt-1" />
                    <p class="text-xs text-gray-500 mt-1">Minimum 10 characters</p>
                </div>
            </div>

            <!-- Modal Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="handleClose" :disabled="submitting">
                    Cancel
                </SecondaryButton>
                <PrimaryButton @click="handleSubmit" :disabled="submitting">
                    {{ submitting ? 'Updating...' : 'Update Due Date' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
