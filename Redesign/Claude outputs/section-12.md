
## 12. Admin pages and forms — choosing a layout when nobody has drawn the page

*10 September 2026. Reference design: `docs/CRM Restructure/Admin Page
Patterns.dc.html`. Built on `/dev/ds` → **Admin pages**
(`resources/js/dev/AdminGallery.tsx`). Prompted by `/admin/ai`.*

`/admin/ai` was built with every pattern in this guide already to hand, and it
still came out wrong in a way none of the earlier sections could have caught:
switches, models, settings, limits, spend and a fifty-row log in **one scroll**;
five settings each with **their own Save button**; a five-field limit form run
**down a single column** with its button inside the field stack; and a log with
**no filter** beyond one dropdown in a card header. Every component was the
right one. Nobody chose a *shape*, and nothing in this guide said how to. §8
and §11 cover pages that show a list; this section covers the page you work
*in* — an admin area, a settings screen, a single record — and the forms on it.

The rule the owner gave, restated for pages: **a page that cannot describe its
own layout cannot get it wrong.** So the layout is components (`RecordPage`,
`FieldGrid`, `SettingsList`, `SaveBar`, `Consequence`), and the choosing is a
four-step procedure that goes in the page's doc comment before any JSX.

### 12a. The thought process — four steps, in the doc comment, before JSX

1. **Inventory what the page has.** Not what it shows — what it *has*: records
   to scan (a log, an audit trail, users), one thing to work in (settings, a
   form, switches), facts about that thing, history, a choice between existing
   things. Write the list down.
2. **Split it into jobs.** One page, one job; a tab is a job with its own
   toolbar. The test is mechanical: *if the body would hold a filterable list
   **and** a form, it is two tabs.* A log, a history, "the last fifty calls" is
   **always** its own tab or page, with a `FilterBar` — a list nobody can narrow
   is a list nobody reads. `/admin/ai` is four tabs: Overview (switches, the
   model in use, spend), Models, Limits, Log. It shipped as one.
3. **Pick the layout per tab**, from the table in 12b. If two rows describe the
   tab, go back to step 2.
4. **Pick the pieces** — `FieldGrid` or `FormStack`, `SettingsList`, `SaveBar`,
   `Consequence` — then look at it in a browser at 1280 and at 390.

### 12b. Which layout — what the tab has, and what that makes it

| The tab has… | So it is… | Because |
|---|---|---|
| Many records of one kind — scanned, searched, narrowed | `ListPage` (title · stats · `FilterBar` · `SectionCard flush` · `Pager`) — §11a | A log, an audit trail, calls, users. Its own tab or page and its own `FilterBar`, always. |
| One thing to work in — settings, a form, switches — plus facts and history about it | `RecordPage` (title · stats · main column of `SectionCard`s · 320px read-only aside) | The work goes left at a reading measure; facts, the policy note and recent activity go in the aside. Nothing you type into goes in the aside. |
| A list beside the thing selected from it | `MasterDetail` | Inbox, templates, a directory with a detail column. One pane at a time on a phone. |
| Both a list to narrow and a form | **Two tabs.** Never one long page. | A page is a place, a tab is a job. |
| A record to add or edit, opened from a list | `SidePanel` + `FormStack` | The list keeps its scroll and filters underneath; fields run down a 400px column, so a stack, not a grid. |
| A dialog that *is* the task ("Add users", "New team") | `Modal size="medium" titleSize="large"` — §8 | One field, the list open under it, one primary action. Not for a form of any size. |
| Five or more fields inside a card | `FieldGroup` › `FieldGrid` › `FieldSpan`, one `SaveBar` in the card footer | Two columns at 760px, three past 1000px; the grid decides. `FormStack` is for panels only. |
| A row of one-line settings — a number, a default, an on/off | `SettingsList` › `SettingRow` in a `SectionCard flush`, one `SaveBar` that counts changes | Label and help left, control right, hairlines between. Fifteen one-field forms read as fifteen screens. |
| Choose an existing thing, or add a new one | One `SectionCard`, `ButtonGroup` at the top, the thing in use shown as a row with its real values | Two cards — a picker and an add form — leave nobody sure whether adding one switched to it. |
| A button with a consequence | `Consequence` strip immediately above the button | Say what pressing it does, in a sentence, where the finger is. |

### 12c. The page — `RecordPage` (`ds/patterns/RecordPage.tsx`)

`PageHeader` (with `tabs`), then a `PageGutter` holding an optional `StatGrid`
and a two-column grid: `minmax(0, 1fr) 320px`, `gap: var(--space-20)`,
`align-items: start`. The aside is `position: sticky` under the top bar and
**read-only** — `FactList`, `Note`, `AttentionBox`, `ActivityLog`. Under 1100px
it drops below the main column. Cards in the main column stack 20px apart.
Omit `aside` and the page is one column at the same measure — right for a page
with nothing to say beside the work, wrong for one that has and puts it in the
main column.

