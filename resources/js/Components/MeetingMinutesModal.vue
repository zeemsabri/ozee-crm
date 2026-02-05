<script setup>
import { reactive, ref, watch, onMounted } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    show: Boolean,
    projectId: Number,
    projects: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'minutesAdded']);

const mode = ref('separate'); // 'separate' or 'full'

// Form state for adding meeting minutes
const minutesForm = reactive({
    project_id: null,
    discussion: '',
    more_info: '',
    blockers: '',
    actions: '',
    full_minutes: '',
});

// Watch for changes in projectId prop
watch(() => props.projectId, (newProjectId) => {
    if (newProjectId) {
        minutesForm.project_id = newProjectId;
    }
}, { immediate: true });

// Reset form when modal is closed
watch(() => props.show, (isVisible) => {
    if (isVisible) {
        minutesForm.project_id = props.projectId || null;
        minutesForm.discussion = '';
        minutesForm.more_info = '';
        minutesForm.blockers = '';
        minutesForm.actions = '';
        minutesForm.full_minutes = '';
        mode.value = 'separate';
    }
});

const handleMinutesSubmitted = (responseData) => {
    emit('minutesAdded');
};

const handleSubmissionError = (error) => {
    console.error('Error in MeetingMinutesModal submission:', error);
};

// Computed property for API endpoint
const apiEndpoint = ref('');
watch(() => minutesForm.project_id, (newProjectId) => {
    if (newProjectId) {
        apiEndpoint.value = `/api/projects/${newProjectId}/meeting-minutes`;
    } else {
        apiEndpoint.value = '';
    }
}, { immediate: true });

const projectOptions = ref([]);

watch(() => props.projects, (newProjects) => {
    projectOptions.value = newProjects.map(p => ({
        value: p.id,
        label: p.name
    }));
}, { immediate: true });

</script>

<template>
    <BaseFormModal
        :show="show"
        title="Add Meeting Minutes"
        :api-endpoint="apiEndpoint"
        http-method="post"
        :form-data="minutesForm"
        submit-button-text="Submit Minutes"
        success-message="Meeting minutes submitted successfully!"
        @close="$emit('close')"
        @submitted="handleMinutesSubmitted"
        @error="handleSubmissionError"
    >
        <template #default="{ errors }">
            <!-- Project Selection -->
            <div class="mb-4" v-if="!projectId">
                <InputLabel for="project_id" value="Select Project" />
                <SelectDropdown
                    v-model="minutesForm.project_id"
                    :options="projectOptions"
                    placeholder="Select a project..."
                    class="mt-1 block w-full"
                />
                <InputError :message="errors.project_id ? errors.project_id[0] : ''" class="mt-2" />
            </div>

            <!-- Mode Selector -->
            <div class="mb-6 flex space-x-4 border-b border-gray-100">
                <button
                    type="button"
                    @click="mode = 'separate'"
                    class="pb-2 px-1 text-sm font-medium transition-colors duration-200 border-b-2"
                    :class="mode === 'separate' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700'"
                >
                    Structured
                </button>
                <button
                    type="button"
                    @click="mode = 'full'"
                    class="pb-2 px-1 text-sm font-medium transition-colors duration-200 border-b-2"
                    :class="mode === 'full' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700'"
                >
                    Paste Text
                </button>
            </div>

            <!-- Separate Fields -->
            <div v-if="mode === 'separate'" class="space-y-4">
                <div>
                    <InputLabel for="discussion" value="Discussion" />
                    <textarea
                        id="discussion"
                        rows="3"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        v-model="minutesForm.discussion"
                        placeholder="What was discussed?"
                    ></textarea>
                    <InputError :message="errors.discussion ? errors.discussion[0] : ''" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="more_info" value="Require More Information" />
                    <textarea
                        id="more_info"
                        rows="2"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        v-model="minutesForm.more_info"
                        placeholder="Any information needed?"
                    ></textarea>
                    <InputError :message="errors.more_info ? errors.more_info[0] : ''" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="blockers" value="Blockers" />
                    <textarea
                        id="blockers"
                        rows="2"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        v-model="minutesForm.blockers"
                        placeholder="Any blockers?"
                    ></textarea>
                    <InputError :message="errors.blockers ? errors.blockers[0] : ''" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="actions" value="Actions" />
                    <textarea
                        id="actions"
                        rows="3"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        v-model="minutesForm.actions"
                        placeholder="Action items..."
                    ></textarea>
                    <InputError :message="errors.actions ? errors.actions[0] : ''" class="mt-2" />
                </div>
            </div>

            <!-- Full Field -->
            <div v-else>
                <InputLabel for="full_minutes" value="Past All Meeting Minutes" />
                <textarea
                    id="full_minutes"
                    rows="12"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm font-mono text-sm"
                    v-model="minutesForm.full_minutes"
                    placeholder="Paste all minutes here..."
                ></textarea>
                <InputError :message="errors.full_minutes ? errors.full_minutes[0] : ''" class="mt-2" />
            </div>
        </template>
    </BaseFormModal>
</template>
