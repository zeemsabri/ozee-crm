# OZee CRM Design System

The design language for the **OZee Web & Digital** CRM — the internal system OZee's team uses to run client projects, tasks, client emails, availability, kudos and reporting. The programme goal is to upgrade that system one page at a time toward a modern, work-management feel: white chrome, colourful status, dense-but-calm tables, fast micro-interactions.

**Who OZee are:** a Perth (Thornlie, WA) web and digital services studio — websites, SEO, Google Ads, content — trading as *OZee Web & Digital Services*, tagline *"Your one stop shop"*, and in email signatures *"Your website and social media are like your home, first impressions matter!"* Contact details live in `config/branding.php` in the app repo (`+61 456 639 389`, `OZeeWeb.com.au`).

## Sources this system was built from

| Source | URL | What was taken |
| --- | --- | --- |
| monday.com **Vibe** design system (open source) | https://github.com/mondaycom/vibe | The foundation layer, copied value-for-value: colour roles, type scale, spacing, radii, shadows, motion tokens, focus ring, component sizing and states, and the 90-glyph icon set in `assets/icons/`. Read `packages/style/src/` for tokens and `packages/core/src/components/` + `packages/components/*` for component CSS. |
| **OZee CRM** application | https://github.com/zeemsabri/ozee-crm | Brand assets (`public/logo*.png`, social SVGs, favicon), brand colours (`config/branding.php`), fonts in use (Figtree), and the information architecture + screens recreated in `ui_kits/crm/` (`resources/js/Pages/**`, `resources/js/Layouts/AuthenticatedLayout.vue`, `resources/js/Components/LeftSidebar.vue`). |

Both repos are worth exploring further before designing anything substantial: Vibe for exact component behaviour and edge-case states, ozee-crm for the real data model, page inventory and permission rules that shape each screen.

**Relationship between the two:** Vibe supplies the *system* (structure, scale, interaction physics). OZee supplies the *brand* (logo, brand blue/amber/green, voice, product vocabulary). Where they conflict, Vibe wins for UI chrome and OZee wins for identity. monday.com's own brand marks and identity colours are deliberately **not** used anywhere in this system.

---

## Content fundamentals

**Voice:** plain, practical Australian-English work language. The app talks about the job, not about itself.

- **Person:** second person for the user's own things (*"My work"*, *"3 items need you today"*, *"Keep me signed in"*); third person for other people's (*"Mia Lu submitted an email for approval"*). Never "we".
- **Casing:** sentence case everywhere — labels, buttons, table headers, modal titles, menu items. Only proper nouns and status words keep capitals (*Done*, *Working on it*, *Stuck*, *To Do* — these come from the board vocabulary and are always capitalised exactly like that).
- **Buttons** are verb-first and specific: *Save changes*, *Approve & send*, *Send back*, *Create task*, *New project*, *Update*. Never *Submit*, *OK*, or *Click here*.
- **Empty states** state the fact, then the next step: *"No tasks due today — nothing needs your attention right now."*
- **Errors** name the cause and the fix, no apology: *"This client already exists"*, *"Waiting on the client's ABN documents before the payment gateway can be approved."*
- **Dates** are human and short: *Today*, *Tomorrow*, *Yesterday*, *Overdue*, *22 Aug*, *Monday 17 August*. Day-month order (AU), no ordinals.
- **Numbers** lead the line in stats (*6 active projects*, *92% on-time delivery*), abbreviated over a thousand (*$148,500*, *$2.4M*).
- **Length:** table cell text is one line; card sub-lines one line; help text one sentence. If it needs two sentences, it belongs in an AttentionBox.
- **Emoji:** not used in UI chrome. They appear only where a human typed them — inside an email body or a kudos message (the real app has *"Site is live 🎉"* in a client email). Never in labels, nav, buttons or headings.
- **Product vocabulary** (use these words, not synonyms): project, task, subtask, milestone, deliverable, client, standup note, kudos, approval, availability, notice board, resource, tier, workspace, automation, schedule.

## Visual foundations

