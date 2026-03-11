<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
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
    Maximize,
    Minimize,
    CornerDownRight,
    Mail,
    ExternalLink
} from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { openTaskDetailSidebar } from '@/Utils/sidebar';
import { 
    notificationSidebarState, 
    closeNotificationsSidebar,
    markNotificationAndRefetch,
    markAllNotificationsAsRead
} from '@/Utils/notification-sidebar';
import { formatMentions } from '@/Utils/mentions';
import MentionInput from '@/Components/ProjectTasks/MentionInput.vue';

const props = defineProps({
    // Initial data can be passed if needed, but we mostly pull from stores
});

const isFullScreen = ref(false);
const activeTab = ref('notifications'); // 'notifications' or 'chat'
const filter = ref('unread'); // 'all', 'unread'
const showContextDropdown = ref(false);
const expandedGroups = ref({});
const replyingTo = ref(null);
const replyText = ref('');
const newMessage = ref('');
const chatMessages = ref([]);
const loadingChat = ref(false);
const projects = ref([]);
const activeProject = ref(null);
const chatContainer = ref(null);
const mentionInputRef = ref(null);
const loadingMore = ref(false);
const hasMore = ref(true);

const user = computed(() => usePage().props.auth.user);
const notifications = computed(() => notificationSidebarState.value.notifications);
const unreadCount = computed(() => notifications.value.filter(n => !n.isRead).length);

// In a real app, we might want unread counts per project
const availableContexts = computed(() => {
    return projects.value.map(p => ({
        id: p.id,
        type: 'Project',
        name: p.name,
        color: 'bg-indigo-500', // Dynamic if available
        unreadCount: 0 // Fetch from meta if needed
    }));
});

const filteredNotifications = computed(() => {
    if (filter.value === 'unread') return notifications.value.filter(n => !n.isRead);
    return notifications.value;
});

const fetchProjects = async () => {
    try {
        const response = await axios.get('/api/projects-simplified');
        projects.value = response.data;
        if (projects.value.length > 0) {
            const currentProjectId = usePage().props.id || usePage().props.project?.id;
            activeProject.value = projects.value.find(p => p.id == currentProjectId) || projects.value[0];
            fetchChatMessages();
        }
    } catch (error) {
        console.error('Error fetching projects:', error);
    }
};

const handleNotificationClick = async (notification) => {
    await markNotificationAndRefetch(notification.view_id);
    
    if (notification.task_id && notification.project_id) {
        openTaskDetailSidebar(notification.task_id, notification.project_id);
        closeNotificationsSidebar();
    }
};

