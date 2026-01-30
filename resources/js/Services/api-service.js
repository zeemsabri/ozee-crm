import axios from 'axios';

const API_BASE_URL = '/api';

export const fetchEmails = async (filters, page) => {
    const params = {
        ...filters,
        page: page,
    };

    if (params.type === 'new') {
        params.is_read = false;
    }
    if (params.type === 'waiting-approval') {
        params.statuses = ['pending_approval', 'pending_approval_received'];
    }

    const response = await axios.get(`${API_BASE_URL}/inbox/all-emails`, { params });
    return response.data;
};

export const getEmailDetails = async (emailId) => {
    const response = await axios.get(`${API_BASE_URL}/emails/${emailId}`);
    return response.data;
};

export const markAsRead = async (emailId) => {
    const response = await axios.post(`${API_BASE_URL}/inbox/emails/${emailId}/mark-as-read`);
    return response.data;
};

export const fetchAttachments = async (emailId) => {
    const response = await axios.get(`${API_BASE_URL}/files`, {
        params: {
            model_type: 'App\\Models\\Email',
            model_id: emailId,
        },
    });
    return response.data;
};

export const deleteEmail = async (emailId, { delete_gmail = false, delete_local = true } = {}) => {
    const response = await axios.delete(`${API_BASE_URL}/emails/${emailId}`, {
        data: { delete_gmail, delete_local },
    });
    return response.data;
};

export const toggleEmailPrivacy = async (emailId, isPrivate = null) => {
    const payload = {};
    if (isPrivate !== null) payload.is_private = !!isPrivate;
    const { data } = await axios.patch(`${API_BASE_URL}/emails/${emailId}/privacy`, payload);
    return data;
};

export const updateConversationProject = async (conversationId, projectId) => {
    const { data } = await axios.patch(`${API_BASE_URL}/conversations/${conversationId}/project`, {
        project_id: projectId,
    });
    return data;
};
