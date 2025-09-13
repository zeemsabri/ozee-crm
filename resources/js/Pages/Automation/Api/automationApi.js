// Centralized API helpers for the Automation Studio
import axios from 'axios';

const API_BASE_URL = '/api';

// ===================== Workflows =====================
export const fetchWorkflows = async (params = {}) => {
  const { data } = await axios.get(`${API_BASE_URL}/workflows`, { params });
  return data; // Laravel paginator object (data, meta, links)
};

export const fetchWorkflow = async (id) => {
  const { data } = await axios.get(`${API_BASE_URL}/workflows/${id}`);
  return data; // Workflow with steps
};

export const createWorkflow = async (payload) => {
  const { data } = await axios.post(`${API_BASE_URL}/workflows`, payload);
  return data;
};

export const updateWorkflow = async (id, payload) => {
  const { data } = await axios.put(`${API_BASE_URL}/workflows/${id}`, payload);
  return data;
};

export const deleteWorkflow = async (id) => {
  await axios.delete(`${API_BASE_URL}/workflows/${id}`);
};

// ===================== Workflow Steps =====================
export const createWorkflowStep = async (payload) => {
  const { data } = await axios.post(`${API_BASE_URL}/workflow-steps`, payload);
  return data;
};

export const updateWorkflowStep = async (id, payload) => {
  const { data } = await axios.put(`${API_BASE_URL}/workflow-steps/${id}`, payload);
  return data;
};

export const deleteWorkflowStep = async (id) => {
  await axios.delete(`${API_BASE_URL}/workflow-steps/${id}`);
};

// ===================== Prompts =====================
export const fetchPrompts = async (params = {}) => {
  const { data } = await axios.get(`${API_BASE_URL}/prompts`, { params });
  return data; // Paginator (data, meta, links)
};

export const createPrompt = async (payload) => {
  const { data } = await axios.post(`${API_BASE_URL}/prompts`, payload);
  return data;
};

export const updatePrompt = async (id, payload) => {
  const { data } = await axios.put(`${API_BASE_URL}/prompts/${id}`, payload);
  return data;
};

export const deletePrompt = async (id) => {
  await axios.delete(`${API_BASE_URL}/prompts/${id}`);
};

// ===================== Schema =====================
// Fetches the data dictionary for the Automation Studio builder
export const fetchAutomationSchema = async () => {
  const { data } = await axios.get(`${API_BASE_URL}/automation/schema`);
  return data;
};
