<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { formatCurrency } from '@/Utils/currency';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { success, error } from '@/Utils/notification';
import axios from 'axios';

const props = defineProps({
    bill: {
        type: Object,
        required: true,
    },
    transaction_types: {
        type: Array,
        default: () => [],
    },
});

const bill = computed(() => props.bill);
const canEdit = computed(() => bill.value?.status === 'pending_approval');
const xeroAccounts = ref([]);
const xeroTaxTypeOptions = [
    { value: 'BASEXCLUDED', label: 'BAS Excluded' },
    { value: 'EXEMPTEXPENSES', label: 'GST Free Expenses' },
    { value: 'EXEMPTOUTPUT', label: 'GST Free Income' },
    { value: 'INPUT', label: 'GST on Expenses' },
    { value: 'INPUTTAXED', label: 'GST on Imports' },
    { value: 'OUTPUT', label: 'GST on Income' },
];
const xeroTaxTypeLabels = Object.fromEntries(xeroTaxTypeOptions.map((option) => [option.value, option.label]));
const xeroAccountOptions = computed(() => {
    const liveCodes = xeroAccounts.value.map((account) => account.code);
    const mappedCodes = props.transaction_types
        .map((transactionType) => transactionType.xero_account_code)
        .filter(Boolean)
        .map((code) => String(code).toUpperCase());

    const uniqueCodes = [...new Set([...liveCodes, ...mappedCodes])];

    if (bill.value?.xero_account_code && !uniqueCodes.includes(bill.value.xero_account_code)) {
        uniqueCodes.unshift(bill.value.xero_account_code);
    }

    if (!bill.value?.xero_account_code && bill.value?.transactionType?.xero_account_code && !uniqueCodes.includes(bill.value.transactionType.xero_account_code)) {
        uniqueCodes.unshift(bill.value.transactionType.xero_account_code);
    }
    return uniqueCodes.map((code) => {
        const account = xeroAccounts.value.find((item) => item.code === code);
        return {
            value: code,
            label: account ? `${account.code} - ${account.name}` : code,
        };
    });
});

const form = useForm({
    amount: bill.value?.amount || '',
    reference_number: bill.value?.reference_number || '',
    due_date: bill.value?.due_date || '',
    currency: bill.value?.currency || bill.value?.project?.currency || 'AUD',
    xero_account_code: bill.value?.xero_account_code || bill.value?.transactionType?.xero_account_code || '',
    xero_tax_type: bill.value?.xero_tax_type === 'NONE' ? 'EXEMPTEXPENSES' : (bill.value?.xero_tax_type || 'INPUT'),
    payment_details: {
        payment_method: bill.value?.payment_detail?.payment_method || 'bank_transfer',
        account_name: bill.value?.payment_detail?.details?.account_name || '',
        account_number: bill.value?.payment_detail?.details?.account_number || '',
        bank_name: bill.value?.payment_detail?.details?.bank_name || '',
        bsb: bill.value?.payment_detail?.details?.bsb || '',
        swift_code: bill.value?.payment_detail?.details?.swift_code || '',
        iban: bill.value?.payment_detail?.details?.iban || '',
        notes: bill.value?.payment_detail?.details?.notes || '',
    },
});

const statusLabel = computed(() => (bill.value?.status || '').replace(/_/g, ' ').toUpperCase());

const taxLabel = computed(() => {
    if (!bill.value?.xero_tax_type) {
        return 'N/A';
    }

    if (bill.value.xero_tax_type === 'NONE') {
        return 'NONE (legacy)';
    }

    return xeroTaxTypeLabels[bill.value.xero_tax_type] || bill.value.xero_tax_type;
});

const authUser = computed(() => usePage().props.auth?.user);

const currentPendingStep = computed(() => {
    const steps = bill.value?.approval_instance?.steps;
    if (!steps?.length) return null;
    return steps.find((s) => s.status === 'pending') || null;
});

const canApproveBill = computed(() => {
    if (bill.value?.status !== 'pending_approval') return false;
    const step = currentPendingStep.value;
    if (!step) {
        // No active flow step — show button only when there's no instance (super admin path)
        return !bill.value?.approval_instance;
    }
    const user = authUser.value;
    if (!user) return false;
    if (step.approver_type === 'user') return Number(step.approver_user_id) === Number(user.id);
    if (step.approver_type === 'role') return Number(step.approver_role_id) === Number(user.role_id);
    return false;
});

const approveProcessing = ref(false);
const approveForm = ref({
    xero_account_code: '',
    xero_tax_type: 'INPUT',
});

