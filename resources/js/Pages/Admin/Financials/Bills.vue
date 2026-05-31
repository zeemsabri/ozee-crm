<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import { formatCurrency } from '@/Utils/currency';
import { success, error, confirmPrompt } from '@/Utils/notification';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { usePermissions } from '@/Directives/permissions';

const bills = ref([]);
const projects = ref([]);
const expendables = ref([]);
const transactionTypes = ref([]);
const loading = ref(true);
const filterStatus = ref('');
const showCreateModal = ref(false);
const showXeroSyncModal = ref(false);
const xeroSyncLoading = ref(false);
const xeroSyncError = ref('');
const xeroCandidates = ref([]);
const selectedXeroContactId = ref('');
const xeroSyncContractor = ref(null);
const approvalConfigByBillId = ref({});
const { canDo } = usePermissions();
const canViewBills = canDo('view_project_bills');
const canCreateBills = canDo('create_project_bills');
const canApproveBills = canDo('approve_project_bills');
const canVoidBills = canDo('void_project_bills');
const canLinkXeroContractors = canDo('link_xero_contractors');

const form = useForm({
    project_id: '',
    project_expendable_id: '',
    contractor_id: '',
    transaction_type_id: '',
    xero_account_code: '',
    xero_tax_type: 'INPUT',
    reference_number: '',
    due_date: '',
    currency: '',
    amount: '',
    payment_details: {
        payment_method: 'bank_transfer',
        account_name: '',
        account_number: '',
        bank_name: '',
        bsb: '',
        swift_code: '',
        iban: '',
        notes: '',
    },
});

const xeroTaxTypeOptions = [
    { value: 'BASEXCLUDED', label: 'BAS Excluded' },
    { value: 'EXEMPTEXPENSES', label: 'GST Free Expenses' },
    { value: 'EXEMPTOUTPUT', label: 'GST Free Income' },
    { value: 'INPUT', label: 'GST on Expenses' },
    { value: 'INPUTTAXED', label: 'GST on Imports' },
    { value: 'OUTPUT', label: 'GST on Income' },
];

const selectedExpendable = computed(() => {
    return expendables.value.find((item) => String(item.id) === String(form.project_expendable_id)) || null;
});

const selectedTransactionType = computed(() => {
    return transactionTypes.value.find((item) => String(item.id) === String(form.transaction_type_id)) || null;
});

const xeroAccountOptions = computed(() => {
    return transactionTypes.value
        .filter((item) => item.xero_account_code)
        .map((item) => ({
            value: item.xero_account_code,
            label: `${item.name} (${item.xero_account_code})`,
        }));
});

const suggestedXeroAccountCodes = computed(() => {
    return [...new Set(xeroAccountOptions.value.map((option) => option.value))];
});

const authUser = computed(() => usePage().props.auth?.user);

const currentPendingStep = (bill) => {
    const steps = bill.approval_instance?.steps;
    if (!steps?.length) return null;
    return steps.find((s) => s.status === 'pending') || null;
};

const canApproveBill = (bill) => {
    if (!canApproveBills.value) return false;
    if (bill.status !== 'pending_approval') return false;
    const step = currentPendingStep(bill);
    if (!step) {
        // No active flow — only super admin can approve (API will guard)
        return !bill.approval_instance;
    }
    const user = authUser.value;
    if (!user) return false;
    if (step.approver_type === 'user') return Number(step.approver_user_id) === Number(user.id);
    if (step.approver_type === 'role') return Number(step.approver_role_id) === Number(user.role_id);
    return false;
};

const pendingApproverLabel = (bill) => {
    const step = currentPendingStep(bill);
    if (!step) return null;
    if (step.approver_type === 'user') return step.approver_user?.name ? `Awaiting: ${step.approver_user.name}` : null;
    if (step.approver_type === 'role') return step.approver_role?.name ? `Awaiting: ${step.approver_role.name} role` : null;
    return null;
};

const getApprovalConfig = (bill) => {
    if (!approvalConfigByBillId.value[bill.id]) {
        approvalConfigByBillId.value[bill.id] = {
            xero_account_code: bill.xero_account_code || bill.transaction_type?.xero_account_code || '',
            xero_tax_type: bill.xero_tax_type === 'NONE' ? 'EXEMPTEXPENSES' : (bill.xero_tax_type || 'INPUT'),
        };
    }

    return approvalConfigByBillId.value[bill.id];
};

