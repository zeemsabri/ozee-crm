<script setup>
import { ref, onMounted, computed, onBeforeUnmount } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    mode: {
        type: String,
        default: 'modal', // 'modal' or 'inline'
    }
});

const report = ref(null);
const humanDate = ref('');
const userFeedback = ref('');
const isSavingFeedback = ref(false);
const showModal = ref(false);
const loading = ref(false);
const pollInterval = ref(null);
const feedbackSaved = ref(false);
const isEditing = ref(false);
const isDashboardHidden = ref(false);

const CACHE_KEY = 'yesterday_productivity_report';

const getYesterdayDate = () => {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    return d.toISOString().split('T')[0];
};

const checkCache = () => {
    try {
        const cached = localStorage.getItem(CACHE_KEY);
        if (cached) {
            const data = JSON.parse(cached);
            const yesterday = getYesterdayDate();
            if (data.date === yesterday && data.user_report) {
                report.value = data.user_report;
                humanDate.value = data.human_date;
                userFeedback.value = data.user_feedback || '';
                feedbackSaved.value = !!data.user_feedback; // Should be true if there is a report
                return true;
            }
        }
    } catch (e) {
        console.error('Error reading productivity cache:', e);
    }
    return false;
};

const saveToCache = (payload) => {
    const yesterday = getYesterdayDate();
    localStorage.setItem(CACHE_KEY, JSON.stringify({
        date: yesterday,
        user_report: payload.user_report,
        human_date: payload.human_date,
        user_feedback: payload.user_feedback,
        feedback_saved: !!payload.user_feedback,
        fetchedAt: new Date().getTime()
    }));
};

const isReportDismissed = () => {
    const yesterday = getYesterdayDate();
    const dismissedDate = localStorage.getItem(`yesterday_report_dismissed_${yesterday}`);
    return dismissedDate === 'true';
};

const checkDashboardHiddenStatus = () => {
    const yesterday = getYesterdayDate();
    const hiddenStatus = localStorage.getItem(`yesterday_report_hidden_${yesterday}`);
    isDashboardHidden.value = hiddenStatus === 'true';
};

const hideDashboardInsight = () => {
    const yesterday = getYesterdayDate();
    localStorage.setItem(`yesterday_report_hidden_${yesterday}`, 'true');
    isDashboardHidden.value = true;
};

const dismissReport = () => {
    showModal.value = false;
    const yesterday = getYesterdayDate();
    localStorage.setItem(`yesterday_report_dismissed_${yesterday}`, 'true');
};

const fetchReport = async () => {
    if (loading.value) return;
    
    if (checkCache()) {
        if (props.mode === 'modal' && !isReportDismissed()) {
            showModal.value = true;
        }
        if (pollInterval.value) {
            clearInterval(pollInterval.value);
            pollInterval.value = null;
        }
        return;
    }

    loading.value = true;
    try {
        const response = await axios.get('/api/productivity/yesterday-report');
        if (response.data && response.data.user_report) {
            report.value = response.data.user_report;
            humanDate.value = response.data.human_date;
            userFeedback.value = response.data.user_feedback || '';
            feedbackSaved.value = !!response.data.user_feedback;
            
            saveToCache({
                user_report: report.value,
                human_date: humanDate.value,
                user_feedback: userFeedback.value,
                feedback_saved: feedbackSaved.value
            });
            
            if (props.mode === 'modal' && !isReportDismissed()) {
                showModal.value = true;
            }
            
            if (pollInterval.value) {
                clearInterval(pollInterval.value);
                pollInterval.value = null;
            }
        }
    } catch (error) {
        console.error('Error fetching productivity report:', error);
    } finally {
        loading.value = false;
    }
};

const submitFeedback = async () => {
    if (!userFeedback.value.trim() || isSavingFeedback.value) return;

    isSavingFeedback.value = true;
    try {
        await axios.post('/api/productivity/yesterday-feedback', {
            feedback: userFeedback.value
        });
        
        feedbackSaved.value = true;
        isEditing.value = false;
        
        // Update cache
        saveToCache({
            user_report: report.value,
            human_date: humanDate.value,
            user_feedback: userFeedback.value,
            feedback_saved: true
        });

        // Close modal after a short delay if in modal mode
        if (props.mode === 'modal') {
            setTimeout(() => {
                dismissReport();
            }, 1000);
        }
    } catch (error) {
        console.error('Error saving feedback:', error);
        alert('Failed to save feedback. Please try again.');
    } finally {
        isSavingFeedback.value = false;
    }
};

