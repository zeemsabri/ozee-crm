<template>
    <modal :show="true" @close="$emit('close')">
        <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-xl w-full transform transition-all">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">How would you like to start?</h2>
                <button @click="$emit('close')" class="h-8 w-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                </button>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div @click="$emit('scratch')" class="p-6 bg-slate-50 rounded-xl border border-slate-200 text-center cursor-pointer transition group hover:-translate-y-1 hover:shadow-lg hover:border-oz-blue">
                    <div class="h-16 w-16 rounded-full flex items-center justify-center mx-auto mb-4 transition-transform bg-blue-100 text-blue-700 group-hover:scale-110">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.165 12.165h5.625m-5.625 0a4.5 4.5 0 11-8.325-2.024 4.5 4.5 0 018.325 2.024zM8.25 4.5h7.5m0 0l-3.75 3.75M12 4.5l3.75 3.75" /></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Start from Scratch</h3>
                    <p class="text-sm text-slate-500 mt-1">Begin with a blank presentation and build it your way.</p>
                </div>

                <div @click="$emit('choose-template')" class="p-6 bg-slate-50 rounded-xl border border-slate-200 text-center cursor-pointer transition group hover:-translate-y-1 hover:shadow-lg hover:border-oz-blue">
                    <div class="h-16 w-16 rounded-full flex items-center justify-center mx-auto mb-4 transition-transform bg-amber-100 text-amber-700 group-hover:scale-110">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Use a Template</h3>
                    <p class="text-sm text-slate-500 mt-1">Choose a professionally designed template to get started faster.</p>
                </div>
            </div>
        </div>
    </modal>
</template>

<script setup>
import Modal from './Components/Modal.vue';

defineEmits(['close', 'scratch', 'choose-template']);
</script>

<style scoped>
</style>

