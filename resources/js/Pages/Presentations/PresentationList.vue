<template>
    <AuthenticatedLayout>
        <div class="bg-slate-50 min-h-full font-montserrat">
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <!-- Page Header -->
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between pb-8 border-b border-slate-200 mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800">Presentations</h1>
                        <p class="mt-1 text-slate-500">Create, manage, and share your client presentations.</p>
                    </div>
                    <button @click="openCreate()" class="btn-primary mt-4 md:mt-0 flex items-center gap-2" aria-label="Create new presentation">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                        New Presentation
                    </button>
                </div>

                <!-- Search & Filters -->
                <div class="mb-6">
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" /></svg>
                        <input
                            v-model="search"
                            placeholder="Search by title or client/lead..."
                            class="search-input"
                            aria-label="Search presentations"
                        />
                    </div>
                </div>

                <!-- Presentations Grid -->
                <div v-if="loading" class="text-center py-16">
                    <div class="flex items-center justify-center gap-3 text-slate-500">
                        <span class="loading-spinner" aria-hidden="true"></span>
                        <span>Loading Presentations...</span>
                    </div>
                </div>
                <div v-else-if="!filtered.length" class="text-center py-16 border-2 border-dashed border-slate-200 rounded-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mx-auto h-12 w-12 text-slate-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-800">No presentations found</h3>
                    <p class="mt-1 text-sm text-slate-500">Get started by creating a new presentation.</p>
                </div>
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="p in filtered" :key="p.id" class="bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col transition-all hover:shadow-md hover:-translate-y-1">
                        <div class="p-5 flex-grow">
                            <div class="flex items-start justify-between">
                                <span class="type-badge">{{ p.type }}</span>
                                <div class="relative group">
                                    <button class="icon-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" /></svg>
                                    </button>
                                    <!-- Dropdown Menu for actions -->
                                    <div class="dropdown-menu">
                                        <a @click="openSummaryModal(p.id)" class="dropdown-item">Manage Slides</a>
                                        <a @click="openSummaryModal(p.id)" class="dropdown-item">Duplicate...</a>
                                        <a @click="copyShare(p)" class="dropdown-item">Share</a>
                                        <div class="my-1 h-px bg-slate-100"></div>
                                        <a @click="destroy(p.id)" class="dropdown-item-danger">Delete</a>
                                    </div>
                                </div>
                            </div>
                            <h2 class="text-lg font-bold text-slate-800 truncate">{{ p.title }}</h2>
                            <p class="text-sm text-slate-500 mt-1">{{ p.presentable_name || p.presentable?.name || '-' }}</p>
                        </div>
                        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-400">Created: {{ formatDate(p.created_at) }}</span>
                            <button @click="goEdit(p.id)" class="btn-secondary">Edit</button>
                        </div>
                    </div>
                </div>
            </main>

            <CreationChoiceModal v-if="showChoice" @close="showChoice=false" @scratch="onScratch" @choose-template="onChooseTemplate" />
            <TemplateBrowser v-if="showTemplateBrowser" @close="showTemplateBrowser=false" @selected="onTemplateSelected" />
            <PresentationForm v-if="showCreate" :template-id="selectedTemplateId" :source-slide-ids="pendingSourceSlideIds" @close="showCreate = false" @created="onCreated" />
            <SlideSummaryModal v-if="showSlides" :presentation-id="activePresentationId" @close="showSlides=false" @created="onModalCreated" @copied="onSlidesCopied" />
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { success, error, confirmPrompt } from '@/Utils/notification';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PresentationForm from './PresentationForm.vue';
import CreationChoiceModal from './CreationChoiceModal.vue';
import TemplateBrowser from './TemplateBrowser.vue';
import SlideSummaryModal from './SlideSummaryModal.vue';
import api from '@/Services/presentationsApi';

const presentations = ref([]);
const loading = ref(false);
const showCreate = ref(false);
const showChoice = ref(false);
const showTemplateBrowser = ref(false);
const showSlides = ref(false);
const activePresentationId = ref(null);
const selectedTemplateId = ref(null);
const pendingSourceSlideIds = ref([]);
const search = ref('');
const duplicatingId = ref(null); // Retained for potential direct duplication actions in future

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const res = await api.list();
        presentations.value = Array.isArray(res) ? res : (res?.data ?? []);
    } catch (e) {
        error('Failed to load presentations');
    } finally {
        loading.value = false;
    }
}

const filtered = computed(() => {
    const q = search.value.toLowerCase();
    const list = Array.isArray(presentations.value) ? presentations.value : [];
    return list.filter(p =>
        (p.title || '').toLowerCase().includes(q) ||
        (p.presentable_name || p.presentable?.name || '').toLowerCase().includes(q)
    );
});