const fetchChatMessages = async (isLoadMore = false) => {
    if (!activeProject.value || (isLoadMore && (!hasMore.value || loadingMore.value))) return;
    
    if (isLoadMore) {
        loadingMore.value = true;
    } else {
        loadingChat.value = true;
        hasMore.value = true; // Reset for new project
    }

    try {
        const oldestTimestamp = isLoadMore && chatMessages.value.length > 0 
            ? chatMessages.value[0].created_at 
            : null;

        const response = await axios.get(`/api/projects/${activeProject.value.id}/chat`, {
            params: { before: oldestTimestamp }
        });

        const newMessages = response.data;
        
        if (isLoadMore) {
            if (newMessages.length > 0) {
                // Prepend older messages
                const currentHeight = chatContainer.value?.scrollHeight || 0;
                chatMessages.value = [...newMessages, ...chatMessages.value];
                
                // Maintain scroll position
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

        // If we got fewer results than a standard page size (e.g., 20), assume no more
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
    // If scrolled to top and there is more to load
    if (scrollTop < 50 && hasMore.value && !loadingMore.value && !loadingChat.value) {
        fetchChatMessages(true);
    }
};

const handleSendMessage = async () => {
    if (!newMessage.value.trim() || !activeProject.value) return;
    
    const messageContent = newMessage.value;
    newMessage.value = '';

    try {
        const response = await axios.post(`/api/projects/${activeProject.value.id}/chat`, {
            message: messageContent
        });
        
        // Optimistically add or wait for broadcast. Here we wait for response.
        const msg = {
            id: response.data.id,
            type: 'text',
            user: user.value.name,
            initials: user.value.name.substring(0, 2).toUpperCase(),
            color: 'bg-indigo-600',
            message: response.data.message,
            time: 'Just now',
            is_me: true,
            created_at: response.data.created_at
        };
        chatMessages.value.push(msg);
        newMessage.value = '';
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

const switchProject = (project) => {
    activeProject.value = project;
    showContextDropdown.value = false;
    fetchChatMessages();
};

const toggleGroup = (id) => {
    expandedGroups.value[id] = !expandedGroups.value[id];
};

const markAllAsRead = async () => {
    await markAllNotificationsAsRead();
};

const getIconForType = (type) => {
    if (type.includes('Task')) return Calendar;
    return Bell;
};

onMounted(() => {
    fetchProjects();
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
            isFullScreen ? 'inset-0 w-full' : 'inset-y-0 right-0 w-full sm:w-[450px] border-l border-slate-200'
        ]">

        <!-- Sidebar Header -->
        <div class="px-5 pt-5 pb-0 border-b border-slate-200 bg-white">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-slate-800">
                    {{ isFullScreen ? 'Communication Center' : 'Inbox' }}
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

            <!-- Tabs -->
            <div v-if="!isFullScreen" class="flex space-x-6">
                <button @click="activeTab = 'notifications'" 
                    :class="['pb-3 text-sm font-medium flex items-center space-x-2 border-b-2 transition-colors', activeTab === 'notifications' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700']">
                    <Bell class="w-4 h-4" />
                    <span>Updates</span>
                    <span v-if="unreadCount > 0" class="bg-blue-100 text-blue-700 py-0.5 px-2 rounded-full text-xs">{{ unreadCount }}</span>
                </button>
                <button @click="activeTab = 'chat'" 
                    :class="['pb-3 text-sm font-medium flex items-center space-x-2 border-b-2 transition-colors', activeTab === 'chat' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700']">
                    <MessageSquare class="w-4 h-4" />
                    <span>Team Chat</span>
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex-1 flex overflow-hidden">
            <!-- NOTIFICATIONS PANE -->
            <div v-if="isFullScreen || activeTab === 'notifications'" 
                :class="['flex flex-col bg-white overflow-hidden', isFullScreen ? 'w-1/3 border-r border-slate-200' : 'w-full']">
                
                <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex space-x-2">
                        <button @click="filter = 'all'" :class="['px-3 py-1 rounded-full text-xs font-medium transition-colors', filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-200 text-slate-600 hover:bg-slate-300']">All</button>
                        <button @click="filter = 'unread'" :class="['px-3 py-1 rounded-full text-xs font-medium transition-colors', filter === 'unread' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100']">Unread</button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                    <div v-for="notification in filteredNotifications" :key="notification.id"
                        class="p-5 transition-colors group cursor-pointer hover:bg-slate-50"
                        @click="handleNotificationClick(notification)">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 mt-1">
                                <component :is="getIconForType(notification.type)" class="w-5 h-5 text-slate-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ notification.title }}</p>
                                    <span class="text-xs text-slate-400 whitespace-nowrap ml-2"><Clock class="w-3 h-3 inline mr-1" />{{ notification.created_at }}</span>
                                </div>
                                <p class="text-sm text-slate-600">{{ notification.message }}</p>
                                
                                <div v-if="notification.count > 1" class="mt-2 text-left">
                                    <button @click.stop="toggleGroup(notification.id)" class="text-xs font-medium text-emerald-600 flex items-center bg-emerald-50 px-2 py-1 rounded hover:bg-emerald-100 transition-colors">
                                        <ChevronDown :class="['w-3 h-3 mr-1 transition-transform', expandedGroups[notification.id] ? 'rotate-180' : '']" />
                                        {{ expandedGroups[notification.id] ? 'Hide' : 'Show' }} {{ notification.count - 1 }} more updates
                                    </button>

                                    <!-- Grouped Updates -->
                                    <div v-if="expandedGroups[notification.id]" class="mt-3 space-y-3 border-l-2 border-slate-100 pl-4 py-1">
                                        <div v-for="(update, idx) in notification.updates" :key="idx" 
                                            class="group/update cursor-pointer"
                                            @click.stop="handleNotificationClick({ ...update, view_id: notification.view_id })">
                                            <div class="flex items-center justify-between mb-0.5">
                                                <p class="text-[13px] font-medium text-slate-700 group-hover/update:text-blue-600 transition-colors">{{ update.title }}</p>
                                                <span v-if="update.created_at" class="text-[10px] text-slate-400 whitespace-nowrap ml-2">{{ update.created_at }}</span>
                                            </div>
                                            <p class="text-xs text-slate-500 line-clamp-1">{{ update.message }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!notification.isRead" class="w-2 h-2 mt-2 bg-blue-600 rounded-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHAT PANE -->
            <div v-if="isFullScreen || activeTab === 'chat'" 
                :class="['flex flex-col bg-slate-50 overflow-hidden', isFullScreen ? 'w-2/3' : 'w-full']">
                
                <!-- Project Selector -->
                <div class="relative z-20">
                    <button @click="showContextDropdown = !showContextDropdown" 
                        class="w-full px-5 py-3 bg-white border-b border-slate-200 flex items-center justify-between hover:bg-slate-50 transition-colors">
                        <div v-if="activeProject" class="text-left">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Project</span>
                            <p class="text-sm font-medium text-slate-800 flex items-center mt-0.5">
                                <span class="w-2 h-2 rounded-full bg-indigo-500 mr-2"></span>
                                {{ activeProject.name }}
                            </p>
                        </div>
                        <ChevronDown :class="['w-4 h-4 text-slate-400 transition-transform', showContextDropdown ? 'rotate-180' : '']" />
                    </button>

                    <div v-if="showContextDropdown" class="absolute top-full left-0 w-full bg-white border-b border-slate-200 shadow-lg max-h-60 overflow-y-auto">
                        <button v-for="ctx in availableContexts" :key="ctx.id" @click="switchProject(ctx)"
                            class="w-full px-5 py-3 flex items-center hover:bg-blue-50 border-t border-slate-100 transition-colors">
                            <span :class="['w-2 h-2 rounded-full mr-3', ctx.color]"></span>
                            <div class="text-left flex-1">
                                <span class="block text-xs text-slate-400">{{ ctx.type }}</span>
                                <span class="block text-sm font-medium text-slate-700">{{ ctx.name }}</span>
                            </div>
                            <Check v-if="activeProject?.id === ctx.id" class="w-4 h-4 text-blue-600" />
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div ref="chatContainer" @scroll="handleScroll" class="flex-1 overflow-y-auto p-5 space-y-6">
                    <!-- Loading Spinner for older messages -->
                    <div v-if="loadingMore" class="flex justify-center py-2">
                        <div class="animate-spin rounded-full h-4 w-4 border-2 border-indigo-500 border-t-transparent"></div>
                    </div>

                    <div v-for="msg in chatMessages" :key="msg.id" class="flex items-start space-x-3">
                        <div :class="['flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold', msg.color]">
                            {{ msg.initials }}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-baseline space-x-2">
                                <span class="text-sm font-semibold text-slate-900">{{ msg.user }}</span>
                                <span class="text-xs text-slate-400">{{ msg.time }}</span>
                            </div>
                            
                            <template v-if="msg.type === 'email'">
                                <div class="mt-1 bg-slate-100/80 border border-slate-200 p-3 rounded-lg shadow-sm w-full max-w-md">
                                    <div class="flex items-center text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wider">
                                        <Mail class="w-3 h-3 mr-1.5" />
                                        {{ msg.direction === 'inbound' ? 'Received' : 'Sent' }}
                                    </div>
                                    <p class="text-sm text-slate-700 italic border-l-2 border-slate-300 pl-3 py-1">"{{ msg.summary }}"</p>
                                </div>
                            </template>
                            <template v-else>
                                <p class="text-sm text-slate-700 mt-1 leading-relaxed bg-white p-3 rounded-lg shadow-sm border border-slate-100 inline-block"
                                   v-html="formatMessage(msg.message)">
                                </p>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="p-4 bg-white border-t border-slate-200">
                    <div class="relative flex items-end gap-2">
                        <MentionInput
                            ref="mentionInputRef"
                            v-model="newMessage"
                            :project-id="activeProject?.id"
                            type="textarea"
                            placeholder="Type a message or use @ to mention..."
                            @submit="handleSendMessage"
                            class="flex-1"
                        />
                        <button @click="handleSendMessage" :disabled="!newMessage.trim()"
                            class="mb-1 p-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 transition-colors flex-shrink-0">
                            <Send class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</teleport>
</template>
