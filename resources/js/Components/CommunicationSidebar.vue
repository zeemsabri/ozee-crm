<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import {
    Bell,
    MessageSquare,
    X,
    Check,
    Clock,
    User,
    Calendar,
    CheckCircle2,
    Filter,
    Send,
    MoreVertical,
    CheckCheck,
    ChevronLeft,
    ChevronDown,
    Plus,
    Maximize,
    Minimize,
    CornerDownRight,
    Mail,
    Hash,
    ExternalLink,
    Search,
    Trash2
} from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { openTaskDetailSidebar } from '@/Utils/sidebar';
import { 
    notificationSidebarState, 
    openChatSidebar,
    openNotificationsSidebar,
    closeNotificationsSidebar,
    markNotificationAndRefetch,
    markToastAsSeen
} from '@/Utils/notification-sidebar';
import { pushSuccess, warning } from '@/Utils/notification';
import { formatDate } from '@/Utils/notification';
import { formatMentions } from '@/Utils/mentions';
import MentionInput from '@/Components/ProjectTasks/MentionInput.vue';
import { v4 as uuidv4 } from 'uuid';
import {
    unreadByProjectState,
    totalChatUnreadCount,
    fetchChatUnreadCounts,
    clearProjectChatUnread,
    incrementProjectChatUnread,
} from '@/Utils/chat-state';
import { maybeShowDesktopNotification } from '@/Utils/browser-notifications';

const props = defineProps({});

const isFullScreen = ref(false);
const activeTab = ref('notifications'); // 'notifications' or 'chat'
const filter = ref('unread');
const notificationType = ref('all');
const searchQuery = ref('');
const isMobileView = ref(false);
const mobilePane = ref('projects');

watch(isFullScreen, (val) => {
    const nextMode = val ? 'chat' : 'notifications';
    activeTab.value = nextMode;

    if (notificationSidebarState.value.mode !== nextMode) {
        notificationSidebarState.value.mode = nextMode;
    }
});

watch(
    () => notificationSidebarState.value.mode,
    (mode) => {
        if (mode === 'chat') {
            isFullScreen.value = true;
            activeTab.value = 'chat';
            return;
        }

        isFullScreen.value = false;
        activeTab.value = 'notifications';
    },
    { immediate: true }
);

const showContextDropdown = ref(false);
const expandedGroups = ref({});
const replyingTo = ref(null);
const replyText = ref('');
const replyToMessage = ref(null);
const addingNoteTo = ref(null);
const noteText = ref('');
const isActionLoading = ref(false);
const newMessage = ref('');
const chatMessages = ref([]);
const loadingChat = ref(false);

const projects = ref([]);
const activeProject = ref(null);

const topics = ref([]);
const activeTopic = ref(null);
const isCreatingTopic = ref(false);
const newTopicName = ref('');
const loadingTopics = ref(false);

const chatContainer = ref(null);
const mentionInputRef = ref(null);
const loadingMore = ref(false);
const hasMore = ref(true);
const pendingMessages = ref({});
const unreadByProject = unreadByProjectState;
const projectSearch = ref('');
const subscribedTopicIds = ref(new Set()); 

const user = computed(() => usePage().props.auth.user);
const notifications = computed(() => notificationSidebarState.value.notifications);
const unreadCount = computed(() => notifications.value.filter(n => !n.isRead).length);
const totalChatUnread = totalChatUnreadCount;
const notificationSummary = computed(() => {
    const unread = notifications.value.filter(n => !n.isRead);
    const mentions = unread.filter(n => isTypeMatch(n, 'mention')).length;
    const tasks = unread.filter(n => isTypeMatch(n, 'task')).length;
    const other = Math.max(unread.length - mentions - tasks, 0);

    return {
        total: unread.length,
        mentions,
        tasks,
        other,
    };
});
const activeTopicClientMessaging = computed(() => activeTopic.value?.client_messaging ?? null);
const isClientMessagingBlocked = computed(() => activeTopicClientMessaging.value && !activeTopicClientMessaging.value.enabled);
const clientMessagingNotice = computed(() => activeTopicClientMessaging.value?.message ?? '');
const activeProjectUnread = computed(() => {
    if (!activeProject.value) {
        return 0;
    }

    return unreadByProject.value[activeProject.value.id] ?? 0;
});
const activeTopicMessageCount = computed(() => chatMessages.value.length);
const activeTopicStatus = computed(() => {
    if (!activeTopic.value) {
        return 'Choose a project and topic to start chatting.';
    }

    return activeProject.value?.name ? `${activeProject.value.name} workspace` : 'Linked Telegram thread';
});
const mobileChatSummary = computed(() => {
    if (!activeTopic.value) {
        return '';
    }

    return `${topics.value.length} topics · ${activeTopicMessageCount.value} messages · ${activeProjectUnread.value} unread`;
});
const sidebarTitle = computed(() => {
    if (isMobileView.value) {
        return activeTab.value === 'chat' ? 'Team Chat' : 'Updates';
    }

    return isFullScreen.value ? 'Communication Center' : 'Team Chat';
});
const showSidebarTabs = computed(() => isMobileView.value || !isFullScreen.value);
const shouldShowChatLanding = computed(() => activeTab.value === 'chat' && !activeTopic.value);
const chatLandingProjects = computed(() => {
    return [...availableContexts.value]
        .sort((left, right) => {
            const unreadDelta = (right.unreadCount || 0) - (left.unreadCount || 0);

            if (unreadDelta !== 0) {
                return unreadDelta;
            }

            return left.name.localeCompare(right.name);
        })
        .slice(0, 6);
});
const activeProjectTopicsPreview = computed(() => topics.value.slice(0, 8));

const availableContexts = computed(() => {
    const search = projectSearch.value.toLowerCase();
    return projects.value
        .filter(p => !search || p.name.toLowerCase().includes(search))
        .map(p => ({
            ...p,
            unreadCount: unreadByProject.value[p.id] ?? 0,
            color: 'bg-indigo-500' // Default
        }))
        .sort((a, b) => a.name.localeCompare(b.name));
});

const filteredNotifications = computed(() => {
    let result = notifications.value;
    
    if (filter.value === 'unread') {
        result = result.filter(n => !n.isRead);
    }
    
    if (notificationType.value !== 'all') {
        result = result.filter(n => isTypeMatch(n, notificationType.value));
    }
    
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(n => {
            const titleMatch = n.title && n.title.toLowerCase().includes(query);
            const messageMatch = n.message && n.message.toLowerCase().includes(query);
            const taskMatch = n.task_number && String(n.task_number).includes(query);
            return titleMatch || messageMatch || taskMatch;
        });
    }

    if (!searchQuery.value.trim()) {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        result = result.filter(n => {
            const nDate = new Date(n.created_at);
            return nDate >= thirtyDaysAgo;
        });
    }
    
    return result;
});

const groupedNotifications = computed(() => {
    const today = [];
    const thisWeek = [];
    const older = [];
    const startOfToday = new Date();
    startOfToday.setHours(0, 0, 0, 0);

    const startOfWeek = new Date();
    startOfWeek.setHours(0, 0, 0, 0);
    startOfWeek.setDate(startOfWeek.getDate() - 7);
    
    filteredNotifications.value.forEach(n => {
        const nDate = new Date(n.created_at);
        if (nDate >= startOfToday) {
            today.push(n);
        } else if (nDate >= startOfWeek) {
            thisWeek.push(n);
        } else {
            older.push(n);
        }
    });
    
    return { today, thisWeek, older };
});

const fetchUnreadCounts = fetchChatUnreadCounts;

