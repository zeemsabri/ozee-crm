<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { success, error } from '@/Utils/notification';

const services = ref([]);
const loading = ref(true);
const creating = ref(false);

const currencyOptions = [
    { value: '', label: 'Auto (Project Currency)' },
    { value: 'AUD', label: 'AUD' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
    { value: 'PKR', label: 'PKR' },
    { value: 'INR', label: 'INR' },
];

const frequencyOptions = [
    { value: '', label: 'one_off (default)' },
    { value: 'one_off', label: 'one_off' },
    { value: 'monthly', label: 'monthly' },
];

const createForm = reactive({
    name: '',
    default_amount: '',
    default_currency: '',
    default_frequency: 'one_off',
    default_description: '',
    default_xero_account_code: '',
    xero_item_code: '',
});

const rowState = reactive({});

const ensureRowState = (service) => {
    if (rowState[service.id]) {
        return rowState[service.id];
    }

    rowState[service.id] = {
        processing: false,
        form: {
            default_amount: service.default_amount ?? '',
            default_currency: service.default_currency ?? '',
            default_frequency: service.default_frequency || 'one_off',
            default_description: service.default_description ?? '',
            default_xero_account_code: service.default_xero_account_code ?? '',
            xero_item_code: service.xero_item_code ?? '',
        },
    };

    return rowState[service.id];
};

const totalServices = computed(() => services.value.length);

const fetchServices = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/crm-services');
        services.value = Array.isArray(data) ? data : [];
        services.value.forEach((service) => ensureRowState(service));
    } catch (e) {
        error(e?.response?.data?.message || 'Failed to load project services.');
    } finally {
        loading.value = false;
    }
};

const saveServiceDefaults = async (service) => {
    const state = ensureRowState(service);
    state.processing = true;

    try {
        await axios.put(`/api/crm-services/${service.id}`, {
            default_amount: state.form.default_amount === '' ? null : Number(state.form.default_amount),
            default_currency: state.form.default_currency || null,
            default_frequency: state.form.default_frequency || null,
            default_description: state.form.default_description || null,
            default_xero_account_code: state.form.default_xero_account_code || null,
            xero_item_code: state.form.xero_item_code || null,
        });

        success(`Defaults saved for ${service.name}.`);
        await fetchServices();
    } catch (e) {
        error(e?.response?.data?.message || `Failed to save defaults for ${service.name}.`);
    } finally {
        state.processing = false;
    }
};

const createService = async () => {
    if (!createForm.name.trim()) {
        error('Service name is required.');
        return;
    }

    creating.value = true;

    try {
        await axios.post('/api/crm-services', {
            name: createForm.name.trim(),
            default_amount: createForm.default_amount === '' ? null : Number(createForm.default_amount),
            default_currency: createForm.default_currency || null,
            default_frequency: createForm.default_frequency || null,
            default_description: createForm.default_description || null,
            default_xero_account_code: createForm.default_xero_account_code || null,
            xero_item_code: createForm.xero_item_code || null,
        });

        createForm.name = '';
        createForm.default_amount = '';
        createForm.default_currency = '';
        createForm.default_frequency = 'one_off';
        createForm.default_description = '';
        createForm.default_xero_account_code = '';
        createForm.xero_item_code = '';

        success('Project service created successfully.');
        await fetchServices();
    } catch (e) {
        error(e?.response?.data?.message || 'Failed to create project service.');
    } finally {
        creating.value = false;
    }
};

onMounted(fetchServices);
</script>

<template>
    <Head title="Project Services" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Project Services</h2>
                <span class="text-sm text-gray-500">{{ totalServices }} services</span>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-gray-900">Create New Service Default</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Defaults are applied when a service is added to a project. Project-level edits remain isolated per project.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 px-6 py-6 md:grid-cols-3">
                        <div>
                            <InputLabel value="Service Name" />
                            <TextInput v-model="createForm.name" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel value="Default Amount" />
                            <TextInput v-model="createForm.default_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel value="Default Currency" />
                            <SelectDropdown v-model="createForm.default_currency" :options="currencyOptions" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel value="Default Frequency" />
                            <SelectDropdown v-model="createForm.default_frequency" :options="frequencyOptions" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel value="Default Xero Account Code" />
                            <TextInput v-model="createForm.default_xero_account_code" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel value="Xero Item Code" />
                            <TextInput v-model="createForm.xero_item_code" class="mt-1 block w-full" />
                        </div>

                        <div class="md:col-span-3">
                            <InputLabel value="Default Description" />
                            <textarea
                                v-model="createForm.default_description"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-200 px-6 py-4">
                        <PrimaryButton @click="createService" :disabled="creating">
                            {{ creating ? 'Creating...' : 'Create Service' }}
                        </PrimaryButton>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-gray-900">Service Defaults</h3>
                    </div>

                    <div v-if="loading" class="px-6 py-12 text-center text-sm text-gray-500">Loading services...</div>

                    <div v-else-if="!services.length" class="px-6 py-12 text-center text-sm text-gray-500">No services found.</div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Service</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Currency</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Frequency</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Xero Account</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Xero Item</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="service in services" :key="service.id">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        <div>{{ service.name }}</div>
                                        <textarea
                                            v-model="ensureRowState(service).form.default_description"
                                            rows="2"
                                            class="mt-2 block w-72 rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Default description"
                                        ></textarea>
                                    </td>
                                    <td class="px-4 py-3">
                                        <TextInput
                                            v-model="ensureRowState(service).form.default_amount"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-32"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <SelectDropdown
                                            v-model="ensureRowState(service).form.default_currency"
                                            :options="currencyOptions"
                                            class="w-40"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <SelectDropdown
                                            v-model="ensureRowState(service).form.default_frequency"
                                            :options="frequencyOptions"
                                            class="w-40"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <TextInput v-model="ensureRowState(service).form.default_xero_account_code" class="w-36" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <TextInput v-model="ensureRowState(service).form.xero_item_code" class="w-36" />
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <PrimaryButton
                                            @click="saveServiceDefaults(service)"
                                            :disabled="ensureRowState(service).processing"
                                        >
                                            {{ ensureRowState(service).processing ? 'Saving...' : 'Save' }}
                                        </PrimaryButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
