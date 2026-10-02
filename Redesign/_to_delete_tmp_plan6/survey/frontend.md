I have everything I need. Here is the full report.

---

# OZee CRM frontend survey — Laravel 12 + Inertia (Vue 3 + React 19 coexisting)

Repo root: `/home/claude/src`. No `node_modules`, no `public/` directory in this extract (important — see gaps at the end).

---

## 1. Build setup and Vue/React coexistence

### Files

| File | Role |
|---|---|
| `/home/claude/src/vite.config.js` | Single Laravel input `resources/js/app.js`; `@vitejs/plugin-vue` (with `transformAssetUrls` base null) plus `@vitejs/plugin-react` **scoped to `include: '**/*.{jsx,tsx}'`** so React's Babel/refresh never touches Vue `.js`/`.vue`. Alias `@` → `/resources/js`. |
| `/home/claude/src/tailwind.config.js` | Tailwind **v3** (despite `@tailwindcss/vite` v4 being in devDeps — v4 plugin is unused). Content globs cover `resources/views/**/*.blade.php` and `resources/js/**/*.vue` — **not `.jsx`**, so Tailwind classes in React files are never generated. Font `Figtree`, plugin `@tailwindcss/forms`. |
| `/home/claude/src/resources/css/app.css` | Only `@tailwind base/components/utilities`. |
| `/home/claude/src/resources/js/app.js` | The dispatcher. Reads Inertia's initial payload off `#app`'s `data-page`, and if `component` starts with `React/` dynamically imports `react-entry.jsx`, else `vue-entry.js`. |
| `/home/claude/src/resources/js/react-entry.jsx` | `createInertiaApp` from `@inertiajs/react`, resolves `./ReactPages/<name minus React/>.jsx` via `import.meta.glob`. Has a defensive branch: if a **non-`React/`** component arrives (redirect crossing frameworks), it calls `window.location.reload()` and returns a never-settling promise so Inertia can't swap into an unrenderable component. Progress colour `#1a73e8`. |
| `/home/claude/src/resources/js/vue-entry.js` | `createInertiaApp` from `@inertiajs/vue3`; installs Pinia, Ziggy, the `v-permission` directive, a hand-rolled `v-click-outside`, mounts `PushNotificationContainer`, then after mount fetches global permissions and subscribes to `Echo.private('App.Models.User.{id}').notification(...)` → `pushSuccess`. Progress colour `#4B5563`. |
| `/home/claude/src/resources/js/bootstrap.js` | axios globals (`X-Requested-With`, `withCredentials`, deliberately relying on the `XSRF-TOKEN` cookie rather than a static meta tag), `window.Pusher`, `window.Echo` = Laravel Echo with **broadcaster `reverb`**, `authEndpoint: /broadcasting/auth`, `enabledTransports: ['ws']`, `forceTLS:false`. |
| `/home/claude/src/resources/views/app.blade.php` | Root view. Computes `$isReactPage = str_starts_with($page['component'],'React/')` and `@vite(['resources/js/app.js', $pageAsset])` where `$pageAsset` is either `resources/js/ReactPages/X.jsx` or `resources/js/Pages/X.vue`; emits `@viteReactRefresh` and `@routes` (Ziggy global). Loads Figtree from fonts.bunny.net and Inter from Google Fonts. Contains a legacy inline monkey-patch of `Element.prototype.addEventListener` and a `window.onerror` swallow for "Invalid left-hand side in assignment" — cruft worth dropping in v2. |
| `resources/views/` others | `client_dashboard.blade.php`, `privacy-policy`, `test-google-auth`, `errors/magic-link`, `scribe/index`, and 14 `emails/*.blade.php` templates (these matter: the React inbox's "client view" renders these server-side). |

### How the two coexist (the rules, verbatim from the code comments)

1. **Only one framework boots per page load.** The decision is made from the server-rendered page payload, both in Blade (for the asset tag) and in `app.js` (for the runtime).
2. **`Inertia::render('React/…')` is the switch.** Controllers doing this today: `InboxBetaController` (`React/Inbox/Index`), `Portal\PortalController` (`React/Portal/Projects|Project|Profile`), `Public\PublicProjectController` (`React/Portal/Project`).
3. **Cross-framework links must be plain `<a>`** (or `Inertia::location` server-side). An Inertia `<Link>` crossing the boundary resolves a component the mounted runtime can't render — and because Inertia resolves *before* pushing history, the failure is silent (frozen page). Two mitigations exist: the reload branch in `react-entry.jsx`, and `signOut()` in `ReactComponents/app/navigation.js` (plain axios POST to `/logout`, then `window.location.assign('/')`).
4. **Styling is segregated.** Vue pages get Tailwind via `app.css`. React pages import `resources/css/ozee-ds/index.css` themselves (usually indirectly through `AppShell`), so the token system never leaks onto legacy pages. `useTheme` sets `data-theme` **and** `data-ozds` on `<html>` and removes them on unmount, so the dark theme only paints while a redesigned page is mounted.
5. **No shared state between the runtimes** — permissions, toasts and theme are implemented twice (Pinia store for Vue, module-level cache + hooks for React), deliberately with matching semantics.

### Inertia shared props (`app/Http/Middleware/HandleInertiaRequests.php`)

Root view `app`. Shares:
- `auth.user` — the full `User` model with `role.permissions` eager-loaded. Note: the code builds a `$globalPermissions` array (all permissions for super admins, role permissions otherwise) but **the assignment onto the user is commented out** (`// $user->global_permissions = ...`), so that work is wasted and both permission clients fetch `/api/user/permissions` over HTTP instead.
- `chrome_extension_link` (`config('services.chrome_extension')`)
- `telegramBotName` (env, default `ozee_web_bot`)
- `default_currency` (env, default `AUD`)
- plus `...parent::share()` → `errors`, and Laravel's flash bag only insofar as the parent provides it. **There is no explicit `flash` share** — flash messaging in the Vue app runs through the client-side `Utils/notification.js` + notification containers, not through Inertia shared props.

---

## 2. React kit already built (`resources/js/ReactComponents`, `ReactPages`)

~21,300 lines across 67 files. Quality is high: every file has a substantial doc-comment explaining the *why*, accessibility deviations from the source bundle are called out, and there are no TODOs/dead code. Styling is **100 % inline styles reading CSS custom properties** — no Tailwind, no CSS modules. That is a deliberate choice (matches the design bundle), and it is the main thing to decide about for v2.

### 2a. `ds/` primitives — the reusable core (28 exports)

| Component | Props |
|---|---|
| `Icon` (`ds/Icon.jsx`) | `name, src, size=20 (or 'xs'\|'small'\|'medium'\|'large'), color='currentColor', title, style, ...rest` — renders a CSS `mask` from `/ozee-ds/icons/<name>.svg` filled with `background: color`; honours `window.OZEE_ICON_BASE` |
| `IconButton` | `icon, name, size='medium' (xs 24/small 32/medium 40/large 48), kind='tertiary', active, disabled, ariaLabel, onClick, style` |
| `Button` (`ds/Button.jsx`) | `children, kind='primary'\|secondary\|tertiary, color='primary'\|brand\|positive\|negative\|inverted, size='xxs\|xs\|small\|medium\|large', disabled, active, loading, leftIcon, rightIcon, fullWidth, type='button', onClick, style` — reproduces the `scale(0.95)` press squeeze |
| `ButtonGroup` | `options=[{value,text}], value, onChange, size='small', style` (segmented control; used for the theme switcher) |
| `FieldShell` (`ds/Fields.jsx`) | `label, required, subText, validation('error'\|'success'), children, style` |
| `TextField` | `value, onChange, placeholder, label, size='medium', iconName, trailingIconName, disabled, readOnly, required, validation, subText, type='text', style, wrapperStyle, ...rest` |
| `TextArea` | `value, onChange, placeholder, label, rows=4, disabled, validation, subText, maxLength, style, ...rest` |
| `Dropdown` | `options, value, onChange, placeholder='Select', label, size, disabled, clearable, multi, searchable, style` — **portalled to `document.body`** via `useAnchoredPopover` because modals/composers clip absolutely-positioned menus |
| `RadioButton` | `label, name, value, checked, disabled, onChange, style` |
| `Checkbox` (`ds/Toggles.jsx`) | `label, checked, indeterminate, disabled, onChange, ariaLabel, style` |
| `Toggle` | `checked, onChange, disabled, size='medium'\|'small', areLabelsHidden=true, onLabel='On', offLabel='Off', ariaLabel, style` |
| `Search` (`ds/Search.jsx`) | `value, onChange, onClear, placeholder='Search', size, ariaLabel, style` — TextField + Search icon + clear button |
| `Chips` (`ds/Chips.jsx`) | `label, color='primary', leftIcon, leftAvatar, onDelete, onClick, selected, readOnly, disabled, size='medium'\|'small', title, style` — upgraded over the bundle: becomes a real `role=button`/`aria-pressed` control when `onClick` is set |
| `Label` (`ds/Display.jsx`) | `text, kind='fill'\|'line', color='primary'\|dark\|positive\|negative, size, style` |
| `Counter` | `count, kind='fill'\|'line', color='primary'\|dark\|negative\|light, size='xs'\|'small'\|'large', maxDigits=3, style` |
| `Tabs` | `tabs=[{value,label,icon?,counter?,disabled?}], value, onChange, size, style` (`role=tablist`) |
| `ProgressBar` | `value, max=100, color='primary'\|positive\|negative\|warning, size='small'\|'medium'\|'large', showLabel, style` |
| `Avatar` | `src, text, ariaLabel, size, square, backgroundColor, style, ...rest` — deterministic palette seeded from first char |
| `AttentionBox` (`ds/Feedback.jsx`) | `title, children, type='primary'\|success\|warning\|danger, withIcon, onClose, style` |
| `Toast` | `children, type='normal'\|positive\|negative\|warning, open, onClose, action, withIcon, style` |
| `Modal` | `open, onClose, title, description, children, footer, size='small'\|'medium'\|'large'\|'fullView', showCloseButton, dense (phone full-bleed), style` — Escape key, body scroll lock, focus on open |
| `AlertBanner` (`ds/States.jsx`) | `children, type, onClose, action, style` (full-width 40px strip) |
| `EmptyState` | `title, description, action, illustrationSrc, iconName='Board', style` |
| `Loader` | `size=32, color, ariaLabel, style` |
| `Skeleton` | `type='rectangle'\|'circle'\|'text', width, height, fullWidth, style` |
| `Divider` | `direction='horizontal'\|'vertical', style` |
| `DialogContentContainer` (`ds/Menu.jsx`) | `children, size, style` |
| `Menu` | `items=[{value,label,icon?,disabled?,destructive?,divider?,title?}], onSelect, style` |
| `MenuButton` | `items, onSelect, ariaLabel, iconName='MoreActions', size, align='end', children, style, menuStyle` — portalled |
| `useAnchoredPopover` (`ds/Popover.jsx`) | `{preferredHeight=260, align='start'\|'end', matchWidth, minWidth, onClose}` → `{open,setOpen,anchorRef,panelRef,position}`; `useLayoutEffect` measurement, owns outside-click + Escape |
| `Popover` | `position, panelRef, children, style` — `createPortal` with `className="ozds"` |

`ds/index.js` documents that the source bundle has ~54 components and that more should be **ported from the bundle rather than invented**.

### 2b. `app/` — the internal-app shell

- **`AppShell.jsx`** — `{title, activeKey, railBadges, headerSearch:{value,onChange,onClear,placeholder}, headerExtra, toasts, onDismissToast, showFooter=true, children}`. 56px white header (logo + "CRM", optional search, theme `ButtonGroup`, notifications `IconButton`, avatar `MenuButton`) + 64px icon rail with permission filtering and pulsing negative badges + flex-column content area (children get `minHeight:0` so panes scroll, not the window) + footer + fixed bottom-right toast stack. Explicitly *not* the Vue `AuthenticatedLayout`.
- **`navigation.js`** — nav as data, plus `url(routeName, fallback)` (Ziggy with graceful degradation) and `signOut()`.
  - `RAIL_ITEMS`: `work` (Home → `/workspace`), `projects` (Board), `tasks` (CheckList → `/dashboard`), `inbox` (Email, perm `view_emails`), `reports` (Chart → `/admin/productivity`, perm `manage_projects`), `team` (Team → `/users`, perm `create_users`), `admin` (Settings → `/admin/roles`, perm `view_admin_dropdown`).
  - `USER_MENU_ITEMS`: Attendance, Profile, divider, Log out (destructive).
  - `FOOTER_LINKS`: Dashboard, My workspace, Leaderboard.
  - `activeRailKey(pathname)` — longest matching prefix wins.
- **`usePermissions.js`** — `usePermissions(projectId=null)` → `{can(slugs,{operator:'and'|'or'}), ready, isSuperAdmin, role}`. Module-level cache + in-flight dedupe + subscriber set. Super admin (`auth.user.role_data.slug === 'super-admin'`) short-circuits. `/api/user/permissions` global; `/api/projects/{id}/permissions` **replaces** the global set. Failed fetch caches empty (fails closed). `invalidatePermissions()` exported.
- **`useTheme.js`** — `{theme,setTheme}`, `THEME_OPTIONS` = Auto/Light/Dark, persisted in `localStorage['ozds-theme']`, sets/clears `data-theme` + `data-ozds` on `<html>`.
- **`useToasts.js`** — `{toasts, push(message,{type='positive',ttl=4000}), dismiss(id)}`; queue capped at 3, timers cleared on unmount.
- **`useIsMobile.js`** — `MOBILE_MAX_WIDTH = 860`, matchMedia with the old Safari `addListener` fallback. Used because the layout switch (not just padding) has to be known in JS when everything is inline-styled.

### 2c. `inbox/` — the largest built feature (~9,600 lines)

Desktop: `DesktopInbox({page})`, `ThreadList`, `ThreadView` (+ `ApprovalBanner`, `AiSummary`, `NoteCard`, `MessageCard`), `FilterRail`, `ReplyBox` (938 lines), `ComposeModal` (1031), `MarkdownEditor`/`LetterEditor` (1070, with `markdownToEditorHtml` / `editorHtmlToMarkdown` / `useSnippets`), `BlockBuilder` (block-based email composer), `TemplateFields`, `Salutation` (`GreetingPicker`, `GreetingLine`, `SignOffPreview`, `greetingTextFor`, `GREETING_MODES`), `EmailBody`, `EmailNumber`, `ClientViewDialog` (sandboxed iframe of the real Blade-rendered email), `SavedList`, `DeletedList`, `InboxDialogs`/`FULL_SCREEN_MODAL`, `modals.jsx` (`RejectModal`, `DeleteModal`, `TaskModal`, `CategoriseModal`).
Mobile (`inbox/mobile/`): `MobileShell`, `MobileInbox`, `MobileThreadList` (887 lines, swipe actions + pull-to-refresh), `MobileThreadView`, `MobileComposer`, `MobileFilterSheet`, and `Sheet.jsx` (`Sheet` bottom-sheet, `PushScreen`, `PushHeader`) — **a genuinely reusable mobile primitive set**.
Hooks/utils: `useInboxPage` (983 lines — the page orchestrator), `useInbox`/`useThread`/`inboxActions`/`EMPTY_FILTERS`/`defaultSortFor`, `useTemplates`/`useTemplatePreview`, `useBlocks` (+ `emptyBlock`, `toPayload`, `isMeaningful`, `splitPaste`, `TYPE_LABEL`), `useComposeDrafts`/`useSavedEmails`/`draftAgeLabel`, `format.js` (`replyStatus`, `formatMinutes`, `shortTime`, `longTime`, `exactTime`, `categoryColour`, `initials`, `plural`, `fileSize`, `VIEW_DEFS`, `viewsFor`, `viewSubtitle`).

### 2d. `portal/` — the external client/supplier portal (~3,700 lines)

`PortalShell({title,branding,account,current,onSignOut,projectContact,children})` + `PortalHeader` + `PortalFooter`; `ProjectHero`, `PhasesTab`, `ProposalsTab`, `ProposalsPanel`, `BillsTab`, `ProposalModal` (1245 lines — multi-phase quoting), `BillModal`, `ProfileView`, `SignInPanel`; hooks `useSignIn(shareToken)`, `usePortalActions({account,proposals,paymentMethods,projectId})`, `useTheme` (re-export); helpers `format.js` (`TONE`, `statusLabel`, `milestoneTone`, `proposalTone`, `billTone`, `deliverableTone`, `money`, `CURRENCIES`, `sumByCurrency`, `PAY_TYPES`, `parsePaymentTerms`, `describePaymentTerms`, `evenPercentages`) and `paymentMethods.js` (`METHOD_TYPES`, `COUNTRIES`, `fieldsFor`, `missingRequired`, `needsCountry`).

### 2e. Pages

`ReactPages/Inbox/Index.jsx` (40 lines — thin: picks Mobile vs Desktop), `ReactPages/Portal/{Projects,Project,Profile}.jsx`, `ReactPages/TestPage.jsx` (the cross-framework link demo).

### Reusability verdict for v2

- **Take as-is:** all of `ds/`, all of `app/` (AppShell, navigation, usePermissions, useTheme, useToasts, useIsMobile), `inbox/mobile/Sheet.jsx`, both `format.js` files, `useAnchoredPopover`.
- **Take with refactor:** the inbox and portal feature folders are excellent but page-specific; they belong under a `features/` directory in v2, not in a flat `ReactComponents/`.
- **Decide early:** inline-styles-everywhere. It works and it exactly matches the mocks, but it makes hover/focus/media queries awkward (hence `useIsMobile`, hence hand-rolled `onMouseEnter` hover state in nearly every primitive). If v2 keeps Tailwind, extend `tailwind.config.js` to map the CSS variables to theme tokens and add `.jsx` to `content` — currently React JSX is not scanned at all.

---

## 3. Vue side — inventory and cross-cutting behaviour v2 must reproduce

### Inventory

| Area | Count | Notes |
|---|---|---|
| `Layouts/` | 2 | `AuthenticatedLayout.vue` (313 lines, 30 imports), `GuestLayout.vue` (22) |
| `Components/` | 185 files — 84 top-level `.vue` + 26 subfolders | Biggest subfolders: BonusCalculator 13, ProjectTasks 11, Dashboard 10, ProjectForm 8, ProjectsEmails 8, Layout 7, Availability 7 |
| `Pages/` | 220 files / 28 folders | Admin 50, Automation 33, AutomationsV2 24, Presentations 23, Emails 20, ClientDashboard 17 |
| `Composables/` | 8 | useClientDetails, useEmailSignature, useEmailTemplate, useEmbeddedScheduler, useExtensionStatus, useFormatter, useLeadDetails, useLeads |
| `Stores/` (Pinia) | 1 | `presentationStore.js` — plus the real permission store lives in `Directives/permissions.js`, and two more in `Pages/Automation/Store/workflowStore.js` and `Pages/AutomationsV2/Store/storeV2.js` |
| `Services/` | 3 | `api.js` and `api-service.js` are **near-duplicates** (both inbox endpoints); `presentationsApi.js` |
| `Utils/` | 11 | notification, notification-sidebar, sidebar, email-sidebar, chat-state, taskState (361 lines of task API + status class helpers), browser-notifications, useNotices, mentions, currency, debounce |
| `Directives/` | 1 | `permissions.js` — 653 lines: Pinia store + `useGlobalPermissions`/`useProjectPermissions`/`useProjectRole`/`usePermissions`/`hasPermission` + `v-permission` directive |
| `Prompts/` | 1 | `config.js` — declarative list driving `Components/Prompts/PromptOrchestrator.vue` |
| `src/` | 1 | `src/Components/Notification.vue` — orphan/stray, duplicate of `Components/Notification.vue` |
| `archive/` | 0 files | empty directory |

Component categories (top-level): buttons/inputs (PrimaryButton, SecondaryButton, DangerButton, TextInput, TextareaInput, Checkbox, InputLabel, InputError, SelectDropdown, MultiSelectDropdown, OverlayMultiSelect, CustomMultiSelect, AsyncSearchDropdown, SelectWithCreateDropdown, TagInput, TimezoneSelect, IconPicker, ProjectTypeInput, BasicPropertyInput, RepeatableDynamicField), overlays (Modal, BaseFormModal, PreviewModal, ResourceModal, NotesModal, MeetingModal, MeetingMinutesModal, StandupModal, BlockReasonModal, ProjectMagicLinkModal, WorkspaceBulkTaskModal, TestFormModal), editors (RichTextEditor, CustomRichTextEditor, ContentEditor, EmailEditor/CKEditor, TiptapEditor, EmailTemplates, PlaceholderInserter, DataTokenPicker, BlockToolbox, Toolbar), shell/nav (LeftSidebar 414, RightSidebar, Layout/*, GlobalSearch, Notification*, PushNotificationContainer 393, StandardNotificationContainer, CommunicationSidebar **2203 lines**), project domain (~40 components), boards/lists (KanbanBoard, TaskList, ChecklistComponent/Creator, ProjectProgressTimeline), viz (ChartComponent, PMPayoutCalculator, BonusCalculator/*), misc (SlideThumbnail, PresentationCard, ZoomControls, Scheduler, EffortEstimationGuide, RelationshipPathPicker).

### Cross-cutting behaviours to reproduce in v2

1. **Auth/user props** — `usePage().props.auth.user` (full user with `role`, `role_data`). Also `default_currency`, `telegramBotName`, `chrome_extension_link`. v2 should extend `HandleInertiaRequests` to also share `flash`, `permissions` (finish the commented-out `global_permissions`), and unread counters so the shell stops needing three XHRs on boot.
2. **Permissions** — two parallel implementations already exist (Pinia `usePermissionStore` + `v-permission` directive; React `usePermissions`). Semantics: super-admin bypass, global via `/api/user/permissions`, project via `/api/projects/{id}/permissions` which *replaces* rather than merges, render-gating only.
3. **Realtime (Echo/Reverb)** — `bootstrap.js` builds `window.Echo`; `vue-entry.js` subscribes to `App.Models.User.{id}` `.notification()`; `CommunicationSidebar.vue` is the only other consumer (project chat channels). `routes/channels.php` (35 lines) defines the authorised channels. **The React shell currently has no Echo subscription at all** — the notifications `IconButton` in `AppShell` is inert. This is the single biggest gap for v2.
4. **Notifications & toasts** — three layers: `Utils/notification.js` (`success`/`error`/`info`/`warning`/`handleLaravelError`/`confirmPrompt`, routed through a registered container ref), `PushNotificationContainer.vue` (realtime pushes), `StandardNotificationContainer.vue`, plus `Utils/notification-sidebar.js` (a full notification centre: `/api/notifications`, `/api/notifications/{id}/read`, `/api/notifications/read-all`, `localStorage['newPushNotificationIds']` de-dupe) and `Utils/browser-notifications.js` (desktop Notification permission bootstrap).
5. **Global search** — `Components/GlobalSearch.vue`: lodash-debounced 300ms `GET /api/global-search?q=`, grouped results, opens task or email in the respective detail sidebar. `AppShell`'s `headerSearch` is currently a pass-through prop with no backing — wire it to this endpoint in v2.
6. **Sidebars as global singletons** — `Utils/sidebar.js` (task detail), `Utils/email-sidebar.js`, `Utils/notification-sidebar.js`, `Utils/chat-state.js` (`/api/chat/unread-counts`, per-project unread). All are module-level `ref`/`reactive` opened from anywhere. v2 needs an equivalent (context + reducer, or a small store).
7. **Modal-heavy layout** — `AuthenticatedLayout` mounts ~14 global overlays: CreateTaskModal, WorkspaceBulkTaskModal, CreateResourceForm, KudoModal, NoticeboardModal, HourlyNotificationSummaryModal, MeetingMinutesModal, YesterdayReport, AvailabilityBlocker, ExtensionEnforcementModal, ExtensionReminderBar, PromptOrchestrator, CommunicationSidebar, TaskSidebar/EmailSidebar. Several are *blocking* (availability blocker, extension enforcement) — v2 must keep those gates.
8. **Prompt orchestration** — `Prompts/config.js` + `PromptOrchestrator.vue` + `UserDataPromptModal.vue`: a declarative queue of "ask the user for missing data" modals.
9. **Notices** — `Utils/useNotices.js`: `/api/notices/unread`, `/api/notices/acknowledge`.
10. **Task lifecycle** — `Utils/taskState.js`: start/pause/resume/complete/block/unblock/revise/delete plus `getTaskStatusClasses`/`getTaskPriorityClasses`/`formatPriority` (Tailwind class strings — these become token/status colours in v2).
11. **Legacy token auth** — `AuthenticatedLayout` sets `Authorization: Bearer localStorage.authToken` and pings `/api/user`, clearing five localStorage keys on 401. `navigation.js:signOut()` clears the same five keys. Session-cookie auth is the real mechanism; the bearer token is vestigial and should not be carried into v2.
12. **Dark mode** — **the Vue app has none** (only 3 files contain any `dark:` class). Dark mode is a redesign-only feature via `data-theme`.
13. **Rich text** — CKEditor 5 in `Components/EmailEditor.vue`; TipTap in `Components/TiptapEditor.vue` and `Pages/Presentations/Components/TiptapEditor.vue`; **Quill is a dependency but has zero imports** — dead. The React side replaced all of this with its own `MarkdownEditor`/`LetterEditor`.
14. **Drag and drop** — `vuedraggable` in 4 files (KanbanBoard, presentations, block editors); React re-implements with native HTML5 DnD in `BlockBuilder`.
15. **Calendar** — `vue-cal` in exactly one place: `Components/DailyStandups/DailyStandups.vue`.
16. **Charts** — `chart.js` (`chart.js/auto`) in 7 files: `ChartComponent.vue`, `PMPayoutCalculator.vue`, `BonusCalculator/TeamMetricsChart.vue`, `Pages/Admin/BonusCalculator/Index.vue`, `Pages/BonusSystem/Index.vue`, `Pages/ClientDashboard/{HomeSection,SEOReport}.vue`.
17. **Automation builder** — `@vue-flow/core|background|controls|minimap` across `Pages/Automation/Components/{VueFlowCanvas,Workflow,WorkflowNode}.vue` and `Pages/AutomationsV2/Components/{Builder,Canvas,CustomNode}.vue`, each with its own Pinia store. This is the hardest thing to port (React Flow is the natural target) and is a good candidate to leave on Vue longest.
18. **Other libs in use:** `@heroicons/vue` (53 files) and `lucide-vue-next` (24) — **two icon sets**, both of which the design system says to drop in favour of the Vibe glyphs; `moment` (9), `lodash` (3), `splitpanes` (3), `vue-multiselect` (4), `uuid` (3), `vue-router` (1, alongside Inertia). Dead deps: `quill`, `swiper`, `vue-json-pretty`.
19. **File upload** — 23 components use `FormData` / `<input type="file">`; no shared uploader component exists. v2 should build one.

---

## 4. Design bundle (`/home/claude/src/Redesign/**`)

### Folder layout

```
Redesign/
├── Multi-proposal milestone submission page/   (+ .zip)
│   ├── EmailBlocks.dc.html
│   ├── Guest Project Proposals.dc.html
│   ├── Inbox.dc.html
│   ├── support.js                      (1911 lines, generated dc-runtime)
│   ├── assets/icons/                   (63 SVGs)
│   ├── uploads/                        (empty)
│   └── _ds/vibe-monday-3d3bcd2f-2617-45d4-830a-b39427322625/
│       ├── readme.md            (14 KB — the written design language)
│       ├── _ds_manifest.json    (33 KB — components + every token, with values)
│       ├── _ds_bundle.js        (177 KB / 5921 lines — IIFE, browser-global)
│       ├── _adherence.oxlintrc.json  (27 KB — lint rules enforcing the system)
│       ├── styles.css           (10 lines — @imports only)
│       └── tokens/{base,brand,colors,fonts,motion,radius,semantic,shadows,spacing,typography}.css
└── inbox-mobile/                              (+ .zip)
    ├── Canvas.dc.html          (EMPTY <x-dc></x-dc> — a blank scratch canvas)
    ├── EmailBlocks.dc.html     ┐
    ├── Guest Project Proposals.dc.html  ├ byte-identical to the other folder
    ├── Inbox.dc.html           ┘
    ├── Inbox Mobile.dc.html    (1109 lines — unique to this folder)
    └── (same support.js, assets/icons, uploads/, _ds/)
```

**The two folders' `_ds` trees are byte-identical** (`diff -rq` clean), as are the three shared mocks. `inbox-mobile` is a strict superset: `Inbox Mobile.dc.html` + the empty `Canvas.dc.html`. Treat `inbox-mobile/` as canonical.

### `_ds/.../readme.md` — the design language (key points)

- Built from **monday.com Vibe** (open source) for the *system* + **OZee CRM** for the *brand*. "Where they conflict, Vibe wins for UI chrome and OZee wins for identity." monday's own brand marks are deliberately not used.
- **Voice**: plain Australian-English, sentence case everywhere, verb-first specific buttons ("Approve & send", "Send back"), errors name cause + fix, human short dates (`22 Aug`, day-month), numbers lead the line, no emoji in chrome. Fixed product vocabulary list (project, task, subtask, milestone, deliverable, client, standup note, kudos, approval, availability, notice board, resource, tier, workspace, automation, schedule).
- **Palette**: one working blue `#1a73e8`, hover `#1560c4`, selection `#d3e4fd`, faintest `#f0f6ff`. Only two greys of text (`#323338`, `#676879`) — "there is no third grey". White chrome on `#f6f7fb`, hairlines `#d0d4e4` (layout) / `#c3c6d4` (control). Semantics green `#00854d`, red `#d83a52`, yellow `#ffcb00`. A 32-colour board palette reserved for status/labels/groups, **never text or chrome**. Brand `#0e2ba4` / amber `#f8a100` / green `#00853b` are identity-only.
- **Type**: Poppins headings (32/40, 24/30, 18/24 — three steps, nothing between), Figtree everything else (16/22, 14/20 default, 12/16). Weights 400/600/700 only.
- **Layout geometry**: 56px top bar, 64px icon rail, 240px sidebar (64px collapsed), 24px screen padding, 16px card padding, 40px rows (36 dense), controls 32/40/48.
- **No gradients, no photography, no illustration, no texture, no backdrop blur.** Empty states use a grey icon disc.
- **Motion**: productive 70/100/150ms, expressive 250/400ms; four named easings; no spring/parallax/ambient loops.
- **States**: signature `transform: scale(0.95)` press squeeze; 3px 50 %-opacity blue focus ring; hover wash `rgba(103,104,121,0.1)`.
- **Icons**: one set only — Vibe glyphs, masked with `currentColor`. "No Font Awesome, no Heroicons, no icon font." Nav mapping: Home→My work, Board→Projects, CheckList→Tasks, Email→Approvals, Chart→Reports, Bolt→Automations, Team→Team, Settings→Admin.
- **Discrepancy to note:** the readme claims **90 glyphs**; `assets/icons/` actually contains **63**. Missing from the shipped set relative to the readme's nav mapping is nothing critical, but audit before relying on a name. Also the readme references `guidelines/`, `components/`, `ui_kits/crm/`, `templates/crm-screen/`, `SKILL.md`, `github.md` and brand PNGs — **none of those directories are present in this extract**; only `tokens/`, `styles.css`, the bundle and the manifest shipped.

### Token families (from `_ds_manifest.json`, all values verified)

| Family | File | Count | Key values |
|---|---|---|---|
| Primary / interactive | `colors.css` | 6 | `--primary-color:#1a73e8`, `-hover:#1560c4`, `-selected:#d3e4fd`, `-selected-hover:#b8d3fb`, `-highlighted:#f0f6ff`, `-surface:#eceff8` |
| Text | `colors.css` | 6 | `--primary-text-color:#323338`, `--secondary-text-color:#676879`, `--text-color-on-primary/inverted/brand:#fff`, `--disabled-text-color:rgba(50,51,56,.4)` |
| Surfaces & chrome | `colors.css` | 14 | `--primary-background-color:#fff`, `--grey-background-color/--allgrey-:#f6f7fb`, `--backdrop-color:rgba(41,47,76,.7)`, `--ui-border-color:#c3c6d4`, `--layout-border-color:#d0d4e4`, `--ui-background-color:#e7e9ef`, `--disabled-background-color:#ecedf5`, `--icon-color:#676879`, `--link-color:#1f76c2`, `--modal/dialog-background-color:#fff` |
| Semantic status | `colors.css` | 9 | positive `#00854d`/hover `#007038`/selected `#bbdbc9`; negative `#d83a52`/`#b63546`/`#f4c3cb`; warning `#ffcb00`/`#eaaa15`/`#fceba1` |
| Board palette | `colors.css` | 32 | `--color-done-green:#00c875`, `--color-working-orange:#fdab3d`, `--color-stuck-red:#df2f4a`, plus egg-yolk, sofia-pink, lipstick, bubble, purple, berry, dark-indigo, indigo, navy, bright-blue, aquamarine, chili-blue, river, winter, explosive, brown, tan, sky, lavender, steel, lilac, teal, … |
| Brand | `brand.css` | 15 | `--ozee-blue:#0e2ba4`, `--ozee-blue-deep:#0a2080`, `--ozee-amber:#f8a100`, `--ozee-green:#00853b`, `--ozee-app-primary:#1a73e8`, `--brand-color:var(--ozee-blue)` |
| Typography | `typography.css` | 40+ | `--font-family: Figtree,…`, `--title-font-family: Poppins,…`; composite shorthands `--font-h1-medium:600 32px/40px`, `--font-h2-medium:600 24px/30px`, `--font-h3-medium:600 18px/24px`, `--font-text1/2/3-{normal,medium,bold}`; letter-spacing −0.5px h1, −0.1px h2/h3 |
| Spacing | `spacing.css` | 12 | 2·4·8·12·16·20·24·32·40·48·64·80 |
| Radius | `radius.css` | 8 | 4/8/12/16 + `-small:4`, `-medium:8`, `-big:16`, `--border-width:1px`, `--border-style:solid` |
| Shadows | `shadows.css` | 5 | xs / small / medium / large + `--focus-ring: 0 0 0 3px hsl(209 100% 50%/50%), 0 0 0 1px var(--primary-hover-color) inset` |
| Motion | `motion.css` | 10 | productive 70/100/150ms, expressive 250/400ms, timings enter `(0,0,.35,1)`, exit `(.4,0,1,1)`, transition `(.4,0,.2,1)`, emphasize `(0,0,.2,1.4)` |
| Semantic aliases | `semantic.css` | 20 | `--text-body/muted/link/on-accent`, `--surface-app/card/sunken/raised/hover/selected`, `--border-hairline/control`, `--accent/-hover`, `--status-done/working/stuck/idle`, **`--shell-topbar-height:56px`, `--shell-sidebar-width:240px`, `--shell-sidebar-collapsed-width:64px`** |
| Base | `base.css` | — | resets: `body{font:var(--font-text1-normal);background:var(--surface-app)}`, `*{box-sizing:border-box}`, link colours, `:focus-visible{box-shadow:var(--focus-ring)}` |
| Fonts | `fonts.css` | — | one Google Fonts `@import` for Figtree 300–700 + Poppins 300–700 |

`styles.css` in the bundle is **only 10 `@import` lines** — there are no component classes there. The component classes live in the app copy (`resources/css/ozee-ds/index.css`, 283 lines): `.ozds-email-body`, `.ozds-email-quote`, `.ozds-letter-body`, `.ozds-letter-source`, plus `.om-app`, `.om-row`, `.om-row-settle`, `.om-scroll` in `inbox-mobile.css` and the `dcRise/dcFade/dcSlideIn/dcPulse/ozeeSpin/ozeeShine` keyframes in `animations.css`. `theme-dark.css` (104 lines) holds the `[data-theme="dark"]` and `prefers-color-scheme` overrides.

### `_ds_bundle.js` — components exported

Browser-global IIFE assigning to `window.OZeeCRMDesignSystem_3d3bcd`. **54 exported components**:

- **core**: `Button`, `Icon`, `IconButton`, `ButtonGroup`, `SplitButton`, `Link`
- **typography**: `Heading`, `Text`, `TextWithHighlight`, `FormattedNumber`, `EditableText`, `EditableHeading`
- **forms**: `TextField`, `TextArea`, `Search`, `NumberField`, `Dropdown`, `Combobox`, `DialogContentContainer`, `Checkbox`, `RadioButton`, `Toggle`, `Slider`, `ProgressBar`, `ColorPicker`, `DatePicker`
- **data**: `Table`, `List`, `ListItem`, `ListTitle`, `Chips`, `Label`, `Counter`, `Badge`, `Avatar`, `AvatarGroup`
- **feedback**: `Toast`, `AlertBanner`, `AttentionBox`, `Tipseen`, `Tooltip`, `Info`, `Loader`, `Skeleton`, `EmptyState`
- **navigation**: `Tabs`, `BreadcrumbsBar`, `Divider`, `Accordion`, `ExpandCollapse`, `Menu`, `MenuButton`, `Steps`, `MultiStepIndicator`
- **overlays**: `Modal`

Plus **unexported UI-kit screens** compiled into the same file, reading a `window.CRM_DATA` fixture: `LoginScreen`, `DashboardScreen`, `ProjectsScreen`, `ProjectDetailScreen`, `ApprovalsScreen`, `Shell`, and helpers `Card` / `StatTile`. These are the closest thing to a full-app reference and are worth reading for the projects board and project-detail layouts, which have no `.dc.html` mock.

**Not yet ported to `ReactComponents/ds/` (26):** `SplitButton`, `Link`, `Heading`, `Text`, `TextWithHighlight`, `FormattedNumber`, `EditableText`, `EditableHeading`, `NumberField`, `Combobox`, `Slider`, `ColorPicker`, `DatePicker`, `Table`, `List`, `ListItem`, `ListTitle`, `Badge`, `AvatarGroup`, `Tipseen`, `Tooltip`, `Info`, `BreadcrumbsBar`, `Accordion`, `ExpandCollapse`, `Steps`, `MultiStepIndicator`. For a CRM, **`Table`, `Tooltip`, `DatePicker`, `Combobox`, `BreadcrumbsBar`, `Accordion` and `AvatarGroup` are the urgent ones.**

### `support.js` (identical in both folders, 1911 lines)

Generated `dc-runtime` — a self-contained browser runtime that makes the `.dc.html` mocks executable without a build step. It provides:

- **Mock parsing**: `parseDcDocument` / `parseDcText` find `<x-dc>`, the `<script data-dc-script data-props="…">` block, and the `$preview` viewport hints.
- **A template mini-language compiler**: `{{ expr }}` interpolation with path resolution and equality, `<sc-for list as>`, `<sc-if value>`, `<x-import component-from-global-scope="NS.Component">`, `style-hover="…"` pseudo-class support (`createPseudoSheet`), camelCase attribute encoding, `cssToObj`, and `hint-size` / `hint-placeholder-count` / `hint-placeholder-val` used to render grey placeholders before a component resolves.
- **Component registry & lazy external modules**: `createRegistry`, `createComponentFactory`, `createExternalModules` (CDN + SRI loading), `Placeholder`.
- **React UMD bootstrapping**: `loadReactUmd()` pulls React/ReactDOM from CDN into `window.React`/`window.ReactDOM` (which is why `_ds_bundle.js` is a global IIFE and, per `ds/index.js`, cannot be `import`ed by Vite).
- **Helmet management** (`createHelmetManager`) so each mock's `<helmet>` block injects stylesheets and the bundle script.
- Streaming/staleness tracking and `hideRawTemplate()` to avoid a flash of unparsed markup.

It is a *prototype viewer*, not production code — nothing in it needs porting.

### The mocks, screen by screen

#### `Inbox.dc.html` (1568 lines) — desktop internal inbox / email approvals

The single most important mock; it is the source for `AppShell` and the whole `ReactComponents/inbox/` desktop tree.

- **Header (56px, white, bottom hairline)**: hamburger `IconButton` (toggles the 240px filter rail) · `ozee-logo-sm.png` (26px, `data-brandmark` so dark mode brightens it) · "CRM" · `Search` (380px max, placeholder *"Search all mail, projects, clients"*) · right cluster: a "Viewing as" role `ButtonGroup` (manager / contractor — a prototype affordance, not a real feature), a theme `ButtonGroup` (Auto/Light/Dark), 1px divider, Notifications `IconButton`, `Avatar`.
- **64px icon rail**: `sc-for navItems` — active item gets `--primary-selected-color` + blue glyph; inactive gets the hover wash; badges are absolutely-positioned red pills animating `dcPulse 2s ease-out 2`.
- **Breach banner**: conditional negative `AlertBanner` with an action, for threads past the 1-hour reply rule.
- **240px filter rail (`<aside>`, collapsible)** with four stacked groups:
  1. **Views** (36px rows, icon + label + `Counter`): `needsReply` "Needs reply" (Alert) — *"Inbound mail with the 1-hour clock running"*; `new` "New" (Inbox) — Unread; `withAi` "With AI" (Robot) — *"Submitted drafts the AI checker is still verifying"*; `approval` "Waiting approval" (Security); `received` "Received" (Email); `sent` "Sent" (Send); `drafts` "Drafts" (Doc); `all` "All mail" (Archive).
  2. **Project** — searchable `Dropdown` (All my projects, Northshore Dental — site refresh, Bayside Legal — SEO retainer, Coast Cafe — online ordering, Perth Plumbing — brochure site, Riverton Physio — Google Ads, Leads (no project)).
  3. **Categories** — wrapped clickable `Chips`.
  4. **Refine** (above a hairline) — "Unread only" and "Overdue to reply only" `Checkbox`es, From/To date `TextField type=date`, tertiary "Clear filters".
- **List view** (`<main>`): sticky page header with `<h1>` in Poppins 24/30, a subtitle line, a sort `ButtonGroup`, and a primary **"New email"** button; then a select-all `Checkbox` row that, when anything is checked, reveals a blue selection tray — *Mark as read · Approve (permission-gated) · Categorise · Delete (negative)* — and, when sorting by breach, the right-aligned hint *"Closest to breaching the 1-hour reply rule first"* with a Sort glyph. Empty state uses `EmptyState iconName="Inbox"`. Rows carry avatar, sender, subject, preview, project chip, SLA chip and unread dot.
- **Thread view**: `<h2>` subject, approval action bar (*Approve & send · Edit & approve · Send back · Send to AI again*), an AI summary card with a suggested task (`{title, meta: "Suggested due Tomorrow · High · Dave Kelso · from Alan's last message"}`), message cards, internal-note cards (the yellow `--om-note-*` treatment), a reply composer, and an overflow `MenuButton`: *Mark as unread · Move to another project · Change categories · Print thread · Open in Gmail*.
- **Three modals**: `size="fullView"` **New email** — *"Templates keep the wording consistent; custom is for one-offs and leads."* (template vs custom mode, recipient modes Project clients / A lead / External address, recipient chips, template picker with 5 templates, greeting picker First name / Full name / Custom, schedule options *Send when approved / Tomorrow 8:00 am client time / Weekly Monday 9:00 am / Custom schedule*); `size="small"` **Send this back to the author** — *"They see your reason and can fix it without starting again."*; `size="medium"` **Create task from this email** (priority Low/Medium/High, assignee).
- **Editable prototype props** (from `data-props`): `role` enum, `theme` enum, `slaMinutes` int 15–240 default 60, `sortByBreach` bool, `aiAlwaysOn` bool.

#### `Inbox Mobile.dc.html` (1109 lines) — phone inbox

Rendered inside a 430px-max phone frame on a `#dfe3ee` stage.

- **Header**: logo + Poppins 18/24 screen title (Inbox / Alerts / Sent / Portal) + a pill button showing the role initials and label. Second row: `Search` (*"Search mail, clients, projects"*) + a 36px filter button with a blue count badge. Third row: a horizontally scrolling row of active-filter chips, each removable with a `✕`.
- **Breach strip**: full-width tappable `#fdecef` bar with warning glyph and chevron.
- **List**: pull-to-refresh (pointer events + a spinner row that grows from height 0), a heading/subheading line, then cards with **swipe actions** (a green "done" layer revealed right, an "Archive" layer left) over a white card containing avatar + SLA dot, sender, time, unread dot, subject, 2-line clamped preview, and a chip row (project short name, SLA chip, status badge).
- **Bottom tab bar**: a 5-column grid `1fr 1fr 76px 1fr 1fr` — **Inbox** (Inbox glyph, blue count badge) · **Alerts** (Alert glyph, red count) · a **56px circular blue FAB** (Edit glyph, `margin-top:-22px`, 3px page-coloured ring, medium shadow) · **Sent** (Send) · **Portal** (Globe).
- **Bottom sheets**: Filters sheet (16px top corners, `mSheet` slide, backdrop, header with "Clear all" and close, sections Status / Risk / Date / Project, and an apply button labelled *"Show N emails"*). Filter vocabulary: Status *Everything / Needs reply / Waiting approval / Screened / New enquiries / Done*; Risk *Any / Past the hour / Under 20 min*; Date *Any time / Today / Yesterday / This week / Last 7 days / This month*; plus a searchable project picker push-screen and "Unread only".
- **Composer**: project `Dropdown` + subject `TextField` + block add-buttons relabelled for phone — **Words / Bullets / Button / Picture** — and templates *Weekly project update / Invoice reminder / Launch handover*.
- **Profile sheet**: My profile · OZee CRM dashboard · Auto/Light/Dark · Comfortable density · Sign out.

This is the direct source for `ReactComponents/inbox/mobile/*` including `Sheet`/`PushScreen`.

#### `Guest Project Proposals.dc.html` (1753 lines) — the external portal, three views in one file

Switched by `isProjectView` / `isProjectsView` / `isProfileView`.

- **Header** (sticky, min 56px, wraps): brand lockup, "Guest — not signed in" or "Signed in as …", nav (All projects / Profile), Sign out.
- **Project view**: a `minmax(0,1fr) 404px` grid at `max-width:1240px`. Left: project hero, **Work phases** (*"Each phase is priced and invoiced on its own"*, *"Completed and cancelled phases are hidden"*), expandable phases with **Open deliverables**, Status, Target completion, and a **Propose for this phase** action. Right rail: either a **Sign in to propose** panel (email → *Send code* → *Code expires in 9:41* / *Resend code* → *Verify and continue*) or **Your proposals** (*"Only you and the OZee team can see your proposals."*, *"No proposals here yet — Quote a phase and it'll show up with its status."*, plus **Phases you haven't quoted**), then **Get in touch / Contact / Need a hand? / Thornlie, Western Australia**.
- **Projects view**: `<h1>` Poppins 32/40 **"Projects shared with you"** + a card grid.
- **Profile view**: `<h1>` **"Your details"** — *"Used on every proposal and bill you send to the OZee team."* — Email verified state, **Verification details**, **Payment methods** (*"No payment methods yet — add one so your bills can be paid."*, Add a method / Make default / Paid to), a warning `AttentionBox` *"Finish your verification details — International transfers can't clear without your date of birth, ID number and address…"*, and *"Your details are only visible to the OZee team running this project."*
- **Footer** (white, top hairline) and three overlays: `size="large"` **proposal modal** — *"Quote one phase, several at once, or the whole project. Several phases are saved as one proposal each, so every phase carries its own price."* with **What does this proposal cover?**, Select all, Amount per phase, **Distribute evenly** / **Split evenly**, Estimated total, Total contract value, Payment terms, Add stage, Supporting document (optional) — *"Backs up this quote — nothing is signed here"*; `size="small"` **Upload your bill** (Invoice, Amount, Choose file, Pay this bill to, *"Bills are paid on 7-day terms."*, Add another bill); and a positive `Toast`.

#### `EmailBlocks.dc.html` (150 lines) — the block-based email composer

A two-column `auto-fit minmax(360px,1fr)` grid. Left: an add-block toolbar (**Text / Bullets / Link / Image** secondary buttons + a tertiary **Bulk paste**) with a right-aligned block count; a blue info strip explaining the inline syntax *"Link a few words inside any line by typing (Label)[https://example.com]. Bold a phrase with \*asterisks\*."*; an optional bulk-paste panel (*"Paste your draft — every line becomes its own block"* → **Split into blocks**); then a draggable block list, each block a card with a Drag handle, a `Label kind="line"` type badge, up/down `IconButton`s and a `MenuButton` (*Text / Bullet list / Link / Image / Duplicate block / Delete block*), and type-specific fields (Image block offers URL + alt + a dashed drop-zone: *"Drop a screenshot here — it goes inline, 600px wide, and is attached as a fallback"*). Right: a **sticky, deliberately light-mode-locked** *"Live preview — what the client receives"* card rendering greeting, paragraphs with bold/link parts, bullet lists, link rows, image placeholders, and an OZee signature block.

#### `Canvas.dc.html` — an empty `<x-dc></x-dc>` scratch canvas. Nothing to describe.

### The navigation model the mocks imply

Two distinct shells, both already reflected in code:

1. **Internal app** — 56px header (brand + global search + theme + notifications + avatar menu) over a 64px icon rail (My work / Projects / Tasks / Inbox / Reports / Team / Admin, permission-filtered, badge-capable), plus an optional 240px *contextual* left panel that belongs to the page (inbox views/filters here; per the readme, a 240/64px project sidebar elsewhere). Content scrolls under the fixed chrome. On phones the rail becomes a 5-slot bottom bar with a centre FAB and sheets replace side panels.
2. **Portal (external)** — a much lighter shell: sticky header with account state, a centred `max-width:1240px` content column with a 404px right rail on the project screen, and a real footer with contact details. No icon rail.

---

## 5. `Design Guide.dc.html` and `ozee-charts.js`

**Neither exists anywhere under `/home/claude/src`.** `find . -iname "*Design Guide*" -o -iname "ozee-charts.js"` returns nothing. The written design guidance lives in `Redesign/*/_ds/vibe-monday-.../readme.md`; there is no charting helper in the design bundle at all, and no chart component in the Vibe component list — charts in v2 will need a fresh decision (the Vue app uses `chart.js/auto` in 7 places).

---

## 6. Other gaps and hazards found

1. **`public/` is absent from this extract.** `ds/Icon.jsx` hardcodes `ICON_BASE = '/ozee-ds/icons'` and `AppShell` requests `/ozee-ds/ozee-logo-sm.png`. Those files must exist (copied from `Redesign/inbox-mobile/assets/icons/`) or every icon renders as an empty box. Also, only **63** of the readme's claimed **90** glyphs shipped.
2. **Tailwind never scans `.jsx`** — any Tailwind class written in a React file silently does nothing today.
3. **`HandleInertiaRequests` computes `$globalPermissions` and throws it away** (assignment commented out), so every React and Vue page does an extra `/api/user/permissions` round trip on boot.
4. **The React shell has no realtime.** The `Notifications` icon in `AppShell` has no `onClick` and no Echo subscription; `bootstrap.js` only wires Echo for the Vue entry path (it is imported by `app.js` so `window.Echo` exists, but nothing subscribes on React pages).
5. **`AppShell.headerSearch` is an unconnected prop** — the global-search endpoint (`/api/global-search`) exists and is used by Vue only.
6. **Duplicate/dead code to not carry over**: `Services/api.js` ≡ `Services/api-service.js`; `resources/js/src/Components/Notification.vue` (orphan); empty `resources/js/archive/`; deps `quill`, `swiper`, `vue-json-pretty`, `@tailwindcss/vite` (v4 plugin unused alongside Tailwind v3); the `<script>` monkey-patch block in `app.blade.php`; the vestigial `localStorage.authToken` bearer-token flow.
7. **Two icon libraries in the Vue app** (`@heroicons/vue` 53 files, `lucide-vue-next` 24) that the design system explicitly forbids.
8. **Nav duplication** — `TopNavigation.vue` and `MobileNavigation.vue` hand-maintain overlapping lists that have already drifted (documented in `navigation.js`). `TopNavigation` exposes far more than the 7 rail items: Clients, Schedules, Automation, Prompts, Task Types, Shareable Resources, Placeholder Definitions, Email Templates/Apps, External Tokens, Categories, Campaigns, Leads, Notice Board, Project Tiers/Services, Monthly Budgets, Stripe Configuration, Financial Dashboard, Sales Invoices, Contractor Bills, Proposals, Project Transactions, Profit and Loss, Productivity/Activity/CTO Reports, User Live Status, Bonus Calculator, Manage Roles/Permissions, Weekly Availability, Team Chat, Give Kudo, Add Task/Resource/Meeting Minutes. **The 7-item rail is a deliberate simplification — v2 needs a designed home for the other ~35 destinations** (the readme's answer would be a 240px contextual sidebar under Admin/Reports/Finance, plus the avatar menu).

---

## 7. Recommended v2 frontend structure

```
resources/
├── css/
│   └── ozee-ds/                     # keep exactly as-is; it is the ported bundle
│       ├── index.css                # the one entry point React pages import
│       ├── tokens/*.css             # sync from Redesign/**/_ds/tokens, never hand-edit
│       ├── theme-dark.css
│       ├── animations.css
│       └── components/              # NEW: the few real class-based styles
│           ├── email-body.css       #   (.ozds-email-body / -quote / -letter-*)
│           └── mobile.css           #   (.om-* )
└── js/
    ├── app.jsx                      # single React entry once Vue is gone;
    │                                # keep the app.js dispatcher while both live
    ├── bootstrap.js                 # axios + Echo (unchanged)
    ├── ds/                          # promoted out of ReactComponents/
    │   ├── Button.jsx Icon.jsx Fields.jsx Toggles.jsx Chips.jsx
    │   ├── Display.jsx Feedback.jsx States.jsx Menu.jsx Popover.jsx Search.jsx
    │   ├── Table.jsx Tooltip.jsx DatePicker.jsx Combobox.jsx   # ← port next, from _ds_bundle.js
    │   ├── Breadcrumbs.jsx Accordion.jsx Steps.jsx AvatarGroup.jsx
    │   └── index.js
    ├── shell/                       # was ReactComponents/app/
    │   ├── AppShell.jsx  Rail.jsx  Header.jsx  Footer.jsx
    │   ├── MobileShell.jsx          # generalised from inbox/mobile/MobileShell
    │   ├── navigation.js            # rail + secondary nav + user menu, as data
    │   └── PortalShell.jsx          # was portal/PortalChrome
    ├── hooks/
    │   ├── usePermissions.js  useTheme.js  useToasts.js  useIsMobile.js
    │   ├── useEcho.js               # NEW: private user channel + presence
    │   ├── useGlobalSearch.js       # NEW: /api/global-search, debounced
    │   ├── useFlash.js              # NEW: reads Inertia shared `flash` → toasts
    │   └── useUpload.js             # NEW: one FormData uploader (23 Vue call sites)
    ├── lib/
    │   ├── format.js                # dates, money, plural, initials, fileSize
    │   ├── status.js                # status → board-palette token mapping
    │   └── api.js                   # thin axios wrapper, error → toast
    ├── features/                    # one folder per domain, page-owned components
    │   ├── inbox/{desktop,mobile,hooks,components}/
    │   ├── portal/
    │   ├── projects/  tasks/  workspace/  admin/  reports/  automations/
    ├── pages/                       # Inertia page components only — thin
    │   ├── Inbox/Index.jsx
    │   ├── Portal/{Projects,Project,Profile}.jsx
    │   └── …
    └── legacy-vue/                  # the whole existing Vue tree, moved wholesale
        ├── Pages/  Components/  Layouts/  Composables/  Utils/  Directives/  Stores/
```

**Sequencing and principles**

1. **Keep the `React/` prefix dispatcher** until the last Vue page is gone; it is proven and the two failure modes (silent frozen page on cross-framework Inertia visits, logout redirect) are already handled. Move the Vue tree under `legacy-vue/` so "is this v1 or v2?" is answerable from the path.
2. **Finish the primitive port before building pages.** `Table` first — a CRM without a table component means every list is bespoke. Port from `_ds_bundle.js` rather than inventing, as `ds/index.js` instructs.
3. **Fix the four plumbing gaps in the shell now**: wire `headerSearch` to `/api/global-search`, add `useEcho` + a notifications panel, share `flash` and `permissions` from `HandleInertiaRequests` (uncomment and finish `global_permissions`), and copy the icon set + brand marks into `public/ozee-ds/`.
4. **Decide inline-styles vs Tailwind once, at the top.** Recommendation: keep inline styles inside `ds/` (they are a faithful port and are already written), but for feature code either add `.jsx` to Tailwind's `content` and map the CSS variables into `theme.extend`, or commit to a small set of `.ozds-*` classes. Do not leave it ambiguous — today React feature files use neither Tailwind nor classes, which makes hover/focus/media queries hand-rolled everywhere.
5. **Model navigation as data in one file** (`shell/navigation.js`) covering the 7-item rail *and* the ~35 secondary destinations grouped for a contextual 240px sidebar (`--shell-sidebar-width` is already a token). This is the one v1 mistake the code comments explicitly call out.
6. **Port order by leverage**: inbox is done → workspace / my work → projects list + project detail (use the bundle's `ProjectsScreen`/`ProjectDetailScreen` as the reference, since no `.dc.html` exists) → tasks → reports (needs a charting decision — no design guidance exists) → admin → automations last (vue-flow → React Flow is the biggest single rewrite).
7. **Enforce the system**: the bundle ships `_adherence.oxlintrc.json` (27 KB of rules). Wire it into the v2 lint step so hardcoded hex values and off-scale spacing fail CI rather than being caught in review.