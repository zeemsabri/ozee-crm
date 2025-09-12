<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

function formatLocalDateTimeInput(d = new Date()) {
  const pad = (n) => String(n).padStart(2, '0');
  const year = d.getFullYear();
  const month = pad(d.getMonth() + 1);
  const day = pad(d.getDate());
  const hours = pad(d.getHours());
  const minutes = pad(d.getMinutes());
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

const props = defineProps({
  schedule: { type: Object, required: true },
  schedulableTypes: { type: Array, required: true },
  tasks: { type: Array, required: true },
  workflows: { type: Array, required: true },
});

// Try to infer mode from schedule flags; default to cron for safety
const initialMode = props.schedule.is_onetime ? 'once' : 'cron';

const form = useForm({
  name: props.schedule.name || '',
  description: props.schedule.description || '',
  scheduled_item_type: String(props.schedule.scheduled_item_type || 'workflow').toLowerCase(),
  scheduled_item_id: props.schedule.scheduled_item_id || null,
  start_at: props.schedule.start_at || formatLocalDateTimeInput(new Date()),
  end_at: props.schedule.end_at || '',
  mode: initialMode,
  // If not once/cron, user can switch; we leave time empty; users can select it
  time: new Date().toTimeString().slice(0,5),
  days_of_week: [],
  day_of_month: null,
  nth: 1,
  dow_for_monthly: 1,
  month: 1,
  cron: props.schedule.recurrence_pattern || '',
  is_active: !!props.schedule.is_active,
});

const typeOptions = computed(() => props.schedulableTypes);
const itemOptions = computed(() => {
  if (String(form.scheduled_item_type).toLowerCase().includes('task')) {
    return props.tasks.map(t => ({ id: t.id, name: t.name || `Task #${t.id}` }));
  }
  return props.workflows.map(w => ({ id: w.id, name: w.name || `Workflow #${w.id}` }));
});

watch(() => form.scheduled_item_type, () => {
  form.scheduled_item_id = null;
});

const modeOptions = [
  { value: 'once', label: 'Once' },
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'yearly', label: 'Yearly' },
  { value: 'cron', label: 'Custom cron' },
];

// Disable past date/time in user's local timezone
const minStartAt = computed(() => formatLocalDateTimeInput(new Date()));
const minEndAt = computed(() => form.start_at || minStartAt.value);

const humanSummary = computed(() => {
  const time = form.time || '00:00';
  switch (form.mode) {
    case 'once':
      return `One-time at ${form.start_at?.replace('T', ' ')} (deactivates after run)`;
    case 'daily':
      return `Daily at ${time}`;
    case 'weekly':
      if (!form.days_of_week?.length) return `Weekly at ${time}`;
      const names = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      const label = form.days_of_week.map(d => names[d]).join(', ');
      return `Weekly on ${label} at ${time}`;
    case 'monthly':
      if (form.day_of_month) return `Monthly on day ${form.day_of_month} at ${time}`;
      return `Monthly on the ${['First','Second','Third','Fourth','Fifth'][form.nth-1]} ${['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][form.dow_for_monthly]} at ${time}`;
    case 'yearly':
      const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return `Yearly on ${monthNames[(form.month||1)-1]} ${form.day_of_month||1} at ${time}`;
    case 'cron':
      return `Custom cron: ${form.cron || '* * * * *'}`;
  }
});

function submit() {
  form.put(route('schedules.update', props.schedule.id));
}
</script>

