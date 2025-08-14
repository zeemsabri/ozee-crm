<script setup>
import { ref, onMounted, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    metrics: {
        type: Object,
        required: true,
    },
});

const chartRef = ref(null);
let myChart = null;
const selectedUser = ref(null);

const formatCurrency = (n, precision = 2) => {
    if (n === null || n === undefined) return '-';
    const numericValue = Number(n);
    const options = {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: precision,
        minimumFractionDigits: precision
    };
    try {
        return new Intl.NumberFormat('en-PK', options).format(numericValue);
    } catch (e) {
        return `PKR ${numericValue.toFixed(precision)}`;
    }
};

const createChart = () => {
    if (myChart) {
        myChart.destroy();
    }

    if (!props.metrics?.most_improved?.recommendations || props.metrics.most_improved.recommendations.length === 0) {
        return;
    }

    const labels = props.metrics.most_improved.recommendations.map(r => r.name);
    const data = props.metrics.most_improved.recommendations.map(r => r.point_increase);

    myChart = new Chart(chartRef.value, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Point Increase',
                data: data,
                backgroundColor: [
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(153, 102, 255, 0.6)',
                    'rgba(255, 159, 64, 0.6)',
                    'rgba(255, 99, 132, 0.6)',
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 99, 132, 1)',
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Point Increase (pts)'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false,
                },
                title: {
                    display: true,
                    text: 'Most Improved Candidates by Point Increase'
                }
            }
        }
    });
};

const handleSubmit = () => {
    if (selectedUser.value) {
        console.log(`Submitting selection for Most Improved: User ID ${selectedUser.value}`);
        // Here you would implement the logic to send this selection to your API
        // axios.post('/api/bonus/most-improved', { user_id: selectedUser.value });
        alert(`Selection submitted! User ID: ${selectedUser.value}`);
    }
};

onMounted(() => {
    createChart();
});

watch(() => props.metrics, () => {
    createChart();
    selectedUser.value = null; // Reset selection when metrics change
});
</script>

<template>
    <div v-if="metrics && metrics.most_improved" class="bg-white p-5 rounded-2xl shadow-md transition-all duration-200 ease-in-out border-l-4 border-purple-500 hover:shadow-lg">
        <h4 class="text-2xl font-bold text-gray-800 mb-4">{{ metrics.most_improved.award }}</h4>
        <div class="h-64">
            <canvas ref="chartRef"></canvas>
        </div>

        <div class="mt-6">
            <p class="font-medium text-gray-700">Select candidate to receive the award:</p>
            <div class="flex flex-col md:flex-row items-center gap-4 mt-2">
                <select v-model="selectedUser" class="flex-1 p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    <option :value="null" disabled>Select a team member</option>
                    <option v-for="rec in metrics.most_improved.recommendations" :key="rec.user_id" :value="rec.user_id">
                        {{ rec.name }} (+{{ rec.point_increase }} pts)
                    </option>
                </select>
                <button
                    @click="handleSubmit"
                    :disabled="!selectedUser"
                    class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-all duration-200 ease-in-out w-full md:w-auto"
                >
                    Submit Selection
                </button>
            </div>
        </div>

        <div class="mt-4 border-t border-gray-200 pt-4 text-center">
            <p class="text-sm font-medium text-gray-700">Bonus Amount</p>
            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(metrics.most_improved.amount) }}</p>
        </div>
    </div>
    <div v-else class="text-gray-600 p-4 border rounded-lg bg-gray-50">
        No "Most Improved" metrics available for this period.
    </div>
</template>

<style scoped>
/* Scoped styles for the component */
</style>
