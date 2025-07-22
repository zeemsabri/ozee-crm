<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Bulk Create Permissions
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <form @submit.prevent="submit">
                            <div class="mb-6">
                                <InputLabel for="category" value="Category" />
                                <div class="flex space-x-2">
                                    <select
                                        id="category"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        v-model="form.category"
                                        required
                                    >
                                        <option value="" disabled>Select a category</option>
                                        <option v-for="category in categories" :key="category" :value="category">
                                            {{ category }}
                                        </option>
                                        <option value="new">+ Add New Category</option>
                                    </select>
                                    <TextInput
                                        v-if="form.category === 'new'"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="newCategory"
                                        placeholder="Enter new category name"
                                        required
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.category" />
                            </div>

                            <div class="mb-6">
                                <InputLabel for="permissions" value="Permissions (one per line)" />
                                <textarea
                                    id="permissions"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.permissions"
                                    rows="10"
                                    required
                                    placeholder="Enter one permission name per line, e.g.:
View Clients
Create Clients
Edit Clients
Delete Clients"
                                ></textarea>
                                <p class="mt-1 text-sm text-gray-500">
                                    Enter one permission name per line. Slugs will be automatically generated.
                                </p>
                                <InputError class="mt-2" :message="form.errors.permissions" />
                            </div>

                            <div class="flex items-center justify-end mt-4">
                                <Link
                                    :href="route('admin.permissions.index')"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                                >
                                    Cancel
                                </Link>

                                <PrimaryButton
                                    class="ml-4"
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Create Permissions
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    categories: Array,
});

const newCategory = ref('');
const form = useForm({
    category: '',
    permissions: '',
});

// Watch for changes to the category selection
watch(() => form.category, (value) => {
    if (value !== 'new') {
        newCategory.value = '';
    }
});

const submit = () => {
    // If a new category is being added, use that value
    if (form.category === 'new' && newCategory.value) {
        form.category = newCategory.value;
    }

    form.post(route('admin.permissions.bulk-store'), {
        onSuccess: () => {
            form.reset();
            newCategory.value = '';
        },
    });
};
</script>
