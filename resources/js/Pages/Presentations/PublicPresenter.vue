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
                        <component :is="renderSlideLayout(slide)" />
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<script setup>
import { toRefs, h, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    presentation: {
        type: Object,
        required: true,
        default: () => ({ slides: [] })
    }
});

const { presentation } = toRefs(props);
const currentSlideIndex = ref(0);

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

function handleKeyDown(event) {
    if (event.key === 'ArrowRight') goToNextSlide();
    else if (event.key === 'ArrowLeft') goToPrevSlide();
}

onMounted(() => {
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

// Layout-specific renderers
const layoutRenderers = {
    'IntroCover': (blocks) => {
        // Map over all blocks in their original, intended order.
        const renderedBlocks = blocks.map(block => {
            // If the current block is an image, wrap it in the special styling div.
            if (block.block_type === 'image') {
                return h('div', { class: 'mx-auto my-5 max-w-[300px] mt-8' }, [
                    renderBlock(block)
                ]);
            }
            // Otherwise, render the block normally.
            return renderBlock(block);
        });

        // The main container for the slide is centered.
        return h('div', { class: 'text-center' }, renderedBlocks);
    },
    'ThreeColumn': (blocks) => {
        const heading = blocks.find(b => b.block_type === 'heading');
        const cards = blocks.filter(b => b.block_type === 'feature_card');
        return h('div', { class: 'text-center' }, [
            heading ? renderBlock(heading) : null,
            h('div', { class: 'grid md:grid-cols-3 gap-8 text-center' }, cards.map(c => renderBlock(c))),
        ]);
    },
    'FourColumn': (blocks) => {
        const heading = blocks.find(b => b.block_type === 'heading');
        const paragraph = blocks.find(b => b.block_type === 'paragraph');
        const cards = blocks.filter(b => b.block_type === 'feature_card');
        return h('div', { class: 'text-center' }, [
            heading ? renderBlock(heading) : null,
            paragraph ? renderBlock(paragraph) : null,
            h('div', { class: 'grid md:grid-cols-4 gap-8 text-center' }, cards.map(c => renderBlock(c))),
        ]);
    },
    'TwoColumnWithImageRight': (blocks) => {
        const imageBlock = blocks.find(b => b.block_type === 'image');
        const contentBlocks = blocks.filter(b => b.block_type !== 'image');
        return h('div', { class: 'grid md:grid-cols-2 gap-12 items-center' }, [
            h('div', { class: 'text-left' }, contentBlocks.map(b => renderBlock(b))),
            imageBlock ? renderBlock(imageBlock) : null,
        ]);
    },
    'TwoColumnWithImageLeft': (blocks) => {
        const imageBlock = blocks.find(b => b.block_type === 'image');
        const contentBlocks = blocks.filter(b => b.block_type !== 'image');
        return h('div', { class: 'grid md:grid-cols-2 gap-12 items-center' }, [
            imageBlock ? renderBlock(imageBlock) : null,
            h('div', { class: 'text-left' }, contentBlocks.map(b => renderBlock(b))),
        ]);
    },
    'FourStepProcess': (blocks) => {
        const heading = blocks.find(b => b.block_type === 'heading');
        const steps = blocks.filter(b => b.block_type === 'step_card');
        return h('div', { class: 'text-center' }, [
            heading ? renderBlock(heading) : null,
            h('div', { class: 'relative grid grid-cols-1 md:grid-cols-4 gap-8 text-center' }, [
                h('div', { class: 'hidden md:block absolute top-1/2 left-0 w-full h-0.5 bg-gray-200', style: 'transform: translateY(-50%); z-index: -1;' }),
                ...steps.map(s => renderBlock(s)),
            ]),
        ]);
    },
    // ====================================================================
    // UPDATED RENDERER LOGIC
    // ====================================================================
    'ThreeStepProcess': (blocks) => {
        const heading = blocks.find(b => b.block_type === 'heading');
        // FIXED: Changed from 'feature_card' to 'step_card' to match the seeder
        const steps = blocks.filter(b => b.block_type === 'step_card');
        return h('div', { class: 'text-center' }, [
            heading ? renderBlock(heading) : null,
            h('div', { class: 'relative grid grid-cols-1 md:grid-cols-3 gap-8 text-center' }, [
                h('div', { class: 'hidden md:block absolute top-1/2 left-0 w-full h-0.5 bg-gray-200', style: 'transform: translateY(-50%); z-index: -1;' }),
                ...steps.map(s => renderBlock(s)),
            ]),
        ]);
    },
    'TwoColumnWithChart': (blocks) => {
        const heading = blocks.find(b => b.block_type === 'heading');
        // FIXED: Changed from 'image' to 'image_block'
        const imageBlock = blocks.find(b => b.block_type === 'image_block');
        // FIXED: Changed from 'feature_card' to 'feature_list'
        const featureList = blocks.find(b => b.block_type === 'feature_list');
        return h('div', { class: 'text-center' }, [
            heading ? renderBlock(heading) : null,
            h('div', { class: 'grid md:grid-cols-2 gap-8 items-center' }, [
                // Render the feature_list directly
                featureList ? renderBlock(featureList) : null,
                h('div', { class: 'p-8 bg-gray-50 rounded-2xl' }, [
                    h('h3', { class: 'text-xl font-bold text-dark-grey mb-4' }, imageBlock?.content_data?.title),
                    h('div', { class: 'w-full h-64 rounded-lg flex items-center justify-center' }, renderBlock(imageBlock)),
                ]),
            ]),
        ]);
    },
    'ProjectDetails': (blocks) => {
        const heading = blocks.find(b => b.block_type === 'heading');
        const pricing = blocks.find(b => b.block_type === 'pricing_table');
        const timeline = blocks.find(b => b.block_type === 'timeline_table');
        return h('div', { class: 'text-left' }, [
            heading ? renderBlock(heading) : null,
            h('div', { class: 'grid md:grid-cols-2 gap-8 text-left' }, [
                pricing ? renderBlock(pricing) : null,
                timeline ? renderBlock(timeline) : null,
            ]),
        ]);
    },
    'CallToAction': (blocks) => {
        return h('div', { class: 'text-center' }, blocks.map(b => renderBlock(b)));
    },
    'default': (blocks) => {
        return h('div', {}, blocks.map(b => renderBlock(b)));
    },
};

function renderSlideLayout(slide) {
    const blocks = slide.content_blocks || [];
    const renderer = layoutRenderers[slide.template_name] || layoutRenderers.default;
    return renderer(blocks);
}

function renderBlock(block) {

    if(!block) {
        return null;
    }

    const content = block.content_data || {};

    switch (block.block_type) {
        case 'heading':
            const tag = `h${content.level || 2}`;
            const headingClasses = `text-4xl font-bold text-oz-blue mb-4`;
            return h(tag, { class: headingClasses }, content.text || '');
        case 'paragraph':
            const pClasses = 'text-lg text-dark-grey mb-6';
            return h('p', { class: pClasses, innerHTML: content.text || '' });
        case 'feature_card':
            const iconMapping = {
                'fa-pencil-alt': 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10',
                'fa-list-alt': 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z',
                'fa-dollar-sign': 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.826-1.106-2.302 0-3.128a2.99 2.99 0 012.003-.659c.725 0 1.45.22 2.003.659l.879.659m0-2.219a.75.75 0 00-1.06-1.06l-1.06 1.06a.75.75 0 001.06 1.06l1.06-1.06z',
                'fa-users': 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m-7.5-2.228a4.5 4.5 0 00-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 001.13-1.897M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.862 4.487z',
                'fa-file-invoice-dollar': 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12z',
                'fa-cog': 'M9.594 3.94c.09-.542.56-1.007 1.11-1.11h2.592c.55.103 1.02.57 1.11 1.11l.09 1.586c.29.078.57.19.828.334l1.493-.672c.49-.222 1.054.02 1.272.512l1.295 2.242c.218.492.02 1.054-.512 1.272l-1.493.672c.044.258.078.536.078.828s-.034.57-.078.828l1.493.672c.492.218.672.78.512 1.272l-1.295 2.242c-.218.492-.782.672-1.272.512l-1.493-.672a6.721 6.721 0 01-.828.334l-.09 1.586c-.103.55-.57 1.02-1.11 1.11h-2.592c-.55-.103-1.02-.57-1.11-1.11l-.09-1.586a6.721 6.721 0 01-.828-.334l-1.493.672c-.49.222-1.054-.02-1.272-.512l-1.295-2.242c-.218-.492-.02-1.054.512-1.272l1.493.672A6.721 6.721 0 019.594 5.526l.09-1.586z',
                'fa-link': 'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75',
            };
            const iconPath = iconMapping[content.icon] || 'M15 19l-7-7 7-7';
            return h('div', { class: 'p-6 rounded-xl bg-gray-50 border border-gray-200' }, [
                h('div', { class: 'flex items-center justify-center h-16 w-16 rounded-full bg-oz-blue text-oz-gold mx-auto mb-4' }, [
                    h('svg', { xmlns: 'http://www.w3.org/2000/svg', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor', class: 'w-8 h-8' }, [
                        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: iconPath })
                    ])
                ]),
                h('h3', { class: 'text-xl font-bold mb-2 text-dark-grey' }, content.title || ''),
                h('p', { class: 'text-gray-600' }, content.description || ''),
            ]);
        case 'image':
            return h('div', { class: 'p-8 bg-gray-50 rounded-2xl flex items-center justify-center' },
                h('img', {
                    src: content.url,
                    alt: content.alt || 'Presentation Image',
                    class: 'max-w-full h-auto',
                    onError: (e) => e.target.style.display = 'none',
                })
            );
        case 'step_card':
            return h('div', { class: 'flex flex-col items-center p-4' }, [
                h('div', { class: 'relative flex items-center justify-center h-20 w-20 rounded-full bg-oz-blue text-white text-2xl font-bold border-4 border-slate-100 shadow-lg mb-4' }, content.step_number),
                h('h3', { class: 'text-lg font-bold mb-2 text-dark-grey' }, content.title),
                h('p', { class: 'text-sm text-gray-600' }, content.description),
            ]);
        case 'slogan':
            return h('p', { class: 'text-2xl font-bold text-oz-blue mt-4' }, content.text);
        case 'pricing_table':
            return h('div', { class: 'p-6 bg-gray-50 rounded-xl border border-gray-200' }, [
                h('h3', { class: 'text-2xl font-bold text-oz-gold mb-4' }, content.title),
                h('p', { class: 'text-lg text-gray-700' }, [
                    h('strong', { class: 'text-2xl text-oz-blue' }, content.price),
                ]),
                h('ul', { class: 'list-disc list-inside text-gray-600 mt-4 space-y-2' }, content.payment_schedule.map(item => h('li', item))),
            ]);
        case 'timeline_table':
            return h('div', { class: 'p-6 bg-gray-50 rounded-xl border border-gray-200' }, [
                h('h3', { class: 'text-2xl font-bold text-oz-gold mb-4' }, content.title),
                h('table', { class: 'w-full text-sm text-left text-gray-600' }, [
                    h('tbody', content.timeline.map(item => h('tr', { class: 'border-b' }, [
                        h('td', { class: 'py-3 pr-3 font-semibold' }, item.phase),
                        h('td', { class: 'py-3' }, item.duration),
                    ]))),
                ]),
            ]);
        case 'details_list':
            return h('div', { class: 'flex justify-center mt-8 space-x-6 text-gray-500 text-sm' },
                content.items.map(item => h('span', { innerHTML: item }))
            );

        // ====================================================================
        // NEWLY ADDED BLOCK RENDERERS
        // ====================================================================
        case 'list_with_icons':
            const checkIcon = h('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 20 20', fill: 'currentColor', class: 'w-5 h-5 text-oz-gold mr-3 flex-shrink-0' }, [
                h('path', { 'fill-rule': 'evenodd', d: 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z', 'clip-rule': 'evenodd' })
            ]);
            return h('ul', { class: 'space-y-4' }, content.items.map(item =>
                h('li', { class: 'flex items-start' }, [ checkIcon, h('span', { class: 'text-dark-grey' }, item) ])
            ));

        case 'feature_list':
            return h('div', { class: 'text-left space-y-8' }, content.items.map(item =>
                h('div', {}, [
                    h('h3', { class: 'text-xl font-bold text-dark-grey mb-2' }, item.title),
                    h('p', { class: 'text-gray-600' }, item.description),
                ])
            ));

        case 'image_block': // Often visually identical to 'image' but separated for logic
            return h('img', {
                src: content.url,
                alt: content.title || 'Presentation Image',
                class: 'max-w-full h-auto rounded-lg',
                onError: (e) => e.target.style.display = 'none',
            });

        default:
            return h('div', { class: 'text-red-500' }, `Unsupported block type: ${block.block_type}`);
    }
}
</script>

<style scoped>
/* Copied styles from dynamic_outdoor.html */
.font-montserrat { font-family: 'Montserrat', sans-serif; }
.embed-container { width: 100%; padding: 2rem; position: relative; min-height: 800px; overflow: hidden; }
.slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.7s cubic-bezier(0.4, 0, 0.2, 1); transform: translateX(100%); opacity: 0; padding: 2rem; z-index: 0; }
.slide.active { transform: translateX(0); opacity: 1; z-index: 1; }
.slide.prev { transform: translateX(-100%); opacity: 0; }
.slide-content { max-width: 1100px; width: 100%; padding: 3rem; background-color: #FFFFFF; border-radius: 24px; box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.1); text-align: center;}
.text-oz-blue { color: #29438E; }
.bg-oz-blue { background-color: #29438E; }
.text-oz-gold { color: #F7A823; }
.bg-oz-gold { background-color: #F7A823; }
.text-dark-grey { color: #333333; }
.nav-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 56px; height: 56px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 5px 20px rgba(41, 67, 142, 0.3); transition: transform 0.3s ease, background-color 0.3s ease; z-index: 10; }
.nav-btn:hover { transform: translateY(-50%) scale(1.1); }
#prev-button { left: 2rem; }
#next-button { right: 2rem; }
#dock-pagination { position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%); display: flex; align-items: flex-end; gap: 0.75rem; z-index: 20; }
.dock-dot { width: 14px; height: 14px; border-radius: 9999px; background: #cbd5e1; transition: transform 0.12s ease, background-color 0.2s ease; position: relative; border: none; padding: 0; cursor: pointer; }
.dock-dot.active { background: #29438E; }
.dock-dot:focus { outline: 2px solid #F7A823; outline-offset: 3px; }
.dock-tip { position: absolute; bottom: 140%; left: 50%; transform: translateX(-50%); background: rgba(17,24,39,0.9); color: #fff; padding: 6px 10px; border-radius: 8px; font-size: 12px; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity 0.15s ease, transform 0.15s ease; }
.dock-dot:hover .dock-tip { opacity: 1; transform: translateX(-50%) translateY(-2px); }

/* Additional styles to maintain original look and feel */
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
.text-4xl { font-size: 2.25rem; line-height: 2.5rem; }
.text-5xl { font-size: 3rem; line-height: 1; }
.border-oz-gold { border-color: #F7A823; }
</style>