```tsx
<RecordPage
  title="AI"
  subtitle="…"
  tabs={tabs} activeTab={tab} onTabChange={setTab}
  stats={<><StatTile … /><StatTile … /></>}
  aside={<><SectionCard title="At a glance"><FactList … /></SectionCard><AttentionBox … /></>}
>
  <SectionCard title="Switches" flush>…</SectionCard>
  <SectionCard title="Models and prices" flush footer={<SaveBar>…</SaveBar>}>…</SectionCard>
</RecordPage>
```

A form never goes in the aside. Policy prose never goes in the main column. No
page-level action bar floats above the cards — every save lives in the footer
of the card it saves.

### 12d. The card — `SectionCard` + `SaveBar`

Title, one grey `detail` line saying what the section is for, `actions` in the
header, body (`flush` when rows bring their own padding), and a `footer`
holding the save. `SaveBar` (`ds/patterns/SaveBar.tsx`) is what goes in that
footer: a muted `status` on the left that says what will change — *"3 settings
changed"*, *"Unsaved — nothing is written until you save"*, *"No ceiling set —
this saves as unlimited"* — and the buttons on the right, tertiary before
primary. **One `SaveBar` per card. Never a Save per field; never one Save for
two cards.** A row that is its own decision — a kill switch — saves on the
spot with no bar at all.

The read-only twin of a form is a `FactList`: a definition list, never
disabled fields, and an unknown value is an em dash.

### 12e. Fields — `FieldGrid`, `FieldGroup`, `FieldSpan` (`ds/patterns/FieldGrid.tsx`)

`FieldGrid` is `repeat(auto-fit, minmax(220px, 1fr))`, `gap: var(--space-16)`
— two columns at 760px, three once the card clears about 1000px. **Let the grid
decide; never hard-code a count** — a fixed three collapses badly in the narrow
column. `measure="fields"` (default) caps the grid at 1100px for short fields;
`measure="prose"` caps it at 760px for fields that hold sentences or a
decision pair. Three is the ceiling for short fields (a name, a number, a
date); anything whose label needs qualifying stays at two — use `prose`.

`FieldGroup` is three to six related fields under a `text3` uppercase grey
heading with a hairline, so a fifteen-field form reads as four decisions.
Stack groups in `<FormStack loose>`; the group carries no margin of its own.
`FieldSpan` makes a textarea, an address, anything holding a sentence, take
the whole row.

`FormStack` is **not** replaced. It is the column inside a `SidePanel` or a
dialog, where a grid is nonsense; and it is what stacks `FieldGroup`s. What
changes is that a `FormStack` of five short `TextField`s inside a 760px card
is now a bug with a name.

**Dependent pairs** — pick the client, then who to sign in as — go on one row
of a `prose` grid. The second field is `disabled` with a `placeholder` that
names what it is waiting for. More than eight options: `searchable`. More than
fifty: a search field, not a dropdown.

### 12f. Settings — `SettingsList`, `SettingRow` (`ds/patterns/SettingsList.tsx`)

Label and help on the left, the control in a 280px column on the right,
hairlines between, in a `SectionCard flush`, with one `SaveBar` in the footer
that counts what changed. The control is `TextField size="small"`,
`Dropdown size="small"` or `Toggle`; a control that needs more than 280px is a
form, not a setting. A toggle row saves on change and is not counted — the
list is the shape, whether a row saves itself is the row's business.

### 12g. Choosing — one card, `ButtonGroup`, the thing in use as a row

Choose-or-create is **one** `SectionCard` with a segmented `ButtonGroup` at the
top and the current choice shown with its real values (*"$0.30 in, $2.50 out
per million"*), not two cards. Creating a thing never silently selects it —
say so in a `Note`, or offer the checkbox (*"Use this model once it is
saved"*). The footer button's label follows the mode.

### 12h. Consequence — `Consequence` (`ds/patterns/Consequence.tsx`)

Any action with a consequence states it in a grey strip immediately above the
button, and the strip carries the button: icon, one sentence, `action`. *"You
will see the portal exactly as Jane does, read only, for 30 minutes. Every
page you open is logged against your name."* — then *Start session*. Not a
tooltip, not a confirm nobody reads. `Note` is the quiet line that explains a
section; `Consequence` sits between a decision and its button.

### 12i. Checklist for any admin, settings or record ticket

- [ ] The doc comment lists what the page *has* and which tab each thing is on (12a)
- [ ] A log, history or audit trail is its own tab — a `ListPage` with a `FilterBar`
- [ ] The working tab is a `RecordPage`; the aside holds only what is read, never typed into
- [ ] Every card that saves has exactly one `SaveBar`, in its footer, saying what will change
- [ ] Five or more fields in a card are a `FieldGrid` in `FieldGroup`s, never a `FormStack`
- [ ] One-line settings are a `SettingsList` with one footer save; toggles save themselves
- [ ] Choose-or-create is one card with a `ButtonGroup`; creating never silently selects
- [ ] A button with a consequence has a `Consequence` strip above it
- [ ] Looked at in a browser at 1280 and 390 — the aside drops, the grid reflows, the save row spans
