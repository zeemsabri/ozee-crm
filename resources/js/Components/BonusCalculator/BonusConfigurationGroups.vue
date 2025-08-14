<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';

// State
const bonusConfigurationGroups = ref([]);
const bonusConfigurations = ref([]);
const editingGroupId = ref(null);
const showCreateModal = ref(false);
const showDeleteModal = ref(false);
const groupToDelete = ref(null);
const uiMessage = ref({ text: '', type: '' }); // type: 'success', 'error', 'info'
const loading = ref(false); // Loading state for API calls

// Form state
const groupForm = ref({
    name: '',
    description: '',
    is_active: true,
    configurations: []
});

// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        console.log('Auth headers set in BonusConfigurationGroups');
    }
};

// UI Message handling
const showMessage = (text, type = 'info') => {
    uiMessage.value = { text, type };
    setTimeout(() => {
        uiMessage.value = { text: '', type: '' };
    }, 4000); // Clear after 4 seconds
};

// API calls
const fetchBonusConfigurationGroups = async () => {
    loading.value = true;
    try {
        ensureAuthHeaders();
        console.log('Fetching bonus configuration groups...');
        const response = await axios.get('/api/bonus-configuration-groups');
        console.log('API Response:', response);

        // Log detailed information about the response data
        if (response.data && Array.isArray(response.data)) {
            console.log('Number of groups:', response.data.length);
            response.data.forEach((group, index) => {
                console.log(`Group ${index + 1}:`, group);
                console.log(`Group ${index + 1} configurations:`, group.bonus_configurations || group.bonusConfigurations);
            });
        }

        bonusConfigurationGroups.value = response.data;
        console.log('Bonus configuration groups after assignment:', bonusConfigurationGroups.value);

        // Check if configurations are properly loaded
        bonusConfigurationGroups.value.forEach((group, index) => {
            console.log(`Group ${index + 1} configurations (configurations):`, group.configurations);
            console.log(`Group ${index + 1} configurations (bonusConfigurations):`, group.bonusConfigurations);

            // Log which property is being used
            const usedConfigs = group.configurations || group.bonusConfigurations || [];
            console.log(`Group ${index + 1} configurations (used):`, usedConfigs);
            console.log(`Group ${index + 1} has ${usedConfigs.length} configurations`);
        });
    } catch (error) {
        console.error('Error fetching bonus configuration groups:', error);
        showMessage('Failed to load bonus configuration groups.', 'error');
    } finally {
        loading.value = false;
    }
};

const fetchBonusConfigurations = async () => {
    loading.value = true;
    try {
        ensureAuthHeaders();
        const response = await axios.get('/api/bonus-configurations');
        bonusConfigurations.value = response.data;
    } catch (error) {
        console.error('Error fetching bonus configurations:', error);
        showMessage('Failed to load bonus configurations.', 'error');
    } finally {
        loading.value = false;
    }
};

const saveGroup = async () => {
    loading.value = true;
    try {
        ensureAuthHeaders();

        if (!groupForm.value.name) {
            showMessage('Group Name is required.', 'error');
            loading.value = false;
            return;
        }

        let response;
        if (editingGroupId.value) {
            // Update existing group
            response = await axios.put(`/api/bonus-configuration-groups/${editingGroupId.value}`, groupForm.value);
            showMessage(`Updated ${groupForm.value.name} group.`, 'success');
        } else {
            // Create new group
            response = await axios.post('/api/bonus-configuration-groups', groupForm.value);
            showMessage(`Created ${groupForm.value.name} group.`, 'success');
        }

        // Close modal and refresh the list
        showCreateModal.value = false;
        fetchBonusConfigurationGroups();

        // Reset form
        resetForm();
    } catch (error) {
        console.error('Error saving bonus configuration group:', error);
        showMessage('Failed to save bonus configuration group.', 'error');
    } finally {
        loading.value = false;
    }
};

const deleteGroup = async () => {
    if (!groupToDelete.value) return;

    loading.value = true;
    try {
        ensureAuthHeaders();
        await axios.delete(`/api/bonus-configuration-groups/${groupToDelete.value.id}`);
        showMessage(`Deleted ${groupToDelete.value.name} group.`, 'success');

        // Close modal and refresh the list
        showDeleteModal.value = false;
        fetchBonusConfigurationGroups();

        // Reset
        groupToDelete.value = null;
    } catch (error) {
        console.error('Error deleting bonus configuration group:', error);
        showMessage('Failed to delete bonus configuration group.', 'error');
    } finally {
        loading.value = false;
    }
};

const duplicateGroup = async (group) => {
    loading.value = true;
    try {
        ensureAuthHeaders();
        const response = await axios.post(`/api/bonus-configuration-groups/${group.id}/duplicate`);
        showMessage(`Duplicated ${group.name} group.`, 'success');

        // Refresh the list
        fetchBonusConfigurationGroups();
    } catch (error) {
        console.error('Error duplicating bonus configuration group:', error);
        showMessage('Failed to duplicate bonus configuration group.', 'error');
    } finally {
        loading.value = false;
    }
};