const xeroCandidateOptions = computed(() => {
    return xeroCandidates.value.map((candidate) => ({
        value: candidate.contact_id,
        label: `${candidate.name || 'Unnamed Contact'}${candidate.email ? ` (${candidate.email})` : ''}`,
    }));
});

const fetchBills = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/bills', { params: { status: filterStatus.value } });
        bills.value = data.data; // Paginated data
        bills.value.forEach((bill) => {
            getApprovalConfig(bill);
        });
    } catch (err) {
        error('Failed to load bills.');
    } finally {
        loading.value = false;
    }
};

const fetchProjects = async () => {
    try {
        const { data } = await axios.get('/api/projects-for-email');
        projects.value = data.projects;
    } catch (err) {
        console.error('Failed to fetch projects', err);
    }
};

const fetchTransactionTypes = async () => {
    try {
        const { data } = await axios.get('/api/transaction-types');
        transactionTypes.value = data || [];
    } catch (err) {
        error('Failed to load transaction types.');
    }
};

watch(() => form.project_id, async (newId) => {
    if (!newId) {
        expendables.value = [];
        form.project_expendable_id = '';
        form.contractor_id = '';
        return;
    }
    try {
        const { data } = await axios.get(`/api/projects/${newId}/expendables`, {
            params: {
                for_billing: true,
            },
        });
        expendables.value = data;
        form.project_expendable_id = '';
        form.contractor_id = '';
    } catch (err) {
        console.error('Failed to fetch expendables', err);
    }
});

watch(() => form.project_expendable_id, () => {
    form.contractor_id = selectedExpendable.value?.user_id || '';
    if (!form.currency && selectedExpendable.value?.currency) {
        form.currency = selectedExpendable.value.currency;
    }
});

watch(() => form.transaction_type_id, () => {
    if (selectedTransactionType.value?.xero_account_code && !form.xero_account_code) {
        form.xero_account_code = selectedTransactionType.value.xero_account_code;
    }
});

const openCreateModal = () => {
    form.reset();
    form.payment_details = {
        payment_method: 'bank_transfer',
        account_name: '',
        account_number: '',
        bank_name: '',
        bsb: '',
        swift_code: '',
        iban: '',
        notes: '',
    };
    form.xero_tax_type = 'INPUT';
    form.reference_number = '';
    form.due_date = '';
    form.currency = '';
    showCreateModal.value = true;
};

const submitBill = () => {
    if (!form.project_expendable_id) return error('Please select a contract.');
    if (!form.project_id) return error('Please select a project.');
    if (!form.contractor_id) return error('Selected contract does not have a contractor assigned.');
    if (!form.transaction_type_id) return error('Please select a transaction type.');

    form.clearErrors();

    const payload = {
        project_expendable_id: form.project_expendable_id,
        contractor_id: form.contractor_id,
        transaction_type_id: form.transaction_type_id,
        xero_account_code: form.xero_account_code || selectedTransactionType.value?.xero_account_code || '',
        xero_tax_type: form.xero_tax_type,
        reference_number: form.reference_number,
        due_date: form.due_date,
        currency: form.currency || 'AUD',
        amount: form.amount,
        payment_details: {
            payment_method: form.payment_details.payment_method,
            account_name: form.payment_details.account_name,
            account_number: form.payment_details.account_number,
            bank_name: form.payment_details.bank_name,
            bsb: form.payment_details.bsb,
            swift_code: form.payment_details.swift_code,
            iban: form.payment_details.iban,
            notes: form.payment_details.notes,
        },
    };

    form.processing = true;

    axios.post(`/api/projects/${form.project_id}/bills`, payload)
        .then(() => {
            showCreateModal.value = false;
            success('Bill created successfully.');
            fetchBills();
        })
        .catch((err) => {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                form.setError(err.response.data.errors);
                error(err.response?.data?.message || 'Please review the form and try again.');
                return;
            }

            error(err.response?.data?.message || 'Failed to create bill. Please review the form and try again.');
        })
        .finally(() => {
            form.processing = false;
        });
};

