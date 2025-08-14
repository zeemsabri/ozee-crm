// src/Utils/useNotices.js

import { ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const showNoticeModal = ref(false);
const unreadNotices = ref([]);
const acknowledgeChecked = ref(false);
let noticeIntervalId = null;

// The main composable function
export function useNotices() {

    const fetchUnreadNotices = async () => {
        try {
            const res = await axios.get('/api/notices/unread');
            unreadNotices.value = res.data?.data || [];
            showNoticeModal.value = unreadNotices.value.length > 0;
        } catch (e) {
            console.error('Failed to fetch unread notices', e);
        }
    };

    const acknowledgeNotices = async () => {
        if (!acknowledgeChecked.value) return;
        try {
            const ids = unreadNotices.value.map(n => n.id);
            if (ids.length === 0) return;
            await axios.post('/api/notices/acknowledge', { notice_ids: ids });
            closeModal();
        } catch (e) {
            console.error('Failed to acknowledge notices', e);
        }
    };

    const closeModal = () => {
        showNoticeModal.value = false;
        unreadNotices.value = [];
        acknowledgeChecked.value = false;
    };

    onMounted(() => {
        fetchUnreadNotices();
        // Start polling for new notices every minute
        // noticeIntervalId = setInterval(fetchUnreadNotices, 60 * 1000);
    });

    onBeforeUnmount(() => {
        if (noticeIntervalId) clearInterval(noticeIntervalId);
    });

    return {
        showNoticeModal,
        unreadNotices,
        acknowledgeChecked,
        fetchUnreadNotices,
        acknowledgeNotices,
        closeModal,
    };
}
