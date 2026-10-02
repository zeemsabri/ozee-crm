# A04 — Builder UI (keep the V2 shape, rebuild on the design system, fix the defects)

Design system rules: `../04-frontend.md`, `../11-design-system-components-and-mobile.md` (layers, patterns, checklists) apply. Screens below reuse `AppShell`, `PageHeader`, `DataTable`, `SidePanel`, `Sheet`, `EmptyState`, `Timeline`, `StatGrid`. Canvas library: **React Flow** (`@xyflow/react`), wrapped in `features/automation/canvas/*`; nodes are L3 components built from `ds` primitives. Legacy reference: `../survey/automation_deep.md §9` (keep list in §9.9).

## 1. Hub — `/automations`
- `PageHeader` "Automations" · sub-line "<n> active · <m> runs today · <f> failed" · actions: `Import`, `New automation` (primary).
- `FilterBar`: search (name/trigger), status (Active/Paused/Draft/Archived), trigger kind, team (visible teams), sort (Recent/Name/Failures/Runs) — all wired (legacy had decorative selects).
- `DataTable` (card mode on phone) columns: name + description, trigger (icon + event/schedule summary in English: "When a task is marked done", "Every weekday at 8:00 Perth"), team, status pill, last run (time + status dot), runs 30d / failed 30d (spark), version (`v3 · published 2 Sep`), actions menu: Open, Run now, Pause/Resume, Duplicate, View runs, Export, Archive (confirm dialog — legacy deleted without confirm).
- Row click → Builder. Failed-runs pill is a link to filtered runs. Empty state copy: "No automations yet — Start from a template or build one".
- Templates drawer: built-in templates (A06 §4) with capability list and "Use template".

## 2. Builder — `/automations/{id}`
Layout (desktop): header · left **Step library** (240px, collapsible) · centre **Canvas** · right **Properties panel** (SidePanel 480px, resizable to 640 for wide forms). Phone: read-only canvas (pan/zoom, tap node → properties as a Sheet in read mode) + Runs tab; editing prompts "Open on desktop to edit".

Header: back · workflow name (`EditableHeading`) · team chip · version pill (`Draft · unsaved` / `Draft v4 (published v3)`) · `Validate` · `Test run` · `Publish` (primary; disabled with tooltip listing blocking issues) · overflow: Duplicate, Export JSON, Version history, Archive. Autosave of the draft every 5 s after change (debounced) with "Saved just now"; explicit `Save draft` too; dirty-state guard on navigation.

Step library: categories Triggers (event, schedule, manual, webhook), Logic (condition, loop, delay, wait for approval, wait for event, stop), Data (fetch records, set variables, transform), Actions (grouped by module from the ActionCatalogue: Contacts, Projects & tasks, Email, Finance, Notifications, Generic records), AI (run prompt, classify, summarise, extract), Integrations (HTTP request, run workflow). Search box. Drag to canvas **or** click "+" on a node/edge to insert at that spot (legacy: new blocks were always unlinked — in v2 a dropped node must be attached; a node dropped on empty canvas becomes a "pending" node with a warning badge and cannot be published).

Canvas rules:
- Node = `StepNode` (280×auto): type icon disc + colour by category, name (inline editable), one-line summary (English, e.g. "Fetch leads where status is contacted, up to 100", "If task.status changed to done", "Run prompt 'Lead reply' v3"), badges: delay, retry, guard, warning/error count from validation, **last run status dot** (from the selected run or latest).
- Edges: sequential edges drawn for every sibling relation including after containers (legacy bug: no edge after a condition). Condition node has `then`/`else` ports with labels; loop node has `body` port and an "after loop" port; wait_for_approval has approved/rejected; `on_error: branch` shows a dashed red port.
- Auto layout (dagre) with manual nudge; layout stored in `versions.layout` (never in step config). "Tidy up" button.
- Insert: hover an edge → "+" → picker; hover node bottom → "+ add step below". Reorder by drag onto edges. Delete container → confirm "Also delete N steps inside?" with option to keep children (splice them up).
- Multi-select, copy/paste (within and across workflows), undo/redo (history stack), keyboard: arrows to move selection, Delete, Cmd/Ctrl+Z, `/` search.
- Minimap, zoom controls, fit view. Virtualised rendering for >150 nodes. Properties edits update only the edited node (no full canvas rebuild — legacy re-rendered everything per keystroke).
- Validation overlay: nodes with issues get a red/amber badge; the issues list panel groups by node with "Go to" links.

