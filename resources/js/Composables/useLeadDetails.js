import { ref, computed } from 'vue';
import axios from 'axios';

export function useLeadDetails(idRef) {
  const loading = ref(false);
  const error = ref('');
  const lead = ref(null);

  // Notes
  const notes = ref([]);
  const notesLoading = ref(false);
  const notesError = ref('');
  const savingNote = ref(false);

  const fullName = computed(() => {
    if (!lead.value) return '';
    const fn = lead.value.first_name || '';
    const ln = lead.value.last_name || '';
    const name = `${fn} ${ln}`.trim();
    return name || lead.value.email || 'Lead';
  });

  const fetchLead = async () => {
    if (!idRef?.value) return;
    loading.value = true;
    error.value = '';
    try {
      const { data } = await axios.get(`/api/leads/${idRef.value}`);
      lead.value = data;
    } catch (e) {
      console.error('Failed to load lead', e);
      error.value = e?.response?.data?.message || 'Failed to load lead';
    } finally {
      loading.value = false;
    }
  };

  const fetchNotes = async () => {
    if (!idRef?.value) return;
    notesLoading.value = true;
    notesError.value = '';
    try {
      const { data } = await axios.get('/api/project_notes', {
        params: {
          noteable_id: idRef.value,
          noteable_type: 'App\\Models\\Lead',
        },
      });
      notes.value = Array.isArray(data) ? data : (data?.data ?? []);
    } catch (e) {
      console.error('Failed to load notes', e);
      notesError.value = e?.response?.data?.message || 'Failed to load notes';
    } finally {
      notesLoading.value = false;
    }
  };

  const addNote = async (body) => {
    if (!idRef?.value || !body || !body.trim()) return;
    try {
      savingNote.value = true;
      await axios.post('/api/project_notes', {
        body,
        noteable_id: idRef.value,
        noteable_type: 'App\\Models\\Lead',
      });
      await fetchNotes();
    } catch (e) {
      console.error('Failed to save note', e);
      notesError.value = e?.response?.data?.message || 'Failed to save note';
      throw e;
    } finally {
      savingNote.value = false;
    }
  };

  return {
    // state
    loading, error, lead, fullName,
    notes, notesLoading, notesError, savingNote,
    // actions
    fetchLead, fetchNotes, addNote,
  };
}
