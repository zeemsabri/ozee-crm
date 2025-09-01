<script setup>
import { defineProps, defineEmits, ref } from 'vue';

const props = defineProps({
    userId: String,
    activeSection: String, // Prop to control active state from parent
    hasWireframes: { type: Boolean, default: false },
    wireframeShareUrl: { type: String, default: '' }
});

const emits = defineEmits(['section-change']);

const handleClick = (sectionId) => {
    emits('section-change', sectionId);
};

// By default, the sidebar is collapsed
const isExpanded = ref(false);

const navItems = [
    { id: 'home', label: 'Dashboard', icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2 2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>` },
    { id: 'tickets', label: 'Tickets', icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>` },
    { id: 'approvals', label: 'Approvals', icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>` },
    { id: 'documents', label: 'Documents', icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>` },
    { id: 'resources', label: 'Resources', icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open-text w-5 h-5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h6z"/><path d="M10 12H6"/><path d="M14 12h4"/></svg>` },
    {
        id: 'seo',
        label: 'SEO Reports',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart-big">
            <path d="M12 20V10"/>
            <path d="M18 20V4"/>
            <path d="M6 20v-4"/>
            <line x1="12" x2="12" y1="20" y2="20"/>
            <line x1="18" x2="18" y1="20" y2="20"/>
            <line x1="6" x2="6" y1="20" y2="20"/>
        </svg>`
    },
    // { id: 'invoices', label: 'Invoices', icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>` },
    // { id: 'announcements', label: 'Announcements', icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592L6 18V6l3-3 2 2zm6-3v14.485c0 .085.033.166.098.221l.685.56a1.76 1.76 0 002.417-.592L21 6V3l-3 3-2-2z"/></svg>` },
];

// Safe opener for wireframes link to avoid window undefined errors (SSR/hydration)
const openWireframes = () => {
    try {
        const url = props.wireframeShareUrl;
        if (!url) return;
        // Prefer globalThis.open when available, fallback to window?.open
        const opener = (typeof globalThis !== 'undefined' && typeof globalThis.open === 'function')
            ? globalThis.open
            : (typeof window !== 'undefined' && typeof window.open === 'function' ? window.open : null);
        if (opener) {
            opener(url, '_blank');
        }
    } catch (e) {
        // Swallow errors to prevent crashing the app in constrained environments
        // Optionally, could emit an event or console.warn
        console.warn('Failed to open wireframes link:', e);
    }
};

</script>

<template>
    <div
        class="sidebar bg-white shadow-lg flex flex-col transition-all duration-300 z-10"
        :class="[isExpanded ? 'expanded' : 'collapsed']"
        @mouseenter="isExpanded = true"
        @mouseleave="isExpanded = false"
    >
        <h1 class="text-2xl font-bold text-gray-800 mb-8 whitespace-nowrap overflow-hidden h-20 flex items-center">
            <span v-if="isExpanded" class="flex h-full items-center">
                <img src="/logo.png" alt="OZEE Logo" class="h-full object-contain">
            </span>
                    <span v-else class="flex h-full items-center justify-center w-full">
                <img src="/logo_sm.png" alt="OZEE logo" class="h-20 object-contain">
            </span>
        </h1>
        <nav class="flex-1">
            <button
                v-for="item in navItems"
                :key="item.id"
                @click="handleClick(item.id)"
                :class="['block w-full text-left py-3 px-1 mb-2 rounded-lg text-gray-700 font-medium flex items-center',
                        {'active bg-blue-100 text-blue-700': props.activeSection === item.id}]"
            >
                <span v-html="item.icon" :class="['icon-container', {'mr-3': isExpanded}]"></span>
                <span class="label-text whitespace-nowrap overflow-hidden" :class="{'opacity-0 w-0': !isExpanded, 'w-full': isExpanded}">
                    {{ item.label }}
                </span>
            </button>

            <!-- Conditionally render Wireframes external link -->
            <button
                v-if="props.hasWireframes && props.wireframeShareUrl"
                @click="openWireframes"
                class="block w-full text-left py-3 px-1 mb-2 rounded-lg text-gray-700 font-medium flex items-center hover:bg-blue-50"
            >
                <span class="icon-container" :class="{'mr-3': isExpanded}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h6m0 0v6m0-6l-8 8M7 7v10a2 2 0 002 2h10"/></svg>
                </span>
                <span class="label-text whitespace-nowrap overflow-hidden" :class="{'opacity-0 w-0': !isExpanded, 'w-full': isExpanded}">
                    Wireframes
                </span>
            </button>
        </nav>
        <div class="mt-auto text-sm text-gray-500 whitespace-nowrap overflow-hidden px-4 pb-2">
            <p v-if="isExpanded">User ID: <span id="user-id-display">{{ props.userId }}</span></p>
            <p v-else class="flex items-center justify-center h-8">ID</p>
        </div>
    </div>
</template>

<style scoped>
/* Sidebar states */
.sidebar {
    position: fixed; /* Use fixed position */
    height: 100vh; /* Full viewport height */
    top: 0;
    left: 0;
    bottom: 0;
    padding-top: 1.5rem; /* Consistent padding at top */
}

.collapsed {
    width: 4rem; /* 64px */
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}

.expanded {
    width: 16rem; /* 256px */
    padding-left: 1.5rem;
    padding-right: 1.5rem;
    box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1); /* Add shadow for better visibility when expanded */
}

/* Icon container */
.icon-container {
    display: flex; /* Use flexbox for centering */
    justify-content: center; /* Center horizontally */
    align-items: center; /* Center vertically */
    flex-shrink: 0; /* Prevent icon from shrinking */
    width: 2rem; /* Give a fixed width for the icon area */
    height: 2rem; /* Give a fixed height for the icon area */
}

/* Label text */
.label-text {
    transition: opacity 0.2s ease, width 0.2s ease; /* Animate width as well */
}

/* Styles for active sidebar button */
.active {
    background-color: #DBEAFE; /* blue-100 */
    color: #1D4ED8; /* blue-700 */
    font-weight: 600;
}

/* Ensure consistent button padding to keep icons aligned */
.sidebar nav button {
    padding-top: 0.75rem; /* py-3 equivalent */
    padding-bottom: 0.75rem; /* py-3 equivalent */
}

.collapsed .sidebar nav button {
    padding-left: 0.25rem; /* Adjust as needed */
    padding-right: 0.25rem; /* Adjust as needed */
}
</style>