// Form handlers
const openCreateModal = async (group = null) => {
    // Ensure configurations are loaded before opening the modal
    if (bonusConfigurations.value.length === 0) {
        await fetchBonusConfigurations();
    }

    if (group) {
        // Edit existing group
        editingGroupId.value = group.id;

        // Get configurations from either property name for backward compatibility
        const groupConfigs = group.configurations || group.bonusConfigurations || [];

        groupForm.value = {
            name: group.name,
            description: group.description || '',
            is_active: group.is_active,
            configurations: groupConfigs.map(config => config.id)
        };

        console.log('Editing group:', group);
        console.log('Group configurations (configurations):', group.configurations);
        console.log('Group configurations (bonusConfigurations):', group.bonusConfigurations);
        console.log('Group configurations (used):', groupConfigs);
        console.log('Form configurations:', groupForm.value.configurations);
    } else {
        // Create new group
        resetForm();
    }

    showCreateModal.value = true;
};

const openDeleteModal = (group) => {
    groupToDelete.value = group;
    showDeleteModal.value = true;
};

const resetForm = () => {
    editingGroupId.value = null;
    groupForm.value = {
        name: '',
        description: '',
        is_active: true,
        configurations: []
    };
};

// Get configuration name by ID
const getConfigurationName = (configId) => {
    const config = bonusConfigurations.value.find(c => c.id === configId);
    return config ? config.name : 'Unknown Configuration';
};

// Create a sample group with predefined configurations
const createSampleGroup = async () => {
    loading.value = true;
    try {
        ensureAuthHeaders();

        // First, check if we have any bonus configurations
        await fetchBonusConfigurations();

        if (bonusConfigurations.value.length === 0) {
            // Create sample configurations first
            const sampleConfigs = [
                {
                    name: "Daily Standup Bonus",
                    type: "bonus",
                    amountType: "percentage",
                    value: 5,
                    appliesTo: "standup",
                    targetBonusTypeForRevocation: "",
                    isActive: true,
                    uuid: crypto.randomUUID()
                },
                {
                    name: "Late Task Penalty",
                    type: "penalty",
                    amountType: "fixed",
                    value: 10,
                    appliesTo: "late_task",
                    targetBonusTypeForRevocation: "",
                    isActive: true,
                    uuid: crypto.randomUUID()
                }
            ];

            // Create each configuration
            const configIds = [];
            for (const config of sampleConfigs) {
                const response = await axios.post('/api/bonus-configurations', {
                    ...config,
                    id: config.uuid
                });
                configIds.push(response.data.id);
            }

            // Now create the group with these configurations
            const groupResponse = await axios.post('/api/bonus-configuration-groups', {
                name: "Standard Bonus Package",
                description: "A standard set of bonuses and penalties for projects",
                is_active: true,
                configurations: configIds
            });

            showMessage("Sample bonus configuration group created successfully!", "success");

            // Refresh the list
            fetchBonusConfigurationGroups();
        } else {
            // We have configurations, just create a group with them
            const configIds = bonusConfigurations.value.slice(0, 2).map(config => config.id);

            const groupResponse = await axios.post('/api/bonus-configuration-groups', {
                name: "Standard Bonus Package",
                description: "A standard set of bonuses and penalties for projects",
                is_active: true,
                configurations: configIds
            });

            showMessage("Sample bonus configuration group created successfully!", "success");

            // Refresh the list
            fetchBonusConfigurationGroups();
        }
    } catch (error) {
        console.error('Error creating sample group:', error);
        showMessage('Failed to create sample group.', 'error');
    } finally {
        loading.value = false;
    }
};

// Lifecycle hooks
onMounted(() => {
    ensureAuthHeaders();
    fetchBonusConfigurationGroups();
    fetchBonusConfigurations();
});
</script>