onMounted(() => {
    checkDashboardHiddenStatus();
    fetchReport();
    if (!report.value) {
        pollInterval.value = setInterval(fetchReport, 30 * 60 * 1000);
    }
});

onBeforeUnmount(() => {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
    }
});
</script>

<template>
    <div v-if="mode === 'inline' && report && !isDashboardHidden" class="group bg-indigo-50 border-l-4 border-indigo-500 p-6 mb-8 rounded-xl shadow-lg transition-all duration-300 hover:shadow-xl relative">
        <!-- Close/Hide Button for Inline Section -->
        <button 
            @click="hideDashboardInsight" 
            class="absolute top-4 right-4 text-indigo-300 hover:text-indigo-600 transition-colors opacity-0 group-hover:opacity-100"
            title="Hide for today"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div class="flex items-start">
            <div class="flex-shrink-0 bg-indigo-100 p-3 rounded-lg">
                <svg class="h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.989-2.386l-.548-.547z" />
                </svg>
            </div>
            <div class="ml-5 flex-1 pr-8">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="text-xl font-bold text-indigo-900">Yesterday's Productivity Insight</h3>
                    <span class="text-indigo-500 text-sm font-semibold bg-indigo-100 px-3 py-1 rounded-full">{{ humanDate }}</span>
                </div>
                <p class="text-indigo-800 leading-relaxed text-lg italic mb-4">
                    "{{ report }}"
                </p>

                <!-- Dynamic Inline Feedback Section -->
                <div class="mt-4 pt-4 border-t border-indigo-100">
                    <div v-if="!feedbackSaved || isEditing">
                        <InputLabel for="dashboard_feedback" value="How was your day? (Optional Feedback)" class="text-indigo-700 font-semibold mb-2" />
                        <div class="flex space-x-2">
                            <TextareaInput
                                id="dashboard_feedback"
                                v-model="userFeedback"
                                class="flex-1 text-sm bg-white border-indigo-200 focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Your thoughts on yesterday's productivity..."
                                rows="1"
                            />
                            <div class="flex space-x-2">
                                <SecondaryButton 
                                    v-if="isEditing" 
                                    @click="isEditing = false"
                                    class="whitespace-nowrap"
                                >
                                    Cancel
                                </SecondaryButton>
                                <PrimaryButton 
                                    @click="submitFeedback" 
                                    :disabled="isSavingFeedback || !userFeedback.trim()"
                                    class="whitespace-nowrap bg-indigo-600 hover:bg-indigo-700"
                                >
                                    {{ isSavingFeedback ? 'Saving...' : (isEditing ? 'Update' : 'Send') }}
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-between py-2 group/feedback">
                        <div class="flex items-center text-green-700 font-medium">
                            <svg class="h-5 w-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="italic text-indigo-900 mr-2">"{{ userFeedback }}"</span>
                            <span class="text-sm text-green-600 font-normal">(Feedback saved)</span>
                        </div>
                        <button 
                            @click="isEditing = true" 
                            class="text-indigo-600 hover:text-indigo-800 text-sm font-bold flex items-center transition-colors px-3 py-1 bg-indigo-100 rounded-lg"
                        >
                            <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Modal :show="showModal" @close="dismissReport" max-width="2xl">
        <div class="p-8 bg-gradient-to-br from-indigo-50 to-white relative overflow-hidden">
            <!-- Decorative background elements -->
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-indigo-200 rounded-full opacity-20 blur-3xl"></div>
            <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-indigo-200 rounded-full opacity-20 blur-3xl"></div>

            <div class="relative">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="bg-indigo-100 p-3 rounded-2xl shadow-sm">
                            <svg class="h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.989-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight">Productivity Insight</h2>
                            <p class="text-indigo-600 text-sm font-semibold uppercase tracking-wider">Analysis for {{ humanDate }}</p>
                        </div>
                    </div>
                    <button @click="dismissReport" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="bg-white rounded-2xl p-8 shadow-xl border border-indigo-50 mb-8 transform transition-all hover:scale-[1.01]">
                    <div class="flex justify-start mb-4">
                        <svg class="h-8 w-8 text-indigo-200" fill="currentColor" viewBox="0 0 32 32">
                            <path d="M10 8c-3.3 0-6 2.7-6 6v10h10V14c0-3.3-2.7-6-6-6zm0 2c2.2 0 4 1.8 4 4v8h-6V14c0-2.2 1.8-4 4-4zm12-2c-3.3 0-6 2.7-6 6v10h10V14c0-3.3-2.7-6-6-6zm0 2c2.2 0 4 1.8 4 4v8h-6V14c0-2.2 1.8-4 4-4z" />
                        </svg>
                    </div>
                    <p class="text-gray-800 text-xl leading-relaxed italic font-medium px-4">
                        {{ report }}
                    </p>
                    <div class="flex justify-end mt-2">
                        <svg class="h-8 w-8 text-indigo-200 transform rotate-180" fill="currentColor" viewBox="0 0 32 32">
                            <path d="M10 8c-3.3 0-6 2.7-6 6v10h10V14c0-3.3-2.7-6-6-6zm0 2c2.2 0 4 1.8 4 4v8h-6V14c0-2.2 1.8-4 4-4zm12-2c-3.3 0-6 2.7-6 6v10h10V14c0-3.3-2.7-6-6-6zm0 2c2.2 0 4 1.8 4 4v8h-6V14c0-2.2 1.8-4 4-4z" />
                        </svg>
                    </div>
                </div>

                <!-- Feedback Section in Modal -->
                <div class="bg-indigo-50 rounded-2xl p-6 border border-indigo-100 shadow-inner mb-8 transition-all" :class="{'opacity-75': feedbackSaved && !isEditing}">
                    <div class="flex items-center space-x-2 mb-4">
                        <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        <h3 class="text-indigo-900 font-bold">How was your day?</h3>
                    </div>
                    
                    <div v-if="!feedbackSaved || isEditing">
                        <TextareaInput
                            v-model="userFeedback"
                            class="w-full bg-white border-indigo-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl px-4 py-3 text-gray-700 placeholder-indigo-300"
                            placeholder="Share any thoughts, challenges or wins from yesterday..."
                            rows="3"
                        />
                        <div class="mt-4 flex justify-end space-x-2">
                             <SecondaryButton 
                                v-if="isEditing" 
                                @click="isEditing = false"
                            >
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton 
                                @click="submitFeedback" 
                                :disabled="isSavingFeedback || !userFeedback.trim()"
                                class="bg-indigo-600 hover:bg-indigo-700 px-8 py-2.5 rounded-xl shadow-md hover:shadow-indigo-200 transition-all font-bold"
                            >
                                {{ isSavingFeedback ? 'Saving...' : (isEditing ? 'Update Feedback' : 'Send Feedback') }}
                            </PrimaryButton>
                        </div>
                    </div>
                    <div v-else class="py-4 text-center">
                        <div class="inline-flex items-center justify-center bg-green-100 p-2 rounded-full mb-2">
                             <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <p class="text-green-800 font-bold text-lg italic mb-2">Feedback saved! We value your input.</p>
                        <button 
                            @click="isEditing = true"
                            class="text-indigo-600 hover:text-indigo-800 text-sm font-bold underline"
                        >
                            Edit Feedback
                        </button>
                    </div>
                </div>

                <div class="flex justify-end items-center">
                    <button @click="dismissReport" class="text-indigo-600 font-bold hover:text-indigo-800 transition-colors py-2 px-4">
                        Dismiss
                    </button>
                    <button @click="dismissReport" class="ml-4 px-10 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xl rounded-2xl shadow-xl hover:shadow-indigo-300 transition-all active:scale-95 group">
                        Awesome! 
                        <span class="inline-block transform transition-transform group-hover:translate-x-1 ml-2">→</span>
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
