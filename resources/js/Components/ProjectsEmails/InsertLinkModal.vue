<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['close', 'insert']);

const linkText = ref('');
const linkUrl = ref('');
const linkError = ref('');

const insertLink = () => {
    if (!linkText.value.trim()) {
        linkError.value = 'Link text cannot be empty.';
        return;
    }
    let urlToInsert = linkUrl.value.trim();
    if (!urlToInsert.startsWith('http://') && !urlToInsert.startsWith('https://')) {
        urlToInsert = 'http://' + urlToInsert; // Default to http if no protocol
    }

    // Basic URL validation
    try {
        new URL(urlToInsert);
    } catch (e) {
        linkError.value = 'Please enter a valid URL (e.g., https://example.com or www.example.com).';
        return;
    }

    const formattedLink = `[${linkText.value.trim()}] {${urlToInsert}}`;
    emit('insert', formattedLink);
    closeModal();
};

const closeModal = () => {
    linkText.value = '';
    linkUrl.value = '';
    linkError.value = '';
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="closeModal" max-width="md">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Insert Link</h3>
            <div v-if="linkError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ linkError }}</span>
            </div>
            <div class="mb-4">
                <InputLabel for="link_text" value="Link Text" />
                <TextInput id="link_text" type="text" class="mt-1 block w-full" v-model="linkText" @keyup.enter="insertLink" />
            </div>
            <div class="mb-6">
                <InputLabel for="link_url" value="URL" />
                <TextInput id="link_url" type="text" class="mt-1 block w-full" v-model="linkUrl" placeholder="e.g., https://www.example.com" @keyup.enter="insertLink" />
            </div>
            <div class="flex justify-end space-x-3">
                <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton @click="insertLink">Insert</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