Properties panel (per selected node): header (type icon, editable name, key shown as code with "edit key" → refactors references), common section (guard, on error, retry, timeout, delay) collapsed by default, then the type form generated from `configSchema` with custom widgets:
- **Expression input** (`TokenInputField` v2): monospace-ish field, token chips rendered inline (`trigger.task.title` as a chip with hover preview of the sample value), "+ Insert token" opens the **Token picker** (`Sheet`/popover: left = sources available *at this step* — Trigger, earlier steps by name, Variables, Loop, Actor/Workspace, Functions; right = fields as a searchable tree with types and sample values from the last run or the event sample payload; nested fields expand; clicking inserts; a "filters" tab appends `| filter`). Live preview line evaluates the expression against the sample context (TS mirror of the evaluator).
- **Rule builder** (condition/filter/fetch where): rows `left expr · operator (filtered by left type) · right expr|literal`, group toggle all/any, nested group once. Operators list = §3 of A03, exactly.
- **Model & field pickers**: model picker grouped by module with search and descriptions (from ModelCatalogue), field select with type/enum badges; enum fields show a dropdown or an expression toggle.
- **Relationship picker** (AI inputs, fetch `with`): tree of relations → fields → nested one level, "all fields" toggle, shows the resulting `| with(...)` expression. Kept from legacy — it is the best authoring affordance in the system.
- **Action forms**: generated from the action's inputSchema (labels, help, required); output preview shows what tokens the step will expose.
- **AI prompt step**: prompt selector (search, version pill, "open prompt" link), inputs mapping table (prompt variable → expression), response schema viewer (read-only), "Test prompt with sample" button (runs against sample context, shows output + tokens/cost), drift warning when the prompt has a newer version.
- **HTTP step**: credential select (with "add credential" for L3+), method, URL with host check, headers/query/body editors (JSON with expression support), response path, response schema builder (name/type/nested), "Send test request" (uses credential, shows status + body sample → fills schema).
- **Schedule trigger**: simple mode (every day/week/month at, weekdays, timezone) with next 5 run times preview; cron mode with human description.
- **Event trigger**: event picker grouped by module with descriptions and sample payload viewer; filter rule builder; "recent occurrences" list (last 10 events of that type in this workspace) to pick a sample.

Test run panel (`Test run`): choose a sample (recent occurrence, a specific record by search, or JSON), toggle **dry run** (no writes: actions return simulated outputs; AI/HTTP run for real only if ticked), execute → live timeline in the right panel with per-node status dots on the canvas, click node → its inputs/outputs. Dry runs are stored as runs flagged `is_test` (7-day retention) so they can be inspected later.

Publish dialog: version diff (steps added/removed/changed, expressions changed), capability list ("This automation can: …"), required approver if guarded, change note, "Pause after publish" option. After publish: Hub shows version; runs use it immediately.

Version history: list of versions with publisher, note, diff viewer, "Restore as draft".

## 3. Runs — `/automations/{id}/runs` and `/automations/runs`
- Filters: status, date range, trigger, subject (search by record), version, has-errors, test runs toggle. `DataTable`: run id, started, duration, trigger summary ("Task OZ-1234 marked done by Mia"), status pill, steps (done/failed/skipped counts), AI cost, version. Bulk: retry failed, cancel waiting. Live updates via Echo.
- Run detail (`/runs/{run}`): header (status, timing, trigger subject with link, actor, version, "Retry from failed step", "Cancel", "Replay as test") · **Timeline** (L2 `Timeline`) of step runs in execution order with loop iterations grouped and collapsible ("Loop leads · 12 iterations · 1 failed" → expand), condition rows show the decision and the evaluated rules in English ("task.status changed to done — passed"), waits show waited-for duration and who approved · right pane per step: Inputs (resolved values), Outputs (JSON tree with search/copy; truncated payloads open the attachment), Effects (records created/updated with links), Error (code, message, suggestion when known, e.g. "Field `due_at` expects a date; got 'tomorrow'. Use `{{ now | add(1,'day') }}`"), Context at this step (folded from deltas, on demand) · Canvas mini-view of the version with status dots (click → step).
- "Which automation touched this?" — an `AutomationEffects` L3 component embedded on task/thread/contact detail pages listing `run_effects` for that record with links to runs.

## 4. Credentials — `/automations/credentials` (L3+)
Table: name, kind, allowed hosts, team scope, last used, created by; create/rotate/delete; values write-only (shown once on create).

## 5. Prompt library — Ai module pages `/ai/prompts` (linked from the step)
Keep the legacy editor's strengths (system prompt editor, template variables with types, response schema builder with nested fields, generation config) on the design system; add version list with diff, "used by N automations" (from step references), test bench with sample inputs, cost estimate, archive (archived prompts not selectable).

## 6. Mobile
Hub and Runs are full pages on phone (card mode, filters in a Sheet); run detail timeline is phone-first (it is the page an on-call person opens from a failure notification); Builder is read-only on phone with pause/resume/run-now actions available. Notification deep links go to the run detail.

## 7. Copy rules (guide §13)
Sentence case, verb-first: "Publish", "Run now", "Retry from failed step", "Pause", "Resume". Status words: Queued · Running · Waiting · Succeeded · Failed · Cancelled · Timed out. Trigger summaries are generated English (`TriggerSummary.ts` + PHP twin used in emails).
