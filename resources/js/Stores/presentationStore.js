import { defineStore } from 'pinia';
import api from '@/Services/presentationsApi';

function debounce(fn, delay = 500) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), delay);
  };
}

export const usePresentationStore = defineStore('presentation', {
  state: () => ({
    loading: false,
    presentation: null, // full payload from GET /presentations/{id}
    selectedSlideId: null,
    savingBlocks: {}, // { [blockId]: boolean }
  }),
  getters: {
    slides(state) {
      return state.presentation?.slides || [];
    },
    selectedSlide(state) {
      return state.slides.find(s => s.id === state.selectedSlideId) || null;
    },
  },
  actions: {
    async load(id) {
      this.loading = true;
      try {
        this.presentation = await api.get(id);
        if (this.presentation.slides?.length) {
          this.selectedSlideId = this.presentation.slides[0].id;
        } else {
          this.selectedSlideId = null;
        }
      } finally {
        this.loading = false;
      }
    },
    selectSlide(id) {
      this.selectedSlideId = id;
    },

    async addSlide(payload = { template_name: 'Heading', title: 'New Slide' }) {
      const slide = await api.addSlide(this.presentation.id, payload);
      this.presentation.slides.push({ ...slide, content_blocks: [] });
      this.selectedSlideId = slide.id;
    },
    async updateSlide(slideId, payload) {
      const updated = await api.updateSlide(slideId, payload);
      const idx = this.presentation.slides.findIndex(s => s.id === slideId);
      if (idx !== -1) Object.assign(this.presentation.slides[idx], updated);
    },
    async deleteSlide(slideId) {
      await api.deleteSlide(slideId);
      this.presentation.slides = this.presentation.slides.filter(s => s.id !== slideId);
      if (this.selectedSlideId === slideId) {
        this.selectedSlideId = this.presentation.slides[0]?.id || null;
      }
    },
    async reorderSlides(newOrderIds) {
      const orders = newOrderIds.map((id, i) => ({ id, display_order: i + 1 }));
      await api.reorderSlides(orders);
      // reorder locally
      const map = new Map(this.presentation.slides.map(s => [s.id, s]));
      this.presentation.slides = newOrderIds.map(id => ({ ...map.get(id), display_order: this.presentation.slides.find(s=>s.id===id)?.display_order }));
    },

    async addBlock(slideId, payload) {
      const block = await api.addBlock(slideId, payload);
      const slide = this.presentation.slides.find(s => s.id === slideId);
      if (slide) {
        slide.content_blocks = slide.content_blocks || [];
        slide.content_blocks.push(block);
      }
    },
    async deleteBlock(blockId) {
      await api.deleteBlock(blockId);
      for (const slide of this.presentation.slides) {
        slide.content_blocks = (slide.content_blocks || []).filter(b => b.id !== blockId);
      }
    },
    async reorderBlocks(slideId, newOrderIds) {
      const orders = newOrderIds.map((id, i) => ({ id, display_order: i + 1 }));
      await api.reorderBlocks(orders);
      const slide = this.presentation.slides.find(s => s.id === slideId);
      if (slide) {
        const map = new Map((slide.content_blocks||[]).map(b => [b.id, b]));
        slide.content_blocks = newOrderIds.map(id => map.get(id));
      }
    },

    scheduleSaveBlock: debounce(async function(blockId, content_data) {
      this.savingBlocks[blockId] = true;
      try {
        await api.updateBlock(blockId, { content_data });
      } finally {
        this.savingBlocks[blockId] = false;
      }
    }, 600),
  }
});
