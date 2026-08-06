<script setup>
import { ref, reactive, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    projects: {
        type: Array,
        default: () => [],
    },
    emailApps: {
        type: Array,
        default: () => [],
    },
    stripeConfigurations: {
        type: Array,
        default: () => [],
    },
    viewToken: {
        type: Object,
        default: null,
    }
});

const emit = defineEmits(['close', 'submitted']);

const currentStep = ref(1);
const integrationType = ref('stripe'); // 'stripe', 'email', 'custom'
const isSubmitting = ref(false);
const errors = ref({});

const form = reactive({
    label: '',
    email: '',
    project_id: '',
    email_app_id: '',
    stripe_app_id: '',
    expires_at: '',
    max_uses: null,
    whitelist_domains: [],
    whitelist_ips: [],
});

const domainInput = ref('');
const ipInput = ref('');
const generatedToken = ref('');
const activeSnippetTab = ref('curl');

// Local tracking of email apps to allow inline additions
const localEmailApps = ref([]);

// Inline Email App Form State
const showNewEmailAppForm = ref(false);
const isCreatingEmailApp = ref(false);
const emailAppErrors = ref({});
const newEmailApp = reactive({
    name: '',
    delivery_mode: 'smtp',
    smtp_host: '',
    smtp_port: 587,
    smtp_username: '',
    smtp_password: '',
    smtp_encryption: 'tls',
    smtp_from_address: '',
    smtp_from_name: '',
});

// Watch to sync props email apps
watch(() => props.emailApps, (newApps) => {
    localEmailApps.value = [...(newApps || [])];
}, { immediate: true });

// If viewToken is passed, configure wizard to show snippets directly for that token
watch(() => props.viewToken, (newToken) => {
    if (newToken) {
        generatedToken.value = newToken.token;
        form.label = newToken.label;
        form.email = newToken.email || '';
        form.project_id = newToken.project_id || '';
        form.email_app_id = newToken.email_app_id || '';
        form.stripe_app_id = '';
        form.whitelist_domains = [...(newToken.whitelist?.domains || [])];
        form.whitelist_ips = [...(newToken.whitelist?.ips || [])];
        
        // Infer integration type
        if (newToken.email_app_id) {
            integrationType.value = 'email';
        } else {
            if (newToken.label.toLowerCase().includes('stripe')) {
                integrationType.value = 'stripe';
            } else {
                integrationType.value = 'custom';
            }
        }
        currentStep.value = 5;
    }
}, { immediate: true });

watch(() => props.show, (newShow) => {
    if (newShow && !props.viewToken) {
        currentStep.value = 1;
        integrationType.value = 'stripe';
        errors.value = {};
        form.label = 'Stripe Checkout Integration';
        form.email = '';
        form.project_id = '';
        form.email_app_id = '';
        form.stripe_app_id = '';
        form.expires_at = '';
        form.max_uses = null;
        form.whitelist_domains = [];
        form.whitelist_ips = [];
        domainInput.value = '';
        ipInput.value = '';
        generatedToken.value = '';
        showNewEmailAppForm.value = false;
        resetNewEmailAppForm();
    }
});

const resetNewEmailAppForm = () => {
    newEmailApp.name = '';
    newEmailApp.delivery_mode = 'smtp';
    newEmailApp.smtp_host = '';
    newEmailApp.smtp_port = 587;
    newEmailApp.smtp_username = '';
    newEmailApp.smtp_password = '';
    newEmailApp.smtp_encryption = 'tls';
    newEmailApp.smtp_from_address = '';
    newEmailApp.smtp_from_name = '';
    emailAppErrors.value = {};
};

const addDomain = () => {
    if (domainInput.value && !form.whitelist_domains.includes(domainInput.value)) {
        form.whitelist_domains.push(domainInput.value);
        domainInput.value = '';
    }
};

const removeDomain = (index) => {
    form.whitelist_domains.splice(index, 1);
};

const addIp = () => {
    if (ipInput.value && !form.whitelist_ips.includes(ipInput.value)) {
        form.whitelist_ips.push(ipInput.value);
        ipInput.value = '';
    }
};

const removeIp = (index) => {
    form.whitelist_ips.splice(index, 1);
};

const handleClose = () => {
    emit('close');
};

const selectType = (type) => {
    integrationType.value = type;
    
    // Auto-fill label default based on selection
    if (type === 'stripe') {
        form.label = 'Stripe Checkout Integration';
        if (props.stripeConfigurations.length > 0) {
            form.stripe_app_id = props.stripeConfigurations[0].app_id;
        }
    } else if (type === 'email') {
        form.label = 'External Email Sender';
        if (localEmailApps.value.length > 0) {
            form.email_app_id = localEmailApps.value[0].id;
        }
    } else {
        form.label = 'Custom API Connection';
    }
    
    currentStep.value = 2;
};

