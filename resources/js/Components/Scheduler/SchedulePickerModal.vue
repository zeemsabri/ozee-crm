<script setup>
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
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
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Schedule' },
  initial: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'save']);

const form = ref({
  name: '',
  description: '',
  start_at: formatLocalDateTimeInput(new Date()),
  end_at: '',
  mode: 'once',
  time: new Date().toTimeString().slice(0,5),
  days_of_week: [],
  day_of_month: null,
  nth: 1,
  dow_for_monthly: 1,
  month: 1,
  cron: '',
});

watch(() => props.show, (v) => {
  if (v) {
    // Reset from initial
    form.value = {
      name: props.initial.name || '',
      description: props.initial.description || '',
      start_at: props.initial.start_at || formatLocalDateTimeInput(new Date()),
      end_at: props.initial.end_at || '',
      mode: props.initial.mode || 'once',
      time: props.initial.time || new Date().toTimeString().slice(0,5),
      days_of_week: props.initial.days_of_week || [],
      day_of_month: props.initial.day_of_month || null,
      nth: props.initial.nth || 1,
      dow_for_monthly: props.initial.dow_for_monthly || 1,
      month: props.initial.month || 1,
      cron: props.initial.cron || '',
    };
  }
});

const modeOptions = [
  { value: 'once', label: 'Once' },
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'yearly', label: 'Yearly' },
  { value: 'cron', label: 'Custom cron' },
];

const minStartAt = computed(() => formatLocalDateTimeInput(new Date()));
const minEndAt = computed(() => form.value.start_at || minStartAt.value);

const humanSummary = computed(() => {
  const f = form.value;
  const time = f.time || '00:00';
  switch (f.mode) {
    case 'once':
      return `One-time at ${f.start_at?.replace('T', ' ')} (deactivates after run)`;
    case 'daily':
      return `Daily at ${time}`;
    case 'weekly':
      if (!f.days_of_week?.length) return `Weekly at ${time}`;
      const names = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      const label = f.days_of_week.map(d => names[d]).join(', ');
      return `Weekly on ${label} at ${time}`;
    case 'monthly':
      if (f.day_of_month) return `Monthly on day ${f.day_of_month} at ${time}`;
      return `Monthly on the ${['First','Second','Third','Fourth','Fifth'][f.nth-1]} ${['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][f.dow_for_monthly]} at ${time}`;
    case 'yearly':
      const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return `Yearly on ${monthNames[(f.month||1)-1]} ${f.day_of_month||1} at ${time}`;
    case 'cron':
      return `Custom cron: ${f.cron || '* * * * *'}`;
  }
});

function save() {
  // Basic validation for past start_at will be enforced server-side too
  emit('save', { ...form.value });
}
</script>

<template>
  <Modal :show="show" @close="() => emit('close')">
    <div class="p-4 sm:p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900">{{ title }}</h3>
        <button @click="() => emit('close')" class="text-gray-500 hover:text-gray-700">✕</button>
      </div>

      <div class="space-y-6">
        <!-- Basic Details -->
        <section>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <InputLabel value="Schedule Name" />
              <input v-model="form.name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g., Weekly reminder" />
            </div>
            <div>
              <InputLabel value="Description (optional)" />
              <input v-model="form.description" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
          </div>
        </section>

        <!-- Recurrence -->
        <section>
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
            </div>
            <div v-if="form.mode !== 'once'">
              <InputLabel value="End at (optional)" />
              <input v-model="form.end_at" type="datetime-local" :min="minEndAt" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
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
                <input v-model.number="form.day_of_month" type="number" min="1" max="31" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g., 15" />
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
          <div class="p-3 rounded-md bg-gray-50 border border-gray-200 text-sm text-gray-800">
            {{ humanSummary }}
          </div>
          <p class="text-[11px] text-gray-500 mt-1">Times are entered in your local timezone; they will be stored in the app timezone.</p>
        </section>

        <div class="flex items-center justify-end gap-3">
          <button @click="() => emit('close')" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
          <button @click="save" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save</button>
        </div>
      </div>
    </div>
  </Modal>
</template>
