<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
</script>

<template>
    <div class="hidden sm:flex sm:items-center">
        <Dropdown align="left" width="screen" :content-classes="'py-6 bg-white p-6 w-full shadow-lg'">
            <template #trigger>
                <span class="inline-flex rounded-md">
                    <button type="button"
                            class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-700 hover:text-gray-900 focus:outline-none"
                    >
                        Bonus
                        <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </span>
            </template>
            <template #content>
                <div class="mx-auto w-full max-w-6xl">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-8">
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Program</h4>
                            <div class="space-y-1">
                                <DropdownLink :href="route('bonus-system.index')" :active="route().current('bonus-system.index')" class="!px-2 !py-1.5">Bonus System</DropdownLink>
                                <DropdownLink :href="route('leaderboard.index')" :active="route().current('leaderboard.index')" class="!px-2 !py-1.5">Leaderboard</DropdownLink>
                                <DropdownLink :href="route('kudos.index')" :active="route().current('kudos.index')" class="!px-2 !py-1.5">Kudos</DropdownLink>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Dropdown>
    </div>
</template>
