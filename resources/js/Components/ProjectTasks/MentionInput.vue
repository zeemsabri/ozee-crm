<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    projectId: {
        type: Number,
        required: true
    },
    placeholder: {
        type: String,
        default: "Add quick action point..."
    },
    modelValue: {
        type: String,
        default: ""
    },
    type: {
        type: String,
        default: "input" // or "textarea"
    }
});

const emit = defineEmits(['update:modelValue', 'submit', 'user-selected', 'task-selected']);

const text = ref(props.modelValue);
watch(() => props.modelValue, (newVal) => {
    text.value = newVal;
});

const members = ref([]);
const tasks = ref([]);
const showSuggestions = ref(false);
const filteredResults = ref([]);
const selectedIndex = ref(0);
const inputRef = ref(null);
const mentionStartIndex = ref(-1);
const currentTrigger = ref('@'); // '@' or '#'

const fetchMembers = async () => {
    if (!props.projectId) {
        members.value = [];
        return;
    }
    
    try {
        const response = await axios.get(`/api/projects/${props.projectId}/sections/meeting-attendees`);
        const users = (response.data.users || []).map(u => ({ ...u, memberType: 'User', type: 'member' }));
        const clients = (response.data.clients || []).map(c => ({ ...c, memberType: 'Client', type: 'member' }));
        members.value = [...users, ...clients];
    } catch (e) {
        console.error('Failed to fetch project members for mentions:', e);
    }
};

const fetchTasks = async (query = '') => {
    if (!props.projectId) {
        tasks.value = [];
        return;
    }
    
    try {
        // Use the index endpoint with project_id and search if needed
        const response = await axios.get('/api/tasks', {
            params: {
                project_id: props.projectId,
                search: query,
                per_page: 20
            }
        });
        // The endpoint might return pagination or direct array depending on per_page
        const taskData = response.data.data || response.data;
        tasks.value = taskData.map(t => ({ ...t, type: 'task' }));
    } catch (e) {
        console.error('Failed to fetch tasks for mentions:', e);
    }
};

watch(() => props.projectId, () => {
    fetchMembers();
}, { immediate: true });

const onInput = (e) => {
    emit('update:modelValue', text.value);
    
    const cursor = e.target.selectionStart;
    const textBeforeCursor = text.value.slice(0, cursor);
    
    const lastAtSymbol = textBeforeCursor.lastIndexOf('@');
    const lastHashSymbol = textBeforeCursor.lastIndexOf('#');
    
    // Determine which trigger is active and closer to cursor
    let trigger = null;
    let symbolIndex = -1;
    
    if (lastAtSymbol > lastHashSymbol) {
        trigger = '@';
        symbolIndex = lastAtSymbol;
    } else if (lastHashSymbol > lastAtSymbol) {
        trigger = '#';
        symbolIndex = lastHashSymbol;
    } else if (lastAtSymbol === lastHashSymbol && lastAtSymbol !== -1) {
        // Technically shouldn't happen unless both are same index, but safeguard
        trigger = '@';
        symbolIndex = lastAtSymbol;
    }
    
    // Check if symbol is at start or following a space/newline
    if (symbolIndex !== -1 && (symbolIndex === 0 || [' ', '\n', '\r'].includes(textBeforeCursor[symbolIndex - 1]))) {
        const query = textBeforeCursor.slice(symbolIndex + 1);
        mentionStartIndex.value = symbolIndex;
        currentTrigger.value = trigger;
        
        if (trigger === '@') {
            filteredResults.value = members.value.filter(m => 
                m.name.toLowerCase().includes(query.toLowerCase())
            );
            if (filteredResults.value.length > 0) {
                showSuggestions.value = true;
                selectedIndex.value = 0;
            } else {
                showSuggestions.value = false;
            }
        } else {
            // For tasks, we might want to fetch based on query if not already loaded or if query is long
            fetchTasks(query).then(() => {
                filteredResults.value = tasks.value;
                if (filteredResults.value.length > 0) {
                    showSuggestions.value = true;
                    selectedIndex.value = 0;
                } else {
                    showSuggestions.value = false;
                }
            });
        }
    } else {
        showSuggestions.value = false;
    }
};

