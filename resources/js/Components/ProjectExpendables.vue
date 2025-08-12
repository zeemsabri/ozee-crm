<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { usePermissions } from '@/Directives/permissions';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { success, error } from '@/Utils/notification';
import { formatCurrency, convertCurrency, displayCurrency, conversionRatesToUSD, fetchCurrencyRates } from '@/Utils/currency';

const props = defineProps({
  projectId: { type: [Number, String], required: true },
  userProjectRole: { type: Object, default: () => ({}) },
});

const { canView, canManage } = usePermissions(() => props.projectId);
const canViewProjectExpendable = canView('project_expendable', props.userProjectRole);
const canManageProjectExpendable = canManage('project_expendable', props.userProjectRole);

const items = ref([]);
const loading = ref(false);
const form = ref({
  name: '',
  description: '',
  amount: '',
  currency: 'PKR',
  status: 'active',
});
const errors = ref({});
const submitting = ref(false);

const currencyOptions = [
  { value: 'PKR', label: 'PKR' },
  { value: 'USD', label: 'USD' },
  { value: 'EUR', label: 'EUR' },
  { value: 'GBP', label: 'GBP' },
  { value: 'AUD', label: 'AUD' },
  { value: 'INR', label: 'INR' },
];

const currentDisplayCurrency = displayCurrency;

const totalInDisplayCurrency = computed(() => {
  if (!conversionRatesToUSD.value || Object.keys(conversionRatesToUSD.value).length === 0) return 0;
  return items.value.reduce((sum, it) => sum + convertCurrency(Number(it.amount || 0), it.currency, currentDisplayCurrency.value), 0);
});

async function fetchItems() {
  if (!canViewProjectExpendable.value) return;
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/projects/${props.projectId}/expendables`);
    items.value = data || [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function createItem() {
  errors.value = {};
  submitting.value = true;
  try {
    const payload = { ...form.value, amount: Number(form.value.amount) };
    const { data } = await window.axios.post(`/api/projects/${props.projectId}/expendables`, payload);
    items.value.unshift(data);
    form.value = { name: '', description: '', amount: '', currency: form.value.currency, status: 'active' };
    success('Expendable created');
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {};
    } else if (e.response?.data?.message) {
      error(e.response.data.message);
    } else {
      error('Failed to create expendable');
    }
  } finally {
    submitting.value = false;
  }
}

async function removeItem(item) {
  if (!confirm('Delete this expendable?')) return;
  try {
    await window.axios.delete(`/api/projects/${props.projectId}/expendables/${item.id}`);
    items.value = items.value.filter(x => x.id !== item.id);
    success('Deleted');
  } catch (e) {
    error('Failed to delete');
    console.error(e);
  }
}

onMounted(async () => {
  // Ensure currency rates are fetched before computing totals or conversions
  const storedCurrency = localStorage.getItem('displayCurrency');
  if (storedCurrency) currentDisplayCurrency.value = storedCurrency;
  await fetchCurrencyRates();
  await fetchItems();
});

// Keep localStorage synced with any currency changes (also handled in util, but safe here)
watch(currentDisplayCurrency, (newCurrency) => {
  if (newCurrency) localStorage.setItem('displayCurrency', newCurrency);
});
</script>

<template>
  <div>
    <!-- Header / Totals -->
    <div class="mb-6 p-4 bg-indigo-50 rounded-lg border border-indigo-100 flex items-center justify-between gap-4">
      <div>
        <div class="text-sm text-gray-600">Total Expendables</div>
        <div class="text-2xl font-semibold text-indigo-800">
          {{ formatCurrency(totalInDisplayCurrency, currentDisplayCurrency) }}
        </div>
      </div>
      <div class="w-48">
        <InputLabel for="display_currency" value="Display Currency" />
        <SelectDropdown id="display_currency" v-model="currentDisplayCurrency"
                        :options="currencyOptions" value-key="value" label-key="label" class="mt-1 w-full" />
      </div>
    </div>

    <!-- Create Form -->
    <div v-if="canManageProjectExpendable" class="mb-6 p-4 border rounded-lg">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <InputLabel for="name" value="Name" />
          <TextInput id="name" v-model="form.name" class="mt-1 w-full" placeholder="e.g., Asset Purchase" />
          <InputError :message="errors.name?.[0]" class="mt-1" />
        </div>
        <div>
          <InputLabel for="amount" value="Amount" />
          <TextInput id="amount" type="number" step="0.01" v-model="form.amount" class="mt-1 w-full" />
          <InputError :message="errors.amount?.[0]" class="mt-1" />
        </div>
        <div>
          <InputLabel for="currency" value="Currency" />
          <SelectDropdown id="currency" v-model="form.currency" :options="currencyOptions" value-key="value" label-key="label" class="mt-1 w-full" />
          <InputError :message="errors.currency?.[0]" class="mt-1" />
        </div>
        <div class="flex items-end">
          <PrimaryButton :disabled="submitting" @click="createItem">Add</PrimaryButton>
        </div>
        <div class="md:col-span-4">
          <InputLabel for="description" value="Description (optional)" />
          <TextInput id="description" v-model="form.description" class="mt-1 w-full" placeholder="Details" />
          <InputError :message="errors.description?.[0]" class="mt-1" />
        </div>
      </div>
    </div>

    <!-- List -->
    <div class="space-y-3">
      <div v-if="loading" class="text-gray-500">Loading...</div>
      <div v-else-if="!items.length" class="text-gray-500">No expendables yet.</div>
      <div v-for="item in items" :key="item.id" class="p-4 border rounded-lg flex items-center justify-between">
        <div>
          <div class="font-medium">{{ item.name }}</div>
          <div class="text-sm text-gray-600" v-if="item.description">{{ item.description }}</div>
          <div class="text-sm text-gray-700 mt-1">
            {{ formatCurrency(Number(item.amount), item.currency) }}
            <span v-if="item.currency && item.currency.toUpperCase() !== currentDisplayCurrency.toUpperCase()" class="text-gray-500">
              ({{ formatCurrency(convertCurrency(Number(item.amount), item.currency, currentDisplayCurrency), currentDisplayCurrency) }})
            </span>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs px-2 py-1 rounded-full" :class="item.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'">{{ item.status || 'active' }}</span>
          <SecondaryButton v-if="canManageProjectExpendable" @click="removeItem(item)" class="!text-red-600">Delete</SecondaryButton>
        </div>
      </div>
    </div>
  </div>
</template>
