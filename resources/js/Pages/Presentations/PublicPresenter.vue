<template>
    <div class="min-h-screen bg-gray-50 p-6" :class="{ 'fullscreen': isFullscreen }">
        <h1 class="text-3xl font-bold mb-6 text-center">{{ presentation.title }}</h1>
        <swiper
            :slides-per-view="1"
            :navigation="true"
            :pagination="{ clickable: true }"
            class="max-w-5xl mx-auto"
            role="region"
            aria-label="Presentation slides"
        >
            <swiper-slide v-for="s in presentation.slides" :key="s.id" class="p-6">
                <h2 class="text-2xl font-semibold mb-4">{{ s.title || s.template_name }}</h2>
                <div v-for="b in s.content_blocks" :key="b.id" class="mb-4">
                    <component :is="getRenderer(b)" />
                </div>
            </swiper-slide>
        </swiper>
        <div class="fixed bottom-4 right-4 flex gap-3">
            <button @click="toggleFullscreen" class="btn" aria-label="Toggle fullscreen">
                {{ isFullscreen ? 'Exit Fullscreen' : 'Fullscreen' }}
            </button>
            <button @click="share" class="btn btn-primary" aria-label="Share presentation">Share</button>
        </div>
    </div>
</template>

<script setup>
import { toRefs, h, ref } from 'vue';
import { success as showSuccess } from '@/Utils/notification';
import { Swiper as SwiperClass } from 'swiper';
import { Swiper, SwiperSlide } from 'swiper/vue';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import { Navigation, Pagination } from 'swiper/modules';
SwiperClass.use([Navigation, Pagination]);

const props = defineProps({ presentation: { type: Object, required: true } });
const { presentation } = toRefs(props);
const isFullscreen = ref(false);

function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value;
    if (isFullscreen.value) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

async function share() {
    const url = `${window.location.origin}/view/${presentation.value.share_token}`;
    await navigator.clipboard.writeText(url);
    showSuccess('Share link copied to clipboard');
}

function getRenderer(b) {
    const c = b.content_data || {};
    if (b.block_type === 'heading') {
        const Tag = `h${c.level || 2}`;
        return {
            render() {
                return h(Tag, { class: 'font-bold text-xl text-gray-900' }, c.text || '');
            },
        };
    }
    if (b.block_type === 'paragraph') {
        return {
            render() {
                return h('p', { class: 'text-gray-700 leading-relaxed' }, c.text || '');
            },
        };
    }
    if (b.block_type === 'feature_card') {
        return {
            render() {
                return h('div', { class: 'p-4 border rounded-lg bg-white shadow-sm' }, [
                    c.icon ? h('i', { class: `${c.icon} mr-2 text-indigo-500` }) : null,
                    h('div', { class: 'font-semibold text-gray-900' }, c.title || ''),
                    h('div', { class: 'text-sm text-gray-600' }, c.description || ''),
                ]);
            },
        };
    }
    if (b.block_type === 'image') {
        return {
            render() {
                const src = c.url || c.src || '';
                const alt = c.alt || 'Image';
                return h('img', {
                    src,
                    alt,
                    class: 'max-w-full h-auto rounded-md shadow-sm',
                    onError: (e) => {
                        const target = e?.target;
                        if (target) target.replaceWith(document.createTextNode('Image failed to load'));
                    },
                });
            },
        };
    }
    return {
        render() {
            return h('div', { class: 'text-red-500' }, `Unsupported: ${b.block_type}`);
        },
    };
}
</script>

<style scoped>
.btn {
    @apply px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors;
}
.btn-primary {
    @apply bg-indigo-600 text-white hover:bg-indigo-700;
}
.fullscreen {
    @apply fixed inset-0 z-50 bg-white;
}
</style>
