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

                <section v-if="connection && branding_themes.length > 0" class="overflow-hidden rounded-lg bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-gray-900">Default Branding Theme</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Select the default branding theme used when creating Xero invoices.
                        </p>
                    </div>

                    <div class="px-6 py-6">
                        <div class="max-w-xl">
                            <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div class="w-full">
                                    <p class="text-sm font-medium text-gray-700">Branding Theme</p>
                                    <SelectDropdown
                                        v-model="brandingThemeForm.default_branding_theme_id"
                                        :options="brandingThemeOptions"
                                        placeholder="No default theme"
                                        class="w-full"
                                    />
                                </div>
                                <PrimaryButton
                                    :disabled="brandingThemeForm.processing"
                                    @click="saveBrandingTheme"
                                >
                                    {{ brandingThemeForm.processing ? 'Saving...' : 'Save' }}
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="connection" class="overflow-hidden rounded-lg bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Xero Mapping Controls</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Rename CRM labels, map to Xero accounts/items, and bulk save all pending changes.
                                </p>
                            </div>
                            <PrimaryButton
                                :disabled="!hasPendingChanges || bulkSaving"
                                @click="saveAllChanges"
                            >
                                {{ bulkSaving ? 'Saving All...' : 'Save All Changes' }}
                            </PrimaryButton>
                        </div>
                    </div>

                    <div class="px-6 py-6">
                        <h4 class="text-base font-semibold text-gray-900">Transaction Types</h4>
                        <p class="mb-4 mt-1 text-sm text-gray-500">
                            Rename transaction types and map them to Xero Chart of Accounts codes.
                        </p>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Transaction Type
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Xero Account
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Usage
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="(type, typeIndex) in transactionRows" :key="type?.id ?? `type-${typeIndex}`">
                                        <td class="px-4 py-4 text-sm text-gray-900">
                                            <input
                                                v-model="transactionTypeForms[type.id].name"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Transaction type name"
                                            >
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500">
                                            <SelectDropdown
                                                v-model="transactionTypeForms[type.id].xero_account_code"
                                                :options="xeroAccountOptions"
                                                placeholder="Select account"
                                                class="w-full"
                                            />
                                        </td>
                                        <td class="px-4 py-4 text-xs text-gray-600">
                                            <div class="relative flex items-center justify-center">
                                                <button
                                                    type="button"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-indigo-200 bg-indigo-50 text-[11px] font-bold text-indigo-700 hover:bg-indigo-100"
                                                    @click="toggleTransactionUsage(type.id)"
                                                >
                                                    i
                                                </button>
                                                <div
                                                    v-if="openTransactionUsageId === type.id"
                                                    class="absolute right-0 top-8 z-20 w-72 rounded-md border border-gray-200 bg-white p-3 shadow-lg"
                                                >
                                                    <p>
                                                        Transactions: <span class="font-semibold">{{ type.transactions_count || 0 }}</span>
                                                        | Bills: <span class="font-semibold">{{ type.bills_count || 0 }}</span>
                                                    </p>
                                                    <p class="mt-1" v-if="(type.usage_projects || []).length">
                                                        Projects: {{ (type.usage_projects || []).slice(0, 3).join(', ') }}
                                                        <span v-if="(type.usage_projects || []).length > 3">+{{ (type.usage_projects || []).length - 3 }} more</span>
                                                    </p>
                                                    <p class="mt-1" v-if="(type.transaction_usages || []).length">
                                                        TX IDs: {{ (type.transaction_usages || []).filter((item) => item && item.id).map((item) => `#${item.id}`).join(', ') }}
                                                    </p>
                                                    <p class="mt-1" v-if="(type.bill_usages || []).length">
                                                        Bill IDs: {{ (type.bill_usages || []).filter((item) => item && item.id).map((item) => `#${item.id}`).join(', ') }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-right text-sm font-medium">
                                            <div class="flex items-center justify-end gap-3">
                                                <button
                                                    @click="createXeroAccountForType(type.id)"
                                                    :disabled="creatingAccountForTypeId === type.id"
                                                    class="text-emerald-600 hover:text-emerald-800 disabled:opacity-50"
                                                >
                                                    {{ creatingAccountForTypeId === type.id ? 'Creating...' : 'Create in Xero' }}
                                                </button>
                                                <button
                                                    @click="saveMapping(type.id)"
                                                    :disabled="transactionTypeForms[type.id].processing || !hasTransactionTypeChanges(type.id)"
                                                    class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50"
                                                >
                                                    {{ transactionTypeForms[type.id].processing ? 'Saving...' : 'Save' }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 px-6 py-6">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold text-gray-900">CRM Services</h4>
                                <p class="mt-1 text-sm text-gray-500">
                                    Rename CRM services, map to Xero items, and merge duplicates into a single canonical service.
                                </p>
                            </div>
                            <PrimaryButton class="!px-2.5 !py-2" @click="toggleMergeMode" :title="mergeMode ? 'Exit merge mode' : 'Enter merge mode'">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M3 5a1 1 0 011-1h6a1 1 0 010 2H6.414l3.293 3.293a1 1 0 01-1.414 1.414L5 7.414V11a1 1 0 11-2 0V5zm14 4a1 1 0 10-2 0v3.586l-3.293-3.293a1 1 0 10-1.414 1.414L13.586 14H10a1 1 0 100 2h6a1 1 0 001-1V9z" />
                                </svg>
                            </PrimaryButton>
                        </div>

                        <!-- Bulk Actions Toolbar -->
                        <div
                            v-if="selectedServiceIds.length > 0"
                            class="mb-4 flex flex-col gap-4 rounded-lg bg-indigo-50 p-4 border border-indigo-100 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span class="text-sm font-medium text-indigo-900">
                                {{ selectedServiceIds.length }} CRM service{{ selectedServiceIds.length === 1 ? '' : 's' }} selected
                            </span>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="w-64">
                                    <SelectDropdown
                                        v-model="bulkAccountCode"
                                        :options="xeroRevenueAccountOptions"
                                        placeholder="Select bulk account"
                                        class="w-full"
                                    />
                                </div>
                                <PrimaryButton
                                    @click="applyBulkAccountUpdate"
                                    :disabled="bulkSavingAccount"
                                    class="!bg-indigo-600 hover:!bg-indigo-700"
                                >
                                    {{ bulkSavingAccount ? 'Updating...' : 'Bulk Update Account' }}
                                </PrimaryButton>
                                <button
                                    @click="selectedServiceIds = []"
                                    class="text-sm text-gray-600 hover:text-gray-950"
                                >
                                    Clear Selection
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="w-10 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            <input
                                                type="checkbox"
                                                :checked="isAllServicesSelected"
                                                @change="toggleSelectAllServices"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            CRM Service
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Xero Item
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Xero Revenue Account
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Usage
                                        </th>
                                        <th v-if="mergeMode" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Merge Into
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="(service, serviceIndex) in crmServiceRows" :key="service?.id ?? `service-${serviceIndex}`" :class="{'bg-indigo-50/30': selectedServiceIds.includes(service.id)}">
                                        <td class="px-4 py-4 text-sm text-gray-500">
                                            <input
                                                type="checkbox"
                                                v-model="selectedServiceIds"
                                                :value="service.id"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900">
                                            <div v-if="editingCrmServiceId !== service.id">
                                                <button
                                                    type="button"
                                                    class="text-left font-medium text-gray-900 hover:text-indigo-700"
                                                    @click="startEditingCrmService(service.id)"
                                                >
                                                    {{ crmServiceForms[service.id].name || 'Unnamed service' }}
                                                </button>
                                            </div>
                                            <div v-else class="flex items-center gap-2">
                                                <input
                                                    v-model="crmServiceForms[service.id].name"
                                                    type="text"
                                                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="CRM service name"
                                                    @keydown.enter.prevent="finishEditingCrmService"
                                                    @keydown.esc.prevent="cancelEditingCrmService(service.id)"
                                                >
                                                <button
                                                    type="button"
                                                    class="rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                                    @click="finishEditingCrmService"
                                                >
                                                    Done
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                                    @click="cancelEditingCrmService(service.id)"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500">
                                            <SelectDropdown
                                                v-model="crmServiceForms[service.id].xero_item_code"
                                                :options="xeroItemOptions"
                                                placeholder="Select item"
                                                class="w-full"
                                            />
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500">
                                            <SelectDropdown
                                                v-model="crmServiceForms[service.id].default_xero_account_code"
                                                :options="xeroRevenueAccountOptions"
                                                placeholder="Select account"
                                                class="w-full"
                                            />
                                        </td>
                                        <td class="px-4 py-4 text-xs text-gray-600">
                                            <div class="relative flex items-center justify-center">
                                                <button
                                                    type="button"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-indigo-200 bg-indigo-50 text-[11px] font-bold text-indigo-700 hover:bg-indigo-100"
                                                    @click="toggleServiceUsage(service.id)"
                                                >
                                                    i
                                                </button>
                                                <div
                                                    v-if="openServiceUsageId === service.id"
                                                    class="absolute right-0 top-8 z-20 w-72 rounded-md border border-gray-200 bg-white p-3 shadow-lg"
                                                >
                                                    <p>
                                                        Project services: <span class="font-semibold">{{ service.project_services_count || 0 }}</span>
                                                    </p>
                                                    <p class="mt-1" v-if="(service.usage_projects || []).length">
                                                        Projects: {{ (service.usage_projects || []).slice(0, 3).join(', ') }}
                                                        <span v-if="(service.usage_projects || []).length > 3">+{{ (service.usage_projects || []).length - 3 }} more</span>
                                                    </p>
                                                    <p class="mt-1" v-if="(service.project_service_usages || []).length">
                                                        Usage IDs: {{ (service.project_service_usages || []).filter((item) => item && item.id).map((item) => `#${item.id}`).join(', ') }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td v-if="mergeMode" class="px-4 py-4 text-sm text-gray-500">
                                            <div class="flex min-w-[220px] items-center gap-2">
                                                <SelectDropdown
                                                    v-model="mergeTargets[service.id]"
                                                    :options="mergeOptionsFor(service.id)"
                                                    placeholder="Select target service"
                                                    class="w-full"
                                                />
                                                <button
                                                    class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100 disabled:opacity-50"
                                                    :disabled="crmServiceForms[service.id].processing || !mergeTargets[service.id]"
                                                    @click="mergeCrmService(service.id)"
                                                >
                                                    Merge
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-right text-sm font-medium">
                                            <div class="flex items-center justify-end gap-3">
                                                <button
                                                    @click="createXeroItemForService(service.id)"
                                                    :disabled="creatingItemForServiceId === service.id"
                                                    class="text-emerald-600 hover:text-emerald-800 disabled:opacity-50"
                                                 >
                                                    {{ creatingItemForServiceId === service.id ? 'Creating...' : 'Create in Xero' }}
                                                </button>
                                                <button
                                                    @click="saveCrmMapping(service.id)"
                                                    :disabled="crmServiceForms[service.id].processing || !hasCrmServiceChanges(service.id)"
                                                    class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50"
                                                >
                                                    {{ crmServiceForms[service.id].processing ? 'Saving...' : 'Save' }}
                                                </button>
                                            </div>
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
import SelectDropdown from '@/Components/SelectDropdown.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, onMounted, watch } from 'vue';
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
    branding_themes: {
        type: Array,
        default: () => [],
    },
});

const transaction_types = ref(Array.isArray(props.transaction_types) ? props.transaction_types.filter(Boolean) : []);
const crm_services = ref(Array.isArray(props.crm_services) ? props.crm_services.filter(Boolean) : []);
const safeTransactionTypes = computed(() => (transaction_types.value || []).filter((type) => type && type.id));
const safeCrmServices = computed(() => (crm_services.value || []).filter((service) => service && service.id));

const brandingThemeForm = useForm({
    default_branding_theme_id: props.connection?.default_branding_theme_id || '',
});

const saveBrandingTheme = () => {
    brandingThemeForm.post(route('admin.xero.default-branding-theme'), {
        preserveScroll: true,
        onSuccess: () => {
            notifySuccess('Default branding theme saved successfully.');
        },
        onError: () => {
            notifyError('Failed to save default branding theme.');
        },
    });
};

const tenantForm = useForm({
    tenant_id: '',
});

const disconnectForm = useForm({});

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

const xeroAccounts = ref([]);
const xeroRevenueAccounts = ref([]);
const xeroItems = ref([]);
const bulkSaving = ref(false);
const creatingAccountForTypeId = ref(null);
const creatingItemForServiceId = ref(null);
const openTransactionUsageId = ref(null);
const openServiceUsageId = ref(null);
const editingCrmServiceId = ref(null);
const mergeMode = ref(false);

const selectedServiceIds = ref([]);
const bulkAccountCode = ref('');
const bulkSavingAccount = ref(false);

const isAllServicesSelected = computed(() => {
    return safeCrmServices.value.length > 0 && selectedServiceIds.value.length === safeCrmServices.value.length;
});

const toggleSelectAllServices = () => {
    if (isAllServicesSelected.value) {
        selectedServiceIds.value = [];
    } else {
        selectedServiceIds.value = safeCrmServices.value.map((s) => s.id);
    }
};

const applyBulkAccountUpdate = async () => {
    if (selectedServiceIds.value.length === 0) {
        notifyError('Please select at least one service to update.');
        return;
    }

    bulkSavingAccount.value = true;
    try {
        await axios.put('/api/crm-services/bulk', {
            ids: selectedServiceIds.value,
            default_xero_account_code: bulkAccountCode.value || null,
        });

        notifySuccess(`Successfully updated ${selectedServiceIds.value.length} services.`);
        
        selectedServiceIds.value.forEach((id) => {
            if (crmServiceForms[id]) {
                crmServiceForms[id].default_xero_account_code = bulkAccountCode.value;
                crmServiceInitial[id].default_xero_account_code = bulkAccountCode.value;
            }
            const service = safeCrmServices.value.find((s) => s.id === id);
            if (service) {
                service.default_xero_account_code = bulkAccountCode.value;
            }
        });
        
        selectedServiceIds.value = [];
        bulkAccountCode.value = '';
    } catch (error) {
        notifyError(error.response?.data?.message || 'Failed to bulk update services.');
    } finally {
        bulkSavingAccount.value = false;
    }
};

const transactionTypeForms = reactive({});
const crmServiceForms = reactive({});
const mergeTargets = reactive({});

const transactionRows = computed(() =>
    safeTransactionTypes.value.filter((type) => Boolean(transactionTypeForms[type.id]))
);
const crmServiceRows = computed(() =>
    safeCrmServices.value.filter((service) => Boolean(crmServiceForms[service.id]))
);

const transactionTypeInitial = reactive({});
const crmServiceInitial = reactive({});

const clearReactiveObject = (state) => {
    Object.keys(state).forEach((key) => {
        delete state[key];
    });
};

const brandingThemeOptions = computed(() => {
    const themeOptions = (props.branding_themes || []).map((theme) => ({
        value: theme.BrandingThemeID,
        label: theme.Name,
    }));
    return [{ value: '', label: 'No default theme' }, ...themeOptions];
});

const xeroAccountOptions = computed(() => {
    const accountOptions = xeroAccounts.value
        .filter((account) => account && account.code)
        .map((account) => ({
            value: account.code,
            label: `${account.code} - ${account.name || 'Unnamed account'}`,
        }));
    return [{ value: '', label: 'No account mapping' }, ...accountOptions];
});

const xeroRevenueAccountOptions = computed(() => {
    const accountOptions = xeroRevenueAccounts.value
        .filter((account) => account && account.code)
        .map((account) => ({
            value: account.code,
            label: `${account.code} - ${account.name || 'Unnamed account'}`,
        }));
    return [{ value: '', label: 'No account mapping' }, ...accountOptions];
});

const xeroItemOptions = computed(() => {
    const itemOptions = xeroItems.value
        .filter((item) => item && item.code)
        .map((item) => ({
            value: item.code,
            label: `${item.code} - ${item.name || 'Unnamed item'}`,
        }));
    return [{ value: '', label: 'No item mapping' }, ...itemOptions];
});

const hydrateForms = () => {
    clearReactiveObject(transactionTypeForms);
    clearReactiveObject(crmServiceForms);
    clearReactiveObject(mergeTargets);
    clearReactiveObject(transactionTypeInitial);
    clearReactiveObject(crmServiceInitial);

    safeTransactionTypes.value.forEach((type) => {
        transactionTypeForms[type.id] = {
            name: type.name || '',
            xero_account_code: type.xero_account_code || '',
            processing: false,
        };
        transactionTypeInitial[type.id] = {
            name: type.name || '',
            xero_account_code: type.xero_account_code || '',
        };
    });

    safeCrmServices.value.forEach((service) => {
        crmServiceForms[service.id] = {
            name: service.name || '',
            xero_item_code: service.xero_item_code || '',
            default_xero_account_code: service.default_xero_account_code || '',
            processing: false,
        };
        crmServiceInitial[service.id] = {
            name: service.name || '',
            xero_item_code: service.xero_item_code || '',
            default_xero_account_code: service.default_xero_account_code || '',
        };
        mergeTargets[service.id] = '';
    });
};

hydrateForms();

const hasTransactionTypeChanges = (typeId) => {
    const form = transactionTypeForms[typeId];
    const initial = transactionTypeInitial[typeId];
    if (!form || !initial) {
        return false;
    }

    return form.name.trim() !== initial.name.trim() || (form.xero_account_code || '') !== (initial.xero_account_code || '');
};

const hasCrmServiceChanges = (serviceId) => {
    const form = crmServiceForms[serviceId];
    const initial = crmServiceInitial[serviceId];
    if (!form || !initial) {
        return false;
    }

    return form.name.trim() !== initial.name.trim() ||
           (form.xero_item_code || '') !== (initial.xero_item_code || '') ||
           (form.default_xero_account_code || '') !== (initial.default_xero_account_code || '');
};

const hasPendingChanges = computed(() => {
    const typeDirty = safeTransactionTypes.value.some((type) => hasTransactionTypeChanges(type.id));
    const serviceDirty = safeCrmServices.value.some((service) => hasCrmServiceChanges(service.id));
    return typeDirty || serviceDirty;
});

const saveMapping = async (typeId, silent = false) => {
    const form = transactionTypeForms[typeId];
    if (!form || form.processing) {
        return true;
    }

    form.processing = true;
    try {
        await axios.put(`/api/transaction-types/${typeId}`, {
            name: form.name.trim(),
            xero_account_code: form.xero_account_code || null,
        });

        transactionTypeInitial[typeId] = {
            name: form.name,
            xero_account_code: form.xero_account_code || '',
        };

        const match = safeTransactionTypes.value.find((type) => type.id === typeId);
        if (match) {
            match.name = form.name.trim();
            match.xero_account_code = form.xero_account_code || '';
        }

        if (!silent) {
            notifySuccess('Transaction type saved successfully.');
        }
        return true;
    } catch (error) {
        if (!silent) {
            notifyError(error.response?.data?.message || 'Failed to save transaction type.');
        }
        return false;
    } finally {
        form.processing = false;
    }
};

const saveCrmMapping = async (serviceId, silent = false) => {
    const form = crmServiceForms[serviceId];
    if (!form || form.processing) {
        return true;
    }

    form.processing = true;
    try {
        await axios.put(`/api/crm-services/${serviceId}`, {
            name: form.name.trim(),
            xero_item_code: form.xero_item_code || null,
            default_xero_account_code: form.default_xero_account_code || null,
        });

        crmServiceInitial[serviceId] = {
            name: form.name,
            xero_item_code: form.xero_item_code || '',
            default_xero_account_code: form.default_xero_account_code || '',
        };

        const match = safeCrmServices.value.find((service) => service.id === serviceId);
        if (match) {
            match.name = form.name.trim();
            match.xero_item_code = form.xero_item_code || '';
            match.default_xero_account_code = form.default_xero_account_code || '';
        }

        if (!silent) {
            notifySuccess('CRM service saved successfully.');
        }
        return true;
    } catch (error) {
        if (!silent) {
            notifyError(error.response?.data?.message || 'Failed to save CRM service.');
        }
        return false;
    } finally {
        form.processing = false;
    }
};

const saveAllChanges = async () => {
    if (bulkSaving.value || !hasPendingChanges.value) {
        return;
    }

    bulkSaving.value = true;
    let successCount = 0;
    let failureCount = 0;

    const dirtyTypeIds = safeTransactionTypes.value
        .map((type) => type.id)
        .filter((id) => hasTransactionTypeChanges(id));
    const dirtyServiceIds = safeCrmServices.value
        .map((service) => service.id)
        .filter((id) => hasCrmServiceChanges(id));

    for (const typeId of dirtyTypeIds) {
        const ok = await saveMapping(typeId, true);
        if (ok) {
            successCount += 1;
        } else {
            failureCount += 1;
        }
    }

    for (const serviceId of dirtyServiceIds) {
        const ok = await saveCrmMapping(serviceId, true);
        if (ok) {
            successCount += 1;
        } else {
            failureCount += 1;
        }
    }

    if (failureCount === 0) {
        notifySuccess(`Saved ${successCount} change${successCount === 1 ? '' : 's'} successfully.`);
    } else {
        notifyError(`Saved ${successCount} change${successCount === 1 ? '' : 's'} and ${failureCount} failed.`);
    }

    bulkSaving.value = false;
};

const mergeOptionsFor = (serviceId) => {
    return safeCrmServices.value
        .filter((service) => service && service.id !== serviceId)
        .map((service) => ({
            value: service.id,
            label: service.name || 'Unnamed service',
        }));
};

const mergeCrmService = async (serviceId) => {
    const form = crmServiceForms[serviceId];
    const targetId = Number(mergeTargets[serviceId] || 0);

    if (!form || form.processing || !targetId) {
        return;
    }

    const sourceService = safeCrmServices.value.find((item) => item.id === serviceId);
    const targetService = safeCrmServices.value.find((item) => item.id === targetId);
    if (!sourceService || !targetService) {
        notifyError('Invalid merge target selected.');
        return;
    }

    const confirmed = window.confirm(
        `Merge "${sourceService.name}" into "${targetService.name}"? This will move all project-service usage and remove the source service.`
    );

    if (!confirmed) {
        return;
    }

    form.processing = true;
    try {
        const { data } = await axios.post(`/api/crm-services/${serviceId}/merge`, {
            target_crm_service_id: targetId,
        });

        notifySuccess(data?.message || 'CRM service merged successfully.');

        router.reload({
            preserveScroll: true,
            only: ['transaction_types', 'crm_services'],
        });
    } catch (error) {
        notifyError(error.response?.data?.message || 'Failed to merge CRM service.');
    } finally {
        form.processing = false;
    }
};

const toggleTransactionUsage = (typeId) => {
    openTransactionUsageId.value = openTransactionUsageId.value === typeId ? null : typeId;
};

const toggleServiceUsage = (serviceId) => {
    openServiceUsageId.value = openServiceUsageId.value === serviceId ? null : serviceId;
};

const startEditingCrmService = (serviceId) => {
    editingCrmServiceId.value = serviceId;
};

const finishEditingCrmService = () => {
    editingCrmServiceId.value = null;
};

const cancelEditingCrmService = (serviceId) => {
    if (crmServiceInitial[serviceId]) {
        crmServiceForms[serviceId].name = crmServiceInitial[serviceId].name;
    }
    editingCrmServiceId.value = null;
};

const toggleMergeMode = () => {
    mergeMode.value = !mergeMode.value;
    if (!mergeMode.value) {
        safeCrmServices.value.forEach((service) => {
            mergeTargets[service.id] = '';
        });
    }
};

const formatDatetime = (value) => {
    if (!value) {
        return 'Not available';
    }

    return new Date(value).toLocaleString();
};

const fetchXeroAccounts = async () => {
    if (!props.connection || props.connection.status !== 'connected') {
        return;
    }

    try {
        const { data } = await axios.get(route('api.xero.accounts', { category: 'expense' }));
        xeroAccounts.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error('Failed to fetch Xero expense accounts', error);
        xeroAccounts.value = [];
    }

    try {
        const { data } = await axios.get(route('api.xero.accounts', { category: 'revenue' }));
        xeroRevenueAccounts.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error('Failed to fetch Xero revenue accounts', error);
        xeroRevenueAccounts.value = [];
    }
};

const fetchXeroItems = async () => {
    if (!props.connection || props.connection.status !== 'connected') {
        return;
    }

    try {
        const { data } = await axios.get(route('api.xero.items'));
        xeroItems.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error('Failed to fetch Xero items', error);
        xeroItems.value = [];
    }
};

const createXeroAccountForType = async (typeId) => {
    const form = transactionTypeForms[typeId];
    if (!form || creatingAccountForTypeId.value === typeId) {
        return;
    }

    const name = (form.name || '').trim();
    if (!name) {
        notifyError('Transaction type name is required before creating an account in Xero.');
        return;
    }

    creatingAccountForTypeId.value = typeId;
    try {
        const { data } = await axios.post(route('api.xero.accounts.store'), {
            name,
        });

        const createdCode = (data?.code || '').toUpperCase();
        const createdName = data?.name || name;

        if (createdCode && !xeroAccounts.value.some((account) => String(account.code).toUpperCase() === createdCode)) {
            xeroAccounts.value = [
                ...xeroAccounts.value,
                {
                    code: createdCode,
                    name: createdName,
                    type: data?.type || 'OVERHEADS',
                },
            ].sort((a, b) => String(a.code).localeCompare(String(b.code)));
        }

        form.xero_account_code = createdCode;
        notifySuccess(`Created Xero account ${createdCode} and linked it.`);
    } catch (error) {
        notifyError(error.response?.data?.message || 'Failed to create Xero account.');
    } finally {
        creatingAccountForTypeId.value = null;
    }
};

const createXeroItemForService = async (serviceId) => {
    const form = crmServiceForms[serviceId];
    if (!form || creatingItemForServiceId.value === serviceId) {
        return;
    }

    const name = (form.name || '').trim();
    if (!name) {
        notifyError('CRM service name is required before creating an item in Xero.');
        return;
    }

    creatingItemForServiceId.value = serviceId;
    try {
        const { data } = await axios.post(route('api.xero.items.store'), {
            name,
        });

        const createdCode = (data?.code || '').toUpperCase();
        const createdName = data?.name || name;

        if (createdCode && !xeroItems.value.some((item) => String(item.code).toUpperCase() === createdCode)) {
            xeroItems.value = [
                ...xeroItems.value,
                {
                    code: createdCode,
                    name: createdName,
                },
            ].sort((a, b) => String(a.code).localeCompare(String(b.code)));
        }

        form.xero_item_code = createdCode;
        notifySuccess(`Created Xero item ${createdCode} and linked it.`);
    } catch (error) {
        notifyError(error.response?.data?.message || 'Failed to create Xero item.');
    } finally {
        creatingItemForServiceId.value = null;
    }
};

onMounted(() => {
    hydrateForms();
    fetchXeroAccounts();
    fetchXeroItems();
});

watch(
    () => props.transaction_types,
    (value) => {
        transaction_types.value = Array.isArray(value) ? value.filter(Boolean) : [];
        hydrateForms();
    }
);

watch(
    () => props.crm_services,
    (value) => {
        crm_services.value = Array.isArray(value) ? value.filter(Boolean) : [];
        hydrateForms();
    }
);
</script>