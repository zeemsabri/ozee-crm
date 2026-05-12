import { ref, computed } from 'vue';
import axios from 'axios';

export function useClientDetails(idRef) {
  const loading = ref(false);
  const error = ref('');
  const client = ref(null);
  const lead = ref(null);
  const presentations = ref([]);
  const emails = ref([]);
  const xeroLogs = ref([]);

  // Notes
  const notes = ref([]);
  const notesLoading = ref(false);
  const notesError = ref('');
  const savingNote = ref(false);

  const fullName = computed(() => {
    if (!client.value) return '';
    return client.value.name || client.value.email || 'Client';
  });

  const fetchClientDetails = async () => {
    if (!idRef?.value) return;
    loading.value = true;
    error.value = '';
    try {
      const { data } = await axios.get(`/api/clients/${idRef.value}/details`);
      client.value = data.client;
      lead.value = data.lead;
      presentations.value = data.presentations || [];
      emails.value = data.emails || [];
      xeroLogs.value = data.xero_logs || [];
    } catch (e) {
      console.error('Failed to load client details', e);
      error.value = e?.response?.data?.message || 'Failed to load client details';
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
          noteable_type: 'App\\Models\\Client',
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
        noteable_type: 'App\\Models\\Client',
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
    loading, error, client, lead, fullName, presentations, emails,
    notes, notesLoading, notesError, savingNote, xeroLogs,
    // actions
    fetchClientDetails, fetchNotes, addNote,
  };
}
