<script setup>
import { onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import Chart from 'chart.js/auto';

const pmPayout = ref(0);
const paPayout = ref(0);
const myPayoutChartInstance = ref(null);
const payoutDistributionChartInstance = ref(null);

const calculateEarnings = () => {
    const projectBudgetInput = document.getElementById('projectBudget');
    const milestoneBudgetInput = document.getElementById('milestoneBudget');
    const numMilestonesInput = document.getElementById('numMilestones');
    const milestonesOnTimeInput = document.getElementById('milestonesOnTime');
    const onTimeCheckbox = document.getElementById('onTimeCheck');
    const userRoleSelect = document.getElementById('userRole');

    const pb = parseFloat(projectBudgetInput.value) || 0;
    const totalMb = parseFloat(milestoneBudgetInput.value) || 0;
    const numMilestones = parseInt(numMilestonesInput.value) || 0;
    const milestonesOnTime = parseInt(milestonesOnTimeInput.value) || 0;
    const onTime = onTimeCheckbox.checked;

    const pmBase = pb * 0.10;
    const contractorPayments = totalMb;
    let pmPerformanceBonus = 0;
    if (numMilestones > 0) {
        const avgMilestoneBudget = totalMb / numMilestones;
        pmPerformanceBonus = (avgMilestoneBudget * 0.05) * milestonesOnTime;
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

    updateCharts(totalPmPayout, totalPaPayout, totalContractorPayout);
};

const updateCharts = (pmPayout, paPayout, contractorPayout) => {
    const userRole = document.getElementById('userRole').value;
    const myPayoutChartTitle = document.getElementById('myPayoutChartTitle');
    const myPayoutChartText = document.getElementById('myPayoutChartText');

    if (myPayoutChartInstance.value) {
        myPayoutChartInstance.value.destroy();
    }
    if (payoutDistributionChartInstance.value) {
        payoutDistributionChartInstance.value.destroy();
    }

    const energeticPalette = {
        blue: '#00A6ED',
        green: '#72C544',
        yellow: '#FFB400',
        red: '#F2545B',
        darkGray: '#2E2E2E'
    };

    const tooltipTitleCallback = function(tooltipItems) {
        const item = tooltipItems[0];
        let label = item.chart.data.labels[item.dataIndex];
        if (Array.isArray(label)) {
            return label.join(' ');
        } else {
            return label;
        }
    };

    if (userRole === 'pm') {
        const pmBase = parseFloat(document.getElementById('projectBudget').value) * 0.10;
        const totalMb = parseFloat(document.getElementById('milestoneBudget').value);
        const numMilestones = parseInt(document.getElementById('numMilestones').value);
        const milestonesOnTime = parseInt(document.getElementById('milestonesOnTime').value);
        let pmPerformanceBonus = 0;
        if (numMilestones > 0) {
            const avgMilestoneBudget = totalMb / numMilestones;
            pmPerformanceBonus = (avgMilestoneBudget * 0.05) * milestonesOnTime;
        }

        const onTime = document.getElementById('onTimeCheck').checked;
        let pmOnTimeBonus = 0;
        const remainingBalance = totalMb > 0 ? (parseFloat(document.getElementById('projectBudget').value) - (pmBase + totalMb + pmPerformanceBonus + (totalMb * 0.05))) : (parseFloat(document.getElementById('projectBudget').value) - pmBase);
        if (onTime && remainingBalance > 0) {
            pmOnTimeBonus = remainingBalance * 0.20;
        }

        myPayoutChartTitle.textContent = "My PM Earning Breakdown";
        myPayoutChartText.textContent = "This chart shows how my total potential payout as a Project Manager is composed, with all payouts distributed at the end of the project.";

        const myCtx = document.getElementById('myPayoutChart');
        myPayoutChartInstance.value = new Chart(myCtx, {
            type: 'doughnut',
            data: {
                labels: ['Base Allocation', 'Milestone Bonus', 'On-Time Bonus'],
                datasets: [{
                    label: 'My Payout',
                    data: [pmBase, pmPerformanceBonus, pmOnTimeBonus],
                    backgroundColor: [
                        energeticPalette.blue,
                        energeticPalette.yellow,
                        energeticPalette.green
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            title: tooltipTitleCallback,
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PKR', minimumFractionDigits: 0 }).format(context.parsed);
                                }
                                return label;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

    } else if (userRole === 'pa') {
        const onTime = document.getElementById('onTimeCheck').checked;
        const pb = parseFloat(document.getElementById('projectBudget').value) || 0;
        const totalMb = parseFloat(document.getElementById('milestoneBudget').value) || 0;
        const numMilestones = parseInt(document.getElementById('numMilestones').value) || 0;
        const milestonesOnTime = parseInt(document.getElementById('milestonesOnTime').value) || 0;

        const pmBase = pb * 0.10;
        const contractorPayments = totalMb;
        let pmPerformanceBonus = 0;
        if (numMilestones > 0) {
            const avgMilestoneBudget = totalMb / numMilestones;
            pmPerformanceBonus = (avgMilestoneBudget * 0.05) * milestonesOnTime;
        }
        const contractorBonus = totalMb * 0.05;

        const remainingBalance = pb - (pmBase + contractorPayments + pmPerformanceBonus + contractorBonus);
        let paOnTimeBonus = 0;

        if (onTime && remainingBalance > 0) {
            paOnTimeBonus = remainingBalance * 0.20;
        }

        myPayoutChartTitle.textContent = "My PA Earning Breakdown";
        myPayoutChartText.textContent = "This chart shows how my total potential payout as a Project Admin is composed, with all payouts distributed at the end of the project.";

        const myCtx = document.getElementById('myPayoutChart');
        myPayoutChartInstance.value = new Chart(myCtx, {
            type: 'doughnut',
            data: {
                labels: ['On-Time Delivery Bonus'],
                datasets: [{
                    label: 'My Payout',
                    data: [paOnTimeBonus],
                    backgroundColor: [
                        energeticPalette.green
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            title: tooltipTitleCallback,
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PKR', minimumFractionDigits: 0 }).format(context.parsed);
                                }
                                return label;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    }

    const payoutDistributionCtx = document.getElementById('payoutDistributionChart');
    payoutDistributionChartInstance.value = new Chart(payoutDistributionCtx, {
        type: 'bar',
        data: {
            labels: ['Total Contractor Payout', 'Total PM Payout', 'PA On-Time Bonus'],
            datasets: [{
                label: 'Payout by Role (PKR)',
                data: [contractorPayout, pmPayout, paPayout],
                backgroundColor: [
                    energeticPalette.blue,
                    energeticPalette.green,
                    energeticPalette.yellow
                ],
                borderColor: [
                    energeticPalette.blue,
                    energeticPalette.green,
                    energeticPalette.yellow
                ],
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        title: tooltipTitleCallback,
                        label: function(context) {
                            let label = ' Payout: ';
                            if (context.parsed.x !== null) {
                                label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PKR', minimumFractionDigits: 0 }).format(context.parsed.x);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'PKR ' + (value / 1000) + 'k';
                        }
                    }
                }
            }
        }
    });
};

onMounted(() => {
    calculateEarnings();

    const projectBudgetInput = document.getElementById('projectBudget');
    const milestoneBudgetInput = document.getElementById('milestoneBudget');
    const numMilestonesInput = document.getElementById('numMilestones');
    const milestonesOnTimeInput = document.getElementById('milestonesOnTime');
    const onTimeCheckbox = document.getElementById('onTimeCheck');
    const userRoleSelect = document.getElementById('userRole');

    projectBudgetInput.addEventListener('input', calculateEarnings);
    milestoneBudgetInput.addEventListener('input', calculateEarnings);
    numMilestonesInput.addEventListener('input', calculateEarnings);
    milestonesOnTimeInput.addEventListener('input', calculateEarnings);
    onTimeCheckbox.addEventListener('change', calculateEarnings);
    userRoleSelect.addEventListener('change', calculateEarnings);
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="PM Payout Calculator" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-2xl text-gray-800">PM Payout Calculator</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-12xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <!-- Infographic Content -->
                        <div class="container mx-auto p-4 md:p-8">
                            <header class="text-center mb-12">
                                <h1 class="text-4xl md:text-5xl font-extrabold text-[#2E2E2E] mb-2">Your Project Incentive System</h1>
                                <p class="text-lg text-gray-600 max-w-3xl mx-auto">A focused overview and calculator for Project Managers and Project Admins to understand and forecast their earnings based on a fixed Project Budget (PB).</p>
                            </header>

                            <main class="space-y-16">
                                <section class="text-center bg-white rounded-xl shadow-2xl p-8">
                                    <h2 class="text-2xl font-bold text-[#00A6ED] mb-2">Total Project Budget (PB)</h2>
                                    <p class="text-gray-600 mb-4">All payouts and bonuses are calculated from a single, fixed budget. The example below shows a common scenario.</p>
                                    <div class="text-7xl font-black text-[#2E2E2E]">
                                        PKR 30,000
                                    </div>
                                </section>

                                <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
                                    <div class="bg-white rounded-xl shadow-xl p-6 order-1 lg:order-2">
                                        <h2 class="text-2xl font-bold text-center mb-4">Your Earnings Calculator</h2>
                                        <p class="text-gray-600 text-center mb-8 max-w-2xl mx-auto">Use this tool to forecast your potential earnings. Adjust the numbers below to see how your payouts change based on project success and efficiency.</p>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label for="userRole" class="block font-semibold text-sm mb-2 text-gray-700">Select Your Role</label>
                                                <select id="userRole" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#00A6ED]">
                                                    <option value="pm">Project Manager (PM)</option>
                                                    <option value="pa">Project Admin (PA)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="projectBudget" class="block font-semibold text-sm mb-2 text-gray-700">Total Project Budget (PB)</label>
                                                <input type="number" id="projectBudget" value="30000" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#00A6ED]">
                                            </div>
                                            <div>
                                                <label for="milestoneBudget" class="block font-semibold text-sm mb-2 text-gray-700">Total Milestone Budgets</label>
                                                <input type="number" id="milestoneBudget" value="13000" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#00A6ED]">
                                            </div>
                                            <div>
                                                <label for="numMilestones" class="block font-semibold text-sm mb-2 text-gray-700">Total Number of Milestones</label>
                                                <input type="number" id="numMilestones" value="5" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#FFB400]">
                                            </div>
                                            <div>
                                                <label for="milestonesOnTime" class="block font-semibold text-sm mb-2 text-gray-700">Milestones Completed On Time</label>
                                                <input type="number" id="milestonesOnTime" value="5" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#72C544]">
                                            </div>
                                            <div class="flex items-center mt-6 col-span-1 ">
                                                <input type="checkbox" id="onTimeCheck" checked class="form-checkbox text-[#00A6ED] h-5 w-5 rounded">
                                                <label for="onTimeCheck" class="ml-2 font-semibold text-sm text-gray-700">Final Project Finished On Time?</label>
                                            </div>
                                        </div>

                                        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6 text-center">
                                            <div class="p-4 bg-gray-100 rounded-lg shadow-inner">
                                                <p class="font-bold text-lg text-[#00A6ED]">PM's Potential Payout</p>
                                                <p id="pmPayout" class="text-3xl font-bold text-[#2E2E2E] mt-2">PKR {{ pmPayout }}</p>
                                            </div>
                                            <div class="p-4 bg-gray-100 rounded-lg shadow-inner">
                                                <p class="font-bold text-lg text-[#FFB400]">PA's Potential Payout</p>
                                                <p id="paPayout" class="text-3xl font-bold text-[#2E2E2E] mt-2">PKR {{ paPayout }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white rounded-xl shadow-xl p-6 order-2 lg:order-1">
                                        <h3 id="myPayoutChartTitle" class="text-2xl font-bold text-center mb-4">My Earning Breakdown</h3>
                                        <p id="myPayoutChartText" class="text-gray-600 text-center mb-8 max-w-2xl mx-auto">This chart shows how your total potential payout is composed, with all payouts distributed at the end of the project.</p>
                                        <div class="chart-container">
                                            <canvas id="myPayoutChart"></canvas>
                                        </div>
                                    </div>
                                </section>

                                <section class="bg-white rounded-xl shadow-xl p-6">
                                    <h3 class="text-2xl font-bold text-center mb-4">Total Payout Distribution</h3>
                                    <p class="text-gray-600 text-center mb-4">This chart visualizes how the total project payouts are distributed among all key roles.</p>
                                    <div class="chart-container">
                                        <canvas id="payoutDistributionChart"></canvas>
                                    </div>
                                </section>

                                <section class="mt-16">
                                    <h2 class="text-3xl font-bold text-center mb-8">The Journey to Payout</h2>
                                    <div class="flex flex-col md:flex-row items-center justify-center space-y-8 md:space-y-0 md:space-x-8">
                                        <div class="flex flex-col items-center">
                                            <div class="text-center p-6 flow-card-bg rounded-xl shadow-md flow-card-border">
                                                <h4 class="font-bold text-lg text-[#00A6ED]">1. Project Begins</h4>
                                                <p class="text-gray-600 mt-2">All payouts are set to be accrued, with final distribution at the end of the project.</p>
                                            </div>
                                            <div class="md:hidden mt-4 flow-connector"></div>
                                        </div>
                                        <div class="hidden md:block flow-connector"></div>
                                        <div class="flex flex-col items-center">
                                            <div class="text-center p-6 flow-card-bg rounded-xl shadow-md flow-card-border">
                                                <h4 class="font-bold text-lg text-[#00A6ED]">2. Milestones Completed</h4>
                                                <p class="text-gray-600 mt-2">PM and Contractors accrue their 5% bonuses as each milestone is approved.</p>
                                            </div>
                                            <div class="md:hidden mt-4 flow-connector"></div>
                                        </div>
                                        <div class="hidden md:block flow-connector"></div>
                                        <div class="flex flex-col items-center">
                                            <div class="text-center p-6 flow-card-bg rounded-xl shadow-md flow-card-border">
                                                <h4 class="font-bold text-lg text-[#00A6ED]">3. On-Time Delivery?</h4>
                                                <p class="text-gray-600 mt-2">The final payouts depend on whether the project is completed by the deadline.</p>
                                            </div>
                                            <div class="md:hidden mt-4 flow-connector"></div>
                                        </div>
                                        <div class="hidden md:block flow-connector"></div>
                                        <div class="flex flex-col items-center space-y-4">
                                            <div class="text-center p-4 flow-card-bg rounded-lg shadow-md flow-card-success-border">
                                                <h4 class="font-bold text-lg text-[#72C544]">Yes: Full Payout</h4>
                                                <p class="text-gray-600">PM & PA get their 20% bonus. All payouts are distributed.</p>
                                            </div>
                                            <div class="text-center p-4 flow-card-bg rounded-lg shadow-md flow-card-fail-border">
                                                <h4 class="font-bold text-lg text-[#F2545B]">No: Partial Payout</h4>
                                                <p class="text-gray-600">The on-time bonuses are not paid out. Other payouts are distributed.</p>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="mt-16">
                                    <h2 class="text-3xl font-bold text-center mb-8">Payout Scenarios</h2>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                        <div class="bg-white rounded-xl shadow-xl p-6 text-center border-t-4 flow-card-success-border">
                                            <div class="text-5xl mb-4">💰</div>
                                            <h3 class="text-2xl font-bold mb-2">Full Payout</h3>
                                            <p class="text-gray-600">This occurs when all project milestones are completed successfully and the project is delivered on or before the deadline. All bonuses are paid.</p>
                                        </div>
                                        <div class="bg-white rounded-xl shadow-xl p-6 text-center border-t-4 flow-card-fail-border">
                                            <div class="text-5xl mb-4">😥</div>
                                            <h3 class="text-2xl font-bold mb-2">Partial Payout</h3>
                                            <p class="text-gray-600">A partial payout happens if the project is completed but is delivered late. The PM and PA do not receive their 20% on-time completion bonus.</p>
                                        </div>
                                        <div class="bg-white rounded-xl shadow-xl p-6 text-center border-t-4 border-[#FFB400]">
                                            <div class="text-5xl mb-4">❌</div>
                                            <h3 class="text-2xl font-bold mb-2">No Payout</h3>
                                            <p class="text-gray-600">A team member receives no bonus if their specific conditions aren't met, such as a contractor's work being rejected or the project being canceled entirely.</p>
                                        </div>
                                    </div>
                                </section>
                            </main>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
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
    .chart-container {
        height: 400px;
    }
}
.flow-card-bg {
    background-color: #F8F8F8;
}
.flow-card-border {
    border-top: 4px solid;
    border-color: #00A6ED;
}
.flow-card-success-border {
    border-color: #72C544;
}
.flow-card-fail-border {
    border-color: #F2545B;
}
.flow-connector {
    width: 2rem;
    height: 2px;
    background-color: #FFB400;
}
@media (min-width: 768px) {
    .flow-connector {
        width: 2px;
        height: 2rem;
    }
}
</style>
