<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { confirmPrompt } from '@/Utils/notification.js';

const props = defineProps({
  schedules: { type: Object, required: true },
});

function statusBadge(active) {
  return active
    ? 'inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20'
    : 'inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10';
}

function toggleSchedule(id) {
  router.patch(route('schedules.toggle', id));
}

async function deleteSchedule(id) {
  const confirmed = await confirmPrompt('Are you sure you want to delete this schedule? This action cannot be undone.', {
    confirmText: 'Delete',
    cancelText: 'Cancel',
    type: 'warning',
  });
  if (confirmed) {
    router.delete(route('schedules.destroy', id));
  }
}
</script>

<template>
  <Head title="Schedules" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Schedules</h2>
        <Link :href="route('schedules.create')" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
          Create Schedule
        </Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Linked Item</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recurrence</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Run</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Run</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="row in schedules.data" :key="row.id">
                    <td class="px-3 py-2 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">{{ row.name }}</div>
                      <div v-if="row.description" class="text-xs text-gray-500">{{ row.description }}</div>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                      <div class="text-sm text-gray-900">{{ row.scheduled_item?.name || '-' }}</div>
                      <div class="text-xs text-gray-500">{{ row.scheduled_item ? row.scheduled_item.type + ' #' + row.scheduled_item.id : '' }}</div>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                      <div>{{ row.recurrence_summary }}</div>
                      <div class="text-xs text-gray-500">{{ row.recurrence_pattern }}</div>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">{{ row.next_run_at || '-' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">{{ row.last_run_at || '-' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                      <span :class="statusBadge(row.is_active)">{{ row.is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-sm">
                      <div class="flex items-center gap-2">
                        <Link :href="route('schedules.edit', row.id)" class="px-2 py-1 border rounded hover:bg-gray-50">Edit</Link>
                        <button @click="toggleSchedule(row.id)" class="px-2 py-1 border rounded" :class="row.is_active ? 'text-amber-700 border-amber-300 hover:bg-amber-50' : 'text-green-700 border-green-300 hover:bg-green-50'">
                          {{ row.is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                        <button @click="deleteSchedule(row.id)" class="px-2 py-1 border rounded text-red-700 border-red-300 hover:bg-red-50">Delete</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 flex items-center justify-between" v-if="schedules.links">
              <div class="text-sm text-gray-500">
                Showing
                <span class="font-medium">{{ schedules.from }}</span>
                to
                <span class="font-medium">{{ schedules.to }}</span>
                of
                <span class="font-medium">{{ schedules.total }}</span>
                results
              </div>
              <div class="flex space-x-2">
                <Link v-for="link in schedules.links" :key="link.url + link.label" :href="link.url || '#'" v-html="link.label"
                      class="px-3 py-1 rounded border text-sm"
                      :class="{
                        'bg-indigo-600 text-white border-indigo-600': link.active,
                        'text-gray-700 bg-white border-gray-300 hover:bg-gray-50': !link.active,
                        'opacity-50 pointer-events-none': !link.url
                      }"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
