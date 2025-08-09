<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { usePermissions } from '@/Directives/permissions';

const { canDo } = usePermissions();
const canAccessProjects = canDo('manage_projects');
const canComposeEmails = canDo('compose_emails');
const canApproveEmails = canDo('approve_emails');
const canManageUsers = canDo('create_users');
const canManageRoles = canDo('manage_roles');
const canManageTaskTypes = canDo('manage_task_types');
const canAccessClients = canDo('create_clients');
const canManageProjectTiers = canDo('manage_project_tiers') || canDo('view_project_tiers');
</script>

<template>
    <div class="hidden sm:flex sm:items-center">
        <Dropdown align="right" width="48">
            <template #trigger>
                <span class="inline-flex rounded-md">
                    <button type="button"
                            class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
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
                <DropdownLink v-if="canAccessProjects" :href="route('projects.index')" :active="route().current('projects.index')">
                    Projects
                </DropdownLink>
                <DropdownLink v-if="canManageUsers" :href="route('availability.index')" :active="route().current('availability.index')">
                    Weekly Availability
                </DropdownLink>
                <DropdownLink v-if="canAccessClients" :href="route('clients.index')" :active="route().current('clients.index')">
                    Clients
                </DropdownLink>
                <DropdownLink v-if="canManageUsers" :href="route('users.index')" :active="route().current('users.index')">
                    Users
                </DropdownLink>
                <DropdownLink v-if="canManageTaskTypes" :href="route('task-types.index')" :active="route().current('task-types.index')">
                    Task Types
                </DropdownLink>
                <DropdownLink v-if="canDo('manage_email_templates')" :href="route('email-templates.index')" :active="route().current('email-templates.index')">
                    Email Templates
                </DropdownLink>
                <DropdownLink v-if="canDo('manage_placeholder_definitions')" :href="route('placeholder-definitions.index')" :active="route().current('placeholder-definitions.index')">
                    Placeholder Definitions
                </DropdownLink>
                <DropdownLink v-if="canDo('manage_roles')" :href="route('admin.roles.index')">
                    Manage Roles
                </DropdownLink>
                <DropdownLink v-if="canDo('manage_permissions')" :href="route('admin.permissions.index')">
                    Manage Permissions
                </DropdownLink>
                <DropdownLink v-if="canManageProjectTiers" href="/admin/project-tiers">
                    Project Tiers
                </DropdownLink>
            </template>
        </Dropdown>
    </div>
</template>
