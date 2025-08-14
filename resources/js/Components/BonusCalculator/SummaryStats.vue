<script setup>
const props = defineProps({
  result: { type: Object, required: true },
  employeeTotalBonus: { type: Number, required: true },
  employeeTotalPoints: { type: Number, required: true },
  employeeCostPerPoint: { type: Number, required: true },
  contractorTotalBonus: { type: Number, required: true },
  contractorProjectBreakdown: { type: Array, required: true },
  formatCurrency: { type: Function, required: true },
});
</script>

<template>
  <div class="p-6 rounded-xl bg-gradient-to-br from-indigo-50 to-white shadow-lg">
    <!-- Top row: Period and Total Budget -->
    <div class="flex justify-between items-center mb-4">
      <div>
        <p class="text-sm font-medium text-gray-500">Bonus Period</p>
        <p class="text-3xl font-bold text-gray-800">{{ result.period }}</p>
      </div>
      <div class="text-right">
        <p class="text-sm font-medium text-gray-500">Total Budget</p>
        <p class="text-3xl font-bold text-gray-800">{{ formatCurrency(result.total_budget, 0) }}</p>
      </div>
    </div>

    <!-- Middle row: Employee and Contractor Pools -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
      <!-- Employee Pool -->
      <div class="bg-white p-6 rounded-lg shadow-sm border border-blue-200">
        <h3 class="text-lg font-semibold text-blue-600 mb-2">Employee Pool</h3>
        <p class="text-3xl font-bold text-blue-600 mb-4">{{ formatCurrency(result.employee_pool_allocated) }}</p>
        <div class="space-y-2 text-sm text-gray-600">
          <div class="flex justify-between">
            <span class="font-medium">Total Bonus Amount</span>
            <span class="font-bold">{{ formatCurrency(employeeTotalBonus) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="font-medium">Total Points</span>
            <span class="font-bold">{{ employeeTotalPoints.toFixed(0) }} pts</span>
          </div>
          <div class="flex justify-between">
            <span class="font-medium">Cost Per Point</span>
            <span class="font-bold">{{ formatCurrency(employeeCostPerPoint, 2) }}</span>
          </div>
        </div>
      </div>

      <!-- Contractor Pool -->
      <div class="bg-white p-6 rounded-lg shadow-sm border border-teal-200">
        <h3 class="text-lg font-semibold text-teal-600 mb-2">Contractor Pool</h3>
        <p class="text-3xl font-bold text-teal-600 mb-4">{{ formatCurrency(result.contractor_pool_allocated) }}</p>
        <div class="space-y-2 text-sm text-gray-600">
          <div class="flex justify-between">
            <span class="font-medium">Total Bonus Amount</span>
            <span class="font-bold">{{ formatCurrency(contractorTotalBonus) }}</span>
          </div>
          <div class="mt-4">
            <p class="font-medium text-gray-700">Project Performance Bonus Breakdown</p>
            <div v-if="contractorProjectBreakdown.length > 0" class="space-y-1 mt-2 pl-4 border-l border-gray-200">
              <div v-for="item in contractorProjectBreakdown" :key="item.project" class="flex justify-between items-center text-xs">
                <span class="truncate">{{ item.project }}</span>
                <span class="font-semibold text-green-600">{{ formatCurrency(item.amount) }}</span>
              </div>
            </div>
            <div v-else class="text-xs italic text-gray-500 mt-2">No project bonuses to display.</div>
          </div>
          <div class="flex justify-between mt-4 border-t pt-2">
            <span class="font-medium">Unallocated Pool</span>
            <span class="font-bold">{{ formatCurrency(result.contractor_metrics.project_performance_bonus_pool.amount) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