**Palette.** One working blue (`--primary-color` #1a73e8, from the app's own branding config) carries every interactive state; hover is a darker `#1560c4`, selection is the pale `#d3e4fd` wash, and `#f0f6ff` is the faintest highlight. Text is only ever `#323338` (primary) or `#676879` (secondary) — there is no third grey. Chrome is white on a `#f6f7fb` app background, separated by 1px hairlines (`#d0d4e4` layout, `#c3c6d4` controls). Semantics: green `#00854d`, red `#d83a52`, yellow `#ffcb00`. A 32-colour board palette (`--color-done-green`, `--color-working-orange`, `--color-stuck-red`, …) is reserved for status cells, labels, groups and timeline bars — never for text or chrome. Brand blue `#0e2ba4`, amber `#f8a100` and green `#00853b` are identity-only (login panel, logo lockups, marketing).

**Type.** Two families: **Poppins** for headings (h1 32/40, h2 24/30, h3 18/24 — three steps, nothing between) and **Figtree** for everything else (text1 16/22, text2 14/20 — the UI default, text3 12/16). Weights are 400 / 600 / 700 only; headings carry slightly negative tracking (−0.5px h1, −0.1px h2/h3). Font smoothing is on (antialiased / grayscale).

**Spacing & layout.** An 8-based scale with 2, 4, 12 and 20 available: `--space-2 … --space-80`. Screen padding 24px, card padding 16px, gaps between cards 16–24px. Fixed shell geometry: 56px top bar, 64px icon nav rail, 240px project sidebar (64px collapsed) — the top bar and rail are the only fixed elements; content scrolls under them. Rows are 40px (36px dense), controls come in 32 / 40 / 48px heights, and inputs are full-width inside their column.

**Backgrounds.** Flat colour only. No gradients in UI (the login brand panel is a solid `--ozee-blue`), no photography, no illustration, no patterns or texture. The one large image in the system is the OZee logo. There is no imagery colour-grade to match because there is no imagery — when a screen needs a picture, use a placeholder and ask for a real asset.

**Cards & containers.** White, 1px `--layout-border-color` border, 8px radius, no shadow when sitting on grey; `--box-shadow-xs` only if it needs to lift on hover. Floating surfaces earn shadow instead of border: menus/tooltips/toasts `--box-shadow-medium`, modals `--box-shadow-large` with a 16px radius. There is no inner-shadow system, and no card ever gets a coloured left border — group colour is expressed as a 3px inline start rule on a *table group*, and as an 8px square swatch in the sidebar.

**Radii.** 2px (checkbox, small label), 4px default (buttons, fields, chips, menu rows, status cells), 8px (cards, medium tooltips), 16px (modals), full pill (toggles, counters). Avatars are circles; square avatars use the 4px radius.

**Borders.** Always 1px solid. Controls: `--ui-border-color` resting → `--primary-text-color` on hover → `--primary-color` on focus → semantic colour when validating. Read-only fields drop the border and fill grey instead.

**Elevation vs. separation.** Inside the page, separate with hairlines and background steps (white on `#f6f7fb`). Only things that float above the page get shadow. Nothing uses both a border and a medium/large shadow.

**Transparency & blur.** Used sparingly and never decoratively: hover washes are `rgba(103,104,121,0.1)`, the modal backdrop is navy at 70% (`rgba(41,47,76,0.7)`), disabled text is 40% black. No backdrop blur, no frosted glass, no protection gradients — text always sits on an opaque surface.

**Motion.** Two speeds. *Productive* (70 / 100 / 150ms) for anything the user is waiting on: colour changes, hover, toggle travel, modal pop, expand. *Expressive* (250 / 400ms) for things that draw the eye: progress fills, counter pops, sidebar collapse. Easings: `cubic-bezier(.4,0,.2,1)` default, `(0,0,.35,1)` entering, `(.4,0,1,1)` exiting, `(0,0,.2,1.4)` for a slight overshoot on chips and counters. No bounce beyond that overshoot, no spring, no parallax, no looping ambient animation.

**States.** Hover: fills darken one step (`--primary-hover-color`) or gain the 10% asphalt wash on ghost surfaces. Press: `transform: scale(0.95)` — the system's signature squeeze, on buttons, labels and icon buttons alike; never a colour-only press. Selected: `--primary-selected-color` background with blue text/icon. Focus: a 3px 50%-opacity blue ring plus a 1px inset (`--focus-ring`), visible on keyboard focus only. Disabled: `--disabled-background-color` fill and 40% text, `cursor: not-allowed`, pointer events off.

**Density.** Information-dense but airy at the row level: 40px rows, 8px gaps inside cards, one line of text per cell, secondary detail as a 12px grey sub-line. Never more than two font sizes in a single row.

## Iconography

- **One set:** the Vibe glyph library, copied into `assets/icons/` as **90 raw SVGs** (`Add.svg`, `Search.svg`, `Filter.svg`, `MoreActions.svg`, `Board.svg`, `Doc.svg`, `Bolt.svg`, `Team.svg`, …). They are filled, single-weight, optically drawn for ~20px, with no stroke-weight variants. This is the *only* icon system — no Font Awesome, no Heroicons, no icon font, no emoji-as-icon, no unicode arrows.
- **How to render:** the `Icon` component masks the SVG (`mask-image` + `background: currentColor`) so any glyph takes any token colour. Reference by file stem: `<Icon name="Search" size={20} />`. Set `window.OZEE_ICON_BASE` once per page if the folder sits elsewhere.
- **Sizes:** 12 (inline meta), 14 (chips, breadcrumbs), 16 (buttons, menu rows, table cells), 20 (nav, icon buttons, top bar), 24 (empty-state discs, large actions). Colours: `--icon-color` resting, `--primary-text-color` for emphasis, `--primary-color` for active nav, `--negative-color` for destructive.
- **Nav mapping used in the kit:** Home → My work, Board → Projects, CheckList → Tasks, Email → Approvals, Chart → Reports, Bolt → Automations, Team → Team, Settings → Admin.
- **Brand marks:** `assets/ozee-logo.png` (full lockup), `ozee-logo-sm.png`, `ozee-logo-header.png`, `favicon.svg`, plus `social-facebook/instagram/linkedin/x.svg` for email signatures. Nothing was drawn or reconstructed — every mark is the file shipped in the CRM repo.

### Substitutions to be aware of

- **Fonts:** neither repo ships font binaries. Figtree and Poppins are loaded from Google Fonts in `tokens/fonts.css` (the app itself loads Figtree from fonts.bunny.net). If OZee has licensed webfont files, drop them into `assets/fonts/` and swap the `@import` for `@font-face` rules.
- **Imagery/illustration:** none exists in either source, so none is included. Empty states use a grey icon disc rather than invented artwork.

---

## Index

| Path | What it is |
| --- | --- |
| `styles.css` | The single entry point consumers link — `@import`s only. |
| `tokens/` | `colors.css`, `brand.css`, `typography.css`, `spacing.css`, `radius.css`, `shadows.css`, `motion.css`, `semantic.css`, `fonts.css`, `base.css`. |
| `guidelines/` | 21 specimen cards (colours, type, spacing, radii, elevation, motion, states, icons, brand). |
| `components/` | The React primitives, grouped by concern — see the list below. |
| `ui_kits/crm/` | Click-through recreation of the CRM: login, my work, projects board, project detail, email approvals. Start at `ui_kits/crm/index.html`. |
| `templates/crm-screen/` | Copyable starting screen for a new CRM page (shell + page header + table). |
| `assets/` | Brand marks, social marks, favicon and the 90-glyph icon set. |
| `SKILL.md` | Agent-skill entry point. |
| `github.md` | Source-repo association and sync record. |

### Components

Grouped by directory; every name below is exported on the design-system namespace.

- **`components/core/`** — `Button`, `Icon`, `IconButton`, `ButtonGroup`, `SplitButton`, `Link`
- **`components/typography/`** — `Heading`, `Text`, `TextWithHighlight`, `FormattedNumber`, `EditableText`, `EditableHeading`
- **`components/forms/`** — `TextField`, `TextArea`, `Search`, `NumberField`, `Dropdown`, `Combobox`, `DialogContentContainer`, `Checkbox`, `RadioButton`, `Toggle`, `Slider`, `ProgressBar`, `ColorPicker`, `DatePicker`
- **`components/data/`** — `Table`, `List`, `ListItem`, `ListTitle`, `Chips`, `Label`, `Counter`, `Badge`, `Avatar`, `AvatarGroup`
- **`components/feedback/`** — `Toast`, `AlertBanner`, `AttentionBox`, `Tipseen`, `Tooltip`, `Info`, `Loader`, `Skeleton`, `EmptyState`
- **`components/navigation/`** — `Tabs`, `BreadcrumbsBar`, `Divider`, `Accordion`, `ExpandCollapse`, `Menu`, `MenuButton`, `Steps`, `MultiStepIndicator`
- **`components/overlays/`** — `Modal`

Each directory carries a `*.card.html` showcase, and each component a `.d.ts` props contract plus a `.prompt.md` usage note.

### Coverage against Vibe's inventory

Built: Accordion, AlertBanner, AttentionBox, Avatar, AvatarGroup, Badge, BreadcrumbsBar, Button, ButtonGroup, Checkbox, Chips, ColorPicker, Combobox, Counter, DatePicker, DialogContentContainer, Divider, Dropdown, EditableHeading, EditableText, EmptyState, ExpandCollapse, FormattedNumber, Heading, Icon, IconButton, Info, Label, Link, List, ListItem, ListTitle, Loader, Menu, MenuButton, Modal, MultiStepIndicator, NumberField, ProgressBar, RadioButton, Search, Skeleton, Slider, SplitButton, Steps, Table, Tabs, Text, TextArea, TextField, TextWithHighlight, Tipseen, Toast, Toggle, Tooltip.

Deliberately not built (non-visual utilities and virtualisation helpers with no design surface): `ThemeProvider`, `LayerProvider`, `Clickable`, `HiddenText`, `GridKeyboardNavigationContext`, `TransitionView`, `VirtualizedList`, `VirtualizedGrid`, `ColorUtils`.

**Intentional additions:** none. `Icon` exists in Vibe too (as `@vibe/icon`); here it wraps the copied SVG set instead of the React glyph components, because a browser-only design system cannot import TSX icon modules.
