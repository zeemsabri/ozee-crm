import { ref, reactive, computed } from 'vue';
import axios from 'axios';

export function useLeads() {
  // State
  const leads = ref([]);
  const loading = ref(false);
  const generalError = ref('');

  // Pagination
  const currentPage = ref(1);
  const perPage = ref(1000); // Increased to 1000 to show 'all' leads at once as requested
  const total = ref(0);
  const lastPage = ref(1);

  // Filters
  const filters = reactive({
    q: '',
    status: '',
    source: '',
    assigned_to_id: '',
    campaign_ids: []
  });

  const enquiryStatuses = ['pending_quote', 'quoted', 'approved', 'rejected', 'converted_to_service'];

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
      if (filters.campaign_ids && filters.campaign_ids.length) params.campaign_ids = filters.campaign_ids.join(',');

      const [leadResponse, enquiryResponse] = await Promise.all([
        axios.get('/api/leads', { params }),
        (filters.campaign_ids && filters.campaign_ids.length)
          ? Promise.resolve({ data: { data: [], meta: { current_page: 1, per_page: perPage.value, total: 0, last_page: 1 } } })
          : axios.get('/api/existing-client-enquiries', { params }),
      ]);

      const leadItems = leadResponse.data.data ?? leadResponse.data ?? [];
      const enquiryItems = enquiryResponse.data.data ?? enquiryResponse.data ?? [];

      leads.value = [...leadItems, ...enquiryItems].sort((left, right) => {
        const leftValue = new Date(left.updated_at || left.created_at || 0).getTime();
        const rightValue = new Date(right.updated_at || right.created_at || 0).getTime();
        return rightValue - leftValue;
      });

      currentPage.value = 1;
      perPage.value = params.per_page;
      total.value = leads.value.length;
      lastPage.value = 1;
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
    filters.campaign_ids = [];
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

  const createExistingClientEnquiry = async (payload) => {
    generalError.value = '';
    const { data } = await axios.post('/api/existing-client-enquiries', payload);
    await fetchLeads();
    return data.data ?? data;
  };

  const updateExistingClientEnquiry = async (id, payload) => {
    generalError.value = '';
    const { data } = await axios.put(`/api/existing-client-enquiries/${id}`, payload);
    await fetchLeads();
    return data.data ?? data;
  };

  const convertExistingClientEnquiry = async (item, payload) => {
    generalError.value = '';
    const enquiryId = item?.enquiry_id || item?.id;
    const projectId = item?.project_id || item?.metadata?.project_id;
    const { data } = await axios.post(`/api/existing-client-enquiries/${enquiryId}/convert`, {
      project_id: projectId,
      ...payload,
    });
    await fetchLeads();
    return data;
  };

  const deleteLead = async (id) => {
    generalError.value = '';
    await axios.delete(`/api/leads/${id}`);
    await fetchLeads();
  };

  const deleteCard = async (item) => {
    if (item?.card_type === 'existing_client_enquiry') {
      generalError.value = '';
      const enquiryId = item?.enquiry_id || item?.id;
      const projectId = item?.project_id || item?.metadata?.project_id;
      await axios.delete(`/api/existing-client-enquiries/${enquiryId}`, { data: { project_id: projectId } });
      await fetchLeads();
      return;
    }

    await deleteLead(item.id);
  };

  const updateCard = async (item, payload) => {
    if (item?.card_type === 'existing_client_enquiry') {
      const enquiryId = item?.enquiry_id || item?.id;
      const projectId = item?.project_id || item?.metadata?.project_id;
      return updateExistingClientEnquiry(enquiryId, {
        project_id: projectId,
        ...payload,
      });
    }

    return updateLead(item.id ?? item, payload);
  };

  // Derived: leads grouped by status for Kanban
  const leadsByStatus = computed(() => {
    const groups = {
        new: [],
        processing: [],
        contacted: [],
        outreach_sent: [],
        qualified: [],
        sequence_completed: [],
        generation_failed: [],
        converted: [],
        lost: [],
        pending_quote: [],
        quoted: [],
        approved: [],
        rejected: [],
        converted_to_service: [],
    };
    for (const lead of leads.value) {
      let key = (lead.status || 'new').toLowerCase();
      
      // Group hot_incoming and hot_outgoing with 'new' as per user request
      if (key === 'hot_incoming' || key === 'hot_outgoing') {
        key = 'new';
      }

      if (lead.card_type === 'existing_client_enquiry' && !enquiryStatuses.includes(key)) {
        key = 'pending_quote';
      }
      
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
    createExistingClientEnquiry, updateExistingClientEnquiry, convertExistingClientEnquiry,
    deleteCard, updateCard,
  };
}
