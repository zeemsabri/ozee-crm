# 13 — Philosophy: how the owner thinks, and how to think on his behalf

This document captures what the product owner (Usama / OZee Web & Digital) has asked for, in his own reasoning, so that any AI session working on v2 makes the same calls he would when the plan is silent. Read it once per session, before the ticket. When two plan files conflict, the one closer to these principles wins; when the plan is silent, decide by these principles and record the decision (ADR).

## 1. Why we are doing this at all
The current app was built over a year, ad hoc, by several AI agents with no plan. It works, but every feature was bolted on where it was easiest at the time: one model doing nine jobs, two inboxes, two automation builders, three tagging systems, five auth paths, permissions seeded in nine places, bugs papered over with synonym casts and commented-out code. The owner's conclusion is not "refactor" but **"start again, properly"**: a new codebase, a new database, a thoughtful structure — and migrate the data once the new version is ready. The redesign (monday.com-style) is the visible part; the invisible part matters more to him.

> "The new version shouldn't be just a new design, it should be a thoughtful structure created in modular structure as well as database."

## 2. The principles, in the owner's priority order

1. **Modular so one thing cannot break another.** Modules own their tables, actions and pages; they talk downward through models/contracts and upward through events; an architecture test enforces it. If you find yourself importing across the graph, the design is wrong — stop and raise it. *(01 §3)*
2. **Separate models for separate jobs — industry standard, not local invention.** He named the failure himself: `ProjectNote` used for everything, `project_expendables` for both budgets and proposals. The rule he wants applied everywhere: if two things have different lifecycles, owners, permissions or nullable-column sets, they are two tables. Follow how mature products model it (comments like GitHub/Jira, taxonomy like WordPress, documents/payments/ledger like accounting systems, contact/account/lead like a CRM). *(10)*
3. **Performance is a requirement, not a polish step.** No queries in serialisation, no JSON used as foreign keys, indexes designed with the queries, caching with a single writer, pagination everywhere, supervised queues. *(10 §B, 08 §4)*
4. **Clear enough that a smaller model can execute it.** The plan exists so that low-cost sessions can follow it *exactly*. So: explicit tickets with acceptance criteria, one source of truth per concern, vocabulary fixed in the glossary, decisions recorded with their reasons so nobody re-litigates them. If a ticket is ambiguous, the fix is to make the plan clearer, not to improvise. *(00 §0)*
5. **Start with what is necessary, then build on top.** He knows too many features were built. Release 1 is the operational spine (identity, contacts, projects/work, mail + approvals, finance, the two portals) plus what the design has drawn. Everything else is deferred with its data preserved — not rebuilt "because it was there". A deferred feature is a success, not a gap. *(07)*
6. **Design is a system, not a set of pages.** Tokens and the Vibe/monday.com kit are the foundation; components are layered and reusable; every new page is composed from the pattern library rather than drawn from scratch. Copy the design system, don't reinvent it; port missing primitives from the bundle. *(04, 11)*
7. **Mobile is never "later".** Dedicated mobile designs may come; until then every page ships with a working phone layout from the recipes, and mobile is part of each page's acceptance. *(11 §4, D22)*
8. **Migrate everything, lose nothing.** The old data is a year of real business; it gets mapped into the new structure with explicit rules, issues logged rather than silently dropped, deferred-module data archived raw, rehearsed twice before cutover, and the legacy DB left untouched for rollback. *(06)*
9. **Fix the causes, not the symptoms.** The legacy repo is full of patches around design flaws (synonym enum casts, `fix-email-types` commands, `withoutEvents` saves, 250 readme post-mortems). In v2, when something is wrong, change the model or the boundary — never add a compensating branch. The "known bugs not to reproduce" list is a contract. *(12 §9)*
10. **Document once, in the place agents will read.** The owner does not want future sessions re-reading the codebase; that is why 12 (legacy reference), the surveys, the decisions register and this file exist. When you learn something durable, put it in the plan or project memory, not in a chat.

## 3. How he makes decisions (so you can predict them)
- He prefers the **recommended, standard option** over the clever one, and a **clean cut over a gradual blend** when the blend creates two sources of truth (he chose "fresh repo + migrate" over "page by page on a shared DB").
- He will accept **more upfront structure** (workspace_id everywhere, enums with CHECK constraints, generic comments/attachments/approvals) if it avoids a second migration later.
- He wants **honest scoping**: say when something in the design is a separate product or a year of work (Client OS) rather than silently absorbing it; propose the split and ask.
- He expects **decisions with reasons**, then no re-litigation. Add to the decisions register; don't reopen D1–D22 in a ticket.
- He notices **duplication and overloading** immediately and dislikes it; if you are about to create a second way to do something that exists, stop.
- He is pragmatic about **legacy behaviour**: keep what users rely on (number prefixes, OTP login, the approval gate, supplier links still working), drop what was never finished or was debug scaffolding.
- He values **explicit, testable pipelines** over configurable engines for critical paths (email approval is code, not a workflow in JSON).

## 4. What "done" means to him
A ticket is done when it is modular (boundary test green), separated correctly (no overloaded model introduced), fast (query budget met), typed and tested, follows the design system on desktop *and* phone, is documented where the next agent will look, and adds no new ambiguity to the plan.

## 5. Things he has explicitly said (verbatim intent, paraphrased minimally)
- Start fresh with a new DB; the original was ad hoc, vibe-coded by multiple agents with no planning; after a year we are ready to start again.
- Use the new design with Laravel, Inertia and React for a monday.com-like experience.
- Make the new version as modular as possible so one thing doesn't break another.
- Create a plan detailed enough that other, lower-usage models can follow it exactly; you are the planner, not the developer.
- Once the new version is ready, migrate all old data into the new structure.
- The new database `ozee-crm-v2` is empty and ready.
- ProjectNote for everything was a mistake; separate models where necessary; follow industry standards and best practices; the goal is not just redesign — performance and standards are necessary.
- Too many features were created that may not be required; start with what is necessary and build on top.
- Project expendables mixing proposals and budgets should be separated; the plan should separate models wherever necessary, clearly and performance-focused.
- Design plan must give clear guidance on creating components with reusability in mind and mobile optimisation; separate mobile designs may come later, but mobile must not be forgotten when creating pages.
- The existing structure should be explained in a document so AI does not have to check existing files every time.
- There should be a philosophy document so AI has more context of the owner's thought process (this file).
