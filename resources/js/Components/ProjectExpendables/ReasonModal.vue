<script setup>
import { ref, watch, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { success, error } from '@/Utils/notification';
import { Square2StackIcon, XCircleIcon } from '@heroicons/vue/24/outline';
import BaseFormModal from '@/Components/BaseFormModal.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Reason Required',
    },
    message: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        default: 'info',
        validator: (value) => ['info', 'warning'].includes(value),
    },
    apiEndpoint: {
        type: String,
        required: true,
    },
    httpMethod: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['submitted', 'close']);

const formData = ref({ reason: '' });
const validationErrors = ref({});

const validateReason = () => {
    if (!formData.value.reason || !formData.value.reason.trim()) {
        error('Reason is required.');
        validationErrors.value.reason = ['Reason is required.'];
        return false;
    }
    validationErrors.value = {};
    return true;
};

const handleSubmitted = () => {
    emit('submitted');
};

const close = () => {
    emit('close');
};

const formatDataForApi = (data) => {
    if (props.httpMethod === 'delete') {
        return { reason: data.reason };
    }
    return { ...data, review: data.reason };
};

watch(() => props.show, (newValue) => {
    if (newValue) {
        formData.value = { reason: '' };
        validationErrors.value = {};
    }
});
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="title"
        :api-endpoint="apiEndpoint"
        :http-method="httpMethod"
        :form-data="formData"
        :submit-button-text="'Submit'"
        :success-message="'Operation successful!'"
        :before-submit="validateReason"
        @close="close"
        @submitted="handleSubmitted"
    >
        <template #default="{ errors }">
            <p class="text-sm text-gray-600 mb-4">{{ message }}</p>
            <div class="mt-2">
                <InputLabel for="reason-input" value="Reason" />
                <TextInput id="reason-input" v-model="formData.reason" class="mt-1 block w-full" @keyup.enter="handleSubmitted" />
                <InputError :message="errors.reason?.[0] || validationErrors.reason?.[0]" class="mt-1" />
            </div>
        </template>
    </BaseFormModal>
</template>
