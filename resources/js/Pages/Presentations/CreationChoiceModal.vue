<template>
    <modal :show="true" @close="$emit('close')">
        <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-xl w-full transform transition-all">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">How would you like to start?</h2>
                <button @click="$emit('close')" class="icon-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                </button>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div @click="$emit('scratch')" class="choice-card group">
                    <div class="choice-card-icon bg-blue-100 text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.165 12.165h5.625m-5.625 0a4.5 4.5 0 11-8.325-2.024 4.5 4.5 0 018.325 2.024zM8.25 4.5h7.5m0 0l-3.75 3.75M12 4.5l3.75 3.75" /></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Start from Scratch</h3>
                    <p class="text-sm text-slate-500 mt-1">Begin with a blank presentation and build it your way.</p>
                </div>

                <div @click="$emit('choose-template')" class="choice-card group">
                    <div class="choice-card-icon bg-amber-100 text-amber-700">
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
.choice-card {
    padding: 1.5rem;
    background-color: #f8fafc; /* slate-50 */
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0; /* slate-200 */
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}
.choice-card:hover {
    border-color: #29438E; /* oz-blue */
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    transform: translateY(-4px);
}
.choice-card-icon {
    height: 4rem;
    width: 4rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    transition: transform 0.2s ease-in-out;
}
.group:hover .choice-card-icon {
    transform: scale(1.1);
}
.icon-btn {
    height: 2rem;
    width: 2rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8; /* slate-400 */
    transition: background-color 0.2s, color 0.2s;
}
.icon-btn:hover {
    background-color: #f1f5f9; /* slate-100 */
    color: #475569; /* slate-600 */
}
</style>

