import { ref } from 'vue';
import * as notification from '@/Utils/notification.js';

export function useEmbeddedScheduler() {
  const showScheduleModal = ref(false);
  const scheduleDraft = ref(null); // holds the raw form payload from SchedulePickerModal

  function open() {
    showScheduleModal.value = true;
  }
  function close() {
    showScheduleModal.value = false;
  }

  function onSaveDraft(payload) {
    scheduleDraft.value = { ...payload };
    showScheduleModal.value = false;
  }

  async function attachAfterCreate(modelType, modelId) {
    if (!scheduleDraft.value) return null;
    try {
      const payload = {
        ...scheduleDraft.value,
        scheduled_item_type: modelType,
        scheduled_item_id: modelId,
      };
      const { data } = await window.axios.post('/api/schedules', payload);
      notification.success('Schedule created');
      scheduleDraft.value = null;
      return data;
    } catch (e) {
      console.error('Failed to create schedule', e);
      notification.error(e?.response?.data?.message || 'Failed to create schedule');
      return null;
    }
  }

  return {
    showScheduleModal,
    scheduleDraft,
    open,
    close,
    onSaveDraft,
    attachAfterCreate,
  };
}
