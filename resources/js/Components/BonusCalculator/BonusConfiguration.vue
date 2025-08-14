<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import BonusConfigurationGroups from '@/Components/BonusConfiguration/BonusConfigurationGroups.vue';

// Tab state
const activeTab = ref('individual'); // 'individual' or 'groups'

// State
const bonusConfigurations = ref([]);
const editingConfigId = ref(null);
const uiMessage = ref({ text: '', type: '' }); // type: 'success', 'error', 'info'

// Form state
const newBonusConfig = ref({
    name: '',
    type: 'bonus', // 'bonus' or 'penalty'
    amountType: 'percentage', // 'percentage', 'fixed', 'all_related_bonus'
    value: 0,
    appliesTo: 'task', // 'task', 'milestone', 'standup', 'late_task', 'late_milestone'
    targetBonusTypeForRevocation: '', // Only for penalty type 'all_related_bonus'
    isActive: true,
});

// Predefined options
const bonusTypeOptions = [
    { value: 'Daily Standup Bonus', label: 'Daily Standup Bonus' },
    { value: 'On-Time Task Bonus', label: 'On-Time Task Bonus' },
    { value: 'On-Time Milestone Bonus', label: 'On-Time Milestone Bonus' },
    { value: 'Late Task Penalty', label: 'Late Task Penalty' },
    { value: 'Late Milestone Penalty', label: 'Late Milestone Penalty' },
    { value: 'Daily Standup Missed Penalty', label: 'Daily Standup Missed Penalty' },
];

const bonusAppliesToOptions = {
    'Daily Standup Bonus': 'standup',
    'On-Time Task Bonus': 'task',
    'On-Time Milestone Bonus': 'milestone',
    'Late Task Penalty': 'late_task',
    'Late Milestone Penalty': 'late_milestone',
    'Daily Standup Missed Penalty': 'standup_missed',
};

// Computed properties
const isPenaltyAmountTypeDisabled = computed(() => {
    return newBonusConfig.value.type === 'bonus' && newBonusConfig.value.amountType === 'all_related_bonus';
});

// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        console.log('Auth headers set in BonusConfiguration');
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
const fetchBonusConfigurations = async () => {
    try {
        ensureAuthHeaders();
        const response = await axios.get('/api/bonus-configurations');
        bonusConfigurations.value = response.data;
    } catch (error) {
        console.error('Error fetching bonus configurations:', error);
        showMessage('Failed to load bonus configurations.', 'error');
    }
};

const saveBonusConfiguration = async () => {
    try {
        ensureAuthHeaders();

        if (!newBonusConfig.value.name) {
            showMessage('Rule Name is required.', 'error');
            return;
        }

        if (newBonusConfig.value.amountType !== 'all_related_bonus' &&
            (newBonusConfig.value.value === null ||
             newBonusConfig.value.value === undefined ||
             newBonusConfig.value.value === '')) {
            showMessage('Value is required for this rule type.', 'error');
            return;
        }

        const uuid = editingConfigId.value || crypto.randomUUID();
        const finalConfig = {
            id: uuid,
            uuid: uuid, // Add uuid field with the same value as id
            ...newBonusConfig.value,
            value: (newBonusConfig.value.amountType === 'all_related_bonus') ? 0 : parseFloat(newBonusConfig.value.value),
            appliesTo: bonusAppliesToOptions[newBonusConfig.value.name] || newBonusConfig.value.appliesTo,
        };

        if (finalConfig.type === 'penalty' &&
            finalConfig.amountType === 'all_related_bonus' &&
            !finalConfig.targetBonusTypeForRevocation) {
            showMessage('For "Revoke All Related Bonus" penalties, you must select a bonus type to revoke.', 'error');
            return;
        }

        if (editingConfigId.value) {
            // Update existing configuration
            await axios.put(`/api/bonus-configurations/${editingConfigId.value}`, finalConfig);
            showMessage(`Updated ${finalConfig.name} configuration.`, 'success');
        } else {
            // Add new configuration
            await axios.post('/api/bonus-configurations', finalConfig);
            showMessage(`Added ${finalConfig.name} configuration.`, 'success');
        }

        // Refresh the list
        fetchBonusConfigurations();

        // Reset form
        resetForm();
    } catch (error) {
        console.error('Error saving bonus configuration:', error);
        showMessage('Failed to save bonus configuration.', 'error');
    }
};

