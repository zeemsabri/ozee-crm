<template>
    <div class="bg-slate-100 font-montserrat min-h-screen flex items-center justify-center">
        <!-- The main container that mimics the original HTML structure -->
        <div class="embed-container">
            <!-- Navigation Buttons -->
            <button v-show="currentSlideIndex > 0" @click="goToPrevSlide" id="prev-button" class="nav-btn bg-oz-blue" aria-label="Previous slide">
                <i class="fa-solid fa-chevron-left text-white text-2xl"></i>
            </button>
            <button v-show="currentSlideIndex < presentation.slides.length - 1" @click="goToNextSlide" id="next-button" class="nav-btn bg-oz-blue" aria-label="Next slide">
                <i class="fa-solid fa-chevron-right text-white text-2xl"></i>
            </button>

            <!-- Dock Pagination -->
            <div id="dock-pagination" role="tablist">
                <button
                    v-for="(slide, index) in presentation.slides"
                    :key="slide.id"
                    class="dock-dot"
                    :class="{ 'active': index === currentSlideIndex }"
                    @click="currentSlideIndex = index"
                    :aria-label="`Go to slide ${index + 1}`"
                    :aria-selected="index === currentSlideIndex"
                >
                    <div class="dock-tip">{{ slide.title || `Slide ${index + 1}` }}</div>
                </button>
            </div>

            <!-- Main content area for slides -->
            <main>
                <section
                    v-for="(slide, index) in presentation.slides"
                    :key="slide.id"
                    class="slide"
                    :class="{
                        'active': index === currentSlideIndex,
                        'prev': index < currentSlideIndex
                    }"
                >
                    <!-- This is the crucial white card -->
                    <div class="slide-content">
                        <!-- We now pass the slide's index to the layout renderer -->
                        <component :is="renderSlideLayout(slide, index)" />
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<script setup>
import { toRefs, h, ref, onMounted, onUnmounted } from 'vue';

// Define props for the presentation data
const props = defineProps({
    presentation: {
        type: Object,
        required: true,
        default: () => ({ slides: [] })
    }
});

const { presentation } = toRefs(props);
const currentSlideIndex = ref(0);

// --- Slide Navigation Logic (Unchanged) ---
function goToNextSlide() {
    if (currentSlideIndex.value < presentation.value.slides.length - 1) {
        currentSlideIndex.value++;
    }
}
function goToPrevSlide() {
    if (currentSlideIndex.value > 0) {
        currentSlideIndex.value--;
    }
}

// --- Keyboard Navigation (Unchanged) ---
function handleKeyDown(event) {
    if (event.key === 'ArrowRight') goToNextSlide();
    else if (event.key === 'ArrowLeft') goToPrevSlide();
}

