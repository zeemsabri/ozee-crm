<script setup>
import { computed } from 'vue';
import KPICard from './KPICard.vue';
import { Cell, Pie, PieChart, ResponsiveContainer, BarChart, XAxis, YAxis, Tooltip, Bar, Legend, CartesianGrid } from 'recharts';

const props = defineProps({
    summary: Object,
    formatCurrency: Function,
});

const totalDistributedPercentage = computed(() => (props.summary.total_distributed_pkr / props.summary.total_budget_pkr) * 100);
const remainingBudget = computed(() => props.summary.total_budget_pkr - props.summary.total_distributed_pkr);

const poolData = computed(() => Object.entries(props.summary.pools).map(([key, value]) => ({
    name: key.charAt(0).toUpperCase() + key.slice(1),
    value: value.distributed_pkr,
})));
const COLORS = ['#22d3ee', '#4f46e5', '#f97316'];

const barData = computed(() => Object.entries(props.summary.pools).map(([key, value]) => ({
    name: key.charAt(0).toUpperCase() + key.slice(1),
    allocated: value.allocated_pkr,
    distributed: value.distributed_pkr,
})));
</script>

<template>
    <div class="space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <KPICard title="Total Budget" :value="formatCurrency(summary.total_budget_pkr)" />
            <KPICard title="Total Distributed" :value="formatCurrency(summary.total_distributed_pkr)" :percentage="totalDistributedPercentage" />
            <KPICard title="Remaining Budget" :value="formatCurrency(remainingBudget)" />
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white p-6 rounded-lg shadow-xl">
                <h3 class="text-xl font-semibold mb-4">Bonus Distribution by Pool</h3>
                <ResponsiveContainer width="100%" height="300">
                    <PieChart>
                        <Pie
                            :data="poolData"
                            dataKey="value"
                            nameKey="name"
                            cx="50%"
                            cy="50%"
                            outerRadius="120"
                            label
                        >
                            <Cell v-for="(entry, index) in poolData" :key="'cell-' + index" :fill="COLORS[index % COLORS.length]" />
                        </Pie>
                        <Tooltip :formatter="(value) => formatCurrency(value)" />
                        <Legend />
                    </PieChart>
                </ResponsiveContainer>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-xl">
                <h3 class="text-xl font-semibold mb-4">Allocated vs. Distributed Bonuses</h3>
                <ResponsiveContainer width="100%" height="300">
                    <BarChart :data="barData">
                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                        <XAxis dataKey="name" stroke="#6b7280" />
                        <YAxis stroke="#6b7280" :formatter="(value) => formatCurrency(value)" />
                        <Tooltip :formatter="(value, name) => [formatCurrency(value), name]" />
                        <Legend />
                        <Bar dataKey="allocated" fill="#d1d5db" name="Allocated" />
                        <Bar dataKey="distributed" fill="#22d3ee" name="Distributed" />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    </div>
</template>
