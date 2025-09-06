// Use the global axios configured in resources/js/bootstrap.js so we inherit CSRF, withCredentials, auth headers, etc.
// Fallback to module axios only if window.axios is not available (e.g., in tests)
import axios from 'axios';

const http = typeof window !== 'undefined' && window.axios ? window.axios : axios;

// For this service, we want a v1 prefix. Build URLs with that prefix while still using the shared axios instance.
const base = '/api/v1';

export default {
  list(params = {}) {
    return http.get(`${base}/presentations`, { params }).then(r => r.data);
  },
  get(id) {
    return http.get(`${base}/presentations/${id}`).then(r => r.data);
  },
  create(payload) {
    return http.post(`${base}/presentations`, payload).then(r => r.data);
  },
  update(id, payload) {
    return http.put(`${base}/presentations/${id}`, payload).then(r => r.data);
  },
  destroy(id) {
    return http.delete(`${base}/presentations/${id}`).then(r => r.data);
  },
  addSlide(presentationId, payload) {
    return http.post(`${base}/presentations/${presentationId}/slides`, payload).then(r => r.data);
  },
  updateSlide(id, payload) {
    return http.put(`${base}/slides/${id}`, payload).then(r => r.data);
  },
  reorderSlides(orders) {
    return http.post(`${base}/slides/reorder`, { orders }).then(r => r.data);
  },
  deleteSlide(id) {
    return http.delete(`${base}/slides/${id}`).then(r => r.data);
  },
  addBlock(slideId, payload) {
    return http.post(`${base}/slides/${slideId}/content_blocks`, payload).then(r => r.data);
  },
  updateBlock(id, payload) {
    return http.put(`${base}/content_blocks/${id}`, payload).then(r => r.data);
  },
  reorderBlocks(orders) {
    return http.post(`${base}/content_blocks/reorder`, { orders }).then(r => r.data);
  },
  deleteBlock(id) {
    return http.delete(`${base}/content_blocks/${id}`).then(r => r.data);
  },
  // New template & duplication APIs
  listTemplates() {
    return http.get(`${base}/templates`).then(r => r.data);
  },
  duplicate(id) {
    return http.post(`${base}/presentations/${id}/duplicate`).then(r => r.data);
  },
  saveAsTemplate(id) {
    return http.post(`${base}/presentations/${id}/save-as-template`).then(r => r.data);
  },
  copySlides(targetId, sourceSlideIds) {
    return http.post(`${base}/presentations/${targetId}/copy-slides`, { source_slide_ids: sourceSlideIds }).then(r => r.data);
  },
  invite(id, payload) {
    return http.post(`${base}/presentations/${id}/invite`, payload).then(r => r.data);
  },
  syncCollaborators(id, userIds, role = 'editor') {
    return http.post(`${base}/presentations/${id}/collaborators`, { user_ids: userIds, role }).then(r => r.data);
  },
};
