<template>
    <div class="bg-slate-100 font-montserrat min-h-screen flex items-center justify-center">
        <!-- The main container -->
        <div class="embed-container">
                    <!-- Loader Overlay -->
                    <div v-if="showLoader" id="loader-overlay" class="fixed inset-0 flex flex-col items-center justify-center bg-white z-50">
                        <div class="animate-spin rounded-full h-16 w-16 border-4 border-oz-blue border-t-transparent mb-4"></div>
                        <div id="loader-text" class="text-sm text-dark-grey">Crafting your report… {{ loadPercent }}%</div>
                    </div>
            <!-- Navigation Buttons -->
            <button v-show="!showFormModal && currentSlideIndex > 0" @click="goToPrevSlide" id="prev-button" class="nav-btn bg-oz-blue" aria-label="Previous slide">
                <i class="fa-solid fa-chevron-left text-white text-2xl"></i>
            </button>
            <button v-show="!showFormModal && currentSlideIndex < presentation.slides.length - 1" @click="goToNextSlide" id="next-button" class="nav-btn bg-oz-blue" aria-label="Next slide">
                <i class="fa-solid fa-chevron-right text-white text-2xl"></i>
            </button>

            <!-- Dock Pagination -->
            <div v-show="!showFormModal" id="dock-pagination" role="tablist">
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
                    <div class="slide-content">
                        <component :is="renderSlideLayout(slide)" />
                    </div>
                </section>
            </main>
        </div>

        <!-- Contact Form Modal via BaseFormModal -->
        <BaseFormModal
            v-if="showFormModal"
            :show="showFormModal"
            :title="displayForm.title"
            :api-endpoint="leadEndpoint"
            http-method="post"
            :form-data="intakeForm"
            :before-submit="beforeLeadSubmit"
            :show-footer="true"
            :success-message="'Thanks! Your details have been received.'"
            :submit-button-text="displayForm.submit_button_text || 'Submit'"
            @close="showFormModal = false"
            @submitted="onLeadSubmitted"
        >
            <template #default="{ errors }">
                <div class="space-y-4">
                    <p class="text-sm text-gray-600">{{ displayForm.description }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div
                            v-for="field in displayForm.fields"
                            :key="field.name"
                            class="form-group"
                            :class="{ 'md:col-span-2': isLongField(field) }"
                        >
                            <label :for="field.name" class="form-label">
                                {{ field.label }}
                                <span v-if="field.required" class="text-red-500">*</span>
                            </label>
                            <input
                                v-if="field.type !== 'textarea'"
                                v-model="intakeForm[field.name]"
                                :type="field.type"
                                :id="field.name"
                                :placeholder="field.placeholder"
                                class="form-input"
                                :required="field.required"
                                :aria-required="field.required ? 'true' : 'false'"
                            />
                            <textarea
                                v-else
                                v-model="intakeForm[field.name]"
                                :id="field.name"
                                :placeholder="field.placeholder"
                                rows="4"
                                class="form-input"
                                :required="field.required"
                                :aria-required="field.required ? 'true' : 'false'"
                            ></textarea>
                            <p v-if="errors && errors[field.name]" class="text-sm text-red-600 mt-1">{{ errors[field.name]?.[0] || errors[field.name] }}</p>
                        </div>
                    </div>
                </div>
            </template>
        </BaseFormModal>
    </div>
</template>

<script setup>
import { toRefs, h, ref, onMounted, onUnmounted, watch, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';

const props = defineProps({
    presentation: {
        type: Object,
        required: true,
        default: () => ({ slides: [] })
    },
    form: {
        type: Object,
        required: false,
        default: () => ({ title: '', description: '', fields: [], submit_button_text: 'Submit' })
    }
});

const { presentation, form } = toRefs(props);
const currentSlideIndex = ref(0);
const showFormModal = ref(false);

// Loader state inspired by fannit.html
const showLoader = ref(true);
const loadPercent = ref(0);

// Fallback form schema (mirrors config/forms.php contact_form)
const fallbackForm = {
    title: 'Get in Touch',
    description: "We're excited to learn more about your project. Please fill out the form below, and one of our specialists will contact you shortly.",
    fields: [
        { name: 'name', label: 'Full Name', type: 'text', placeholder: 'e.g., Jane Doe', required: true },
        { name: 'email', label: 'Email Address', type: 'email', placeholder: 'e.g., jane.doe@example.com', required: true },
        { name: 'phone', label: 'Phone Number', type: 'tel', placeholder: 'e.g., 0412 345 678', required: false },
        { name: 'company_name', label: 'Company Name', type: 'text', placeholder: 'e.g., Future Co', required: false },
        { name: 'abn', label: 'ABN', type: 'text', placeholder: 'e.g., 50 123 456 789', required: false },
        { name: 'address', label: 'Address', type: 'text', placeholder: 'e.g., 123 Example St, Sydney NSW 2000', required: false },
        { name: 'message', label: 'Your Message', type: 'textarea', placeholder: 'Tell us a bit about your project or requirements...', required: true },
    ],
    submit_button_text: 'Send Inquiry',
};

// Use provided form if it has fields; otherwise use fallback
const displayForm = computed(() => {
    const fields = form?.value?.fields;
    return Array.isArray(fields) && fields.length > 0 ? form.value : fallbackForm;
});

// Lead intake form state for BaseFormModal
const intakeForm = ref({});
const leadEndpoint = '/api/public/lead-intake';

function initIntakeForm() {
    try {
        const fields = Array.isArray(displayForm.value?.fields) ? displayForm.value.fields : [];
        const obj = {};
        for (const f of fields) {
            if (f?.name) obj[f.name] = '';
        }
        intakeForm.value = obj;
    } catch (e) {
        intakeForm.value = {};
    }
}

watch(showFormModal, (v) => {
    if (v) initIntakeForm();
});

async function beforeLeadSubmit() {
    // Ensure email exists if field present
    const email = intakeForm.value?.email?.trim();
    if (!email) {
        // Let server-side validation handle messaging; return false to prevent empty submit
        return false;
    }
    return true;
}

function onLeadSubmitted() {
    showFormModal.value = false;
}

function isLongField(field) {
    const name = field?.name || '';
    const type = field?.type || '';
    return name === 'address' || name === 'message' || type === 'textarea';
}

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
    if (showFormModal.value && event.key === 'Escape') {
        showFormModal.value = false;
        return;
    }
    if (!showFormModal.value) {
        if (event.key === 'ArrowRight') goToNextSlide();
        else if (event.key === 'ArrowLeft') goToPrevSlide();
    }
}

function submitForm() {
    // In a real app, you would handle form submission here (e.g., API call).
    alert('Thank you for your submission!');
    showFormModal.value = false;
}

onMounted(() => {
    // Preload images within slides to mirror fannit.html loading overlay
    try {
        const imgs = Array.from(document.querySelectorAll('.slide img'));
        const total = imgs.length || 1;
        let loaded = 0;
        const update = () => { loadPercent.value = Math.min(100, Math.round((loaded / total) * 100)); };
        const finish = () => { showLoader.value = false; };
        if (imgs.length === 0) {
            finish();
        } else {
            imgs.forEach(img => {
                if (img.complete) { loaded++; update(); return; }
                const onDone = () => { loaded++; update(); if (loaded >= total) finish(); };
                img.addEventListener('load', onDone, { once: true });
                img.addEventListener('error', onDone, { once: true });
            });
            setTimeout(finish, 8000);
            if (loaded >= total) finish(); else update();
        }
    } catch (e) { showLoader.value = false; }
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
        const renderedBlocks = blocks.map(block => {
            if (block.block_type === 'image') {
                return h('div', { class: 'mx-auto my-5 max-w-[300px] mt-8' }, [
                    renderBlock(block)
                ]);
            }
            return renderBlock(block);
        });
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
    'ThreeStepProcess': (blocks) => {
        const heading = blocks.find(b => b.block_type === 'heading');
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
        const imageBlock = blocks.find(b => b.block_type === 'image_block');
        const featureList = blocks.find(b => b.block_type === 'feature_list');
        return h('div', { class: 'text-center' }, [
            heading ? renderBlock(heading) : null,
            h('div', { class: 'grid md:grid-cols-2 gap-8 items-center' }, [
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
    'CallToAction': (blocks) => h('div', { class: 'text-center' }, blocks.map(b => renderBlock(b))),
    'default': (blocks) => h('div', {}, blocks.map(b => renderBlock(b))),
};

function renderSlideLayout(slide) {
    const blocks = slide.content_blocks || [];
    const renderer = layoutRenderers[slide.template_name] || layoutRenderers.default;
    return renderer(blocks);
}

function renderBlock(block) {
    if(!block) return null;
    const content = block.content_data || {};

    switch (block.block_type) {
        case 'heading':
            const tag = `h${content.level || 2}`;
            return h(tag, { class: 'text-4xl font-bold text-oz-blue mb-4' }, content.text || '');
        case 'paragraph':
            return h('p', { class: 'text-lg text-dark-grey mb-6', innerHTML: content.text || '' });
        case 'feature_card':
            function normalizeFa(icon) {
                if (!icon || typeof icon !== 'string') return '';
                const trimmed = icon.trim();
                const hasStyle = /(fa-solid|fa-regular|fa-light|fa-thin|fa-brands|fa-duotone)/.test(trimmed);
                return hasStyle ? trimmed : `fa-solid ${trimmed}`;
            }
            const iconClass = normalizeFa(content.icon || 'fa-star');
            return h('div', { class: 'p-6 rounded-xl bg-gray-50 border border-gray-200' }, [
                h('div', { class: 'flex items-center justify-center h-16 w-16 rounded-full bg-oz-blue text-oz-gold mx-auto mb-4' }, [
                    h('i', { class: `${iconClass} text-3xl` })
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
        case 'image_block':
            return h('img', {
                src: content.url,
                alt: content.title || 'Presentation Image',
                class: 'max-w-full h-auto rounded-lg',
                onError: (e) => e.target.style.display = 'none',
            });
        case 'button':
            return h('button', {
                class: 'mt-8 px-10 py-4 bg-oz-gold text-white font-bold rounded-full text-lg shadow-lg hover:bg-opacity-90 transition-transform transform hover:scale-105',
                onClick: () => {
                    if (content.action === 'show_contact_form') {
                        showFormModal.value = true;
                    }
                }
            }, content.text);
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
.bg-oz-blue { background-color: #29438E; }
.bg-oz-gold { background-color: #F7A823; }
.text-oz-gold { color: #F7A823; }
.text-dark-grey { color: #374151; }
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

.form-group { display: flex; flex-direction: column; text-align: left; }
.form-label { margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
.form-input { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 0.75rem 1rem; transition: border-color 0.2s, box-shadow 0.2s; }
.form-input:focus { outline: none; border-color: #29438E; box-shadow: 0 0 0 3px rgba(41, 67, 142, 0.2); }
.form-submit-btn { background-color: #F7A823; color: white; font-weight: bold; padding: 0.8rem 1rem; border-radius: 8px; transition: background-color 0.2s; margin-top: 1rem; }
.form-submit-btn:hover { background-color: #f9b449; }
#loader-overlay{position:absolute; top:0; left:0; right:0; bottom:0;}
</style>

