# OZee CRM — Claude Design export survey ("CRM Restructure")

Source: `/home/claude/design/CRM Restructure/` (Claude Design export, 2 Sep 2026).
Target: Laravel + Inertia + React rewrite of the OZee Web & Digital internal CRM plus a client-facing portal ("Client OS").

This document is written for a planner turning screens into implementation tickets. Every value quoted is taken verbatim from the mocks or the guide. Where I infer, I say "inferred". Section numbering:

0. Export layout & file inventory
1. `Design Guide.dc.html` — the build reference (all 15 sections, every rule, every recipe)
2. `uploads/OZEE Design Guide.pdf` — brand sheet, and conflicts with the HTML guide
3. `ozee-charts.js` — `<ozee-chart>` API
4. `image-slot.js`
5. Screen mocks, one section each (5.1 … 5.16)
6. `_ds/…/assets` and `assets/ozee-logo-full.png`
7. `uploads/OZee CRM Inbox (offline).html`, `.thumbnail`, and the other uploads (SQL dump, graphml)
8. Consolidated navigation model (internal app + client portal)
9. Component inventory: used vs already ported vs still to port
10. Entities / fields the schema must have
11. Open questions & ambiguities

---

## 0. Export layout & file inventory

```
CRM Restructure/
├── Design Guide.dc.html            123 KB  build reference (15 sections)
├── Home A - Today.dc.html           12 KB  internal home, variant A
├── Home B - At a glance.dc.html     14 KB  internal home, variant B
├── OZee CRM Inbox.dc.html          132 KB  internal inbox (light/dark), many modals
├── OZee Admin Console.dc.html      488 KB  internal admin (many tabs)
├── Client Board.dc.html            116 KB  internal client board table
├── Client OS Login.dc.html          24 KB  client portal
├── Client OS Home.dc.html           20 KB
├── Client OS Announcement.dc.html   16 KB
├── Client OS Product Plan.dc.html   32 KB
├── Client OS SEO Reports.dc.html    60 KB
├── Client OS Signature Builder.dc.html 28 KB
├── Client OS Signature Studio.dc.html  60 KB
├── Client OS Signature Suite.dc.html  152 KB
├── SignatureBlock.dc.html           16 KB  fragment (email signature HTML)
├── Canvas.dc.html                    206 B  EMPTY (just <x-dc></x-dc>)
├── ozee-charts.js                   24 KB  <ozee-chart> custom element (Chart.js wrapper)
├── image-slot.js                    64 KB  <image-slot> drag-drop image placeholder (Claude Design starter)
├── support.js                       68 KB  Claude Design runtime (template language) — not product code
├── assets/ozee-logo-full.png        1200×1200 RGBA, identical (md5) to uploads/OZEE Logo-2.png
├── _ds/vibe-monday-3d3bcd2f-2617-45d4-830a-b39427322625/
│   ├── readme.md, _ds_manifest.json, _ds_bundle.js, styles.css, _adherence.oxlintrc.json
│   ├── tokens/{base,brand,colors,fonts,motion,radius,semantic,shadows,spacing,typography}.css
│   └── assets/ ozee-logo.png (1003×524), ozee-logo-sm.png (534×351),
│               social-{facebook,instagram,linkedin,x}.svg, icons/*.svg (76 files — see §6)
├── uploads/
│   ├── OZEE Design Guide.pdf        1 page brand sheet
│   ├── OZEE Logo-2.png              = assets/ozee-logo-full.png
│   ├── brand-guide-p1.png           1216×1150 — renders BLANK WHITE (broken raster of the PDF)
│   ├── OZee CRM Inbox (offline).html  1.7 MB rendered snapshot of inbox mock
│   ├── ozee-crm-admin-c9_30_tables_20260901_000000.sql  1.4 MB dump of the EXISTING CRM DB (schema reference)
│   └── ozee-crm@localhost.graphml, ozee-crm@localhost-b06e5027.graphml  (536 KB each, identical size) — DB ER diagrams
└── .thumbnail                        640×391 WebP screenshot of Design Guide page
```

Template language (all `.dc.html`): `<x-import component-from-global-scope="OZeeCRMDesignSystem_3d3bcd.X" prop="…" hint-size="w,h">` mounts a design-system React component; `<sc-for list="{{ arr }}" as="x">` loops; `<sc-if value="{{ bool }}">` branches; `{{ }}` interpolates; `style-hover=` / `style-active=` are inline hover/press styles; `data-screen-label` names a screen-level section; `<script data-dc-script data-props=…>` holds a `class Component extends DCLogic { renderVals() {...} }` returning fixture data and props (the "Tweaks" panel props come from `data-props`).

Design-system namespace: `OZeeCRMDesignSystem_3d3bcd` (Vibe/monday.com foundation, OZee brand). 55 components exported (list in §9).

---

## 1. `Design Guide.dc.html` — the build reference

### 1.0 The guide's own shell (a layout recipe in itself)

The guide page is itself built on the app shell and doubles as the reference "docs page" layout:

- Root: `height:100vh; display:flex; flex-direction:column; font-family:Figtree; background:#f6f7fb; color:#323338; -webkit-font-smoothing:antialiased`.
- **Header 56px**: white, `border-bottom:1px solid #d0d4e4`, `padding:0 16px`, `gap:14px`. Contents: `ozee-logo-sm.png` at `height:26px`; a 1×22px divider `#e6e9ef`; title "Design guide" (Figtree 600 14/20); a pill tag "Build reference" (Figtree 400 12/16, `#676879`, `border:1px solid #d0d4e4`, radius 4, padding 1px 6px); right side: "Vibe foundation · OZee brand" 12px grey + `Avatar text="Zeeshan Sabri" size="medium"` (32px).
- **Icon rail 64px**: white, `border-inline-end:1px solid #d0d4e4`, `padding-top:8px`, `gap:2px`, items 48×48, radius 4, 20px `Icon`, `transition:background 100ms cubic-bezier(.4,0,.2,1)`. Active item: `bg:#d3e4fd fg:#1a73e8`; resting `bg:transparent fg:#676879`. Rail items in the guide: Home "My work", Board "Projects", CheckList "Tasks", Email "Approvals", Chart "Reports", Doc "Design guide" (active), Team "Team", Settings "Admin".
- **Secondary sidebar 240px**: white, `padding:20px 12px 40px`, heading "CONTENTS" (eyebrow style), TOC links 34px tall, radius 4, hover `rgba(103,104,121,0.1)`, number column 20px wide (600 12/16 grey). Below the TOC a "Golden rule" wash card (`#f0f6ff`, radius 4, padding 12): *"If the design system has a component for it, use the component. Only hand-build when nothing fits — then add it to section 11 so the next person reuses it."*
- **Main**: `flex:1; min-width:0; overflow:auto`; inner column `max-width:1000px; padding:32px 32px 96px; gap:56px` between sections.
- Anchor styling: `a{color:var(--ozee-blue)} a:hover{color:var(--ozee-blue-deep);text-decoration:underline}`.
- Tweaks props: `showCode` (bool, default true), `showInventoryStatus` (bool, default true), `inventoryFilter` enum `All | Internal CRM | Client OS`.

