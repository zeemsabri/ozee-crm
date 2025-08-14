<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    projectId: {
        type: [Number, String],
        required: true
    }
});

const emit = defineEmits(['close', 'group-attached']);

// State
const bonusConfigurationGroups = ref([]);
const selectedGroupId = ref(null);
const loading = ref(false);
const error = ref('');
const success = ref('');
const showCreateGroupForm = ref(false);

// Form state for creating a new group
const newGroupForm = ref({
    name: '',
    description: '',
    configurations: []
});

// Available configurations for new group
const availableConfigurations = ref([]);

// Computed properties
const selectedGroup = computed(() => {
    return bonusConfigurationGroups.value.find(group => group.id === selectedGroupId.value);
});

// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
};

// Fetch bonus configuration groups
const fetchBonusConfigurationGroups = async () => {
    loading.value = true;
    error.value = '';

    try {
        ensureAuthHeaders();
        const response = await axios.get('/api/bonus-configuration-groups');
        bonusConfigurationGroups.value = response.data;
    } catch (err) {
        console.error('Error fetching bonus configuration groups:', err);
        error.value = 'Failed to load bonus configuration groups';
    } finally {
        loading.value = false;
    }
};

// Fetch available configurations for new group
const fetchAvailableConfigurations = async () => {
    try {
        ensureAuthHeaders();
        const response = await axios.get('/api/bonus-configurations');
        availableConfigurations.value = response.data;
    } catch (err) {
        console.error('Error fetching bonus configurations:', err);
        error.value = 'Failed to load bonus configurations';
    }
};

// Attach selected group to project
const attachGroupToProject = async () => {
    if (!selectedGroupId.value) {
        error.value = 'Please select a bonus configuration group';
        return;
    }

    loading.value = true;
    error.value = '';
    success.value = '';

    try {
        ensureAuthHeaders();
        await axios.post(`/api/projects/${props.projectId}/attach-bonus-configuration-group`, {
            group_id: selectedGroupId.value
        });

        success.value = 'Bonus configuration group attached successfully';
        emit('group-attached');

        // Reset after a short delay
        setTimeout(() => {
            success.value = '';
            selectedGroupId.value = null;
            emit('close');
        }, 1500);
    } catch (err) {
        console.error('Error attaching bonus configuration group:', err);
        error.value = err.response?.data?.message || 'Failed to attach bonus configuration group';
    } finally {
        loading.value = false;
    }
};

// Create a new group and attach it to the project
const createAndAttachGroup = async () => {
    if (!newGroupForm.value.name) {
        error.value = 'Group name is required';
        return;
    }

    loading.value = true;
    error.value = '';
    success.value = '';

    try {
        ensureAuthHeaders();

        // Create the new group
        const createResponse = await axios.post('/api/bonus-configuration-groups', newGroupForm.value);
        const newGroupId = createResponse.data.id;

        // Attach the new group to the project
        await axios.post(`/api/projects/${props.projectId}/attach-bonus-configuration-group`, {
            group_id: newGroupId
        });

        success.value = 'New bonus configuration group created and attached successfully';
        emit('group-attached');

        // Reset after a short delay
        setTimeout(() => {
            success.value = '';
            showCreateGroupForm.value = false;
            newGroupForm.value = {
                name: '',
                description: '',
                configurations: []
            };
            emit('close');
        }, 1500);
    } catch (err) {
        console.error('Error creating and attaching bonus configuration group:', err);
        error.value = err.response?.data?.message || 'Failed to create and attach bonus configuration group';
    } finally {
        loading.value = false;
    }
};

// Toggle create group form
const toggleCreateGroupForm = () => {
    showCreateGroupForm.value = !showCreateGroupForm.value;
    if (showCreateGroupForm.value && availableConfigurations.value.length === 0) {
        fetchAvailableConfigurations();
    }
};

// Reset the form
const resetForm = () => {
    selectedGroupId.value = null;
    error.value = '';
    success.value = '';
    showCreateGroupForm.value = false;
    newGroupForm.value = {
        name: '',
        description: '',
        configurations: []
    };
};

// Watch for show prop changes
watch(() => props.show, (newValue) => {
    if (newValue) {
        fetchBonusConfigurationGroups();
        resetForm();
    }
});

// Initialize
onMounted(() => {
    if (props.show) {
        fetchBonusConfigurationGroups();
    }
});
</script>

