<script setup>
import { reactive, watch, ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { useLeads } from '@/Composables/useLeads.js';

const props = defineProps({
  show: { type: Boolean, default: false },
  lead: { type: Object, default: null },
  users: { type: Array, default: () => [] },
  sourceOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'lead-created', 'lead-updated']);

const { createLead, updateLead } = useLeads();

const errors = ref({});
const saving = ref(false);

const form = reactive({
  id: null,
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  company: '',
  title: '',
  status: 'new',
  source: '',
  pipeline_stage: '',
  estimated_value: '',
  currency: 'USD',
  assigned_to_id: null,
  website: '',
  country: '',
  state: '',
  city: '',
  address: '',
  zip: '',
  tags: '',
  notes: '',
});

const userOptions = computed(() => (props.users || []).map(u => ({ label: u.name, value: u.id })));
const statusOpts = computed(() => props.statusOptions || []);
const sourceOpts = computed(() => props.sourceOptions || []);

const loadFromLead = (lead) => {
  if (!lead) {
    Object.assign(form, {
      id: null,
      first_name: '', last_name: '', email: '', phone: '',
      company: '', title: '', status: 'new', source: '', pipeline_stage: '',
      estimated_value: '', currency: 'USD', assigned_to_id: null,
      website: '', country: '', state: '', city: '', address: '', zip: '',
      tags: '', notes: '',
    });
    errors.value = {};
    return;
  }
  Object.assign(form, {
    id: lead.id,
    first_name: lead.first_name || '',
    last_name: lead.last_name || '',
    email: lead.email || '',
    phone: lead.phone || '',
    company: lead.company || '',
    title: lead.title || '',
    status: lead.status || 'new',
    source: lead.source || '',
    pipeline_stage: lead.pipeline_stage || '',
    estimated_value: lead.estimated_value ?? '',
    currency: lead.currency || 'USD',
    assigned_to_id: lead.assigned_to_id || null,
    website: lead.website || '',
    country: lead.country || '',
    state: lead.state || '',
    city: lead.city || '',
    address: lead.address || '',
    zip: lead.zip || '',
    tags: lead.tags || '',
    notes: lead.notes || '',
  });
  errors.value = {};
};

watch(() => props.lead, (val) => loadFromLead(val), { immediate: true });

const onSave = async () => {
  saving.value = true;
  errors.value = {};
  try {
    if (!form.id) {
      const data = await createLead(form);
      emit('lead-created', data);
    } else {
      const data = await updateLead(form.id, form);
      emit('lead-updated', data);
    }
    emit('close');
  } catch (error) {
    if (error?.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      console.error('Save failed', error);
    }
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <Modal :show="props.show" @close="emit('close')">
    <div class="p-6">
      <h3 class="text-lg font-semibold mb-4">{{ form.id ? 'Edit Lead' : 'Create Lead' }}</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <InputLabel value="First Name" />
          <TextInput v-model="form.first_name" class="mt-1 block w-full" />
          <InputError :message="errors.first_name" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Last Name" />
          <TextInput v-model="form.last_name" class="mt-1 block w-full" />
          <InputError :message="errors.last_name" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Email" />
          <TextInput v-model="form.email" class="mt-1 block w-full" />
          <InputError :message="errors.email" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Phone" />
          <TextInput v-model="form.phone" class="mt-1 block w-full" />
          <InputError :message="errors.phone" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Company" />
          <TextInput v-model="form.company" class="mt-1 block w-full" />
          <InputError :message="errors.company" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Title" />
          <TextInput v-model="form.title" class="mt-1 block w-full" />
          <InputError :message="errors.title" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Status" />
          <SelectDropdown
            :options="statusOpts"
            v-model="form.status"
            placeholder="Select status"
          />
          <InputError :message="errors.status" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Source" />
          <SelectDropdown
            :options="sourceOpts"
            v-model="form.source"
            placeholder="Select source"
          />
          <InputError :message="errors.source" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Assigned To" />
          <SelectDropdown
            :options="userOptions"
            v-model="form.assigned_to_id"
            placeholder="Unassigned"
          />
          <InputError :message="errors.assigned_to_id" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Estimated Value" />
          <TextInput v-model="form.estimated_value" type="number" step="0.01" class="mt-1 block w-full" />
          <InputError :message="errors.estimated_value" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Website" />
          <TextInput v-model="form.website" class="mt-1 block w-full" />
          <InputError :message="errors.website" class="mt-1" />
        </div>
        <div class="md:col-span-2">
          <InputLabel value="Notes" />
          <textarea v-model="form.notes" class="mt-1 block w-full border-gray-300 rounded-md" rows="3"></textarea>
          <InputError :message="errors.notes" class="mt-1" />
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-2">
        <SecondaryButton :disabled="saving" @click="emit('close')">Cancel</SecondaryButton>
        <PrimaryButton :disabled="saving" @click="onSave">{{ saving ? 'Saving...' : (form.id ? 'Update' : 'Create') }}</PrimaryButton>
      </div>
    </div>
  </Modal>
</template>
