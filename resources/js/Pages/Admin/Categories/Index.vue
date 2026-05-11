<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

const loading = ref(false);
const errors = ref({});
const step = ref(1);

const sets = ref([]);
const models = ref([]);
const categories = ref([]);

const setMode = ref('existing');
const selectedSetId = ref(null);

const setForm = reactive({
    id: null,
    name: '',
    allowed_models: [],
});

const newCategoryName = ref('');
const categoryEdit = reactive({
    id: null,
    name: '',
});

const selectedSet = computed(() => sets.value.find((set) => set.id === selectedSetId.value) || null);
const selectedSetBindings = computed(() => selectedSet.value?.bindings?.map((item) => item.model_type) || []);
const canContinueStepOne = computed(() => {
    if (setMode.value === 'new') return true;
    return !!selectedSetId.value;
});

const clearErrors = () => {
    errors.value = {};
};

const toOptionLabel = (modelType) => {
    const found = models.value.find((item) => item.value === modelType);
    return found ? found.label : modelType;
};

const setFormFromSelected = () => {
    if (!selectedSet.value) {
        setForm.id = null;
        setForm.name = '';
        setForm.allowed_models = [];
        return;
    }

    setForm.id = selectedSet.value.id;
    setForm.name = selectedSet.value.name;
    setForm.allowed_models = [...selectedSetBindings.value];
};

const loadSets = async () => {
    const response = await window.axios.get('/api/category-sets');
    sets.value = response.data || [];
};

const loadModels = async () => {
    const response = await window.axios.get('/api/models/available');
    models.value = response.data || [];
};

const loadCategories = async () => {
    if (!selectedSetId.value) {
        categories.value = [];
        return;
    }

    const response = await window.axios.get(`/api/category-sets/${selectedSetId.value}/categories`);
    categories.value = response.data || [];
};

const initializeWizard = async () => {
    loading.value = true;
    clearErrors();

    try {
        await Promise.all([loadSets(), loadModels()]);

        if (sets.value.length > 0) {
            setMode.value = 'existing';
            selectedSetId.value = sets.value[0].id;
            setFormFromSelected();
            await loadCategories();
        } else {
            setMode.value = 'new';
            selectedSetId.value = null;
            setForm.id = null;
            setForm.name = '';
            setForm.allowed_models = [];
            categories.value = [];
        }
    } catch (error) {
        console.error('Failed to initialize categories wizard', error);
    } finally {
        loading.value = false;
    }
};

const goToStep = (targetStep) => {
    if (targetStep < 1 || targetStep > 3) return;
    step.value = targetStep;
};

const continueFromStepOne = async () => {
    clearErrors();

    if (setMode.value === 'existing' && !selectedSetId.value) {
        errors.value.selected_set = ['Please select a category set to continue.'];
        return;
    }

    if (setMode.value === 'existing') {
        setFormFromSelected();
        await loadCategories();
    } else {
        setForm.id = null;
        setForm.name = '';
        setForm.allowed_models = [];
        categories.value = [];
    }

    step.value = 2;
};

const saveSetAndContinue = async () => {
    clearErrors();

    if (!setForm.name.trim()) {
        errors.value.name = ['Set name is required.'];
        return;
    }

    try {
        loading.value = true;

        if (setMode.value === 'new') {
            const response = await window.axios.post('/api/category-sets', {
                name: setForm.name,
                allowed_models: setForm.allowed_models,
            });

            selectedSetId.value = response.data.id;
            setMode.value = 'existing';
        } else {
            await window.axios.put(`/api/category-sets/${setForm.id}`, {
                name: setForm.name,
                allowed_models: setForm.allowed_models,
            });
        }

        await loadSets();
        if (selectedSetId.value) {
            const refreshed = sets.value.find((set) => set.id === selectedSetId.value);
            if (refreshed) {
                setForm.id = refreshed.id;
                setForm.name = refreshed.name;
                setForm.allowed_models = (refreshed.bindings || []).map((item) => item.model_type);
            }
        }

        await loadCategories();
        step.value = 3;
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {};
        } else {
            console.error('Failed to save set', error);
        }
    } finally {
        loading.value = false;
    }
};

