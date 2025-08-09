<script setup>
import { computed } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { usePage } from '@inertiajs/vue3';

const user = computed(() => usePage().props.auth.user);

const emit = defineEmits(['logoutSuccess', 'logoutError']);

const handleLogoutSuccess = () => {
    localStorage.removeItem('authToken');
    localStorage.removeItem('userRole');
    localStorage.removeItem('userId');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('remembered');
    delete window.axios.defaults.headers.common['Authorization'];
    emit('logoutSuccess');
};

const handleLogoutError = (error) => {
    localStorage.removeItem('authToken');
    localStorage.removeItem('userRole');
    localStorage.removeItem('userId');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('remembered');
    delete window.axios.defaults.headers.common['Authorization'];
    window.location.href = '/login';
    emit('logoutError', error);
};
</script>

<template>
    <div class="relative ms-3">
        <Dropdown align="right" width="48">
            <template #trigger>
                <span class="inline-flex rounded-md">
                    <button type="button"
                            class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                    >
                        {{ user.name }} ({{ user.role_data?.name }})

                        <svg class="ms-2 -me-0.5 h-4 w-4"
                             xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 20 20"
                             fill="currentColor"
                        >
                            <path fill-rule="evenodd"
                                  d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                  clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                </span>
            </template>

            <template #content>
                <DropdownLink :href="route('profile.edit')">
                    Profile
                </DropdownLink>
                <DropdownLink :href="route('logout')" method="post" as="button" @success="handleLogoutSuccess" @error="handleLogoutError">
                    Log Out
                </DropdownLink>
            </template>
        </Dropdown>
    </div>
</template>
