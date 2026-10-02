# 11 — Design system build guide: component layers, reusability rules, mobile optimisation

This file is the *how* for every frontend ticket. 04 says *what* exists; this says how to build it so it stays reusable, and how every screen behaves on a phone. Executors must follow the checklists in §7 and §8 before marking a UI ticket done.

## 1. The five layers (nothing skips a layer)

```
L0 tokens        resources/css/ozee-ds/tokens/*.css        values only (colour, type, space, radius, shadow, motion)
L1 primitives    resources/js/ds/*                          Button, TextField, Table, Modal… (Vibe port). Zero business meaning.
L2 patterns      resources/js/ds/patterns/*                 PageHeader, StatTile, ListRow, HeroCard, EmptyState, SidePanel, FilterRail, BoardTable, Sheet… Composed from L1. Still zero business meaning.
L3 feature       resources/js/features/<module>/*           TaskCard, ThreadListItem, InvoiceStatusPill, ProposalModal… Know entities; composed from L1+L2; no routing.
L4 pages         resources/js/pages/<Module>/<Page>.tsx     Inertia pages: read props, pick layout, compose L3. ≤150 lines; no styling of their own.
```

Rules:
- A file may import only from its own layer or lower. ESLint `import/no-restricted-paths` enforces it (config in F0-04).
- Business words (task, invoice, thread) never appear in L1/L2 file names, props, or copy.
- If two L3 components share markup, the shared piece becomes an L2 pattern **only after the second use** (rule of two, not rule of one — avoid speculative abstractions).
- Every L1/L2 component has a story on `/dev/ds` (a plain route in dev showing every variant, both themes, three viewports). A component without a story does not exist.

## 2. Component API conventions (L1–L3)

1. **One responsibility, named by what it is**, not where it is used: `ListRow`, not `InboxRow`; `StatusPill`, not `TaskStatusPill` (the task variant is an L3 wrapper mapping enum → pill props).
2. **Props are data + callbacks, never JSX bags of styles.** Allowed prop kinds: values (`value`, `items`), enums (`size`, `kind`, `tone`), booleans (`disabled`, `loading`, `selected`), callbacks (`onChange`, `onSelect`), slots (`children`, `leading`, `trailing`, `actions`, `footer`). Forbidden: `style`, `className` on L2/L3 (L1 accepts `style` only for layout glue: `width`, `margin`).
3. **Variants are enums, never booleans that fight**: `kind="primary" | "secondary" | "tertiary"` not `primary`, `secondary` booleans. Sizes: `xs | small | medium | large` (Vibe scale: 24/32/40/48 px controls).
4. **Tones are semantic**, mapped centrally: `tone="neutral" | "info" | "positive" | "warning" | "negative" | "brand"` → `lib/status.ts` maps every entity enum to a tone + label once (`taskStatusTone(status)`). No component reads an entity enum directly except these mappers.
5. **Controlled by default, uncontrolled optional**: `value` + `onChange`; `defaultValue` for forms that don't need control. Same for `open`/`onOpenChange` on overlays.
6. **Composition over configuration**: `Modal` has `title`, `description`, `children`, `footer`; it does not have `showSaveButton`. Complex bodies are children. Compound components for tight coupling: `Table.Root / Table.Head / Table.Row / Table.Cell / Table.SelectionTray`; `Tabs.List / Tabs.Tab / Tabs.Panel`; `Sheet.Root / Sheet.Header / Sheet.Body / Sheet.Footer`.
7. **Every interactive primitive is accessible by construction**: real `<button>`/`<a>`/`<input>`; `aria-*` derived from props (`aria-pressed` from `selected`, `aria-busy` from `loading`, `aria-invalid` from `validation`); focus ring token; Escape closes overlays; focus trap in `Modal`/`Sheet`; roving tabindex in `Menu`/`Tabs`; `IconButton` requires `ariaLabel` (TS error otherwise).
8. **Loading/empty/error are props, not separate components**: list-type L2/L3 components accept `state="idle" | "loading" | "empty" | "error"` with `emptyTitle/emptyBody/emptyAction` and render `Skeleton`/`EmptyState`/`AlertBanner` themselves. Pages never hand-roll a loading branch.
9. **Density**: `density="comfortable" | "compact"` on `Table`, `ListRow`, `BoardTable` (40 / 36 px rows); read from user preference, never hard-coded per page.
10. **Typed contracts**: every component exports its `Props` type; L3 components take **DTO types generated from PHP** (`types/generated.d.ts`) — never `any`, never re-declared shapes.
11. **No data fetching inside L1/L2.** L3 may use TanStack Query hooks from `features/<module>/hooks` (one hook per endpoint, typed). Pages pass Inertia props down.
12. **Styling**: L1 = inline styles from tokens (faithful Vibe port, already written). L2/L3 = CSS Modules (`Component.module.css`) using `var(--…)` only; responsive via `@media` in the module; hover/focus via CSS. No inline hover state in JS outside L1.
13. **Icons** by name only (`<Icon name="Search" />`); the 76-glyph set is the whitelist (TS union generated from `public/ozee-ds/icons`).
14. **Copy** lives in the component's `copy.ts` (sentence case, verb-first) so UX text is reviewable and later translatable; dates via `lib/format.ts` (`22 Aug`, `Tomorrow 8:00 am`).
15. **Deprecation**: a component is removed only after `grep` shows zero imports; never keep two versions (`ThreadListV2`).

