<script setup>
import { ref, computed } from 'vue';

const effortGuideMode = ref('developer');

// --- Developer Calculator Logic ---
const calcDifficulty = ref(1);
const calcScope = ref(1);

const calculatedPoints = computed(() => {
    if (effortGuideMode.value === 'admin') {
        const vol = taskVolume.value;
        const pts = vol === 1 ? 1 : (vol === 2 ? 2 : (vol === 3 ? 3 : 5));
        return pts;
    }
    
    // Developer Logic
    const raw = calcDifficulty.value * calcScope.value;
    const fib = [1, 2, 3, 5, 8, 13];
    return fib.reduce((prev, curr) => {
        return (Math.abs(curr - raw) < Math.abs(prev - raw) ? curr : prev);
    });
});

// --- Admin Calculator Logic ---
const taskVolume = ref(1);
const taskVolumeOptions = [
    { value: 1, label: 'Quick Task / Reply (< 1 hr)' },
    { value: 2, label: 'Routine / Notes (1-2 hrs)' },
    { value: 3, label: 'Half Day / Focus (3-4 hrs)' },
    { value: 4, label: 'Full Day / Complex (5+ hrs)' },
];

</script>

<template>
    <div class="space-y-6 text-sm text-gray-700">
        <!-- Role Toggle -->
        <div class="flex justify-center bg-gray-100 p-1 rounded-lg mb-4">
            <button 
                @click="effortGuideMode = 'developer'"
                class="flex-1 py-1 text-xs font-semibold rounded-md transition-colors"
                :class="effortGuideMode === 'developer' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            >
                Developer
            </button>
            <button 
                @click="effortGuideMode = 'admin'"
                class="flex-1 py-1 text-xs font-semibold rounded-md transition-colors"
                :class="effortGuideMode === 'admin' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            >
                Admin / Staff
            </button>
        </div>

        <!-- Developer Content -->
        <template v-if="effortGuideMode === 'developer'">
            <section>
                <h4 class="font-bold text-gray-900 text-lg mb-2">What are Story Points?</h4>
                <p class="mb-2">Story points estimate the <span class="font-semibold">effort</span> required to implement a task. They consider:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li><span class="font-semibold">Complexity:</span> How difficult is the logic?</li>
                    <li><span class="font-semibold">Risk:</span> How much uncertainty is there?</li>
                    <li><span class="font-semibold">Repetition:</span> How tedious is the work?</li>
                </ul>
            </section>

            <section class="bg-indigo-50 p-4 rounded-lg border border-indigo-100">
                <h4 class="font-bold text-indigo-900 text-md mb-3">Quick Reference</h4>
                <div class="grid grid-cols-1 gap-3">
                    <div class="flex gap-3 items-start border-b border-indigo-200 pb-2">
                        <span class="bg-indigo-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">1</span>
                        <div>
                            <span class="font-bold block text-indigo-900">Tiny / Trivial</span>
                            <span class="text-xs">Typo fix, color change, simple config. &lt; 1 hour.</span>
                        </div>
                    </div>
                    <div class="flex gap-3 items-start border-b border-indigo-200 pb-2">
                        <span class="bg-indigo-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">2</span>
                        <div>
                            <span class="font-bold block text-indigo-900">Small / Routine</span>
                            <span class="text-xs">Add field, update text, standard bug fix. 1-4 hours.</span>
                        </div>
                    </div>
                    <div class="flex gap-3 items-start border-b border-indigo-200 pb-2">
                        <span class="bg-indigo-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">3</span>
                        <div>
                            <span class="font-bold block text-indigo-900">Medium</span>
                            <span class="text-xs">New simple feature, standard page dev, component logic. 4-8 hours (1 day).</span>
                        </div>
                    </div>
                    <div class="flex gap-3 items-start border-b border-indigo-200 pb-2">
                        <span class="bg-indigo-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">5</span>
                        <div>
                            <span class="font-bold block text-indigo-900">Large</span>
                            <span class="text-xs">Complex feature, integration, heavy logic. 2-3 days.</span>
                        </div>
                    </div>
                    <div class="flex gap-3 items-start">
                        <span class="bg-yellow-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">8+</span>
                        <div>
                            <span class="font-bold block text-yellow-900">Ex-Large (Break Down!)</span>
                            <span class="text-xs">Full module, major refactor. Consider splitting into smaller subtasks. 3-5+ days.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-t pt-4">
                <h4 class="font-bold text-gray-900 text-md mb-3">Estimation Calculator</h4>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Difficulty (1-5)</label>
                        <input type="range" min="1" max="5" v-model.number="calcDifficulty" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Easy</span>
                            <span>Expert</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Scope/Uncertainty (1-3)</label>
                        <div class="flex gap-2">
                            <button @click="calcScope = 1" :class="calcScope === 1 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="flex-1 py-1 rounded text-xs">Clear</button>
                            <button @click="calcScope = 2" :class="calcScope === 2 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="flex-1 py-1 rounded text-xs">Vague</button>
                            <button @click="calcScope = 3" :class="calcScope === 3 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="flex-1 py-1 rounded text-xs">Unknown</button>
                        </div>
                    </div>
                    <div class="bg-gray-900 text-white p-4 rounded-lg text-center mt-4">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Suggested Points</div>
                        <div class="text-4xl font-bold text-indigo-400">{{ calculatedPoints }}</div>
                    </div>
                </div>
            </section>
        </template>

        <!-- Admin / Staff Content -->
        <template v-else>
            <section>
                <h4 class="font-bold text-gray-900 text-lg mb-2">Admin / Staff Points</h4>
                <p class="mb-2">For non-technical tasks, use <span class="font-semibold">Time & Volume</span> to estimate points.</p>
            </section>

            <section class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                <h4 class="font-bold text-blue-900 text-md mb-3">Reference Table</h4>
                <div class="grid grid-cols-1 gap-3">
                    <div class="flex gap-3 items-start border-b border-blue-200 pb-2">
                        <span class="bg-blue-600 text-white font-bold w-12 h-6 flex items-center justify-center rounded px-2 text-xs shrink-0">1 pt</span>
                        <div>
                            <span class="font-bold block text-blue-900">Quick Task</span>
                            <span class="text-xs">Replies, emails, simple checks. &lt; 1 hour.</span>
                        </div>
                    </div>
                    <div class="flex gap-3 items-start border-b border-blue-200 pb-2">
                        <span class="bg-blue-600 text-white font-bold w-12 h-6 flex items-center justify-center rounded px-2 text-xs shrink-0">2 pts</span>
                        <div>
                            <span class="font-bold block text-blue-900">Routine Task</span>
                            <span class="text-xs">Meeting minutes, detailed responses. 1-2 hours.</span>
                        </div>
                    </div>
                    <div class="flex gap-3 items-start border-b border-blue-200 pb-2">
                        <span class="bg-blue-600 text-white font-bold w-12 h-6 flex items-center justify-center rounded px-2 text-xs shrink-0">3 pts</span>
                        <div>
                            <span class="font-bold block text-blue-900">Half Day</span>
                            <span class="text-xs">Deep focus admin work, organization. 3-4 hours.</span>
                        </div>
                    </div>
                    <div class="flex gap-3 items-start">
                        <span class="bg-blue-600 text-white font-bold w-12 h-6 flex items-center justify-center rounded px-2 text-xs shrink-0">5 pts</span>
                        <div>
                            <span class="font-bold block text-blue-900">Full Day</span>
                            <span class="text-xs">Complex analysis, long meetings/planning. 5+ hours.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-t pt-4">
                <h4 class="font-bold text-gray-900 text-md mb-3">Calculator</h4>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Task Type / Volume</label>
                        <select v-model.number="taskVolume" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="opt in taskVolumeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div class="bg-gray-900 text-white p-4 rounded-lg text-center mt-4">
                        <div class="text-xs uppercase tracking-wide text-gray-400">Suggested Points</div>
                        <div class="text-4xl font-bold text-blue-400">{{ calculatedPoints }}</div>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
