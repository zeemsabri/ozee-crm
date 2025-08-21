<script setup>
import { reactive, watch, ref, onMounted, computed } from 'vue';
import axios from 'axios';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
  initialFilters: Object,
});

const emit = defineEmits(['change', 'reset']);

// Local state for the filters, initialized from props
const localFilters = reactive({ ...props.initialFilters });

// Lists for dropdowns
const projects = ref([]);
const senders = ref([]);

const fetchFilterOptions = async () => {
  try {
    const projectsResponse = await axios.get('/api/projects-simplified');
    projects.value = projectsResponse.data.map(project => ({
      value: project.id,
      label: project.name
    }));

    const usersResponse = await axios.get('/api/users');
    senders.value = usersResponse.data.map(user => ({
      value: user.id,
      label: user.name
    }));
  } catch (err) {
    console.error('Error fetching filter options:', err);
  }
};

const statusOptions = computed(() => {
  // Reset status when type changes to prevent invalid combinations
  localFilters.status = '';

  switch (localFilters.type) {
    case 'sent':
      return [
        { value: '', label: 'All Sent Statuses' },
        { value: 'pending_approval', label: 'Pending Approval' },
        { value: 'sent', label: 'Sent' },
      ];
    case 'received':
      return [
        { value: '', label: 'All Received Statuses' },
        { value: 'received', label: 'Received' },
        { value: 'pending_approval_received', label: 'Pending Approval (Received)' },
      ];
    case 'waiting-approval':
      return [{ value: '', label: 'All' }];
    case 'new':
      return [{ value: '', label: 'All' }];
    default: // 'all'
      return [
        { value: '', label: 'All Statuses' },
        { value: 'sent', label: 'Sent' },
        { value: 'received', label: 'Received' },
        { value: 'pending_approval', label: 'Pending Approval' },
        { value: 'pending_approval_received', label: 'Pending Approval (Received)' },
      ];
  }
});

watch(localFilters, (newFilters) => {
  emit('change', newFilters);
}, { deep: true });

const resetFilters = () => {
  localFilters.type = 'new';
  localFilters.status = '';
  localFilters.startDate = '';
  localFilters.endDate = '';
  localFilters.search = '';
  localFilters.projectId = null;
  localFilters.senderId = '';
};

onMounted(() => {
  fetchFilterOptions();
});
</script>

<template>
  <div class="space-y-4">
    <div>
      <InputLabel for="typeFilter" value="Type" />
      <SelectDropdown
          id="typeFilter"
          v-model="localFilters.type"
          :options="[
                    { value: 'new', label: 'New Emails' },
                    { value: 'waiting-approval', label: 'Waiting for Approval' },
                    { value: 'sent', label: 'Sent' },
                    { value: 'received', label: 'Received' },
                    { value: 'all', label: 'All Emails' },
                ]"
          placeholder="Select Type"
      />
    </div>

    <div v-if="localFilters.type !== 'new' && localFilters.type !== 'waiting-approval'">
      <InputLabel for="statusFilter" value="Status" />
      <SelectDropdown
          id="statusFilter"
          v-model="localFilters.status"
          :options="statusOptions"
          placeholder="All Statuses"
      />
    </div>

    <div>
      <InputLabel for="projectFilter" value="Project" />
      <SelectDropdown
          id="projectFilter"
          v-model="localFilters.projectId"
          :options="[{ value: null, label: 'All Projects' }, ...projects]"
          placeholder="All Projects"
      />
    </div>

    <div>
      <InputLabel for="senderFilter" value="Sender" />
      <SelectDropdown
          id="senderFilter"
          v-model="localFilters.senderId"
          :options="senders"
          placeholder="All Senders"
      />
    </div>

    <div>
      <InputLabel for="startDate" value="From Date" />
      <TextInput type="date" id="startDate" v-model="localFilters.startDate" class="mt-1 block w-full" />
    </div>

    <div>
      <InputLabel for="endDate" value="To Date" />
      <TextInput type="date" id="endDate" v-model="localFilters.endDate" class="mt-1 block w-full" />
    </div>

    <div>
      <InputLabel for="searchFilter" value="Search Content" />
      <div class="mt-1 relative rounded-md shadow-sm">
        <TextInput type="text" id="searchFilter" v-model="localFilters.search" class="block w-full pr-10" placeholder="Search in email content..." />
        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
          <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
          </svg>
        </div>
      </div>
    </div>

    <div class="flex justify-end mt-4">
      <button @click="resetFilters" type="button" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
        Reset Filters
      </button>
    </div>
  </div>
</template>
