<template>
    <div class="fixed bottom-4 right-4 z-[9999] flex flex-col items-end space-y-2 pointer-events-none" v-if="!sidebarIsOpen">
        <!-- Container for active individual toasts -->
        <div class="notification-list w-full max-w-sm space-y-2" v-if="visibleNotifications.length > 0">
            <transition-group name="toast-fade">
                <div
                    v-for="notification in visibleNotifications"
                    :key="notification.id"
                    class="bg-white rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] p-4 flex flex-col border border-slate-200 transition-all pointer-events-auto"
                    @mouseenter="pauseTimer(notification.id)"
                    @mouseleave="resumeTimer(notification.id)"
                >
                    <!-- Close button -->
                    <button @click="dismissNotification(notification.id)" class="absolute top-2 right-2 p-1 text-slate-400 hover:text-slate-600 rounded-md transition-colors">
                        <X class="w-3.5 h-3.5" />
                    </button>

                    <div class="flex items-start space-x-3 cursor-pointer" @click="handleBodyClick(notification)">
                        <div class="flex-shrink-0 mt-0.5">
                            <component :is="getIconForType(notification.type)" class="w-5 h-5 text-indigo-500" />
                        </div>
                        <div class="flex-1 min-w-0 pr-4">
                            <!-- Title & Time -->
                            <div class="flex items-center justify-between mb-0.5">
                                <p class="text-[13px] font-bold text-slate-900 truncate pr-2">{{ notification.title }}</p>
                                <span class="text-[10px] text-slate-400 whitespace-nowrap">Just now</span>
                            </div>
                            
                            <!-- Project Context -->
                            <p v-if="notification.project_name" class="text-[10px] font-bold text-indigo-600 uppercase tracking-wide mb-1 flex items-center">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-1.5"></span>
                                {{ notification.project_name }}
                            </p>

                            <!-- Message Body -->
                            <p class="text-xs text-slate-600 leading-snug line-clamp-3">
                                {{ notification.message }}
                            </p>

                            <!-- Quick Actions Row -->
                            <div v-if="notification.count === 1 || !notification.count" class="mt-2.5 flex flex-wrap gap-1.5" @click.stop>
                                <button v-if="isTypeMatch(notification, 'mention')" 
                                    @click="toggleReply(notification.id)"
                                    class="text-[10px] font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 px-2 py-1 rounded flex items-center transition-colors">
                                    <MessageSquare class="w-3 h-3 mr-1" /> Reply
                                </button>

                                <template v-if="isTypeMatch(notification, 'task')">
                                    <button @click="toggleNote(notification.id)"
                                        class="text-[10px] font-semibold text-slate-600 hover:text-slate-800 bg-slate-100 px-2 py-1 rounded flex items-center transition-colors">
                                        <Send class="w-3 h-3 mr-1" /> Add Note
                                    </button>
                                    <button @click="quickAddToDaily(notification)"
                                        class="text-[10px] font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 px-2 py-1 rounded flex items-center transition-colors">
                                        <CheckCircle2 class="w-3 h-3 mr-1" /> Daily Task
                                    </button>
                                </template>
                            </div>

                            <!-- Inline Input: Reply -->
                            <div v-if="replyingTo === notification.id" class="mt-2 flex items-end space-x-2" @click.stop>
                                <MentionInput 
                                    v-model="replyText" 
                                    :project-id="notification.project_id"
                                    placeholder="Type your reply..." 
                                    type="input"
                                    @submit="submitQuickReply(notification)"
                                    class="flex-1"
                                />
                                <button @click="submitQuickReply(notification)" :disabled="isActionLoading || !replyText.trim()"
                                    class="mb-1 p-1.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                                    <Send class="w-3 h-3" />
                                </button>
                                <button @click="replyingTo = null" class="mb-1 p-1 text-slate-400 hover:text-slate-600">
                                    <X class="w-3 h-3" />
                                </button>
                            </div>

                            <!-- Inline Input: Note -->
                            <div v-if="addingNoteTo === notification.id" class="mt-2 flex items-end space-x-2" @click.stop>
                                <MentionInput 
                                    v-model="noteText" 
                                    :project-id="notification.project_id"
                                    placeholder="Add a quick note..." 
                                    type="input"
                                    @submit="submitQuickNote(notification)"
                                    class="flex-1"
                                />
                                <button @click="submitQuickNote(notification)" :disabled="isActionLoading || !noteText.trim()"
                                    class="mb-1 p-1.5 bg-slate-800 text-white rounded-md hover:bg-slate-900 disabled:opacity-50">
                                    <Check class="w-3 h-3" />
                                </button>
                                <button @click="addingNoteTo = null" class="mb-1 p-1 text-slate-400 hover:text-slate-600">
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition-group>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import { 
    Bell, 
    MessageSquare, 
    Calendar,
    X, 
    Send, 
    CheckCircle2, 
    Check,
    BellRing
} from 'lucide-vue-next';
import { openTaskDetailSidebar } from '@/Utils/sidebar';
import {
    notificationSidebarState,
    markNotificationAndRefetch,
    markToastAsSeen,
    openNotificationsSidebar
} from '@/Utils/notification-sidebar';
import MentionInput from '@/Components/ProjectTasks/MentionInput.vue';

