<script setup>
import { ref, onMounted, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        default: 'Productivity Chart'
    },
    chartId: {
        type: String,
        default: 'productivity-chart'
    },
    type: {
        type: String, // 'bar', 'line', 'pie', 'doughnut'
        default: 'bar'
    }
});

const chartRef = ref(null);
let myChart = null;

const createChart = () => {
    if (myChart) {
        myChart.destroy();
    }
    
    if (!chartRef.value) return;

    myChart = new Chart(chartRef.value, {
        type: props.type,
        data: props.data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                title: {
                    display: !!props.title,
                    text: props.title
                }
            },
            scales: props.type === 'pie' || props.type === 'doughnut' ? {} : {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hours'
                    }
                }
            }
        }
    });
};

onMounted(() => {
    createChart();
});

watch(() => props.data, () => {
    createChart();
}, { deep: true });
</script>

<template>
    <div class="h-64 w-full">
        <canvas ref="chartRef" :id="chartId"></canvas>
    </div>
</template>
