<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';

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

const emit = defineEmits(['update:modelValue', 'submit', 'user-selected']);

const text = ref(props.modelValue);
watch(() => props.modelValue, (newVal) => {
    text.value = newVal;
});
const members = ref([]);
const showSuggestions = ref(false);
const filteredMembers = ref([]);
const selectedIndex = ref(0);
const inputRef = ref(null);
const mentionStartIndex = ref(-1);

const fetchMembers = async () => {
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/meeting-attendees`);
        // Combine users and clients as suggestable members
        const users = (response.data.users || []).map(u => ({ ...u, memberType: 'User' }));
        const clients = (response.data.clients || []).map(c => ({ ...c, memberType: 'Client' }));
        members.value = [...users, ...clients];
    } catch (e) {
        console.error('Failed to fetch project members for mentions:', e);
    }
};

const onInput = (e) => {
    emit('update:modelValue', text.value);
    
    const cursor = e.target.selectionStart;
    const textBeforeCursor = text.value.slice(0, cursor);
    const lastAtSymbol = textBeforeCursor.lastIndexOf('@');
    
    // Check if @ is at start or following a space/newline
    if (lastAtSymbol !== -1 && (lastAtSymbol === 0 || [' ', '\n', '\r'].includes(textBeforeCursor[lastAtSymbol - 1]))) {
        const query = textBeforeCursor.slice(lastAtSymbol + 1);
        mentionStartIndex.value = lastAtSymbol;
        
        filteredMembers.value = members.value.filter(m => 
            m.name.toLowerCase().includes(query.toLowerCase())
        );
        
        if (filteredMembers.value.length > 0) {
            showSuggestions.value = true;
            selectedIndex.value = 0;
        } else {
            showSuggestions.value = false;
        }
    } else {
        showSuggestions.value = false;
    }
};

const selectMember = (member) => {
    const beforeMention = text.value.slice(0, mentionStartIndex.value);
    const afterMention = text.value.slice(inputRef.value.selectionStart);
    
    text.value = `${beforeMention}@${member.name} ${afterMention}`;
    showSuggestions.value = false;
    emit('update:modelValue', text.value);
    emit('user-selected', member);
    
    nextTick(() => {
        inputRef.value.focus();
    });
};

const moveDown = () => {
    if (!showSuggestions.value) return;
    selectedIndex.value = (selectedIndex.value + 1) % filteredMembers.value.length;
};

const moveUp = () => {
    if (!showSuggestions.value) return;
    selectedIndex.value = (selectedIndex.value - 1 + filteredMembers.value.length) % filteredMembers.value.length;
};

const onEnter = (e) => {
    if (showSuggestions.value && filteredMembers.value.length > 0) {
        e.preventDefault();
        selectMember(filteredMembers.value[selectedIndex.value]);
    } else if (props.type === 'input') {
        e.preventDefault();
        emit('submit');
    }
    // In textarea mode, default enter behavior (newline) is preserved
};

onMounted(() => {
    fetchMembers();
});

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
            class="w-full rounded-2xl border-indigo-100 focus:ring-indigo-500 text-sm p-4 min-h-[120px] placeholder:text-indigo-200"
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
        <div v-if="showSuggestions && filteredMembers.length > 0" 
             class="absolute z-50 bottom-full mb-2 w-64 bg-white border border-gray-200 rounded-2xl shadow-2xl max-h-60 overflow-y-auto p-2">
            <div class="px-3 py-2 text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50 mb-1">Project Members</div>
            <div v-for="(member, index) in filteredMembers" 
                 :key="member.id"
                 @click="selectMember(member)"
                 :class="{'bg-indigo-50 text-indigo-700': selectedIndex === index, 'text-gray-700': selectedIndex !== index}"
                 class="px-3 py-2 text-xs font-bold cursor-pointer rounded-xl hover:bg-gray-50 flex items-center justify-between transition-colors">
                <div class="flex items-center gap-2">
                    <div class="h-6 w-6 rounded-lg bg-indigo-100 flex items-center justify-center text-[10px] text-indigo-600 font-black">
                        {{ member.name.substring(0, 2).toUpperCase() }}
                    </div>
                    <span>{{ member.name }}</span>
                </div>
                <span class="text-[9px] text-gray-400 uppercase font-bold">{{ member.memberType === 'Client' ? 'Client' : (member.pivot?.role || member.role?.name || 'User') }}</span>
            </div>
        </div>
    </div>
</template>
