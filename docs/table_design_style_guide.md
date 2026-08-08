# UI Design & Style Guide

This style guide details the UI/UX design patterns, layout strategies, and components used in the **Credentials Management Page** (and inspired by the **Proposals Page**). Use these patterns to build consistent, premium interfaces across the application.

---

## 1. Page Header & Structural Shell

Every page uses the `AuthenticatedLayout` wrapper and defines page metadata via `<Head>`.

```vue
<template>
    <Head title="Page Title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Page Title</h2>
                <!-- Optional primary action button here -->
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-[100%] px-4 sm:px-6 lg:px-8">
                <!-- Content grid / components go here -->
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

---

## 2. Stats Dashboard Grid

Stats cards provide quick business insights at the top of the interface. They are styled with clean borders and optional colored accent borders.

```html
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
    <!-- Basic Stats Card -->
    <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Metric Label</div>
        <div class="mt-2 flex items-baseline justify-between">
            <div class="text-xl font-bold text-gray-900">123</div>
        </div>
    </div>

    <!-- Accent Border Stats Card (Indigo) -->
    <div class="bg-white border border-indigo-200 rounded-lg p-4 shadow-sm border-l-4 border-l-indigo-500">
        <div class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">Indigo Metric</div>
        <div class="mt-2 flex items-baseline justify-between">
            <div class="text-xl font-bold text-indigo-950">45</div>
        </div>
    </div>
</div>
```

| Card Type | Accent Color | Border Style | Accent Text Color | Typical Usage |
| :--- | :--- | :--- | :--- | :--- |
| **Total / Neutral** | Gray | `border-gray-200` | `text-gray-900` | Total Invoices, Total Items |
| **Primary Info** | Indigo | `border-l-indigo-500 border-indigo-200` | `text-indigo-950` | Synced Invoices |
| **General Info** | Blue | `border-l-blue-500 border-blue-200` | `text-blue-950` | Approved Bills, Active Invoices |
| **Success State** | Emerald | `border-l-emerald-500 border-emerald-200` | `text-emerald-950` | Paid Invoices / Paid Bills |
| **Warning / Alert** | Amber | `border-l-amber-500 border-amber-200` | `text-amber-950` | Pending Approvals, Unsynced Invoices |
| **Danger / Alert** | Rose | `border-l-rose-500 border-rose-200` | `text-rose-950` | Voided Bills, Voided Invoices |

---

## 3. Filters panel

The filter section groups search inputs, custom dropdown lists, and selectors inside a white panel. Keep labels short and use all-caps tracking headers.

```vue
<div class="bg-white border border-gray-200 rounded-lg p-4 mb-6 shadow-sm">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Text Input -->
        <div class="lg:col-span-2">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Search</label>
            <input
                v-model="filterSearch"
                type="text"
                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200"
                placeholder="Search..."
            />
        </div>

        <!-- Custom Select Dropdown -->
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Dropdown</label>
            <SelectDropdown
                v-model="filterId"
                :options="optionsList"
                value-key="id"
                label-key="name"
                placeholder="All Items"
            />
        </div>
    </div>
    
    <!-- Clear Action Button (conditional) -->
    <div v-if="hasActiveFilters" class="mt-3 flex justify-end">
        <button @click="clearFilters" class="text-xs font-medium text-indigo-600 hover:text-indigo-900 flex items-center gap-1">
            Clear Filters
        </button>
    </div>
</div>
```

---

## 4. Premium Data Tables

Tables are clean, border-wrapped, and overflow-safe on mobile viewports. Row clicks trigger detail overlays or sidebars.

```html
<div class="bg-white shadow sm:rounded-lg border border-gray-200 overflow-hidden">
    <div class="w-full overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="w-8 px-3 py-3"></th> <!-- Left Action Indicator -->
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr class="hover:bg-gray-50 cursor-pointer" @click="openDetails(item)">
                    <td class="px-3 py-3 text-center">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4 text-gray-400 hover:text-indigo-600 transition-colors inline-block" />
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900">John Doe</td>
                    <td class="px-4 py-3">
                        <!-- Custom Badge -->
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-100">
                            Active
                        </span>
                    </td>
                    <!-- Inline Action Buttons (click.stop to prevent row trigger) -->
                    <td class="px-4 py-3 text-right" @click.stop>
                        <DangerButton size="sm" @click="deleteItem(item.id)">Delete</DangerButton>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

---

## 5. Overlay Right Sidebar Details Panel

Detail panels slide in from the right edge, hosting comprehensive attributes, action boxes, logs, and sub-forms.

```vue
<RightSidebar :show="showSidebar" @close="showSidebar = false" title="Item Details" :initialWidth="40">
    <template #content>
        <div v-if="selectedItem" class="p-6 space-y-6">
            <!-- Sidebar Header -->
            <div class="border-b border-gray-200 pb-4">
                <h3 class="text-lg font-bold text-gray-900">{{ selectedItem.name }}</h3>
            </div>

            <!-- Block-style Information Group -->
            <div class="bg-gray-50 rounded-lg p-4 space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Owner:</span>
                    <span class="font-medium text-gray-900">{{ selectedItem.owner }}</span>
                </div>
            </div>

            <!-- Nested Form Card -->
            <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                <h4 class="text-sm font-semibold text-gray-800">Action Box</h4>
                <div class="flex gap-2">
                    <PrimaryButton @click="processItem">Apply</PrimaryButton>
                </div>
            </div>
        </div>
    </template>
</RightSidebar>
```

---

## 6. Permissions Guarding

Use standard directives `v-permission` for templates, and dynamic store permissions `canDo` inside script setup:

```vue
<script setup>
import { usePermissions } from '@/Directives/permissions';
const { canDo } = usePermissions();

// Define a reactive computed permission state
const canEditItem = computed(() => canDo('edit_credential').value);
</script>

<template>
    <!-- Template-based directive check -->
    <button v-permission="'manage_roles'">Admin Only</button>

    <!-- Computed reactive checks -->
    <div v-if="canEditItem">
        <label>Settings Form</label>
    </div>
</template>
```
