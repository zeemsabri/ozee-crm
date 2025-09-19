### What to do first — a small, safe, high‑impact plan

Below is a prioritized, bite‑sized plan to continue the enum adoption and remove stray string literals, one model at a time, while keeping everything backward compatible and shippable in small PRs.

---

### Phase A: Stabilize foundation (quick checks)
- Confirm Value Dictionary endpoints work in all environments:
    - `GET /api/value-dictionaries`
    - `GET /api/value-dictionaries/{model}/{field}`
- Turn on soft validation logs in non‑prod to catch bad values:
    - Ensure `value_sets.enforce_validation=false` and logs are visible.
- Create a PR template/checklist for each model migration (see below).

---

### Model migration checklist (use this per model)
For each model (Task, Milestone, Project, Email, ProjectExpendable, then others), do the following:
- Replace raw string usages with enum cases in:
    - Model methods, accessors/mutators, events, scopes.
    - Controllers/Services/Jobs/Policies touching this model’s fields.
    - Query filters (use `->value` where needed).
- Keep backward compatibility:
    - Keep existing constants as aliases to enum values (or deprecate with comments).
    - Accept incoming string values in requests (casts handle coercion).
- Add request validation using `ValueSetValidator` where the API writes these fields.
- Update tests and add new ones for enum paths and legacy string inputs.
- Run a quick data probe to surface unexpected values in DB for that field.

PR acceptance criteria:
- No raw string literals for the field remain in `app/` for that model’s logic.
- All queries compile; existing API contracts unchanged.
- Soft validation emits no warnings for common paths.

---

### Execution order (small chunks)
1) Task (already partially enum‑ized)
2) Milestone (mostly enum‑ized; clean up leftovers)
3) Project (status enum adopted; sweep controllers/services)
4) Email (status/type enums; many constants and conditionals to normalize)
5) ProjectExpendable (status enum; complete integration in logs/actions)
6) Secondary/related models (e.g., TaskType, any custom status/priority fields) — only if needed.

---

### Step 1 (this sprint): Finish Task model cleanup
Scope: Task model + direct controllers/services.

Tasks:
- Replace internal string comparisons with `TaskStatus` (some are already done):
    - Accessors/helpers like `isCompleted()`, `start()`, `block()`, `archive()` are good; scan remaining methods for `'To Do'`, `'In Progress'`, `'Paused'`, `'Done'`, `'Blocked'`, `'Archived'`.
- Constants strategy (backward compatibility):
    - Keep current constants (e.g., `STATUS_DONE`) but redefine them to reference enum values:
      ```php
      public const STATUS_DONE = \App\Enums\TaskStatus::Done->value;
      ```
    - Mark constants as deprecated in PHPDoc; steer new code to enums.
- Queries in controllers/services:
    - `TaskController::getTaskStatistics()` already uses `TaskStatus::Done->value`, `Archived->value` — good.
    - `TaskController::index()` currently allows `status` query param as string; keep it, but optionally map to enum when building the query:
        - If provided value matches an allowed value from the registry for `Task.status`, use it as is.
        - Optionally add a thin helper to coerce case/label to value if needed.
- Factories/seeders:
    - Ensure `TaskFactory` uses enum values for `status` (if not already).
- Validation:
    - For any endpoint creating/updating tasks with `status`, call `ValueSetValidator->validate('Task','status',$status)` before save (soft‑fail in this stage).
- Tests:
    - Add tests for enum coercion on save and JSON (serialize enum as value).
    - Add tests for filtering by status with both enum values and legacy strings.

Deliverable: PR-Task-Enums-Cleanup

---

### Step 2: Milestone cleanup (tiny)
Scope: Milestone model + any controllers/jobs that set status.

Tasks:
- Replace string comparisons with `MilestoneStatus` in methods not yet converted.
- Keep any constants as enum value aliases; add deprecation notes.
- Ensure event hook uses enum comparisons consistently (part already uses enum).
- Add `ValueSetValidator` for API writes touching `Milestone.status`.
- Tests: Completed/Overdue logic with enum cases.

Deliverable: PR-Milestone-Enums-Cleanup

---

### Step 3: Project cleanup
Scope: Project model + controllers/services using `status`.

Tasks:
- Ensure all comparisons use `ProjectStatus` (model casts are present already).
- Update any filters (controllers) to use `->value` and accept legacy strings in requests.
- Add `ValueSetValidator` for `Project.status` in write endpoints.
- Tests: filtering and serialization with enums.

Deliverable: PR-Project-Enums-Cleanup

---

### Step 4: Email (status/type) — slightly larger
Scope: Email model, email controllers/services, points logic, approval flows.

Tasks:
- Replace constants/string comparisons with `EmailStatus`/`EmailType` where practical.
- Keep constants as enum value aliases for now (many parts of the app and templates may reference them).
- Normalize spots that lowercase strings (e.g., `strtolower((string)$email->type) === 'sent'`) to use enum comparisons first and fallback to string.
- Add `ValueSetValidator` for `Email.status` and `Email.type` on writes.
- Tests: status/type state transitions, event triggers (awarding points) under enums and legacy strings.

Deliverable: PR-Email-Enums-Cleanup

---

### Step 5: ProjectExpendable cleanup
Scope: ProjectExpendable model + activity log usages.

Tasks:
- Replace constants with enum value aliases and use `ProjectExpendableStatus` in methods `accept()`/`reject()` and elsewhere.
- Ensure defaulting to `PendingApproval` works via casts/mutators.
- Add `ValueSetValidator` on writes.
- Tests: transitions and activity log properties include enum values.

Deliverable: PR-ProjectExpendable-Enums-Cleanup

---

### Tooling: Finding remaining raw strings quickly
Run these greps (or equivalents) to identify hotspots per model:
- Status literals in PHP:
    - `grep -R "status[^\n]*'\|\"" app | grep -E "To Do|In Progress|Paused|Done|Blocked|Archived|planned|in_progress|on_hold|completed|canceled|pending|approved|rejected|overdue|expired|draft|sent|received|Pending Approval|Accepted|Rejected"`
- Comparisons/queries:
    - `grep -R "where(\s*'status'" app`
    - `grep -R "->status\s*=\s*'" app`
    - `grep -R "===\s*'\|!==\s*'" app`
- Factories/seeders:
    - `grep -R "factory|Seeder" database | grep -i status`

For each hit, convert to enum usage or validate through the registry.

---

### Communication & flags
- Keep validation in soft mode for one full cycle after each PR to spot logs without breaking flows.
- After all five PRs are merged and logs are clean, consider enabling `value_sets.enforce_validation=true` in staging.

---

### What I will do next (immediately)
- Start PR-Task-Enums-Cleanup (Step 1):
    - Sweep `Task` model for leftover literals and constants mapping.
    - Update `TaskController::index()` to optionally coerce `status` filter via registry and keep string compatibility.
    - Add `ValueSetValidator` to create/update paths for Task.
    - Add unit tests for enum behaviors.

Once you approve this plan, I’ll execute Step 1 and report back with the diff and any follow‑ups.
