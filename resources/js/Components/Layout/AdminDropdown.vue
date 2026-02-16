<script setup>
import { ref, reactive, onMounted } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const showCategoriesModal = ref(false);
const isLoading = ref(false);

const state = reactive({
    sets: [],
    models: [],
    // Create Category form
    categoryName: '',
    setMode: 'existing', // 'existing' | 'new'
    selectedSetId: null,
    newSetName: '',
    newSetModels: [], // array of model FQCNs
    // Create Set form
    setName: '',
    setModels: [],
    errors: {},
});

const openCategoriesModal = async () => {
    showCategoriesModal.value = true;
    await Promise.all([fetchSets(), fetchModels()]);
};

const fetchSets = async () => {
    try {
        const { data } = await window.axios.get('/api/category-sets');
        state.sets = data.map(s => ({ value: s.id, label: s.name, raw: s }));
    } catch (e) {
        console.error('Failed to load sets', e);
    }
};

const fetchModels = async () => {
    try {
        const { data } = await window.axios.get('/api/models/available');
        state.models = data; // [{value: fqcn, label: name}]
    } catch (e) {
        console.error('Failed to load models', e);
    }
};

const resetErrors = () => { state.errors = {}; };

const createSet = async () => {
    resetErrors();
    isLoading.value = true;
    try {
        const payload = { name: state.setName, allowed_models: state.setModels };
        const { data } = await window.axios.post('/api/category-sets', payload);
        // Refresh sets and clear form
        await fetchSets();
        state.setName = '';
        state.setModels = [];
    } catch (e) {
        if (e.response?.status === 422) {
            state.errors = e.response.data.errors || {};
        }
    } finally {
        isLoading.value = false;
    }
};

