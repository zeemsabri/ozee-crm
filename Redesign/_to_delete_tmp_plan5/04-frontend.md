# 04 — Frontend (React 19 + Inertia 2 + TypeScript)

> Read together with `11-design-system-components-and-mobile.md`, which defines the component layers, API rules, pattern library, mobile recipes and the checklists.

## 1. Foundations to copy on day one (ticket F0-04/F0-05)

From legacy repo → new repo:
- `resources/css/ozee-ds/**` → same path. Keep `tokens/*.css` byte-identical to `Redesign/CRM Restructure/_ds/vibe-monday-*/tokens/`. Add `components/` folder for the few class-based styles (`.ozds-email-body`, `.ozds-letter-*`, `.om-*`).
- `resources/js/ReactComponents/ds/**` → `resources/js/ds/` (rename `.jsx` → `.tsx` incrementally; keep behaviour). Includes `useAnchoredPopover`/`Popover` — **all anchored panels go through it** (portal on body, z 10050).
- `resources/js/ReactComponents/app/{AppShell,navigation,usePermissions,useTheme,useToasts,useIsMobile}` → `resources/js/shell/` and `hooks/`. `usePermissions` is rewritten to read `usePage().props.auth.permissions` (no fetch).
- `resources/js/ReactComponents/inbox/**` → `features/inbox/` (desktop + mobile + `Sheet`/`PushScreen` primitives → `ds/mobile/`). Wire to the new `/api/v1/inbox/*` shapes (03-modules §3.5).
- `resources/js/ReactComponents/portal/**` → `features/portal/`.
- Icons: `Redesign/CRM Restructure/_ds/.../assets/icons/*.svg` → `public/ozee-ds/icons/`; logos `ozee-logo-sm.png`, `assets/ozee-logo-full.png` → `public/ozee-ds/`.
- `Redesign/CRM Restructure/ozee-charts.js` → port to `ds/charts/OzeeChart.tsx` (see §6).

## 2. Primitives still to port from `_ds_bundle.js` (in priority order)
`Table` (headless TanStack + Vibe styling: 40px rows / 36 dense, sticky header `#f6f7fb`, hover `#f0f6ff`, sortable headers, row selection tray), `Tooltip`, `DatePicker`, `Combobox` (async search picker for contacts/projects/users), `BreadcrumbsBar`, `Accordion`/`ExpandCollapse`, `AvatarGroup`, `Badge`, `Heading`/`Text`, `Link`, `Steps`/`MultiStepIndicator`, `NumberField`, `SplitButton`, `Tipseen`, `List/ListItem`, `EditableText`, `Slider`, `ColorPicker`. Rule from `ds/index.js`: port from the bundle, do not invent. Each port = one ticket with a Storybook-less "kitchen sink" page at `/dev/ds` (dev env only) showing every variant.

## 3. Composite patterns (from Design Guide §11 — copy the recipes verbatim, as components)
`PageHeader` (eyebrow, h1 Poppins 32/40, sub-line, actions right: secondary first, primary last), `StatTile` + `StatGrid` (`repeat(auto-fit,minmax(190px,1fr))`, delta green/red/grey, card stays white), `HeroCard` (4px accent strip, the only coloured bar allowed), `InsightWash` (blue neutral / green good / amber watch / purple explanation), `ListRow` (32px icon disc: square for objects, circle for people; title 600 14/20; sub 400 12/16 grey; tag pill; chevron), `EmptyState` (icon disc 56, Poppins 18/24 title, ≤340px body, secondary button; no illustrations), `SidePanel` (right-anchored detail panel used for task detail / thread actions), `FilterRail` (240px collapsible contextual left panel), `SelectionTray` (blue tray above tables when rows are checked), `BoardTable` (grouped rows with group headers — Client Board), `PhoneFrame` layouts: bottom tab bar 5 slots with centre FAB, sheets.

## 4. Shell & navigation (one data file `shell/navigation.ts`)
- **Internal shell:** 56px header (logo, "CRM", global search `Search` 380px → `/api/v1/search`, theme `ButtonGroup`, notifications `IconButton` → `NotificationsPanel` (Echo-live), avatar `MenuButton`: Profile, Attendance(deferred → hidden), Log out) + 64px rail: `home` Home, `projects` Board, `tasks` CheckList, `inbox` Email (badge = needs_reply), `contacts` Person (Client Board), `finance` Chart (perm finance.dashboard), `admin` Settings (perm admin.view). Badges pulse `dcPulseAlert`.
- **Contextual 240px sidebar** (per section, `--shell-sidebar-width`): Finance → Dashboard, Invoices, Bills, Proposals, Ledger, Reconciliation, Catalogue, Xero, Stripe. Admin → Users, Roles, Settings, Email templates, Mailboxes, Access links, Vault, Task types, Taxonomies. Projects → project tabs. Inbox → FilterRail views.
- **Mobile (≤860px):** bottom bar Inbox · Alerts · FAB(compose/new task) · Sent · Portal; sheets replace panels.
- **Client OS shell:** same 56px bar + rail but rail items: Home, Plan, Reports, Approvals, Documents, Invoices, Vault; content centred 780px (reading) / 1000px (plan). Larger type, no jargon.
- **Supplier portal shell:** `PortalShell` — sticky header (brand, account state, All projects / Profile, Sign out), centred 1240px, 404px right rail on project, footer with contact details. No rail.
- Cross-page navigation is Inertia `<Link>` everywhere (single runtime now). Sign out is a POST form.

