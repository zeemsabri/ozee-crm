<script setup>
import { reactive, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  milestoneId: { type: [Number, String], required: true },
  initialCompletionDate: { type: String, default: null }, // YYYY-MM-DD
});

const emit = defineEmits(['close', 'updated']);

const form = reactive({
  completion_date: props.initialCompletionDate || null,
});

const localErrors = reactive({ completion_date: null });

watch(
  () => props.show,
  (val) => {
    if (val) {
      form.completion_date = props.initialCompletionDate || null;
      localErrors.completion_date = null;
    }
  }
);

const apiEndpoint = computed(() => `/api/milestones/${props.milestoneId}`);

const validate = () => {
  localErrors.completion_date = null;
  if (!form.completion_date) {
    localErrors.completion_date = ['Completion date is required.'];
    return false;
  }
  return true;
};

const handleSubmitted = (updatedMilestone) => {
  emit('updated', updatedMilestone);
  emit('close');
};

const close = () => emit('close');
</script>

<template>
  <BaseFormModal
    :show="show"
    title="Set Milestone Completion Date"
    :api-endpoint="apiEndpoint"
    http-method="put"
    :form-data="form"
    submit-button-text="Save Date"
    success-message="Milestone updated"
    :before-submit="validate"
    @close="close"
    @submitted="handleSubmitted"
  >
    <template #default="{ errors }">
      <div class="space-y-3">
        <p class="text-sm text-gray-600">
          This milestone does not have a completion date. Please set it to continue creating tasks for its contracts.
        </p>
        <div>
          <InputLabel for="milestone-completion-date" value="Completion Date" />
          <TextInput
            id="milestone-completion-date"
            v-model="form.completion_date"
            type="date"
            class="mt-1 block w-full"
            required
          />
          <InputError :message="errors.completion_date?.[0] || localErrors.completion_date?.[0]" class="mt-1" />
        </div>
      </div>
    </template>
  </BaseFormModal>
</template>
