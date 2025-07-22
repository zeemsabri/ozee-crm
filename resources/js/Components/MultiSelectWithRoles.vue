<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';

const props = defineProps({
    label: {
        type: String,
        required: true
    },
    items: {
        type: Array,
        default: () => []
    },
    endpoint: {
        type: String,
        default: ''
    },
    selectedItems: {
        type: Array,
        default: () => []
    },
    itemText: {
        type: String,
        default: 'name'
    },
    itemSubtext: {
        type: String,
        default: 'email'
    },
    itemValue: {
        type: String,
        default: 'id'
    },
    roleOptions: {
        type: Array,
        default: () => []
    },
    roleType: {
        type: String,
        default: 'application'
    },
    defaultRoleId: {
        type: Number,
        default: null
    },
    error: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Select an item'
    },
    showRemoveButton: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['update:selectedItems']);

// Reactive state
const selectedIds = ref([]);
const selectedItemsWithRoles = ref([]);
const dbRoles = ref([]);
const fetchedItems = ref([]);
const loading = ref(false);

// Fetch roles from the database if not provided
const fetchRoles = async () => {
    if (props.roleOptions.length > 0) {
        return;
    }

    try {
        // Add type parameter to filter roles by type
        const response = await axios.get(`/api/roles?type=${props.roleType}`);
        const roles = response.data;

        // Map roles to the format expected by the dropdowns
        dbRoles.value = roles.map(role => ({
            value: role.id,
            label: role.name
        }));
    } catch (error) {
        console.error('Error fetching roles:', error);
    }
};

// Computed property to use either props or fetched roles
const roleOptionsComputed = computed(() => {
    return dbRoles.value.length > 0 ? dbRoles.value : props.roleOptions;
});

// Get default role ID
const getDefaultRoleId = () => {
    if (props.defaultRoleId) {
        return props.defaultRoleId;
    }

    return roleOptionsComputed.value.length > 0
        ? roleOptionsComputed.value[0].value
        : 1;
};

// Initialize from props
const initializeFromProps = () => {
    console.log('initializeFromProps called with selectedItems:', props.selectedItems);

    // Only clear arrays if we're not receiving valid data
    // This prevents losing selected items when the component re-renders
    if (!props.selectedItems || props.selectedItems.length === 0) {
        selectedIds.value = [];
        selectedItemsWithRoles.value = [];
        console.log('Cleared selectedIds and selectedItemsWithRoles because props.selectedItems is empty');
        return;
    }

    // Keep track of IDs we've processed to avoid duplicates
    const processedIds = new Set();

    // Process each item in props.selectedItems
    props.selectedItems.forEach(item => {
        const id = typeof item === 'object' ? item.id : item;

        // Skip if we've already processed this ID
        if (processedIds.has(id)) {
            return;
        }

        processedIds.add(id);

        // Check if this ID is already in our selectedIds array
        if (!selectedIds.value.includes(id)) {
            selectedIds.value.push(id);

            // Add to selectedItemsWithRoles with role_id if available
            if (typeof item === 'object' && item.role_id) {
                selectedItemsWithRoles.value.push({
                    id: id,
                    role_id: item.role_id
                });
            } else {
                selectedItemsWithRoles.value.push({
                    id: id,
                    role_id: getDefaultRoleId()
                });
            }
        } else {
            // Update the role_id if the item is already in our array
            if (typeof item === 'object' && item.role_id) {
                const existingItem = selectedItemsWithRoles.value.find(i => i.id === id);
                if (existingItem) {
                    existingItem.role_id = item.role_id;
                }
            }
        }
    });

    console.log('After initialization, selectedIds:', selectedIds.value);
    console.log('After initialization, selectedItemsWithRoles:', selectedItemsWithRoles.value);
};