## 5. Page catalogue (R1) — `resources/js/pages/<Module>/<Page>.tsx`
| Route | Page component | Layout | Design source |
|---|---|---|---|
| /login, /login/verify, /forgot-password, /reset-password | Identity/Login, VerifyCode, Forgot, Reset | Guest split panel (reuse Client OS Login brand panel) | Client OS Login |
| /home | Work/Home (variant today|glance via prop) | Internal | Home A / Home B |
| /projects | Work/Projects/Index (BoardTable grouped by status; filters) | Internal | Admin Console tables + bundle ProjectsScreen |
| /projects/create, /projects/{id}/settings | Work/Projects/Form | Internal | Page header + form recipe |
| /projects/{id} (+tabs) | Work/Projects/Show + tab components | Internal + project sidebar | bundle ProjectDetailScreen; Guest Project Proposals for phases |
| /tasks, /tasks/board | Work/Tasks/Index, Board | Internal | Home A queue rows; kanban from legacy KanbanBoard behaviour |
| /inbox | Comms/Inbox/Index (Desktop/Mobile) | Internal + FilterRail | OZee CRM Inbox (newest), Inbox Mobile |
| /admin/email-templates | Comms/Templates/Index, Edit (editor + preview) | Internal + admin sidebar | Admin Console |
| /inbox/settings | Comms/Settings | Internal | Admin Console |
| /contacts, /contacts/{id} | Crm/Contacts/Board, Show | Internal | Client Board |
| /leads | Crm/Leads/Pipeline | Internal | Client Board (kanban variant) |
| /campaigns | Crm/Campaigns/Index | Internal | Admin Console table |
| /finance, /finance/invoices, /finance/invoices/{id}, /finance/bills(+id), /finance/proposals, /finance/ledger, /finance/reconciliation | Finance/* | Internal + finance sidebar | Stat tiles + tables; charts via OzeeChart |
| /admin/users, /admin/roles, /admin/settings, /admin/catalogue, /admin/xero, /admin/stripe, /admin/access-links, /admin/vault, /admin/task-types, /admin/taxonomies | Identity/Admin/*, Finance/Admin/*, Access/Admin/*, Platform/Admin/* | Internal + admin sidebar | OZee Admin Console (every tab/panel in that mock maps here — see 09) |
| /portal, /portal/projects/{id}, /portal/profile, /p/{id}/{code} | Portal/Supplier/* | PortalShell | Guest Project Proposals |
| /client/login, /client, /client/plan, /client/reports(+/{month}), /client/approvals, /client/documents, /client/invoices, /client/vault, /client/announcements/{id} | Portal/Client/* | Client OS shell | Client OS mocks |
| /dev/ds | Dev/DesignSystem (dev only) | Internal | Design Guide §10 live components |

## 6. Charts
Port `ozee-charts.js` faithfully: `<OzeeChart type spec height big?>` wrapping `react-chartjs-2` (Chart.js 4) for line/bar/stacked-bar/donut/sparkline, and pure DOM for progress/gauge/heatmap/funnel; theme from tokens via `getComputedStyle` re-read on theme change; series colour order locked: bright-blue, done-green, working-orange, dark-purple, aquamarine, lipstick; `ResizeObserver` for sizing. Spec schema identical so Laravel can send report payloads straight through (`survey/design_guide.md §3`). Chart rules (guide §12): one accent series unless comparing, grid hairlines only, no 3D, numbers lead.

## 7. Types & data flow
- Inertia page props typed via `resources/js/types/inertia.d.ts`; generate TS types from PHP DTOs with `spatie/laravel-typescript-transformer` (`npm run types`). Every Query returns a `Data` DTO (spatie/laravel-data) so types are exact.
- Mutations: Inertia `router.post/put` with `preserveScroll` and flash → toast (`useFlash` hook). In-page lists (inbox threads, pickers, notifications): TanStack Query against `/api/v1`, `staleTime` 15s, invalidated on Echo events.
- Realtime: `useEcho()` subscribes `private-user.{id}` (notifications, counters) and `private-project.{id}` on project pages.
- Forms: `ds` fields + `react-hook-form` + `zod` schemas mirrored from FormRequests (keep messages identical).
- Permissions: `can('slug')` from shared props; project pages pass `project_permissions`.
- Theme: `data-theme` on `<html>` (auto/light/dark) — dark mode is a first-class requirement (guide §06).
- No localStorage for business state; only theme/density/last filters.

## 8. Styling rules (lint-enforced)
- Tokens only: any hex literal outside `resources/css/ozee-ds/tokens` fails lint (`_adherence.oxlintrc.json` ported into `eslint.config.js`, plus a stylelint rule `color-no-hex` for `.module.css`).
- Type scale: Poppins 32/40, 24/30, 18/24 headings; Figtree 16/22, 14/20, 12/16; weights 400/600/700 only.
- Spacing scale 2·4·8·12·16·20·24·32·40·48·64·80; radius 4/8/16; shadows xs/s/m/l; motion 70/100/150 productive, 250/400 expressive; press squeeze `scale(.95)`; focus ring token.
- No gradients, photography, illustration, blur, icon fonts. Sentence case, verb-first buttons, dates like `22 Aug`, no emoji in chrome.
- Accessibility: every clickable is a button/link; `aria-pressed` for selected chips; focus visible; modal focus trap; colour never the only signal (status pill has text).

## 9. Legacy behaviours to reproduce in the shell (from survey/frontend.md §3)
Global search, notifications centre with read/dismiss, toasts (queue of 3), blocking gates as needed (extension enforcement modal when `user.requires_extension` — keep; availability blocker deferred), prompt orchestrator (deferred), task side panel opened from anywhere, unread counters, dark mode, uploader hook `useUpload` (single implementation, replaces 23 ad-hoc FormData uploads).