const createCategory = async () => {
    clearErrors();

    if (!newCategoryName.value.trim()) {
        errors.value.category_name = ['Category name is required.'];
        return;
    }

    if (!selectedSetId.value) {
        errors.value.category_name = ['Please save or select a category set first.'];
        return;
    }

    try {
        await window.axios.post('/api/categories', {
            name: newCategoryName.value,
            category_set_id: selectedSetId.value,
        });

        newCategoryName.value = '';
        await Promise.all([loadCategories(), loadSets()]);
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {};
        } else {
            console.error('Failed to create category', error);
        }
    }
};

const startEditCategory = (category) => {
    categoryEdit.id = category.id;
    categoryEdit.name = category.name;
};

const cancelEditCategory = () => {
    categoryEdit.id = null;
    categoryEdit.name = '';
};

const saveEditCategory = async () => {
    if (!categoryEdit.id) return;
    clearErrors();

    if (!categoryEdit.name.trim()) {
        errors.value.edit_category_name = ['Category name is required.'];
        return;
    }

    try {
        await window.axios.put(`/api/categories/${categoryEdit.id}`, {
            name: categoryEdit.name,
            category_set_id: selectedSetId.value,
        });

        cancelEditCategory();
        await loadCategories();
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {};
        } else {
            console.error('Failed to update category', error);
        }
    }
};

const deleteCategory = async (category) => {
    if (!confirm(`Delete category "${category.name}"?`)) return;

    try {
        await window.axios.delete(`/api/categories/${category.id}`);
        await Promise.all([loadCategories(), loadSets()]);
    } catch (error) {
        console.error('Failed to delete category', error);
    }
};

const restartWizard = () => {
    step.value = 1;
    newCategoryName.value = '';
    cancelEditCategory();
};

watch(selectedSetId, async () => {
    if (setMode.value !== 'existing') return;
    setFormFromSelected();
    await loadCategories();
});

onMounted(() => {
    initializeWizard();
});
</script>

