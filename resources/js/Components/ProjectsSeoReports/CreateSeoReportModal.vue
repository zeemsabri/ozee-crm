<script setup>
import { reactive, computed, watch, ref } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue'; // Assuming you have this base modal component
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    projectId: {
        type: [String, Number],
        required: true,
    },
    initialData: { // NEW PROP: For editing existing reports
        type: Object,
        default: null,
    },
});

const emits = defineEmits(['close', 'saved']);

// Reactive form data
const seoReportForm = reactive({
    report_date: '', // YYYY-MM format
    data: '',        // JSON string
});

const jsonInputRef = ref(null); // Ref for the JSON textarea

// Watch for modal opening or initialData change to reset/populate form
watch(() => [props.show, props.initialData], ([newShow, newInitialData]) => {
    if (newShow) {
        if (newInitialData) {
            // Edit mode: populate form with initial data
            Object.assign(seoReportForm, {
                report_date: newInitialData.report_date || '',
                data: newInitialData.data || '', // data should already be a stringified JSON
            });
        } else {
            // Create mode: reset form
            Object.assign(seoReportForm, {
                report_date: '',
                data: '',
            });
        }
    }
}, { immediate: true });


// Function to format data for the API call (required by BaseFormModal)
const formatDataForApi = (data) => {
    // Ensure the data field is a stringified JSON
    let jsonData = data.data;
    try {
        // Attempt to parse and then stringify to ensure it's valid JSON
        jsonData = JSON.stringify(JSON.parse(data.data));
    } catch (e) {
        // If it's not valid JSON, send it as is, backend validation will catch it
        console.warn('Provided data is not valid JSON, sending as plain string. Backend should validate.');
    }

    return {
        report_date: data.report_date,
        data: jsonData,
    };
};

// Computed properties for BaseFormModal
const modalTitle = computed(() => props.initialData ? 'Edit SEO Report' : 'Create New SEO Report');
const apiEndpoint = computed(() => `/api/projects/${props.projectId}/seo-reports`);
const httpMethod = computed(() => 'post'); // The backend store method handles both create/update based on existence
const submitButtonText = computed(() => props.initialData ? 'Update Report' : 'Create Report');
const successMessage = computed(() => props.initialData ? 'SEO Report updated successfully!' : 'SEO Report created successfully!');

// Handle successful submission from BaseFormModal
const handleSaved = (responseData) => {
    emits('saved', responseData);
    emits('close');
};

// Pass through the close event
const closeModal = () => {
    emits('close');
};

// Function to attempt pretty-printing JSON
const prettyPrintJson = () => {
    try {
        const parsed = JSON.parse(seoReportForm.data);
        seoReportForm.data = JSON.stringify(parsed, null, 2);
    } catch (e) {
        // Ignore if not valid JSON, let user type freely
        console.error('Invalid JSON for pretty print:', e);
    }
};

// Function to validate JSON on blur
const validateJson = (errors) => {
    if (seoReportForm.data) {
        try {
            JSON.parse(seoReportForm.data);
            // Clear any previous JSON error if valid
            if (errors.data && errors.data[0] === 'The data field must be a valid JSON string.') {
                errors.data = []; // Clear specific error
            }
        } catch (e) {
            if (!errors.data || errors.data.length === 0) {
                errors.data = ['The data field must be a valid JSON string.'];
            }
        }
    }
};
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="modalTitle"
        :api-endpoint="apiEndpoint"
        :http-method="httpMethod"
        :form-data="seoReportForm"
        :submit-button-text="submitButtonText"
        :success-message="successMessage"
        :format-data-for-api="formatDataForApi"
        @close="closeModal"
        @submitted="handleSaved"
    >
        <template #default="{ errors }">
            <div class="space-y-4">
                <!-- Report Date -->
                <div>
                    <InputLabel for="report_date" value="Report Month (YYYY-MM)" />
                    <TextInput
                        id="report_date"
                        v-model="seoReportForm.report_date"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="e.g., 2025-07"
                        required
                        autofocus
                        pattern="\d{4}-\d{2}"
                        title="Please enter a date in YYYY-MM format (e.g., 2025-07)"
                        :disabled="!!initialData"
                    />
                    <InputError :message="errors.report_date ? errors.report_date[0] : ''" class="mt-2" />
                    <p class="text-xs text-gray-500 mt-1">Enter the report month in YYYY-MM format (e.g., 2025-07).</p>
                    <p v-if="!!initialData" class="text-xs text-gray-500 mt-1 text-red-500">Note: Report month cannot be changed when editing.</p>
                </div>

                <!-- Report Data (JSON) -->
                <div>
                    <InputLabel for="report_data" value="Report Data (JSON)" />
                    <textarea
                        id="report_data"
                        ref="jsonInputRef"
                        v-model="seoReportForm.data"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono"
                        rows="10"
                        placeholder='Enter JSON data for the report, e.g., {"keywords_ranked": 150, "traffic": 1200, "rankings": {"keyword1": 5, "keyword2": 12}}'
                        required
                        @blur="validateJson(errors)"
                    ></textarea>
                    <InputError :message="errors.data ? errors.data[0] : ''" class="mt-2" />
                    <div class="flex justify-end mt-2">
                        <button
                            type="button"
                            @click="prettyPrintJson"
                            class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                        >
                            Format JSON
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>
