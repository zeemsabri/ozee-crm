<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const state = reactive({
  sets: [],
  models: [],
  selectedSetId: null,
  categories: [],
  loading: false,
  errors: {},
});

const newSet = reactive({ name: '', allowed_models: [] });
const editSet = reactive({ id: null, name: '', allowed_models: [] });

const newCategory = reactive({ name: '', category_set_id: null });
const editCategory = reactive({ id: null, name: '', category_set_id: null });

const selectedSet = computed(() => state.sets.find(s => s.value === state.selectedSetId));

const loadSets = async () => {
  try {
    const { data } = await window.axios.get('/api/category-sets');
    state.sets = data.map(s => ({ value: s.id, label: s.name, raw: s }));
  } catch (e) {
    console.error('Failed to load sets', e);
  }
};

const loadModels = async () => {
  try {
    const { data } = await window.axios.get('/api/models/available');
    state.models = data; // [{ value, label }]
  } catch (e) {
    console.error('Failed to load models', e);
  }
};

const loadCategories = async (categorySetId) => {
  if (!categorySetId) { state.categories = []; return; }
  try {
    const { data } = await window.axios.get(`/api/category-sets/${categorySetId}/categories`);
    state.categories = data;
  } catch (e) {
    console.error('Failed to load categories', e);
  }
};

onMounted(async () => {
  await Promise.all([loadSets(), loadModels()]);
});

// Set CRUD
const resetErrors = () => { state.errors = {}; };

const createSet = async () => {
  resetErrors();
  try {
    await window.axios.post('/api/category-sets', {
      name: newSet.name,
      allowed_models: newSet.allowed_models,
    });
    newSet.name = '';
    newSet.allowed_models = [];
    await loadSets();
  } catch (e) {
    if (e.response?.status === 422) state.errors = e.response.data.errors || {}; else console.error(e);
  }
};

const startEditSet = (set) => {
  editSet.id = set.raw.id;
  editSet.name = set.raw.name;
  editSet.allowed_models = (set.raw.bindings || []).map(b => b.model_type);
};

const updateSet = async () => {
  if (!editSet.id) return;
  resetErrors();
  try {
    await window.axios.put(`/api/category-sets/${editSet.id}`, {
      name: editSet.name,
      allowed_models: editSet.allowed_models,
    });
    await loadSets();
    // refresh selected set label if it was edited
    if (state.selectedSetId === editSet.id) {
      const s = state.sets.find(x => x.value === editSet.id);
      if (s) s.label = editSet.name;
    }
    // Clear edit form
    editSet.id = null; editSet.name = ''; editSet.allowed_models = [];
  } catch (e) {
    if (e.response?.status === 422) state.errors = e.response.data.errors || {}; else console.error(e);
  }
};

const deleteSet = async (setId) => {
  if (!confirm('Delete this set? This will also delete its categories.')) return;
  try {
    await window.axios.delete(`/api/category-sets/${setId}`);
    if (state.selectedSetId === setId) {
      state.selectedSetId = null;
      state.categories = [];
    }
    await loadSets();
  } catch (e) {
    console.error(e);
  }
};

// Category CRUD
const createCategory = async () => {
  resetErrors();
  try {
    await window.axios.post('/api/categories', {
      name: newCategory.name,
      category_set_id: state.selectedSetId,
    });
    newCategory.name = '';
    await loadCategories(state.selectedSetId);
    await loadSets();
  } catch (e) {
    if (e.response?.status === 422) state.errors = e.response.data.errors || {}; else console.error(e);
  }
};

const startEditCategory = (category) => {
  editCategory.id = category.id;
  editCategory.name = category.name;
  editCategory.category_set_id = state.selectedSetId;
};

const updateCategory = async () => {
  if (!editCategory.id) return;
  resetErrors();
  try {
    await window.axios.put(`/api/categories/${editCategory.id}`, {
      name: editCategory.name,
      category_set_id: editCategory.category_set_id,
    });
    await loadCategories(state.selectedSetId);
    await loadSets();
    editCategory.id = null; editCategory.name = ''; editCategory.category_set_id = null;
  } catch (e) {
    if (e.response?.status === 422) state.errors = e.response.data.errors || {}; else console.error(e);
  }
};