onMounted(() => {
    // Dynamically add external scripts and styles
    const faScript = document.createElement('script');
    faScript.src = "https://kit.fontawesome.com/6afed830a9.js";
    faScript.crossOrigin = "anonymous";
    document.head.appendChild(faScript);

    const fontLink = document.createElement('link');
    fontLink.href = "https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap";
    fontLink.rel = "stylesheet";
    document.head.appendChild(fontLink);

    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

// --- Top-Level Slide Layout Renderer ---
function renderSlideLayout(slide, index) {
    const allBlocks = slide.content_blocks || [];

    // --- Special handling for CoverSlide template ---
    if (slide.template_name === 'CoverSlide' || index === 0) {
        // This creates a centered layout.
        return h('div', { class: 'text-center' }, allBlocks.map(block => renderBlock(block, 'cover')));
    }

    const imageBlock = allBlocks.find(b => b.block_type === 'image');
    const hasOtherContent = allBlocks.some(b => b.block_type !== 'image');

    // Automatic two-column layout detection
    if ((imageBlock && hasOtherContent) || slide.template_name === 'TwoColumnWithImage') {
        const contentBlocks = allBlocks.filter(b => b.block_type !== 'image');

        const contentColumn = h('div', { class: 'space-y-8' }, contentBlocks.map(block => renderBlock(block)));
        const imageColumn = h('div', {}, imageBlock ? renderBlock(imageBlock, 'sidebar') : null);

        // Alternating layout logic
        if (index % 2 !== 0) {
            return h('div', { class: 'grid md:grid-cols-2 gap-12 items-start text-left' }, [ imageColumn, contentColumn ]);
        }
        return h('div', { class: 'grid md:grid-cols-2 gap-12 items-start text-left' }, [ contentColumn, imageColumn ]);
    }

    // Fallback layout
    return h('div', { class: 'text-left' }, allBlocks.map(block => renderBlock(block)));
}


// --- Individual Block Renderer ---
function renderBlock(block, context = 'default') {
    const content = block.content_data || {};

    switch (block.block_type) {
        case 'heading':
            const tag = `h${content.level || 2}`;
            const headingClasses = 'text-4xl font-bold text-oz-blue mb-8';
            return h(tag, { class: headingClasses }, content.text || '');

        case 'paragraph':
            const pClasses = 'text-lg text-dark-grey mb-6';
            return h('p', { class: pClasses, innerHTML: content.text || '' });

        case 'feature_card':
            return h('div', { class: 'flex items-start' }, [
                h('div', { class: 'icon-badge flex-shrink-0 bg-red-500 text-white mr-4'},
                    h('i', { class: `fa-solid ${content.icon || 'fa-circle-exclamation'} icon-24` })
                ),
                h('div', {}, [
                    h('h4', { class: 'text-xl font-bold text-dark-grey' }, content.title || ''),
                    h('p', { class: 'text-gray-600' }, content.description || '')
                ])
            ]);

        case 'image':
            if (context === 'cover') {
                // Special rendering for the logo on a cover slide
                return h('div', { class: 'w-100 mb-6 text-center'},
                    h('img', {
                        src: content.url,
                        alt: content.alt_text || 'Logo',
                        class: 'w-50 mx-auto'
                    })
                );
            }
            // Default rendering for an image in a sidebar
            return h('div', { class: 'p-8 bg-gray-50 rounded-2xl flex items-center justify-center' },
                h('img', {
                    src: content.url,
                    alt: content.alt_text || 'Presentation Image',
                    class: 'max-w-full h-auto'
                })
            );

        default:
            return h('div', { class: 'text-red-500' }, `Unsupported block type: ${block.block_type}`);
    }
}
</script>

<style scoped>
/* --- Copied styles from fannit.html --- */
.font-montserrat { font-family: 'Montserrat', sans-serif; }
.embed-container { width: 100%; padding: 2rem; position: relative; min-height: 800px; overflow: hidden; }
.slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.7s cubic-bezier(0.4, 0, 0.2, 1); transform: translateX(100%); opacity: 0; padding: 2rem; z-index: 0; }
.slide.active { transform: translateX(0); opacity: 1; z-index: 1; }
.slide.prev { transform: translateX(-100%); opacity: 0; }
.slide-content { max-width: 1100px; width: 100%; padding: 3rem; background-color: #FFFFFF; border-radius: 24px; box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.1); }
.text-oz-blue { color: #29438E; }
.bg-oz-blue { background-color: #29438E; }
.text-oz-gold { color: #F7A23; }
.bg-oz-gold { background-color: #F7A23; }
.text-dark-grey { color: #333333; }
.nav-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 56px; height: 56px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 5px 20px rgba(41, 67, 142, 0.3); transition: transform 0.3s ease, background-color 0.3s ease; z-index: 10; }
.nav-btn:hover { transform: translateY(-50%) scale(1.1); }
#prev-button { left: 2rem; }
#next-button { right: 2rem; }
#dock-pagination { position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%); display: flex; align-items: flex-end; gap: 0.75rem; z-index: 20; }
.dock-dot { width: 14px; height: 14px; border-radius: 9999px; background: #cbd5e1; transition: transform 0.12s ease, background-color 0.2s ease; position: relative; border: none; padding: 0; cursor: pointer; }
.dock-dot.active { background: #29438E; }
.dock-dot:focus { outline: 2px solid #F7A23; outline-offset: 3px; }
.dock-tip { position: absolute; bottom: 140%; left: 50%; transform: translateX(-50%); background: rgba(17,24,39,0.9); color: #fff; padding: 6px 10px; border-radius: 8px; font-size: 12px; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity 0.15s ease, transform 0.15s ease; }
.dock-dot:hover .dock-tip { opacity: 1; transform: translateX(-50%) translateY(-2px); }

/* --- Icon centering styles from fannit.html --- */
.icon-badge { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 9999px; }
.icon-24 { font-size: 1.25rem; line-height: 1; }
i.fa-solid { display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
</style>

