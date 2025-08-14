<script setup>
const props = defineProps({
  employees: { type: Array, required: true },
  formatCurrency: { type: Function, required: true },
});

const toggle = (user) => {
  // mutate reactive object directly (parent provides reactive array)
  user.showDetails = !user.showDetails;
};
</script>

<template>
  <div class="space-y-4">
    <h3 class="text-2xl font-bold text-gray-800">Employee Bonuses</h3>
    <div v-if="employees?.length === 0" class="text-gray-600 p-4 border rounded-lg bg-gray-50">No employee bonuses were calculated for this period.</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="employee in employees" :key="employee.user_id" class="bg-white p-5 rounded-2xl shadow-md transition-all duration-200 ease-in-out border-l-4 border-blue-500 hover:shadow-lg">
        <div class="flex justify-between items-center cursor-pointer" @click="toggle(employee)">
          <div class="flex items-center">
            <div class="flex-1">
              <h4 class="text-xl font-bold text-gray-800">{{ employee.name }}</h4>
              <span v-if="employee.points" class="text-sm font-semibold text-gray-600">{{ employee.points }} pts</span>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <span class="text-sm font-bold text-green-600">{{ formatCurrency(employee.awards.reduce((acc, curr) => acc + (Number(curr.amount) || 0), 0)) }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" :class="{'rotate-180': employee.showDetails}" class="h-5 w-5 text-gray-400 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </div>
        </div>
        <div v-show="employee.showDetails" class="mt-4 border-t border-gray-200 pt-4 space-y-2">
          <div v-for="award in employee.awards" :key="award.award" class="flex justify-between items-center text-sm">
            <span class="font-medium text-gray-700">{{ award.award }}</span>
            <span class="font-bold text-green-600">{{ formatCurrency(award.amount) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