<template>
    <div class="min-h-screen bg-gray-100 font-sans text-gray-800">
        <!-- UI Message Display -->
        <div v-if="uiMessage.text"
             :class="`p-3 rounded-md mb-4 text-center font-medium ${
                 uiMessage.type === 'success' ? 'bg-green-100 text-green-700' :
                 uiMessage.type === 'error' ? 'bg-red-100 text-red-700' :
                 'bg-blue-100 text-blue-700'
             }`">
            {{ uiMessage.text }}
        </div>

        <!-- Header with Create Button -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-blue-600">Bonus Configuration Groups</h2>
            <PrimaryButton
                @click="openCreateModal()"
                class="transform hover:scale-105"
                :disabled="loading"
                :class="{ 'opacity-50 cursor-not-allowed': loading }"
            >
                Create New Group
            </PrimaryButton>
        </div>

        <!-- Groups List -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <!-- Loading indicator -->
            <div v-if="loading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mb-4"></div>
                <p class="text-gray-600">Loading bonus configuration groups...</p>
            </div>

            <!-- Empty state -->
            <div v-else-if="bonusConfigurationGroups.length === 0" class="text-center py-6">
                <p class="text-gray-500 mb-4">No bonus configuration groups defined yet.</p>
                <p class="text-sm text-gray-600 mb-6">Bonus configuration groups allow you to organize and manage related bonus configurations together.</p>
                <div class="flex justify-center space-x-4">
                    <PrimaryButton
                        @click="openCreateModal()"
                        class="mx-auto"
                        :disabled="loading"
                        :class="{ 'opacity-50 cursor-not-allowed': loading }"
                    >
                        Create Your First Group
                    </PrimaryButton>
                    <SecondaryButton
                        @click="createSampleGroup"
                        class="mx-auto"
                        :disabled="loading"
                        :class="{ 'opacity-50 cursor-not-allowed': loading }"
                    >
                        Create Sample Group
                    </SecondaryButton>
                </div>
            </div>

            <!-- Groups list -->
            <div v-else class="space-y-4">
                <div v-for="group in bonusConfigurationGroups" :key="group.id"
                     class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-medium text-blue-700">{{ group.name }}</h3>
                            <p v-if="group.description" class="text-sm text-gray-600 mt-1">{{ group.description }}</p>

                            <!-- Configurations in this group -->
                            <div class="mt-3">
                                <h4 class="text-sm font-medium text-gray-700 mb-1">Configurations:</h4>
                                <ul class="pl-5 list-disc text-sm">
                                    <li v-for="config in (group.configurations || group.bonusConfigurations || [])" :key="config.id"
                                        :class="config.type === 'bonus' ? 'text-green-600' : 'text-red-600'">
                                        {{ config.name }}
                                    </li>
                                    <li v-if="(!group.configurations || group.configurations.length === 0) &&
                                              (!group.bonusConfigurations || group.bonusConfigurations.length === 0)"
                                        class="text-gray-500">
                                        No configurations in this group
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex space-x-2">
                            <button @click="openCreateModal(group)"
                                    class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded text-sm"
                                    :disabled="loading"
                                    :class="{ 'opacity-50 cursor-not-allowed hover:bg-blue-100': loading }">
                                Edit
                            </button>
                            <button @click="duplicateGroup(group)"
                                    class="bg-purple-100 hover:bg-purple-200 text-purple-700 px-3 py-1 rounded text-sm"
                                    :disabled="loading"
                                    :class="{ 'opacity-50 cursor-not-allowed hover:bg-purple-100': loading }">
                                Duplicate
                            </button>
                            <button @click="openDeleteModal(group)"
                                    class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded text-sm"
                                    :disabled="loading"
                                    :class="{ 'opacity-50 cursor-not-allowed hover:bg-red-100': loading }">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    {{ editingGroupId ? 'Edit Group' : 'Create New Group' }}
                </h2>

                <div class="space-y-4">
                    <!-- Name Field -->
                    <div>
                        <InputLabel for="name" value="Group Name" />
                        <TextInput id="name" v-model="groupForm.name" type="text" class="mt-1 block w-full" required />
                    </div>

                    <!-- Description Field -->
                    <div>
                        <InputLabel for="description" value="Description (Optional)" />
                        <textarea id="description" v-model="groupForm.description"
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="3"></textarea>
                    </div>

                    <!-- Active Status -->
                    <div class="flex items-center">
                        <input id="is_active" type="checkbox" v-model="groupForm.is_active"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">Active</label>
                    </div>

                    <!-- Configurations Multi-select -->
                    <div>
                        <InputLabel value="Configurations" />
                        <div class="mt-1 border border-gray-300 rounded-md p-2 max-h-60 overflow-y-auto">
                            <div v-for="config in bonusConfigurations" :key="config.id" class="flex items-center py-1">
                                <input :id="`config-${config.id}`" type="checkbox"
                                       :value="config.id" v-model="groupForm.configurations"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <label :for="`config-${config.id}`" class="ml-2 block text-sm text-gray-900">
                                    {{ config.name }}
                                    <span :class="config.type === 'bonus' ? 'text-green-600' : 'text-red-600'">
                                        ({{ config.type === 'bonus' ? 'Bonus' : 'Penalty' }})
                                    </span>
                                </label>
                            </div>
                            <div v-if="bonusConfigurations.length === 0" class="text-gray-500 text-sm py-2">
                                No configurations available. Please create some first.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton
                        @click="showCreateModal = false"
                        :disabled="loading"
                        :class="{ 'opacity-50 cursor-not-allowed': loading }"
                    >
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton
                        @click="saveGroup"
                        :disabled="loading"
                        :class="{ 'opacity-50 cursor-not-allowed': loading }"
                    >
                        {{ editingGroupId ? 'Update Group' : 'Create Group' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    Delete Group
                </h2>

                <p class="text-gray-700 mb-6">
                    Are you sure you want to delete the group "{{ groupToDelete?.name }}"?
                    This action cannot be undone.
                </p>

                <div class="flex justify-end space-x-3">
                    <SecondaryButton
                        @click="showDeleteModal = false"
                        :disabled="loading"
                        :class="{ 'opacity-50 cursor-not-allowed': loading }"
                    >
                        Cancel
                    </SecondaryButton>
                    <button @click="deleteGroup"
                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            :disabled="loading"
                            :class="{ 'opacity-50 cursor-not-allowed hover:bg-red-600': loading }"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
