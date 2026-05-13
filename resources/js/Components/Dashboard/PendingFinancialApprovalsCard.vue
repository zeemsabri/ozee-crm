<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { WalletIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';
import { formatCurrency } from '@/Utils/currency';

const pendingBillsCount = ref(0);
const pendingInvoicesCount = ref(0);
const loading = ref(true);

const fetchCounts = async () => {
    try {
        const { data } = await axios.get('/api/admin/financial-pending-counts');
        pendingBillsCount.value = data.bills;
        pendingInvoicesCount.value = data.invoices;
    } catch (err) {
        console.error('Failed to fetch pending financial counts', err);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchCounts);
</script>

<template>
    <div v-if="pendingBillsCount > 0 || pendingInvoicesCount > 0" class="bg-white overflow-hidden shadow sm:rounded-lg border border-amber-200">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <WalletIcon class="h-6 w-6 text-amber-600" aria-hidden="true" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Financial Approvals Pending</dt>
                        <dd>
                            <div class="flex items-baseline gap-4">
                                <div v-if="pendingBillsCount > 0" class="text-lg font-semibold text-gray-900">
                                    {{ pendingBillsCount }} Bills
                                </div>
                                <div v-if="pendingInvoicesCount > 0" class="text-lg font-semibold text-gray-900">
                                    {{ pendingInvoicesCount }} Invoices
                                </div>
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-amber-50 px-5 py-3">
            <div class="text-sm">
                <Link :href="route('project-expendables.index')" class="font-medium text-amber-700 hover:text-amber-900 flex items-center gap-1">
                    Review and Approve
                    <ArrowRightIcon class="h-4 w-4" />
                </Link>
            </div>
        </div>
    </div>
</template>
