<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['close', 'insert']);

const listItemsInput = ref(''); // Raw text input for list items
const listType = ref('bullet'); // 'bullet' or 'numbered'
const listError = ref('');

const insertList = () => {
    const items = listItemsInput.value.split('\n').map(item => item.trim()).filter(item => item !== '');

    if (items.length === 0) {
        listError.value = 'Please enter at least one list item.';
        return;
    }

    // Format the list items into a structured string
    const listTag = listType.value === 'bullet' ? 'ul' : 'ol';
    let formattedList = `<${listTag}>`;
    items.forEach(item => {
        formattedList += `<li>${item}</li>`;
    });
    formattedList += `</${listTag}>`;

    emit('insert', formattedList);
    closeModal();
};

const closeModal = () => {
    listItemsInput.value = '';
    listType.value = 'bullet';
    listError.value = '';
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="closeModal" max-width="md">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Insert List</h3>
            <div v-if="listError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ listError }}</span>
            </div>
            <div class="mb-4">
                <InputLabel for="list_items" value="List Items (one per line)" />
                <textarea id="list_items" rows="6" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="listItemsInput" placeholder="Enter each list item on a new line"></textarea>
            </div>
            <div class="mb-6">
                <InputLabel for="list_type" value="List Type" />
                <select id="list_type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="listType">
                    <option value="bullet">Bulleted List</option>
                    <option value="numbered">Numbered List</option>
                </select>
            </div>
            <div class="flex justify-end space-x-3">
                <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton @click="insertList">Insert List</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
