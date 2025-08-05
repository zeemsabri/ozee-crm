<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';

// --- ICONS (Expanded) ---
const ICONS = {
    chevronLeft: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>`,
    chevronRight: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>`,
    hero: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="10" x="3" y="11" rx="2"/><path d="m7 11-4 4"/><path d="M21 11.1c.33.34.66.68 1 1"/><path d="m21 16-4-4"/><path d="M3 21h18"/><path d="M12 3v8"/></svg>`,
    image: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`,
    text: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 6.1H3"/><path d="M21 12.1H3"/><path d="M15.1 18.1H3"/></svg>`,
    button: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="10" x="3" y="7" rx="2"/><path d="M8 12h8"/></svg>`,
    input: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="10" rx="2"/><path d="M8 14h.01"/></svg>`,
    navbar: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="6" x="2" y="4" rx="1"/><path d="M6 8h.01"/><path d="M10 8h.01"/><path d="M14 8h.01"/></svg>`,
    trash: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>`,
    copy: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>`,
    upload: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>`,
    download: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>`,
    refresh: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9c-2.4 0-4.6 1-6.2 2.7l-4.7 4.7"/><path d="M11 19l-4.7-4.7C4.1 12.6 3 10.4 3 8a9 9 0 0 1 9-9c2.4 0 4.6 1 6.2 2.7l1.5 1.5"/><path d="M22 12a9 9 0 0 1-9 9c-2.4 0-4.6-1-6.2-2.7l-4.7-4.7"/><path d="M11 5l-4.7 4.7C4.1 11.4 3 13.6 3 16a9 9 0 0 0 9 9c2.4 0 4.6-1 6.2-2.7l1.5-1.5"/></svg>`,
    container: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>`,
    heading: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h12M6 5v14m12-14v14"/></svg>`,
    paragraph: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18M3 7h18M3 17h18"/></svg>`,
    label: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 20h4M12 20V4M5 16h14"></path></svg>`,
    link: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>`,
    hr: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line></svg>`,
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>`,
    footer: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="14" width="18" height="7" rx="2"></rect><line x1="7" y1="17" x2="17" y2="17"></line></svg>`,
    sidebar: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>`,
    tabs: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><path d="M3 9h18"></path><path d="M8 3v6"></path><path d="M16 3v6"></path></svg>`,
    textarea: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18M3 7h18M3 17h12"/></svg>`,
    checkbox: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>`,
    radio: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="4"></circle></svg>`,
    select: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="6" rx="2"></rect><path d="m6 8 6 6 6-6"></path></svg>`,
    card: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20"></path></svg>`,
    table: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line><line x1="12" y1="3" x2="12" y2="21"></line></svg>`,
    modal: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"></rect><path d="M9 7h6"></path><path d="M9 11h6"></path><path d="M9 15h4"></path></svg>`,
    avatar: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"></circle><path d="M20 21a8 8 0 0 0-16 0"></path></svg>`,
    accordion: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="5" rx="1"></rect><rect x="3" y="10" width="18" height="5" rx="1"></rect><rect x="3" y="17" width="18" height="5" rx="1"></rect></svg>`,
    carousel: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="18" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line><polyline points="8 15 12 11 16 15"></polyline></svg>`,
    player: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"></rect><path d="M12 11V7l5 4-5 4v-4"></path></svg>`,
    pricing: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"></path><path d="M17 17H7"></path><path d="M17 7H7"></path><path d="M22 12H2"></path></svg>`,
    testimonial: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11H7a4 4 0 1 0 0 8h3c1.1 0 2-.9 2-2V7c0-2.2-1.8-4-4-4H4"/><path d="M18 11h-3a4 4 0 1 0 0 8h3c1.1 0 2-.9 2-2V7c0-2.2-1.8-4-4-4h-3"/></svg>`,
    device: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>`,
    list: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>`,
};

