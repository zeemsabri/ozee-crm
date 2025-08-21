<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Compare and Manage Role Permissions
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <!-- Role Selector -->
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Select up to 3 roles</h3>
                            <div class="max-w-3xl">
                                <MultiSelectDropdown
                                    v-model="selectedRoleIds"
                                    :options="roleOptions"
                                    :isMulti="true"
                                    placeholder="Select roles (max 3)"
                                    @change="handleRoleChange"
                                />
                                <p v-if="selectError" class="text-sm text-red-600 mt-2">{{ selectError }}</p>
                            </div>
                        </div>

                        <!-- Permissions Grid -->
                        <div v-if="Object.keys(groupedPermissions).length === 0" class="text-sm text-gray-500 p-4 bg-gray-50 rounded-lg">
                            No permissions available. Please create some permissions first.
                        </div>

                        <div v-else>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">
                                                Permission
                                            </th>
                                            <th v-for="(role, idx) in selectedRoles" :key="role.id" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                {{ role.name }}
                                            </th>
                                            <!-- Fill empty columns to keep layout consistent -->
                                            <th v-for="i in (3 - selectedRoles.length)" :key="'empty-col-'+i" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                                Role {{ selectedRoles.length + i }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <!-- Render by category with a category row -->
                                        <template v-for="(permissions, category) in groupedPermissions" :key="category">
                                            <tr class="bg-gray-100">
                                                <td class="px-6 py-2 text-sm font-semibold text-gray-700" :colspan="1 + Math.max(3, selectedRoles.length)">
                                                    {{ category }}
                                                </td>
                                            </tr>
                                            <tr v-for="perm in permissions" :key="perm.id">
                                                <td class="px-6 py-3 text-sm text-gray-900">
                                                    <div class="font-medium">{{ perm.name }}</div>
                                                    <div v-if="perm.description" class="text-xs text-gray-500">{{ perm.description }}</div>
                                                </td>

                                                <!-- Checkbox cells for selected roles -->
                                                <td v-for="role in selectedRoles" :key="role.id + '-' + perm.id" class="px-6 py-3">
                                                    <label class="inline-flex items-center space-x-2">
                                                        <input
                                                            type="checkbox"
                                                            class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                                            :checked="hasPermission(role.id, perm.id)"
                                                            :disabled="isPending(role.id, perm.id)"
                                                            @change="togglePermission(role.id, perm.id)"
                                                        />
                                                        <span v-if="isPending(role.id, perm.id)" class="text-xs text-gray-500">Saving...</span>
                                                    </label>
                                                </td>

                                                <!-- Empty cells to complete up to 3 columns -->
                                                <td v-for="i in (3 - selectedRoles.length)" :key="perm.id + '-empty-' + i" class="px-6 py-3 text-gray-300">
                                                    —
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <Link :href="route('admin.roles.index')" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                                Back to Roles
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    roles: Array,                // All roles with permissions eager loaded
    permissions: Object          // Grouped by category
});

// Dropdown data
const roleOptions = computed(() => (props.roles || []).map(r => ({ value: r.id, label: r.name })));
const selectedRoleIds = ref([]);
const selectError = ref('');

// Enforce max 3 selections
const handleRoleChange = (values) => {
    selectError.value = '';
    if (Array.isArray(values) && values.length > 3) {
        // Keep first 3
        selectedRoleIds.value = values.slice(0, 3);
        selectError.value = 'You can select a maximum of 3 roles.';
    } else {
        selectedRoleIds.value = values;
    }
};

// Helper: map roleId -> Set(permissionIds)
const rolePerms = reactive({});

const initRolePerms = () => {
    (props.roles || []).forEach(r => {
        rolePerms[r.id] = new Set((r.permissions || []).map(p => p.id));
    });
};
initRolePerms();

// Selected role objects
const selectedRoles = computed(() => (props.roles || []).filter(r => selectedRoleIds.value?.includes(r.id)));

// Grouped permissions passthrough
const groupedPermissions = computed(() => props.permissions || {});

// Pending state per cell: key `${roleId}:${permId}`
const pending = reactive({});
const keyFor = (roleId, permId) => `${roleId}:${permId}`;
const isPending = (roleId, permId) => !!pending[keyFor(roleId, permId)];

const hasPermission = (roleId, permId) => {
    return !!rolePerms[roleId] && rolePerms[roleId].has(permId);
};

const togglePermission = async (roleId, permId) => {
    if (!rolePerms[roleId]) {
        rolePerms[roleId] = new Set();
    }

    const k = keyFor(roleId, permId);
    if (pending[k]) return;

    // Optimistic toggle
    const currentlyHas = rolePerms[roleId].has(permId);
    if (currentlyHas) {
        rolePerms[roleId].delete(permId);
    } else {
        rolePerms[roleId].add(permId);
    }

    pending[k] = true;
    try {
        // Send updated full permission list for this role
        const permissionsArray = Array.from(rolePerms[roleId]);
        await axios.post(`/api/roles/${roleId}/permissions`, { permissions: permissionsArray });
    } catch (e) {
        // Revert on error
        if (currentlyHas) {
            rolePerms[roleId].add(permId);
        } else {
            rolePerms[roleId].delete(permId);
        }
        console.error('Failed to update permission:', e);
    } finally {
        pending[k] = false;
    }
};

// Keep internal sets in sync if props.roles updated by Inertia navigation
watch(() => props.roles, () => initRolePerms(), { deep: true });
</script>
