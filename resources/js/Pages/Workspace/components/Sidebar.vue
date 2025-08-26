<script setup>
import { ref, onMounted, watch } from 'vue';
import ChecklistComponent from "@/Components/ChecklistComponent.vue";
const props = defineProps({
    checklistItems: Array,
    notes: String,
});

const emits = defineEmits(['add-checklist-item', 'remove-checklist-item', 'update-notes']);

const newChecklistItemInput = ref(null);

function addItem() {
    const text = newChecklistItemInput.value?.value?.trim();
    if (text) {
        emits('add-checklist-item', text);
        newChecklistItemInput.value.value = '';
        newChecklistItemInput.value.focus();
    }
}

function removeItem(index) {
    emits('remove-checklist-item', index);
}

function updateNotes(event) {
    emits('update-notes', event.target.value);
}
</script>

<template>
    <div>
        <!-- My Performance -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">My Performance</h3>
            <div class="flex items-center justify-between mb-4">
                <div class="flex-1">
                    <p class="text-sm text-gray-500">Total Points</p>
                    <span class="text-5xl font-bold text-indigo-600">1,450</span>
                </div>
                <div class="flex-1 text-right">
                    <p class="text-sm text-gray-500">Leaderboard Rank</p>
                    <span class="text-5xl font-bold text-gray-900">#8</span>
                </div>
            </div>
            <a href="#" class="block text-center text-indigo-600 text-sm font-medium mt-4 hover:underline transition-all-colors">View Leaderboard Details →</a>
        </div>

        <!-- My Checklist -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">My Checklist</h3>
<!--            <ChecklistComponent-->
<!--                :items="[]"-->
<!--                :api-endpoint="`/api/user-checklist/${deliverable.id}`"-->
<!--                title="Deliverable Checklist:"-->
<!--                :payload-transformer="(items, index) => ({-->
<!--                                ...deliverable,-->
<!--                                details: {-->
<!--                                    ...deliverable.details,-->
<!--                                    checklist: items-->
<!--                                }-->
<!--                            })"-->
<!--                @item-toggled="(data) => $emit('checklist-item-toggled', {-->
<!--                                deliverable,-->
<!--                                index: data.index,-->
<!--                                completed: data.completed-->
<!--                            })"-->
<!--            />-->

            <h3 class="text-xl font-semibold text-gray-900 mb-4 mt-6">My Notes</h3>
            <div class="space-y-3">
                <textarea :value="notes" @input="updateNotes" class="w-full h-32 p-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Write your notes here..."></textarea>
            </div>
        </div>

        <!-- Company Updates -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">Company Updates</h3>
            <ul class="space-y-3">
                <li class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-800 font-medium">Company-wide meeting at 3 PM</p>
                    <p class="text-xs text-gray-500 mt-1">Don't miss the Q3 planning session.</p>
                </li>
                <li class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-800 font-medium">New HR policy document available</p>
                    <p class="text-xs text-gray-500 mt-1">Please review the updated remote work guidelines.</p>
                </li>
            </ul>
        </div>
    </div>
</template>
