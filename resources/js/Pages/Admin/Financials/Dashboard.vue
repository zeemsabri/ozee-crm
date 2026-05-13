<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { WalletIcon, DocumentTextIcon, CheckCircleIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { formatCurrency } from '@/Utils/currency';

const stats = ref({
    pendingBills: 0,
    pendingInvoices: 0,
});
const loading = ref(true);

const fetchStats = async () => {
    try {
        const { data } = await axios.get('/api/admin/financial-pending-counts');
        stats.value = {
            pendingBills: data.bills,
            pendingInvoices: data.invoices,
        };
    } catch (err) {
        console.error('Failed to fetch stats', err);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchStats);
</script>

<template>
    <Head title="Financial Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Financial Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Pending Bills Card -->
                    <div class="bg-white overflow-hidden shadow sm:rounded-lg border border-amber-200">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-3 bg-amber-100 rounded-lg">
                                    <WalletIcon class="h-8 w-8 text-amber-600" />
                                </div>
                                <div class="ml-5">
                                    <h3 class="text-sm font-medium text-gray-500 uppercase">Pending Bills</h3>
                                    <p class="text-2xl font-bold text-gray-900">{{ stats.pendingBills }}</p>
                                </div>
                            </div>
                            <div class="mt-6">
                                <Link :href="route('admin.financials.bills')" class="text-sm font-medium text-amber-700 hover:text-amber-900 flex items-center gap-1">
                                    Manage Bills <ArrowRightIcon class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Invoices Card -->
                    <div class="bg-white overflow-hidden shadow sm:rounded-lg border border-blue-200">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <DocumentTextIcon class="h-8 w-8 text-blue-600" />
                                </div>
                                <div class="ml-5">
                                    <h3 class="text-sm font-medium text-gray-500 uppercase">Pending Invoices</h3>
                                    <p class="text-2xl font-bold text-gray-900">{{ stats.pendingInvoices }}</p>
                                </div>
                            </div>
                            <div class="mt-6">
                                <Link :href="route('admin.financials.invoices')" class="text-sm font-medium text-blue-700 hover:text-blue-900 flex items-center gap-1">
                                    Manage Invoices <ArrowRightIcon class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Xero Integration Card -->
                    <div class="bg-white overflow-hidden shadow sm:rounded-lg border border-gray-200">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-3 bg-gray-100 rounded-lg">
                                    <CheckCircleIcon class="h-8 w-8 text-gray-600" />
                                </div>
                                <div class="ml-5">
                                    <h3 class="text-sm font-medium text-gray-500 uppercase">Xero Status</h3>
                                    <p class="text-lg font-semibold text-gray-700">Connected</p>
                                </div>
                            </div>
                            <div class="mt-6">
                                <Link :href="route('admin.xero.index')" class="text-sm font-medium text-gray-700 hover:text-gray-900 flex items-center gap-1">
                                    Xero Settings <ArrowRightIcon class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
