<script setup>
/**
 * TriggerConfig.vue
 * Configuration panel for TRIGGER and SCHEDULE_TRIGGER step types.
 */
import { computed } from 'vue';
import { useAutomationsV2Store } from '../../Store/storeV2';

const props = defineProps({
    step: { type: Object, required: true },
});
const emit = defineEmits(['update:step']);
const store = useAutomationsV2Store();
const schema = computed(() => store.automationSchema || []);

function cfg(key, val) {
    // partial update to step_config
    emit('update:step', {
        ...props.step,
        step_config: { ...props.step.step_config, [key]: val },
    });
}

const isSchedule = computed(() => props.step.step_type === 'SCHEDULE_TRIGGER');

const selectedModel = computed({
    get: () => {
        if (props.step.step_config?.model) return props.step.step_config.model;
        const te = props.step.step_config?.trigger_event;
        if (te && te.includes('.')) {
            const parts = te.split('.');
            return parts[0].charAt(0).toUpperCase() + parts[0].slice(1);
        }
        return null;
    },
    set: (m) => emit('update:step', { ...props.step, step_config: { model: m, event: null, trigger_event: null } }),
});

const selectedEvent = computed({
    get: () => {
        if (props.step.step_config?.event) return props.step.step_config.event;
        const te = props.step.step_config?.trigger_event;
        if (te && te.includes('.')) {
            return te.split('.')[1];
        }
        return null;
    },
    set: (ev) => {
        const model = selectedModel.value;
        emit('update:step', {
            ...props.step,
            step_config: {
                ...props.step.step_config,
                event: ev,
                trigger_event: model && ev ? `${model.toLowerCase()}.${ev}` : null,
            },
        });
    },
});

const modelSchema = computed(() => schema.value.find(m => m.name === selectedModel.value) || null);
const events      = computed(() => modelSchema.value?.events || []);
const isUnsupported = computed(() => selectedModel.value && events.value.length === 0);

// Schedule cron / time fields
const scheduleType = computed({
    get: () => props.step.step_config?.schedule_type || 'daily',
    set: (v) => cfg('schedule_type', v),
});

const scheduleTime = computed({
    get: () => props.step.step_config?.schedule_time || '08:00',
    set: (v) => cfg('schedule_time', v),
});

const scheduleCron = computed({
    get: () => props.step.step_config?.cron_expression || '',
    set: (v) => cfg('cron_expression', v),
});
</script>

<template>
    <!-- Schedule Trigger -->
    <template v-if="isSchedule">
        <div class="space-y-3">
            <div class="rounded-xl bg-sky-50 border border-sky-200 px-4 py-3 text-sm text-sky-800">
                ⏰ This automation runs on a recurring schedule.
            </div>
            <div>
                <label class="field-label">Run frequency</label>
                <select :value="scheduleType" @change="scheduleType = $event.target.value" class="v2-select">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="hourly">Hourly</option>
                    <option value="cron">Custom (cron)</option>
                </select>
            </div>
            <div v-if="scheduleType !== 'cron'">
                <label class="field-label">Time</label>
                <input type="time" :value="scheduleTime" @input="scheduleTime = $event.target.value" class="v2-input" />
            </div>
            <div v-else>
                <label class="field-label">Cron Expression</label>
                <input type="text" :value="scheduleCron" @input="scheduleCron = $event.target.value" class="v2-input" placeholder="e.g. 0 8 * * 1-5" />
                <p class="text-[11px] text-gray-400 mt-1">Standard 5-field cron syntax (min, hour, day, month, weekday).</p>
            </div>
        </div>
    </template>

    <!-- Event Trigger -->
    <template v-else>
        <div class="space-y-3">
            <div>
                <label class="field-label">When this model</label>
                <select :value="selectedModel" @change="selectedModel = $event.target.value" class="v2-select">
                    <option value="">— Select model —</option>
                    <option v-for="m in schema" :key="m.name" :value="m.name">{{ m.name }}</option>
                </select>
            </div>

            <div v-if="selectedModel && events.length">
                <label class="field-label">Triggers on</label>
                <select :value="selectedEvent" @change="selectedEvent = $event.target.value" class="v2-select">
                    <option value="">— Select event —</option>
                    <option v-for="ev in events" :key="ev" :value="ev">{{ ev }}</option>
                </select>
            </div>

            <div v-if="isUnsupported" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                ⚠️ No trigger events are available for "{{ selectedModel }}". You can still use it in conditions or actions.
            </div>

            <div v-if="step.step_config?.trigger_event" class="rounded-xl bg-violet-50 border border-violet-200 px-3 py-2 text-xs font-mono text-violet-700">
                trigger_event: {{ step.step_config.trigger_event }}
            </div>
        </div>
    </template>
</template>
