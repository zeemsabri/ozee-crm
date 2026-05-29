<script setup>
import { ref, computed, watch } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { formatCurrency } from '@/Utils/currency';

const props = defineProps({
    modelValue: {
        type: [Object, String, null],
        default: null,
    },
    contractAmount: {
        type: [Number, String],
        default: 0,
    },
    contractCurrency: {
        type: String,
        default: 'PKR',
    },
    milestones: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

const PAYMENT_TYPES = [
    { value: 'fixed',        label: 'Fixed Price (Full on Completion)' },
    { value: 'installments', label: 'Custom Installments (e.g. 30/30/40)' },
    { value: 'milestone',    label: 'Milestone Based' },
    { value: 'retainer',     label: 'Monthly Retainer' },
    { value: 'hourly',       label: 'Hourly' },
];

const paymentTypeOptions = PAYMENT_TYPES.map(t => ({ value: t.value, label: t.label }));

// Internal state
const selectedType = ref('fixed');
const installments = ref([{ label: 'Payment 1', percentage: 100 }]);
const retainerMonths = ref(1);
const hourlyRate = ref('');
const estimatedHours = ref('');

// Parse existing value on mount
watch(() => props.modelValue, (val) => {
    if (!val) return;
    try {
        const parsed = typeof val === 'string' ? JSON.parse(val) : val;
        if (parsed && parsed.type) {
            selectedType.value = parsed.type;
            if (parsed.installments) installments.value = parsed.installments;
            if (parsed.months) retainerMonths.value = parsed.months;
            if (parsed.hourly_rate) hourlyRate.value = parsed.hourly_rate;
            if (parsed.estimated_hours) estimatedHours.value = parsed.estimated_hours;
        }
    } catch (e) {
        // fallback — old free-text value
    }
}, { immediate: true });

// When type changes, reset to sensible defaults
watch(selectedType, (type) => {
    if (type === 'fixed') {
        installments.value = [{ label: 'Full Payment', percentage: 100 }];
    } else if (type === 'installments') {
        installments.value = [
            { label: 'Kickoff', percentage: 50 },
            { label: 'Final Delivery', percentage: 50 },
        ];
    } else if (type === 'milestone') {
        // Auto-populate from milestones
        if (props.milestones.length > 0) {
            const equalShare = +(100 / props.milestones.length).toFixed(2);
            installments.value = props.milestones.map((m, idx) => ({
                label: m.name || `Milestone ${idx + 1}`,
                percentage: idx === props.milestones.length - 1
                    ? +(100 - equalShare * (props.milestones.length - 1)).toFixed(2)
                    : equalShare,
            }));
        } else {
            installments.value = [{ label: 'Milestone 1', percentage: 100 }];
        }
    } else if (type === 'retainer') {
        retainerMonths.value = 1;
    } else if (type === 'hourly') {
        hourlyRate.value = '';
        estimatedHours.value = '';
    }
    emitValue();
});

// Derived calculations
const totalAmount = computed(() => Number(props.contractAmount) || 0);

const totalPercentage = computed(() =>
    installments.value.reduce((sum, i) => sum + (Number(i.percentage) || 0), 0)
);

const percentageError = computed(() => {
    if (['installments', 'milestone'].includes(selectedType.value)) {
        const diff = Math.abs(totalPercentage.value - 100);
        return diff > 0.01 ? `Installments must total 100% (currently ${totalPercentage.value.toFixed(2)}%)` : null;
    }
    return null;
});

const retainerTotal = computed(() =>
    (Number(props.contractAmount) || 0) * Number(retainerMonths.value || 1)
);

const hourlyTotal = computed(() =>
    (Number(hourlyRate.value) || 0) * (Number(estimatedHours.value) || 0)
);

const installmentAmounts = computed(() =>
    installments.value.map(i => ({
        ...i,
        amount: (totalAmount.value * (Number(i.percentage) || 0)) / 100,
    }))
);

// Emit structured value on every change
function emitValue() {
    let value = { type: selectedType.value };

    if (['installments', 'milestone', 'fixed'].includes(selectedType.value)) {
        value.installments = installments.value.map(i => ({
            label: i.label,
            percentage: Number(i.percentage) || 0,
        }));
    } else if (selectedType.value === 'retainer') {
        value.months = Number(retainerMonths.value) || 1;
        value.monthly_amount = totalAmount.value;
    } else if (selectedType.value === 'hourly') {
        value.hourly_rate = Number(hourlyRate.value) || 0;
        value.estimated_hours = Number(estimatedHours.value) || 0;
    }

    emit('update:modelValue', JSON.stringify(value));
}

function addInstallment() {
    installments.value.push({ label: `Payment ${installments.value.length + 1}`, percentage: 0 });
    emitValue();
}

function removeInstallment(index) {
    if (installments.value.length <= 1) return;
    installments.value.splice(index, 1);
    emitValue();
}

function distributeEvenly() {
    const count = installments.value.length;
    const even = +(100 / count).toFixed(2);
    installments.value = installments.value.map((i, idx) => ({
        ...i,
        percentage: idx === count - 1 ? +(100 - even * (count - 1)).toFixed(2) : even,
    }));
    emitValue();
}
</script>

<template>
    <div class="space-y-4">
        <!-- Payment Type Selector -->
        <div>
            <InputLabel value="Payment Type" class="mb-1" />
            <SelectDropdown
                v-model="selectedType"
                :options="paymentTypeOptions"
                value-key="value"
                label-key="label"
                class="w-full"
                @update:modelValue="emitValue"
            />
        </div>

        <!-- ─── FIXED ─────────────────────────────────────────── -->
        <div v-if="selectedType === 'fixed'" class="rounded-xl bg-indigo-50 border border-indigo-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white text-lg font-bold flex-shrink-0">
                    💰
                </div>
                <div>
                    <p class="font-semibold text-indigo-900">Full Payment on Completion</p>
                    <p class="text-sm text-indigo-600">100% of the contract amount is due once the work is fully delivered and accepted.</p>
                </div>
            </div>
            <div class="mt-3 flex justify-between items-center bg-white rounded-lg p-3 border border-indigo-200">
                <span class="text-sm font-medium text-gray-700">Total Due</span>
                <span class="font-bold text-indigo-700 text-lg">{{ formatCurrency(totalAmount, contractCurrency) }}</span>
            </div>
        </div>

        <!-- ─── CUSTOM INSTALLMENTS / MILESTONE ──────────────── -->
        <div v-else-if="selectedType === 'installments' || selectedType === 'milestone'" class="space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-700">
                    {{ selectedType === 'milestone' ? 'Milestone Installments' : 'Custom Installments' }}
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="distributeEvenly"
                        class="text-xs px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
                    >
                        Distribute Evenly
                    </button>
                    <button
                        v-if="selectedType === 'installments'"
                        type="button"
                        @click="addInstallment"
                        class="text-xs px-3 py-1 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white transition"
                    >
                        + Add
                    </button>
                </div>
            </div>

            <!-- Installment Rows -->
            <div class="space-y-2">
                <div
                    v-for="(item, idx) in installments"
                    :key="idx"
                    class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg p-3"
                >
                    <div class="flex-1">
                        <input
                            v-model="item.label"
                            @input="emitValue"
                            :placeholder="`Payment ${idx + 1} label`"
                            class="w-full text-sm rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-2"
                        />
                    </div>
                    <div class="w-24 flex items-center gap-1">
                        <input
                            v-model.number="item.percentage"
                            @input="emitValue"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="w-full text-sm rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-2 text-right"
                        />
                        <span class="text-gray-500 text-sm">%</span>
                    </div>
                    <div class="w-28 text-right text-sm font-semibold text-indigo-700">
                        {{ formatCurrency((totalAmount * (Number(item.percentage) || 0)) / 100, contractCurrency) }}
                    </div>
                    <button
                        v-if="installments.length > 1"
                        type="button"
                        @click="removeInstallment(idx)"
                        class="text-red-400 hover:text-red-600 transition flex-shrink-0"
                        title="Remove"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Summary Bar -->
            <div
                class="flex justify-between items-center rounded-lg p-3 border"
                :class="percentageError ? 'bg-red-50 border-red-300' : 'bg-green-50 border-green-300'"
            >
                <div>
                    <span class="text-sm font-medium" :class="percentageError ? 'text-red-700' : 'text-green-700'">
                        Total: {{ totalPercentage.toFixed(2) }}%
                    </span>
                    <p v-if="percentageError" class="text-xs text-red-600 mt-0.5">{{ percentageError }}</p>
                    <p v-else class="text-xs text-green-600 mt-0.5">✓ Splits are balanced</p>
                </div>
                <span class="font-bold text-lg" :class="percentageError ? 'text-red-700' : 'text-green-700'">
                    {{ formatCurrency(totalAmount, contractCurrency) }}
                </span>
            </div>
        </div>

        <!-- ─── MONTHLY RETAINER ───────────────────────────────── -->
        <div v-else-if="selectedType === 'retainer'" class="space-y-3">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel value="Monthly Amount" class="mb-1" />
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-sm text-gray-500">{{ contractCurrency }}</span>
                        <TextInput
                            :model-value="contractAmount"
                            disabled
                            class="w-full bg-gray-100 text-gray-500 cursor-not-allowed"
                        />
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Set via contract amount above</p>
                </div>
                <div>
                    <InputLabel value="Number of Months" class="mb-1" />
                    <TextInput
                        v-model.number="retainerMonths"
                        type="number"
                        min="1"
                        @input="emitValue"
                        class="mt-1 w-full"
                        placeholder="e.g. 3"
                    />
                </div>
            </div>
            <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 flex justify-between items-center">
                <div>
                    <p class="text-sm font-medium text-blue-900">{{ formatCurrency(totalAmount, contractCurrency) }} × {{ retainerMonths }} months</p>
                    <p class="text-xs text-blue-600 mt-0.5">Recurring monthly payment</p>
                </div>
                <span class="font-bold text-blue-700 text-lg">{{ formatCurrency(retainerTotal, contractCurrency) }} total</span>
            </div>
        </div>

        <!-- ─── HOURLY ─────────────────────────────────────────── -->
        <div v-else-if="selectedType === 'hourly'" class="space-y-3">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel value="Hourly Rate" class="mb-1" />
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-sm text-gray-500">{{ contractCurrency }}</span>
                        <TextInput
                            v-model.number="hourlyRate"
                            type="number"
                            min="0"
                            step="0.01"
                            @input="emitValue"
                            class="w-full"
                            placeholder="e.g. 50"
                        />
                    </div>
                </div>
                <div>
                    <InputLabel value="Estimated Hours" class="mb-1" />
                    <TextInput
                        v-model.number="estimatedHours"
                        type="number"
                        min="0"
                        step="0.5"
                        @input="emitValue"
                        class="mt-1 w-full"
                        placeholder="e.g. 40"
                    />
                </div>
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 flex justify-between items-center">
                <div>
                    <p class="text-sm font-medium text-amber-900">
                        {{ formatCurrency(Number(hourlyRate) || 0, contractCurrency) }}/hr × {{ estimatedHours || 0 }} hrs
                    </p>
                    <p class="text-xs text-amber-600 mt-0.5">Estimate — billed on actual hours</p>
                </div>
                <span class="font-bold text-amber-700 text-lg">{{ formatCurrency(hourlyTotal, contractCurrency) }}</span>
            </div>
        </div>
    </div>
</template>
