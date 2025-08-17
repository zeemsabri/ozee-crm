<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import Chart from 'chart.js/auto';

// Internal state (self-contained defaults)
const projectBudget = ref(30000);
const milestoneBudget = ref(13000);
const numMilestones = ref(5);
const milestonesOnTime = ref(5);
const onTimeCheck = ref(true);
const userRole = ref('pm'); // 'pm' or 'pa'

// Derived values shown on the UI
const pmPayout = ref(0);
const paPayout = ref(0);

// Chart refs and instances
const myPayoutCanvas = ref(null);
const totalDistributionCanvas = ref(null);
let myPayoutChartInstance = null;
let payoutDistributionChartInstance = null;

const energeticPalette = {
  blue: '#00A6ED',
  green: '#72C544',
  yellow: '#FFB400',
  red: '#F2545B',
  darkGray: '#2E2E2E'
};

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PKR', minimumFractionDigits: 0 }).format(value);

const chartTitle = ref('My Earning Breakdown');
const chartSubtitle = ref('This chart shows how your total potential payout is composed, with all payouts distributed at the end of the project.');

const calculateEarnings = () => {
  const pb = parseFloat(projectBudget.value) || 0;
  const totalMb = parseFloat(milestoneBudget.value) || 0;
  const numMs = parseInt(numMilestones.value) || 0;
  const msOnTime = parseInt(milestonesOnTime.value) || 0;
  const onTime = onTimeCheck.value;

  const pmBase = pb * 0.10;
  const contractorPayments = totalMb;
  let pmPerformanceBonus = 0;
  if (numMs > 0) {
    const avgMilestoneBudget = totalMb / numMs;
    pmPerformanceBonus = (avgMilestoneBudget * 0.05) * msOnTime;
  }
  const contractorBonus = totalMb * 0.05;
  const remainingBalance = pb - (pmBase + contractorPayments + pmPerformanceBonus + contractorBonus);

  let pmOnTimeBonus = 0;
  let paOnTimeBonus = 0;
  if (onTime && remainingBalance > 0) {
    pmOnTimeBonus = remainingBalance * 0.20;
    paOnTimeBonus = remainingBalance * 0.20;
  }

  const totalPmPayout = pmBase + pmPerformanceBonus + pmOnTimeBonus;
  const totalContractorPayout = contractorPayments + contractorBonus;
  const totalPaPayout = paOnTimeBonus;

  pmPayout.value = totalPmPayout.toFixed(0);
  paPayout.value = totalPaPayout.toFixed(0);

  // Update chart headings based on role
  if (userRole.value === 'pm') {
    chartTitle.value = 'My PM Earning Breakdown';
    chartSubtitle.value = 'This chart shows how my total potential payout as a Project Manager is composed, with all payouts distributed at the end of the project.';
  } else {
    chartTitle.value = 'My PA Earning Breakdown';
    chartSubtitle.value = 'This chart shows how my total potential payout as a Project Admin is composed, with all payouts distributed at the end of the project.';
  }

  updateCharts({
    pmBase,
    pmPerformanceBonus,
    pmOnTimeBonus,
    paOnTimeBonus,
    totalContractorPayout,
    totalPmPayout,
    totalPaPayout,
  });
};

const tooltipTitleCallback = function(tooltipItems) {
  const item = tooltipItems[0];
  let label = item.chart.data.labels[item.dataIndex];
  if (Array.isArray(label)) return label.join(' ');
  return label;
};

const updateCharts = ({ pmBase, pmPerformanceBonus, pmOnTimeBonus, paOnTimeBonus, totalContractorPayout, totalPmPayout, totalPaPayout }) => {
  // My payout doughnut
  if (myPayoutCanvas.value) {
    const labels = userRole.value === 'pm'
      ? ['Base Allocation', 'Milestone Bonus', 'On-Time Bonus']
      : ['On-Time Delivery Bonus'];

    const data = userRole.value === 'pm'
      ? [pmBase, pmPerformanceBonus, pmOnTimeBonus]
      : [paOnTimeBonus];

    const colors = userRole.value === 'pm'
      ? [energeticPalette.blue, energeticPalette.yellow, energeticPalette.green]
      : [energeticPalette.green];

    if (myPayoutChartInstance) {
      myPayoutChartInstance.data.labels = labels;
      myPayoutChartInstance.data.datasets[0].data = data;
      myPayoutChartInstance.data.datasets[0].backgroundColor = colors;
      myPayoutChartInstance.update();
    } else {
      myPayoutChartInstance = new Chart(myPayoutCanvas.value, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{
            label: 'My Payout',
            data,
            backgroundColor: colors,
            borderColor: '#ffffff',
            borderWidth: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' },
            tooltip: {
              callbacks: {
                title: tooltipTitleCallback,
                label: function(context) {
                  let label = context.label || '';
                  if (label) label += ': ';
                  if (context.parsed !== null) label += formatCurrency(context.parsed);
                  return label;
                }
              }
            }
          },
          cutout: '60%'
        }
      });
    }
  }

  // Total distribution bar (horizontal)
  if (totalDistributionCanvas.value) {
    const distData = [totalContractorPayout, totalPmPayout, totalPaPayout];
    if (payoutDistributionChartInstance) {
      payoutDistributionChartInstance.data.datasets[0].data = distData;
      payoutDistributionChartInstance.update();
    } else {
      payoutDistributionChartInstance = new Chart(totalDistributionCanvas.value, {
        type: 'bar',
        data: {
          labels: ['Total Contractor Payout', 'Total PM Payout', 'PA On-Time Bonus'],
          datasets: [{
            label: 'Payout by Role (PKR)',
            data: distData,
            backgroundColor: [energeticPalette.blue, energeticPalette.green, energeticPalette.yellow],
            borderColor: [energeticPalette.blue, energeticPalette.green, energeticPalette.yellow],
            borderWidth: 1
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: tooltipTitleCallback,
                label: function(context) {
                  let label = ' Payout: ';
                  if (context.parsed.x !== null) label += formatCurrency(context.parsed.x);
                  return label;
                }
              }
            }
          },
          scales: {
            x: {
              beginAtZero: true,
              ticks: {
                callback: function(value) { return 'PKR ' + (value / 1000) + 'k'; }
              }
            }
          }
        }
      });
    }
  }
};