function formatDate(d) {
    if (!d) return '-';
    try { return new Date(d).toLocaleDateString(); } catch { return d; }
}

function goEdit(id) {
    router.visit(`/presentations/${id}/edit`);
}

function openCreate() {
    showChoice.value = true;
}

function onScratch() {
    showChoice.value = false;
    selectedTemplateId.value = null;
    pendingSourceSlideIds.value = [];
    showCreate.value = true;
}

function onChooseTemplate() {
    showChoice.value = false;
    showTemplateBrowser.value = true;
}

function onTemplateSelected(tpl) {
    showTemplateBrowser.value = false;
    selectedTemplateId.value = tpl?.id || null;
    pendingSourceSlideIds.value = [];
    showCreate.value = true;
}

// **FIX:** Consolidated function to open the summary modal for both managing slides and duplicating
function openSummaryModal(id) {
    activePresentationId.value = id;
    showSlides.value = true;
}

async function copyShare(p) {
    const url = `${window.location.origin}/view/${p.share_token || ''}`;
    await navigator.clipboard.writeText(url);
    success('Share link copied to clipboard');
}

async function destroy(id) {
    const ok = await confirmPrompt('Delete this presentation?', { confirmText: 'Delete', cancelText: 'Cancel', type: 'warning' });
    if (!ok) return;
    try {
        await api.destroy(id);
        presentations.value = presentations.value.filter(x => x.id !== id);
        success('Presentation deleted');
    } catch (e) {
        error('Failed to delete presentation');
    }
}

function onCreated(newP) {
    showCreate.value = false;
    selectedTemplateId.value = null;
    pendingSourceSlideIds.value = [];
    if (newP) presentations.value.unshift(newP);
}

function onModalCreated(payload) {
    showSlides.value = false;
    if (payload && payload._fromSelection) {
        selectedTemplateId.value = null;
        pendingSourceSlideIds.value = payload.source_slide_ids || [];
        showCreate.value = true;
    } else if (payload && payload.id) {
        presentations.value.unshift(payload);
    }
}

function onSlidesCopied() {
    showSlides.value = false;
}
</script>

<style scoped>
.font-montserrat { font-family: 'Montserrat', sans-serif; }

.btn-primary {
    padding: 0.625rem 1rem;
    background-color: #29438E;
    color: white;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    transition: background-color 0.2s;
}
.btn-primary:hover { background-color: #223670; }
.btn-primary:focus { outline: 2px solid transparent; outline-offset: 2px; box-shadow: 0 0 0 2px white, 0 0 0 4px #29438E; }

.btn-secondary {
    padding: 0.25rem 0.75rem;
    background-color: #f1f5f9;
    color: #334155;
    border-radius: 0.375rem;
    font-weight: 600;
    font-size: 0.75rem;
    transition: background-color 0.2s;
}
.btn-secondary:hover { background-color: #e2e8f0; }
.btn-secondary:focus { outline: 2px solid transparent; outline-offset: 1px; box-shadow: 0 0 0 2px white, 0 0 0 4px #29438E; }

.icon-btn {
    height: 2rem;
    width: 2rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    transition: background-color 0.2s, color 0.2s;
}
.icon-btn:hover { background-color: #f1f5f9; color: #475569; }

/* **FIX:** Dropdown menu styling for better usability */
.dropdown-menu {
    position: absolute;
    right: 0;
    margin-top: 0.25rem;
    width: 12rem;
    background-color: white;
    border-radius: 0.375rem;
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    border: 1px solid #e2e8f0;
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
    z-index: 10;
    opacity: 0;
    transform: scale(0.95);
    transition: opacity 150ms ease-out, transform 150ms ease-out;
    pointer-events: none;
}
.group:hover .dropdown-menu, .group:focus-within .dropdown-menu {
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
}

.dropdown-item { display: block; width: 100%; text-align: left; padding: 0.5rem 1rem; font-size: 0.875rem; color: #334155; cursor: pointer; }
.dropdown-item:hover { background-color: #f1f5f9; }

.dropdown-item-danger { display: block; width: 100%; text-align: left; padding: 0.5rem 1rem; font-size: 0.875rem; color: #dc2626; cursor: pointer; }
.dropdown-item-danger:hover { background-color: #fef2f2; }

.search-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.625rem 2.5rem; transition: box-shadow 0.2s, border-color 0.2s; }
.search-input:focus { outline: none; border-color: #29438E; box-shadow: 0 0 0 2px #29438E; }

.loading-spinner { display: inline-block; height: 1.5rem; width: 1.5rem; border: 2px solid #cbd5e1; border-top-color: #29438E; border-radius: 9999px; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.type-badge { display: inline-block; padding: 0.25rem 0.625rem; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; margin-bottom: 0.75rem; background-color: rgba(41, 67, 142, 0.1); color: #29438E; }
</style>