const syncMobilePaneToSelection = () => {
    if (activeTopic.value) {
        mobilePane.value = 'messages';
        return;
    }

    if (activeProject.value) {
        mobilePane.value = 'topics';
        return;
    }

    mobilePane.value = 'projects';
};

const updateViewportMode = () => {
    isMobileView.value = window.innerWidth < 640;

    if (isMobileView.value) {
        syncMobilePaneToSelection();
    } else {
        mobilePane.value = 'messages';
    }
};

const fetchProjects = async () => {
    try {
        const response = await axios.get('/api/projects-simplified');
        projects.value = response.data;
        if (projects.value.length > 0) {
            const currentProjectId = usePage().props.id || usePage().props.project?.id;
            const p = projects.value.find(p => p.id == currentProjectId);
            if (p) {
                switchProject(p, { autoSelectTopic: false });
            } else {
                switchProject(projects.value[0], { autoSelectTopic: false });
            }
        }
        fetchUnreadCounts();
        subscribeToAllProjects();
    } catch (error) {
        console.error('Error fetching projects:', error);
    }
};

const fetchTopics = async ({ autoSelectTopic = false } = {}) => {
    if (!activeProject.value) return;
    loadingTopics.value = true;
    try {
        const response = await axios.get(`/api/projects/${activeProject.value.id}/topics`);
        topics.value = response.data;
        
        if (autoSelectTopic && topics.value.length > 0) {
            switchTopic(topics.value[0]);
        } else {
            activeTopic.value = null;
            chatMessages.value = [];
        }
    } catch (error) {
        console.error('Error fetching topics:', error);
    } finally {
        loadingTopics.value = false;
    }
};

const createNewTopic = async () => {
    if (!newTopicName.value.trim() || !activeProject.value) return;
    
    isActionLoading.value = true;
    try {
        const response = await axios.post(`/api/projects/${activeProject.value.id}/topics`, {
            name: newTopicName.value,
        });
        topics.value.unshift(response.data);
        newTopicName.value = '';
        isCreatingTopic.value = false;
        switchTopic(response.data);
    } catch (err) {
        console.error("Failed to create topic", err);
        const errorMessage = err.response?.data?.error || "Failed to create topic";
        const instructions = err.response?.data?.instructions ? `\n\n${err.response.data.instructions}` : '';
        alert(errorMessage + instructions);
    } finally {
        isActionLoading.value = false;
    }
};

const handleNotificationClick = async (notification) => {
    await markNotificationAndRefetch(notification.view_id);
    
    if (notification.task_id && notification.project_id) {
        openTaskDetailSidebar(notification.task_id, notification.project_id);
        closeNotificationsSidebar();
    } else if (isTypeMatch(notification, 'mention') && notification.project_id) {
        openChatSidebar();
        const proj = projects.value.find(p => p.id == notification.project_id);
        if (proj) {
            await switchProject(proj, { autoSelectTopic: true });
            if (notification.source_id && notification.source_type === 'ChatMessage') {
                nextTick(() => {
                    scrollToMessage(notification.source_id);
                });
            }
        }
    }
};