Section header recipe used throughout: eyebrow (`600 12/16 Figtree, letter-spacing 0.4px, uppercase, #676879`) → h1/h2 (Poppins) with `margin:6px 0 0` → grey sub-line (`400 14/20 Figtree #676879, max-width:720px, text-wrap:pretty`).

### 1.1 Contents (left nav of the guide)

| # | Label | anchor |
|---|---|---|
| 01 | Start here | #start |
| 02 | Screen inventory | #inventory |
| 03 | Colour | #colour |
| 04 | Type | #type |
| 05 | Spacing & geometry | #geometry |
| 06 | Dark mode | #dark |
| 07 | Motion & animation | #motion |
| 08 | Iconography | #icons |
| 09 | Component picker | #picker |
| 10 | Live components | #live |
| 11 | Patterns | #patterns |
| 12 | Charts | #charts |
| 13 | Copy rules | #copy |
| 14 | Build conventions | #build |
| 15 | Master records | #records |

### 1.2 Section 01 · Start here

h1 "OZee CRM design guide". Lead: *"One reference for everything we have built so far: which screens exist, which design-system component to reach for, and the layout patterns to copy rather than reinvent. Foundations come from the Vibe design system; identity comes from OZee."*

Three start cards (3-col grid, white card, 1px `#d0d4e4`, radius 8, padding 16, icon disc 32px `#f0f6ff`/`#1a73e8`):
1. **Building a new page** (Board) — "Duplicate the nearest screen in section 02, keep the shell, swap the content. Never start from blank."
2. **Need an element** (Search) — "Check the picker in section 09 first, then the patterns in section 11. Hand-build only as a last resort."
3. **Changing a foundation** (Bolt) — "Colours, type steps and bar heights live in the design-system tokens — change them there, not on the page."

### 1.3 Section 02 · Screen inventory

Lead: *"Two audiences, two content treatments on one shell. Internal CRM screens fill the width with dense tables. Client OS screens keep the same chrome but centre a reading column, use larger type and drop the jargon."*

Table (grid `2fr 1fr 1.4fr 110px`, header row 40px `#f6f7fb`, rows min 48px, hover `#f0f6ff`):

| Screen | Purpose | Audience | Shell & layout | State |
|---|---|---|---|---|
| OZee Admin Console | Workspace, tiers and automation admin | Internal | Rail + full-width tables | Reference |
| Client Board | All clients, status and owner | Internal | Rail + grouped board table | Reference |
| OZee CRM Inbox | Client mail, triage and reply — light or dark | Internal | Rail + list / reading pane | Reference |
| Home A — Today | My work, ordered by what needs you | Internal | Rail + two-column cards | Option |
| Home B — At a glance | My work, stat-led overview | Internal | Rail + stat grid | Option |
| SignatureBlock | Email signature block, reusable | Internal | Fragment, no shell | Component |
| Client OS Login | Client sign-in with brand panel | Client OS | Split brand panel + form | Reference |
| Client OS Home | What happened and what's next | Client OS | Rail + 780px centred column | Reference |
| Client OS Product Plan | What we're building, module by module | Client OS | Rail + 1000px column | Reference |
| Client OS SEO Reports | Monthly report list and detail read | Client OS | Rail + list / detail switch | Reference |
| Client OS Announcement | Notice board post for clients | Client OS | Centred reading column | Reference |
| Client OS Signature Suite | Signature product surface | Client OS | Rail + preview panel | Exploration |
| Client OS Signature Studio | Signature editing surface | Client OS | Rail + editor and live preview | Exploration |
| Client OS Signature Builder | Step-by-step signature build | Client OS | Rail + stepped form | Exploration |

State tag colours: Reference `#e5f6ed/#00854d`; Option `#fff8e6/#8a5b00`; Component `#f0f6ff/#1a73e8`; Exploration `#f3ebff/#7c3fbf`.

Footnote: *"New page? Duplicate the closest screen in this list rather than starting from an empty file — the shell, spacing and header are already correct."*

**Planner note:** Home A vs Home B are explicitly "Option" (choose one); the three Signature screens are "Exploration" (not final).

### 1.4 Section 03 · Colour

Lead: *"One working blue for every interactive state. Two greys for text — there is no third. Board colours are for status only, never text or chrome. Brand blue, amber and green are identity-only."*

| Group | Rule | Hex | Token | Use |
|---|---|---|---|---|
| Working blue | Every interactive state. One blue, four jobs. | `#1a73e8` | `--primary-color` | Buttons, active nav, links |
| | | `#1560c4` | `--primary-hover-color` | Hover — one step darker |
| | | `#d3e4fd` | `--primary-selected-color` | Selected row, active rail item |
| | | `#f0f6ff` | (faintest highlight, no token) | Insight wash, icon disc |
| Text & chrome | Two text greys only. There is no third. | `#323338` | `--primary-text-color` | All primary text |
| | | `#676879` | `--secondary-text-color` | Sub-lines, meta, icons |
| | | `#f6f7fb` | (app background) | Page behind white cards |
| | | `#d0d4e4` | `--layout-border-color` | Card and layout hairline |
| | | `#c3c6d4` | `--ui-border-color` | Control borders |
| Semantic | Meaning only — success, failure, caution. | `#00854d` | `--positive-color` | Done, on-time, up |
| | | `#d83a52` | `--negative-color` | Errors, destructive, down |
| | | `#ffcb00` | `--warning-color` | Caution, at risk |
| Board palette | Status cells, labels, groups, timeline bars. Never text, never chrome. | `#00c875` | `--color-done-green` | Done |
| | | `#fdab3d` | `--color-working-orange` | Working on it |
| | | `#df2f4a` | `--color-stuck-red` | Stuck |
| | | `#c4c4c4` | `--color-explosive` | To Do |
| | | `#784bd1` | `--color-dark-purple` | Group / label colour |
| OZee identity | Login panel, logo lockups, signatures. Not UI chrome. | `#0e2ba4` | `--ozee-blue` | Brand blue |
| | | `#f8a100` | `--ozee-amber` | Brand amber, accent strip |
| | | `#00853b` | `--ozee-green` | Brand green |

