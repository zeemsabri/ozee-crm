<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
        <!-- Header -->
        <div class="p-5 border-b border-gray-100 flex justify-between items-start bg-gray-50/50">
            <div>
                <h3 class="text-lg font-bold text-gray-900 line-clamp-1">{{ data.project.name }}</h3>
                <p class="text-xs text-gray-500 mt-1">ID: {{ data.project.id }}</p>
            </div>
            <div v-if="data.unpaid_balance_alert" title="Cash out exceeds cash in" class="bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full flex items-center shadow-sm">
                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                Cash Deficit
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="p-5 grid grid-cols-2 gap-4">
            <!-- Accrual Profit -->
            <div class="col-span-2 bg-gray-50 rounded-lg p-4 border border-gray-100">
                <p class="text-sm text-gray-500 font-medium mb-1">Accrual Net Profit</p>
                <div class="flex items-end justify-between">
                    <p class="text-2xl font-bold" :class="data.net_profit_aud >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                        {{ baseCurrency }} {{ formatCurrency(data.net_profit_aud) }}
                    </p>
                    <div class="flex flex-col items-end">
                        <span class="text-xs font-semibold px-2 py-1 rounded" :class="data.margin_percent >= 20 ? 'bg-emerald-100 text-emerald-800' : (data.margin_percent > 0 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800')">
                            {{ data.margin_percent }}% Margin
                        </span>
                    </div>
                </div>
            </div>

            <!-- Invoiced vs Costs (Accrual) -->
            <div>
                <p class="text-xs text-gray-500 mb-1">Total Invoiced (Accrual)</p>
                <p class="text-lg font-semibold text-gray-900">{{ baseCurrency }} {{ formatCurrency(data.invoiced_aud) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">Total Costs (Accrual)</p>
                <p class="text-lg font-semibold text-gray-900">{{ baseCurrency }} {{ formatCurrency(data.costs_aud) }}</p>
            </div>
            
            <div class="col-span-2 border-t border-gray-100 my-1"></div>

            <!-- Cash In vs Cash Out -->
            <div>
                <p class="text-xs text-gray-400 mb-1">Cash Received</p>
                <p class="text-sm font-medium text-gray-700">{{ baseCurrency }} {{ formatCurrency(data.cash_in_aud) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 mb-1">Cash Paid Out</p>
                <p class="text-sm font-medium text-gray-700">{{ baseCurrency }} {{ formatCurrency(data.cash_out_aud) }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    data: {
        type: Object,
        required: true
    },
    baseCurrency: {
        type: String,
        default: 'AUD'
    }
});

const formatCurrency = (val) => {
    return Number(val).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
