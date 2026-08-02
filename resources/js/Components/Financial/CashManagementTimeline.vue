<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-6">Upcoming Cash Flow (Next 30 Days)</h3>
            
            <div class="space-y-6 relative">
                <!-- Timeline Line -->
                <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>

                <div v-if="combinedTimeline.length === 0" class="text-center py-8 text-gray-500">
                    No upcoming bills or expected invoices.
                </div>
                
                <div v-for="(item, index) in combinedTimeline" :key="index" class="relative pl-10">
                    <!-- Icon Indicator -->
                    <div class="absolute left-1.5 -translate-x-1/2 top-1 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center"
                         :class="item.type === 'invoice' ? 'bg-emerald-500' : 'bg-rose-500'">
                        <svg v-if="item.type === 'invoice'" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <svg v-else class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                    </div>
                    
                    <!-- Content Card -->
                    <div class="bg-gray-50 border border-gray-100 rounded-lg p-4 group relative">
                        <!-- Action Pin -->
                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="$emit('addInstruction', item)" class="p-1.5 bg-white text-blue-600 rounded shadow hover:bg-blue-50 border border-blue-100" title="Add Instruction">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>
                        </div>
                        
                        <div class="flex justify-between items-start mb-2 pr-8">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider" :class="item.type === 'invoice' ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ item.type === 'invoice' ? 'Expected Inward' : 'Due Outward' }}
                                </span>
                                <h4 class="text-sm font-bold text-gray-900 mt-1">{{ item.project }}</h4>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold" :class="item.type === 'invoice' ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ item.type === 'invoice' ? '+' : '-' }}A$ {{ formatCurrency(item.amount_aud) }}
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5">({{ item.currency }} {{ formatCurrency(item.amount) }})</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center text-xs text-gray-500 mt-3 pt-3 border-t border-gray-200">
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span class="font-medium text-gray-700">{{ formatDate(item.due_date) }}</span>
                                <span v-if="item.type === 'invoice'" class="ml-2 px-2 py-0.5 bg-gray-200 rounded text-gray-700">
                                    {{ item.days_since_generation }} days old
                                </span>
                            </div>
                            <span class="capitalize px-2 py-1 rounded bg-gray-200 text-gray-700 font-medium">
                                {{ item.status.replace('_', ' ') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['addInstruction']);

const combinedTimeline = computed(() => {
    const bills = props.data.upcoming_bills || [];
    const invoices = props.data.expected_invoices || [];
    
    // Combine and sort by due date ascending
    const combined = [...bills, ...invoices].sort((a, b) => {
        return new Date(a.due_date) - new Date(b.due_date);
    });
    
    return combined;
});

const formatCurrency = (val) => {
    return Number(val).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('en-AU', {
        weekday: 'short',
        month: 'short', 
        day: 'numeric'
    });
};
</script>
