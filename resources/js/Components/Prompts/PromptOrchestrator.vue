<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import UserDataPromptModal from '@/Components/Prompts/UserDataPromptModal.vue';
import promptConfig from '@/Prompts/config.js';

const show = ref(false);
const currentConfigItem = ref(null);
const queue = ref([]);

const ensureAuthHeaders = () => {
  const token = localStorage.getItem('authToken');
  if (token && !axios.defaults.headers.common['Authorization']) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  }
};

const loadUser = async () => {
  ensureAuthHeaders();
  const { data } = await axios.get('/api/user');
  return data;
};

const buildQueue = (user) => {
  const items = [];
  for (const item of promptConfig) {
    try {
      if (typeof item.isMissing === 'function' ? item.isMissing(user) : !user?.[item.column]) {
        items.push(item);
      }
    } catch (e) {
      console.error('Error evaluating prompt config', item, e);
    }
  }
  return items;
};

const nextPrompt = () => {
  if (queue.value.length === 0) {
    currentConfigItem.value = null;
    show.value = false;
    return;
  }
  currentConfigItem.value = queue.value.shift();
  show.value = true;
};

const init = async () => {
  try {
    const user = await loadUser();
    queue.value = buildQueue(user);
    nextPrompt();
  } catch (e) {
    console.error('PromptOrchestrator initialization failed', e);
  }
};

const handleSubmitted = async () => {
  // After saving, reload the user to re-evaluate remaining prompts
  try {
    const user = await loadUser();
    queue.value = buildQueue(user);
  } catch (e) {
    // If user reload fails, keep existing queue
  }
  nextPrompt();
};

onMounted(() => {
  init();
});
</script>

<template>
  <UserDataPromptModal
    v-if="currentConfigItem"
    :show="show"
    :config-item="currentConfigItem"
    @close="nextPrompt"
    @submitted="handleSubmitted"
  />
</template>
