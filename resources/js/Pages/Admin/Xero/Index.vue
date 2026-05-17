<template>
    <Head title="Xero Integration" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Xero Integration
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Connect one Xero organization for the whole CRM.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        :href="route('admin.xero.connect')"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                    >
                        {{ connection ? 'Reconnect Xero' : 'Connect to Xero' }}
                    </a>

                    <DangerButton
                        v-if="connection"
                        :disabled="disconnectForm.processing"
                        @click="disconnect"
                    >
                        Disconnect
                    </DangerButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div
                    v-if="$page.props.flash?.error"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ $page.props.flash.error }}
                </div>

                <section class="overflow-hidden rounded-lg bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Connection Status</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Only a Super Admin can authorize or switch the connected Xero organization.
                                </p>
                            </div>

                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                                :class="statusClasses"
                            >
                                {{ statusLabel }}
                            </span>
                        </div>
                    </div>

                    <div v-if="!connection" class="px-6 py-12 text-center">
                        <div class="mx-auto max-w-2xl">
                            <h4 class="text-lg font-semibold text-gray-900">No Xero connection configured</h4>
                            <p class="mt-2 text-sm text-gray-500">
                                Start the OAuth flow with your Super Admin account, then choose the Xero organization this CRM should use for invoices and bills.
                            </p>
                        </div>
                    </div>

                    <div v-else class="grid gap-6 px-6 py-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                        <div class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Connected By</p>
                                    <p class="mt-2 text-sm font-medium text-gray-900">
                                        {{ connection.connected_by?.name || 'Unknown user' }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ connection.connected_by?.email || 'No email available' }}
                                    </p>
                                </div>

                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Selected Organization</p>
                                    <p class="mt-2 text-sm font-medium text-gray-900">
                                        {{ connection.selected_tenant_name || 'Selection required' }}
                                    </p>
                                    <p class="mt-1 break-all text-xs text-gray-500">
                                        {{ connection.selected_tenant_id || 'No tenant selected yet' }}
                                    </p>
                                </div>

                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Last Connected</p>
                                    <p class="mt-2 text-sm text-gray-900">
                                        {{ formatDatetime(connection.last_connected_at) }}
                                    </p>
                                </div>

                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Access Token Expires</p>
                                    <p class="mt-2 text-sm text-gray-900">
                                        {{ formatDatetime(connection.access_token_expires_at) }}
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Scopes</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="scope in scopes"
                                        :key="scope"
                                        class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700"
                                    >
                                        {{ scope }}
                                    </span>
                                    <span v-if="scopes.length === 0" class="text-sm text-gray-500">
                                        No scopes recorded.
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="connection.last_error"
                                class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                            >
                                <p class="font-semibold">Latest Xero error</p>
                                <p class="mt-1 break-words">{{ connection.last_error }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200">
                            <div class="border-b border-gray-200 px-4 py-4">
                                <h4 class="text-base font-semibold text-gray-900">Authorized Organizations</h4>
                                <p class="mt-1 text-sm text-gray-500">
                                    Choose the Xero organization the CRM should use for all downstream accounting actions.
                                </p>
                            </div>

                            <div v-if="tenants.length === 0" class="px-4 py-8 text-sm text-gray-500">
                                No authorized organizations returned from Xero.
                            </div>

                            <div v-else class="divide-y divide-gray-200">
                                <div
                                    v-for="tenant in tenants"
                                    :key="tenant.id"
                                    class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ tenant.tenant_name || 'Unnamed Xero organization' }}
                                            </p>
                                            <span
                                                v-if="tenant.is_selected"
                                                class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                                            >
                                                Active
                                            </span>
                                        </div>
                                        <p class="mt-1 break-all text-xs text-gray-500">{{ tenant.tenant_id }}</p>
                                        <p class="mt-2 text-xs uppercase tracking-wide text-gray-400">
                                            {{ tenant.tenant_type || 'Unknown type' }}
                                        </p>
                                    </div>

                                    <PrimaryButton
                                        :disabled="tenantForm.processing || tenant.is_selected"
                                        @click="selectTenant(tenant.tenant_id)"
                                    >
                                        {{ tenant.is_selected ? 'Selected' : 'Use This Organization' }}
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Account Mapping Section -->
                <section v-if="connection" class="overflow-hidden rounded-lg bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-gray-900">Xero Account Mapping</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Map CRM transaction types to Xero Chart of Accounts codes.
                        </p>
                    </div>

                    <div class="px-6 py-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Transaction Type
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Xero Account Code
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="type in transaction_types" :key="type.id">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                            {{ type.name }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            <input
                                                v-model="mappingForms[type.id].xero_account_code"
                                                type="text"
                                                class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="e.g. 620"
                                            />
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                            <button
                                                @click="saveMapping(type.id)"
                                                :disabled="mappingForms[type.id].processing"
                                                class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50"
                                            >
                                                {{ mappingForms[type.id].processing ? 'Saving...' : 'Save' }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- CRM Services Account Mapping Section -->
                <section v-if="connection" class="overflow-hidden rounded-lg bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-gray-900">CRM Services Mapping</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Map CRM services (e.g. SEO, Website Designing) to Xero Item Codes.
                        </p>
                    </div>

                    <div class="px-6 py-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            CRM Service
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Xero Item Code
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="service in crm_services" :key="service.id">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                            {{ service.name }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            <input
                                                v-model="crmServiceForms[service.id].xero_item_code"
                                                type="text"
                                                class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="e.g. ItemCode123"
                                            />
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                            <button
                                                @click="saveCrmMapping(service.id)"
                                                :disabled="crmServiceForms[service.id].processing"
                                                class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50"
                                            >
                                                {{ crmServiceForms[service.id].processing ? 'Saving...' : 'Save' }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { success as notifySuccess, error as notifyError } from '@/Utils/notification';
import axios from 'axios';

const props = defineProps({
    connection: {
        type: Object,
        default: null,
    },
    transaction_types: {
        type: Array,
        default: () => [],
    },
    crm_services: {
        type: Array,
        default: () => [],
    },
});

const tenantForm = useForm({
    tenant_id: '',
});

const disconnectForm = useForm({});

// Mapping forms for each transaction type
const mappingForms = reactive(
    props.transaction_types.reduce((acc, type) => {
        acc[type.id] = useForm({
            name: type.name,
            xero_account_code: type.xero_account_code || '',
        });
        return acc;
    }, {})
);

// Mapping forms for CRM services
const crmServiceForms = reactive(
    props.crm_services.reduce((acc, service) => {
        acc[service.id] = useForm({
            name: service.name,
            xero_item_code: service.xero_item_code || '',
        });
        return acc;
    }, {})
);

const tenants = computed(() => props.connection?.tenants ?? []);
const scopes = computed(() => (props.connection?.scope ? props.connection.scope.split(' ').filter(Boolean) : []));

const statusLabel = computed(() => {
    if (!props.connection) {
        return 'Disconnected';
    }

    return String(props.connection.status || 'disconnected').replaceAll('_', ' ');
});

const statusClasses = computed(() => {
    switch (props.connection?.status) {
        case 'connected':
            return 'bg-emerald-100 text-emerald-700';
        case 'pending_tenant_selection':
            return 'bg-amber-100 text-amber-800';
        case 'reconnect_required':
            return 'bg-red-100 text-red-700';
        default:
            return 'bg-gray-100 text-gray-600';
    }
});

const selectTenant = (tenantId) => {
    tenantForm.tenant_id = tenantId;
    tenantForm.post(route('admin.xero.select-tenant'));
};

const disconnect = () => {
    disconnectForm.delete(route('admin.xero.disconnect'));
};

const saveMapping = (typeId) => {
    const form = mappingForms[typeId];
    form.put(`/api/transaction-types/${typeId}`, {
        preserveScroll: true,
        onSuccess: () => {
            notifySuccess('Mapping saved successfully.');
        },
        onError: () => {
            notifyError('Failed to save mapping.');
        }
    });
};

const saveCrmMapping = async (serviceId) => {
    const form = crmServiceForms[serviceId];
    form.processing = true;
    try {
        await axios.put(`/api/crm-services/${serviceId}`, {
            xero_item_code: form.xero_item_code,
        });
        notifySuccess('CRM Service mapping saved successfully.');
    } catch (error) {
        console.error(error);
        notifyError('Failed to save CRM Service mapping.');
    } finally {
        form.processing = false;
    }
};

const formatDatetime = (value) => {
    if (!value) {
        return 'Not available';
    }

    return new Date(value).toLocaleString();
};
</script>