## 3. Canonical L2 pattern library (build these, then reuse everywhere)

| Pattern | Props (summary) | Used by |
|---|---|---|
| `AppShell` | `title, activeRail, sidebar?, headerSearch?, headerExtra?, children` | every internal page |
| `PortalShell` / `ClientShell` | `account, branding, current, children` | portals |
| `ContextualSidebar` | `groups:[{label, items:[{key,label,icon,href,badge}]}], collapsed` | Admin, Finance, Projects, Inbox rails |
| `PageHeader` | `eyebrow?, title, subtitle?, breadcrumbs?, actions?(secondary first), tabs?` | all list/detail pages |
| `StatGrid` + `StatTile` | `label, value, delta?:{value,tone}, note?, spark?:spec` | Home, project overview, finance |
| `HeroCard` | `accentTone, eyebrow, headline, action` | Home "needs you" |
| `InsightWash` | `tone, icon, children` | reports |
| `ListRow` / `ListGroup` | `disc:{icon|avatar,shape,tone}, title, sub, tag?, meta?, chevron, onClick, selected` | every "row with disc" list |
| `BoardTable` | `groups:[{key,label,tone,rows}], columns, onGroupToggle, selection` | contacts, projects, admin lists |
| `DataTable` (wraps `Table`) | `columns (typed), rows, sort, selection, state, density, emptyProps, pagination|cursor` | all tables |
| `Kanban` | `columns:[{key,label,tone,items}], onMove, renderCard` | tasks board, leads pipeline, Client Board |
| `SidePanel` | `open, title, tabs?, children, footer, width=480` | task detail, contact quick view |
| `FilterRail` | `sections:[views|select|chips|refine], collapsed` | inbox, finance lists |
| `SelectionTray` | `count, actions:[{label,tone,onClick,permission}]` | tables |
| `FormLayout` | `sections:[{title,description,fields}]` two-column on desktop, stacked on phone | all forms |
| `FilterBar` | `search, filters:[{key,label,options}], sort, onClear` | list pages (collapses to a Sheet on phone) |
| `EmptyState` | `icon, title, body, action` | everywhere |
| `Sheet` / `PushScreen` | bottom sheet / push navigation | phone overlays |
| `BottomTabBar` | `items(5, centre FAB)` | phone shell |
| `Timeline` | `items:[{when, who, what, tone}]` | activity feeds, audit |
| `ApprovalBar` | `status, actions, reason?` | messages, bills, proposals, deliverables |
| `OzeeChart` | `type, spec, height, big?` | reports |
| `ImageSlot` | `value, onUpload, ratio, purpose` | logos, media |

## 4. Mobile optimisation — the strategy

**Policy (user, 2026-09-02):** dedicated mobile designs may be produced later; until then **no page ships without a working phone layout** derived from the recipes below. Mobile is an acceptance criterion of every page ticket (§8), not a later phase. When a dedicated mobile mock arrives for a page, it replaces the recipe for that page only — the L2 patterns stay.

**Breakpoints** (CSS custom media, single source `resources/css/ozee-ds/breakpoints.css`): `phone < 640`, `tablet 640–1023`, `desktop ≥ 1024`, `wide ≥ 1440`. `useViewport()` hook returns the same names. Everything is **mobile-first in CSS** (base = phone, `@media (min-width)` adds).

**Shell behaviour**

| Region | Desktop | Tablet | Phone |
|---|---|---|---|
| Top bar | 56px: logo, search 380px, theme, notifications, avatar | 56px: logo, search icon → expands, avatar | 56px: title + search icon + avatar; page actions move into an overflow menu |
| Icon rail | 64px | 64px | hidden → `BottomTabBar` (5 slots, centre FAB = primary action of the current area) |
| Contextual sidebar 240px | visible, collapsible | collapsed 64px, expands as overlay | hidden → "Filters"/"Sections" button opens a `Sheet` |
| Main | fills, `padding 24` | `padding 16` | `padding 12–16`, single column |
| Side panel 480px | overlays right | overlays right (`max 92vw`) | full-screen `PushScreen` |
| Modal | centred, sizes | centred | full-screen `dense` (already in `Modal`) |
| Toasts | bottom-right stack | same | top, full width |

**Page-type recipes (every page declares which one it uses)**

