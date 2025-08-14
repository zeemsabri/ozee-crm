<script setup>
const props = defineProps({
  contractors: { type: Array, required: true },
  formatCurrency: { type: Function, required: true },
});

const toggle = (user) => {
  user.showDetails = !user.showDetails;
};
</script>

<template>
  <div class="space-y-4">
    <h3 class="text-2xl font-bold text-gray-800">Contractor Bonuses</h3>
    <div v-if="contractors?.length === 0" class="text-gray-600 p-4 border rounded-lg bg-gray-50">No contractor bonuses were calculated for this period.</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="contractor in contractors" :key="contractor.user_id" class="bg-white p-5 rounded-2xl shadow-md transition-all duration-200 ease-in-out border-l-4 border-teal-500 hover:shadow-lg">
        <div class="flex justify-between items-center cursor-pointer" @click="toggle(contractor)">
          <div class="flex items-center">
            <div class="flex-1">
              <h4 class="text-xl font-bold text-gray-800">{{ contractor.name }}</h4>
              <span v-if="contractor.points" class="text-sm font-semibold text-gray-600">{{ contractor.points }} pts</span>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <span class="text-sm font-bold text-green-600">{{ formatCurrency(contractor.awards.reduce((acc, curr) => acc + (Number(curr.amount) || 0), 0)) }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" :class="{'rotate-180': contractor.showDetails}" class="h-5 w-5 text-gray-400 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </div>
        </div>
        <div v-show="contractor.showDetails" class="mt-4 border-t border-gray-200 pt-4 space-y-2">
          <div v-for="award in contractor.awards" :key="award.award" class="flex flex-col items-start text-sm">
            <div class="flex justify-between w-full items-center">
              <span class="font-medium text-gray-700">{{ award.award }}</span>
              <span class="font-bold text-green-600">{{ formatCurrency(award.amount) }}</span>
            </div>
            <p v-if="award.details" class="text-xs text-gray-500 mt-1">{{ award.details }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