const selectItem = (item) => {
    const beforeMention = text.value.slice(0, mentionStartIndex.value);
    const afterMention = text.value.slice(inputRef.value.selectionStart || text.value.length);
    
    if (currentTrigger.value === '@') {
        text.value = `${beforeMention}@{${item.id}:${item.name}} ${afterMention}`;
        emit('user-selected', item);
    } else {
        // Use task_number for display in the tag and name for the title
        const taskNum = item.task_number || `OZ${item.id}`;
        const taskName = item.name || 'Task';
        text.value = `${beforeMention}#{${item.id}:${taskNum}:${taskName}} ${afterMention}`;
        emit('task-selected', item);
    }
    
    showSuggestions.value = false;
    emit('update:modelValue', text.value);
    
    nextTick(() => {
        inputRef.value.focus();
    });
};

const moveDown = () => {
    if (!showSuggestions.value) return;
    selectedIndex.value = (selectedIndex.value + 1) % filteredResults.value.length;
};

const moveUp = () => {
    if (!showSuggestions.value) return;
    selectedIndex.value = (selectedIndex.value - 1 + filteredResults.value.length) % filteredResults.value.length;
};

const onEnter = (e) => {
    if (showSuggestions.value && filteredResults.value.length > 0) {
        e.preventDefault();
        selectItem(filteredResults.value[selectedIndex.value]);
    } else if (props.type === 'input') {
        e.preventDefault();
        emit('submit');
    }
};

defineExpose({
    focus: () => inputRef.value?.focus(),
    clear: () => { text.value = ''; emit('update:modelValue', ''); }
});
</script>

<template>
    <div class="relative flex-1">
        <textarea
            v-if="type === 'textarea'"
            ref="inputRef"
            v-model="text"
            @input="onInput"
            @keydown.down.prevent="moveDown"
            @keydown.up.prevent="moveUp"
            @keydown.enter="onEnter"
            @keydown.esc="showSuggestions = false"
            @blur="setTimeout(() => showSuggestions = false, 200)"
            :placeholder="placeholder"
            class="w-full rounded-2xl border-indigo-100 focus:ring-indigo-500 text-sm p-4 min-h-[60px] max-h-[150px] placeholder:text-indigo-200"
        ></textarea>
        <input 
            v-else
            ref="inputRef"
            type="text" 
            v-model="text" 
            @input="onInput"
            @keydown.down.prevent="moveDown"
            @keydown.up.prevent="moveUp"
            @keydown.enter.prevent="onEnter"
            @keydown.esc="showSuggestions = false"
            @blur="setTimeout(() => showSuggestions = false, 200)"
            :placeholder="placeholder" 
            class="w-full rounded-xl border-gray-200 text-sm focus:ring-indigo-500 font-medium h-10 px-4"
        />
        
        <!-- Suggestions Dropdown -->
        <div v-if="showSuggestions && filteredResults.length > 0" 
             class="absolute z-50 bottom-full mb-2 w-72 bg-white border border-gray-200 rounded-2xl shadow-2xl max-h-60 overflow-y-auto p-2">
            <div class="px-3 py-2 text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50 mb-1">
                {{ currentTrigger === '@' ? 'Project Members' : 'Project Tasks' }}
            </div>
            <div v-for="(item, index) in filteredResults" 
                 :key="item.id"
                 @click="selectItem(item)"
                 :class="{'bg-indigo-50 text-indigo-700': selectedIndex === index, 'text-gray-700': selectedIndex !== index}"
                 class="px-3 py-2 text-xs font-bold cursor-pointer rounded-xl hover:bg-gray-50 flex items-center justify-between transition-colors">
                <div class="flex items-center gap-2 min-w-0">
                    <div :class="[
                        'h-6 w-6 rounded-lg flex items-center justify-center text-[10px] font-black flex-shrink-0',
                        currentTrigger === '@' ? 'bg-indigo-100 text-indigo-600' : 'bg-emerald-100 text-emerald-600'
                    ]">
                        {{ currentTrigger === '@' ? (item.name ? item.name.substring(0, 2).toUpperCase() : '??') : '#' }}
                    </div>
                    <span class="truncate">{{ currentTrigger === '@' ? item.name : item.name }}</span>
                </div>
                <!-- Role or task status -->
                <span class="text-[9px] text-gray-400 uppercase font-bold flex-shrink-0 ml-2">
                    {{ currentTrigger === '@' ? (item.memberType === 'Client' ? 'Client' : (item.pivot?.role || item.role?.name || 'User')) : (item.task_number || item.status) }}
                </span>
            </div>
        </div>
    </div>
</template>