Additional literals used everywhere in mocks (not tabled but consistent): row divider hairline `#f0f1f5`; header divider `#e6e9ef`; wash colours `#f0f6ff` (blue), `#e5f6ed` (green), `#fff8e6` (amber), `#f3ebff` (purple); dark amber text `#8a5b00`; purple text `#7c3fbf` / `#a25ddc`.

`tokens/brand.css` also carries: `--ozee-blue-deep:#0a2080`, `--ozee-amber-hover:#d98c00`, legacy `--ozee-app-primary:#1a73e8`, `--ozee-app-secondary:#fbbc05`, `--ozee-text-primary:#1a202c`, `--ozee-text-secondary:#4a5568`, `--ozee-border:#e5e7eb`, `--ozee-page-background:#f9fafb`, `--brand-selected-color:#ccd5f3`, `--brand-selected-hover-color:#b6c2ed`.

### 1.5 Section 04 · Type

Lead: *"Poppins for headings — three steps, nothing between. Figtree for everything else. Weights 400 / 600 / 700 only. Never more than two sizes in one table row."*

| Role | Spec |
|---|---|
| h1 Page title | Poppins 600 · 32/40 · letter-spacing −0.5px |
| h2 Section heading | Poppins 600 · 24/30 · −0.1px |
| h3 Card heading | Poppins 600 · 18/24 · −0.1px |
| text1 Lead / client-facing body | Figtree 400 · 16/22 |
| text2 UI default (labels, cells, menu rows) | Figtree 400/600 · 14/20 |
| text3 Sub-line, meta, timestamp | Figtree 400 · 12/16 · `#676879` |
| Eyebrow / stat label ("our addition") | Figtree 600 · 12/16 · +0.4px · uppercase · `#676879` |

Also used in practice: 13/18 Figtree for card body/helper text; 11/14 for chart/heatmap micro labels; stat numbers Poppins 700 32/40 −0.5px; code `Consolas, monospace 12/18`.

Fonts are loaded from Google Fonts by `tokens/fonts.css` (Figtree + Poppins). The app's existing Laravel build loads Figtree from fonts.bunny.net.

### 1.6 Section 05 · Spacing & geometry

Lead: *"8-based scale with 2, 4, 12 and 20 available. Shell geometry is fixed — do not invent new bar, rail or row heights."*

**Fixed geometry**

| Value | What |
|---|---|
| 56px | Top bar — fixed, white, 1px bottom hairline |
| 64px | Icon nav rail — 48px items, 20px glyphs |
| 240px | Secondary sidebar (64px collapsed) |
| 24px | Screen padding; 16px card padding |
| 16–24px | Gap between cards |
| 40px | Table row (36px dense) |
| 32/40/48px | Control heights — nothing between |
| 780px | Client OS reading column max-width |

**Radius & separation**

| px | Where |
|---|---|
| 2px | checkbox, small label |
| 4px | buttons, fields, chips, status cells, menu rows |
| 8px | cards, medium tooltips |
| 16px | modals only |

*"Inside the page: 1px hairline plus a background step. Only things that float get shadow. Never a border and a medium shadow on the same surface."*

**States — every interactive element**: Resting `#1a73e8`; Hover `#1560c4`; Press `transform:scale(0.95)`; Focus ring `box-shadow:0 0 0 3px rgba(26,115,232,0.5)`; Disabled `bg:#e6e9ef color:rgba(0,0,0,0.4)`. *"The 0.95 press squeeze is the system signature — buttons, chips and icon buttons alike. Never a colour-only press."*

### 1.7 Section 06 · Dark mode

Lead: *"Dark mode is a token swap, nothing more — same layout, same components, same spacing. It ships on the Inbox as a three-way choice: follow the operating system, force light, force dark. Source of truth for the values below is the Inbox page."*

How it is wired: `data-theme` on `:root` = `system | light | dark`. Two rule blocks — one for forced dark, one inside `prefers-color-scheme` for system. Nothing else changes. *"A page can only theme if every colour it paints comes from a token. One hard-coded hex is one bug in the dark."*

Rules:
- ✅ Paint from tokens — every colour is a `var(--*)`.
- ✅ Status colour is meaning — board palette identical in both themes.
- ✅ Separation still by hairline — border lightens, surface does not glow.
- ⚠ Brandmarks need help — logo gets `filter:brightness(1.3)` in dark (`img[data-brandmark]`).
- ❌ No dark-only design.

Token pairs:

| Token | Light | Dark | Job |
|---|---|---|---|
| `--primary-color` | #1a73e8 | #4a90f0 | Interactive blue |
| `--primary-text-color` | #323338 | #e6e9ef | Primary text |
| `--secondary-text-color` | #676879 | #c5c7d0 | Sub-lines, meta |
| `--primary-background-color` | #ffffff | #30324e | Cards, bars, panels |
| `--grey-background-color` | #f6f7fb | #181b34 | App background |
| `--allgrey-background-color` | #f6f7fb | #292b45 | Sunken areas |
| `--layout-border-color` | #d0d4e4 | #454962 | Card hairline |
| `--ui-border-color` | #c3c6d4 | #5c6081 | Control border |
| `--primary-selected-color` | #d3e4fd | #2b3f6e | Selected row |
| `--positive-color` | #00854d | #00c875 | Success text |
| `--negative-color` | #d83a52 | #ff6b81 | Error, destructive |
| `--backdrop-color` | rgba(41,47,76,0.7) | rgba(12,14,28,0.72) | Modal backdrop |

