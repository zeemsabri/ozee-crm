<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <!-- Backdrop -->
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="close"></div>

            <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                
                <div class="sm:flex sm:items-start">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-blue-100 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            Add Instruction for {{ type === 'bill' ? 'Bill' : 'Invoice' }} #{{ id }}
                        </h3>
                        
                        <form @submit.prevent="submit" class="mt-4 space-y-4">
                            <!-- Directives -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Quick Directive</label>
                                <div class="flex flex-wrap gap-2">
                                    <button 
                                        type="button" 
                                        v-for="chip in directiveChips" 
                                        :key="chip"
                                        @click="form.directive = chip"
                                        :class="[
                                            'px-3 py-1.5 rounded-full text-sm font-medium transition-colors border',
                                            form.directive === chip 
                                                ? 'bg-blue-50 border-blue-200 text-blue-700' 
                                                : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'
                                        ]"
                                    >
                                        {{ chip }}
                                    </button>
                                </div>
                            </div>

                            <!-- Assignee -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Assign Task To</label>
                                <select v-model="form.assigned_to" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option :value="null">Unassigned</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Custom Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Custom Notes (Optional)</label>
                                <textarea 
                                    v-model="form.notes"
                                    rows="3" 
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    placeholder="Add any additional context..."
                                ></textarea>
                            </div>

                            <!-- Actions -->
                            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                <button 
                                    type="submit" 
                                    :disabled="form.processing || !form.directive"
                                    class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Saving...' : 'Submit Instruction' }}
                                </button>
                                <button 
                                    type="button" 
                                    @click="close"
                                    class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch, onMounted } from 'vue';

const props = defineProps({
    show: Boolean,
    type: String, // 'bill' or 'invoice'
    id: Number,
    users: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close']);

const directiveChips = [
    'Send Reminder',
    'Hold Payment',
    'Approve for Friday',
    'Review Dispute',
    'Check Xero Sync'
];

const form = useForm({
    instructable_type: '',
    instructable_id: null,
    directive: '',
    notes: '',
    assigned_to: null,
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        form.instructable_type = props.type;
        form.instructable_id = props.id;
        form.directive = '';
        form.notes = '';
        
        // Load assigned user from local storage
        const savedUserId = localStorage.getItem('ceo_dashboard_last_assignee');
        if (savedUserId && props.users.some(u => u.id == savedUserId)) {
            form.assigned_to = parseInt(savedUserId);
        } else {
            form.assigned_to = null;
        }
    }
});

const submit = () => {
    // Save selection to local storage
    if (form.assigned_to) {
        localStorage.setItem('ceo_dashboard_last_assignee', form.assigned_to);
    }
    
    form.post(route('dashboard.ceo-financial.instruction'), {
        preserveScroll: true,
        onSuccess: () => {
            close();
            // Reset form
            form.reset();
        }
    });
};

const close = () => {
    emit('close');
};
</script>