<template>
    <Modal :show="show" @close="$emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                Attach Bonus Configuration Group
            </h2>

            <!-- Error/Success Messages -->
            <div v-if="error" class="mb-4 p-3 bg-red-100 text-red-700 rounded-md">
                {{ error }}
            </div>
            <div v-if="success" class="mb-4 p-3 bg-green-100 text-green-700 rounded-md">
                {{ success }}
            </div>

            <div v-if="!showCreateGroupForm">
                <!-- Select Existing Group -->
                <div v-if="loading" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
                    <p class="mt-2 text-gray-600">Loading bonus configuration groups...</p>
                </div>

                <div v-else>
                    <div v-if="bonusConfigurationGroups.length === 0" class="text-center py-4">
                        <p class="text-gray-600">No bonus configuration groups available.</p>
                        <p class="mt-2">
                            <button @click="toggleCreateGroupForm" class="text-blue-600 hover:text-blue-800 underline">
                                Create a new group
                            </button>
                        </p>
                    </div>

                    <div v-else>
                        <div class="mb-4">
                            <InputLabel for="group" value="Select a Bonus Configuration Group" />
                            <select
                                id="group"
                                v-model="selectedGroupId"
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">-- Select a Group --</option>
                                <option v-for="group in bonusConfigurationGroups" :key="group.id" :value="group.id">
                                    {{ group.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Preview Selected Group -->
                        <div v-if="selectedGroup" class="mb-4 p-4 border border-gray-200 rounded-md bg-gray-50">
                            <h3 class="font-medium text-gray-900">{{ selectedGroup.name }}</h3>
                            <p v-if="selectedGroup.description" class="text-sm text-gray-600 mt-1">
                                {{ selectedGroup.description }}
                            </p>

                            <div class="mt-3">
                                <h4 class="text-sm font-medium text-gray-700">Configurations:</h4>
                                <ul class="mt-1 pl-5 list-disc text-sm">
                                    <li v-for="config in selectedGroup.configurations" :key="config.id"
                                        :class="config.type === 'bonus' ? 'text-green-600' : 'text-red-600'">
                                        {{ config.name }}
                                    </li>
                                    <li v-if="selectedGroup.configurations.length === 0" class="text-gray-500">
                                        No configurations in this group
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="flex justify-between">
                            <button @click="toggleCreateGroupForm" class="text-blue-600 hover:text-blue-800 underline">
                                Create a new group
                            </button>

                            <div class="flex space-x-3">
                                <SecondaryButton @click="$emit('close')">
                                    Cancel
                                </SecondaryButton>
                                <PrimaryButton
                                    @click="attachGroupToProject"
                                    :disabled="!selectedGroupId || loading"
                                    :class="{ 'opacity-50 cursor-not-allowed': !selectedGroupId || loading }"
                                >
                                    Attach Group
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create New Group Form -->
            <div v-else>
                <div class="space-y-4">
                    <!-- Name Field -->
                    <div>
                        <InputLabel for="name" value="Group Name" />
                        <TextInput id="name" v-model="newGroupForm.name" type="text" class="mt-1 block w-full" required />
                    </div>

                    <!-- Description Field -->
                    <div>
                        <InputLabel for="description" value="Description (Optional)" />
                        <textarea id="description" v-model="newGroupForm.description"
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="2"></textarea>
                    </div>

                    <!-- Configurations Multi-select -->
                    <div>
                        <InputLabel value="Configurations" />
                        <div class="mt-1 border border-gray-300 rounded-md p-2 max-h-40 overflow-y-auto">
                            <div v-if="availableConfigurations.length === 0" class="text-gray-500 text-sm py-2 text-center">
                                Loading configurations...
                            </div>
                            <div v-else v-for="config in availableConfigurations" :key="config.id" class="flex items-center py-1">
                                <input :id="`config-${config.id}`" type="checkbox"
                                       :value="config.id" v-model="newGroupForm.configurations"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <label :for="`config-${config.id}`" class="ml-2 block text-sm text-gray-900">
                                    {{ config.name }}
                                    <span :class="config.type === 'bonus' ? 'text-green-600' : 'text-red-600'">
                                        ({{ config.type === 'bonus' ? 'Bonus' : 'Penalty' }})
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-between">
                    <button @click="toggleCreateGroupForm" class="text-blue-600 hover:text-blue-800 underline">
                        Back to existing groups
                    </button>

                    <div class="flex space-x-3">
                        <SecondaryButton @click="$emit('close')">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            @click="createAndAttachGroup"
                            :disabled="!newGroupForm.name || loading"
                            :class="{ 'opacity-50 cursor-not-allowed': !newGroupForm.name || loading }"
                        >
                            Create & Attach
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
