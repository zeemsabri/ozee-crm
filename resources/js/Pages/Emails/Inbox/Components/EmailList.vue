<script setup>
import { defineProps, defineEmits, computed } from 'vue';
import moment from 'moment';
import {
  PaperAirplaneIcon,
  InboxArrowDownIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  emails: {
    type: Array,
    default: () => []
  },
  loading: Boolean,
  error: String,
  pagination: Object,
});

const emit = defineEmits(['view-email', 'change-page', 'refresh', 'show-notification']);

const changePage = (page) => {
  emit('change-page', page);
};


const canShowEmail = (email) => {

  if(email.type === 'received' && email.status === 'pending_approval_received') {
    return false;
  }

  return true;

}

const showPermissionAlert = () => {
  // Alert is replaced with an event emission for a custom notification system.
  emit('show-notification', {
    message: 'You do not have permission to view this email. Please contact the project administrator for assistance.',
    type: 'warning'
  });
};

</script>

<template>
  <div class="p-6">

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500 mx-auto"></div>
      <p class="mt-2 text-sm text-gray-500">Loading emails...</p>
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-500">
      {{ error }}
    </div>

    <div v-else-if="emails.length === 0" class="text-center py-8 text-gray-500">
      <p>No emails found.</p>
    </div>

    <div v-else class="overflow-x-auto shadow rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Read Status</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sender</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved By</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
        </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
        <tr v-for="email in emails" :key="email.id" @click="canShowEmail(email) ? $emit('view-email', email) : showPermissionAlert()" class="hover:bg-gray-50 cursor-pointer">
          <td class="px-4 py-3 text-sm font-medium text-gray-900">
            <div class="flex items-center space-x-2">
              <component :is="email.type === 'sent' ? PaperAirplaneIcon : InboxArrowDownIcon" class="h-4 w-4" />
              <span>{{ email.subject }}</span>
            </div>
          </td>
          <td class="px-4 py-3 text-sm text-gray-500">{{ email.type }}</td>
          <td class="px-4 py-3 text-sm">
                            <span v-if="email.type === 'sent'" :class="{'text-green-600': email.read_at, 'text-red-600': !email.read_at}">
                                {{ email.read_at ? 'Read' : 'Unread' }}
                            </span>
            <span v-else class="text-gray-500">
                                N/A
                            </span>
          </td>
          <td class="px-4 py-3 text-sm text-gray-500">{{ email.sender?.name || 'N/A' }}</td>
          <td class="px-4 py-3 text-sm text-gray-500">{{ email.approver?.name || 'N/A' }}</td>
          <td class="px-4 py-3 text-sm text-gray-500">{{ email.conversation?.project?.name || 'N/A' }}</td>
          <td class="px-4 py-3 text-sm">
                            <span
                                :class="{
                                    'px-2 py-1 rounded-full text-xs font-medium': true,
                                    'bg-green-100 text-green-800': email.status === 'sent',
                                    'bg-yellow-100 text-yellow-800': email.status === 'pending_approval' || email.status === 'pending_approval_received',
                                    'bg-blue-100 text-blue-800': email.status === 'received'
                                }"
                            >
                                {{ email.status ? email.status.replace(/_/g, ' ').toUpperCase() : 'N/A' }}
                            </span>
          </td>
          <td class="px-4 py-3 text-sm text-gray-500">
            {{ email.created_at ? moment(email.created_at).format('MMM D, YYYY h:mm A') : 'N/A' }}
          </td>
        </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination.lastPage > 1" class="mt-4 flex justify-center">
      <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
        <button
            @click="changePage(pagination.currentPage - 1)"
            :disabled="pagination.currentPage === 1"
            :class="[
                        pagination.currentPage === 1 ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-50',
                        'relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500'
                    ]"
        >
          <span class="sr-only">Previous</span>
          <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
        </button>
        <template v-for="page in pagination.lastPage" :key="page">
          <button
              v-if="page === 1 || page === pagination.lastPage || (page >= pagination.currentPage - 1 && page <= pagination.currentPage + 1)"
              @click="changePage(page)"
              :class="[
                            page === pagination.currentPage ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50',
                            'relative inline-flex items-center px-4 py-2 border text-sm font-medium'
                        ]"
          >
            {{ page }}
          </button>
          <span
              v-else-if="(page === 2 && pagination.currentPage > 3) || (page === pagination.lastPage - 1 && pagination.currentPage < pagination.lastPage - 2)"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700"
          >
                        ...
                    </span>
        </template>
        <button
            @click="changePage(pagination.currentPage + 1)"
            :disabled="pagination.currentPage === pagination.lastPage"
            :class="[
                        pagination.currentPage === pagination.lastPage ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-50',
                        'relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500'
                    ]"
        >
          <span class="sr-only">Next</span>
          <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
          </svg>
        </button>
      </nav>
    </div>
  </div>
</template>