const approveBill = async () => {
    if (!confirm('Approve this bill?')) return;
    approveProcessing.value = true;
    try {
        await axios.post(route('api.bills.approve', { bill: bill.value.id }), {
            xero_account_code: approveForm.value.xero_account_code || bill.value.xero_account_code || bill.value.transactionType?.xero_account_code || '',
            xero_tax_type: approveForm.value.xero_tax_type || bill.value.xero_tax_type || 'INPUT',
        });
        success('Bill approved.');
        window.location.reload();
    } catch (err) {
        error(err.response?.data?.message || 'Approval failed.');
    } finally {
        approveProcessing.value = false;
    }
};

const saveBill = async () => {
    try {
        const payload = {
            amount: form.amount,
            reference_number: form.reference_number,
            due_date: form.due_date,
            currency: form.currency,
            xero_account_code: form.xero_account_code,
            xero_tax_type: form.xero_tax_type,
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

        await axios.put(route('api.bills.update', { project: bill.value.project_id, bill: bill.value.id }), payload);
        success('Bill updated successfully.');
        window.location.reload();
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            form.setError(err.response.data.errors);
            error(err.response?.data?.message || 'Please review the form and try again.');
            return;
        }

        error(err.response?.data?.message || 'Failed to update bill.');
    }
};

const fetchXeroAccounts = async () => {
    try {
        const { data } = await axios.get(route('api.xero.accounts'));
        xeroAccounts.value = Array.isArray(data) ? data : [];
    } catch {
        xeroAccounts.value = [];
    }
};

onMounted(() => {
    fetchXeroAccounts();
});
</script>