// --- COMPONENT DEFINITIONS (Expanded) ---
const COMPONENT_DEFS = {
    Container: { category: 'Basic UI & Primitives', name: "Container", icon: ICONS.container, default: { size: { width: 400, height: 200 }, content: {} }, render: () => `<div class="h-full w-full bg-slate-100 border-2 border-dashed border-slate-400 rounded-lg"></div>` },
    Heading: { category: 'Basic UI & Primitives', name: "Heading", icon: ICONS.heading, default: { size: { width: 300, height: 50 }, content: { text: "Main Heading" } }, render: ({ content }) => `<div class="p-2"><h1 class="text-2xl font-bold text-slate-800 truncate">${content.text}</h1></div>` },
    Paragraph: { category: 'Basic UI & Primitives', name: "Paragraph", icon: ICONS.paragraph, default: { size: { width: 300, height: 100 }, content: { text: "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua." } }, render: ({ content }) => `<div class="p-2"><p class="text-sm text-slate-700">${content.text}</p></div>` },
    Label: { category: 'Basic UI & Primitives', name: "Label", icon: ICONS.label, default: { size: { width: 100, height: 20 }, content: { text: "Label:" } }, render: ({ content }) => `<div class="p-1"><span class="text-xs font-semibold text-slate-600">${content.text}</span></div>` },
    Link: { category: 'Basic UI & Primitives', name: "Link", icon: ICONS.link, default: { size: { width: 100, height: 20 }, content: { text: "Clickable Link" } }, render: ({ content }) => `<div class="p-1"><a href="#" class="text-blue-500 hover:underline">${content.text}</a></div>` },
    ImagePlaceholder: { category: 'Basic UI & Primitives', name: "Image Placeholder", icon: ICONS.image, default: { size: { width: 300, height: 200 }, content: { alt: "Image Placeholder" } }, render: ({ content }) => `<div class="flex items-center justify-center h-full w-full p-4 border-2 border-dashed border-slate-400 bg-slate-100 rounded-lg text-slate-500 font-sans text-sm"><div class="text-center">${ICONS.image}<span class="mt-2 block">${content.alt}</span></div></div>` },
    Icon: { category: 'Basic UI & Primitives', name: "Icon", icon: ICONS.icon, default: { size: { width: 40, height: 40 }, content: {} }, render: () => `<div class="flex items-center justify-center h-full w-full text-slate-500">${ICONS.icon}</div>` },
    HorizontalRule: { category: 'Basic UI & Primitives', name: "Horizontal Rule", icon: ICONS.hr, default: { size: { width: 400, height: 10 }, content: {} }, render: () => `<div class="flex items-center justify-center h-full w-full p-1"><div class="h-0.5 w-full bg-slate-400"></div></div>` },

    Navbar: { category: 'Navigation', name: "Navbar", icon: ICONS.navbar, default: { size: { width: 800, height: 60 }, content: { logo: "Logo", links: "Home,About,Services,Contact" } }, render: ({ content }) => `<div class="flex items-center justify-between h-full w-full bg-slate-100 rounded-lg text-sm font-medium border-2 border-dashed border-slate-400 px-4"><div class="font-bold text-slate-800">${content.logo}</div><div class="flex gap-4 text-slate-600">${content.links.split(',').map(link => `<span>${link.trim()}</span>`).join('')}</div></div>` },
    Footer: { category: 'Navigation', name: "Footer", icon: ICONS.footer, default: { size: { width: 800, height: 100 }, content: { text: "© 2023 MyCompany. All rights reserved." } }, render: ({ content }) => `<div class="flex items-center justify-center h-full w-full bg-slate-100 rounded-lg text-xs font-medium border-2 border-dashed border-slate-400 px-4 text-slate-600">${content.text}</div>` },
    Sidebar: { category: 'Navigation', name: "Sidebar", icon: ICONS.sidebar, default: { size: { width: 200, height: 500 }, content: { links: "Dashboard,Profile,Settings" } }, render: ({ content }) => `<div class="h-full w-full bg-slate-100 rounded-lg border-2 border-dashed border-slate-400 p-4"><h4 class="font-semibold text-slate-800 mb-2">Sidebar</h4><div class="flex flex-col gap-1 text-sm text-slate-600">${content.links.split(',').map(link => `<span class="py-1 px-2 hover:bg-slate-200 rounded">${link.trim()}</span>`).join('')}</div></div>` },
    Tabs: { category: 'Navigation', name: "Tabs", icon: ICONS.tabs, default: { size: { width: 400, height: 50 }, content: { tabs: "Tab 1,Tab 2,Tab 3" } }, render: ({ content }) => `<div class="flex h-full w-full rounded-lg text-sm border-2 border-dashed border-slate-400 p-1 bg-slate-100">${content.tabs.split(',').map((tab, i) => `<button class="flex-grow flex items-center justify-center px-4 rounded transition-colors ${i === 0 ? 'bg-white text-slate-800 font-semibold' : 'text-slate-600 hover:bg-slate-200'}">${tab.trim()}</button>`).join('')}</div>` },

    TextInput: { category: 'Forms & Input', name: "Text Input", icon: ICONS.input, default: { size: { width: 240, height: 40 }, content: { placeholder: "Enter your email" } }, render: ({ content }) => `<div class="flex items-center h-full w-full bg-white rounded-md text-sm text-slate-500 border-2 border-dashed border-slate-400 px-3">${content.placeholder}</div>` },
    Textarea: { category: 'Forms & Input', name: "Text Area", icon: ICONS.textarea, default: { size: { width: 240, height: 80 }, content: { placeholder: "Enter your message" } }, render: ({ content }) => `<div class="h-full w-full bg-white rounded-md text-sm text-slate-500 border-2 border-dashed border-slate-400 p-3">${content.placeholder}</div>` },
    Dropdown: { category: 'Forms & Input', name: "Dropdown", icon: ICONS.select, default: { size: { width: 180, height: 40 }, content: { text: "Choose an option" } }, render: ({ content }) => `<div class="flex items-center justify-between h-full w-full bg-white rounded-md text-sm text-slate-700 border-2 border-dashed border-slate-400 px-3"><span>${content.text}</span><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></div>` },
    Checkbox: { category: 'Forms & Input', name: "Checkbox", icon: ICONS.checkbox, default: { size: { width: 150, height: 24 }, content: { label: "I agree to the terms" } }, render: ({ content }) => `<div class="flex items-center gap-2 p-1"><div class="w-5 h-5 border-2 border-slate-400 rounded"></div><span class="text-sm text-slate-700">${content.label}</span></div>` },
    RadioButton: { category: 'Forms & Input', name: "Radio Button", icon: ICONS.radio, default: { size: { width: 150, height: 24 }, content: { label: "Select this option" } }, render: ({ content }) => `<div class="flex items-center gap-2 p-1"><div class="w-5 h-5 border-2 border-slate-400 rounded-full"></div><span class="text-sm text-slate-700">${content.label}</span></div>` },
    DatePicker: { category: 'Forms & Input', name: "Date Picker", icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>`, default: { size: { width: 180, height: 40 }, content: { text: "Select a date" } }, render: ({ content }) => `<div class="flex items-center h-full w-full bg-white rounded-md text-sm text-slate-500 border-2 border-dashed border-slate-400 px-3 justify-between"><span>${content.text}</span><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>` },
    FileUploader: { category: 'Forms & Input', name: "File Uploader", icon: ICONS.upload, default: { size: { width: 240, height: 60 }, content: { text: "Drag & drop files or click to upload" } }, render: ({ content }) => `<div class="flex flex-col items-center justify-center h-full w-full bg-slate-100 rounded-md text-sm text-slate-500 border-2 border-dashed border-slate-400 p-3"><div class="w-6 h-6">${ICONS.upload}</div><span class="mt-2 text-xs text-center">${content.text}</span></div>` },
    PrimaryButton: { category: 'Buttons & Calls to Action', name: "Primary Button", icon: ICONS.button, default: { size: { width: 150, height: 40 }, content: { text: "Submit" } }, render: ({ content }) => `<div class="flex items-center justify-center h-full w-full bg-blue-600 text-white rounded-md text-sm font-semibold">${content.text}</div>` },

    Card: { category: 'Content Display', name: "Card", icon: ICONS.card, default: { size: { width: 250, height: 300 }, content: { title: "Card Title", text: "Some quick example text to build on the card title.", button: "Read more" } }, render: ({ content }) => `<div class="h-full w-full bg-white border-2 border-dashed border-slate-400 rounded-lg shadow-sm p-4 flex flex-col"><div class="h-32 bg-slate-200 rounded mb-4"></div><h4 class="font-bold text-slate-800 mb-1">${content.title}</h4><p class="text-xs text-slate-600 flex-grow">${content.text}</p><div class="flex justify-end pt-2"><div class="px-3 py-1 bg-slate-800 text-white rounded text-xs">${content.button}</div></div></div>` },
    Accordion: { category: 'Content Display', name: "Accordion", icon: ICONS.accordion, default: { size: { width: 400, height: 200 }, content: { title1: "Item 1", text1: "Content for item 1", title2: "Item 2", text2: "Content for item 2" } }, render: ({ content }) => `<div class="h-full w-full bg-white border-2 border-dashed border-slate-400 rounded-lg p-2"><div class="p-2 border-b-2 border-dashed border-slate-300 flex justify-between items-center"><span class="font-semibold text-sm">${content.title1}</span><span>+</span></div><div class="p-2 border-b-2 border-dashed border-slate-300 flex justify-between items-center"><span class="font-semibold text-sm">${content.title2}</span><span>+</span></div></div>` },
    Table: { category: 'Content Display', name: "Table", icon: ICONS.table, default: { size: { width: 500, height: 200 }, content: { headers: "Col 1,Col 2,Col 3", rows: "Val A,Val B,Val C\\nVal D,Val E,Val F" } }, render: ({ content }) => `<div class="h-full w-full border-2 border-dashed border-slate-400 rounded-lg p-2 text-xs"><table class="w-full"><thead><tr class="border-b-2 border-dashed border-slate-400">${content.headers.split(',').map(h => `<th class="p-1 text-left font-bold">${h.trim()}</th>`).join('')}</tr></thead><tbody>${content.rows.split('\\n').map(row => `<tr class="border-b border-dashed border-slate-300">${row.split(',').map(cell => `<td class="p-1">${cell.trim()}</td>`).join('')}</tr>`).join('')}</tbody></table></div>` },
    List: { category: 'Content Display', name: "List", icon: ICONS.list, default: { size: { width: 250, height: 150 }, content: { items: "Item 1,Item 2,Item 3,Item 4" } }, render: ({ content }) => `<div class="h-full w-full p-4 border-2 border-dashed border-slate-400 rounded-lg bg-slate-100"><ul class="list-disc list-inside text-sm text-slate-700 space-y-1">${content.items.split(',').map(item => `<li>${item.trim()}</li>`).join('')}</ul></div>` },
    ImageCarousel: { category: 'Content Display', name: "Image Carousel", icon: ICONS.carousel, default: { size: { width: 600, height: 300 }, content: {} }, render: () => `<div class="h-full w-full relative bg-slate-100 border-2 border-dashed border-slate-400 rounded-lg flex items-center justify-between p-4"><div class="w-8 h-8 rounded-full bg-slate-400 flex items-center justify-center text-white"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></div><div class="w-full h-full bg-slate-200 m-4 flex items-center justify-center text-sm text-slate-500">Carousel Item</div><div class="w-8 h-8 rounded-full bg-slate-400 flex items-center justify-center text-white"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></div></div>` },
    MediaPlayer: { category: 'Content Display', name: "Media Player", icon: ICONS.player, default: { size: { width: 400, height: 250 }, content: {} }, render: () => `<div class="h-full w-full bg-black rounded-lg flex items-center justify-center text-white text-sm">Media Player Placeholder</div>` },
    Avatar: { category: 'Content Display', name: "Avatar", icon: ICONS.avatar, default: { size: { width: 80, height: 80 }, content: {} }, render: () => `<div class="h-full w-full bg-slate-200 border-2 border-dashed border-slate-400 rounded-full flex items-center justify-center text-slate-400">${ICONS.avatar}</div>` },

    Modal: { category: 'Feedback & Overlays', name: "Modal", icon: ICONS.modal, default: { size: { width: 400, height: 250 }, content: { title: "Modal Title", text: "This is the modal body text." } }, render: ({ content }) => `<div class="h-full w-full bg-white border-2 border-dashed border-slate-400 rounded-lg shadow-lg p-4 flex flex-col"><h4 class="font-bold text-slate-800 mb-2 pb-2 border-b-2 border-dashed border-slate-300">${content.title}</h4><p class="text-sm text-slate-600 py-2 flex-grow">${content.text}</p><div class="flex justify-end gap-2 pt-2 border-t-2 border-dashed border-slate-300"><div class="px-3 py-1 bg-slate-200 rounded text-sm">Cancel</div><div class="px-3 py-1 bg-slate-800 text-white rounded text-sm">Action</div></div></div>` },

    HeroSection: { category: 'Advanced & Pre-built Structures', name: "Hero Section", icon: ICONS.hero, default: { size: { width: 700, height: 300 }, content: { heading: "Welcome to Our Site", subheading: "Your amazing subtitle goes here.", buttonText: "Learn More" } }, render: ({ content }) => `<div class="flex flex-col items-center justify-center h-full p-6 text-center bg-slate-100 border-2 border-dashed border-slate-400 rounded-lg"><h3 class="text-2xl font-bold text-slate-800">${content.heading}</h3><p class="text-md text-slate-600 mt-2">${content.subheading}</p><div class="mt-4 px-4 py-2 bg-slate-800 text-white rounded-md text-sm font-semibold">${content.buttonText}</div></div>` },
    PricingTable: { category: 'Advanced & Pre-built Structures', name: "Pricing Table", icon: ICONS.pricing, default: { size: { width: 800, height: 400 }, content: { tiers: "Basic:Free,Pro:$10/mo,Enterprise:Custom" } }, render: ({ content }) => `<div class="h-full w-full grid grid-cols-3 gap-4 p-4 border-2 border-dashed border-slate-400 rounded-lg bg-slate-100">${content.tiers.split(',').map((tier, i) => `<div class="flex flex-col items-center justify-center bg-white rounded-lg p-4 border border-dashed border-slate-300"><span class="text-lg font-bold">${tier.split(':')[0]}</span><span class="text-3xl font-extrabold text-blue-600 mt-2">${tier.split(':')[1]}</span><ul class="list-disc list-inside text-xs text-slate-600 mt-4 space-y-1"><li>Feature 1</li><li>Feature 2</li></ul><div class="mt-4 px-4 py-2 bg-slate-800 text-white rounded-md text-sm font-semibold">Sign up</div></div>`).join('')}</div>` },
    TestimonialBlock: { category: 'Advanced & Pre-built Structures', name: "Testimonial Block", icon: ICONS.testimonial, default: { size: { width: 500, height: 150 }, content: { quote: '"This tool is amazing and has changed the way I work."', author: "Jane Doe, CEO" } }, render: ({ content }) => `<div class="h-full w-full flex items-center gap-4 p-4 bg-slate-100 border-2 border-dashed border-slate-400 rounded-lg"><div class="w-16 h-16 rounded-full bg-slate-200 flex-shrink-0"></div><div class="flex flex-col"><p class="italic text-sm text-slate-700">${content.quote}</p><span class="mt-2 text-xs font-bold text-slate-800">- ${content.author}</span></div></div>` },
    DeviceFrame: { category: 'Advanced & Pre-built Structures', name: "Device Frame", icon: ICONS.device, default: { size: { width: 375, height: 667 }, content: { device: "Mobile" } }, render: ({ content }) => `<div class="h-full w-full p-2 bg-black border-4 border-slate-800 rounded-[2rem] flex items-center justify-center text-white text-xs"><span>${content.device} Frame</span></div>` },
};

function getInitialState() {
    return { components: [], selectedIds: [], canvasSize: { width: 1280, height: 720 }, view: { scale: 1, panOffset: { x: 0, y: 0 } } };
}

// --- STATE MANAGEMENT ---
function useWireframeState() {
    const state = reactive({
        history: [getInitialState()],
        historyIndex: 0
    });

    const currentFrame = computed(() => state.history[state.historyIndex]);
    const canUndo = computed(() => state.historyIndex > 0);
    const canRedo = computed(() => state.historyIndex < state.history.length - 1);

    const setState = (newStateFn, isTransient = false) => {
        const currentState = state.history[state.historyIndex];
        const newState = typeof newStateFn === 'function' ? newStateFn(currentState) : newStateFn;

        if (isTransient) {
            state.history[state.historyIndex] = newState;
        } else {
            const newHistory = state.history.slice(0, state.historyIndex + 1);
            newHistory.push(newState);
            state.history = newHistory;
            state.historyIndex = newHistory.length - 1;
        }
    };

    const undo = () => { if(canUndo.value) state.historyIndex-- };
    const redo = () => { if(canRedo.value) state.historyIndex++ };
    const reset = () => {
        state.history = [getInitialState()];
        state.historyIndex = 0;
    };

    return { state: currentFrame, setState, undo, redo, reset, canUndo, canRedo };
}

// Global state and methods
const { state, setState, undo, redo, reset, canUndo, canRedo } = useWireframeState();
const isLeftSidebarCollapsed = ref(false);
const isRightSidebarCollapsed = ref(true);
const mainContainerRef = ref(null);
const interaction = ref({});
const marquee = ref(null);
const isPanning = ref(false);
const showResetConfirm = ref(false);
const GRID_SIZE = 20;

// Computed properties from state
const components = computed(() => state.value.components);
const selectedIds = computed(() => state.value.selectedIds);
const canvasSize = computed(() => state.value.canvasSize);
const view = computed(() => state.value.view);
const scale = computed(() => view.value.scale);
const panOffset = computed(() => view.value.panOffset);

// --- HELPER FUNCTIONS ---
const worldToScreen = (pos) => ({ x: pos.x * scale.value + panOffset.value.x, y: pos.y * scale.value + panOffset.value.y });
const screenToWorld = (pos) => ({ x: (pos.x - panOffset.value.x) / scale.value, y: (pos.y - panOffset.value.y) / scale.value });

// --- MAIN LOGIC ---
const addComponent = (type) => {
    const def = COMPONENT_DEFS[type];
    if (!def || !mainContainerRef.value) return;
    const { width, height } = mainContainerRef.value.getBoundingClientRect();
    const centerInScreen = { x: width / 2, y: height / 2 };
    const centerInWorld = screenToWorld(centerInScreen);
    const newPosition = { x: Math.round(centerInWorld.x / GRID_SIZE) * GRID_SIZE, y: Math.round(centerInWorld.y / GRID_SIZE) * GRID_SIZE };
    setState(s => ({ ...s, components: [...s.components, { id: crypto.randomUUID(), type, position: newPosition, ...def.default }], selectedIds: [] }));
};

const updateComponent = (id, updates, isTransient = false) => {
    setState(s => ({ ...s, components: s.components.map(c => c.id === id ? { ...c, ...updates } : c) }), isTransient);
};

const deleteComponent = () => {
    setState(s => ({ ...s, components: s.components.filter(c => !s.selectedIds.includes(c.id)), selectedIds: [] }));
};

const duplicateComponent = (id) => {
    setState(s => {
        const newComps = s.selectedIds.map(id => {
            const sourceComp = s.components.find(c => c.id === id);
            if (!sourceComp) return null;
            return { ...sourceComp, id: crypto.randomUUID(), position: { x: sourceComp.position.x + GRID_SIZE, y: sourceComp.position.y + GRID_SIZE } };
        }).filter(Boolean);
        return { ...s, components: [...s.components, ...newComps], selectedIds: newComps.map(c => c.id) };
    });
};

const handleMouseMove = (e) => {
    const { type, dragStart, startPos, marqueeStart, id, direction, initialRect, originalPositions } = interaction.value;
    if (!type) return;

    if (type === 'pan') {
        const dx = e.clientX - dragStart.x;
        const dy = e.clientY - dragStart.y;
        setState(s => ({ ...s, view: { ...s.view, panOffset: { x: s.view.panOffset.x + dx, y: s.view.panOffset.y + dy } } }), true);
        interaction.value.dragStart = { x: e.clientX, y: e.clientY };
    } else if (type === 'drag') {
        const dx = (e.clientX - dragStart.x) / scale.value;
        const dy = (e.clientY - dragStart.y) / scale.value;
        setState(s => ({
            ...s,
            components: s.components.map(c => {
                if (s.selectedIds.includes(c.id)) {
                    const originalPos = originalPositions[c.id];
                    return { ...c, position: { x: Math.round((originalPos.x + dx) / GRID_SIZE) * GRID_SIZE, y: Math.round((originalPos.y + dy) / GRID_SIZE) * GRID_SIZE } };
                }
                return c;
            })
        }), true);
    } else if (type === 'resize') {
        const dx = (e.clientX - startPos.x) / scale.value;
        const dy = (e.clientY - startPos.y) / scale.value;
        let { x, y, width, height } = initialRect;
        if (direction.includes('right')) width = initialRect.width + dx;
        if (direction.includes('left')) { width = initialRect.width - dx; x = initialRect.x + dx; }
        if (direction.includes('bottom')) height = initialRect.height + dy;
        if (direction.includes('top')) { height = initialRect.height - dy; y = initialRect.y + dy; }
        width = Math.max(GRID_SIZE * 2, Math.round(width / GRID_SIZE) * GRID_SIZE);
        height = Math.max(GRID_SIZE * 2, Math.round(height / GRID_SIZE) * GRID_SIZE);
        x = Math.round(x / GRID_SIZE) * GRID_SIZE;
        y = Math.round(y / GRID_SIZE) * GRID_SIZE;
        updateComponent(id, { position: { x, y }, size: { width, height } }, true);
    } else if (type === 'marquee') {
        const rect = mainContainerRef.value.getBoundingClientRect();
        marquee.value = { x: Math.min(e.clientX - rect.left, marqueeStart.x - rect.left), y: Math.min(e.clientY - rect.top, marqueeStart.y - rect.top), width: Math.abs(e.clientX - marqueeStart.x), height: Math.abs(e.clientY - marqueeStart.y) };
    }
};

const handleMouseUp = (e) => {
    const { type, marqueeStart } = interaction.value;
    if (type === 'marquee') {
        const rect = mainContainerRef.value.getBoundingClientRect();
        const marqueeRect = { x: Math.min(e.clientX - rect.left, marqueeStart.x - rect.left), y: Math.min(e.clientY - rect.top, marqueeStart.y - rect.top), width: Math.abs(e.clientX - marqueeStart.x), height: Math.abs(e.clientY - marqueeStart.y) };
        const selected = components.value.filter(c => {
            const compRect = { ...worldToScreen(c.position), width: c.size.width * scale.value, height: c.size.height * scale.value };
            return compRect.x < marqueeRect.x + marqueeRect.width && compRect.x + compRect.width > marqueeRect.x && compRect.y < marqueeRect.y + marqueeRect.height && compRect.y + compRect.height > marqueeRect.y;
        }).map(c => c.id);
        setState(s => ({ ...s, selectedIds: selected }));
        marquee.value = null;
    }
    if (type === 'drag' || type === 'resize') {
        setState(s => ({...s}), false); // Commit final position/size
    }
    interaction.value = {};
    window.removeEventListener('mousemove', handleMouseMove);
    window.removeEventListener('mouseup', handleMouseUp);
};

const handleCanvasMouseDown = (e) => {
    if (isPanning.value || e.button !== 0) return;
    interaction.value = { type: 'marquee', marqueeStart: { x: e.clientX, y: e.clientY } };
    setState(s => ({ ...s, selectedIds: [] }));
    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseup', handleMouseUp);
};

const handleComponentMouseDown = (e, id) => {
    e.stopPropagation();
    if (isPanning.value || e.button !== 0) return;

    let newSelectedIds = [...selectedIds.value];
    if (e.shiftKey) {
        if (newSelectedIds.includes(id)) {
            newSelectedIds = newSelectedIds.filter(sid => sid !== id);
        } else {
            newSelectedIds.push(id);
        }
    } else {
        newSelectedIds = [id];
    }
    setState(s => ({ ...s, selectedIds: newSelectedIds }));

    interaction.value = {
        type: 'drag',
        dragStart: { x: e.clientX, y: e.clientY },
        originalPositions: components.value.reduce((acc, c) => ({...acc, [c.id]: c.position}), {})
    };
    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseup', handleMouseUp);
};

const handleResizeStart = (e, id, direction) => {
    e.stopPropagation();
    if (isPanning.value || e.button !== 0) return;
    const component = components.value.find(c => c.id === id);
    if (!component) return;
    interaction.value = {
        type: 'resize', id, direction, startPos: { x: e.clientX, y: e.clientY },
        initialRect: { ...component.position, ...component.size }
    };
    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseup', handleMouseUp);
};

const handleWheel = (e) => {
    if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        const zoomFactor = 1.1;
        const newScale = e.deltaY > 0 ? scale.value / zoomFactor : scale.value * zoomFactor;
        const rect = mainContainerRef.value.getBoundingClientRect();
        const mousePos = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        const mouseBeforeZoom = screenToWorld(mousePos);
        const newPanOffset = { x: mousePos.x - mouseBeforeZoom.x * newScale, y: mousePos.y - mouseBeforeZoom.y * newScale };
        setState(s => ({ ...s, view: { scale: newScale, panOffset: newPanOffset } }), true);
    }
};

const handleExport = () => {
    const jsonString = JSON.stringify(state.value, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `wireframe-export.json`;
    a.click();
    URL.revokeObjectURL(a.href);
};

const handleImport = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'application/json';
    input.onchange = e => {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (event) => {
            try {
                const importedState = JSON.parse(event.target.result);
                if (importedState.components && importedState.canvasSize) {
                    setState(importedState);
                } else {
                    // Using a custom message box instead of alert() or console.error
                    console.error('Invalid wireframe file format.');
                }
            } catch (err) {
                // Using a custom message box instead of alert() or console.error
                console.error('Error reading JSON file.');
            }
        };
        reader.readAsText(file);
    };
    input.click();
};

const handleReset = () => {
    reset();
    showResetConfirm.value = false;
};

const handleKeyDown = (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (selectedIds.value.length > 0 && (e.key === 'Delete' || e.key === 'Backspace')) {
        e.preventDefault();
        deleteComponent();
    }
    if ((e.metaKey || e.ctrlKey) && e.key === 'z') {
        e.preventDefault();
        undo();
    }
    if ((e.metaKey || e.ctrlKey) && (e.key === 'y' || (e.key === 'Z' && e.shiftKey))) {
        e.preventDefault();
        redo();
    }
    if (e.code === 'Space' && !interaction.value.type) {
        e.preventDefault();
        isPanning.value = true;
        interaction.value = { type: 'pan', dragStart: { x: e.clientX, y: e.clientY } };
        window.addEventListener('mousemove', handleMouseMove);
        window.addEventListener('mouseup', handleMouseUp);
    }
};

const handleKeyUp = (e) => {
    if (e.code === 'Space') {
        isPanning.value = false;
        interaction.value = {};
        window.removeEventListener('mousemove', handleMouseMove);
        window.removeEventListener('mouseup', handleMouseUp);
    }
};

onMounted(() => {
    const mainEl = mainContainerRef.value;
    mainEl.addEventListener('wheel', handleWheel, { passive: false });
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);
});

onUnmounted(() => {
    const mainEl = mainContainerRef.value;
    mainEl.removeEventListener('wheel', handleWheel);
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('keyup', handleKeyUp);
});

// A component to render the dynamic content.
const DynamicComponent = {
    props: ['type', 'data'],
    setup(props) {
        return {
            html: computed(() => {
                const def = COMPONENT_DEFS[props.type];
                return def ? def.render(props.data) : '';
            })
        };
    },
    template: '<div v-html="html"></div>'
};

const selectedComponents = computed(() => components.value.filter(c => selectedIds.value.includes(c.id)));
const updateCanvasSize = (newSize) => {
    setState(s => ({ ...s, canvasSize: newSize }));
};

const categorizedComponents = computed(() => {
    return Object.entries(COMPONENT_DEFS).reduce((acc, [type, def]) => {
        const category = def.category || 'Misc';
        if (!acc[category]) { acc[category] = []; }
        acc[category].push({ type, ...def });
        return acc;
    }, {});
});
</script>

<template>
    <div :class="['h-screen w-screen flex flex-col bg-slate-50 overflow-hidden', isPanning ? 'cursor-grab' : '']">
        <!-- TopBar -->
        <div class="h-14 bg-white border-b border-slate-200 flex items-center justify-between px-4 shadow-sm flex-shrink-0">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-bold text-slate-800">Wireframe Pro (Vue)</h1>
            </div>
            <div class="flex items-center gap-2">
                <button :disabled="!canUndo" @click="undo" class="px-3 py-1.5 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">Undo</button>
                <button :disabled="!canRedo" @click="redo" class="px-3 py-1.5 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">Redo</button>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showResetConfirm = true" class="flex items-center gap-2 px-3 py-1.5 text-sm font-semibold text-red-700 bg-red-50 border border-red-300 rounded-md shadow-sm hover:bg-red-100">
                    <span v-html="ICONS.refresh"></span>
                    Reset
                </button>
                <button @click="handleImport" class="flex items-center gap-2 px-3 py-1.5 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm hover:bg-slate-50" v-html="ICONS.upload + ' Import'"></button>
                <button @click="handleExport" class="flex items-center gap-2 px-3 py-1.5 text-sm font-semibold text-white bg-slate-800 border border-slate-800 rounded-md shadow-sm hover:bg-slate-700" v-html="ICONS.download + ' Export'"></button>
            </div>
        </div>

        <div class="flex flex-grow min-h-0">
            <!-- Left Sidebar -->
            <aside :class="['relative bg-white border-r border-slate-200 flex-shrink-0 transition-all duration-300 ease-in-out', isLeftSidebarCollapsed ? 'w-14' : 'w-60']">
                <button @click="isLeftSidebarCollapsed = !isLeftSidebarCollapsed" class="absolute top-1/2 -right-4 z-10 w-8 h-8 flex items-center justify-center bg-white border border-slate-300 rounded-full shadow-md hover:bg-slate-100 transition-colors -translate-y-1/2">
                    <span v-html="isLeftSidebarCollapsed ? ICONS.chevronRight : ICONS.chevronLeft"></span>
                </button>
                <div :class="['transition-opacity duration-200 overflow-y-auto h-full', isLeftSidebarCollapsed ? 'opacity-0 pointer-events-none' : 'opacity-100']">
                    <div v-if="!isLeftSidebarCollapsed" class="p-4 space-y-4">
                        <div v-for="(components, category) in categorizedComponents" :key="category">
                            <h3 class="text-sm font-semibold text-slate-500 mb-2 px-2">{{ category }}</h3>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="comp in components" :key="comp.type" @click="addComponent(comp.type)" :title="`Add ${comp.name}`" class="flex flex-col items-center justify-center p-2 rounded-lg text-slate-600 hover:bg-slate-200 hover:text-slate-800 transition-colors duration-150 text-center">
                                    <div class="w-8 h-8 flex items-center justify-center" v-html="comp.icon"></div>
                                    <span class="text-xs mt-1">{{ comp.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Canvas -->
            <main ref="mainContainerRef" :class="['flex-grow bg-slate-100 overflow-hidden relative', isPanning ? 'cursor-grab' : '']" @mousedown="handleCanvasMouseDown">
                <div class="absolute inset-0 grid-background" :style="{ backgroundPosition: `${panOffset.x}px ${panOffset.y}px`, backgroundSize: `${20 * scale}px ${20 * scale}px` }"></div>
                <div class="absolute top-0 left-0" :style="{ transform: `translate(${panOffset.x}px, ${panOffset.y}px) scale(${scale})`, transformOrigin: '0 0' }">
                    <div class="absolute top-0 left-0" :style="{ width: canvasSize.width + 'px', height: canvasSize.height + 'px' }">
                        <div v-for="comp in components" :key="comp.id"
                             :style="{ transform: `translate(${comp.position.x}px, ${comp.position.y}px)`, width: comp.size.width + 'px', height: comp.size.height + 'px' }"
                             :class="['group absolute transition-all duration-100', selectedIds.includes(comp.id) ? 'outline outline-2 outline-blue-500 outline-offset-2 rounded-lg' : '']"
                             @mousedown.stop="e => handleComponentMouseDown(e, comp.id)"
                             :data-component-id="comp.id">
                            <div v-html="COMPONENT_DEFS[comp.type].render(comp)"></div>
                            <template v-if="selectedIds.includes(comp.id)">
                                <div v-for="(classes, dir) in { 'top-left': 'top-0 left-0 cursor-nwse-resize', 'top-right': 'top-0 right-0 cursor-nesw-resize', 'bottom-left': 'bottom-0 left-0 cursor-nesw-resize', 'bottom-right': 'bottom-0 right-0 cursor-nwse-resize' }" :key="dir"
                                     :class="`absolute w-3 h-3 bg-blue-500 border-2 border-white rounded-full -translate-x-1/2 -translate-y-1/2 ${classes}`"
                                     @mousedown.stop="e => handleResizeStart(e, comp.id, dir)">
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div v-if="marquee" class="absolute border border-blue-500 bg-blue-500 bg-opacity-20 pointer-events-none" :style="{ left: marquee.x + 'px', top: marquee.y + 'px', width: marquee.width + 'px', height: marquee.height + 'px' }"></div>
            </main>

            <!-- Right Sidebar -->
            <aside :class="['relative bg-white border-l border-slate-200 flex-shrink-0 transition-all duration-300 ease-in-out', isRightSidebarCollapsed ? 'w-14' : 'w-72']">
                <button @click="isRightSidebarCollapsed = !isRightSidebarCollapsed" class="absolute top-1/2 -left-4 z-10 w-8 h-8 flex items-center justify-center bg-white border border-slate-300 rounded-full shadow-md hover:bg-slate-100 transition-colors -translate-y-1/2">
                    <span v-html="isRightSidebarCollapsed ? ICONS.chevronLeft : ICONS.chevronRight"></span>
                </button>
                <div :class="['transition-opacity duration-200 overflow-y-auto h-full', isRightSidebarCollapsed ? 'opacity-0 pointer-events-none' : 'opacity-100']">
                    <div v-if="!isRightSidebarCollapsed" class="p-4">
                        <div v-if="selectedComponents.length > 1">
                            <h3 class="text-sm font-semibold text-slate-500 mb-3 px-2">{{ selectedComponents.length }} items selected</h3>
                        </div>
                        <div v-else-if="selectedComponents.length === 1">
                            <h3 class="text-sm font-semibold text-slate-500 mb-3 px-2">Properties: {{ COMPONENT_DEFS[selectedComponents[0].type].name }}</h3>
                            <div class="space-y-4">
                                <div>
                                    <h4 class="text-xs font-bold uppercase text-slate-400 mb-2">Content</h4>
                                    <div class="space-y-3">
                                        <div v-for="(value, key) in selectedComponents[0].content" :key="key">
                                            <label class="block text-xs font-medium text-slate-500 capitalize mb-1">{{ key }}</label>
                                            <textarea v-if="typeof value === 'string'"
                                                      v-model="selectedComponents[0].content[key]"
                                                      :rows="Math.max(2, String(value).split('\n').length)"
                                                      class="w-full px-2 py-1.5 bg-slate-200 border border-transparent rounded-md text-sm text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all font-mono text-xs resize-y leading-relaxed"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold uppercase text-slate-400 mb-2">Layout</h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-slate-500 capitalize mb-1">Width</label>
                                            <input type="number" v-model.number="selectedComponents[0].size.width" class="w-full px-2 py-1.5 bg-slate-200 border border-transparent rounded-md text-sm text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all font-mono text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-500 capitalize mb-1">Height</label>
                                            <input type="number" v-model.number="selectedComponents[0].size.height" class="w-full px-2 py-1.5 bg-slate-200 border border-transparent rounded-md text-sm text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all font-mono text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-500 capitalize mb-1">X</label>
                                            <input type="number" :value="selectedComponents[0].position.x" readonly class="w-full px-2 py-1.5 bg-slate-200 border border-transparent rounded-md text-sm text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all font-mono text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-500 capitalize mb-1">Y</label>
                                            <input type="number" :value="selectedComponents[0].position.y" readonly class="w-full px-2 py-1.5 bg-slate-200 border border-transparent rounded-md text-sm text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all font-mono text-xs" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else>
                            <h3 class="text-sm font-semibold text-slate-500 mb-3 px-2">Canvas</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 capitalize mb-1">Width</label>
                                    <input type="number" :value="canvasSize.width" @input="e => updateCanvasSize({ ...canvasSize, width: Number(e.target.value) })" class="w-full px-2 py-1.5 bg-slate-200 border border-transparent rounded-md text-sm text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 capitalize mb-1">Height</label>
                                    <input type="number" :value="canvasSize.height" @input="e => updateCanvasSize({ ...canvasSize, height: Number(e.target.value) })" class="w-full px-2 py-1.5 bg-slate-200 border border-transparent rounded-md text-sm text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all font-mono text-xs" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Context Menu -->
        <div v-if="selectedIds.length > 0" class="fixed z-50 flex items-center justify-center -translate-y-1/2 p-2 rounded-lg bg-white shadow-xl border border-slate-200" :style="{ left: `50%`, top: `90px`, transform: `translate(-50%, 0)` }">
            <button @click="duplicateComponent" class="flex items-center gap-2 p-2 text-sm text-slate-700 hover:bg-slate-100 rounded-md">
                <span v-html="ICONS.copy"></span>
                Duplicate
            </button>
            <button @click="deleteComponent" class="flex items-center gap-2 p-2 text-sm text-red-600 hover:bg-red-50 rounded-md">
                <span v-html="ICONS.trash"></span>
                Delete
            </button>
        </div>

        <!-- Reset Confirmation Dialog -->
        <div v-if="showResetConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full mx-4">
                <h3 class="text-lg font-bold text-slate-800 mb-2">Are you sure?</h3>
                <p class="text-sm text-slate-600 mb-4">This action will clear all components from the canvas and cannot be undone.</p>
                <div class="flex justify-end gap-2">
                    <button @click="showResetConfirm = false" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm hover:bg-slate-50">Cancel</button>
                    <button @click="handleReset" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-md shadow-sm hover:bg-red-700">Clear Canvas</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
body {
    font-family: 'Inter', sans-serif;
    background-color: #f8fafc; /* slate-50 */
}
/* Custom scrollbar for a modern look */
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: #f1f5f9; }
::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #64748b; }
.grid-background {
    background-image:
        linear-gradient(to right, #e2e8f0 1px, transparent 1px),
        linear-gradient(to bottom, #e2e8f0 1px, transparent 1px);
}
.canvas-container {
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -khtml-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
</style>
