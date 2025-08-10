<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { UserGroupIcon, EnvelopeIcon, PhoneIcon, GlobeAltIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    canViewClientContacts: {
        type: Boolean,
        default: false,
    },
});

const clients = ref([]);
const loading = ref(true);
const error = ref(null);

const fetchClients = async () => {
    loading.value = true;
    error.value = null;
    if (!props.canViewClientContacts) {
        error.value = "You don't have permission to view project clients.";
        loading.value = false;
        return;
    }

    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/clients?type=clients`);
        clients.value = response.data;
    } catch (e) {
        console.error('Failed to fetch project clients:', e);
        error.value = e.response?.data?.message || 'Failed to load client data.';
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchClients();
});

// Watch for changes in permission prop to re-fetch if permissions are granted dynamically
watch(() => props.canViewClientContacts, () => {
    fetchClients();
});
</script>

<template>
    <div class="bg-white p-4 rounded-xl shadow-md transition-shadow hover:shadow-lg flex flex-col h-full">
        <h4 class="text-sm font-semibold text-gray-900 mb-3">Project Clients</h4>

        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <div class="space-y-2 w-full">
                <div class="h-3 bg-gray-200 rounded animate-pulse w-3/4 mx-auto"></div>
                <div class="h-3 bg-gray-200 rounded animate-pulse w-2/3 mx-auto"></div>
            </div>
        </div>

        <div v-else-if="error" class="flex-1 flex items-center justify-center text-red-600 text-xs text-center">
            <p>{{ error }}</p>
        </div>

        <div v-else class="flex-1 flex flex-col overflow-hidden">
            <div v-if="clients.length" class="flex-1 overflow-y-auto">
                <div v-for="client in clients" :key="client.id" class="space-y-3">
                    <!-- Main Client Info -->
                    <div class="p-2 bg-gray-50 rounded-lg flex items-center space-x-2">
                        <div class="flex-shrink-0 bg-indigo-500 rounded-full h-8 w-8 flex items-center justify-center text-white font-medium text-xs">
                            {{ client.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ client.name }}</p>
                            <p class="text-xs text-gray-500 flex items-center mt-0.5">
                                <span v-if="client.email" class="flex items-center space-x-1 mr-2">
                                     <EnvelopeIcon class="h-3 w-3"/>
                                     <span>{{ client.email }}</span>
                                </span>
                                <span v-if="client.phone" class="flex items-center space-x-1">
                                     <PhoneIcon class="h-3 w-3"/>
                                     <span>{{ client.phone }}</span>
                                </span>
                            </p>
                        </div>
                        <a v-if="client.website" :href="client.website" target="_blank" class="text-indigo-600 hover:text-indigo-800 transition-colors">
                            <GlobeAltIcon class="h-5 w-5" />
                        </a>
                    </div>

                    <!-- Client Contacts -->
                    <div v-if="client.contacts && client.contacts.length" class="mt-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2 ml-2">Client Contacts</p>
                        <ul class="space-y-1">
                            <li v-for="contact in client.contacts" :key="contact.id" class="flex items-center justify-between p-2 bg-gray-100 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <div class="flex-shrink-0 bg-gray-300 rounded-full h-6 w-6 flex items-center justify-center text-white font-medium text-xs">
                                        {{ contact.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-gray-900">{{ contact.name }}</p>
                                        <p v-if="contact.role" class="text-xs text-gray-500">{{ contact.role }}</p>
                                    </div>
                                </div>
                                <a v-if="contact.email" :href="'mailto:' + contact.email" class="text-gray-400 hover:text-indigo-600 transition-colors" title="Email Contact">
                                    <EnvelopeIcon class="h-4 w-4"/>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <p v-else class="text-gray-400 text-xs flex-1 flex items-center justify-center text-center">
                No clients assigned. <br/> To view this card, please assign a client to the project.
            </p>
        </div>
    </div>
</template>
