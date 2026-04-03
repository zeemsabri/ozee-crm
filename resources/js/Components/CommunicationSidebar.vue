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
    ChevronDown,
    Plus,
    Maximize,
    Minimize,
    CornerDownRight,
    Mail,
    Hash,
    ExternalLink,
    Search
} from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { openTaskDetailSidebar } from '@/Utils/sidebar';
import { 
    notificationSidebarState, 
    closeNotificationsSidebar,
    markNotificationAndRefetch,
    markToastAsSeen
} from '@/Utils/notification-sidebar';
import { pushSuccess } from '@/Utils/notification';
import { formatDate } from '@/Utils/notification';
import { formatMentions } from '@/Utils/mentions';
import MentionInput from '@/Components/ProjectTasks/MentionInput.vue';

const props = defineProps({});

const isFullScreen = ref(false);
const activeTab = ref('notifications'); // 'notifications' or 'chat'
const filter = ref('unread');
const notificationType = ref('all');
const searchQuery = ref('');

watch(isFullScreen, (val) => {
    if (val) {
        activeTab.value = 'chat';
    } else {
        activeTab.value = 'notifications';
    }
});

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
const unreadByProject = ref({}); 
const projectSearch = ref('');
const subscribedTopicIds = ref(new Set()); 

const user = computed(() => usePage().props.auth.user);
const notifications = computed(() => notificationSidebarState.value.notifications);
const unreadCount = computed(() => notifications.value.filter(n => !n.isRead).length);
const totalChatUnread = computed(() => Object.values(unreadByProject.value).reduce((a, b) => a + b, 0));

const availableContexts = computed(() => {
    const search = projectSearch.value.toLowerCase();
    return projects.value
        .filter(p => !search || p.name.toLowerCase().includes(search))
        .map(p => ({
            ...p,
            unreadCount: unreadByProject.value[p.id] ?? 0,
            color: 'bg-indigo-500' // Default
        }))
        .sort((a, b) => b.unreadCount - a.unreadCount);
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

const fetchUnreadCounts = async () => {
    try {
        const { data } = await axios.get('/api/chat/unread-counts');
        unreadByProject.value = data;
    } catch (e) {
        // silent
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
                switchProject(p, false);
            } else {
                switchProject(projects.value[0], false);
            }
        }
        fetchUnreadCounts();
    } catch (error) {
        console.error('Error fetching projects:', error);
    }
};

