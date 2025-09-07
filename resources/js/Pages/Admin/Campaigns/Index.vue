<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import axios from 'axios';

const campaigns = ref([]);
const loading = ref(false);
const errorMsg = ref('');
const pagination = ref({});

async function fetchCampaigns(page = 1) {
    loading.value = true;
    errorMsg.value = '';
    try {
        const { data } = await axios.get(`/api/campaigns?page=${page}`);
        campaigns.value = data.data || [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
        };
    } catch (e) {
        console.error(e);
        errorMsg.value = 'Failed to load campaigns';
    } finally {
        loading.value = false;
    }
}

onMounted(fetchCampaigns);
</script>

<template>
    <Head title="Campaigns" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">AI Lead Gen / Campaigns</h2>
                <Link href="/campaigns/create">
                    <PrimaryButton>Create New Campaign</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="errorMsg" class="mb-4 text-red-600">{{ errorMsg }}</div>
                        <div v-if="loading" class="text-center text-gray-500">Loading campaigns...</div>

                        <div v-if="!campaigns.length && !loading" class="text-center text-gray-500 py-8">
                            <h3 class="text-lg font-medium">No Campaigns Found</h3>
                            <p class="mt-1 text-sm">Get started by creating your first AI lead generation campaign.</p>
                            <Link href="/campaigns/create" class="mt-4 inline-block">
                                <PrimaryButton>Create New Campaign</PrimaryButton>
                            </Link>
                        </div>

                        <div v-else class="space-y-4">
                            <div v-for="c in campaigns" :key="c.id" class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between">
                                    <div>
                                        <Link :href="`/campaigns/${c.id}/edit`" class="font-semibold text-lg text-indigo-600 hover:underline">{{ c.name }}</Link>
                                        <p class="text-sm text-gray-500 mt-1">{{ c.goal || 'No goal set' }}</p>
                                    </div>
                                    <div class="mt-2 sm:mt-0 flex items-center gap-4">
                    <span class="text-xs px-2.5 py-1 rounded-full font-medium" :class="c.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">
                      {{ c.is_active ? 'Active' : 'Inactive' }}
                    </span>
                                        <Link :href="`/campaigns/${c.id}/edit`">
                                            <SecondaryButton>Edit</SecondaryButton>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="pagination.last_page > 1" class="mt-6 flex justify-center items-center space-x-2">
                            <button @click="fetchCampaigns(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="px-4 py-2 text-sm border rounded disabled:opacity-50">
                                Prev
                            </button>
                            <span class="text-sm text-gray-600">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
                            <button @click="fetchCampaigns(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="px-4 py-2 text-sm border rounded disabled:opacity-50">
                                Next
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
