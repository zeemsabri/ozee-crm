<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue'; // Import the custom SelectDropdown
import axios from 'axios';

const props = defineProps({
    label: {
        type: String,
        required: true
    },
    items: { // All available items (e.g., users, clients) to select from
        type: Array,
        default: () => []
    },
    endpoint: { // Optional API endpoint to fetch items if not provided via 'items' prop
        type: String,
        default: ''
    },
    selectedItems: { // The currently selected items with their roles [{id: 1, role_id: 2}]
        type: Array,
        default: () => []
    },
    itemText: { // Key for the display text of an item (e.g., 'name')
        type: String,
        default: 'name'
    },
    itemSubtext: { // Key for secondary display text (e.g., 'email')
        type: String,
        default: 'email'
    },
    itemValue: { // Key for the unique value of an item (e.g., 'id')
        type: String,
        default: 'id'
    },
    roleOptions: { // Role options provided by parent, or fetched internally
        type: Array,
        default: () => []
    },
    roleType: { // Type of roles to fetch if roleOptions is empty (e.g., 'application', 'client', 'project')
        type: String,
        default: 'application'
    },
    defaultRoleId: { // Default role ID for newly added items
        type: Number,
        default: null
    },
    error: { // Validation error message
        type: String,
        default: ''
    },
    placeholder: { // Placeholder for the main select dropdown
        type: String,
        default: 'Select an item'
    },
    showRemoveButton: { // Whether to show the remove button for selected items
        type: Boolean,
        default: true
    },
    disabled: { // Disable all interactions
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:selectedItems']);

// Reactive state
const selectedIds = ref([]); // Stores only the IDs of selected items
const selectedItemsWithRoles = ref([]); // Stores {id, role_id} for selected items
const dbRoles = ref([]); // Roles fetched from DB if not provided by prop
const fetchedItems = ref([]); // Items fetched from endpoint if provided
const loading = ref(false); // Loading state for fetching items

// Fetch roles from the database if roleOptions is empty
const fetchRoles = async () => {
    if (props.roleOptions.length > 0) {
        // If roles are provided by props, no need to fetch
        return;
    }

    try {
        const response = await axios.get(`/api/roles?type=${props.roleType}`);
        const roles = response.data;
        // Map roles to the format expected by SelectDropdown
        dbRoles.value = roles.map(role => ({
            value: role.id,
            label: role.name
        }));
    } catch (error) {
        console.error('Error fetching roles:', error);
    }
};

// Computed property to use either props.roleOptions or fetched dbRoles
const roleOptionsComputed = computed(() => {
    return dbRoles.value.length > 0 ? dbRoles.value : props.roleOptions;
});

// Get the default role ID for new items
const getDefaultRoleId = () => {
    if (props.defaultRoleId) {
        return props.defaultRoleId;
    }
    // Fallback to the first available role if no defaultRoleId is provided
    return roleOptionsComputed.value.length > 0
        ? roleOptionsComputed.value[0].value
        : 1; // Default to 1 if no roles are available
};

// Initialize selectedIds and selectedItemsWithRoles from props.selectedItems
const initializeFromProps = () => {
    // Clear current state to ensure fresh initialization
    selectedIds.value = [];
    selectedItemsWithRoles.value = [];

    if (!props.selectedItems || props.selectedItems.length === 0) {
        return; // Nothing to initialize
    }

    props.selectedItems.forEach(item => {
        const id = typeof item === 'object' ? item[props.itemValue] : item; // Get ID from object or direct value
        const role_id = typeof item === 'object' && item.role_id !== undefined ? item.role_id : getDefaultRoleId();

        // Ensure no duplicates are added during initialization
        if (!selectedIds.value.includes(id)) {
            selectedIds.value.push(id);
            selectedItemsWithRoles.value.push({ id: id, role_id: role_id });
        } else {
            // If item already exists, just update its role_id if it changed
            const existingItem = selectedItemsWithRoles.value.find(i => i.id === id);
            if (existingItem && existingItem.role_id !== role_id) {
                existingItem.role_id = role_id;
            }
        }
    });
};

// Watch for changes in props.selectedItems (deeply) to re-initialize
watch(() => props.selectedItems, (newValue, oldValue) => {
    // Perform a deep comparison to avoid unnecessary re-initialization
    if (JSON.stringify(newValue) !== JSON.stringify(oldValue)) {
        initializeFromProps();
    }
}, { deep: true, immediate: true }); // immediate: true to run on component mount

// Watch for changes in selectedItemsWithRoles to emit updates to parent
watch(selectedItemsWithRoles, (newValue) => {
    emit('update:selectedItems', newValue);
}, { deep: true });

// Add an item from the main SelectDropdown
const addItem = (itemId) => {
    if (props.disabled) return;
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
    if (props.disabled) return;
    selectedIds.value = selectedIds.value.filter(id => id !== itemId);
    selectedItemsWithRoles.value = selectedItemsWithRoles.value.filter(item => item.id !== itemId);
};

// Update role for an item
const updateRole = (itemId, roleId) => {
    if (props.disabled) return;
    const item = selectedItemsWithRoles.value.find(item => item.id === itemId);
    if (item) {
        item.role_id = parseInt(roleId);
    }
};

// Find full item object by ID (from either props.items or fetchedItems)
const findItem = (itemId) => {
    return itemsComputed.value.find(item => item[props.itemValue] === itemId);
};

// Get role for a specific selected item
const getItemRole = (itemId) => {
    const item = selectedItemsWithRoles.value.find(item => item.id === itemId);
    return item ? item.role_id : getDefaultRoleId();
};

// Fetch items from the endpoint (if provided)
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

// Options for the main SelectDropdown (filtered to exclude already selected items)
const availableItemsForDropdown = computed(() => {
    return itemsComputed.value.filter(item => !selectedIds.value.includes(item[props.itemValue])).map(item => ({
        value: item[props.itemValue],
        label: `${item[props.itemText]} (${item[props.itemSubtext]})`
    }));
});

// Initialize component on mount
onMounted(() => {
    fetchRoles(); // Fetch roles if needed
    if (props.endpoint) {
        fetchItems(); // Fetch items if endpoint is provided
    }
});
</script>

<template>
    <div class="mb-4">
        <InputLabel :value="label" />
        <div class="mt-2">
            <div v-if="loading" class="text-gray-500 text-sm mb-2">Loading available items...</div>

            <!-- Main SelectDropdown for adding new items -->
            <div class="mb-4">
                <SelectDropdown
                    v-model="selectedItemToAdd"
                    :options="availableItemsForDropdown"
                    valueKey="value"
                    labelKey="label"
                    :placeholder="placeholder"
                    :disabled="loading || disabled"
                    @change="addItem"
                    class="w-full"
                />
            </div>

            <!-- List of Selected Items with Roles -->
            <div v-if="selectedIds.length > 0" class="space-y-3">
                <div
                    v-for="itemId in selectedIds"
                    :key="itemId"
                    class="flex items-center p-3 border border-gray-200 rounded-lg bg-gray-50 shadow-sm"
                >
                    <div class="flex-grow text-gray-800 font-medium">
                        {{ findItem(itemId)?.[itemText] }}
                        <span class="text-gray-600 text-sm">({{ findItem(itemId)?.[itemSubtext] }})</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <SelectDropdown
                            :modelValue="getItemRole(itemId)"
                            @update:modelValue="roleId => updateRole(itemId, roleId)"
                            :options="roleOptionsComputed"
                            valueKey="value"
                            labelKey="label"
                            placeholder="Select Role"
                            :disabled="disabled"
                            class="w-32"
                        />
                        <button
                            v-if="showRemoveButton && !disabled"
                            type="button"
                            class="text-red-500 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded-full p-1 transition-colors duration-200"
                            @click="removeItem(itemId)"
                            aria-label="Remove item"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="p-4 bg-gray-50 rounded-lg text-gray-600 text-center border border-dashed border-gray-200">
                No {{ label.toLowerCase() }} selected.
            </div>
        </div>
        <InputError :message="error" class="mt-2" />
    </div>
</template>

