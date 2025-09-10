<script setup>
import { ref, watch, defineEmits } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import CustomRichTextEditor from '@/Components/CustomRichTextEditor.vue'; // <-- Correctly renamed
import PlaceholderInserter from '@/Components/PlaceholderInserter.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    isEditing: {
        type: Boolean,
        default: false,
    },
    template: {
        type: Object,
        default: null,
    },
    placeholderDefinitions: {
        type: Array,
        required: true,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'submitted']);

const form = useForm({
    name: '',
    slug: '',
    subject: '',
    body_html: '',
    description: '',
    is_default: false,
    is_private: false,
});

const subjectEditorRef = ref(null);
const bodyEditorRef = ref(null);

const activeEditorRef = ref(null);

const isSidebarExpanded = ref(true);

const close = () => {
    emit('close');
};

const handleSubmitted = () => {
    form.reset();
    emit('submitted');
};

watch(() => props.show, (newValue) => {
    if (newValue && props.isEditing && props.template) {
        form.name = props.template.name;
        form.slug = props.template.slug;
        form.subject = props.template.subject;
        form.body_html = props.template.body_html;
        form.description = props.template.description;
        form.is_default = props.template.is_default;
        form.is_private = props.template.is_private ?? false;
    } else if (newValue && !props.isEditing) {
        form.reset();
    }
});

watch(() => props.isEditing, (newValue) => {
    if (!newValue) {
        form.reset();
    }
});

const insertPlaceholder = (placeholderText) => {
    if (!activeEditorRef.value) {
        console.error('No active editor selected to insert placeholder.');
        return;
    }

    const textarea = activeEditorRef.value.$el;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;

    const newText = text.substring(0, start) + placeholderText + text.substring(end);

    // Update the correct form property
    if (activeEditorRef.value.id === 'subject') {
        form.subject = newText;
    } else if (activeEditorRef.value.id === 'body_html') {
        form.body_html = newText;
    }

    // Wait for Vue to update the DOM before setting cursor position
    nextTick(() => {
        const newCursorPosition = start + placeholderText.length;
        textarea.setSelectionRange(newCursorPosition, newCursorPosition);
        textarea.focus();
    });
};

const setActiveEditor = (editorRef) => {
    activeEditorRef.value = editorRef;
};
</script>

<template>
    <BaseFormModal
        :show="show"
        :title="isEditing ? 'Edit Email Template' : 'Create New Email Template'"
        :api-endpoint="isEditing ? `/api/email-templates/${template.id}` : '/api/email-templates'"
        :http-method="isEditing ? 'put' : 'post'"
        :form-data="form.data()"
        :submit-button-text="isEditing ? 'Update Template' : 'Save Template'"
        success-message="Email template saved successfully!"
        @close="close"
        @submitted="handleSubmitted"
    >
        <template #default="{ errors }">
            <div class="flex">
                <!-- Left Content Panel (Form Inputs) -->
                <div class="flex-1 pr-6">
                    <div class="mb-4">
                        <InputLabel for="name" value="Template Name" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError class="mt-2" :message="errors.name?.[0]" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="slug" value="Template Slug" />
                        <TextInput id="slug" v-model="form.slug" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="errors.slug?.[0]" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="subject" value="Subject Line" />
                        <CustomRichTextEditor
                            id="subject"
                            v-model="form.subject"
                            :definitions="placeholderDefinitions"
                            :rows="2"
                            required
                            @focus="setActiveEditor($event)"
                        />
                        <InputError class="mt-2" :message="errors.subject?.[0]" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="body_html" value="Email Body (HTML)" />
                        <CustomRichTextEditor
                            id="body_html"
                            v-model="form.body_html"
                            :definitions="placeholderDefinitions"
                            :rows="10"
                            required
                            @focus="setActiveEditor($event)"
                        />
                        <InputError class="mt-2" :message="errors.body_html?.[0]" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full"
                        ></textarea>
                        <InputError class="mt-2" :message="errors.description?.[0]" />
                    </div>
                    <div class="mb-4 space-y-2">
                        <label class="flex items-center">
                            <Checkbox name="is_default" v-model:checked="form.is_default" />
                            <span class="ml-2 text-sm text-gray-600">Is a system default template?</span>
                        </label>
                        <InputError class="mt-2" :message="errors.is_default?.[0]" />
                        <label class="flex items-center">
                            <Checkbox name="is_private" v-model:checked="form.is_private" />
                            <span class="ml-2 text-sm text-gray-600">Private (visible only to users with permission)</span>
                        </label>
                        <InputError class="mt-2" :message="errors.is_private?.[0]" />
                    </div>
                </div>

                <!-- Right Sidebar Panel (Placeholder List) -->
                <div class="w-64 border-l border-gray-200 pl-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-900">Placeholders</h4>
                        <button @click="isSidebarExpanded = !isSidebarExpanded" type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                            <svg v-if="isSidebarExpanded" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                    <div v-if="isSidebarExpanded" class="space-y-2">
                        <p class="text-xs text-gray-500">Click to insert into the active editor.</p>
                        <div class="max-h-96 overflow-y-auto pr-2">
                            <button
                                v-for="placeholder in placeholderDefinitions"
                                :key="placeholder.id"
                                @click.prevent="insertPlaceholder(`{{ ${placeholder.name} }}`)"
                                type="button"
                                class="w-full text-left p-2 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150 text-sm mb-2"
                            >
                                <span class="font-medium text-gray-900">{{ placeholder.name }}</span>
                                <p class="text-xs text-gray-500">{{ placeholder.description }}</p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>