<template>
    <Head title="Categories" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Categories Wizard
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-3">
                            <button
                                type="button"
                                class="rounded-lg border px-4 py-3 text-left"
                                :class="step === 1 ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-white'"
                                @click="goToStep(1)"
                            >
                                <div class="text-sm font-semibold text-gray-900">Step 1</div>
                                <div class="text-sm text-gray-600">Choose a set</div>
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border px-4 py-3 text-left"
                                :class="step === 2 ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-white'"
                                :disabled="step < 2"
                                @click="goToStep(2)"
                            >
                                <div class="text-sm font-semibold text-gray-900">Step 2</div>
                                <div class="text-sm text-gray-600">Configure set</div>
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border px-4 py-3 text-left"
                                :class="step === 3 ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-white'"
                                :disabled="step < 3"
                                @click="goToStep(3)"
                            >
                                <div class="text-sm font-semibold text-gray-900">Step 3</div>
                                <div class="text-sm text-gray-600">Manage categories</div>
                            </button>
                        </div>

                        <div v-if="loading" class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                            Loading wizard data...
                        </div>

                        <div v-else>
                            <div v-if="step === 1" class="space-y-6">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Start by selecting your mode</h3>
                                    <p class="mt-1 text-sm text-gray-600">
                                        Pick an existing set to update or create a new set from scratch.
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border p-4 text-left"
                                        :class="setMode === 'existing' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
                                        @click="setMode = 'existing'"
                                    >
                                        <div class="font-semibold text-gray-900">Update Existing Set</div>
                                        <div class="mt-1 text-sm text-gray-600">Edit a set and adjust categories.</div>
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-lg border p-4 text-left"
                                        :class="setMode === 'new' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
                                        @click="setMode = 'new'"
                                    >
                                        <div class="font-semibold text-gray-900">Create New Set</div>
                                        <div class="mt-1 text-sm text-gray-600">Start a new category structure.</div>
                                    </button>
                                </div>

                                <div v-if="setMode === 'existing'" class="space-y-3">
                                    <InputLabel value="Available category sets" />
                                    <select
                                        v-model="selectedSetId"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option :value="null">Select set</option>
                                        <option v-for="set in sets" :key="set.id" :value="set.id">
                                            {{ set.name }} ({{ set.categories_count || 0 }} categories)
                                        </option>
                                    </select>
                                    <InputError :message="errors.selected_set?.[0]" />
                                </div>

                                <div class="flex justify-end">
                                    <PrimaryButton :disabled="!canContinueStepOne" @click="continueFromStepOne">
                                        Continue
                                    </PrimaryButton>
                                </div>
                            </div>

                            <div v-if="step === 2" class="space-y-6">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Configure the category set</h3>
                                    <p class="mt-1 text-sm text-gray-600">
                                        Define set name and which models can use it.
                                    </p>
                                </div>

                                <div>
                                    <InputLabel value="Set name" />
                                    <TextInput v-model="setForm.name" class="mt-1 block w-full" />
                                    <InputError :message="errors.name?.[0]" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel value="Allowed models" />
                                    <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                                        <label
                                            v-for="modelOption in models"
                                            :key="modelOption.value"
                                            class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm"
                                        >
                                            <input
                                                :value="modelOption.value"
                                                v-model="setForm.allowed_models"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            />
                                            {{ modelOption.label }}
                                        </label>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <SecondaryButton @click="goToStep(1)">Back</SecondaryButton>
                                    <PrimaryButton @click="saveSetAndContinue">Save Set and Continue</PrimaryButton>
                                </div>
                            </div>

                            <div v-if="step === 3" class="space-y-6">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Manage categories</h3>
                                        <p class="text-sm text-gray-600">
                                            Working on: <span class="font-medium text-gray-800">{{ selectedSet?.name || 'No set selected' }}</span>
                                        </p>
                                    </div>
                                    <SecondaryButton @click="restartWizard">Start Over</SecondaryButton>
                                </div>

                                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                    <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                                        <h4 class="font-semibold text-gray-900">Add new category</h4>
                                        <div>
                                            <InputLabel value="Category name" />
                                            <TextInput v-model="newCategoryName" class="mt-1 block w-full" />
                                            <InputError :message="errors.category_name?.[0] || errors.name?.[0]" class="mt-2" />
                                        </div>
                                        <div class="flex justify-end">
                                            <PrimaryButton @click="createCategory">Add Category</PrimaryButton>
                                        </div>
                                    </div>

                                    <div class="space-y-2 rounded-lg border border-gray-200 p-4">
                                        <h4 class="font-semibold text-gray-900">Set summary</h4>
                                        <div class="text-sm text-gray-700">
                                            <div><span class="font-medium">Name:</span> {{ selectedSet?.name || setForm.name || 'Not set' }}</div>
                                            <div class="mt-2">
                                                <span class="font-medium">Allowed Models:</span>
                                                <div v-if="(setForm.allowed_models || []).length > 0" class="mt-1 flex flex-wrap gap-1">
                                                    <span
                                                        v-for="modelType in setForm.allowed_models"
                                                        :key="modelType"
                                                        class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700"
                                                    >
                                                        {{ toOptionLabel(modelType) }}
                                                    </span>
                                                </div>
                                                <div v-else class="mt-1 text-xs text-gray-500">No model restriction (global set).</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-200 p-4">
                                    <h4 class="mb-3 font-semibold text-gray-900">Existing categories</h4>

                                    <div v-if="categories.length === 0" class="text-sm text-gray-500">
                                        No categories yet. Add your first one above.
                                    </div>

                                    <div v-else class="space-y-2">
                                        <div
                                            v-for="category in categories"
                                            :key="category.id"
                                            class="flex flex-col gap-2 rounded border border-gray-200 p-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div v-if="categoryEdit.id !== category.id" class="text-sm font-medium text-gray-800">
                                                {{ category.name }}
                                            </div>

                                            <div v-else class="w-full sm:w-80">
                                                <TextInput v-model="categoryEdit.name" class="block w-full" />
                                                <InputError :message="errors.edit_category_name?.[0] || errors.name?.[0]" class="mt-2" />
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <template v-if="categoryEdit.id !== category.id">
                                                    <button type="button" class="text-sm text-indigo-600 hover:text-indigo-800" @click="startEditCategory(category)">
                                                        Edit
                                                    </button>
                                                    <button type="button" class="text-sm text-red-600 hover:text-red-800" @click="deleteCategory(category)">
                                                        Delete
                                                    </button>
                                                </template>
                                                <template v-else>
                                                    <button type="button" class="text-sm text-gray-600 hover:text-gray-800" @click="cancelEditCategory">
                                                        Cancel
                                                    </button>
                                                    <button type="button" class="text-sm text-indigo-600 hover:text-indigo-800" @click="saveEditCategory">
                                                        Save
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
