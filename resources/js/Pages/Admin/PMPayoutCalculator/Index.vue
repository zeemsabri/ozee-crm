<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PMPayoutCalculator from '@/Components/PMPayoutCalculator.vue';
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

                                <PMPayoutCalculator />

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
                                                <p class="text-gray-600">The on-time bonuses are not paid out. All other payouts are distributed.</p>
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
