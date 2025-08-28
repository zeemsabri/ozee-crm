<script setup>
import { ref, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import TimezoneSelect from '@/Components/TimezoneSelect.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  configItem: { type: Object, required: true },
});

const emit = defineEmits(['close', 'submitted', 'error']);

const formData = ref({
  value: '',
});

watch(() => props.show, (val) => {
  if (val) {
    formData.value = { value: '' };
  }
});

const apiEndpoint = computed(() => props.configItem?.api?.endpoint || '/api/user/update-profile-field');
const httpMethod = computed(() => props.configItem?.api?.method || 'post');

const formatDataForApi = (data) => {
  if (props.configItem?.toPayload) {
    return props.configItem.toPayload(data.value);
  }
  const fieldParam = props.configItem?.api?.fieldParam || 'field';
  const valueParam = props.configItem?.api?.valueParam || 'value';
  return { [fieldParam]: props.configItem.column, [valueParam]: data.value };
};

const title = computed(() => props.configItem?.title || 'Additional Information Required');
</script>

<template>
  <BaseFormModal
    :show="show"
    :title="title"
    :api-endpoint="apiEndpoint"
    :http-method="httpMethod"
    :form-data="formData"
    submit-button-text="Save"
    success-message="Saved successfully"
    :format-data-for-api="formatDataForApi"
    @close="$emit('close')"
    @submitted="$emit('submitted', $event)"
    @error="$emit('error', $event)"
  >
    <template #default="{ errors }">
      <p class="text-sm text-gray-600 mb-3" v-if="configItem?.description">{{ configItem.description }}</p>
      <div>
        <!-- Render based on config -->
        <template v-if="configItem?.input?.type === 'component' && configItem?.input?.component === 'TimezoneSelect'">
          <TimezoneSelect v-model="formData.value" :required="true" />
          <InputError :message="errors?.value?.[0]" class="mt-2" />
        </template>
        <template v-else-if="configItem?.input?.type === 'select'">
          <InputLabel :value="configItem?.label || 'Select an option'" />
          <select v-model="formData.value" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option disabled value="">Please select</option>
            <option v-for="opt in (configItem?.input?.options || [])" :key="opt.value || opt" :value="opt.value || opt">
              {{ opt.label || opt }}
            </option>
          </select>
          <InputError :message="errors?.value?.[0]" class="mt-2" />
        </template>
        <template v-else>
          <InputLabel :value="configItem?.label || 'Value'" />
          <TextInput v-model="formData.value" class="mt-1 block w-full" />
          <InputError :message="errors?.value?.[0]" class="mt-2" />
        </template>
      </div>
    </template>
  </BaseFormModal>
</template>
