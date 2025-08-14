<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { usePermissions } from '@/Directives/permissions';

const { canDo } = usePermissions();
const canAccessProjects = canDo('manage_projects');
const canManageUsers = canDo('create_users');
const canManageRoles = canDo('manage_roles');
const canManageTaskTypes = canDo('manage_task_types');
const canAccessClients = canDo('create_clients');
const canManageProjectTiers = canDo('manage_project_tiers') || canDo('view_project_tiers');
</script>

<template>
    <div class="hidden sm:flex sm:items-center">
        <!-- Mega menu using the enhanced Dropdown component -->
        <Dropdown align="left" width="screen" :content-classes="'py-6 bg-white p-6 w-full shadow-lg'">
            <template #trigger>
                <span class="inline-flex rounded-md">
                    <button type="button"
                            class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-700 hover:text-gray-900 focus:outline-none"
                    >
                        Admin
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
                <div class="mx-auto w-full max-w-6xl">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                        <!-- Management Category -->
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Management</h4>
                            <div class="space-y-1">
                                <DropdownLink v-if="canAccessProjects" :href="route('projects.index')" :active="route().current('projects.index')" class="!px-2 !py-1.5">Projects</DropdownLink>
                                <DropdownLink v-if="canAccessClients" :href="route('clients.index')" :active="route().current('clients.index')" class="!px-2 !py-1.5">Clients</DropdownLink>
                                <DropdownLink v-if="canManageUsers" :href="route('users.index')" :active="route().current('users.index')" class="!px-2 !py-1.5">Users</DropdownLink>

                            </div>
                        </div>

                        <!-- Planning Category -->
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Planning / Sharing</h4>
                            <div class="space-y-1">
                                <DropdownLink v-if="canManageUsers" :href="route('availability.index')" :active="route().current('availability.index')" class="!px-2 !py-1.5">Weekly Availability</DropdownLink>
                                <DropdownLink v-if="canDo('manage_notices')" :href="route('admin.notice-board.index')" class="!px-2 !py-1.5">Notice Board</DropdownLink>
                                <DropdownLink v-if="canDo('manage_notices')" :href="route('shareable-resources.index')" class="!px-2 !py-1.5">Shareable Resources</DropdownLink>
                            </div>
                        </div>

                        <!-- Configuration Category -->
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Configuration</h4>
                            <div class="space-y-1">
                                <DropdownLink v-if="canManageTaskTypes" :href="route('task-types.index')" :active="route().current('task-types.index')" class="!px-2 !py-1.5">Task Types</DropdownLink>
                                <DropdownLink v-if="canManageProjectTiers || canDo('create_project_tiers') || canDo('edit_project_tiers')" href="/admin/project-tiers" class="!px-2 !py-1.5">Project Tiers</DropdownLink>
                                <DropdownLink v-if="canDo('manage_email_templates')" :href="route('email-templates.index')" :active="route().current('email-templates.index')" class="!px-2 !py-1.5">Email Templates</DropdownLink>
                                <DropdownLink v-if="canDo('manage_placeholder_definitions')" :href="route('placeholder-definitions.index')" :active="route().current('placeholder-definitions.index')" class="!px-2 !py-1.5">Placeholder Definitions</DropdownLink>
                            </div>
                        </div>

                        <!-- Permissions/Finance Category -->
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Access & Finance</h4>
                            <div class="space-y-1">
                                <DropdownLink v-if="canManageRoles" :href="route('admin.roles.index')" class="!px-2 !py-1.5">Manage Roles</DropdownLink>
                                <DropdownLink v-if="canDo('manage_permissions')" :href="route('admin.permissions.index')" class="!px-2 !py-1.5">Manage Permissions</DropdownLink>
                                <DropdownLink v-if="canDo('manage_monthly_budgets')" href="/admin/monthly-budgets" class="!px-2 !py-1.5">Monthly Budgets</DropdownLink>
                                <DropdownLink v-if="canDo('view_monthly_budgets')" href="/admin/bonus-calculator" class="!px-2 !py-1.5">Bonus Calculator</DropdownLink>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Dropdown>
    </div>
</template>