const fetchTopics = async () => {
    if (!activeProject.value) return;
    loadingTopics.value = true;
    try {
        const response = await axios.get(`/api/projects/${activeProject.value.id}/topics`);
        topics.value = response.data;
        
        // Select first topic automatically or General
        if (topics.value.length > 0) {
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
        activeTab.value = 'chat';
        const proj = projects.value.find(p => p.id == notification.project_id);
        if (proj) {
            await switchProject(proj, true);
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
    
    const messageContent = newMessage.value;
    newMessage.value = '';

    try {
        const response = await axios.post(`/api/projects/${activeProject.value.id}/chat`, {
            message: messageContent,
            parent_id: replyToMessage.value?.id,
            telegram_topic_id: activeTopic.value.id
        });
        
        const msg = {
            id: response.data.id,
            type: 'text',
            user: user.value.name,
            initials: user.value.name.substring(0, 2).toUpperCase(),
            color: 'bg-indigo-600',
            message: response.data.message,
            parent: replyToMessage.value,
            time: 'Just now',
            is_me: true,
            created_at: response.data.created_at
        };
        chatMessages.value.push(msg);
        newMessage.value = '';
        replyToMessage.value = null;
        mentionInputRef.value?.clear();
        scrollToBottom();
    } catch (error) {
        console.error('Error sending message:', error);
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

const switchProject = async (project, fetchChat = true) => {
    activeProject.value = project;
    showContextDropdown.value = false;
    projectSearch.value = '';
    
    if (unreadByProject.value[project.id]) {
        unreadByProject.value = { ...unreadByProject.value, [project.id]: 0 };
    }
    
    await fetchTopics();
};

const switchTopic = (topic) => {
    activeTopic.value = topic;
    subscribeToTopic(topic.id);
    fetchChatMessages();
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

const subscribeToTopic = (topicId) => {
    if (!window.Echo || !topicId) return;
    if (subscribedTopicIds.value.has(topicId)) return; 

    subscribedTopicIds.value.add(topicId);

    window.Echo.private(`topic.${topicId}`)
        .listen('.ChatMessageSent', (data) => {
            const incoming = data.messagePayload;
            if (!incoming) return;

            incoming.is_me = incoming.sender_id === user.value?.id;

            if (activeTopic.value?.id === topicId) {
                if (!incoming.is_me) {
                    chatMessages.value.push(incoming);
                    scrollToBottom();
                }
            } else {
                if (!incoming.is_me) {
                    // Update badge later... setup simple toast push here
                    pushSuccess({
                        view_id: `chat_${incoming.id}`, 
                        title: incoming.user || 'New Message',
                        project_name: 'Team Chat',
                        message: incoming.message,
                        type: 'chat_message',
                        isNewPush: true,
                        isRead: false
                    });
                }
            }
        });
};

const handleOpenProjectChat = async (event) => {
    const { projectId } = event.detail;
    notificationSidebarState.value.show = true;
    isFullScreen.value = true;
    activeTab.value = 'chat';
    
    if (!projects.value.length) {
        await fetchProjects();
    }
    
    const project = projects.value.find(p => p.id == projectId);
    if (project) {
        switchProject(project);
    }
};

onMounted(() => {
    fetchProjects();
    window.addEventListener('open-project-chat', handleOpenProjectChat);
});

onUnmounted(() => {
    if (window.Echo) {
        subscribedTopicIds.value.forEach(id => {
            window.Echo.leave(`topic.${id}`);
        });
    }
    window.removeEventListener('open-project-chat', handleOpenProjectChat);
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
            'fixed bg-white shadow-2xl z-[100] flex flex-col transform transition-all duration-300 ease-in-out',
            notificationSidebarState.show ? 'translate-x-0' : 'translate-x-full',
            isFullScreen ? 'inset-0 w-full' : 'inset-y-0 right-0 w-full sm:w-[500px] border-l border-slate-200'
        ]">

        <!-- Sidebar Header -->
        <div class="px-5 pt-4 pb-0 border-b border-slate-200 bg-white shadow-sm z-30">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-bold text-slate-800 flex items-center space-x-2">
                    <MessageSquare class="w-5 h-5 text-indigo-600" />
                    <span>{{ isFullScreen ? 'Communication Center' : 'Team Chat' }}</span>
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
            <div v-if="!isFullScreen" class="flex space-x-6">
                <button @click="isFullScreen = true" 
                    :class="['pb-2 text-sm font-medium flex items-center space-x-2 border-b-2 transition-colors', activeTab === 'chat' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']">
                    <MessageSquare class="w-4 h-4" />
                    <span>Topics</span>
                    <span v-if="totalChatUnread > 0" class="bg-indigo-600 text-white py-0.5 px-2 rounded-full text-[10px]">{{ totalChatUnread }}</span>
                </button>
                <button @click="activeTab = 'notifications'" 
                    :class="['pb-2 text-sm font-medium flex items-center space-x-2 border-b-2 transition-colors', activeTab === 'notifications' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']">
                    <Bell class="w-4 h-4" />
                    <span>Updates</span>
                    <span v-if="unreadCount > 0" class="bg-slate-100 text-slate-700 py-0.5 px-2 rounded-full text-[10px]">{{ unreadCount }}</span>
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex-1 flex overflow-hidden bg-slate-50">
            
            <!-- NOTIFICATIONS PANE (Hidden entirely in full screen based on instruction) -->
            <div v-if="!isFullScreen && activeTab === 'notifications'" class="w-full flex flex-col bg-white h-full overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 flex flex-col space-y-3 bg-slate-50/50 shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="flex space-x-2">
                            <button @click="filter = 'all'" :class="['px-3 py-1 rounded-full text-xs font-medium transition-colors', filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-200 text-slate-600 hover:bg-slate-300']">All</button>
                            <button @click="filter = 'unread'" :class="['px-3 py-1 rounded-full text-xs font-medium transition-colors', filter === 'unread' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100']">Unread</button>
                        </div>
                        <div class="flex space-x-2">
                            <select v-model="notificationType" class="text-xs border-slate-300 rounded focus:ring-indigo-500 focus:border-indigo-500 py-1 pl-2 pr-6 bg-white shrink-0 cursor-pointer">
                                <option value="all">All Types</option>
                                <option value="mention">Mentions</option>
                                <option value="task">Tasks</option>
                            </select>
                        </div>
                    </div>
                    <div class="w-full relative">
                        <input v-model="searchQuery" type="text" placeholder="Search notifications..." class="w-full text-xs border-slate-300 rounded focus:ring-indigo-500 focus:border-indigo-500 pl-8 py-1.5 bg-white" />
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <Search class="w-3.5 h-3.5 text-slate-400" />
                        </div>
                    </div>
                </div>
                <!-- Standard Notification List -->
                <div class="flex-1 overflow-y-auto">
                    <!-- Today's Notifications -->
                    <div v-if="groupedNotifications.today.length > 0">
                        <div class="sticky top-0 bg-slate-100/90 backdrop-blur-sm px-5 py-2 z-10 border-b border-slate-200">
                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Today</h3>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="notification in groupedNotifications.today" :key="notification.id"
                                class="p-5 transition-colors group cursor-pointer hover:bg-slate-50"
                                @click="handleNotificationClick(notification)">
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0 mt-1">
                                        <component :is="getIconForType(notification.type)" class="w-5 h-5 text-slate-400" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <p class="text-sm font-semibold text-slate-900 truncate">
                                                <span v-if="notification.task_number" class="text-indigo-600 mr-1">#{{ notification.task_number }}</span>
                                                {{ notification.title }}
                                            </p>
                                            <span class="text-xs text-slate-400 whitespace-nowrap ml-2"><Clock class="w-3 h-3 inline mr-1" />{{ notification.created_at }}</span>
                                        </div>
                                        <p class="text-sm text-slate-600" v-html="formatMessage(notification.message)"></p>
                                    </div>
                                    <div v-if="!notification.isRead" class="w-2 h-2 mt-2 bg-indigo-600 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- This Week's Notifications -->
                    <div v-if="groupedNotifications.thisWeek.length > 0">
                        <div class="sticky top-0 bg-slate-100/90 backdrop-blur-sm px-5 py-2 z-10 border-y border-slate-200">
                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">This Week</h3>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="notification in groupedNotifications.thisWeek" :key="notification.id"
                                class="p-5 transition-colors group cursor-pointer hover:bg-slate-50"
                                @click="handleNotificationClick(notification)">
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0 mt-1">
                                        <component :is="getIconForType(notification.type)" class="w-5 h-5 text-slate-400" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <p class="text-sm font-semibold text-slate-900 truncate">
                                                <span v-if="notification.task_number" class="text-indigo-600 mr-1">#{{ notification.task_number }}</span>
                                                {{ notification.title }}
                                            </p>
                                            <span class="text-xs text-slate-400 whitespace-nowrap ml-2"><Clock class="w-3 h-3 inline mr-1" />{{ notification.created_at }}</span>
                                        </div>
                                        <p class="text-sm text-slate-600" v-html="formatMessage(notification.message)"></p>
                                    </div>
                                    <div v-if="!notification.isRead" class="w-2 h-2 mt-2 bg-indigo-600 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Older Notifications -->
                    <div v-if="groupedNotifications.older.length > 0">
                        <div class="sticky top-0 bg-slate-100/90 backdrop-blur-sm px-5 py-2 z-10 border-y border-slate-200">
                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Older</h3>
                        </div>
                        <div class="divide-y divide-slate-100 mb-10">
                            <div v-for="notification in groupedNotifications.older" :key="notification.id"
                                class="p-5 transition-colors group cursor-pointer hover:bg-slate-50"
                                @click="handleNotificationClick(notification)">
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0 mt-1">
                                        <component :is="getIconForType(notification.type)" class="w-5 h-5 text-slate-400" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <p class="text-sm font-semibold text-slate-900 truncate">
                                                <span v-if="notification.task_number" class="text-indigo-600 mr-1">#{{ notification.task_number }}</span>
                                                {{ notification.title }}
                                            </p>
                                            <span class="text-xs text-slate-400 whitespace-nowrap ml-2"><Clock class="w-3 h-3 inline mr-1" />{{ notification.created_at }}</span>
                                        </div>
                                        <p class="text-sm text-slate-600" v-html="formatMessage(notification.message)"></p>
                                    </div>
                                    <div v-if="!notification.isRead" class="w-2 h-2 mt-2 bg-indigo-600 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="groupedNotifications.today.length === 0 && groupedNotifications.thisWeek.length === 0 && groupedNotifications.older.length === 0" class="text-center text-slate-500 py-10 text-sm">
                        No notifications found.
                    </div>
                </div>
            </div>

            <!-- CHAT / TELEGRAM SYSTEM PANE -->
            <div v-if="isFullScreen || activeTab === 'chat'" class="flex-1 flex w-full h-full overflow-hidden">
                
                <!-- SIDEBAR 1: PROJECTS -->
                <div :class="['bg-slate-100 border-r border-slate-200 flex flex-col', isFullScreen ? 'w-64' : 'w-20 sm:w-16']" style="min-width: 64px;">
                    <div v-if="isFullScreen" class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                        <input v-model="projectSearch" type="text" placeholder="Filter projects..." class="w-full text-xs border-slate-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div class="flex-1 overflow-y-auto py-2 flex flex-col items-center sm:items-stretch">
                        <button v-for="p in availableContexts" :key="p.id" @click="switchProject(p)"
                            :class="['w-full sm:px-3 px-0 py-3 flex flex-col sm:flex-row items-center sm:justify-start justify-center transition-colors border-l-4',
                                activeProject?.id === p.id ? 'bg-white border-indigo-600 shadow-sm' : 'border-transparent hover:bg-slate-200/50'
                            ]"
                            :title="p.name">
                            <div :class="['w-8 h-8 rounded shrink-0 flex items-center justify-center text-white text-xs font-bold uppercase shadow-sm sm:mr-3 mr-0', p.color]">
                                {{ p.name.substring(0,2) }}
                            </div>
                            <div v-if="isFullScreen" class="text-left flex-1 min-w-0 hidden sm:block">
                                <span class="block text-sm font-medium text-slate-700 truncate" :class="{'text-indigo-700 font-bold': activeProject?.id === p.id}">{{ p.name }}</span>
                            </div>
                            <span v-if="p.unreadCount > 0 && isFullScreen" class="ml-auto bg-indigo-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full hidden sm:block">{{ p.unreadCount }}</span>
                            <span v-else-if="p.unreadCount > 0" class="absolute right-1 top-1 bg-indigo-600 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full sm:hidden">{{ p.unreadCount }}</span>
                        </button>
                    </div>
                </div>

                <!-- SIDEBAR 2: TOPICS -->
                <div :class="['bg-white border-r border-slate-200 flex flex-col', isFullScreen ? 'w-72' : 'w-48']">
                    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between bg-slate-50 shrink-0">
                        <h3 class="text-sm font-bold text-slate-800 truncate">{{ activeProject?.name || 'Select Project' }}</h3>
                        <button @click="isCreatingTopic = !isCreatingTopic" class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 p-1 rounded transition-colors" title="New Topic">
                            <Plus class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Create Topic Inline Form -->
                    <div v-if="isCreatingTopic" class="p-3 bg-white border-b border-indigo-100 shadow-sm z-10">
                        <p class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider mb-2">New Telegram Topic</p>
                        <input v-model="newTopicName" @keyup.enter="createNewTopic" type="text" placeholder="e.g. Budget Discuss..." class="w-full text-xs box-border border-slate-300 rounded focus:ring-indigo-500 focus:border-indigo-500 mb-2">
                        <div class="flex justify-end space-x-2">
                            <button @click="isCreatingTopic = false" class="px-2 py-1 text-xs text-slate-500 hover:text-slate-800">Cancel</button>
                            <button @click="createNewTopic" :disabled="!newTopicName.trim() || isActionLoading" class="px-3 py-1 bg-indigo-600 text-white text-xs font-semibold rounded hover:bg-indigo-700 disabled:opacity-50 transition-colors">Create</button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto bg-white p-2 space-y-0.5">
                        <div v-if="loadingTopics" class="p-4 flex justify-center text-indigo-500">
                            <div class="animate-spin rounded-full h-4 w-4 border-2 border-indigo-500 border-t-transparent"></div>
                        </div>
                        <template v-else>
                            <button v-for="topic in topics" :key="topic.id" @click="switchTopic(topic)"
                                :class="['w-full group px-3 py-2 rounded-md flex items-center text-left transition-colors',
                                    activeTopic?.id === topic.id ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100'
                                ]">
                                <span class="text-slate-400 mr-2 group-hover:text-indigo-400">
                                    <Hash class="w-4 h-4" />
                                </span>
                                <span class="text-sm font-medium truncate shrink min-w-0" :class="{'font-bold': activeTopic?.id === topic.id}">{{ topic.name }}</span>
                            </button>
                            <div v-if="!topics.length && !loadingTopics" class="text-xs text-center text-slate-400 mt-10">No topics found.<br/>Click + to link a telegram thread.</div>
                        </template>
                    </div>
                </div>

                <!-- MAIN AREA: CHAT -->
                <div class="flex-1 flex flex-col bg-[#F8FAFC] min-w-0">
                    <!-- Chat Header -->
                    <div v-if="activeTopic" class="px-5 py-3 border-b border-slate-200 bg-white shadow-sm flex items-center shrink-0">
                        <Hash class="w-5 h-5 text-indigo-400 mr-2" />
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">{{ activeTopic.name }}</h3>
                            <p class="text-[10px] text-slate-500">Linked to Telegram Thread</p>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div ref="chatContainer" @scroll="handleScroll" @click="handleChatClick" class="flex-1 overflow-y-auto p-5 space-y-6">
                        <div v-if="!activeTopic && projects.length" class="h-full flex flex-col items-center justify-center text-slate-400">
                            <MessageSquare class="w-12 h-12 mb-3 text-slate-200" />
                            <p class="text-sm">Select a project and a topic to start chatting.</p>
                        </div>

                        <div v-if="loadingMore" class="flex justify-center py-2">
                            <div class="animate-spin rounded-full h-4 w-4 border-2 border-indigo-500 border-t-transparent"></div>
                        </div>

                        <div v-for="msg in chatMessages" :key="msg.id" :id="`msg-${msg.id}`" 
                            class="flex flex-col space-y-1 group/msg max-w-[85%] relative"
                            :class="[msg.is_me ? 'ml-auto items-end' : 'mr-auto items-start']">
                            
                            <div class="flex items-baseline space-x-2 mb-0.5 px-1" :class="[msg.is_me ? 'flex-row-reverse space-x-reverse' : '']">
                                <span class="text-xs font-semibold text-slate-700">{{ msg.user }}</span>
                                <span class="text-[10px] text-slate-400">{{ msg.time }}</span>
                            </div>
                            
                            <!-- Reply action overlay -->
                            <div v-if="msg.type !== 'email'" 
                                class="absolute top-4 opacity-0 group-hover/msg:opacity-100 transition-opacity"
                                :class="[msg.is_me ? '-left-8' : '-right-8']">
                                <button @click="replyToMessage = msg"
                                    class="p-1.5 text-slate-400 bg-white hover:text-indigo-600 shadow-sm rounded-full border border-slate-200 transition-all">
                                    <CornerDownRight class="w-3.5 h-3.5" />
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
                                    class="px-3 py-1.5 rounded-lg text-xs cursor-pointer truncate max-w-full opacity-75 hover:opacity-100 transition-opacity mb-1"
                                    :class="[msg.is_me ? 'bg-indigo-50 text-indigo-700 text-right mr-1' : 'bg-slate-200 text-slate-600 text-left ml-1']">
                                    <span class="font-bold block text-[10px] uppercase mb-0.5">{{ msg.parent.user }}</span>
                                    <span class="opacity-80">{{ msg.parent.message }}</span>
                                </div>

                                <div class="px-4 py-2.5 rounded-2xl text-sm leading-relaxed shadow-sm break-words max-w-full"
                                     :class="[msg.is_me ? 'bg-indigo-600 text-white rounded-tr-sm' : 'bg-white border text-slate-700 border-slate-100 rounded-tl-sm']"
                                     v-html="formatMessage(msg.message)">
                                </div>
                                <div v-if="msg.is_me" class="flex items-center pr-1 h-3 mt-0.5">
                                    <CheckCheck v-if="msg.reads && msg.reads.length" class="w-3.5 h-3.5 text-indigo-500" :title="msg.reads.map(r => r.user).join(', ')" />
                                    <CheckCheck v-else class="w-3.5 h-3.5 text-slate-300" title="Sent" />
                                </div>
                            </template>
                        </div>
                        <div v-if="chatMessages.length === 0 && activeTopic && !loadingChat" class="text-center text-sm text-slate-400 py-10">
                            No messages in this topic yet.
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div v-if="activeTopic" class="p-4 bg-white border-t border-slate-200 shrink-0">
                        <div v-if="replyToMessage" class="mb-2 bg-slate-50 border-l-4 border-indigo-500 p-2 rounded-r flex items-center justify-between mx-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider">Replying to {{ replyToMessage.user }}</p>
                                <p class="text-xs text-slate-600 truncate opacity-80 mt-0.5">{{ replyToMessage.message }}</p>
                            </div>
                            <button @click="replyToMessage = null" class="p-1.5 text-slate-400 hover:bg-slate-200 rounded-md ml-2 transition-colors">
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div class="relative flex items-end gap-2 bg-slate-50 border border-slate-200 rounded-xl p-1 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition-all shadow-sm">
                            <MentionInput
                                ref="mentionInputRef"
                                v-model="newMessage"
                                :project-id="activeProject?.id"
                                type="textarea"
                                placeholder="Message to topic..."
                                @submit="handleSendMessage"
                                class="flex-1 bg-transparent !border-none !shadow-none !ring-0 !min-h-[44px] pt-[12px]"
                            />
                            <button @click="handleSendMessage" :disabled="!newMessage.trim()"
                                class="m-1 p-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 shadow-sm disabled:opacity-50 transition-colors flex-shrink-0 flex items-center justify-center">
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
/* Scoped css if any */
</style>