1. **List + detail (master/detail)** — inbox, threads, contacts, invoices: desktop = list pane + reading pane; tablet = list, detail as side panel; phone = list only, tap → `PushScreen` detail with back header (already how the inbox mobile works — generalise it into `MasterDetail` L2).
2. **Table pages** — never horizontal-scroll the page. Phone: `DataTable` renders `cardMode` (each row as a card with the 2–3 `primary` columns + an overflow for the rest; column config marks `priority: 1|2|3`; priority 3 hidden on tablet, 2+3 on phone). Selection tray becomes a sticky bottom bar.
3. **Kanban** — phone: one column at a time with a swipeable column header (chips), drag disabled → move via card menu ("Move to…").
4. **Detail pages with tabs** — tabs become a horizontally scrollable `Tabs` strip; sticky under the header; stat grid wraps to 2-up.
5. **Forms** — `FormLayout` stacks; inputs full-width; sticky footer with primary action; native pickers on phone (`DatePicker` uses `<input type="date">` under 640 px).
6. **Dashboards/reports** — `StatGrid` 1-up on phone, 2-up on tablet; charts get `height` from `useViewport` and hide legends below 640 (legend rendered as a list under the chart).
7. **Composer** (email, comments) — phone: full-screen `Sheet`, toolbar as bottom row, attachments as chips; autosave draft every 5 s.
8. **Portals** — Client OS and supplier portal are **phone-first**: 780px reading column collapses to full width, cards stack, the 404px right rail moves below the content, approve/request-changes become a sticky bottom action bar.

**Touch & performance rules**: targets ≥ 44×44 px (Vibe `medium` 40 px controls get 4 px extra tap padding on phone); no hover-only affordances (every hover action has a tap equivalent: overflow `MenuButton` or long-press sheet); swipe actions only where the inbox already defines them (done/archive) and always with a visible menu alternative; `100dvh` not `100vh`; safe-area insets on the bottom bar; lists virtualised beyond 100 rows (`@tanstack/react-virtual`); images lazy with fixed aspect boxes; no layout shift on data load (skeletons keep dimensions); route-level code splitting so the phone loads only its page.

**Testing**: Playwright runs the six smoke journeys at 390×844 (phone), 834×1194 (tablet), 1440×900 (desktop); axe checks per viewport; a screenshot per `/dev/ds` story per viewport per theme is stored as a visual baseline (`npm run ds:snap`).

## 5. Theme, density, motion
`data-theme` auto/light/dark on `<html>` (internal); Client OS light-only in R1 but built with tokens so dark is a switch later. Density preference stored per user (`users.preferences.density`). Motion tokens only; `prefers-reduced-motion` disables `dcRise/dcPulse`.

## 6. Where the existing inbox code goes (worked example of the layers)
`features/inbox/`: `ThreadList` (L3) = `MasterDetail.List` + `ListRow` variants; `ThreadView` (L3) = `PageHeader` + `ApprovalBar` + `MessageCard` (L3) + `Composer` (L3); mobile files (`MobileThreadList`, `Sheet`, `PushScreen`) split: `Sheet`/`PushScreen`/`BottomTabBar` → `ds/patterns` (L2), the rest stays L3 and is expressed through the recipes above instead of a separate mobile tree. Target: **one** `pages/Comms/Inbox.tsx` that renders the same L3 components with viewport-driven layout — not two page trees.

## 7. Component ticket checklist (L1/L2)
- [ ] API follows §2 (variants enums, slots, controlled, tone mapping, no style/className leakage)
- [ ] Story on `/dev/ds`: all variants × light/dark × phone/tablet/desktop
- [ ] Keyboard: tab order, Enter/Space, Escape, arrow keys where a list/menu
- [ ] axe clean; contrast AA; focus ring visible
- [ ] `state` prop for loading/empty/error where it renders collections
- [ ] Density variants where rows exist
- [ ] Unit test (Vitest + Testing Library) for behaviour, not markup
- [ ] Docs comment at top: purpose, when NOT to use it, closest alternative

## 8. Page ticket checklist (L4)
- [ ] Declares its recipe (§4) and renders correctly at the three viewports (screenshots attached to the PR)
- [ ] No component defined inside the page file; no inline styles; no business copy outside `copy.ts`
- [ ] Uses `PageHeader`, `EmptyState`, `Skeleton` from patterns; permissions via `can()` hide actions (not disable) unless the guide says otherwise
- [ ] Primary action reachable from the phone bottom bar/FAB
- [ ] Inertia partial reloads for filters; TanStack Query for live lists; no waterfall of client fetches on load (max 1 after the page render)
- [ ] Dark mode checked (internal pages)
- [ ] Playwright journey updated if the page is on one

## 9. Tickets (added to 05)
- **F0-04b · Layer lint + breakpoints + `useViewport` + `/dev/ds` harness with viewport/theme switcher and `ds:snap`** — M, blocked by F0-04.
- **F0-07** (existing) now must deliver the full §3 pattern library incl. `MasterDetail`, `DataTable` card mode, `Kanban` phone mode, `FormLayout`, `FilterBar`→Sheet, `BottomTabBar`, `Timeline`, `ApprovalBar` — upgrade to L, split into F0-07a (layout/shell patterns) and F0-07b (data patterns).
- **C2-09/C2-10 merge**: one inbox page tree per §6 (the mobile ticket becomes "apply recipes", not a second tree).
- Every page ticket in 05 inherits checklist §8; every primitive ticket inherits §7.
