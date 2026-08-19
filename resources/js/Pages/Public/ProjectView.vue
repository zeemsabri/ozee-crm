<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import PaymentTermsBuilder from '@/Components/ProjectExpendables/PaymentTermsBuilder.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    branding: {
        type: Object,
        default: () => ({}),
    },
});

// Steps: email → otp → profile (if needed) → proposal → success
const step = ref('email');
const loading = ref(false);
const errorMsg = ref('');
const successMsg = ref('');

// Email step
const email = ref('');

// OTP step
const otp = ref('');

// Profile step
const userName = ref('');
const phone = ref('');

// Proposal step
const proposalScope = ref('milestone');
const selectedMilestoneId = ref(null);
const proposalDescription = ref('');
const proposalAmount = ref('');
const proposalCurrency = ref('USD');
const paymentTerms = ref('');
const proposalDocument = ref(null);
const documentInput = ref(null);
const sessionToken = ref(localStorage.getItem('project_session_' + props.project.token) || '');

const currencies = ['PKR', 'AUD', 'USD', 'EUR', 'GBP', 'INR'];

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    completed: 'bg-blue-100 text-blue-800',
    'in progress': 'bg-indigo-100 text-indigo-800',
    overdue: 'bg-red-100 text-red-800',
    rejected: 'bg-red-100 text-red-800',
    canceled: 'bg-gray-100 text-gray-600',
};

const milestoneStatusColor = (status) => statusColors[status?.toLowerCase()] || 'bg-gray-100 text-gray-600';

const generalDeliverables = computed(() =>
    (props.project.deliverables || []).filter(d => !d.milestone_id)
);

const deliverablesByMilestone = computed(() => {
    const grouped = {};
    (props.project.deliverables || []).forEach((d) => {
        if (!d.milestone_id) return;
        if (!grouped[d.milestone_id]) grouped[d.milestone_id] = [];
        grouped[d.milestone_id].push(d);
    });
    return grouped;
});

function setError(msg) {
    errorMsg.value = msg;
    successMsg.value = '';
    loading.value = false;
}

function applyPrefill(payload) {
    if (!payload) {
        return;
    }

    if (payload.user) {
        userName.value = payload.user.name || userName.value;
        email.value = payload.user.email || email.value;
        phone.value = payload.user.phone || phone.value;
    }

    const last = payload.latest_proposal;
    if (!last) {
        return;
    }

    proposalScope.value = last.proposal_scope || proposalScope.value;
    selectedMilestoneId.value = last.milestone_id ?? selectedMilestoneId.value;
    proposalDescription.value = last.description || proposalDescription.value;
    proposalAmount.value = last.amount != null ? String(last.amount) : proposalAmount.value;
    proposalCurrency.value = last.currency || proposalCurrency.value;
    paymentTerms.value = last.payment_terms || paymentTerms.value;
}

async function hydrateSessionPrefill() {
    if (!sessionToken.value) {
        return false;
    }

    try {
        const { data } = await axios.post(`/projects/classic/${props.project.token}/session`, {
            session_token: sessionToken.value,
        });
        applyPrefill(data);
        return true;
    } catch {
        localStorage.removeItem('project_session_' + props.project.token);
        sessionToken.value = '';
        return false;
    }
}

async function sendOtp() {
    if (!email.value) return setError('Please enter your email address.');
    errorMsg.value = '';
    loading.value = true;
    try {
        await axios.post(`/projects/classic/${props.project.token}/otp`, { email: email.value });
        step.value = 'otp';
    } catch (e) {
        setError(e.response?.data?.errors?.email?.[0] || e.response?.data?.message || 'Failed to send code.');
    } finally {
        loading.value = false;
    }
}