const approveBill = async (bill) => {
    if (!await confirmPrompt('Approve this bill for Xero sync?')) return;
    if (!canApproveBills.value) {
        error('You do not have permission to approve bills.');
        return;
    }
    try {
        const approvalConfig = getApprovalConfig(bill);
        if (!approvalConfig.xero_account_code) {
            error('Please set Xero Account before approving this bill.');
            return;
        }

        await axios.post(route('api.bills.approve', { bill: bill.id }), {
            xero_account_code: approvalConfig.xero_account_code,
            xero_tax_type: approvalConfig.xero_tax_type,
        });
        success('Bill approved.');
        fetchBills();
    } catch (err) {
        const message = err.response?.data?.message || 'Approval failed.';
        if (message.includes('not linked to Xero') && bill?.contractor?.id) {
            await openXeroSyncModal(bill.contractor);
            return;
        }

        error(message);
    }
};

const voidBill = async (bill) => {
    if (!await confirmPrompt('Void this bill?')) return;
    if (!canVoidBills.value) {
        error('You do not have permission to void bills.');
        return;
    }
    try {
        await axios.post(route('api.bills.void', { bill: bill.id }));
        success('Bill voided.');
        fetchBills();
    } catch (err) {
        error(err.response?.data?.message || 'Voiding failed.');
    }
};

const fetchXeroCandidates = async () => {
    if (!xeroSyncContractor.value?.id) {
        return;
    }

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const response = await axios.get(`/api/users/${xeroSyncContractor.value.id}/xero-contact-candidates`);
        xeroCandidates.value = response.data?.candidates || [];

        if (xeroCandidates.value.length === 1) {
            selectedXeroContactId.value = xeroCandidates.value[0].contact_id;
        }
    } catch (err) {
        xeroSyncError.value = err.response?.data?.message || 'Failed to fetch Xero contacts.';
    } finally {
        xeroSyncLoading.value = false;
    }
};

const openXeroSyncModal = async (contractor) => {
    if (!canLinkXeroContractors.value) {
        error('You do not have permission to link contractors to Xero.');
        return;
    }

    xeroSyncContractor.value = contractor;
    xeroCandidates.value = [];
    selectedXeroContactId.value = '';
    xeroSyncError.value = '';
    showXeroSyncModal.value = true;

    await fetchXeroCandidates();
};

const syncContractorWithXero = async () => {
    if (!xeroSyncContractor.value?.id) {
        return;
    }

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const payload = selectedXeroContactId.value
            ? { selected_contact_id: selectedXeroContactId.value }
            : {};

        await axios.post(`/api/users/${xeroSyncContractor.value.id}/xero-contact-sync`, payload);
        success('Contractor linked to Xero successfully.');
        showXeroSyncModal.value = false;
        await fetchBills();
    } catch (err) {
        const responseData = err.response?.data || {};
        if (responseData.requires_selection && Array.isArray(responseData.candidates)) {
            xeroCandidates.value = responseData.candidates;
        }

        xeroSyncError.value = responseData.message || 'Failed to link contractor to Xero.';
        error(xeroSyncError.value);
    } finally {
        xeroSyncLoading.value = false;
    }
};

const createXeroContactForContractor = async () => {
    if (!xeroSyncContractor.value?.id) {
        return;
    }

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        await axios.post(`/api/users/${xeroSyncContractor.value.id}/xero-contact-create`);
        success('Xero contact created and linked successfully.');
        showXeroSyncModal.value = false;
        await fetchBills();
    } catch (err) {
        xeroSyncError.value = err.response?.data?.message || 'Failed to create Xero contact.';
        error(xeroSyncError.value);
    } finally {
        xeroSyncLoading.value = false;
    }
};

onMounted(() => {
    fetchBills();
    fetchProjects();
    fetchTransactionTypes();
});