const createEmailAppInline = async () => {
    isCreatingEmailApp.value = true;
    emailAppErrors.value = {};

    try {
        const response = await window.axios.post(route('admin.email-apps.store'), newEmailApp);
        if (response.data && response.data.emailApp) {
            const createdApp = response.data.emailApp;
            localEmailApps.value.push(createdApp);
            form.email_app_id = createdApp.id;
            showNewEmailAppForm.value = false;
            resetNewEmailAppForm();
            alert('New Email App successfully created and linked!');
        }
    } catch (err) {
        if (err.response && err.response.status === 422) {
            emailAppErrors.value = err.response.data.errors || {};
        } else {
            alert('Failed to create Email App. Please check fields.');
        }
    } finally {
        isCreatingEmailApp.value = false;
    }
};

const nextStep = () => {
    errors.value = {};
    if (currentStep.value === 2) {
        if (!form.label) {
            errors.value.label = 'Token label is required.';
            return;
        }
        if (integrationType.value === 'stripe' && !form.stripe_app_id) {
            errors.value.stripe_app_id = 'Please select a Stripe App Configuration.';
            return;
        }
    } else if (currentStep.value === 3) {
        if (integrationType.value === 'email' && !form.email_app_id) {
            errors.value.email_app_id = 'Please link an Email App to proceed.';
            return;
        }
    }
    currentStep.value++;
};

const prevStep = () => {
    currentStep.value--;
};

const generateToken = async () => {
    isSubmitting.value = true;
    errors.value = {};
    
    try {
        const payload = {
            label: form.label,
            email: form.email || null,
            project_id: form.project_id || null,
            email_app_id: form.email_app_id || null,
            expires_at: form.expires_at || null,
            max_uses: form.max_uses || null,
            whitelist_domains: form.whitelist_domains,
            whitelist_ips: form.whitelist_ips,
        };

        const response = await window.axios.post(route('admin.external-tokens.store'), payload);
        if (response.data && response.data.token) {
            generatedToken.value = response.data.token.token;
        }
        emit('submitted');
        currentStep.value = 5;
    } catch (err) {
        if (err.response && err.response.status === 422) {
            errors.value = err.response.data.errors || {};
        } else {
            errors.value.general = 'Failed to generate token. Please try again.';
        }
    } finally {
        isSubmitting.value = false;
    }
};

const activeTokenValue = computed(() => {
    return generatedToken.value || 'YOUR_SECRET_TOKEN';
});

const activeEmailAppId = computed(() => {
    return form.email_app_id || 'YOUR_EMAIL_APP_ID';
});

const activeStripeAppId = computed(() => {
    return form.stripe_app_id || 'stripe-app-identifier';
});

// Snippet codes
const curlSnippet = computed(() => {
    if (integrationType.value === 'email' || form.email_app_id) {
        return `curl -X POST "${window.location.origin}/api/external/email/send" \\
  -H "Content-Type: application/json" \\
  -H "X-Magic-Token: ${activeTokenValue.value}" \\
  -d '{
    "app_id": ${activeEmailAppId.value},
    "to": "customer@example.com",
    "subject": "Hello from Portal",
    "body_html": "<p>Thank you for your order!</p>"
  }'`;
    } else if (integrationType.value === 'stripe') {
        return `curl -X POST "${window.location.origin}/api/external/payment/create-session" \\
  -H "Content-Type: application/json" \\
  -H "X-Magic-Token: ${activeTokenValue.value}" \\
  -d '{
    "app_id": "${activeStripeAppId.value}",
    "mode": "payment",
    "line_items": [
      {
        "price_data": {
          "currency": "usd",
          "product_data": {
            "name": "Service Purchase"
          },
          "unit_amount": 2500
        },
        "quantity": 1
      }
    ],
    "success_url": "https://yourwebsite.com/success",
    "cancel_url": "https://yourwebsite.com/cancel"
  }'`;
    } else {
        return `curl -X GET "${window.location.origin}/api/activity/tasks" \\
  -H "X-Magic-Token: ${activeTokenValue.value}"`;
    }
});