async function verifyOtp() {
    if (otp.value.length !== 6) return setError('Please enter the 6-digit code.');
    errorMsg.value = '';
    loading.value = true;
    try {
        const { data } = await axios.post(`/projects/classic/${props.project.token}/otp/verify`, {
            email: email.value,
            otp: otp.value,
        });
        sessionToken.value = data.session_token;
        localStorage.setItem('project_session_' + props.project.token, data.session_token);
        applyPrefill(data);
        await trackPublicEvent('link_open');
        if (data.needs_profile) {
            userName.value = data.user?.name || '';
            phone.value = data.user?.phone || '';
            step.value = 'profile';
        } else {
            step.value = 'proposal';
        }
    } catch (e) {
        setError(e.response?.data?.message || 'Invalid or expired code.');
    } finally {
        loading.value = false;
    }
}

async function saveProfile() {
    if (!userName.value.trim()) return setError('Please enter your full name.');
    if (!phone.value.trim()) return setError('Please enter your phone number.');
    errorMsg.value = '';
    loading.value = true;
    try {
        await axios.post(`/projects/classic/${props.project.token}/profile`, {
            session_token: sessionToken.value,
            name: userName.value,
            phone: phone.value,
        });
        step.value = 'proposal';
    } catch (e) {
        setError(e.response?.data?.errors?.name?.[0] || e.response?.data?.message || 'Failed to save profile.');
    } finally {
        loading.value = false;
    }
}

async function trackPublicEvent(eventName) {
    if (!sessionToken.value) {
        return;
    }

    try {
        await axios.post(`/projects/classic/${props.project.token}/track`, {
            session_token: sessionToken.value,
            event: eventName,
        });
    } catch {
        // Tracking should never block the user flow.
    }
}

function handleDocumentChange(event) {
    const file = event.target.files[0];
    if (file && file.type === 'application/pdf') {
        proposalDocument.value = file;
    } else {
        proposalDocument.value = null;
        if (documentInput.value) {
            documentInput.value.value = '';
        }
        setError('Please select a valid PDF file.');
    }
}

