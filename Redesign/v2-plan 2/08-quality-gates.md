# 08 — Quality gates, testing, CI, security, ADRs

## 1. Definition of done (every ticket)
1. Feature tests for every new HTTP endpoint: happy path, 403 (wrong permission / wrong project), 422 (validation) — Pest, `RefreshDatabase`, factories.
2. Unit tests for every Action with a business rule; table-driven tests for every state machine (Message, Task, Milestone, Invoice, Bill, Proposal, Approval).
3. Query tests with seeded datasets asserting shape and ordering (Inertia prop types generated and type-checked).
4. `composer test` (Pest, parallel), `vendor/bin/pint --test`, `vendor/bin/phpstan analyse` (level 6), `npm run typecheck`, `npm run lint`, `npm run build` — all green in CI.
5. Architecture test green (module boundaries).
6. `Model::preventLazyLoading()` and `preventSilentlyDiscardingAttributes()` enabled in tests → any N+1 or dropped attribute fails.
7. New enum → CHECK constraint test; new table → factory + migration rollback test.
8. Frontend: every new page has an empty state, loading skeleton, error toast path, dark mode check, keyboard path; a Playwright smoke test where it is on a core journey.
9. CHANGELOG entry; ADR when a decision deviates from the plan.

## 2. Test data
`DatabaseSeeder` (dev): 1 super-admin, 2 managers, 5 staff, 3 suppliers; 12 contacts (5 leads); 6 projects with milestones/tasks/scope/deliverables; 40 threads/200 messages across all body formats and statuses (fixtures ported from legacy `inbox:body-samples` categories); invoices/bills/proposals/payments covering every status; Xero/Stripe disabled. `php artisan demo:seed` idempotent.

## 3. CI pipeline (GitHub Actions)
`lint` (pint, phpstan, eslint, stylelint, prettier) → `test` (MySQL 8 service, Pest parallel, coverage ≥ 70% lines on `modules/`) → `frontend` (typecheck, build) → `e2e` (Playwright against `php artisan serve` + built assets, Gmail/Xero/Stripe/Gemini faked via `Http::fake` seams and `MailProvider` fake) → `deploy` (main only).

## 4. Performance budgets
- Inbox thread list p95 < 300 ms server time at 50k messages (indexes in 02; `EXPLAIN` checked in H5-02).
- Home / project detail p95 < 400 ms; ≤ 12 queries per page (assert with `assertQueryCountLessThan` in feature tests for the 6 heavy pages).
- No per-row signed URL generation or permission checks in serialisation (grep gate: no `$appends` in models).
- JS bundle ≤ 350 kB gz for the shell + inbox route (code-split per page via Inertia).
- Poll job ≤ 20 s per mailbox; send job ≤ 30 s; queue depth alert > 100.

## 5. Security checklist (H5-01, and at each phase gate)
- Every route has auth + permission middleware (test: `routes:list` diff against an allow-list of public routes).
- Policies deny by default; super-admin bypass only via `Gate::before`.
- Secrets: encrypted casts; `.env` only; rotate legacy public API keys at cutover.
- Signed URLs for downloads/pixels; access-link tokens hashed; OTP hashed; PIN hashed (bcrypt).
- Rate limits per 01 §11; auth attempts table + lockout; portal/client sessions revocable.
- Uploads: mime allow-list per purpose, size caps, images re-encoded, no SVG.
- Server-side redaction for private/screening messages and client-visible comments/attachments — never CSS hiding.
- Ghost sessions logged with actor; banner visible; expires in 60 min.
- Webhooks: signature verified, idempotent, replay-safe.
- Headers: CSP (self + fonts self-hosted), HSTS, frame-ancestors none except the sandboxed email preview iframe (`sandbox` attr, `srcdoc`).
- Dependency audit in CI (`composer audit`, `npm audit --production`).

## 6. Observability
Structured logs with module/entity context; Laravel Pulse (queues, slow queries) at `/pulse` (admin.view); health `/up`; failed job alerts; migration runs table; AI usage roll-up.

## 7. ADR template (`docs/decisions/ADR-NNNN-title.md`)
```
# ADR-NNNN: <title>
Date · Status (proposed/accepted/superseded) · Ticket
## Context  (what the plan said, what we found)
## Decision
## Consequences (what changes in the plan; files touched)
```
ADR-0001 = "Adopt master plan v1.0". ADR-0002 = "Workspaces/multi-tenancy deferred to R2" (09 §3).