const deleteBonusConfiguration = async (id) => {
    try {
        ensureAuthHeaders();
        await axios.delete(`/api/bonus-configurations/${id}`);
        showMessage('Configuration deleted successfully.', 'success');

        // Refresh the list
        fetchBonusConfigurations();

        // If we're currently editing this config, reset the form
        if (editingConfigId.value === id) {
            resetForm();
        }
    } catch (error) {
        console.error('Error deleting bonus configuration:', error);
        showMessage('Failed to delete bonus configuration.', 'error');
    }
};

// Form handlers
const handleAddBonusConfiguration = () => {
    saveBonusConfiguration();
};

const handleEditBonusConfiguration = (config) => {
    editingConfigId.value = config.id;
    newBonusConfig.value = {
        name: config.name,
        type: config.type,
        amountType: config.amountType,
        value: config.value,
        appliesTo: config.appliesTo,
        targetBonusTypeForRevocation: config.targetBonusTypeForRevocation || '',
        isActive: config.isActive,
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const handleDeleteBonusConfiguration = (id) => {
    deleteBonusConfiguration(id);
};

const resetForm = () => {
    editingConfigId.value = null;
    newBonusConfig.value = {
        name: '',
        type: 'bonus',
        amountType: 'percentage',
        value: 0,
        appliesTo: 'task',
        targetBonusTypeForRevocation: '',
        isActive: true,
    };
};

// Lifecycle hooks
onMounted(() => {
    ensureAuthHeaders();
    fetchBonusConfigurations();
});
</script>

<template>
    <div class="min-h-screen bg-gray-100 p-8 font-sans text-gray-800">
        <!-- UI Message Display -->
        <div v-if="uiMessage.text"
             :class="`p-3 rounded-md mb-4 text-center font-medium ${
                 uiMessage.type === 'success' ? 'bg-green-100 text-green-700' :
                 uiMessage.type === 'error' ? 'bg-red-100 text-red-700' :
                 'bg-blue-100 text-blue-700'
             }`">
            {{ uiMessage.text }}
        </div>

        <!-- Tab Navigation -->
        <div class="max-w-3xl mx-auto mb-6">
            <div class="flex border-b border-gray-200">
                <button
                    @click="activeTab = 'individual'"
                    :class="`px-4 py-2 font-medium text-sm focus:outline-none ${
                        activeTab === 'individual'
                            ? 'border-b-2 border-blue-500 text-blue-600'
                            : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    }`"
                >
                    Individual Configurations
                </button>
                <button
                    @click="activeTab = 'groups'"
                    :class="`px-4 py-2 font-medium text-sm focus:outline-none ${
                        activeTab === 'groups'
                            ? 'border-b-2 border-blue-500 text-blue-600'
                            : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    }`"
                >
                    Configuration Groups
                </button>
            </div>
        </div>

        <!-- Individual Configurations Tab -->
        <div v-if="activeTab === 'individual'" class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold mb-4 text-blue-600">Configure Bonuses & Penalties</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <InputLabel for="bonusConfigName" value="Rule Name" />
                    <select
                        id="bonusConfigName"
                        class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        v-model="newBonusConfig.name"
                        @change="(e) => {
                            newBonusConfig.type = e.target.value.includes('Penalty') ? 'penalty' : 'bonus';
                            // Reset amountType if it becomes invalid for the new type or for 'all_related_bonus' default
                            newBonusConfig.amountType = (e.target.value.includes('Daily Standup Missed Penalty'))
                                ? 'all_related_bonus'
                                : ((e.target.value.includes('Penalty') && newBonusConfig.amountType === 'percentage')
                                    ? 'fixed'
                                    : newBonusConfig.amountType);
                            // Auto-select for this penalty type
                            newBonusConfig.targetBonusTypeForRevocation = (e.target.value.includes('Daily Standup Missed Penalty'))
                                ? 'Daily Standup Bonus'
                                : '';
                            // Value is 0 for this type
                            newBonusConfig.value = (e.target.value.includes('Daily Standup Missed Penalty'))
                                ? 0
                                : newBonusConfig.value;
                        }"
                    >
                        <option value="">-- Select Rule Type --</option>
                        <option v-for="option in bonusTypeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <InputLabel for="value" value="Value (e.g., 5 or 50)" />
                    <TextInput
                        id="value"
                        type="number"
                        class="w-full"
                        v-model="newBonusConfig.value"
                        min="0"
                        step="0.1"
                        :disabled="newBonusConfig.amountType === 'all_related_bonus'"
                    />
                </div>
                <div>
                    <InputLabel for="amountType" value="Amount Type" />
                    <select
                        id="amountType"
                        class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        v-model="newBonusConfig.amountType"
                        :disabled="newBonusConfig.name.includes('Daily Standup Missed Penalty') || isPenaltyAmountTypeDisabled"
                    >
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount ($)</option>
                        <option v-if="newBonusConfig.type === 'penalty'" value="all_related_bonus">Revoke All Related Bonus</option>
                    </select>
                </div>
                <div v-if="newBonusConfig.type === 'penalty' && newBonusConfig.amountType === 'all_related_bonus'">
                    <InputLabel for="targetBonusTypeForRevocation" value="Revoke Which Bonus Type?" />
                    <select
                        id="targetBonusTypeForRevocation"
                        class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        v-model="newBonusConfig.targetBonusTypeForRevocation"
                    >
                        <option value="">-- Select Bonus Type --</option>
                        <!-- Include predefined bonus types -->
                        <option
                            v-for="option in bonusTypeOptions.filter(option => !option.value.includes('Penalty'))"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                        <!-- Also include any custom bonus configurations that aren't in the predefined list -->
                        <option
                            v-for="bc in bonusConfigurations.filter(bc => bc.type === 'bonus' && !bonusTypeOptions.some(option => option.value === bc.name))"
                            :key="bc.id"
                            :value="bc.name"
                        >
                            {{ bc.name }}
                        </option>
                    </select>
                </div>
            </div>
            <PrimaryButton
                @click="handleAddBonusConfiguration"
                :class="editingConfigId ? 'bg-green-500 hover:bg-green-600' : 'bg-blue-500 hover:bg-blue-600'"
                class="w-full transform hover:scale-105"
            >
                {{ editingConfigId ? 'Update Rule' : 'Add Rule' }}
            </PrimaryButton>

            <SecondaryButton
                v-if="editingConfigId"
                @click="resetForm"
                class="w-full mt-2"
            >
                Cancel Editing
            </SecondaryButton>

            <h3 class="text-xl font-semibold mt-6 mb-3 text-blue-500">Current Rules</h3>
            <p v-if="bonusConfigurations.length === 0" class="text-gray-500">No bonus/penalty rules defined yet.</p>
            <ul v-else class="space-y-2">
                <li
                    v-for="rule in bonusConfigurations"
                    :key="rule.id"
                    :class="`p-3 rounded-md border text-sm ${rule.type === 'bonus' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="font-medium">{{ rule.name }}</span> ({{ rule.type === 'bonus' ? 'Bonus' : 'Penalty' }}):
                            <span v-if="rule.amountType === 'all_related_bonus'">
                                Revoke all "{{ rule.targetBonusTypeForRevocation }}" bonuses
                            </span>
                            <span v-else>
                                {{ rule.value }}{{ rule.amountType === 'percentage' ? '%' : '$' }}
                                (Applies to: {{ rule.appliesTo.replace(/_/g, ' ') }})
                            </span>
                        </div>
                        <div class="flex space-x-2 ml-2">
                            <button
                                @click="() => handleEditBonusConfiguration(rule)"
                                class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-2 py-1 rounded text-xs"
                            >
                                Edit
                            </button>
                            <button
                                @click="() => handleDeleteBonusConfiguration(rule.id)"
                                class="bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded text-xs"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Configuration Groups Tab -->
        <div v-if="activeTab === 'groups'" class="max-w-3xl mx-auto">
            <BonusConfigurationGroups />
        </div>
    </div>
</template>