<template>
  <Head title="Edit Schedule" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Schedule</h2>
        <Link :href="route('schedules.index')" class="text-sm text-indigo-600 hover:underline">Back to list</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900 space-y-8">
            <!-- Basic Details -->
            <section>
              <h3 class="text-lg font-medium text-gray-900 mb-4">1. Basic Details</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <InputLabel value="Schedule Name" />
                  <input v-model="form.name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                  <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                </div>
                <div>
                  <InputLabel value="Linked Type" />
                  <SelectDropdown v-model="form.scheduled_item_type" :options="typeOptions" placeholder="Select type..." />
                </div>
                <div>
                  <InputLabel value="Linked Item" />
                  <SelectDropdown v-model="form.scheduled_item_id" :options="itemOptions" valueKey="id" labelKey="name" placeholder="Select item..." />
                  <div v-if="form.errors.scheduled_item_id" class="text-sm text-red-600 mt-1">{{ form.errors.scheduled_item_id }}</div>
                </div>
                <div>
                  <InputLabel value="Description (optional)" />
                  <input v-model="form.description" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
              </div>
            </section>

            <!-- Recurrence -->
            <section>
              <h3 class="text-lg font-medium text-gray-900 mb-4">2. Recurrence</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <InputLabel value="Run" />
                  <SelectDropdown v-model="form.mode" :options="modeOptions" placeholder="Select frequency..." />
                </div>
                <div v-if="form.mode !== 'cron' && form.mode !== 'once'">
                  <InputLabel value="Time" />
                  <input v-model="form.time" type="time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                <div>
                  <InputLabel value="Start at" />
                  <input v-model="form.start_at" type="datetime-local" :min="minStartAt" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                  <div v-if="form.errors.start_at" class="text-sm text-red-600 mt-1">{{ form.errors.start_at }}</div>
                </div>
                <div v-if="form.mode !== 'once'">
                  <InputLabel value="End at (optional)" />
                  <input v-model="form.end_at" type="datetime-local" :min="minEndAt" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                  <div v-if="form.errors.end_at" class="text-sm text-red-600 mt-1">{{ form.errors.end_at }}</div>
                </div>

                <div v-if="form.mode === 'weekly'" class="md:col-span-2">
                  <InputLabel value="Days of the week" />
                  <div class="mt-2 flex flex-wrap gap-2">
                    <label v-for="d in 7" :key="d-1" class="inline-flex items-center">
                      <input class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" type="checkbox" :value="d-1" v-model="form.days_of_week" />
                      <span class="ml-2 text-sm">{{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d-1] }}</span>
                    </label>
                  </div>
                </div>

                <template v-if="form.mode === 'monthly'">
                  <div>
                    <InputLabel value="Day of month" />
                    <input v-model.number="form.day_of_month" type="number" min="1" max="31" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <InputLabel value="Or: Nth weekday" />
                    <div class="flex gap-2">
                      <select v-model.number="form.nth" class="mt-1 block w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option :value="1">First</option>
                        <option :value="2">Second</option>
                        <option :value="3">Third</option>
                        <option :value="4">Fourth</option>
                        <option :value="5">Fifth</option>
                      </select>
                      <select v-model.number="form.dow_for_monthly" class="mt-1 block w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="d in 7" :key="d-1" :value="d-1">{{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d-1] }}</option>
                      </select>
                    </div>
                  </div>
                </template>

                <template v-if="form.mode === 'yearly'">
                  <div>
                    <InputLabel value="Month" />
                    <select v-model.number="form.month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                      <option v-for="m in 12" :key="m" :value="m">{{ ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][m-1] }}</option>
                    </select>
                  </div>
                  <div>
                    <InputLabel value="Day of month" />
                    <input v-model.number="form.day_of_month" type="number" min="1" max="31" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                  </div>
                </template>

                <div v-if="form.mode === 'cron'" class="md:col-span-2">
                  <InputLabel value="Cron expression" />
                  <input v-model="form.cron" type="text" placeholder="* * * * *" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
              </div>
            </section>

            <!-- Confirmation -->
            <section>
              <h3 class="text-lg font-medium text-gray-900 mb-4">3. Confirmation</h3>
              <div class="p-4 rounded-md bg-gray-50 border border-gray-200 text-sm text-gray-800">
                {{ humanSummary }}
              </div>
            </section>

            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-600">Status:</span>
                <span class="text-xs font-medium" :class="form.is_active ? 'text-green-700' : 'text-gray-600'">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
              </div>
              <div class="flex items-center gap-3">
                <Link :href="route('schedules.index')" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Cancel</Link>
                <button @click="submit" :disabled="form.processing" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                  {{ form.processing ? 'Saving…' : 'Save Changes' }}
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