<template>
    <Head :title="`Bill #${bill.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bill #{{ bill.id }}</h2>
                    <p class="text-sm text-gray-500">{{ bill.project?.name }} · {{ bill.expendable?.name }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('admin.financials.bills')">
                        <PrimaryButton>Back to Bills</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow sm:rounded-lg border border-gray-200 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Bill Summary</h3>
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Status</dt>
                                    <dd class="font-medium text-gray-900">{{ statusLabel }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Contractor</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.contractor?.name || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Amount</dt>
                                    <dd class="font-medium text-gray-900">{{ formatCurrency(bill.amount, bill.currency || bill.project?.currency || 'AUD') }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Reference Number</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.reference_number || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Due Date</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.due_date || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Transaction Type</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.transactionType?.name || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Xero Account</dt>
                                    <dd class="font-medium text-gray-900">{{ bill.xero_account_code || bill.transactionType?.xero_account_code || 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Tax Type</dt>
                                    <dd class="font-medium text-gray-900">{{ taxLabel }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Xero Invoice ID</dt>
                                    <dd class="font-medium text-gray-900 break-all flex items-center gap-2">
                                        {{ bill.xero_invoice_id || 'Not synced yet' }}
                                        <a v-if="bill.xero_invoice_id" :href="`https://go.xero.com/AccountsPayable/View.aspx?invoiceID=${bill.xero_invoice_id}`" target="_blank" class="text-indigo-600 hover:text-indigo-900 text-xs">
                                            (View in Xero)
                                        </a>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Payment Details Snapshot</h3>
                            <div v-if="bill.payment_detail" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm space-y-2">
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Method</span><span class="font-medium">{{ bill.payment_detail.payment_method }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Account Name</span><span class="font-medium">{{ bill.payment_detail.details?.account_name }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Account Number</span><span class="font-medium">{{ bill.payment_detail.details?.account_number }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Bank Name</span><span class="font-medium">{{ bill.payment_detail.details?.bank_name || 'N/A' }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">BSB</span><span class="font-medium">{{ bill.payment_detail.details?.bsb || 'N/A' }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">SWIFT</span><span class="font-medium">{{ bill.payment_detail.details?.swift_code || 'N/A' }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">IBAN</span><span class="font-medium">{{ bill.payment_detail.details?.iban || 'N/A' }}</span></div>
                                <div class="flex justify-between gap-4"><span class="text-gray-500">Notes</span><span class="font-medium text-right max-w-xs">{{ bill.payment_detail.details?.notes || 'N/A' }}</span></div>
                            </div>
                            <div v-else class="text-sm text-gray-500">No payment snapshot stored.</div>
                        </div>
                    </div>
                </div>

                <div v-if="canEdit" class="bg-white shadow sm:rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Before Approval</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="bill_amount" value="Amount" />
                            <TextInput id="bill_amount" v-model="form.amount" type="number" step="0.01" class="mt-1 block w-full" />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div>
                            <InputLabel for="bill_reference_number" value="Reference Number" />
                            <TextInput id="bill_reference_number" v-model="form.reference_number" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors.reference_number" />
                        </div>
                        <div>
                            <InputLabel for="bill_due_date" value="Due Date" />
                            <TextInput id="bill_due_date" v-model="form.due_date" type="date" class="mt-1 block w-full" />
                            <InputError :message="form.errors.due_date" />
                        </div>
                        <div>
                            <InputLabel for="bill_currency" value="Currency" />
                            <TextInput id="bill_currency" v-model="form.currency" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors.currency" />
                        </div>
                        <div>
                            <InputLabel for="bill_xero_account_code" value="Xero Account" />
                            <select id="bill_xero_account_code" v-model="form.xero_account_code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select Xero account</option>
                                <option v-for="option in xeroAccountOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.xero_account_code" />
                        </div>
                        <div>
                            <InputLabel for="bill_xero_tax_type" value="Tax Type" />
                            <select id="bill_xero_tax_type" v-model="form.xero_tax_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="option in xeroTaxTypeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.xero_tax_type" />
                        </div>
                        <div>
                            <InputLabel for="bill_payment_method" value="Payment Method" />
                            <select id="bill_payment_method" v-model="form.payment_details.payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="paypal">PayPal</option>
                                <option value="other">Other</option>
                            </select>
                            <InputError :message="form.errors['payment_details.payment_method']" />
                        </div>
                        <div>
                            <InputLabel for="bill_account_name" value="Account Name" />
                            <TextInput id="bill_account_name" v-model="form.payment_details.account_name" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.account_name']" />
                        </div>
                        <div>
                            <InputLabel for="bill_account_number" value="Account Number" />
                            <TextInput id="bill_account_number" v-model="form.payment_details.account_number" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.account_number']" />
                        </div>
                        <div>
                            <InputLabel for="bill_bank_name" value="Bank Name" />
                            <TextInput id="bill_bank_name" v-model="form.payment_details.bank_name" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.bank_name']" />
                        </div>
                        <div>
                            <InputLabel for="bill_bsb" value="BSB / Routing" />
                            <TextInput id="bill_bsb" v-model="form.payment_details.bsb" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.bsb']" />
                        </div>
                        <div>
                            <InputLabel for="bill_swift" value="SWIFT Code" />
                            <TextInput id="bill_swift" v-model="form.payment_details.swift_code" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.swift_code']" />
                        </div>
                        <div>
                            <InputLabel for="bill_iban" value="IBAN" />
                            <TextInput id="bill_iban" v-model="form.payment_details.iban" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors['payment_details.iban']" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="bill_notes" value="Payment Notes" />
                            <textarea id="bill_notes" v-model="form.payment_details.notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3"></textarea>
                            <InputError :message="form.errors['payment_details.notes']" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton :disabled="form.processing" @click="window.location.reload()">Reset</SecondaryButton>
                        <PrimaryButton :disabled="form.processing" @click="saveBill">
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </PrimaryButton>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Approval Flow</h3>
                    <div v-if="bill.approval_instance?.steps?.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Step</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Approver</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acted By</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Comment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="step in bill.approval_instance.steps" :key="step.id">
                                    <td class="px-4 py-2 text-sm">{{ step.step_order }}</td>
                                    <td class="px-4 py-2 text-sm">{{ step.label || (step.approver_type === 'role' ? step.approver_role?.name : step.approver_user?.name) }}</td>
                                    <td class="px-4 py-2 text-sm">{{ (step.status || '').toUpperCase() }}</td>
                                    <td class="px-4 py-2 text-sm">{{ step.acted_by?.name || '—' }}</td>
                                    <td class="px-4 py-2 text-sm">{{ step.comment || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-sm text-gray-500">No approval flow assigned to this bill.</div>

                    <div v-if="canApproveBill" class="mt-6 border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">
                            Your Approval Action
                            <span v-if="currentPendingStep" class="ml-2 text-xs font-normal text-gray-500">
                                (Step {{ currentPendingStep.step_order }}: {{ currentPendingStep.label || (currentPendingStep.approver_type === 'role' ? currentPendingStep.approver_role?.name : currentPendingStep.approver_user?.name) }})
                            </span>
                        </h4>
                        <div class="flex items-end gap-3 flex-wrap">
                            <div>
                                <InputLabel value="Xero Account Code" />
                                <select v-model="approveForm.xero_account_code" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">Use bill's current code</option>
                                    <option v-for="option in xeroAccountOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Tax Type" />
                                <select v-model="approveForm.xero_tax_type" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option v-for="option in xeroTaxTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <PrimaryButton :disabled="approveProcessing" @click="approveBill" class="bg-green-600 hover:bg-green-700 focus:ring-green-500">
                                {{ approveProcessing ? 'Approving…' : 'Approve Bill' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
