<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({ id: { type: Number, required: true } });

const loading = ref(true);
const error = ref('');
const data = ref({ client: null, lead: null, presentations: [], emails: [] });

onMounted(async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await window.axios.get(`/api/clients/${props.id}/details`);
    data.value = res?.data || data.value;
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load client details';
  } finally {
    loading.value = false;
  }
});

const client = computed(() => data.value.client || {});
const lead = computed(() => data.value.lead || null);
const presentations = computed(() => Array.isArray(data.value.presentations) ? data.value.presentations : []);
const emails = computed(() => Array.isArray(data.value.emails) ? data.value.emails : []);

function fullLeadName(l) {
  if (!l) return '';
  const fn = (l.first_name || '').trim();
  const ln = (l.last_name || '').trim();
  return [fn, ln].filter(Boolean).join(' ');
}
</script>

<template>
  <Head :title="`Client: ${client.name || '#' + props.id}`" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="flex items-center gap-3">
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">Client Details</h2>
          <span v-if="lead" class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700" title="Linked Lead">
            Linked Lead
          </span>
        </div>
        <div class="flex items-center gap-2">
          <SecondaryButton as="a" :href="'/clients'">Back to Clients</SecondaryButton>
        </div>
      </div>
    </template>

    <div class="py-6 min-h-screen w-full">
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <div v-if="error" class="mb-4 text-red-600">{{ error }}</div>
            <div v-if="loading" class="animate-pulse space-y-3">
              <div class="h-5 bg-gray-200 rounded w-1/3"></div>
              <div class="h-4 bg-gray-200 rounded w-1/2"></div>
              <div class="h-32 bg-gray-100 rounded"></div>
            </div>

            <div v-if="!loading && !error" class="space-y-8">
              <!-- Profile -->
              <section>
                <h3 class="text-lg font-semibold mb-2">Profile</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                  <div><span class="text-gray-500">Name:</span> <span class="font-medium">{{ client.name || '-' }}</span></div>
                  <div v-if="client.email"><span class="text-gray-500">Email:</span> <span class="font-medium">{{ client.email }}</span></div>
                  <div v-if="client.phone"><span class="text-gray-500">Phone:</span> <span class="font-medium">{{ client.phone }}</span></div>
                  <div v-if="client.address"><span class="text-gray-500">Address:</span> <span class="font-medium">{{ client.address }}</span></div>
                  <div v-if="client.notes"><span class="text-gray-500">Notes:</span> <span class="font-medium">{{ client.notes }}</span></div>
                </div>
                <div v-if="lead" class="mt-4 p-4 rounded-md bg-amber-50 border border-amber-100">
                  <div class="text-sm text-amber-800"><strong>Originated from Lead:</strong> {{ fullLeadName(lead) || ('#' + lead.id) }} <span v-if="lead.email">• {{ lead.email }}</span></div>
                </div>
              </section>

              <!-- Presentations -->
              <section>
                <h3 class="text-lg font-semibold mb-2">Presentations</h3>
                <div v-if="presentations.length === 0" class="text-sm text-gray-500">No presentations found.</div>
                <ul v-else class="divide-y divide-gray-200 rounded-md border border-gray-200">
                  <li v-for="p in presentations" :key="p.id" class="p-3 flex items-center justify-between">
                    <div>
                      <div class="font-medium">{{ p.title }}</div>
                      <div class="text-xs text-gray-500">Type: {{ p.type }} • Source: {{ p.source }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                      <PrimaryButton as="a" :href="`/view/${p.share_token}`" target="_blank" title="Open public preview">Open</PrimaryButton>
                    </div>
                  </li>
                </ul>
              </section>

              <!-- Emails -->
              <section>
                <h3 class="text-lg font-semibold mb-2">Emails</h3>
                <div v-if="emails.length === 0" class="text-sm text-gray-500">No emails found.</div>
                <div v-else class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-2 text-left text-gray-500">Subject</th>
                        <th class="px-4 py-2 text-left text-gray-500">Status</th>
                        <th class="px-4 py-2 text-left text-gray-500">Type</th>
                        <th class="px-4 py-2 text-left text-gray-500">Created</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                      <tr v-for="e in emails" :key="e.id">
                        <td class="px-4 py-2">{{ e.subject || '(no subject)' }}</td>
                        <td class="px-4 py-2">{{ e.status }}</td>
                        <td class="px-4 py-2">{{ e.type }}</td>
                        <td class="px-4 py-2">{{ new Date(e.created_at).toLocaleString() }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </section>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
</style>