const nodeSnippet = computed(() => {
    if (integrationType.value === 'email' || form.email_app_id) {
        return `const axios = require('axios');

axios.post('${window.location.origin}/api/external/email/send', {
  app_id: ${activeEmailAppId.value},
  to: 'customer@example.com',
  subject: 'Hello from Portal',
  body_html: '<p>Thank you for your order!</p>'
}, {
  headers: {
    'X-Magic-Token': '${activeTokenValue.value}',
    'Content-Type': 'application/json'
  }
})
.then(res => console.log(res.data))
.catch(err => console.error(err));`;
    } else if (integrationType.value === 'stripe') {
        return `const axios = require('axios');

axios.post('${window.location.origin}/api/external/payment/create-session', {
  app_id: '${activeStripeAppId.value}',
  mode: 'payment',
  line_items: [{
    price_data: {
      currency: 'usd',
      product_data: { name: 'Service Purchase' },
      unit_amount: 2500
    },
    quantity: 1
  }],
  success_url: 'https://yourwebsite.com/success',
  cancel_url: 'https://yourwebsite.com/cancel'
}, {
  headers: {
    'X-Magic-Token': '${activeTokenValue.value}',
    'Content-Type': 'application/json'
  }
})
.then(res => console.log(res.data))
.catch(err => console.error(err));`;
    } else {
        return `const axios = require('axios');

axios.get('${window.location.origin}/api/activity/tasks', {
  headers: { 'X-Magic-Token': '${activeTokenValue.value}' }
})
.then(res => console.log(res.data))
.catch(err => console.error(err));`;
    }
});

const phpSnippet = computed(() => {
    if (integrationType.value === 'email' || form.email_app_id) {
        return `<?php
$client = new \\GuzzleHttp\\Client();

$response = $client->post('${window.location.origin}/api/external/email/send', [
    'headers' => [
        'X-Magic-Token' => '${activeTokenValue.value}',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'app_id' => ${activeEmailAppId.value},
        'to' => 'customer@example.com',
        'subject' => 'Hello from Portal',
        'body_html' => '<p>Thank you for your order!</p>',
    ]
]);

echo $response->getBody();`;
    } else if (integrationType.value === 'stripe') {
        return `<?php
$client = new \\GuzzleHttp\\Client();

$response = $client->post('${window.location.origin}/api/external/payment/create-session', [
    'headers' => [
        'X-Magic-Token' => '${activeTokenValue.value}',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'app_id' => '${activeStripeAppId.value}',
        'mode' => 'payment',
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => ['name' => 'Service Purchase'],
                'unit_amount' => 2500,
            ],
            'quantity' => 1,
        ]],
        'success_url' => 'https://yourwebsite.com/success',
        'cancel_url' => 'https://yourwebsite.com/cancel',
    ]
]);

echo $response->getBody();`;
    } else {
        return `<?php
$client = new \\GuzzleHttp\\Client();

$response = $client->get('${window.location.origin}/api/activity/tasks', [
    'headers' => [
        'X-Magic-Token' => '${activeTokenValue.value}',
    ]
]);

echo $response->getBody();`;
    }
});

const copySnippet = (text) => {
    navigator.clipboard.writeText(text);
    alert('Code snippet copied to clipboard!');
};

const copyToClipboard = (text, message = 'Copied to clipboard!') => {
    navigator.clipboard.writeText(text);
    alert(message);
};
</script>