const scrollToMessage = (messageId) => {
    const el = document.getElementById(`msg-${messageId}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('bg-blue-50', 'ring-2', 'ring-blue-200');
        setTimeout(() => {
            el.classList.remove('bg-blue-50', 'ring-2', 'ring-blue-200');
        }, 3000);
    }
};

const handleChatClick = (event) => {
    const taskMention = event.target.closest('.task-mention');
    if (taskMention && activeProject.value) {
        const taskId = taskMention.getAttribute('data-task-id');
        openTaskDetailSidebar(taskId, activeProject.value.id);
    }
};

const fetchChatMessages = async (isLoadMore = false) => {
    if (!activeProject.value || !activeTopic.value || (isLoadMore && (!hasMore.value || loadingMore.value))) return;
    
    if (isLoadMore) {
        loadingMore.value = true;
    } else {
        loadingChat.value = true;
        hasMore.value = true; 
    }

    try {
        const oldestTimestamp = isLoadMore && chatMessages.value.length > 0 
            ? chatMessages.value[0].created_at 
            : null;

        const response = await axios.get(`/api/projects/${activeProject.value.id}/chat`, {
            params: { before: oldestTimestamp, topic_id: activeTopic.value.id }
        });

        const newMessages = response.data;
        
        if (isLoadMore) {
            if (newMessages.length > 0) {
                const currentHeight = chatContainer.value?.scrollHeight || 0;
                chatMessages.value = [...newMessages, ...chatMessages.value];
                
                nextTick(() => {
                    if (chatContainer.value) {
                        chatContainer.value.scrollTop = chatContainer.value.scrollHeight - currentHeight;
                    }
                });
            }
        } else {
            chatMessages.value = newMessages;
            scrollToBottom();
        }

        if (newMessages.length < 20) {
            hasMore.value = false;
        }
    } catch (error) {
        console.error('Error fetching chat:', error);
    } finally {
        loadingChat.value = false;
        loadingMore.value = false;
    }
};

const handleScroll = (e) => {
    const { scrollTop } = e.target;
    if (scrollTop < 50 && hasMore.value && !loadingMore.value && !loadingChat.value) {
        fetchChatMessages(true);
    }
};

const handleSendMessage = async () => {
    if (!newMessage.value.trim() || !activeProject.value || !activeTopic.value) return;

    if (isClientMessagingBlocked.value) {
        warning(clientMessagingNotice.value || 'Client messaging is unavailable for this project.');
        return;
    }
    
    const messageContent = newMessage.value;
    newMessage.value = '';

    // Generate a temporary ID for optimistic UI
    const tempId = uuidv4();
    const tempMsg = {
        id: tempId,
        type: 'text',
        user: user.value.name,
        initials: user.value.name.substring(0, 2).toUpperCase(),
        color: 'bg-indigo-600',
        message: messageContent,
        parent: replyToMessage.value,
        time: 'Just now',
        is_me: true,
        created_at: new Date().toISOString(),
        pending: true,
    };
    chatMessages.value.push(tempMsg);
    pendingMessages.value[tempId] = true;
    replyToMessage.value = null;
    mentionInputRef.value?.clear();
    scrollToBottom();

    try {
        await axios.post(`/api/projects/${activeProject.value.id}/chat`, {
            message: messageContent,
            parent_id: tempMsg.parent?.id,
            telegram_topic_id: activeTopic.value.id
        });
        // No need to update here; real message will arrive via Echo event
    } catch (error) {
        // Remove pending message on error
        chatMessages.value = chatMessages.value.filter(m => m.id !== tempId);
        delete pendingMessages.value[tempId];
        warning(error.response?.data?.message || 'Failed to send message');
        console.error('Error sending message:', error);
    }
};

const deleteMessage = async (msg) => {
    if (!confirm('Are you sure you want to delete this message? This will also remove it from Telegram.')) return;
    
    try {
        await axios.delete(`/api/projects/${activeProject.value.id}/chat/${msg.id}`);
        chatMessages.value = chatMessages.value.filter(m => m.id !== msg.id);
        pushSuccess({ 
            view_id: `delete_${msg.id}`,
            title: 'Deleted', 
            message: 'Message removed successfully' 
        });
    } catch (error) {
        console.error('Error deleting message:', error);
        alert('Failed to delete message');
    }
};

const formatMessage = (message) => {
    return formatMentions(message);
};

const scrollToBottom = () => {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
};

const switchProject = async (project, { autoSelectTopic = false } = {}) => {
    activeProject.value = project;
    showContextDropdown.value = false;
    projectSearch.value = '';
    activeTopic.value = null;
    chatMessages.value = [];
    
    clearProjectChatUnread(project.id);
    
    await fetchTopics({ autoSelectTopic });

    if (isMobileView.value) {
        syncMobilePaneToSelection();
    }
};

const switchTopic = (topic) => {
    activeTopic.value = topic;
    isCreatingTopic.value = false;
    if (isMobileView.value) {
        mobilePane.value = 'messages';
    }
    fetchChatMessages();
};

const openChatTab = () => {
    activeTab.value = 'chat';
    isFullScreen.value = true;
    notificationSidebarState.value.mode = 'chat';
    if (isMobileView.value) {
        syncMobilePaneToSelection();
    }
};

const openNotificationsTab = () => {
    activeTab.value = 'notifications';
    isFullScreen.value = false;
    notificationSidebarState.value.mode = 'notifications';
    mobilePane.value = 'messages';
};

const goToMobileProjects = () => {
    isCreatingTopic.value = false;
    mobilePane.value = 'projects';
};

const goToMobileTopics = () => {
    if (!activeProject.value) {
        return;
    }

    mobilePane.value = 'topics';
};

const toggleGroup = (id) => {
    expandedGroups.value[id] = !expandedGroups.value[id];
};

const markAllAsRead = async () => {
    // await markAllNotificationsAsRead();
};

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
        await markNotificationAndRefetch(notification.view_id);
    } catch (error) {} finally {
        isActionLoading.value = false;
    }
};

const quickAddToDaily = async (notification) => {
    if (!notification.task_id) return;
    isActionLoading.value = true;
    try {
        await axios.post('/api/daily-tasks', { task_ids: [notification.task_id] });
        await markNotificationAndRefetch(notification.view_id);
    } catch (error) {} finally {
        isActionLoading.value = false;
    }
};

const submitQuickNote = async (notification) => {
    if (!noteText.value.trim() || !notification.task_id) return;
    isActionLoading.value = true;
    try {
        await axios.post(`/api/tasks/${notification.task_id}/notes`, { note: noteText.value });
        noteText.value = '';
        addingNoteTo.value = null;
        await markNotificationAndRefetch(notification.view_id);
    } catch (error) {} finally {
        isActionLoading.value = false;
    }
};

const isTypeMatch = (notification, type) => {
    const rawType = notification.type || '';
    if (type === 'task') return rawType === 'task_assigned' || rawType.includes('TaskAssigned');
    if (type === 'mention') return rawType === 'user_mentioned' || rawType.includes('UserMentioned');
    return false;
};

const getIconForType = (type) => {
    const t = type.toLowerCase();
    if (t.includes('task')) return Calendar;
    if (t.includes('mention')) return MessageSquare;
    return Bell;
};

const subscribedProjectIds = ref(new Set());

const subscribeToAllProjects = () => {
    console.log("subscribeToAllProjects called! window.Echo exists?", !!window.Echo);
    if (!window.Echo) return;
    
    projects.value.forEach(p => {
        if (subscribedProjectIds.value.has(p.id)) return;
        console.log(`Subscribing Vue App to project.${p.id} on Reverb...`);
        subscribedProjectIds.value.add(p.id);

        const channel = window.Echo.private(`project.${p.id}`);
        
        channel.on('pusher:subscription_succeeded', () => {
            console.log(`Successfully subscribed to project.${p.id} on Reverb!`);
        });

        channel.on('pusher:subscription_error', (error) => {
            console.error(`Failed to subscribe to project.${p.id} on Reverb! Auth error:`, error);
        });

        channel.listen('.ChatMessageSent', (data) => {
                const incoming = data.messagePayload || data.message || data;
                if (!incoming) return;
                
                const msgTopicId = incoming.topic_id || incoming.telegram_topic_id || data.topicId || null;
                incoming.is_me = (incoming.sender_id == user.value?.id) || (incoming.user_id == user.value?.id) || (data.senderId == user.value?.id);

                console.log('[Reverb] ChatMessageSent received', { msgTopicId, activeTopicId: activeTopic.value?.id, activeProjectId: activeProject.value?.id, incomingProjectId: p.id });

                // Replace pending message if exists (own messages sent from this tab)
                if (incoming.is_me) {
                    const idx = chatMessages.value.findIndex(m => m.pending && m.message === incoming.message);
                    if (idx !== -1) {
                        chatMessages.value[idx] = { ...incoming, pending: false };
                        delete pendingMessages.value[chatMessages.value[idx].id];
                        scrollToBottom();
                        return; // Successfully replaced local optimistic message
                    }
                }

                // Determine if this message belongs to the currently visible chat.
                // topicId can be null for messages not linked to a Telegram topic — in that
                // case we match on project alone so the message still appears.
                const projectMatches = activeProject.value?.id == p.id;
                const topicMatches = msgTopicId === null || activeTopic.value?.id == msgTopicId;

                // If message belongs to active view
                if (projectMatches && topicMatches) {
                    const exists = chatMessages.value.find(m => m.id == incoming.id);
                    if (!exists) {
                        chatMessages.value.push(incoming);
                        scrollToBottom();
                    }

                    if (!incoming.is_me) {
                        maybeShowDesktopNotification({
                            title: incoming.user || p.name || 'New chat message',
                            body: incoming.message || '',
                            tag: `chat_${incoming.id}`,
                            onClick: () => {
                                window.dispatchEvent(new CustomEvent('open-project-chat', { detail: { projectId: p.id } }));
                            },
                        });
                    }
                } else {
                    // It belongs to another view. Only notify if it was NOT sent by the current user.
                    if (!incoming.is_me) {
                        incrementProjectChatUnread(p.id);

                        maybeShowDesktopNotification({
                            title: incoming.user || p.name || 'New chat message',
                            body: incoming.message || '',
                            tag: `chat_${incoming.id}`,
                            onClick: () => {
                                window.dispatchEvent(new CustomEvent('open-project-chat', { detail: { projectId: p.id } }));
                            },
                        });

                        pushSuccess({
                            view_id: `chat_${incoming.id}`,
                            title: incoming.user || 'New Message',
                            project_name: p.name,
                            message: incoming.message,
                            type: 'chat_message',
                            isNewPush: true,
                            isRead: false
                        });
                    }
                }
            });
    });
};

const handleOpenProjectChat = async (event) => {
    const { projectId } = event.detail;
    openChatSidebar();
    
    if (!projects.value.length) {
        await fetchProjects();
    }
    
    const project = projects.value.find(p => p.id == projectId);
    if (project) {
        switchProject(project, { autoSelectTopic: true });
    }
};

const handleOpenNotificationsPanel = () => {
    openNotificationsSidebar();
};

onMounted(() => {
    updateViewportMode();
    fetchProjects();
    window.addEventListener('open-project-chat', handleOpenProjectChat);
    window.addEventListener('open-notifications-panel', handleOpenNotificationsPanel);
    window.addEventListener('resize', updateViewportMode);
});

onUnmounted(() => {
    if (window.Echo) {
        subscribedProjectIds.value.forEach(id => {
            window.Echo.leave(`project.${id}`);
        });
    }
    window.removeEventListener('open-project-chat', handleOpenProjectChat);
    window.removeEventListener('open-notifications-panel', handleOpenNotificationsPanel);
    window.removeEventListener('resize', updateViewportMode);
});

const closeSidebar = () => {
    closeNotificationsSidebar();
};
</script>

<template>
    <teleport to="body">
        <div v-show="notificationSidebarState.show" 
            class="fixed inset-0 bg-slate-900/20 backdrop-blur-sm z-[90]" 
            @click="closeSidebar">
        </div>

        <div :class="[
            'communication-sidebar fixed shadow-2xl z-[100] flex flex-col transform transition-all duration-300 ease-in-out',
            notificationSidebarState.show ? 'translate-x-0' : 'translate-x-full',
            isFullScreen ? 'inset-0 w-full' : 'inset-y-0 right-0 w-full sm:w-[500px] border-l border-slate-200'
        ]">

        <!-- Sidebar Header -->
        <div class="px-5 pt-4 pb-0 border-b border-slate-200 bg-white shadow-sm z-30">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-bold text-slate-800 flex items-center space-x-2">
                    <MessageSquare class="w-5 h-5 text-indigo-600" />
                    <span>{{ sidebarTitle }}</span>
                </h2>
                <div class="flex space-x-2">
                    <button @click="markAllAsRead" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors" title="Mark all as read">
                        <CheckCheck class="w-5 h-5" />
                    </button>
                    <button @click="isFullScreen = !isFullScreen" class="p-1.5 text-slate-400 hover:bg-slate-100 rounded-md transition-colors hidden sm:block">
                        <component :is="isFullScreen ? Minimize : Maximize" class="w-5 h-5" />
                    </button>
                    <button @click="closeSidebar" class="p-1.5 text-slate-400 hover:bg-slate-100 rounded-md transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Tabs (only visible when not full screen to switch to Updates) -->
            <div v-if="showSidebarTabs" class="flex space-x-6 overflow-x-auto">
                <button @click="openChatTab" 
                    :class="['pb-2 text-sm font-medium flex items-center space-x-2 border-b-2 transition-colors', activeTab === 'chat' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']">
                    <MessageSquare class="w-4 h-4" />
                    <span>Topics</span>
                    <span v-if="totalChatUnread > 0" class="bg-indigo-600 text-white py-0.5 px-2 rounded-full text-[10px]">{{ totalChatUnread }}</span>
                </button>
                <button @click="openNotificationsTab" 
                    :class="['pb-2 text-sm font-medium flex items-center space-x-2 border-b-2 transition-colors', activeTab === 'notifications' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']">
                    <Bell class="w-4 h-4" />
                    <span>Updates</span>
                    <span v-if="unreadCount > 0" class="bg-slate-100 text-slate-700 py-0.5 px-2 rounded-full text-[10px]">{{ unreadCount }}</span>
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex-1 flex min-h-0 overflow-hidden bg-slate-50">
            
            <!-- NOTIFICATIONS PANE (Hidden entirely in full screen based on instruction) -->
            <div v-if="!isFullScreen && activeTab === 'notifications'" class="notification-panel w-full flex flex-col h-full overflow-hidden text-white">
                <div class="notification-toolbar px-5 py-4 border-b border-white/10 flex flex-col space-y-4 shrink-0">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Updates Hub</p>
                            <h3 class="mt-1 text-base font-bold text-white">Live activity across projects</h3>
                        </div>
                        <div class="notification-summary-badge">
                            <span class="text-lg font-bold text-white">{{ notificationSummary.total }}</span>
                            <span class="text-[10px] uppercase tracking-[0.18em] text-slate-400">Unread</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="notification-stat-card">
                            <span class="notification-stat-value">{{ notificationSummary.mentions }}</span>
                            <span class="notification-stat-label">Mentions</span>
                        </div>
                        <div class="notification-stat-card">
                            <span class="notification-stat-value">{{ notificationSummary.tasks }}</span>
                            <span class="notification-stat-label">Tasks</span>
                        </div>
                        <div class="notification-stat-card">
                            <span class="notification-stat-value">{{ notificationSummary.other }}</span>
                            <span class="notification-stat-label">Other</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex space-x-2">
                            <button @click="filter = 'all'" :class="['notification-filter-chip', filter === 'all' ? 'notification-filter-chip-active' : '']">All</button>
                            <button @click="filter = 'unread'" :class="['notification-filter-chip', filter === 'unread' ? 'notification-filter-chip-active' : '']">Unread</button>
                        </div>
                        <div class="flex space-x-2">
                            <select v-model="notificationType" class="notification-select text-xs py-1 pl-2 pr-6 shrink-0 cursor-pointer">
                                <option value="all">All Types</option>
                                <option value="mention">Mentions</option>
                                <option value="task">Tasks</option>
                            </select>
                        </div>
                    </div>
                    <div class="w-full relative">
                        <input v-model="searchQuery" type="text" placeholder="Search notifications..." class="notification-search-input w-full text-xs pl-8 py-1.5" />
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <Search class="w-3.5 h-3.5 text-slate-500" />
                        </div>
                    </div>
                </div>
                <!-- Standard Notification List -->
                <div class="notification-list-shell flex-1 overflow-y-auto px-4 py-4">
                    <!-- Today's Notifications -->
                    <div v-if="groupedNotifications.today.length > 0">
                        <div class="notification-section-heading sticky top-0 z-10 px-2 py-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Today</h3>
                        </div>
                        <div class="space-y-3 pb-3">
                            <div v-for="notification in groupedNotifications.today" :key="notification.id"
                                class="notification-item p-5 transition-colors group cursor-pointer"
                                @click="handleNotificationClick(notification)">
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="notification-icon-shell">
                                            <component :is="getIconForType(notification.type)" class="w-5 h-5 text-sky-200" />
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <p class="text-sm font-semibold text-white truncate">
                                                <span v-if="notification.task_number" class="text-sky-300 mr-1">#{{ notification.task_number }}</span>
                                                {{ notification.title }}
                                            </p>
                                            <span class="text-xs text-slate-400 whitespace-nowrap ml-2"><Clock class="w-3 h-3 inline mr-1" />{{ notification.created_at }}</span>
                                        </div>
                                        <p class="text-sm text-slate-300" v-html="formatMessage(notification.message)"></p>
                                    </div>
                                    <div v-if="!notification.isRead" class="w-2 h-2 mt-2 bg-sky-300 rounded-full shadow-[0_0_12px_rgba(125,211,252,0.7)]"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- This Week's Notifications -->
                    <div v-if="groupedNotifications.thisWeek.length > 0">
                        <div class="notification-section-heading sticky top-0 z-10 px-2 py-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">This Week</h3>
                        </div>
                        <div class="space-y-3 pb-3">
                            <div v-for="notification in groupedNotifications.thisWeek" :key="notification.id"
                                class="notification-item p-5 transition-colors group cursor-pointer"
                                @click="handleNotificationClick(notification)">
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="notification-icon-shell">
                                            <component :is="getIconForType(notification.type)" class="w-5 h-5 text-sky-200" />
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <p class="text-sm font-semibold text-white truncate">
                                                <span v-if="notification.task_number" class="text-sky-300 mr-1">#{{ notification.task_number }}</span>
                                                {{ notification.title }}
                                            </p>
                                            <span class="text-xs text-slate-400 whitespace-nowrap ml-2"><Clock class="w-3 h-3 inline mr-1" />{{ notification.created_at }}</span>
                                        </div>
                                        <p class="text-sm text-slate-300" v-html="formatMessage(notification.message)"></p>
                                    </div>
                                    <div v-if="!notification.isRead" class="w-2 h-2 mt-2 bg-sky-300 rounded-full shadow-[0_0_12px_rgba(125,211,252,0.7)]"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Older Notifications -->
                    <div v-if="groupedNotifications.older.length > 0">
                        <div class="notification-section-heading sticky top-0 z-10 px-2 py-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Older</h3>
                        </div>
                        <div class="space-y-3 mb-10">
                            <div v-for="notification in groupedNotifications.older" :key="notification.id"
                                class="notification-item p-5 transition-colors group cursor-pointer"
                                @click="handleNotificationClick(notification)">
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="notification-icon-shell">
                                            <component :is="getIconForType(notification.type)" class="w-5 h-5 text-sky-200" />
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <p class="text-sm font-semibold text-white truncate">
                                                <span v-if="notification.task_number" class="text-sky-300 mr-1">#{{ notification.task_number }}</span>
                                                {{ notification.title }}
                                            </p>
                                            <span class="text-xs text-slate-400 whitespace-nowrap ml-2"><Clock class="w-3 h-3 inline mr-1" />{{ notification.created_at }}</span>
                                        </div>
                                        <p class="text-sm text-slate-300" v-html="formatMessage(notification.message)"></p>
                                    </div>
                                    <div v-if="!notification.isRead" class="w-2 h-2 mt-2 bg-sky-300 rounded-full shadow-[0_0_12px_rgba(125,211,252,0.7)]"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="groupedNotifications.today.length === 0 && groupedNotifications.thisWeek.length === 0 && groupedNotifications.older.length === 0" class="notification-empty-state text-center py-10 text-sm">
                        <p class="text-base font-semibold text-white">No notifications found</p>
                        <p class="mt-2 text-sm text-slate-400">When mentions, tasks, or live project updates arrive, they’ll appear here.</p>
                    </div>
                </div>
            </div>

            <!-- CHAT / TELEGRAM SYSTEM PANE -->
            <div v-if="isFullScreen || activeTab === 'chat'" :class="['flex-1 flex w-full h-full min-h-0 overflow-hidden', isMobileView ? 'flex-col' : 'flex-row']">
                <div v-if="isMobileView && mobilePane !== 'messages'" class="chat-mobile-panel flex-1 min-h-0 overflow-hidden text-white">
                    <div
                        :class="['flex h-full w-[200%] transition-transform duration-300 ease-out', mobilePane === 'topics' ? '-translate-x-1/2' : 'translate-x-0']"
                    >
                        <section class="w-1/2 h-full border-r border-white/10 bg-[#0f172a] flex flex-col">
                            <div class="px-4 py-3 border-b border-white/10 shrink-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400/80">Projects</p>
                                <h3 class="mt-1 text-sm font-bold text-white">Choose Workspace</h3>
                                <input v-model="projectSearch" type="text" placeholder="Search projects..." class="mt-3 w-full rounded-xl border-white/10 bg-white/5 text-sm text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-sky-400">
                            </div>
                            <div class="flex-1 overflow-y-auto px-3 py-3 space-y-2">
                                <button v-for="p in availableContexts" :key="p.id" @click="switchProject(p)"
                                    :class="['w-full rounded-2xl border px-3 py-3 text-left transition-colors', activeProject?.id === p.id ? 'border-sky-300/40 bg-sky-400 text-slate-950 shadow-sm' : 'border-white/10 bg-white/5 text-slate-200']">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/10 text-[10px] font-bold uppercase">{{ p.name.substring(0,2) }}</span>
                                        <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ p.name }}</span>
                                        <span v-if="p.unreadCount > 0" :class="['rounded-full px-1.5 py-0.5 text-[10px] font-bold', activeProject?.id === p.id ? 'bg-slate-950/15 text-slate-950' : 'bg-sky-400/15 text-sky-200']">{{ p.unreadCount }}</span>
                                    </div>
                                </button>
                            </div>
                        </section>

                        <section class="w-1/2 h-full bg-[#0f172a] flex flex-col">
                            <div class="px-3 py-3 border-b border-white/10 shrink-0">
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        @click="goToMobileProjects"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-200"
                                    >
                                        <ChevronLeft class="w-3.5 h-3.5" />
                                        Projects
                                    </button>
                                    <button @click="isCreatingTopic = !isCreatingTopic" class="inline-flex items-center gap-1 rounded-full bg-sky-400/15 px-3 py-1.5 text-xs font-semibold text-sky-200 hover:bg-sky-400/25 transition-colors">
                                        <Plus class="w-3.5 h-3.5" />
                                        Topic
                                    </button>
                                </div>
                                <h3 class="mt-3 text-sm font-bold text-white truncate">{{ activeProject?.name || 'Select Project' }}</h3>
                            </div>

                            <div v-if="isCreatingTopic" class="mx-3 mt-3 rounded-2xl border border-white/10 bg-white/5 p-3 shadow-sm">
                                <p class="text-[10px] uppercase font-bold tracking-wider text-sky-200">New Telegram Topic</p>
                                <input v-model="newTopicName" @keyup.enter="createNewTopic" type="text" placeholder="e.g. Budget Discuss..." class="mt-2 mb-2 w-full rounded-xl border-white/10 bg-white/5 text-sm text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-sky-400">
                                <div class="flex justify-end gap-2">
                                    <button @click="isCreatingTopic = false" class="px-3 py-1.5 text-xs font-medium text-slate-400">Cancel</button>
                                    <button @click="createNewTopic" :disabled="!newTopicName.trim() || isActionLoading" class="rounded-full bg-sky-400 px-3 py-1.5 text-xs font-semibold text-slate-950 disabled:opacity-50">Create</button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto px-3 py-3">
                                <div v-if="loadingTopics" class="flex justify-center py-3 text-indigo-500">
                                    <div class="animate-spin rounded-full h-4 w-4 border-2 border-indigo-500 border-t-transparent"></div>
                                </div>
                                <div v-else class="space-y-2">
                                    <button v-for="topic in topics" :key="topic.id" @click="switchTopic(topic)"
                                        :class="['flex w-full items-center gap-2 rounded-2xl border px-3 py-3 text-left text-sm font-medium transition-colors', activeTopic?.id === topic.id ? 'border-sky-300/40 bg-sky-400 text-slate-950' : 'border-white/10 bg-white/5 text-slate-200']">
                                        <Hash class="h-4 w-4 shrink-0" />
                                        <span class="truncate">{{ topic.name }}</span>
                                    </button>
                                    <div v-if="!topics.length" class="py-3 text-center text-xs text-slate-400">No topics found. Add one to start chatting.</div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <template v-else-if="!isMobileView">
                    <!-- SIDEBAR 1: PROJECTS -->
                    <div :class="['chat-projects-panel border-r border-white/10 flex flex-col', isFullScreen ? 'w-64' : 'w-20 sm:w-16']" style="min-width: 64px;">
                        <div v-if="isFullScreen" class="px-4 py-3 border-b border-white/10 bg-white/5">
                            <input v-model="projectSearch" type="text" placeholder="Filter projects..." class="w-full rounded-xl border-white/10 bg-white/5 text-xs text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-sky-400">
                        </div>
                        
                        <div class="flex-1 overflow-y-auto py-2 flex flex-col items-center sm:items-stretch">
                            <button v-for="p in availableContexts" :key="p.id" @click="switchProject(p)"
                                :class="['w-full sm:px-3 px-0 py-3 flex flex-col sm:flex-row items-center sm:justify-start justify-center transition-colors border-l-4 relative',
                                    activeProject?.id === p.id ? 'bg-white/10 border-sky-400 shadow-sm' : 'border-transparent hover:bg-white/5'
                                ]"
                                :title="p.name">
                                <div :class="['w-8 h-8 rounded shrink-0 flex items-center justify-center text-white text-xs font-bold uppercase shadow-sm sm:mr-3 mr-0', activeProject?.id === p.id ? 'bg-sky-400 text-slate-950' : 'bg-slate-700']">
                                    {{ p.name.substring(0,2) }}
                                </div>
                                <div v-if="isFullScreen" class="text-left flex-1 min-w-0 hidden sm:block">
                                    <span class="block text-sm font-medium truncate" :class="activeProject?.id === p.id ? 'text-white font-bold' : 'text-slate-300'">{{ p.name }}</span>
                                </div>
                                <span v-if="p.unreadCount > 0 && isFullScreen" class="ml-auto bg-sky-400/15 text-sky-200 text-[10px] font-bold px-1.5 py-0.5 rounded-full hidden sm:block">{{ p.unreadCount }}</span>
                                <span v-else-if="p.unreadCount > 0" class="absolute right-1 top-1 bg-sky-400 text-slate-950 text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full sm:hidden">{{ p.unreadCount }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- SIDEBAR 2: TOPICS -->
                    <div :class="['chat-topics-panel border-r border-white/10 flex flex-col', isFullScreen ? 'w-72' : 'w-48']">
                        <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between bg-white/5 shrink-0">
                            <h3 class="text-sm font-bold text-white truncate">{{ activeProject?.name || 'Select Project' }}</h3>
                            <button @click="isCreatingTopic = !isCreatingTopic" class="bg-sky-400/15 text-sky-200 hover:bg-sky-400/25 p-1 rounded transition-colors" title="New Topic">
                                <Plus class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Create Topic Inline Form -->
                        <div v-if="isCreatingTopic" class="p-3 bg-white/5 border-b border-white/10 shadow-sm z-10">
                            <p class="text-[10px] uppercase font-bold text-sky-200 tracking-wider mb-2">New Telegram Topic</p>
                            <input v-model="newTopicName" @keyup.enter="createNewTopic" type="text" placeholder="e.g. Budget Discuss..." class="w-full text-xs box-border rounded-xl border-white/10 bg-white/5 text-white placeholder:text-slate-400 focus:ring-sky-400 focus:border-sky-400 mb-2">
                            <div class="flex justify-end space-x-2">
                                <button @click="isCreatingTopic = false" class="px-2 py-1 text-xs text-slate-400 hover:text-white">Cancel</button>
                                <button @click="createNewTopic" :disabled="!newTopicName.trim() || isActionLoading" class="px-3 py-1 bg-sky-400 text-slate-950 text-xs font-semibold rounded-full hover:bg-sky-300 disabled:opacity-50 transition-colors">Create</button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto bg-transparent p-2 space-y-0.5">
                            <div v-if="loadingTopics" class="p-4 flex justify-center text-indigo-500">
                                <div class="animate-spin rounded-full h-4 w-4 border-2 border-indigo-500 border-t-transparent"></div>
                            </div>
                            <template v-else>
                                <button v-for="topic in topics" :key="topic.id" @click="switchTopic(topic)"
                                    :class="['w-full group px-3 py-2 rounded-md flex items-center text-left transition-colors',
                                        activeTopic?.id === topic.id ? 'bg-sky-400 text-slate-950' : 'text-slate-300 hover:bg-white/5'
                                    ]">
                                    <span :class="['mr-2', activeTopic?.id === topic.id ? 'text-slate-950' : 'text-slate-500 group-hover:text-sky-300']">
                                        <Hash class="w-4 h-4" />
                                    </span>
                                    <span class="text-sm font-medium truncate shrink min-w-0" :class="{'font-bold': activeTopic?.id === topic.id}">{{ topic.name }}</span>
                                </button>
                                <div v-if="!topics.length && !loadingTopics" class="text-xs text-center text-slate-400 mt-10">No topics found.<br/>Click + to link a telegram thread.</div>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- MAIN AREA: CHAT -->
                <div v-if="!isMobileView || mobilePane === 'messages'" class="chat-main-panel flex-1 flex min-h-0 flex-col min-w-0">
                    <!-- Chat Header -->
                    <div v-if="activeTopic" :class="['chat-stage-header border-b border-white/10 shadow-sm shrink-0', isMobileView ? 'px-3 py-3' : 'px-5 py-4']">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <button
                                    v-if="isMobileView"
                                    type="button"
                                    @click="goToMobileTopics"
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/15 bg-white/5 text-slate-200"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </button>
                                <div :class="['flex items-center justify-center rounded-2xl bg-sky-400/15 text-sky-200 shadow-inner shadow-sky-400/10', isMobileView ? 'h-9 w-9' : 'h-10 w-10']">
                                    <Hash class="w-5 h-5" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-bold text-white">{{ activeTopic.name }}</h3>
                                    <p v-if="!isMobileView" class="text-xs text-slate-400">{{ activeTopicStatus }}</p>
                                    <p v-else class="text-[11px] text-slate-400 truncate">{{ mobileChatSummary }}</p>
                                </div>
                            </div>
                            <div v-if="!isMobileView" class="grid grid-cols-3 gap-2 text-center md:min-w-[260px]">
                                <div class="chat-stat-card">
                                    <span class="chat-stat-value">{{ topics.length }}</span>
                                    <span class="chat-stat-label">Topics</span>
                                </div>
                                <div class="chat-stat-card">
                                    <span class="chat-stat-value">{{ activeTopicMessageCount }}</span>
                                    <span class="chat-stat-label">Messages</span>
                                </div>
                                <div class="chat-stat-card">
                                    <span class="chat-stat-value">{{ activeProjectUnread }}</span>
                                    <span class="chat-stat-label">Unread</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div ref="chatContainer" @scroll="handleScroll" @click="handleChatClick" class="chat-history-shell flex-1 min-h-0 overflow-y-auto p-5 space-y-6">
                        <div v-if="shouldShowChatLanding && projects.length" class="chat-landing-grid">
                            <section class="chat-empty-state chat-landing-hero">
                                <MessageSquare class="mx-auto mb-4 w-12 h-12 text-sky-300/80" />
                                <p class="text-base font-semibold text-white">Recent conversations</p>
                                <p class="mt-2 text-sm text-slate-400">Pick a workspace below to jump into the latest topic thread without being forced into the first channel.</p>
                            </section>

                            <section class="chat-landing-panel">
                                <div class="chat-landing-panel-header">
                                    <h4 class="text-sm font-bold text-white">Quick Access</h4>
                                    <span class="text-[10px] uppercase tracking-[0.18em] text-slate-400">Projects</span>
                                </div>
                                <div class="chat-landing-project-list">
                                    <button
                                        v-for="project in chatLandingProjects"
                                        :key="project.id"
                                        @click="switchProject(project, { autoSelectTopic: false })"
                                        class="chat-landing-project-card"
                                        :class="{ 'chat-landing-project-card-active': activeProject?.id === project.id }"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="chat-landing-avatar">{{ project.name.substring(0, 2) }}</div>
                                            <div class="min-w-0 text-left">
                                                <p class="truncate text-sm font-semibold text-white">{{ project.name }}</p>
                                                <p class="truncate text-xs text-slate-400">
                                                    {{ project.unreadCount > 0 ? `${project.unreadCount} unread messages` : 'No unread messages' }}
                                                </p>
                                            </div>
                                        </div>
                                        <span v-if="project.unreadCount > 0" class="chat-landing-count-pill">{{ project.unreadCount }}</span>
                                    </button>
                                </div>
                            </section>

                            <section class="chat-landing-panel">
                                <div class="chat-landing-panel-header">
                                    <h4 class="text-sm font-bold text-white">{{ activeProject?.name || 'Topics' }}</h4>
                                    <span class="text-[10px] uppercase tracking-[0.18em] text-slate-400">Topic Shortcuts</span>
                                </div>
                                <div v-if="loadingTopics" class="flex justify-center py-8 text-sky-300">
                                    <div class="animate-spin rounded-full h-5 w-5 border-2 border-sky-300 border-t-transparent"></div>
                                </div>
                                <div v-else-if="activeProjectTopicsPreview.length" class="chat-landing-topics-grid">
                                    <button
                                        v-for="topic in activeProjectTopicsPreview"
                                        :key="topic.id"
                                        @click="switchTopic(topic)"
                                        class="chat-landing-topic-card"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Hash class="w-4 h-4 text-sky-300" />
                                            <span class="truncate text-sm font-semibold text-white">{{ topic.name }}</span>
                                        </div>
                                        <span class="text-xs text-slate-400">Open thread</span>
                                    </button>
                                </div>
                                <div v-else class="chat-landing-empty-copy">
                                    <p class="text-sm font-semibold text-white">No linked topics yet</p>
                                    <p class="mt-1 text-xs text-slate-400">Create or connect a Telegram topic to start a conversation for this project.</p>
                                </div>
                            </section>
                        </div>

                        <div v-if="loadingMore" class="flex justify-center py-2">
                            <div class="animate-spin rounded-full h-4 w-4 border-2 border-indigo-500 border-t-transparent"></div>
                        </div>

                        <div v-for="msg in chatMessages" :key="msg.id" :id="`msg-${msg.id}`" 
                            class="chat-message-row flex flex-col space-y-1 group/msg max-w-[85%] relative"
                            :class="[msg.is_me ? 'ml-auto items-end' : 'mr-auto items-start']">
                            
                            <div class="flex items-baseline space-x-2 mb-0.5 px-1" :class="[msg.is_me ? 'flex-row-reverse space-x-reverse' : '']">
                                <span class="text-xs font-semibold text-slate-700">{{ msg.user }}</span>
                                <span class="text-[10px] text-slate-400">{{ msg.time }}</span>
                            </div>
                            
                            <div v-if="msg.type !== 'email'" 
                                class="absolute top-4 opacity-0 group-hover/msg:opacity-100 transition-opacity flex items-center space-x-1"
                                :class="[msg.is_me ? '-left-16' : '-right-16']">
                                <button @click="replyToMessage = msg"
                                    class="p-1.5 text-slate-400 bg-white hover:text-indigo-600 shadow-sm rounded-full border border-slate-200 transition-all">
                                    <CornerDownRight class="w-3.5 h-3.5" />
                                </button>
                                <button v-if="msg.is_me || user.role?.slug === 'super-admin' || user.role?.slug === 'admin' || user.role?.slug === 'manager'" 
                                    @click="deleteMessage(msg)"
                                    class="p-1.5 text-slate-400 bg-white hover:text-red-500 shadow-sm rounded-full border border-slate-200 transition-all">
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <template v-if="msg.type === 'email'">
                                <div class="bg-slate-100/80 border border-slate-200 p-3 rounded-xl shadow-sm">
                                    <div class="flex items-center text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wider">
                                        <Mail class="w-3 h-3 mr-1.5" />
                                        {{ msg.direction === 'inbound' ? 'Received' : 'Sent' }}
                                    </div>
                                    <p class="text-sm text-slate-700 italic border-l-2 border-slate-300 pl-3 py-1">"{{ msg.summary }}"</p>
                                </div>
                            </template>
                            <template v-else>
                                <!-- Parent Preview -->
                                <div v-if="msg.parent" @click="scrollToMessage(msg.parent.id)"
                                    class="px-3 py-1.5 rounded-2xl text-xs cursor-pointer truncate max-w-full opacity-75 hover:opacity-100 transition-opacity mb-1 border"
                                    :class="[msg.is_me ? 'bg-sky-400/10 border-sky-300/20 text-sky-100 text-right mr-1' : 'bg-white/5 border-white/10 text-slate-300 text-left ml-1']">
                                    <span class="font-bold block text-[10px] uppercase mb-0.5">{{ msg.parent.user }}</span>
                                    <span class="opacity-80">{{ msg.parent.message }}</span>
                                </div>

                                <div class="chat-message-bubble px-4 py-3 rounded-[18px] text-sm leading-relaxed shadow-sm break-words max-w-full"
                                     :class="[msg.is_me ? 'chat-message-bubble-sent text-slate-950 rounded-tr-sm' : 'chat-message-bubble-received text-slate-100 rounded-tl-sm']"
                                     v-html="formatMessage(msg.message)">
                                </div>
                                <div v-if="msg.is_me" class="flex items-center pr-1 h-3 mt-0.5">
                                    <CheckCheck v-if="msg.reads && msg.reads.length" class="w-3.5 h-3.5 text-sky-300" :title="msg.reads.map(r => r.user).join(', ')" />
                                    <CheckCheck v-else class="w-3.5 h-3.5 text-slate-500" title="Sent" />
                                </div>
                            </template>
                        </div>
                        <div v-if="chatMessages.length === 0 && activeTopic && !loadingChat" class="py-10 text-center text-sm text-slate-300">
                            <div class="chat-empty-state mx-auto max-w-md">
                                <p class="text-base font-semibold text-white">No messages in this topic yet</p>
                                <p class="mt-2 text-sm text-slate-400">Start the thread here and the Telegram-linked conversation will stay synced.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div v-if="activeTopic" :class="['chat-composer-panel border-t border-white/10 shrink-0', isMobileView ? 'p-3' : 'p-4']">
                        <div v-if="isCreatingTopic && isMobileView" class="mb-3 rounded-2xl border border-white/10 bg-white/5 p-3 shadow-sm">
                            <p class="text-[10px] uppercase font-bold tracking-wider text-sky-200">New Telegram Topic</p>
                            <input v-model="newTopicName" @keyup.enter="createNewTopic" type="text" placeholder="e.g. Budget Discuss..." class="mt-2 mb-2 w-full rounded-xl border-white/10 bg-white/5 text-sm text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-sky-400">
                            <div class="flex justify-end gap-2">
                                <button @click="isCreatingTopic = false" class="px-3 py-1.5 text-xs font-medium text-slate-400">Cancel</button>
                                <button @click="createNewTopic" :disabled="!newTopicName.trim() || isActionLoading" class="rounded-full bg-sky-400 px-3 py-1.5 text-xs font-semibold text-slate-950 disabled:opacity-50">Create</button>
                            </div>
                        </div>
                        <div v-if="isClientMessagingBlocked" class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2">
                            <p class="text-xs font-semibold text-amber-800">Client messaging unavailable</p>
                            <p class="mt-1 text-xs text-amber-700">
                                {{ clientMessagingNotice }}
                            </p>
                            <p class="mt-1 text-[11px] text-amber-700/90">
                                Linked clients: {{ activeTopicClientMessaging?.linked_client_count ?? 0 }} / {{ activeTopicClientMessaging?.total_client_count ?? 0 }}
                            </p>
                        </div>

                        <div v-if="replyToMessage" class="mb-2 mx-2 flex items-center justify-between rounded-r-2xl border-l-4 border-sky-400 bg-white/5 p-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] uppercase font-bold text-sky-200 tracking-wider">Replying to {{ replyToMessage.user }}</p>
                                <p class="text-xs text-slate-300 truncate opacity-80 mt-0.5">{{ replyToMessage.message }}</p>
                            </div>
                            <button @click="replyToMessage = null" class="ml-2 rounded-md p-1.5 text-slate-400 transition-colors hover:bg-white/10">
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div class="relative flex items-end gap-2 rounded-[24px] border border-white/10 bg-white/5 p-1 shadow-sm transition-all focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-400/30">
                            <MentionInput
                                ref="mentionInputRef"
                                v-model="newMessage"
                                :project-id="activeProject?.id"
                                type="textarea"
                                :placeholder="isClientMessagingBlocked ? 'Client messaging is unavailable until a client links Telegram.' : 'Message to topic...'"
                                @submit="handleSendMessage"
                                class="flex-1 bg-transparent text-white !border-none !shadow-none !ring-0 !min-h-[44px] pt-[12px]"
                            />
                            <button @click="handleSendMessage" :disabled="!newMessage.trim() || isClientMessagingBlocked"
                                class="m-1 flex shrink-0 items-center justify-center rounded-full bg-sky-400 p-2 text-slate-950 shadow-sm transition-colors hover:bg-sky-300 disabled:opacity-50">
                                <Send class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</teleport>
</template>
<style scoped>
.communication-sidebar {
    background:
        radial-gradient(circle at top left, rgba(56, 189, 248, 0.16), transparent 22%),
        radial-gradient(circle at top right, rgba(129, 140, 248, 0.14), transparent 26%),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
}

.notification-panel,
.notification-toolbar {
    background:
        radial-gradient(circle at top, rgba(56, 189, 248, 0.1), transparent 22%),
        linear-gradient(180deg, rgba(15, 23, 42, 0.97) 0%, rgba(2, 6, 23, 0.98) 100%);
}

.notification-list-shell {
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.04) 1px, transparent 1px);
    background-size: 24px 24px;
}

.notification-summary-badge,
.notification-stat-card,
.notification-item,
.notification-empty-state,
.notification-section-heading {
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(12px);
}

.notification-summary-badge {
    display: flex;
    min-width: 4.8rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    border-radius: 1rem;
    padding: 0.8rem;
}

.notification-stat-card {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    border-radius: 1rem;
    padding: 0.85rem;
}

.notification-stat-value {
    font-size: 1rem;
    font-weight: 700;
    color: #f8fafc;
}

.notification-stat-label {
    font-size: 0.68rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #94a3b8;
}

.notification-filter-chip {
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.04);
    padding: 0.35rem 0.8rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #cbd5e1;
    transition: all 0.2s ease;
}

.notification-filter-chip:hover,
.notification-filter-chip-active {
    background: #38bdf8;
    border-color: rgba(125, 211, 252, 0.4);
    color: #020617;
}

.notification-select,
.notification-search-input {
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.04);
    color: #f8fafc;
}

.notification-select:focus,
.notification-search-input:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
    outline: none;
}

.notification-search-input::placeholder {
    color: #64748b;
}

.notification-section-heading {
    border-radius: 1rem;
}

.notification-item {
    border-radius: 1.35rem;
    transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
}

.notification-item:hover {
    transform: translateY(-1px);
    border-color: rgba(56, 189, 248, 0.22);
    background: rgba(255, 255, 255, 0.06);
}

.notification-icon-shell {
    display: flex;
    height: 2.5rem;
    width: 2.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    border: 1px solid rgba(125, 211, 252, 0.16);
    background: rgba(56, 189, 248, 0.12);
}

.notification-empty-state {
    margin: 3rem auto;
    max-width: 24rem;
    border-radius: 1.5rem;
    padding: 2rem;
}

.chat-projects-panel,
.chat-topics-panel,
.chat-main-panel,
.chat-mobile-panel,
.chat-composer-panel,
.chat-stage-header {
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(14px);
}

.chat-main-panel {
    background:
        radial-gradient(circle at top, rgba(56, 189, 248, 0.14), transparent 24%),
        linear-gradient(180deg, rgba(15, 23, 42, 0.98) 0%, rgba(2, 6, 23, 0.96) 100%);
}

.chat-history-shell {
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.05) 1px, transparent 1px);
    background-position: center;
    background-size: 28px 28px;
}

.chat-stat-card {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1rem;
    padding: 0.7rem 0.85rem;
    background: rgba(255, 255, 255, 0.04);
}

.chat-stat-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #f8fafc;
}

.chat-stat-label {
    font-size: 0.68rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #94a3b8;
}

.chat-empty-state {
    max-width: 28rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1.5rem;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(12px);
}

.chat-message-row {
    filter: drop-shadow(0 12px 20px rgba(2, 6, 23, 0.18));
}

.chat-landing-grid {
    display: grid;
    gap: 1.25rem;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
}

.chat-landing-hero {
    max-width: none;
}

.chat-landing-panel {
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1.5rem;
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(12px);
}

.chat-landing-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.chat-landing-project-list,
.chat-landing-topics-grid {
    display: grid;
    gap: 0.75rem;
}

.chat-landing-project-card,
.chat-landing-topic-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1.1rem;
    padding: 0.95rem 1rem;
    background: rgba(255, 255, 255, 0.03);
    transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
}

.chat-landing-project-card:hover,
.chat-landing-topic-card:hover,
.chat-landing-project-card-active {
    transform: translateY(-1px);
    border-color: rgba(56, 189, 248, 0.28);
    background: rgba(56, 189, 248, 0.08);
}

.chat-landing-avatar {
    display: inline-flex;
    height: 2.4rem;
    width: 2.4rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%);
    color: #020617;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
}

.chat-landing-count-pill {
    display: inline-flex;
    min-width: 1.65rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: rgba(56, 189, 248, 0.14);
    padding: 0.25rem 0.45rem;
    color: #bae6fd;
    font-size: 0.7rem;
    font-weight: 700;
}

.chat-landing-empty-copy {
    border: 1px dashed rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    padding: 1rem;
    text-align: center;
}

.chat-message-bubble {
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.chat-message-bubble-sent {
    background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%);
    border-color: rgba(125, 211, 252, 0.32);
}

.chat-message-bubble-received {
    background: rgba(30, 41, 59, 0.92);
    border-color: rgba(255, 255, 255, 0.08);
}

.chat-composer-panel {
    box-shadow: 0 -8px 32px rgba(2, 6, 23, 0.18);
}

.chat-composer-panel :deep(textarea),
.chat-composer-panel :deep(input[type="text"]) {
    color: #f1f5f9;
    background: transparent;
    caret-color: #38bdf8;
}

.chat-composer-panel :deep(textarea::placeholder),
.chat-composer-panel :deep(input[type="text"]::placeholder) {
    color: #64748b;
}

@media (max-width: 639px) {
    .communication-sidebar {
        background: linear-gradient(180deg, #020617 0%, #0f172a 100%);
        height: 100dvh;
    }

    .chat-history-shell {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .chat-empty-state {
        padding: 1.5rem;
    }

    .chat-landing-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

.mobile-selector-button {
    display: inline-flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.04);
    padding: 0.85rem 0.95rem;
    color: #e2e8f0;
    font-size: 0.75rem;
    font-weight: 700;
    transition: all 0.2s ease;
}

.mobile-selector-button:disabled {
    opacity: 0.45;
}

.mobile-selector-button-active {
    border-color: rgba(125, 211, 252, 0.4);
    background: rgba(56, 189, 248, 0.12);
    color: #f8fafc;
}

.mobile-selector-meta {
    display: inline-flex;
    min-width: 1.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: rgba(15, 23, 42, 0.3);
    padding: 0.2rem 0.45rem;
    font-size: 0.65rem;
    font-weight: 800;
}
</style>
