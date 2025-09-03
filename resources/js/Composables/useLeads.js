import { ref, reactive, computed } from 'vue';
import axios from 'axios';

export function useLeads() {
  // State
  const leads = ref([]);
  const loading = ref(false);
  const generalError = ref('');

  // Pagination
  const currentPage = ref(1);
  const perPage = ref(15);
  const total = ref(0);
  const lastPage = ref(1);

  // Filters
  const filters = reactive({
    q: '',
    status: '',
    source: '',
    assigned_to_id: ''
  });

  // Users (for assignment)
  const users = ref([]);

  // Fetch users once (for dropdowns / cards)
  const fetchUsers = async () => {
    try {
      const { data } = await axios.get('/api/users');
      users.value = data;
    } catch (e) {
      console.error('Failed to load users', e);
    }
  };

  // Fetch leads with pagination & filters
  const fetchLeads = async () => {
    loading.value = true;
    generalError.value = '';
    try {
      const params = {
        page: currentPage.value,
        per_page: perPage.value,
      };
      if (filters.q) params.q = filters.q;
      if (filters.status) params.status = filters.status;
      if (filters.source) params.source = filters.source;
      if (filters.assigned_to_id) params.assigned_to_id = filters.assigned_to_id;

      const { data } = await axios.get('/api/leads', { params });

      leads.value = data.data ?? data;
      const meta = data.meta ?? null;
      if (meta) {
        currentPage.value = meta.current_page;
        perPage.value = meta.per_page;
        total.value = meta.total;
        lastPage.value = meta.last_page;
      } else {
        total.value = Array.isArray(leads.value) ? leads.value.length : 0;
        lastPage.value = 1;
      }
    } catch (error) {
      console.error('Error fetching leads', error);
      generalError.value = error?.response?.data?.message || 'Failed to fetch leads.';
      if (error.response && error.response.status === 401) {
        localStorage.removeItem('authToken');
        window.location.href = '/login';
      }
    } finally {
      loading.value = false;
    }
  };

  const changePage = async (page) => {
    if (page < 1 || page > lastPage.value) return;
    currentPage.value = page;
    await fetchLeads();
  };

  const resetFilters = async () => {
    filters.q = '';
    filters.status = '';
    filters.source = '';
    filters.assigned_to_id = '';
    currentPage.value = 1;
    await fetchLeads();
  };

  // CRUD
  const createLead = async (payload) => {
    generalError.value = '';
    const { data } = await axios.post('/api/leads', payload);
    await fetchLeads();
    return data;
  };

  const updateLead = async (id, payload) => {
    generalError.value = '';
    const { data } = await axios.put(`/api/leads/${id}`, payload);
    await fetchLeads();
    return data;
  };

  const deleteLead = async (id) => {
    generalError.value = '';
    await axios.delete(`/api/leads/${id}`);
    await fetchLeads();
  };

  // Derived: leads grouped by status for Kanban
  const leadsByStatus = computed(() => {
    const groups = { new: [], contacted: [], qualified: [], converted: [], lost: [] };
    for (const lead of leads.value) {
      const key = (lead.status || 'new').toLowerCase();
      if (!groups[key]) groups[key] = [];
      groups[key].push(lead);
    }
    return groups;
  });

  return {
    // state
    leads, loading, generalError,
    currentPage, perPage, total, lastPage,
    filters, users,
    leadsByStatus,
    // actions
    fetchUsers, fetchLeads, changePage, resetFilters,
    createLead, updateLead, deleteLead,
  };
}