const deleteCategory = async (categoryId) => {
  if (!confirm('Delete this category?')) return;
  try {
    await window.axios.delete(`/api/categories/${categoryId}`);
    await loadCategories(state.selectedSetId);
    await loadSets();
  } catch (e) {
    console.error(e);
  }
};
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Categories</h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Sets column -->
      <div class="lg:col-span-1 space-y-4">
        <div class="border rounded-lg p-4">
          <h2 class="text-sm font-semibold mb-3">Create Set</h2>
          <div class="space-y-3">
            <div>
              <InputLabel value="Name" />
              <TextInput v-model="newSet.name" class="w-full mt-1" />
              <InputError :message="state.errors?.name?.[0]" />
            </div>
            <div>
              <InputLabel value="Allowed Models (optional)" />
              <MultiSelectDropdown :options="state.models" v-model="newSet.allowed_models" :isMulti="true" placeholder="Select models" />
            </div>
            <div class="flex justify-end">
              <PrimaryButton type="button" @click="createSet">Create</PrimaryButton>
            </div>
          </div>
        </div>

        <div class="border rounded-lg p-4">
          <h2 class="text-sm font-semibold mb-3">Sets</h2>
          <div class="space-y-2">
            <div v-for="set in state.sets" :key="set.value" class="flex items-center justify-between p-2 rounded hover:bg-gray-50 border">
              <button class="text-left flex-1" @click="state.selectedSetId = set.value; loadCategories(set.value)">{{ set.label }}</button>
              <div class="flex items-center gap-2">
                <button class="text-xs text-indigo-600" @click="startEditSet(set)">Edit</button>
                <button class="text-xs text-red-600" @click="deleteSet(set.value)">Delete</button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="editSet.id" class="border rounded-lg p-4">
          <h2 class="text-sm font-semibold mb-3">Edit Set</h2>
          <div class="space-y-3">
            <div>
              <InputLabel value="Name" />
              <TextInput v-model="editSet.name" class="w-full mt-1" />
              <InputError :message="state.errors?.name?.[0]" />
            </div>
            <div>
              <InputLabel value="Allowed Models" />
              <MultiSelectDropdown :options="state.models" v-model="editSet.allowed_models" :isMulti="true" placeholder="Select models" />
            </div>
            <div class="flex justify-end gap-2">
              <button type="button" class="text-sm text-gray-600" @click="editSet.id=null; editSet.name=''; editSet.allowed_models=[]">Cancel</button>
              <PrimaryButton type="button" @click="updateSet">Save</PrimaryButton>
            </div>
          </div>
        </div>
      </div>

      <!-- Categories column -->
      <div class="lg:col-span-2 space-y-4">
        <div class="border rounded-lg p-4">
          <h2 class="text-sm font-semibold mb-3">Create Category</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <InputLabel value="Name" />
              <TextInput v-model="newCategory.name" class="w-full mt-1" />
              <InputError :message="state.errors?.name?.[0]" />
            </div>
            <div>
              <InputLabel value="Set" />
              <SelectDropdown :options="state.sets" v-model="state.selectedSetId" placeholder="Select set" />
            </div>
          </div>
          <div class="flex justify-end mt-3">
            <PrimaryButton type="button" :disabled="!state.selectedSetId || !newCategory.name" @click="createCategory">Add Category</PrimaryButton>
          </div>
        </div>

        <div class="border rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold">Categories in {{ selectedSet?.label || '—' }}</h2>
            <div class="text-xs text-gray-500" v-if="!state.selectedSetId">Select a set to view categories</div>
          </div>
          <div v-if="state.selectedSetId" class="space-y-2">
            <div v-for="cat in state.categories" :key="cat.id" class="flex items-center justify-between p-2 rounded border hover:bg-gray-50">
              <div>{{ cat.name }}</div>
              <div class="flex items-center gap-2">
                <button class="text-xs text-indigo-600" @click="startEditCategory(cat)">Edit</button>
                <button class="text-xs text-red-600" @click="deleteCategory(cat.id)">Delete</button>
              </div>
            </div>
            <div v-if="state.categories.length === 0" class="text-sm text-gray-500">No categories yet.</div>
          </div>
        </div>

        <div v-if="editCategory.id" class="border rounded-lg p-4">
          <h2 class="text-sm font-semibold mb-3">Edit Category</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <InputLabel value="Name" />
              <TextInput v-model="editCategory.name" class="w-full mt-1" />
              <InputError :message="state.errors?.name?.[0]" />
            </div>
            <div>
              <InputLabel value="Move to Set" />
              <SelectDropdown :options="state.sets" v-model="editCategory.category_set_id" placeholder="Select set" />
              <InputError :message="state.errors?.category_set_id?.[0]" />
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-3">
            <button type="button" class="text-sm text-gray-600" @click="editCategory.id=null; editCategory.name=''; editCategory.category_set_id=null">Cancel</button>
            <PrimaryButton type="button" @click="updateCategory">Save</PrimaryButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
