<template>
    <AuthenticatedLayout>
        <div class="bg-slate-50 min-h-screen">
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <!-- Page Header -->
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between pb-8 border-b border-slate-200 mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800">Presentations</h1>
                        <p class="mt-1 text-slate-500">Create, manage, and share your client presentations.</p>
                    </div>
                    <button @click="openCreate()" class="mt-4 md:mt-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600" aria-label="Create new presentation">
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
                            class="w-full border border-slate-300 rounded-lg py-2 pl-10 pr-4 focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition-shadow"
                            aria-label="Search presentations"
                        />
                    </div>
                </div>

                <!-- Presentations Grid -->
                <div v-if="loading" class="text-center py-16">
                    <div class="flex items-center justify-center gap-3 text-slate-500">
                        <span class="inline-block h-6 w-6 border-2 border-slate-300 border-t-blue-600 rounded-full animate-spin" aria-hidden="true"></span>
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
                                <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full mb-3 bg-blue-100 text-blue-600">{{ p.type }}</span>
                                <div class="flex items-center gap-2">
                                    <button @click.stop="openCollaborators(p)" class="relative h-8 w-8 rounded-full flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-700" title="Collaborators">
                                        <!-- Users icon -->
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M12 12a4 4 0 100-8 4 4 0 000 8z"/><path fill-rule="evenodd" d="M2 20a8 8 0 1116 0v1H2v-1z" clip-rule="evenodd"/></svg>
                                        <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white bg-indigo-600 rounded-full" aria-label="Collaborators count">{{ p.users_count ?? 0 }}</span>
                                    </button>
                                    <div class="relative group" @mouseenter="openDropdown(p.id)" @mouseleave="closeDropdown(p.id)">
                                        <button class="h-8 w-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" /></svg>
                                        </button>
                                    <!-- Dropdown Menu for actions -->
                                    <div
                                        v-show="activeDropdownId === p.id"
                                        class="absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg border border-slate-200 py-1 z-10 transition-opacity duration-200"
                                        @mouseenter="keepDropdownOpen(p.id)"
                                        @mouseleave="leaveDropdown(p.id)"
                                    >
                                        <a @click="openSummaryModal(p.id)" class="block w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer">Duplicate...</a>
                                        <a @click="copyShare(p)" class="block w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer">Share</a>
                                        <a @click="beginRename(p)" class="block w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer">Rename</a>
                                        <div class="my-1 h-px bg-slate-100"></div>
                                        <a @click="destroy(p.id)" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 cursor-pointer">Delete</a>
                                    </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="renamingId === p.id" class="mt-1 flex items-center gap-2">
                                <input
                                    v-model="renameTitle"
                                    placeholder="New title"
                                    class="flex-1 border border-slate-300 rounded-lg p-1.5 text-sm focus:ring-2 focus:ring-blue-600 focus:border-blue-600"
                                    :aria-label="`Rename ${p.title}`"
                                    @keyup.enter="saveRename(p)"
                                    @keyup.esc="cancelRename"
                                    :disabled="savingRename"
                                />
                                <button @click="saveRename(p)" class="px-2 py-1 bg-blue-600 text-white rounded-md text-xs font-semibold hover:bg-blue-700 disabled:opacity-50" :disabled="savingRename" aria-label="Save new title">Save</button>
                                <button @click="cancelRename" class="px-2 py-1 bg-slate-100 text-slate-700 rounded-md text-xs font-semibold hover:bg-slate-200" aria-label="Cancel rename">Cancel</button>
                            </div>
                            <div v-else>
                                <h2 class="text-lg font-bold text-slate-800 truncate" :title="p.title">{{ p.title }}</h2>
                                <p class="text-sm text-slate-500 mt-1">{{ p.presentable_name || p.presentable?.name || '-' }}</p>
                            </div>
                        </div>
                        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-400">Created: {{ formatDate(p.created_at) }}</span>
                            <button @click="goEdit(p.id)" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-md text-xs font-semibold hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-600">Edit</button>
                        </div>
                    </div>
                </div>
            </main>

            <CreationChoiceModal v-if="showChoice" @close="showChoice=false" @scratch="onScratch" @choose-template="onChooseTemplate" @created="onCreated" />
            <TemplateBrowser v-if="showTemplateBrowser" @close="showTemplateBrowser=false" @selected="onTemplateSelected" />
            <PresentationForm v-if="showCreate" :template-id="selectedTemplateId" :source-slide-ids="pendingSourceSlideIds" @close="showCreate = false" @created="onCreated" />
            <SlideSummaryModal v-if="showSlides" :presentation-id="activePresentationId" @close="showSlides=false" @created="onModalCreated" @copied="onSlidesCopied" />
            <CollaborateModal v-if="showCollaborate" :show="showCollaborate" :presentation="activePresentation" @close="showCollaborate=false" @updated="onCollaboratorsUpdated" />
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
import CollaborateModal from '@/Pages/Presentations/Components/CollaborateModal.vue';
import api from '@/Services/presentationsApi';

const presentations = ref([]);
const loading = ref(false);
const showCreate = ref(false);
const showChoice = ref(false);
const showTemplateBrowser = ref(false);
const showSlides = ref(false);
const showCollaborate = ref(false);
const activePresentationId = ref(null);
const activePresentation = ref(null);
const selectedTemplateId = ref(null);
const pendingSourceSlideIds = ref([]);
const search = ref('');
const activeDropdownId = ref(null);
// Rename state
const renamingId = ref(null);
const renameTitle = ref('');
const savingRename = ref(false);

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

async function openCollaborators(p) {
    try {
        // Fetch full presentation with users for prefill
        const full = await api.get(p.id);
        activePresentation.value = full;
        showCollaborate.value = true;
    } catch (e) {
        error('Failed to load collaborators');
    }
}

function openSummaryModal(id) {
    activePresentationId.value = id;
    showSlides.value = true;
}

function openDropdown(id) {
    activeDropdownId.value = id;
}

function keepDropdownOpen(id) {
    activeDropdownId.value = id;
}

function closeDropdown(id) {
    setTimeout(() => {
        if(!activeDropdownId.value === id) {
            activeDropdownId.value = null
        }
    }, 500);
}

function leaveDropdown(id) {
    activeDropdownId.value = null;
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

function beginRename(p) {
    renamingId.value = p.id;
    renameTitle.value = p.title || '';
    activeDropdownId.value = null;
}

function cancelRename() {
    renamingId.value = null;
    renameTitle.value = '';
}

async function saveRename(p) {
    if (!p?.id) return;
    const newTitle = (renameTitle.value || '').trim();
    if (!newTitle) {
        error('Title cannot be empty');
        return;
    }
    savingRename.value = true;
    try {
        const updated = await api.update(p.id, { title: newTitle });
        const idx = presentations.value.findIndex(x => x.id === p.id);
        if (idx !== -1) {
            presentations.value[idx].title = updated?.title ?? newTitle;
        }
        success('Presentation renamed');
        renamingId.value = null;
        renameTitle.value = '';
    } catch (e) {
        error('Failed to rename presentation');
    } finally {
        savingRename.value = false;
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

function onCollaboratorsUpdated(collaborators) {
    // Update badge count on the active item
    try {
        const id = activePresentation.value?.id;
        const idx = presentations.value.findIndex(x => x.id === id);
        if (idx !== -1) {
            const count = Array.isArray(collaborators) ? collaborators.length : 0;
            // Laravel withCount exposes users_count
            presentations.value[idx].users_count = count;
        }
    } catch {}
    showCollaborate.value = false;
}
</script>
