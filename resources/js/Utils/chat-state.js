import { computed, ref } from 'vue';

export const unreadByProjectState = ref({});

export const totalChatUnreadCount = computed(() => {
    return Object.values(unreadByProjectState.value).reduce((sum, count) => sum + Number(count || 0), 0);
});

export const fetchChatUnreadCounts = async () => {
    try {
        const { data } = await window.axios.get('/api/chat/unread-counts');
        unreadByProjectState.value = data || {};
    } catch (error) {
        console.error('Failed to fetch chat unread counts:', error);
    }
};

export const clearProjectChatUnread = (projectId) => {
    if (!projectId) {
        return;
    }

    unreadByProjectState.value = {
        ...unreadByProjectState.value,
        [projectId]: 0,
    };
};

export const incrementProjectChatUnread = (projectId) => {
    if (!projectId) {
        return;
    }

    unreadByProjectState.value = {
        ...unreadByProjectState.value,
        [projectId]: Number(unreadByProjectState.value[projectId] || 0) + 1,
    };
};
