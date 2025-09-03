import { defineStore } from 'pinia';
import api from '@/Services/presentationsApi';
// Optional notifications (fallback to console if not available)
let notify = { success: (m)=>console.log(m), error: (m)=>console.error(m) };
try {
    // Lazy import path per prompt (may exist in project)
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const mod = require('@/Utils/notification');
    if (mod?.success && mod?.error) notify = mod;
} catch {}

function debounce(fn, delay = 500) {
    let t;
    return function (...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), delay);
    };
}

export const usePresentationStore = defineStore('presentation', {
    state: () => ({
        loading: false,
        presentation: null, // full payload from GET /presentations/{id}
        selectedSlideId: null,
        selectedBlockId: null,
        savingBlocks: {}, // { [blockId]: boolean }
    }),
    getters: {
        slides(state) {
            // Ensure slides are always sorted by their display_order property
            return (state.presentation?.slides || []).slice().sort((a, b) => a.display_order - b.display_order);
        },
        selectedSlide(state) {
            // Use this.slides to access another getter; state.slides is undefined in Pinia
            return this.slides.find(s => s.id === state.selectedSlideId) || null;
        },
    },
    actions: {
        async load(id) {
            this.loading = true;
            try {
                this.presentation = await api.get(id);
                if (this.slides?.length) { // Use the getter to ensure sorted list
                    this.selectedSlideId = this.slides[0].id;
                } else {
                    this.selectedSlideId = null;
                }
            } catch (e) {
                notify.error('Failed to load presentation.');
                console.error(e);
                this.presentation = { id, slides: [] };
                this.selectedSlideId = null;
            } finally {
                this.loading = false;
            }
        },
        selectSlide(id) {
            this.selectedSlideId = id;
        },
        selectBlock(id) {
            this.selectedBlockId = id;
        },
        async savePresentation() {
            if (!this.presentation?.id) return;
            try {
                // Corrected: Renamed `updatePresentation` to `update`
                await api.update(this.presentation.id, {
                    title: this.presentation.title
                });
                notify.success('Presentation saved.');
            } catch (e) {
                notify.error('Failed to save presentation.');
                console.error(e);
            }
        },
        async updatePresentationTitle(title) {
            if (!this.presentation?.id) return;
            const originalTitle = this.presentation.title;
            this.presentation.title = title;
            try {
                // Corrected: Renamed `updatePresentation` to `update`
                await api.update(this.presentation.id, { title });
                notify.success('Presentation title updated.');
            } catch (e) {
                notify.error('Failed to update presentation title.');
                this.presentation.title = originalTitle;
                console.error(e);
            }
        },

        async addSlide(payload = { template_name: 'Heading', title: 'New Slide' }) {
            const originalSlides = [...(this.presentation.slides || [])];
            try {
                const slide = await api.addSlide(this.presentation.id, payload);
                const newSlide = { ...slide, content_blocks: slide.content_blocks || [] };
                this.presentation.slides.push(newSlide);
                this.selectedSlideId = newSlide.id;
                notify.success('Slide added.');
            } catch (e) {
                notify.error('Failed to add slide.');
                this.presentation.slides = originalSlides;
                console.error(e);
            }
        },
        async updateSlide(slideId, payload) {
            const idx = this.presentation.slides.findIndex(s => s.id === slideId);
            const snapshot = idx !== -1 ? { ...this.presentation.slides[idx] } : null;
            try {
                const updated = await api.updateSlide(slideId, payload);
                if (idx !== -1) Object.assign(this.presentation.slides[idx], updated);
                notify.success('Slide updated.');
            } catch (e) {
                notify.error('Failed to update slide.');
                if (idx !== -1 && snapshot) this.presentation.slides[idx] = snapshot;
                console.error(e);
            }
        },
        async deleteSlide(slideId) {
            const originalSlides = [...this.presentation.slides];
            this.presentation.slides = originalSlides.filter(s => s.id !== slideId);
            if (this.selectedSlideId === slideId) {
                this.selectedSlideId = this.slides[0]?.id || null; // Use getter
            }
            try {
                await api.deleteSlide(slideId);
                notify.success('Slide deleted successfully.');
            } catch (e) {
                notify.error('Failed to delete slide. Please try again.');
                this.presentation.slides = originalSlides; // revert
                if (!this.presentation.slides.find(s => s.id === this.selectedSlideId)) {
                    this.selectedSlideId = slideId;
                }
                console.error(e);
            }
        },

        /**
         * [FIXED] Handles reordering slides. This function now performs an optimistic
         * update for a snappy UI, correctly updates local state, and reverts on API failure.
         */
        async reorderSlides(newOrderIds) {
            const originalSlides = JSON.parse(JSON.stringify(this.presentation.slides));

            // 1. Optimistic Update: Reorder the local state immediately for a fast UI response.
            const slideMap = new Map(this.presentation.slides.map(s => [s.id, s]));
            this.presentation.slides = newOrderIds.map((id, index) => {
                const slide = slideMap.get(id);
                if (slide) {
                    slide.display_order = index + 1; // Assign new, correct order
                }
                return slide;
            }).filter(Boolean); // Filter out any undefined if an ID was not found

            // 2. Prepare payload for the API.
            const orders = newOrderIds.map((id, i) => ({ id, display_order: i + 1 }));

            // 3. Call API and handle errors.
            try {
                await api.reorderSlides(orders);
                notify.success('Slides reordered.');
            } catch (e) {
                notify.error('Failed to reorder blocks.');
                // 4. Revert state if API call fails.
                this.presentation.slides = originalSlides;
                console.error(e);
            }
        },

        // --- Block Actions remain unchanged ---

        async addBlock(slideId, payload) {
            const slide = this.presentation.slides.find(s => s.id === slideId);
            const originalBlocks = slide ? [...(slide.content_blocks || [])] : [];
            try {
                const block = await api.addBlock(slideId, payload);
                if (slide) {
                    slide.content_blocks = slide.content_blocks || [];
                    slide.content_blocks.push(block);
                }
                notify.success('Block added.');
            } catch (e) {
                notify.error('Failed to add block.');
                if (slide) slide.content_blocks = originalBlocks;
                console.error(e);
            }
        },
        async deleteBlock(blockId) {
            let affectedSlide = null;
            for (const s of this.presentation.slides) {
                if ((s.content_blocks || []).some(b => b.id === blockId)) { affectedSlide = s; break; }
            }
            const original = affectedSlide ? [...(affectedSlide.content_blocks || [])] : null;
            if (affectedSlide) {
                affectedSlide.content_blocks = (affectedSlide.content_blocks || []).filter(b => b.id !== blockId);
            }
            try {
                await api.deleteBlock(blockId);
                notify.success('Block deleted.');
            } catch (e) {
                notify.error('Failed to delete block.');
                if (affectedSlide && original) affectedSlide.content_blocks = original;
                console.error(e);
            }
        },
        async reorderBlocks(slideId, newOrderIds) {
            const slide = this.presentation.slides.find(s => s.id === slideId);
            const original = slide ? [...(slide.content_blocks || [])] : [];
            const orders = newOrderIds.map((id, i) => ({ id, display_order: i + 1 }));
            try {
                await api.reorderBlocks(orders);
                if (slide) {
                    const map = new Map((slide.content_blocks||[]).map(b => [b.id, b]));
                    slide.content_blocks = newOrderIds.map((id, index) => {
                        const b = map.get(id);
                        if (b) b.display_order = index + 1;
                        return b;
                    }).filter(Boolean);
                }
                notify.success('Blocks reordered.');
            } catch (e) {
                notify.error('Failed to reorder blocks.');
                if (slide) slide.content_blocks = original;
                console.error(e);
            }
        },
        scheduleSaveBlock: debounce(async function(blockId, content_data) {
            // Find the slide and block in the local state
            const slide = this.slides.find(s => s.content_blocks?.some(b => b.id === blockId));
            if (!slide) return;
            const block = slide.content_blocks.find(b => b.id === blockId);
            if (!block) return;

            // Optimistically update the local state for an instant UI change
            Object.assign(block.content_data, content_data);

            this.savingBlocks[blockId] = true;
            try {
                await api.updateBlock(blockId, { content_data });
                // Don't show success here, it's too noisy. The UI shows "Saved ✓"
            } catch (e) {
                notify.error('Failed to save block.');
                console.error(e);
            } finally {
                this.savingBlocks[blockId] = false;
            }
        }, 600),
    }
});