async function submitProposal() {
    if (proposalScope.value === 'milestone' && !selectedMilestoneId.value) {
        return setError('Please select a milestone.');
    }
    if (!proposalDescription.value.trim() || proposalDescription.value.length < 20)
        return setError('Description must be at least 20 characters.');
    if (!proposalAmount.value || Number(proposalAmount.value) < 1)
        return setError('Please enter a valid amount.');
    errorMsg.value = '';
    successMsg.value = '';
    loading.value = true;
    try {
        const formData = new FormData();
        formData.append('session_token', sessionToken.value);
        formData.append('proposal_scope', proposalScope.value);
        if (selectedMilestoneId.value) {
            formData.append('milestone_id', selectedMilestoneId.value);
        }
        formData.append('description', proposalDescription.value);
        formData.append('amount', proposalAmount.value);
        formData.append('currency', proposalCurrency.value);
        if (paymentTerms.value) {
            formData.append('payment_terms', paymentTerms.value);
        }
        if (proposalDocument.value) {
            formData.append('document', proposalDocument.value);
        }

        await axios.post(`/projects/classic/${props.project.token}/proposals`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        await trackPublicEvent('proposal_submitted');
        step.value = 'confirmation';
        successMsg.value = 'Proposal submitted successfully!';
    } catch (e) {
        const errors = e.response?.data?.errors;
        if (errors) {
            setError(Object.values(errors).flat().join(' '));
        } else {
            setError(e.response?.data?.message || 'Failed to submit proposal.');
        }
    } finally {
        loading.value = false;
    }
}

// On mount: if we already have a cached session token, skip to proposal step
onMounted(() => {
    if (!sessionToken.value) {
        return;
    }

    hydrateSessionPrefill().then((ok) => {
        if (!ok) {
            return;
        }

        trackPublicEvent('link_open');
        step.value = 'proposal';
    });
});

const companyName = computed(() => props.branding?.company?.name || 'Portal');
const companyLogo = computed(() => props.branding?.company?.logo_url || null);
const companyWebsite = computed(() => props.branding?.company?.website || null);
</script>

<template>
    <Head :title="project.name + ' — Submit Proposal'" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-cyan-50 to-blue-100">
        <!-- Header -->
        <header class="backdrop-blur bg-white/80 border-b border-white/70 shadow-sm sticky top-0 z-10">
            <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <img
                        v-if="companyLogo"
                        :src="companyLogo"
                        :alt="`${companyName} logo`"
                        class="h-9 w-auto object-contain"
                    />
                    <div class="min-w-0">
                        <p class="text-lg font-bold text-slate-800 tracking-wide truncate">{{ companyName }}</p>
                        <a
                            v-if="companyWebsite"
                            :href="`https://${companyWebsite.replace(/^https?:\/\//, '')}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs text-cyan-700 hover:text-cyan-800"
                        >
                            {{ companyWebsite }}
                        </a>
                    </div>
                </div>
                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full">Project Brief</span>
            </div>
        </header>

        <main class="max-w-4xl mx-auto px-4 py-8">
            <!-- Project Hero -->
            <div class="bg-white/95 rounded-2xl shadow-lg border border-white/70 p-6 mb-6 relative overflow-hidden">
                <div class="absolute -right-12 -top-12 w-40 h-40 bg-cyan-100 rounded-full opacity-60"></div>
                <div class="absolute -left-8 -bottom-10 w-32 h-32 bg-blue-100 rounded-full opacity-50"></div>
                <div class="flex items-start justify-between flex-wrap gap-3">
                    <div class="relative z-10">
                        <p class="text-xs uppercase tracking-wider text-cyan-700 font-semibold mb-2">Project Opportunity</p>
                        <h1 class="text-3xl font-extrabold text-slate-900 leading-tight">{{ project.name }}</h1>
                        <p v-if="project.description" class="mt-3 text-slate-600 leading-relaxed max-w-2xl">
                            {{ project.description }}
                        </p>
                    </div>
                    <span
                        class="relative z-10 px-3 py-1 rounded-full text-sm font-medium capitalize"
                        :class="milestoneStatusColor(project.status)"
                    >
                        {{ project.status }}
                    </span>
                </div>
            </div>

            <!-- General Deliverables (not linked to milestone) -->
            <section v-if="generalDeliverables.length" class="mb-8">
                <h2 class="text-lg font-semibold text-slate-800 mb-3">Project Deliverables</h2>
                <div class="space-y-3">
                    <div
                        v-for="d in generalDeliverables"
                        :key="d.id"
                        class="bg-white/95 border border-white/80 rounded-xl p-4 shadow-md"
                    >
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <h3 class="font-semibold text-slate-900">{{ d.name }}</h3>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="milestoneStatusColor(d.status)">{{ d.status }}</span>
                        </div>
                        <p v-if="d.description" class="mt-1 text-sm text-slate-600">{{ d.description }}</p>
                        <p v-if="d.due_date" class="mt-1 text-xs text-gray-400">Due: {{ d.due_date }}</p>
                        <div v-if="d.checklist?.length" class="mt-3 pt-3 border-t border-slate-100">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">
                                Checklist
                            </p>
                            <ul class="space-y-1.5">
                                <li
                                    v-for="(item, index) in d.checklist"
                                    :key="`${d.id}-check-${index}`"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <span
                                        class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border text-[10px] font-semibold"
                                        :class="item.completed
                                            ? 'border-emerald-300 bg-emerald-100 text-emerald-700'
                                            : 'border-slate-300 bg-white text-slate-400'"
                                    >
                                        {{ item.completed ? '✓' : '' }}
                                    </span>
                                    <span :class="item.completed ? 'text-slate-500 line-through' : 'text-slate-700'">
                                        {{ item.name }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Milestones -->
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-slate-800 mb-3">Active Milestones</h2>
                <div v-if="project.milestones.length === 0" class="text-gray-400 text-sm">
                    No active milestones available right now.
                </div>
                <div class="space-y-3">
                    <div
                        v-for="m in project.milestones"
                        :key="m.id"
                        class="bg-white/95 border border-white/80 rounded-xl p-4 shadow-md hover:shadow-lg transition-shadow"
                    >
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <h3 class="font-semibold text-slate-900">{{ m.name }}</h3>
                            <span
                                class="px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="milestoneStatusColor(m.status)"
                            >{{ m.status }}</span>
                        </div>
                        <p v-if="m.description" class="mt-1 text-sm text-slate-600">{{ m.description }}</p>
                        <p v-if="m.completion_date" class="mt-1 text-xs text-gray-400">Due: {{ m.completion_date }}</p>

                        <div v-if="deliverablesByMilestone[m.id]?.length" class="mt-3 pt-3 border-t border-slate-100">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Deliverables</p>
                            <div class="space-y-2">
                                <div
                                    v-for="d in deliverablesByMilestone[m.id]"
                                    :key="d.id"
                                    class="bg-slate-50 border border-slate-100 rounded-lg p-3"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-medium text-slate-800">{{ d.name }}</p>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium capitalize" :class="milestoneStatusColor(d.status)">{{ d.status }}</span>
                                    </div>
                                    <p v-if="d.description" class="text-xs text-slate-500 mt-1">{{ d.description }}</p>
                                    <p v-if="d.due_date" class="text-[11px] text-gray-400 mt-1">Due: {{ d.due_date }}</p>
                                    <div v-if="d.checklist?.length" class="mt-2 pt-2 border-t border-slate-200">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1.5">
                                            Checklist
                                        </p>
                                        <ul class="space-y-1">
                                            <li
                                                v-for="(item, index) in d.checklist"
                                                :key="`${d.id}-milestone-check-${index}`"
                                                class="flex items-start gap-2 text-xs"
                                            >
                                                <span
                                                    class="mt-0.5 inline-flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border text-[9px] font-semibold"
                                                    :class="item.completed
                                                        ? 'border-emerald-300 bg-emerald-100 text-emerald-700'
                                                        : 'border-slate-300 bg-white text-slate-400'"
                                                >
                                                    {{ item.completed ? '✓' : '' }}
                                                </span>
                                                <span :class="item.completed ? 'text-slate-400 line-through' : 'text-slate-600'">
                                                    {{ item.name }}
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Proposal Flow Card -->
            <div
                v-if="step !== 'success'"
                class="bg-white/95 rounded-2xl shadow-lg border border-white/70 p-6"
            >
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <template v-if="step === 'email'">Verify Your Email</template>
                    <template v-else-if="step === 'otp'">Enter Verification Code</template>
                    <template v-else-if="step === 'profile'">Complete Your Profile</template>
                    <template v-else-if="step === 'proposal'">Submit Proposal</template>
                </h2>

                <div class="mb-4 p-3 rounded-lg bg-blue-50 border border-blue-100 text-blue-700 text-sm">
                    Public submissions can be sent for a milestone or for the whole project. Include your proposed payment terms so the internal team can review your structure.
                </div>

                <div v-if="errorMsg" class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                    {{ errorMsg }}
                </div>

                <!-- Step: Email -->
                <div v-if="step === 'email'" class="space-y-4">
                    <p class="text-sm text-gray-500">We'll send a 6-digit code to verify your email address.</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input
                            v-model="email"
                            type="email"
                            placeholder="you@example.com"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @keyup.enter="sendOtp"
                        />
                    </div>
                    <button
                        @click="sendOtp"
                        :disabled="loading"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg transition"
                    >
                        {{ loading ? 'Sending…' : 'Send Code' }}
                    </button>
                </div>

                <!-- Step: OTP -->
                <div v-else-if="step === 'otp'" class="space-y-4">
                    <p class="text-sm text-gray-500">A 6-digit code was sent to <strong>{{ email }}</strong>. It expires in 10 minutes.</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Verification Code</label>
                        <input
                            v-model="otp"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            placeholder="123456"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm tracking-widest text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @keyup.enter="verifyOtp"
                        />
                    </div>
                    <button
                        @click="verifyOtp"
                        :disabled="loading"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg transition"
                    >
                        {{ loading ? 'Verifying…' : 'Verify Code' }}
                    </button>
                    <button @click="step = 'email'" class="w-full text-sm text-gray-400 hover:text-gray-600 underline">
                        Use a different email
                    </button>
                </div>

                <!-- Step: Profile -->
                <div v-else-if="step === 'profile'" class="space-y-4">
                    <p class="text-sm text-gray-500">Please provide your details so we can follow up on your proposal.</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input
                            v-model="userName"
                            type="text"
                            placeholder="Your full name"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input
                            v-model="phone"
                            type="tel"
                            placeholder="+1 555 000 0000"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <button
                        @click="saveProfile"
                        :disabled="loading"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg transition"
                    >
                        {{ loading ? 'Saving…' : 'Save & Continue' }}
                    </button>
                </div>

                <!-- Step: Proposal -->
                <div v-else-if="step === 'proposal'" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Proposal Type</label>
                        <select
                            v-model="proposalScope"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="milestone">Milestone Proposal</option>
                            <option value="project">Whole Project Proposal</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">
                            Use milestone proposals for specific deliverables or whole-project proposals for broader scope.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Milestone Proposal For</label>
                        <select
                            v-if="proposalScope === 'milestone'"
                            v-model="selectedMilestoneId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null" disabled>Select a milestone</option>
                            <option v-for="m in project.milestones" :key="m.id" :value="m.id">
                                {{ m.name }}
                            </option>
                        </select>
                        <div v-else class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-600">
                            This proposal will be submitted for the whole project.
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Choose what this proposal applies to before adding amount and terms.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cover Letter</label>
                        <textarea
                            v-model="proposalDescription"
                            rows="5"
                            placeholder="Write a cover letter describing your experience, approach, and what you'll deliver..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p class="text-xs text-gray-400 mt-1">Minimum 20 characters in your cover letter ({{ proposalDescription.length }})</p>
                    </div>
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Proposed Amount</label>
                            <input
                                v-model="proposalAmount"
                                type="number"
                                min="1"
                                placeholder="0.00"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div class="w-28">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                            <select
                                v-model="proposalCurrency"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Terms</label>
                        <PaymentTermsBuilder
                            v-model="paymentTerms"
                            :contract-amount="proposalAmount || 0"
                            :contract-currency="proposalCurrency"
                            :milestones="project.milestones || []"
                        />
                        <p class="text-xs text-gray-400 mt-1">Optional, but recommended for faster approval.</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supporting Document (Optional PDF)</label>
                        <input
                            type="file"
                            accept=".pdf"
                            ref="documentInput"
                            @change="handleDocumentChange"
                            class="w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-50 file:text-blue-700
                                hover:file:bg-blue-100"
                        />
                        <p class="text-xs text-gray-400 mt-1">Upload an optional PDF document to support your proposal.</p>
                    </div>

                    <button
                        @click="submitProposal"
                        :disabled="loading"
                        class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg transition"
                    >
                        {{ loading ? 'Submitting…' : 'Submit Proposal' }}
                    </button>
                </div>
            </div>

            <!-- Confirmation Step -->
            <div
                v-if="step === 'confirmation'"
                class="bg-white/95 rounded-2xl shadow-lg border border-white/70 p-8 text-center max-w-lg mx-auto"
            >
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Proposal Submitted</h2>
                <p class="text-gray-600 mb-6">
                    Thank you! Your proposal has been sent successfully. We will review it and get back to you shortly.
                </p>
                <button
                    @click="step = 'proposal'; successMsg = ''; errorMsg = '';"
                    class="bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-2 px-6 rounded-lg transition"
                >
                    Edit Proposal
                </button>
            </div>

        </main>
    </div>
</template>