const props = defineProps({
    sidebarIsOpen: {
        type: Boolean,
        default: false
    }
});

// --- STATE ---
const isWindowFocused = ref(true);
const notificationTimers = ref(new Map());
const pausedTimers = ref(new Set()); // IDs that are hovered/interacted
const AUTO_DISMISS_SECONDS = 5; // Updated from 120s to 5s

// Action states
const replyingTo = ref(null);
const replyText = ref('');
const addingNoteTo = ref(null);
const noteText = ref('');
const isActionLoading = ref(false);

// --- COMPUTED ---
const maxVisible = 3;

// All notifications flagged as new pushes
const allPendingPushes = computed(() => {
    return notificationSidebarState.value.notifications.filter(n => n.isNewPush);
});

// If focused, show up to maxVisible. If blurred, we hide them to show the bubble instead.
const visibleNotifications = computed(() => {
    return allPendingPushes.value.slice(0, maxVisible);
});

// --- METHODS ---

const getTaskIdFromUrl = (url) => {
    if (!url) return null;
    const match = url.match(/\/task\/(\d+)/);
    return match ? parseInt(match[1]) : null;
};

const getProjectIdFromUrl = (url) => {
    if (!url) return null;
    const match = url.match(/\/project\/(\d+)\//);
    return match ? parseInt(match[1]) : null;
};

const handleBodyClick = async (notification) => {
    // If we're interacting with inline inputs, don't trigger the body click
    if (replyingTo.value === notification.id || addingNoteTo.value === notification.id) return;
    
    markToastAsSeen(notification.id);
    await markNotificationAndRefetch(notification.view_id);

    if (notification.type === 'chat_message' && notification.project_id) {
        openNotificationsSidebar();
        window.dispatchEvent(new CustomEvent('open-project-chat', { detail: { projectId: notification.project_id } }));
        return;
    }

    const taskId = notification.task_id || getTaskIdFromUrl(notification.url);
    const projectId = notification.project_id || getProjectIdFromUrl(notification.url);

    if (taskId && projectId) {
        openTaskDetailSidebar(taskId, projectId);
    } else if (notification.url) {
        window.location.href = notification.url;
    }
};

const dismissNotification = (id) => {
    markToastAsSeen(id);
    notificationTimers.value.delete(id);
    pausedTimers.value.delete(id);
};

const openSidebarAndClear = () => {
    // Clear all new push flags so they just appear as normal unread in the sidebar
    allPendingPushes.value.forEach(n => markToastAsSeen(n.id));
    openNotificationsSidebar();
};

// --- QUICK ACTIONS ---

const isTypeMatch = (notification, type) => {
    const rawType = notification.type || '';
    if (type === 'task') return rawType === 'task_assigned' || rawType.includes('TaskAssigned');
    if (type === 'mention') return rawType === 'user_mentioned' || rawType.includes('UserMentioned') || rawType === 'chat_message';
    return false;
};

const getIconForType = (type) => {
    const t = (type || '').toLowerCase();
    if (t.includes('task')) return Calendar;
    if (t.includes('mention')) return MessageSquare;
    return Bell;
};

const toggleReply = (id) => {
    replyingTo.value = replyingTo.value === id ? null : id;
    if (replyingTo.value) {
        addingNoteTo.value = null; // close other
        pauseTimer(id); // Keep toast alive while typing
    } else {
        resumeTimer(id);
    }
};

const toggleNote = (id) => {
    addingNoteTo.value = addingNoteTo.value === id ? null : id;
    if (addingNoteTo.value) {
        replyingTo.value = null;
        pauseTimer(id);
    } else {
        resumeTimer(id);
    }
};

// Shared with CommunicationSidebar
const submitQuickReply = async (notification) => {
    if (!replyText.value.trim() || !notification.project_id) return;
    
    isActionLoading.value = true;
    try {
        await axios.post(`/api/projects/${notification.project_id}/chat`, {
            message: replyText.value,
            parent_id: notification.source_id ?? null
        });
        replyText.value = '';
        replyingTo.value = null;
        dismissNotification(notification.id);
        await markNotificationAndRefetch(notification.view_id);
    } catch (error) {
        console.error('Error sending quick reply:', error);
    } finally {
        isActionLoading.value = false;
    }
};

const submitQuickNote = async (notification) => {
    if (!noteText.value.trim() || !notification.task_id) return;
    
    isActionLoading.value = true;
    try {
        await axios.post(`/api/tasks/${notification.task_id}/notes`, {
            note: noteText.value
        });
        noteText.value = '';
        addingNoteTo.value = null;
        dismissNotification(notification.id);
        await markNotificationAndRefetch(notification.view_id);
    } catch (error) {
        console.error('Error adding quick note:', error);
    } finally {
        isActionLoading.value = false;
    }
};

const quickAddToDaily = async (notification) => {
    if (!notification.task_id) return;
    
    isActionLoading.value = true;
    try {
        await axios.post('/api/daily-tasks', {
            task_ids: [notification.task_id]
        });
        dismissNotification(notification.id);
        await markNotificationAndRefetch(notification.view_id);
    } catch (error) {
        console.error('Error adding to daily tasks:', error);
    } finally {
        isActionLoading.value = false;
    }
};

// --- AUTO DISMISS LOGIC ---

const updateFocus = () => {
    isWindowFocused.value = document.hasFocus();
};

const pauseTimer = (id) => {
    pausedTimers.value.add(id);
};

const resumeTimer = (id) => {
    // Only resume if they aren't actively typing
    if (replyingTo.value !== id && addingNoteTo.value !== id) {
        pausedTimers.value.delete(id);
    }
};

let tickInterval = null;

const startTicker = () => {
    tickInterval = setInterval(() => {
        // If window is blurred, don't decrement so they wait until user returns
        if (!isWindowFocused.value) return;

        for (const [id, remaining] of notificationTimers.value.entries()) {
            // Check if timer is paused by hover or interaction
            if (pausedTimers.value.has(id)) continue;

            if (remaining <= 1) {
                dismissNotification(id);
            } else {
                notificationTimers.value.set(id, remaining - 1);
            }
        }
    }, 1000);
};

watch(allPendingPushes, (newPushes) => {
    // Find newly added pushes and start their timers
    newPushes.forEach(n => {
        if (!notificationTimers.value.has(n.id)) {
            notificationTimers.value.set(n.id, AUTO_DISMISS_SECONDS);
        }
    });

    // Cleanup closed notifications
    const activeIds = new Set(newPushes.map(n => n.id));
    for (const [id] of notificationTimers.value) {
        if (!activeIds.has(id)) {
            notificationTimers.value.delete(id);
            pausedTimers.value.delete(id);
        }
    }
}, { deep: true, immediate: true });

// --- LIFECYCLE ---
onMounted(() => {
    window.addEventListener('focus', updateFocus);
    window.addEventListener('blur', updateFocus);
    updateFocus();
    startTicker();
});

onUnmounted(() => {
    window.removeEventListener('focus', updateFocus);
    window.removeEventListener('blur', updateFocus);
    if (tickInterval) clearInterval(tickInterval);
});
</script>

<style scoped>
.toast-fade-enter-active,
.toast-fade-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.toast-fade-enter-from {
    opacity: 0;
    transform: translateX(100%) scale(0.95);
}

.toast-fade-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}

/* Base styles for the transition group to take up layout space properly */
.notification-list > div {
    backface-visibility: hidden;
    position: relative;
}
</style>