const getStatusClass = (status) => {
    switch (status.toLowerCase()) {
        case 'approved': return 'bg-green-100 text-green-800';
        case 'pending_approval': return 'bg-amber-100 text-amber-800';
        case 'void': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="Contractor Bills" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Contractor Bills</h2>
                <div class="flex gap-4 items-center">
                    <select v-model="filterStatus" @change="fetchBills" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All Statuses</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="void">Void</option>
                    </select>
                    <PrimaryButton v-if="canCreateBills" @click="openCreateModal">
                        Create Bill
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow sm:rounded-lg border border-gray-200">
                    <div v-if="loading" class="p-12 text-center text-gray-500">Loading bills...</div>
                    <div v-else-if="!canViewBills" class="p-12 text-center text-gray-500">You do not have permission to view bills.</div>
                    <div v-else-if="!bills.length" class="p-12 text-center text-gray-500">No bills found.</div>
                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project / Contract</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contractor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="bill in bills" :key="bill.id">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ bill.project?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ bill.expendable?.name }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div>{{ bill.contractor?.name }}</div>
                                    <div v-if="!bill.contractor?.xero_contact_id" class="text-xs text-amber-700 mt-1">
                                        Not linked to Xero
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                    {{ formatCurrency(bill.amount, bill.currency || bill.project?.currency) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['px-2 py-1 text-xs font-bold rounded-full', getStatusClass(bill.status)]">
                                        {{ bill.status.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <Link v-if="canViewBills" :href="route('admin.financials.bills.show', { id: bill.id })" class="text-indigo-600 hover:text-indigo-900">View</Link>
                                        <button
                                            v-if="canLinkXeroContractors && !bill.contractor?.xero_contact_id"
                                            @click="openXeroSyncModal(bill.contractor)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Link Xero
                                        </button>
                                        <button v-if="canApproveBill(bill)" @click="approveBill(bill)" class="text-green-600 hover:text-green-900">Approve</button>
                                        <button v-if="bill.status === 'approved' && canVoidBills" @click="voidBill(bill)" class="text-red-600 hover:text-red-900">Void</button>
                                    </div>
                                    <div v-if="bill.status === 'pending_approval' && pendingApproverLabel(bill)" class="mt-1 text-xs text-amber-700">
                                        {{ pendingApproverLabel(bill) }}
                                    </div>
                                    <div v-if="bill.status === 'pending_approval'" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <TextInput
                                            :id="`approve_xero_account_${bill.id}`"
                                            v-model="getApprovalConfig(bill).xero_account_code"
                                            type="text"
                                            list="xero-account-codes"
                                            placeholder="Xero account code"
                                            class="w-full"
                                        />
                                        <select
                                            :id="`approve_xero_tax_${bill.id}`"
                                            v-model="getApprovalConfig(bill).xero_tax_type"
                                            class="rounded-md border-gray-300 shadow-sm text-sm"
                                        >
                                            <option v-for="option in xeroTaxTypeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <datalist id="xero-account-codes">
                        <option v-for="code in suggestedXeroAccountCodes" :key="code" :value="code" />
                    </datalist>
                </div>
            </div>
        </div>

        <!-- Create Bill Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Create Contractor Bill</h3>
                
                <div class="space-y-4">
                    <div>
                        <InputLabel for="bill_project_id" value="Project" />
                        <select 
                            id="bill_project_id" 
                            v-model="form.project_id" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select Project</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.project_id" />
                    </div>

                    <div v-if="form.project_id">
                        <InputLabel for="project_expendable_id" value="Contract (Expendable)" />
                        <select 
                            id="project_expendable_id" 
                            v-model="form.project_expendable_id" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select Contract</option>
                            <option v-for="exp in expendables" :key="exp.id" :value="exp.id">
                                {{ exp.name }} ({{ exp.expendable_type?.replace('App\\Models\\', '') || 'Project' }}) - Rem: {{ formatCurrency(exp.balance, exp.currency) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.project_expendable_id" />
                    </div>

                    <div>
                        <InputLabel for="bill_transaction_type" value="Transaction Type" />
                        <select
                            id="bill_transaction_type"
                            v-model="form.transaction_type_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select Transaction Type</option>
                            <option v-for="type in transactionTypes" :key="type.id" :value="type.id">
                                {{ type.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.transaction_type_id" />
                    </div>

                    <div>
                        <InputLabel for="bill_xero_account_code" value="Xero Account" />
                        <TextInput
                            id="bill_xero_account_code"
                            v-model="form.xero_account_code"
                            type="text"
                            list="xero-account-codes"
                            placeholder="e.g. 400"
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-gray-500">Enter account code manually or pick from known mapped account codes.</p>
                        <InputError :message="form.errors.xero_account_code" />
                    </div>

                    <div>
                        <InputLabel for="bill_xero_tax_type" value="Xero Tax Type" />
                        <select
                            id="bill_xero_tax_type"
                            v-model="form.xero_tax_type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="option in xeroTaxTypeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.xero_tax_type" />
                    </div>

                    <div>
                        <InputLabel for="bill_reference_number" value="Reference Number" />
                        <TextInput
                            id="bill_reference_number"
                            v-model="form.reference_number"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.reference_number" />
                    </div>

                    <div>
                        <InputLabel for="bill_due_date" value="Due Date" />
                        <TextInput
                            id="bill_due_date"
                            v-model="form.due_date"
                            type="date"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.due_date" />
                    </div>

                    <div>
                        <InputLabel for="bill_currency" value="Currency" />
                        <TextInput
                            id="bill_currency"
                            v-model="form.currency"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g. AUD"
                        />
                        <InputError :message="form.errors.currency" />
                    </div>

                    <div>
                        <InputLabel for="bill_amount" value="Amount" />
                        <TextInput 
                            id="bill_amount" 
                            v-model="form.amount" 
                            type="number" 
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="border rounded-md p-4 bg-gray-50 space-y-4">
                        <h4 class="text-sm font-semibold text-gray-800">Payment Details (Required)</h4>

                        <div>
                            <InputLabel for="bill_payment_method" value="Payment Method" />
                            <select
                                id="bill_payment_method"
                                v-model="form.payment_details.payment_method"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="paypal">PayPal</option>
                                <option value="other">Other</option>
                            </select>
                            <InputError :message="form.errors['payment_details.payment_method']" />
                        </div>

                        <div>
                            <InputLabel for="bill_account_name" value="Account Name" />
                            <TextInput
                                id="bill_account_name"
                                v-model="form.payment_details.account_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.account_name']" />
                        </div>

                        <div>
                            <InputLabel for="bill_account_number" value="Account Number" />
                            <TextInput
                                id="bill_account_number"
                                v-model="form.payment_details.account_number"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.account_number']" />
                        </div>

                        <div>
                            <InputLabel for="bill_bank_name" value="Bank Name" />
                            <TextInput
                                id="bill_bank_name"
                                v-model="form.payment_details.bank_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.bank_name']" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="bill_bsb" value="BSB / Routing" />
                                <TextInput
                                    id="bill_bsb"
                                    v-model="form.payment_details.bsb"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors['payment_details.bsb']" />
                            </div>
                            <div>
                                <InputLabel for="bill_swift" value="SWIFT Code" />
                                <TextInput
                                    id="bill_swift"
                                    v-model="form.payment_details.swift_code"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors['payment_details.swift_code']" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="bill_iban" value="IBAN" />
                            <TextInput
                                id="bill_iban"
                                v-model="form.payment_details.iban"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors['payment_details.iban']" />
                        </div>

                        <div>
                            <InputLabel for="bill_notes" value="Payment Notes" />
                            <textarea
                                id="bill_notes"
                                v-model="form.payment_details.notes"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                rows="2"
                            ></textarea>
                            <InputError :message="form.errors['payment_details.notes']" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitBill" :disabled="form.processing">
                        Create Bill
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showXeroSyncModal" @close="showXeroSyncModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-2">Link Contractor to Xero</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Contractor: <span class="font-medium">{{ xeroSyncContractor?.name }}</span>
                </p>

                <div v-if="xeroSyncError" class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    {{ xeroSyncError }}
                </div>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="xero_contact_candidate" value="Existing Xero Contact" />
                        <select
                            id="xero_contact_candidate"
                            v-model="selectedXeroContactId"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Auto-select if single match</option>
                            <option v-for="option in xeroCandidateOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showXeroSyncModal = false">Cancel</SecondaryButton>
                    <SecondaryButton @click="fetchXeroCandidates" :disabled="xeroSyncLoading">
                        Refresh Matches
                    </SecondaryButton>
                    <SecondaryButton @click="createXeroContactForContractor" :disabled="xeroSyncLoading">
                        Create In Xero
                    </SecondaryButton>
                    <PrimaryButton @click="syncContractorWithXero" :disabled="xeroSyncLoading">
                        Link Selected
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