// Watch for changes in props.selectedItems
watch(() => props.selectedItems, (newValue, oldValue) => {
    console.log('selectedItems changed:', newValue);

    // Check if the arrays are different before reinitializing
    // This prevents unnecessary reinitialization which could cause issues
    const isDifferent = JSON.stringify(newValue) !== JSON.stringify(oldValue);

    if (isDifferent) {
        console.log('selectedItems changed significantly, reinitializing');
        initializeFromProps();
    } else {
        console.log('selectedItems change was not significant, skipping reinitialization');
    }
}, { deep: true, immediate: true });

// Watch for changes in selectedItemsWithRoles to emit updates
watch(selectedItemsWithRoles, (newValue) => {
    emit('update:selectedItems', newValue);
}, { deep: true });

// Add an item
const addItem = (itemId) => {
    if (itemId && !selectedIds.value.includes(itemId)) {
        selectedIds.value.push(itemId);
        selectedItemsWithRoles.value.push({
            id: itemId,
            role_id: getDefaultRoleId()
        });
    }
};

// Remove an item
const removeItem = (itemId) => {
    selectedIds.value = selectedIds.value.filter(id => id !== itemId);
    selectedItemsWithRoles.value = selectedItemsWithRoles.value.filter(item => item.id !== itemId);
};

// Update role for an item
const updateRole = (itemId, roleId) => {
    const item = selectedItemsWithRoles.value.find(item => item.id === itemId);
    if (item) {
        item.role_id = parseInt(roleId);
    }
};

// Find item by ID
const findItem = (itemId) => {
    return itemsComputed.value.find(item => item[props.itemValue] === itemId);
};

// Get role for an item
const getItemRole = (itemId) => {
    const item = selectedItemsWithRoles.value.find(item => item.id === itemId);
    return item ? item.role_id : getDefaultRoleId();
};

// Fetch items from the endpoint
const fetchItems = async () => {
    if (!props.endpoint) {
        return;
    }

    loading.value = true;
    try {
        const response = await axios.get(props.endpoint);
        if (response.data && Array.isArray(response.data)) {
            fetchedItems.value = response.data;
        } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
            fetchedItems.value = response.data.data;
        }
    } catch (error) {
        console.error('Error fetching items from endpoint:', error);
    } finally {
        loading.value = false;
    }
};

// Computed property to use either props.items or fetchedItems
const itemsComputed = computed(() => {
    return props.endpoint && fetchedItems.value.length > 0 ? fetchedItems.value : props.items;
});

// Initialize component
onMounted(() => {
    fetchRoles();
    initializeFromProps();
    if (props.endpoint) {
        fetchItems();
    }
});
</script>

<template>
    <div>
        <InputLabel :value="label" />
        <div class="mt-2">
            <div class="mb-2">
                <div v-if="loading" class="text-gray-500 text-sm mb-2">Loading...</div>
                <select
                    v-if="showRemoveButton"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                    @change="e => {
                        const itemId = parseInt(e.target.value);
                        addItem(itemId);
                        e.target.value = '';
                    }"
                    :disabled="loading"
                >
                    <option value="">{{ placeholder }}</option>
                    <option
                        v-for="item in itemsComputed.filter(item => !selectedIds.includes(item[itemValue]))"
                        :key="item[itemValue]"
                        :value="item[itemValue]"
                    >
                        {{ item[itemText] }} ({{ item[itemSubtext] }})
                    </option>
                </select>
            </div>

            <div v-for="itemId in selectedIds" :key="itemId" class="flex items-center mb-1 p-2 border rounded">
                <div class="flex-grow">
                    {{ findItem(itemId)?.[itemText] }} ({{ findItem(itemId)?.[itemSubtext] }})
                </div>
                <select
                    class="ms-2 border-gray-300 rounded-md"
                    :value="getItemRole(itemId)"
                    @change="e => updateRole(itemId, e.target.value)"
                    :disabled="!showRemoveButton"
                >
                    <option
                        v-for="option in roleOptionsComputed"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <button
                    v-if="showRemoveButton"
                    type="button"
                    class="ml-2 text-red-600"
                    @click="() => removeItem(itemId)"
                >
                    Remove
                </button>
            </div>
        </div>
        <InputError :message="error" class="mt-2" />
    </div>
</template>