Code recipe:
```css
:root[data-theme="dark"] { color-scheme: dark; --primary-color:#4a90f0; --primary-text-color:#e6e9ef;
  --primary-background-color:#30324e; --grey-background-color:#181b34; --layout-border-color:#454962; }
@media (prefers-color-scheme: dark) { :root[data-theme="system"] { /* same block */ } }
:root[data-theme="dark"] img[data-brandmark] { filter: brightness(1.3) }
```
Dark example card: primary button in dark is `bg:#4a90f0 color:#181b34`; Done label `#00c875` with `color:#181b34`.

### 1.8 Section 07 · Motion & animation

Lead: *"Two speeds, four easings, four named entrances — that is the whole vocabulary."*

| Token | ms | Use |
|---|---|---|
| `--motion-productive-short` | 70ms | Row and cell hover, icon colour change |
| `--motion-productive-medium` | 100ms | Button fills, link colour, toggle travel |
| `--motion-productive-long` | 150ms | Modal pop, menu open, section expand |
| `--motion-expressive-short` | 250ms | Progress fill, counter pop, chip in |
| `--motion-expressive-long` | 400ms | Sidebar collapse, reading-pane swap |

Easings: Transition `cubic-bezier(.4,0,.2,1)` (default); Enter `cubic-bezier(0,0,.35,1)`; Exit `cubic-bezier(.4,0,1,1)`; Emphasize `cubic-bezier(0,0,.2,1.4)` (only overshoot allowed).

Never: spring/bounce beyond the overshoot; looping ambient animation; parallax/scroll-tied; animating table width/height (animate opacity/transform); anything over 400ms ("needs a loading state, not a longer animation").

Named entrances (declare once per page): `dcRise` fade up 10px (cards/list items entering a column); `dcFade` opacity only (content swapped in a pane); `dcSlideIn` slide 10px from inline end (reading pane, detail drawers); `dcPulse` two-step red halo, once (item that just breached its reply rule).

```css
@keyframes dcRise { from { opacity:0; transform:translateY(10px) } to { opacity:1; transform:none } }
animation: dcRise 250ms cubic-bezier(0,0,.35,1) both;
transition: background 70ms cubic-bezier(.4,0,.2,1);
style-hover="background:#1560c4"  style-active="transform:scale(0.95)"
```

### 1.9 Section 08 · Iconography