const createCategory = async () => {
    resetErrors();
    isLoading.value = true;
    try {
        const payload = { name: state.categoryName };
        if (state.setMode === 'existing') {
            payload.category_set_id = state.selectedSetId;
        } else {
            payload.new_set_name = state.newSetName;
            payload.allowed_models = state.newSetModels;
        }
        const { data } = await window.axios.post('/api/categories', payload);
        // Refresh sets for display and clear form
        await fetchSets();
        state.categoryName = '';
        state.selectedSetId = null;
        state.newSetName = '';
        state.newSetModels = [];
        state.setMode = 'existing';
    } catch (e) {
        if (e.response?.status === 422) {
            state.errors = e.response.data.errors || {};
        }
    } finally {
        isLoading.value = false;
    }
};
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
                                <DropdownLink v-permission="'manage_projects'" :href="route('projects.index')" :active="route().current('projects.index')" class="!px-2 !py-1.5">Projects</DropdownLink>
                                <DropdownLink v-permission="'create_clients'" :href="route('clients.page')" :active="route().current('clients.page')" class="!px-2 !py-1.5">Clients</DropdownLink>
                                <DropdownLink v-permission="'create_users'" :href="route('users.page')" :active="route().current('users.page')" class="!px-2 !py-1.5">Users</DropdownLink>
                                <DropdownLink v-permission="'manage_projects'" :href="route('leads.page')" :active="route().current('leads.page')" class="!px-2 !py-1.5">Leads</DropdownLink>
                                <DropdownLink v-permission="'manage_projects'" href="/campaigns" :active="$page.url && $page.url.startsWith('/campaigns')" class="!px-2 !py-1.5">Campaigns</DropdownLink>
                                <DropdownLink v-permission="'manage_projects'" href="/admin/productivity" :active="$page.url && $page.url.startsWith('/productivity')" class="!px-2 !py-1.5">Productivity Report</DropdownLink>
                                <DropdownLink v-permission="'manage_projects'" :href="route('admin.activity-report.index')" :active="route().current('admin.activity-report.index')" class="!px-2 !py-1.5">Activity Report</DropdownLink>
                            </div>
                        </div>

                        <!-- Planning Category -->
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Planning / Sharing</h4>
                            <div class="space-y-1">
                                <DropdownLink v-permission="'create_users'" :href="route('availability.index')" :active="route().current('availability.index')" class="!px-2 !py-1.5">Weekly Availability</DropdownLink>
                                <DropdownLink v-permission="'manage_notices'" :href="route('admin.notice-board.index')" class="!px-2 !py-1.5">Notice Board</DropdownLink>
                                <DropdownLink v-permission="'view_shareable_resources'" :href="route('shareable-resources.page')" class="!px-2 !py-1.5">Shareable Resources</DropdownLink>
                            </div>
                        </div>

                        <!-- Configuration Category -->
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Configuration</h4>
                            <div class="space-y-1">
                                <DropdownLink v-permission="'manage_projects'" :href="route('task-types.page')" :active="route().current('task-types.page')" class="!px-2 !py-1.5">Task Types</DropdownLink>
                                <DropdownLink v-permission="'view_project_tiers'" href="/admin/project-tiers" class="!px-2 !py-1.5">Project Tiers</DropdownLink>
                                <DropdownLink v-permission="'manage_email_templates'" :href="route('email-templates.page')" :active="route().current('email-templates.page')" class="!px-2 !py-1.5">Email Templates</DropdownLink>
                                <DropdownLink v-permission="'manage_placeholder_definitions'" :href="route('placeholder-definitions.page')" :active="route().current('placeholder-definitions.page')" class="!px-2 !py-1.5">Placeholder Definitions</DropdownLink>
                                <DropdownLink v-permission="'create_automations'" :href="route('automation.page')" :active="route().current('automation.page')" class="!px-2 !py-1.5">Automation</DropdownLink>
                                <DropdownLink v-permission="'create_automations'" :href="route('prompts.page')" :active="route().current('prompts.page')" class="!px-2 !py-1.5">Prompts</DropdownLink>
                                <DropdownLink v-permission="'create_schedules'" :href="route('schedules.index')" :active="$page.url && $page.url.startsWith('/schedules')" class="!px-2 !py-1.5">Schedules</DropdownLink>
                                <DropdownLink :href="route('admin.categories.index')" class="!px-2 !py-1.5">Categories</DropdownLink>
                                <button type="button" class="!px-2 !py-1.5 text-left text-sm text-gray-700 hover:text-gray-900" @click="openCategoriesModal">Quick Add</button>
                            </div>
                        </div>

                        <!-- Permissions/Finance Category -->
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Access & Finance</h4>
                            <div class="space-y-1">
                                <DropdownLink v-permission="'manage_roles'" :href="route('admin.roles.index')" class="!px-2 !py-1.5">Manage Roles</DropdownLink>
                                <DropdownLink v-permission="'assign_permissions'" :href="route('admin.permissions.index')" class="!px-2 !py-1.5">Manage Permissions</DropdownLink>
                                <DropdownLink v-permission="'manage_monthly_budgets'" href="/admin/monthly-budgets" class="!px-2 !py-1.5">Monthly Budgets</DropdownLink>
                                <DropdownLink v-permission="'view_monthly_budgets'" href="/admin/bonus-calculator" class="!px-2 !py-1.5">Bonus Calculator</DropdownLink>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Dropdown>
    </div>

    <!-- Manage Categories Modal -->
    <Modal :show="showCategoriesModal" @close="showCategoriesModal = false" maxWidth="3xl">
        <div class="p-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Manage Categories</h2>
                <button @click="showCategoriesModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Create Set -->
                <div class="border rounded-lg p-4">
                    <h3 class="text-sm font-semibold mb-3">Create Category Set</h3>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Set Name" />
                            <TextInput v-model="state.setName" class="w-full mt-1" />
                            <InputError :message="state.errors?.name?.[0]" />
                        </div>
                        <div>
                            <InputLabel value="Allowed Models (optional)" />
                            <MultiSelectDropdown
                                :options="state.models"
                                v-model="state.setModels"
                                :isMulti="true"
                                placeholder="Select models"
                            />
                        </div>
                        <div class="flex justify-end">
                            <PrimaryButton type="button" @click="createSet" :disabled="isLoading">Create Set</PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- Create Category -->
                <div class="border rounded-lg p-4">
                    <h3 class="text-sm font-semibold mb-3">Create Category</h3>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Category Name" />
                            <TextInput v-model="state.categoryName" class="w-full mt-1" />
                            <InputError :message="state.errors?.name?.[0]" />
                        </div>
                        <div class="flex items-center gap-3 mt-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" value="existing" v-model="state.setMode" /> Use Existing Set
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" value="new" v-model="state.setMode" /> Create New Set
                            </label>
                        </div>
                        <div v-if="state.setMode === 'existing'">
                            <InputLabel value="Select Set" />
                            <SelectDropdown
                                :options="state.sets"
                                v-model="state.selectedSetId"
                                placeholder="Choose a set"
                            />
                            <InputError :message="state.errors?.category_set_id?.[0]" />
                        </div>
                        <div v-else>
                            <InputLabel value="New Set Name" />
                            <TextInput v-model="state.newSetName" class="w-full mt-1" />
                            <InputError :message="state.errors?.new_set_name?.[0]" />
                            <div class="mt-3">
                                <InputLabel value="Allowed Models (optional)" />
                                <MultiSelectDropdown
                                    :options="state.models"
                                    v-model="state.newSetModels"
                                    :isMulti="true"
                                    placeholder="Select models"
                                />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <PrimaryButton type="button" @click="createCategory" :disabled="isLoading">Create Category</PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
