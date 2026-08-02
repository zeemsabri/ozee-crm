<template>
    <div class="bg-gray-900 text-white py-2 overflow-hidden flex whitespace-nowrap">
        <div class="animate-marquee flex space-x-8 items-center pl-4">
            <span class="font-bold text-sm tracking-widest text-gray-400">LIVE FX (vs USD):</span>
            <div v-for="(rate, currency) in rates" :key="currency" class="flex items-center space-x-2">
                <span class="font-semibold text-gray-300">{{ currency }}</span>
                <span class="text-green-400 font-mono">{{ formatRate(rate) }}</span>
            </div>
            <!-- Duplicate for seamless looping -->
            <div v-for="(rate, currency) in rates" :key="'dup-'+currency" class="flex items-center space-x-2">
                <span class="font-semibold text-gray-300">{{ currency }}</span>
                <span class="text-green-400 font-mono">{{ formatRate(rate) }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    rates: {
        type: Object,
        required: true
    }
});

const formatRate = (rate) => {
    return Number(rate).toFixed(4);
};
</script>

<style scoped>
.animate-marquee {
    animation: marquee 500s linear infinite;
}
.animate-marquee:hover {
    animation-play-state: paused;
}

@keyframes marquee {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
</style>
