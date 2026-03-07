// Centralized API helpers for the Automation Studio V2
// Re-uses the same backend endpoints as V1
import axios from 'axios';

const BASE = '/api';

// ===================== Workflows =====================
export const fetchWorkflows = (params = {}) =>
    axios.get(`${BASE}/workflows`, { params }).then(r => r.data);

export const fetchWorkflow = (id) =>
    axios.get(`${BASE}/workflows/${id}`).then(r => r.data);

export const createWorkflow = (payload) =>
    axios.post(`${BASE}/workflows`, payload).then(r => r.data);

export const updateWorkflow = (id, payload) =>
    axios.put(`${BASE}/workflows/${id}`, payload).then(r => r.data);

export const deleteWorkflow = (id) =>
    axios.delete(`${BASE}/workflows/${id}`);

// ===================== Schema =====================
export const fetchAutomationSchema = () =>
    axios.get(`${BASE}/automation/schema`).then(r => r.data);

// ===================== Logs =====================
export const fetchWorkflowLogs = (workflowId, params = {}) =>
    axios.get(`${BASE}/workflows/${workflowId}/logs`, { params }).then(r => r.data);

// ===================== Prompts =====================
export const fetchPrompts = (params = {}) =>
    axios.get(`${BASE}/prompts`, { params }).then(r => r.data);

export const createPrompt = (payload) =>
    axios.post(`${BASE}/prompts`, payload).then(r => r.data);

export const updatePrompt = (id, payload) =>
    axios.put(`${BASE}/prompts/${id}`, payload).then(r => r.data);

// ===================== Value Dictionaries =====================
export const fetchValueDictionary = (model, field) =>
    axios.get(`${BASE}/value-dictionaries/${encodeURIComponent(model)}/${encodeURIComponent(field)}`).then(r => r.data);