onMounted(() => {
  calculateEarnings();
});

watch([projectBudget, milestoneBudget, numMilestones, milestonesOnTime, onTimeCheck, userRole], () => {
  calculateEarnings();
});

onBeforeUnmount(() => {
  if (myPayoutChartInstance) {
    myPayoutChartInstance.destroy();
    myPayoutChartInstance = null;
  }
  if (payoutDistributionChartInstance) {
    payoutDistributionChartInstance.destroy();
    payoutDistributionChartInstance = null;
  }
});
</script>

<template>
  <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
    <div class="bg-white rounded-xl shadow-xl p-6 order-1 lg:order-2">
      <h2 class="text-3xl font-bold text-center mb-6">Your Earnings Calculator</h2>
      <p class="text-gray-600 text-center mb-8 max-w-2xl mx-auto">Use this tool to forecast your potential earnings. Adjust the numbers below to see how your payouts change based on project success and efficiency.</p>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label for="userRole" class="block font-semibold text-sm mb-2 text-gray-700">Select Your Role</label>
          <select id="userRole" v-model="userRole" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#00A6ED]">
            <option value="pm">Project Manager (PM)</option>
            <option value="pa">Project Admin (PA)</option>
          </select>
        </div>
        <div>
          <label for="projectBudget" class="block font-semibold text-sm mb-2 text-gray-700">Total Project Budget (PB)</label>
          <input type="number" id="projectBudget" v-model.number="projectBudget" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#00A6ED]">
        </div>
        <div>
          <label for="milestoneBudget" class="block font-semibold text-sm mb-2 text-gray-700">Total Milestone Budgets</label>
          <input type="number" id="milestoneBudget" v-model.number="milestoneBudget" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#00A6ED]">
        </div>
        <div>
          <label for="numMilestones" class="block font-semibold text-sm mb-2 text-gray-700">Total Number of Milestones</label>
          <input type="number" id="numMilestones" v-model.number="numMilestones" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#FFB400]">
        </div>
        <div>
          <label for="milestonesOnTime" class="block font-semibold text-sm mb-2 text-gray-700">Milestones Completed On Time</label>
          <input type="number" id="milestonesOnTime" v-model.number="milestonesOnTime" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#72C544]">
        </div>
        <div class="flex items-center mt-6 col-span-1 md:col-span-2">
          <input type="checkbox" id="onTimeCheck" v-model="onTimeCheck" class="form-checkbox text-[#00A6ED] h-5 w-5 rounded">
          <label for="onTimeCheck" class="ml-2 font-semibold text-sm text-gray-700">Final Project Finished On Time?</label>
        </div>
      </div>

      <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6 text-center">
        <div class="p-4 bg-gray-100 rounded-lg shadow-inner">
          <p class="font-bold text-lg text-[#00A6ED]">PM's Potential Payout</p>
          <p class="text-3xl font-bold text-[#2E2E2E] mt-2">PKR {{ pmPayout }}</p>
        </div>
        <div class="p-4 bg-gray-100 rounded-lg shadow-inner">
          <p class="font-bold text-lg text-[#FFB400]">PA's Potential Payout</p>
          <p class="text-3xl font-bold text-[#2E2E2E] mt-2">PKR {{ paPayout }}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-xl p-6 order-2 lg:order-1">
      <h3 class="text-2xl font-bold text-center mb-4">{{ chartTitle }}</h3>
      <p class="text-gray-600 text-center mb-4">{{ chartSubtitle }}</p>
      <div class="chart-container">
        <canvas ref="myPayoutCanvas"></canvas>
      </div>
    </div>
  </section>

  <section class="bg-white rounded-xl shadow-xl p-6 mt-8">
    <h3 class="text-2xl font-bold text-center mb-4">Total Payout Distribution</h3>
    <p class="text-gray-600 text-center mb-4">This chart visualizes how the total project payouts are distributed among all key roles.</p>
    <div class="chart-container">
      <canvas ref="totalDistributionCanvas"></canvas>
    </div>
  </section>
</template>

<style scoped>
.chart-container {
  position: relative;
  width: 100%;
  max-width: 450px;
  margin-left: auto;
  margin-right: auto;
  height: 320px;
  max-height: 400px;
}
@media (min-width: 768px) {
  .chart-container { height: 400px; }
}
</style>