<template>
    <Modal :show="show" @close="handleClose" max-width="2xl">
        <div class="p-6 bg-slate-50 rounded-lg shadow-xl text-gray-800 flex flex-col max-h-[90vh] overflow-hidden">
            <!-- Wizard Header (Static) -->
            <div class="flex justify-between items-center pb-4 mb-4 border-b border-gray-200 shrink-0">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 flex items-center">
                        <span class="mr-2 text-indigo-600 font-extrabold">✨</span>
                        {{ viewToken ? 'Developer Integration Snippets' : 'Token Creation Wizard' }}
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ viewToken ? 'Interactive code setups and configurations for this token' : 'Configure your API access token with guided step-by-step assistance' }}
                    </p>
                </div>
                <button @click="handleClose" class="text-gray-400 hover:text-gray-500 transition-colors">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Scrollable Content Body -->
            <div class="flex-1 overflow-y-auto pr-1 space-y-4 min-h-[30vh]">
                <!-- Steps Progress Bar (Hidden in View Mode) -->
                <div v-if="currentStep <= 4" class="mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold', currentStep === 1 ? 'bg-indigo-600 text-white shadow-md' : currentStep > 1 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-500']">1</span>
                            <span class="text-xs font-medium text-gray-600">Purpose</span>
                        </div>
                        <div class="flex-1 h-0.5 mx-2 bg-gray-200">
                            <div class="h-full bg-indigo-600 transition-all duration-300" :style="{ width: currentStep > 1 ? '100%' : '0%' }"></div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold', currentStep === 2 ? 'bg-indigo-600 text-white shadow-md' : currentStep > 2 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-500']">2</span>
                            <span class="text-xs font-medium text-gray-600">Configure</span>
                        </div>
                        <div class="flex-1 h-0.5 mx-2 bg-gray-200">
                            <div class="h-full bg-indigo-600 transition-all duration-300" :style="{ width: currentStep > 2 ? '100%' : '0%' }"></div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold', currentStep === 3 ? 'bg-indigo-600 text-white shadow-md' : currentStep > 3 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-500']">3</span>
                            <span class="text-xs font-medium text-gray-600">Email App</span>
                        </div>
                        <div class="flex-1 h-0.5 mx-2 bg-gray-200">
                            <div class="h-full bg-indigo-600 transition-all duration-300" :style="{ width: currentStep > 3 ? '100%' : '0%' }"></div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold', currentStep === 4 ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-200 text-gray-500']">4</span>
                            <span class="text-xs font-medium text-gray-600">Security & Review</span>
                        </div>
                    </div>
                </div>

                <!-- STEP 1: Integration Purpose -->
                <div v-if="currentStep === 1" class="space-y-4">
                    <div class="text-center mb-6">
                        <h3 class="text-lg font-bold text-gray-900">What will this token be used for?</h3>
                        <p class="text-sm text-gray-500">Choose the integration type to configure helper steps and default parameters.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Stripe Card -->
                        <button 
                            type="button"
                            @click="selectType('stripe')"
                            class="flex flex-col text-left p-5 bg-white border border-gray-200 rounded-xl hover:border-indigo-500 hover:shadow-md transition-all group"
                        >
                            <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center mb-4 group-hover:bg-indigo-100 transition-colors">
                                <span class="text-xl">💳</span>
                            </div>
                            <h4 class="font-semibold text-gray-900 group-hover:text-indigo-600">Stripe Payments</h4>
                            <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                                Create a token to initiate Stripe Checkout sessions, manage subscriptions, or update billing configs.
                            </p>
                        </button>

                        <!-- Email Card -->
                        <button 
                            type="button"
                            @click="selectType('email')"
                            class="flex flex-col text-left p-5 bg-white border border-gray-200 rounded-xl hover:border-indigo-500 hover:shadow-md transition-all group"
                        >
                            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center mb-4 group-hover:bg-blue-100 transition-colors">
                                <span class="text-xl">✉️</span>
                            </div>
                            <h4 class="font-semibold text-gray-900 group-hover:text-indigo-600">Email App Send</h4>
                            <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                                Allow external systems to send transactional emails through a pre-linked email sending server.
                            </p>
                        </button>

                        <!-- Custom Client Card -->
                        <button 
                            type="button"
                            @click="selectType('custom')"
                            class="flex flex-col text-left p-5 bg-white border border-gray-200 rounded-xl hover:border-indigo-500 hover:shadow-md transition-all group"
                        >
                            <div class="w-10 h-10 rounded-lg bg-slate-50 flex items-center justify-center mb-4 group-hover:bg-slate-100 transition-colors">
                                <span class="text-xl">⚡</span>
                            </div>
                            <h4 class="font-semibold text-gray-900 group-hover:text-indigo-600">Custom Integration</h4>
                            <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                                Create a general API token for querying project tasks, tracking developer activity, or custom calls.
                            </p>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Basic Configuration -->
                <div v-if="currentStep === 2" class="space-y-5 bg-white p-5 rounded-xl border border-gray-100">
                    <div class="flex justify-between items-center mb-2 border-b border-slate-100 pb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded">
                            {{ integrationType === 'stripe' ? 'Stripe Setup' : integrationType === 'email' ? 'Email App Setup' : 'Custom Client Setup' }}
                        </span>
                        <button type="button" @click="currentStep = 1" class="text-xs text-gray-500 hover:text-indigo-600 underline">Change integration type</button>
                    </div>

                    <!-- Stripe Warning / Instructions -->
                    <div v-if="integrationType === 'stripe'" class="bg-amber-50 border-l-4 border-amber-500 p-4 text-xs text-amber-800 rounded shadow-sm">
                        <p class="font-bold mb-1">Stripe Integration Details:</p>
                        <p class="leading-relaxed">
                            To receive live payment notifications from Stripe:
                        </p>
                        <ul class="list-disc pl-4 mt-1 space-y-1">
                            <li>Set up webhooks pointing to: <code class="bg-amber-100 px-1 py-0.5 rounded font-mono">https://[your-domain]/api/external/stripe/webhook/{app_id}</code></li>
                            <li>Save the Stripe secret signing key under <a href="/admin/stripe-configurations" target="_blank" class="underline font-bold text-amber-950">Stripe Configurations</a>.</li>
                        </ul>
                    </div>

                    <!-- Form Fields -->
                    <div>
                        <InputLabel for="wiz_label" value="Token Name / Label" />
                        <TextInput id="wiz_label" v-model="form.label" type="text" class="mt-1 block w-full" placeholder="e.g. My Website Stripe Webhook" required />
                        <p class="text-[11px] text-gray-400 mt-1">Make it clear which service or client will be using this token.</p>
                        <InputError :message="errors.label" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="wiz_email" value="Identifier Email (Optional)" />
                        <TextInput id="wiz_email" v-model="form.email" type="email" class="mt-1 block w-full" placeholder="system@example.com" />
                        <p class="text-[11px] text-gray-400 mt-1">Useful to track who generated API requests or link log records to an administrator.</p>
                        <InputError :message="errors.email" class="mt-1" />
                    </div>

                    <!-- Stripe App configuration select -->
                    <div v-if="integrationType === 'stripe'">
                        <InputLabel for="wiz_stripe_app_id" value="Select Stripe Configuration App ID" />
                        <select id="wiz_stripe_app_id" v-model="form.stripe_app_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">-- Select Configured Stripe App --</option>
                            <option v-for="config in stripeConfigurations" :key="config.id" :value="config.app_id">
                                {{ config.app_name }} ({{ config.app_id }})
                            </option>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">
                            Select from your configurations in <a href="/admin/stripe-configurations" target="_blank" class="underline text-indigo-600">Stripe configurations</a>.
                        </p>
                        <InputError :message="errors.stripe_app_id" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="wiz_project_id" value="Associated Project (Optional)" />
                        <select id="wiz_project_id" v-model="form.project_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">None (Global Access)</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Restricts token scopes to retrieve tasks or track activities for a specific project.</p>
                        <InputError :message="errors.project_id" class="mt-1" />
                    </div>
                </div>

                <!-- STEP 3: Linked Email App Configuration -->
                <div v-if="currentStep === 3" class="space-y-5 bg-white p-5 rounded-xl border border-gray-100">
                    <div class="flex justify-between items-center mb-2 border-b border-slate-100 pb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded">
                            Email Application Link
                        </span>
                        <span class="text-xs font-medium text-gray-500">
                            {{ integrationType === 'email' ? 'Required' : 'Optional' }}
                        </span>
                    </div>

                    <p class="text-xs text-gray-500 leading-relaxed mb-4">
                        Linking an email application enables external systems to trigger SMTP transactional emails through the sending API using this token.
                    </p>

                    <!-- Select Dropdown -->
                    <div v-if="!showNewEmailAppForm">
                        <InputLabel for="wiz_email_app_id" value="Select Linked Email App" />
                        <div class="flex space-x-2 mt-1">
                            <select id="wiz_email_app_id" v-model="form.email_app_id" class="flex-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">{{ integrationType === 'email' ? '-- Select Required Email App --' : 'None (Optional - cannot send external email)' }}</option>
                                <option v-for="app in localEmailApps" :key="app.id" :value="app.id">
                                    {{ app.name }} ({{ app.is_active ? 'Active' : 'Inactive' }})
                                </option>
                            </select>
                            <SecondaryButton type="button" @click="showNewEmailAppForm = true" class="!px-3 flex items-center justify-center">
                                <span class="mr-1 font-bold">+</span> New
                            </SecondaryButton>
                        </div>
                        <InputError :message="errors.email_app_id" class="mt-1" />
                    </div>

                    <!-- Create Email App Inline Form -->
                    <div v-if="showNewEmailAppForm" class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 space-y-4">
                        <div class="flex justify-between items-center border-b border-indigo-100 pb-2">
                            <span class="text-xs font-bold text-indigo-900">Create Email App Inline</span>
                            <button type="button" @click="showNewEmailAppForm = false" class="text-xs text-gray-400 hover:text-red-500">Cancel</button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <InputLabel for="new_app_name" value="App Name" />
                                <TextInput id="new_app_name" v-model="newEmailApp.name" type="text" class="mt-1 block w-full text-xs" placeholder="e.g. SMTP Sender Portal" required />
                                <InputError :message="emailAppErrors.name" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="new_smtp_host" value="SMTP Host" />
                                <TextInput id="new_smtp_host" v-model="newEmailApp.smtp_host" type="text" class="mt-1 block w-full text-xs" placeholder="smtp.mailgun.org" required />
                                <InputError :message="emailAppErrors.smtp_host" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="new_smtp_port" value="SMTP Port" />
                                <TextInput id="new_smtp_port" v-model="newEmailApp.smtp_port" type="number" class="mt-1 block w-full text-xs" placeholder="587" required />
                                <InputError :message="emailAppErrors.smtp_port" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="new_smtp_username" value="SMTP Username" />
                                <TextInput id="new_smtp_username" v-model="newEmailApp.smtp_username" type="text" class="mt-1 block w-full text-xs" placeholder="postmaster@yourdomain.com" required />
                                <InputError :message="emailAppErrors.smtp_username" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="new_smtp_password" value="SMTP Password" />
                                <TextInput id="new_smtp_password" v-model="newEmailApp.smtp_password" type="password" class="mt-1 block w-full text-xs" placeholder="••••••••••••" required />
                                <InputError :message="emailAppErrors.smtp_password" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="new_smtp_from_address" value="SMTP From Address" />
                                <TextInput id="new_smtp_from_address" v-model="newEmailApp.smtp_from_address" type="email" class="mt-1 block w-full text-xs" placeholder="noreply@domain.com" required />
                                <InputError :message="emailAppErrors.smtp_from_address" class="mt-1" />
                            </div>

                            <div class="col-span-2">
                                <InputLabel for="new_smtp_from_name" value="SMTP From Name" />
                                <TextInput id="new_smtp_from_name" v-model="newEmailApp.smtp_from_name" type="text" class="mt-1 block w-full text-xs" placeholder="App Portal" />
                                <InputError :message="emailAppErrors.smtp_from_name" class="mt-1" />
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <PrimaryButton 
                                type="button" 
                                @click="createEmailAppInline" 
                                :disabled="isCreatingEmailApp"
                                class="!py-1.5 !text-xs"
                            >
                                {{ isCreatingEmailApp ? 'Creating...' : 'Save & Link App' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Security & Review -->
                <div v-if="currentStep === 4" class="space-y-5 bg-white p-5 rounded-xl border border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Security, Limits & Review</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="wiz_expires_at" value="Expiry Date" />
                            <TextInput id="wiz_expires_at" v-model="form.expires_at" type="date" class="mt-1 block w-full" />
                            <p class="text-[10px] text-gray-400 mt-1">Leave empty for a permanent token.</p>
                            <InputError :message="errors.expires_at" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="wiz_max_uses" value="Usage Limit" />
                            <TextInput id="wiz_max_uses" v-model="form.max_uses" type="number" class="mt-1 block w-full" placeholder="e.g. 500" />
                            <p class="text-[10px] text-gray-400 mt-1">Token stops working after this amount of hits.</p>
                            <InputError :message="errors.max_uses" class="mt-1" />
                        </div>
                    </div>

                    <!-- Whitelists -->
                    <div class="border-t border-gray-100 pt-4">
                        <InputLabel value="Whitelist Domains" />
                        <div class="flex mt-1">
                            <TextInput v-model="domainInput" type="text" class="block w-full text-sm" placeholder="e.g. api.stripe.com" @keyup.enter.prevent="addDomain" />
                            <SecondaryButton type="button" class="ml-2 !py-2" @click="addDomain">Add</SecondaryButton>
                        </div>
                        <div class="flex flex-wrap gap-1.5 mt-2.5">
                            <span v-for="(domain, idx) in form.whitelist_domains" :key="idx" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                {{ domain }}
                                <button type="button" class="ml-1.5 text-indigo-400 hover:text-indigo-600 focus:outline-none" @click="removeDomain(idx)">&times;</button>
                            </span>
                            <span v-if="form.whitelist_domains.length === 0" class="text-[11px] text-gray-400 italic">No domains whitelisted</span>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <InputLabel value="Whitelist IP Addresses" />
                        <div class="flex mt-1">
                            <TextInput v-model="ipInput" type="text" class="block w-full text-sm" placeholder="e.g. 54.187.174.169" @keyup.enter.prevent="addIp" />
                            <SecondaryButton type="button" class="ml-2 !py-2" @click="addIp">Add</SecondaryButton>
                        </div>
                        <div class="flex flex-wrap gap-1.5 mt-2.5">
                            <span v-for="(ip, idx) in form.whitelist_ips" :key="idx" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                                {{ ip }}
                                <button type="button" class="ml-1.5 text-emerald-400 hover:text-emerald-600 focus:outline-none" @click="removeIp(idx)">&times;</button>
                            </span>
                            <span v-if="form.whitelist_ips.length === 0" class="text-[11px] text-gray-400 italic">No IPs whitelisted</span>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 bg-slate-50 p-4 rounded-lg">
                        <p class="text-xs font-bold text-gray-900 mb-2">Review Configuration Summary</p>
                        <div class="text-[11px] space-y-1.5 text-gray-600">
                            <div class="flex justify-between"><span class="font-semibold">Integration:</span><span class="capitalize text-gray-900">{{ integrationType }}</span></div>
                            <div class="flex justify-between"><span class="font-semibold">Label:</span><span class="text-gray-900">{{ form.label }}</span></div>
                            <div class="flex justify-between" v-if="integrationType === 'stripe'"><span class="font-semibold">Stripe App ID:</span><span class="text-gray-900">{{ form.stripe_app_id }}</span></div>
                            <div class="flex justify-between"><span class="font-semibold">Linked Email App:</span><span class="text-gray-900 font-semibold text-indigo-600">{{ form.email_app_id ? localEmailApps.find(a => a.id === form.email_app_id)?.name : 'Not linked' }}</span></div>
                            <div class="flex justify-between"><span class="font-semibold">Expiration:</span><span class="text-gray-900">{{ form.expires_at || 'Never' }}</span></div>
                        </div>
                    </div>

                    <div v-if="errors.general" class="bg-red-50 border-l-4 border-red-500 p-3 text-xs text-red-700">
                        {{ errors.general }}
                    </div>
                </div>

                <!-- STEP 5: Success & Developer Code snippets -->
                <div v-if="currentStep === 5" class="space-y-6">
                    <!-- Success Header -->
                    <div v-if="!viewToken" class="text-center bg-indigo-50 p-6 rounded-xl border border-indigo-100 shadow-sm">
                        <span class="text-3xl">🎉</span>
                        <h3 class="text-lg font-bold text-indigo-900 mt-2">Token Created Successfully!</h3>
                        <p class="text-xs text-indigo-600 mt-1">
                            Token generation was registered. Grab details or copy integration examples below.
                        </p>
                    </div>

                    <!-- Token & App ID Copy Block -->
                    <div class="bg-white p-5 rounded-xl border border-gray-200 space-y-4">
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-xs font-bold text-gray-700 uppercase">Integration Parameters</span>
                            <span v-if="viewToken" class="text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded font-semibold">{{ viewToken.label }}</span>
                        </div>

                        <!-- Token Input Copy -->
                        <div>
                            <label class="text-[11px] font-semibold text-gray-500 block mb-1">X-Magic-Token</label>
                            <div class="flex rounded-md shadow-sm">
                                <input 
                                    type="text" 
                                    readonly 
                                    :value="activeTokenValue" 
                                    class="flex-1 min-w-0 block w-full px-3 py-1.5 rounded-none rounded-l-md text-xs font-mono bg-gray-50 border-gray-300 focus:ring-0 focus:border-gray-300"
                                />
                                <button 
                                    type="button" 
                                    @click="copyToClipboard(activeTokenValue, 'Token copied to clipboard!')"
                                    class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-100 text-gray-600 text-xs hover:bg-gray-200 active:bg-gray-300 transition-colors"
                                >
                                    Copy
                                </button>
                            </div>
                        </div>

                        <!-- App ID Input Copy (Email Linked) -->
                        <div v-if="form.email_app_id">
                            <label class="text-[11px] font-semibold text-gray-500 block mb-1">Linked Email App ID (app_id)</label>
                            <div class="flex rounded-md shadow-sm">
                                <input 
                                    type="text" 
                                    readonly 
                                    :value="activeEmailAppId" 
                                    class="flex-1 min-w-0 block w-full px-3 py-1.5 rounded-none rounded-l-md text-xs font-mono bg-gray-50 border-gray-300 focus:ring-0 focus:border-gray-300"
                                />
                                <button 
                                    type="button" 
                                    @click="copyToClipboard(activeEmailAppId, 'App ID copied!')"
                                    class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-100 text-gray-600 text-xs hover:bg-gray-200 active:bg-gray-300 transition-colors"
                                >
                                    Copy
                                </button>
                            </div>
                        </div>

                        <!-- App ID Input Copy (Stripe Mode) -->
                        <div v-if="integrationType === 'stripe'">
                            <div class="flex justify-between items-center mb-1">
                                <label class="text-[11px] font-semibold text-gray-500 block">Stripe App ID (app_id)</label>
                            </div>
                            <div class="flex rounded-md shadow-sm" v-if="!viewToken">
                                <input 
                                    type="text" 
                                    readonly 
                                    :value="activeStripeAppId" 
                                    class="flex-1 min-w-0 block w-full px-3 py-1.5 rounded-none rounded-l-md text-xs font-mono bg-gray-50 border-gray-300 focus:ring-0 focus:border-gray-300"
                                />
                                <button 
                                    type="button" 
                                    @click="copyToClipboard(activeStripeAppId, 'Stripe App ID copied!')"
                                    class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-100 text-gray-600 text-xs hover:bg-gray-200 active:bg-gray-300 transition-colors"
                                >
                                    Copy
                                </button>
                            </div>
                            <div v-else class="space-y-2">
                                <div class="flex space-x-2">
                                    <select v-model="form.stripe_app_id" class="flex-1 text-xs border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                        <option value="">-- Choose target Stripe config ID --</option>
                                        <option v-for="config in stripeConfigurations" :key="config.id" :value="config.app_id">
                                            {{ config.app_name }} ({{ config.app_id }})
                                        </option>
                                    </select>
                                    <button 
                                        type="button" 
                                        @click="copyToClipboard(activeStripeAppId, 'Stripe App ID copied!')"
                                        class="inline-flex items-center px-3 rounded-md border border-gray-300 bg-gray-100 text-gray-600 text-xs hover:bg-gray-200 transition-colors"
                                    >
                                        Copy App ID
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Code Snippets Integration Box -->
                    <div class="bg-white p-5 rounded-xl border border-gray-200">
                        <p class="text-xs font-bold text-gray-700 uppercase mb-2">Instructions for Developers:</p>
                        <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                            Execute these code templates directly to connect your third-party applications with the endpoints.
                        </p>

                        <!-- Snippets tab selectors -->
                        <div class="flex space-x-1 border-b border-gray-200 mb-4">
                            <button 
                                type="button"
                                @click="activeSnippetTab = 'curl'"
                                :class="['px-3 py-1.5 text-xs font-semibold border-b-2', activeSnippetTab === 'curl' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
                            >
                                cURL
                            </button>
                            <button 
                                type="button"
                                @click="activeSnippetTab = 'node'"
                                :class="['px-3 py-1.5 text-xs font-semibold border-b-2', activeSnippetTab === 'node' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
                            >
                                Node.js (Axios)
                            </button>
                            <button 
                                type="button"
                                @click="activeSnippetTab = 'php'"
                                :class="['px-3 py-1.5 text-xs font-semibold border-b-2', activeSnippetTab === 'php' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
                            >
                                PHP (Guzzle)
                            </button>
                        </div>

                        <!-- Code containers -->
                        <div class="relative bg-slate-900 text-slate-100 rounded-lg p-4 font-mono text-[11px] overflow-x-auto max-h-64 shadow-inner">
                            <button 
                                type="button" 
                                @click="copySnippet(activeSnippetTab === 'curl' ? curlSnippet : activeSnippetTab === 'node' ? nodeSnippet : phpSnippet)"
                                class="absolute top-2 right-2 bg-slate-800 hover:bg-slate-700 text-[10px] text-indigo-400 hover:text-indigo-300 px-2 py-1 rounded transition-colors"
                            >
                                Copy Code
                            </button>
                            <pre v-if="activeSnippetTab === 'curl'">{{ curlSnippet }}</pre>
                            <pre v-else-if="activeSnippetTab === 'node'">{{ nodeSnippet }}</pre>
                            <pre v-else-if="activeSnippetTab === 'php'">{{ phpSnippet }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Buttons (Static Footer) -->
            <div class="mt-6 flex justify-end space-x-3 border-t border-gray-100 pt-4 shrink-0">
                <!-- Back / Cancel -->
                <SecondaryButton 
                    v-if="currentStep > 1 && currentStep <= 4" 
                    @click="prevStep" 
                    :disabled="isSubmitting"
                >
                    Back
                </SecondaryButton>
                
                <SecondaryButton 
                    v-if="currentStep === 1 || currentStep === 5" 
                    @click="handleClose"
                >
                    {{ currentStep === 5 ? 'Close' : 'Cancel' }}
                </SecondaryButton>

                <!-- Next / Generate -->
                <PrimaryButton 
                    v-if="currentStep < 4" 
                    @click="nextStep"
                    :disabled="currentStep === 1 || showNewEmailAppForm"
                    :class="{ 'opacity-50 cursor-not-allowed': currentStep === 1 || showNewEmailAppForm }"
                >
                    Next
                </PrimaryButton>

                <PrimaryButton 
                    v-if="currentStep === 4" 
                    @click="generateToken"
                    :disabled="isSubmitting"
                >
                    {{ isSubmitting ? 'Generating...' : 'Generate Token' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