Lead: *"One set: 90 filled, single-weight glyphs drawn for 20px, referenced by file stem through the Icon component. No second icon library, no icon font, no emoji standing in for a glyph, no unicode arrows."* (Note: the export's `assets/icons` actually contains **76** SVGs — see §6.)

Icons in use across screens (iconGrid): Home, Board, CheckList, Email, Chart, Bolt, Team, Settings, Inbox, Search, Filter, Sort, Add, Edit, Delete, Reply, Send, Archive, Attach, Download, Upload, Note, File, Doc, Robot, Wand, Security, Person, Globe, Update, Notifications, MoreActions, Check, Close, Warning, Alert.

Sizes: 12 inline meta; 14 chips/breadcrumbs/rule lists; 16 buttons/menu rows/table cells; 20 nav rail/top bar/icon buttons; 24 empty-state discs/large actions. Colour: grey resting, dark emphasis, blue active nav, red destructive; masked so takes `currentColor`. Never recolour with board palette or scale past 24px.

**Nav mapping — fixed**: Home→My work, Board→Projects, CheckList→Tasks, Email→Approvals, Inbox→Inbox, Chart→Reports, Bolt→Automations, Team→Team, Settings→Admin.

Warning: referencing an icon name not in `assets/icons` renders a solid grey square.

Recipe: `<script>window.OZEE_ICON_BASE="_ds/<system>/assets/icons"</script>` once; `<Icon name="Search" size={20} color="currentColor">`.

### 1.10 Section 09 · Component picker

*"Deliberately not built: theme and layer providers, virtualised list and grid helpers, colour utils. Everything else in the library exists."*

| Group | Need | Component | Note |
|---|---|---|---|
| Actions | Main action on a page or modal | Button | One primary per view; secondary for the rest, tertiary for cancel. |
| | Icon-only action in a bar or row | IconButton | Always pass ariaLabel. 32px in rows, 40px in the top bar. |
| | Action with variants behind it | SplitButton | e.g. Approve & send / Approve only. |
| | Two or three exclusive modes | ButtonGroup | Use instead of hand-rolled pill tabs. |
| | Navigation inside a sentence | Link | Never a Button styled as text. |
| Text | Any heading | Heading | h1/h2/h3 only. |
| | Body, labels, meta | Text | text1/text2/text3; secondary colour for sub-lines. |
| | Rename in place | EditableText, EditableHeading | Project and task names — not form fields. |
| | Money, counts, percentages | FormattedNumber | AU formatting and abbreviation. |
| Input | Single-line entry | TextField | Full width in its column; title above, one line of help below. |
| | Longer note or email body | TextArea | Standup notes, kudos, client emails. |
| | Filter a table or list | Search | In the page header row, not in the top bar. |
| | Pick one from a known set | Dropdown | Combobox when long/searchable. |
| | Yes/no setting | Toggle | Checkbox for multi-select in lists; Toggle for settings. |
| | A date or range | DatePicker | Due dates, availability, report period. |
| Data | Rows of records | Table | 40px rows, one line per cell, 3px group rule at inline-start. |
| | Status or category cell | Label | Board colour fills the cell. Four status words only. |
| | Removable filter or tag | Chips | Applied filters, skills, tiers. |
| | Unread or pending count | Counter | Pill, beside a nav or tab label. |
| | A person | Avatar, AvatarGroup | Circle. Group caps at four, then +n. |
| | Progress toward a milestone | ProgressBar | Expressive 400ms fill. |
| Feedback | Explain a blocker or condition | AttentionBox | Two sentences max. |
| | Result of the user's action | Toast | Save confirmations, sends. Auto-dismiss. |
| | System-wide notice | AlertBanner | Outage, migration, billing. Top of content, not the bar. |
| | Nothing to show | EmptyState | Icon disc, the fact, the next step. |
| | Loading | Skeleton, Loader | Skeleton for known layout, Loader for unknown wait. |
| Navigation & overlays | Switch views inside a page | Tabs | Reports, project detail sections. |
| | Where am I | BreadcrumbsBar | Client → project → task. |
| | Row or card overflow actions | Menu, MenuButton | MoreActions glyph, medium shadow. |
| | Multi-step process | Steps, MultiStepIndicator | Onboarding, signature builder. |
| | Focused task over the page | Modal | 16px radius, large shadow, navy 70% backdrop. |
| | Optional detail | Accordion, ExpandCollapse | Keeps dense pages calm. |

### 1.11 Section 10 · Live components

Rendered examples with the exact props used:
- Buttons: `Button` (primary default) "Save changes" 130×40; `kind="secondary"` "Send back"; `kind="tertiary"` "Cancel"; `color="negative"` "Delete task"; `size="small"` "Create task" (110×32); `disabled` "Approve & send".
- `IconButton name="Search|Filter|MoreActions" ariaLabel=…` 32×32; `Avatar text="Zeeshan Sabri" size="medium"` 32px; `Counter count={12}` and `Counter count={3} color="negative"`.
- **Status vocabulary — the only four words**: Done `#00c875`, Working on it `#fdab3d`, Stuck `#df2f4a`, To Do `#c4c4c4` — 28px tall pill, radius 4, white 600 13/18 text, padding 0 14px. *"Capitalised exactly like this. Board colour fills the whole status cell — it never becomes text colour or a card border."*
- Fields: `TextField title="Project name" placeholder="Harper & Vale website"`; `Search placeholder="Search tasks"`.
- Feedback: `AttentionBox title="Waiting on the client"` body "Waiting on the client's ABN documents before the payment gateway can be approved."; `AttentionBox type="danger" title="This client already exists"` body "Open the existing record instead of creating a duplicate."

### 1.12 Section 11 · Patterns (layout recipes — copy verbatim)

**App shell** — "Every screen. 56px bar, 64px rail, content is the only thing that scrolls. Internal pages fill the width; Client OS pages centre a 780px column."
```html
<div style="height:100vh;display:flex;flex-direction:column;font-family:Figtree,sans-serif;background:#f6f7fb;color:#323338">
  <header style="height:56px;flex:none;display:flex;align-items:center;gap:14px;padding:0 16px;background:#fff;border-bottom:1px solid #d0d4e4"> … </header>
  <div style="flex:1;display:flex;min-height:0">
    <nav style="width:64px;flex:none;background:#fff;border-inline-end:1px solid #d0d4e4"> … </nav>
    <main style="flex:1;min-width:0;overflow:auto;padding:24px"> … </main>
  </div>
</div>
```

**Page header** — "Eyebrow, h1, one grey sub-line, actions on the right. Secondary action first, primary last."
```html
<div style="display:flex;align-items:flex-end;gap:20px;flex-wrap:wrap">
  <div style="flex:1;min-width:260px">
    <div class=eyebrow>Harper &amp; Vale</div>
    <h1 style="margin:4px 0 0;font:600 32px/40px Poppins">Website rebuild</h1>
    <div style="margin-top:4px;font:400 14px/20px Figtree;color:#676879">6 tasks open · next milestone Monday 17 August</div>
  </div>
  <div style="display:flex;gap:8px"> Button secondary small "Export plan" · Button small "Create task" </div>
</div>
```

**Stat tile** — "Uppercase label, number leads, one grey line of context. Delta is green up, red down — the card itself stays white." Grid `repeat(auto-fit,minmax(190px,1fr)) gap 16`. Card: white, 1px `#d0d4e4`, radius 8, padding 16; label eyebrow; value `700 32/40 Poppins −0.5px` + delta `600 13/18` (`#00854d` / `#d83a52` / `#676879`); note `400 12/16 #676879`. Examples: Active projects 6 (+1) "2 launching this month"; On-time delivery 92% (+4%) "Rolling 90 days"; Awaiting approval 3 "Oldest 2 days"; Billed this month $148,500 (−6%) "Against $158k last month".

**Accent strip & insight wash** — "The one place a colour bar is allowed: a 4px inline-start rule on a hero card. Explanations sit in a tinted wash block — never in a coloured card."
- Hero card: white card, `display:flex; overflow:hidden`, `<span style="width:4px;background:#f8a100">` then content `padding:18px 20px` with eyebrow "Needs you today" + `700 32/40 Poppins` "3 items" + `Button small` "Open queue".
- Insight wash: `display:flex;gap:10px;padding:10px 12px;background:#f0f6ff;border-radius:4px` — 16px icon + one sentence (e.g. Globe icon, *"Average Maps Pack position **2.4** — you appear in the three-result map block for 14 of your tracked searches."*).
- Wash vocabulary: `#f0f6ff` blue = neutral insight; `#e5f6ed` green = went well; `#fff8e6` amber = watch this; `#f3ebff` purple = explanation.

**List row with icon disc** — "Our standard row: icon disc, one line of title, one grey sub-line, tag, chevron. 4px square disc for objects, circle for people and events." Container white card `padding:4px 12px`; row `display:flex;gap:12px;padding:10px 4px;border-bottom:1px solid #f0f1f5;cursor:pointer` hover `#f0f6ff`; disc 32×32 radius 4 wash/tone; title `600 14/20`; sub `400 12/16 #676879`; tag pill `600 12/16 padding 2px 8px radius 4`; chevron = `NavigationChevronLeft` rotated 180° 16px grey. Examples: "August SEO report / Sent 2 Sep · Harper & Vale / New(green)"; "Launch email for approval / Submitted by Mia Lu · 2 days ago / Waiting(amber)"; "Homepage content signed off / Yesterday · milestone 2 of 5 / Done(green)".

**Empty state** — "Icon disc, the fact, the next step. No illustration — we have no illustration system." White card padding 32, centred, gap 10; disc 56px circle `#e5f6ed/#00854d` with `Completed` icon 24; title Poppins 600 18/24 "You're all clear"; body max-width 340 `400 14/20 #676879` "No tasks due today — nothing needs your attention right now."; `Button secondary small` "View this week".

### 1.13 Section 12 · Charts (rules; API in §3)

Lead: *"Charts come from one wrapper — `<ozee-chart>` in `ozee-charts.js`. It wraps Chart.js and reads its colours, type and grid straight from the tokens … Nine chart types cover every report we have planned. Never drop a raw Chart.js canvas or a second charting library into a page."*

Series colour — locked order: 1 `--color-bright-blue #579bfc`, 2 `--color-done-green #00c875`, 3 `--color-working-orange #fdab3d`, 4 `--color-dark-purple #784bd1`, 5 `--color-aquamarine #4eccc6`, 6 `--color-lipstick #ff5ac4`. One series → working blue `--primary-color`, not palette 1. Previous period → grey `--ui-border-color` dashed. Seven or more series → "the chart is wrong" — group into "Other".

Rules: zero baseline on bars, always; title in the card header not in the chart; horizontal gridlines only (1px hairline, no vertical grid, no axis border, no tick marks); units on the number ($, %, h) in value and tooltip; no chart junk (3D, shadows, gradients, labels on every point, decorated pies); no red/green for categories; empty is a state (EmptyState in card body, not an empty axis).

Per-type guidance:
- **Trend over time (line)**: default report chart; one metric, filled; compare arrives as grey dashed; date range sits above the chart as a `ButtonGroup` (options "30 days" / "6 months" / "12 months", `size="small"`, 210×32), never inside. Headline row above chart: `600 24/30 Poppins` "18,420" + `600 13/18 #00854d` "+24% on last year" + grey "Organic sessions · Bright Dental". Height 260.
- **Bar**: discrete periods, zero baseline, 2px radius, horizontal gridlines only. Height 220.
- **Stacked bar**: only when total matters as much as parts; 3–4 segments, never eight.
- **Donut**: five slices max, largest first, legend below, percentages in tooltip. Height 240.
- **Gauge**: semi-circle, colour by threshold, one per card. 200×150.
- **Sparkline in a row**: shape only, no axes/labels; 32px tall in a table cell (grid `1.4fr 100px 120px 1fr`: Client / Sessions / Change / Last 12 months).
- **Progress against goal**: pill track, blue fill, value and target on one line; "use instead of a bar chart with one bar".
- **Funnel**: bars not trapezoids; drop spelled out in words underneath.
- **Heatmap**: one hue, opacity carries value; per person per day (availability, standup notes, publishing cadence).

Internal vs Client OS sizing:

| | Internal | Client OS |
|---|---|---|
| Chart height | 200–260px | 280–340px |
| Tick / legend type | 12px | 14px |
| Series per chart | Up to 6 | 1–3 |
| Charts per screen | Up to 6 | 2–3, one per idea |
| Reading order | Grid of cards | One column, headline number first |

Set `"big": true` in spec for client-facing charts.

Interaction the wrapper gives: hover tooltip (exact value w/ unit, dark surface, index mode); legend toggles series (bottom-left, circular keys, only when ≥2 series); date range = ButtonGroup owned by page; compare = second series `color:"compare"`; export PNG via `el.download("sessions")` (canvas types only).

### 1.14 Section 13 · Copy rules

*"Plain, practical Australian-English work language. The app talks about the job, not about itself."*

| Rule | Write this | Not this |
|---|---|---|
| Sentence case everywhere | Create task | Create Task |
| Verb-first, specific buttons | Approve & send | Submit |
| Second person for the user's own things | 3 items need you today | We found 3 items for you |
| Third person for other people's | Mia Lu submitted an email for approval | An email was submitted |
| Errors name the cause and the fix | This client already exists | Something went wrong |
| Empty states: the fact, then the next step | No tasks due today — nothing needs your attention right now. | Nothing here! |
| AU dates, day-month, no ordinals | 22 Aug · Monday 17 August | August 22nd |
| Numbers lead the line | 6 active projects | There are six active projects |
| Emoji only where a human typed them | Site is live 🎉 — inside a client email | 🚀 Projects |

From `_ds/readme.md` (same voice rules, plus): never "we"; product vocabulary to use verbatim — *project, task, subtask, milestone, deliverable, client, standup note, kudos, approval, availability, notice board, resource, tier, workspace, automation, schedule*; dates "Today / Tomorrow / Yesterday / Overdue / 22 Aug / Monday 17 August"; numbers abbreviated over a thousand ($148,500, $2.4M); table cell text one line; help text one sentence.

### 1.15 Section 14 · Build conventions

Do:
- Load the full token set, styles.css and `_ds_bundle.js` in every page.
- Set `window.OZEE_ICON_BASE` once per page.
- Mount components from the `OZeeCRMDesignSystem_3d3bcd` namespace and always give a size hint.
- Style inline against the token values; repeat the literals rather than inventing classes. (**For the React rewrite: translate to tokens/CSS vars; the "inline literals" rule is a mock-tooling constraint, not a product rule.**)
- Keep one line of text per table cell; push detail to a 12px grey sub-line.
- Label every screen-level section.

Don't:
- No gradients, photography, illustration, texture or backdrop blur.
- No third grey, no second blue, no board colour used as text or chrome.
- No coloured left border on a card — the 4px accent strip on a hero card is the only exception.
- No Font Awesome, Heroicons, icon fonts, emoji-as-icon or unicode arrows.
- No new bar, rail or row heights; no control height between 32, 40 and 48.
- No border plus medium shadow on the same surface.

Setup order: tokens (fonts, colors, brand, typography, spacing, radius, shadows, motion, semantic, base) → styles.css → `OZEE_ICON_BASE` → bundle → reset (`html,body{margin:0;height:100%} #root{height:100%} a{color:var(--ozee-blue)}`). Interaction: hover `transition: background 100ms cubic-bezier(.4,0,.2,1)`, press `scale(0.95)`, focus 3px blue ring at 50% opacity keyboard-only.

### 1.16 Section 15 · Master records

*"This guide is a reading of the system, not the system itself. When the two disagree, the files below win."*

| Path | What |
|---|---|
| `_ds/vibe-monday-…/tokens/` | source of every value |
| `_ds/vibe-monday-…/styles.css` | single stylesheet entry |
| `_ds/vibe-monday-…/_ds_bundle.js` | all components on the namespace |
| `_ds/vibe-monday-…/assets/icons/` | approved glyphs |
| `_ds/vibe-monday-…/assets/ozee-logo*.png` | brand lockups, never redrawn |
| `templates/crm-screen/` | copyable starting screen (NOT in this export) |
| `ui_kits/crm/index.html` | click-through CRM recreation (NOT in this export) |
| `guidelines/` | 21 specimen cards (NOT in this export) |

*"Changing a foundation — a colour, a type step, a bar height — is a change to the design system, not to a page."*

### 1.17 `_ds/readme.md` extras worth carrying (not in the HTML guide)

- Who OZee are: Perth (Thornlie, WA) web & digital studio, trading as *OZee Web & Digital Services*, tagline *"Your one stop shop"*, email-signature line *"Your website and social media are like your home, first impressions matter!"*, contact `+61 456 639 389`, `OZeeWeb.com.au`; branding config lives in `config/branding.php` in the app repo (`github.com/zeemsabri/ozee-crm`).
- Cards: white, 1px `--layout-border-color`, radius 8, no shadow on grey; `--box-shadow-xs` only to lift on hover; menus/tooltips/toasts `--box-shadow-medium`; modals `--box-shadow-large` + 16px radius. Group colour = 3px inline-start rule on a table group and an 8px square swatch in the sidebar.
- Borders: controls `--ui-border-color` → `--primary-text-color` hover → `--primary-color` focus → semantic when validating. Read-only fields drop the border and fill grey.
- Transparency: hover wash `rgba(103,104,121,0.1)`; modal backdrop `rgba(41,47,76,0.7)`; disabled text 40% black.
- Avatars circles; square avatars use 4px radius. Toggles and counters are full pills.
- Brand marks in the DS: `ozee-logo.png`, `ozee-logo-sm.png`, (`ozee-logo-header.png`, `favicon.svg` mentioned but NOT in this export), social SVGs for email signatures.

---

## 2. `uploads/OZEE Design Guide.pdf` — brand sheet

Single page (1 page only, 338 KB), titled **"OZEE Style Guide"**. Three columns plus a "Backgrounds" strip.

**Typography**
- "1st Font **SirinStencil Regular**" — the display/logo face (the OZEE wordmark and "Header Text" `Aa` sample use it).
- "2nd Font **Bree Regular**" — the secondary face (body sample; "Sub Header Text" / "Body Text" `Aa` samples).
- Samples: "Header Text" (large Aa, SirinStencil), "Sub Header Text" (medium Aa), "Body Text" (small Aa).

**Color Guide** — three brand hues with four tints each (100/80/60/40/20 opacity steps down to very pale):
- `#213AA8` blue
- `#F8A50D` orange/amber
- `#1F9355` green

**Logo Variations** (5): full-colour on white (blue O/E/E, orange Z, green "WEB & DIGITAL SERVICES", dark "YOUR ONE STOP SHOP"); white on `#213AA8` blue block; white on `#F8A50D` orange block; white on `#1F9355` green block; black on white (mono).

**Backgrounds** (approved two-tone pairings): blue+white; blue+orange; orange+white; green+orange.

`uploads/brand-guide-p1.png` (1216×1150) is a **blank white** image — a failed raster of the PDF; ignore it.

**Conflicts with the HTML guide / DS tokens:**

| Item | PDF brand sheet | HTML guide / `tokens/brand.css` | Resolution to propose |
|---|---|---|---|
| Brand blue | `#213AA8` | `--ozee-blue: #0e2ba4` (sampled from logo PNG) | Use the DS token `#0e2ba4` for UI identity surfaces (it matches the shipped logo raster); record `#213AA8` as the print/marketing value. Flag to client. |
| Brand amber | `#F8A50D` | `--ozee-amber: #f8a100` | Same approach; near-identical. |
| Brand green | `#1F9355` | `--ozee-green: #00853b` | Same. Visibly different (PDF green is lighter/teal-ish). |
| Fonts | SirinStencil (headings) + Bree (body) | Poppins (headings) + Figtree (body) | The DS explicitly chose Poppins/Figtree; SirinStencil is the logo face only — never render UI text in it. Bree not used anywhere. |
| Tints | Five-step tints of each brand hue | UI uses `#f0f6ff/#e5f6ed/#fff8e6/#f3ebff` washes, not brand tints | Brand tints are marketing-only. |
| Logo on colour blocks | Allowed (white-on-blue/orange/green, mono black) | Only the full-colour lockup is shipped (`ozee-logo*.png`); the login mock puts the lockup in a white panel on the navy brand panel | Request white/mono logo files from client if a dark-background lockup is ever needed (dark mode uses `brightness(1.3)` on the colour lockup instead). |

---

## 3. `ozee-charts.js` — `<ozee-chart>` API (complete)

A vanilla custom element (`customElements.define("ozee-chart", OzeeChart)`, also `window.OzeeChart`, `window.OZEE_CHART_SERIES`). Requires **Chart.js 4.4.1 UMD** loaded globally (`window.Chart`) for canvas types; the DOM types work without it. Loaded via `<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js">` then `<script src="./ozee-charts.js">`. In the mocks it is mounted as `<x-import component-from-global-scope="ozee-chart" from="./ozee-charts.js" type="line" height="260" spec="{{ json }}">`. **Only the Design Guide page uses it** — none of the screen mocks embed a chart (the SEO Reports client screen hand-draws bars in DOM; see §5.12).

### Attributes / properties
- `type` — `line` (default) · `bar` · `stacked-bar` · `donut` · `sparkline` · `progress` · `gauge` · `heatmap` · `funnel`. Observed attribute; changing re-renders.
- `spec` — JSON string attribute, or set `el.spec = obj` (property setter re-renders). All options live inside spec, not as attributes.
- `height` — number (px) or a string with `px|%|rem`; sets `style.height` on the element (`display:block`). Width is the container's.
- Methods: `el.rerender()`, `el.toPng()` → base64 PNG or null, `el.download(name)` → triggers `<name>.png` download. Canvas types only (progress/gauge/heatmap/funnel are DOM and return null).

### Common spec keys
- `labels: string[]` — x-axis / slice labels (line, bar, stacked-bar, donut, sparkline).
- `series: [{ name, data:number[], color? }]` — `color` may be `"accent"` (→ `--primary-color`), `"compare"` (→ `--ui-border-color`, dashed on line, excluded from the metric count), `"positive"`, `"negative"`, any CSS colour, or omitted (→ palette by index). If exactly one non-compare series and it has no colour → accent blue.
- `unit: "" | "$" | "%" | "h" | <string>` — formatting: `$` prefix; `%`/`h` suffix; other strings appended with a space; `en-AU` locale grouping; axis ticks abbreviate ≥1000 to `k` (1 decimal below 10k).
- `big: boolean` — client-facing sizing: legend font 14 (else 12), tick font 13 (else 12), `maxTicksLimit` 4 (else 5), `autoSkipPadding` 24 (else 12), bar `maxBarThickness` 40 (else 28), donut cutout 64% (else 58%), gauge 200px/stroke 18 (else 168/14) with 32/40 Poppins value (else 24/30), heatmap cell 28 (else 24), funnel track 28 (else 22).
- `fill: false` — line/sparkline: disable area fill (default filled when single metric).

### Per-type spec
| type | spec | rendering |
|---|---|---|
| `line` | `{labels, series[], unit?, big?, fill?}` | Chart.js line, `tension:0.3`, 2px stroke, no points (hover radius 4 with surface-coloured border), single-series area fill at alpha 0.10; compare series `borderDash:[4,4]` no fill; legend bottom/start, circle keys, only when >1 series. |
| `bar` | `{labels, series[], unit?, big?}` | `borderRadius:2, borderSkipped:false`, hover 85% alpha, y `beginAtZero:true`. |
| `stacked-bar` | same | `stack:"s"`, scales `stacked:true`. |
| `donut` | `{labels, series:[{data}], unit?, big?}` | Chart.js doughnut; slice colours = palette by index; border 2px surface colour; `hoverOffset:6`; legend forced on; tooltip shows value and `(nn%)` of total. |
| `sparkline` | `{labels, series[], fill?}` | line with no scales, no legend, padding 2, 250ms animation, fill alpha 0.14, `tension:0.35`. |
| `progress` | `{unit?, items:[{label, value, target?, tone?:"positive"|"negative"}]}` | DOM. Row: label (13/18) · `value / target` (600 13/18) · `pct%` (12/16 grey); 8px pill track (`--allgrey-background-color`), fill accent/positive/negative, `width` animates 400ms enter-easing. `target` defaults 100. |
| `gauge` | `{value(0–100), unit?(default "%"), caption?, goodAbove?(80), warnAbove?(50), big?}` | DOM SVG semi-circle; tone: ≥goodAbove positive, ≥warnAbove `--color-working-orange`, else negative; value text Poppins under arc; caption 13/18 grey. Track `--allgrey-background-color`. |
| `heatmap` | `{rows:string[], columns:string[], cells:[{row,col,value}], unit?, big?}` | DOM grid; cell bg = `--allgrey-background-color` when 0 else accent at alpha `0.12 + ratio*0.88`; 2px radius, 3px gap, hover `scale(1.15)`; `title` tooltip `"<row> <col> — <value unit>"`; key "Less ▪▪▪▪▪ More". |
| `funnel` | `{unit?, steps:[{label,value}], big?}` | DOM; each step: label · value · `% of top`; 22px track radius 4; fill = palette by index (min 2% width); under each step from 2nd: `"<n>% drop from <prev label>"` or `"No drop"`. |

### Theming
Reads computed CSS vars at render time: `--primary-text-color`, `--secondary-text-color`, `--layout-border-color` (grid), `--primary-background-color` (surface), `--allgrey-background-color` (sunken), `--primary-color` (accent), `--ui-border-color` (compare), `--positive-color`, `--negative-color`, `--inverted-color-background` (tooltip bg, default `#323338`), `--text-color-on-inverted` (tooltip text), and the six series tokens. Re-renders on `data-theme` attribute change (MutationObserver on `<html>`) and on `prefers-color-scheme` change. Font `Figtree, sans-serif` throughout.

### Base Chart.js options (for a faithful React port)
`responsive:false, maintainAspectRatio:false, animation:{duration:400, easing:"easeOutQuart"}, interaction:{mode:"index", intersect:false}, layout.padding:{top:4,right:4,bottom:0,left:0}`; legend `position:"bottom", align:"start", usePointStyle circle, boxWidth/Height 10, padding 16`; tooltip `backgroundColor: inverted, padding 10, cornerRadius 4, usePointStyle, boxWidth 8, boxPadding 6, titleFont 600 12, bodyFont 400 13`; x-axis: no grid, border coloured `--layout-border-color`, `maxRotation:0`; y-axis: `beginAtZero:true`, grid 1px `--layout-border-color`, `drawTicks:false`, no border, ticks padding 8, callback → short format with unit.

### Sizing/lifecycle quirks (relevant to a React port)
- It sizes the canvas itself (`responsive:false`) from `getBoundingClientRect()`, rebuilds the Chart on a ≥2px box change, polls every 400ms (shared timer) plus `window.resize`; waits up to 90 frames for a non-zero box.
- Draws synchronously when `document.hidden` (for screenshot/export), and re-renders on `visibilitychange`.
- While `window.Chart` is missing it shows a sunken placeholder box (min-height 80) and retries every 120ms.

### Recommendation for the React app
Port as a `<OzeeChart type spec height>` React component wrapping `react-chartjs-2`/Chart.js 4 with the exact option builders above, plus four pure-React DOM renderers (Progress, Gauge, Heatmap, Funnel). Keep the spec schema identical so report payloads from Laravel can be passed straight through. Use `ResizeObserver` instead of the poll. The "big" flag maps to the Client OS variant.

---

## 4. `image-slot.js` — `<image-slot>`

A Claude Design ("omelette") starter component: a user-fillable image placeholder for mocks. It is **design-tool scaffolding, not product code**. Marked `// @ds-adherence-ignore -- omelette starter scaffold`.

API (custom element `<image-slot>`):
- `id` — persistence key (required for the drop to survive reload; sidecar `.image-slots.state.json` next to the HTML).
- `shape` — `rect | rounded | circle | pill` (default `rounded`); `radius` px for rounded (default 12); `mask` any CSS clip-path (overrides shape).
- `fit` — `cover | contain` (default cover); double-click/Edit control enters reframe mode (drag/scroll/corner handles; Esc commits). Crop persists.
- `placeholder` — empty-state caption (default "Drop an image").
- `src` — initial/fallback URL; `credit` / `credit-href` — attribution overlay (required for Unsplash sources).
- Sizing: fills its container (100%/100%); in indefinite-height flow falls back to full width at 3:2. Read-only outside the omelette runtime.

Used in two mocks: `Client OS Announcement` and `Client OS Signature Suite` (see those sections for what the slot stands for — a hero/attachment image and the signature headshot/logo). **In the product, replace with a real file-upload/image field (Laravel media, S3) and an `<img>`.**

