Normalize the workspace board so All Users and individual-user views use the same lifecycle rules, then optimize performance by loading active work first and treating Done as a separate summarized dataset. Keep new tasks in To Do and expose newness as UI metadata rather than a new backend status. Also modernize the filter system and board presentation so the page matches current industry-standard team workspace patterns: predictable server-backed filters, clear quick filters, better status visibility, sensible defaults, and a view that makes active work easy to scan. Avoid one network request per column as the default strategy; prefer a hybrid model where active work is fetched in one scoped query and heavier columns such as Done are loaded separately only when that improves performance or pagination.

Steps
1. Align the board query contract in /Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Workspace/Index.vue so All Users and per-user views both fetch the same active status set by default.
2. Remove the current hidden due-date narrowing from the default All Users board behavior unless it is an explicit user-selected filter.
3. Change the main kanban payload to focus on active statuses only: To Do, In Progress, Paused, and Blocked.
4. Keep Done as a secondary summarized dataset, ideally via a lightweight recent-completions fetch or count query rather than mixing it into the main active-work payload.
5. Do not split the default board into one request per visible column unless there is a measured need. The recommended fetch pattern is a hybrid: one scoped query for active work across the board, plus separate lazy or on-demand queries for heavy or review-oriented datasets such as Done, Archived, or expanded history.
6. If column-level fetching is introduced later, use it intentionally for independent pagination, virtualized long columns, or lazy loading of hidden columns, not as a blanket replacement for a coherent board query.
7. Redesign the filter model to follow modern workspace standards: keep primary filters always visible and lightweight, such as assignee, project, status scope, due date, and search; move lower-frequency filters such as milestone, priority, and completion date into an advanced filter drawer or popover.
8. Make filters predictable and consistent by pushing primary filtering server-side where practical, especially for assignee, project, status set, due window, and search. Avoid mixing hidden backend filters with unrelated frontend-only filtering unless the UI clearly communicates what is local versus server-backed.
9. Add a small set of opinionated default views or presets, for example My Active Work, Team Active Work, Overdue, and Recent Done, so users can get to the most common slices without manually rebuilding filters every time.
10. Update the board UI in /Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Workspace/Index.vue so Done remains limited, but users can clearly see there are more completed tasks than the visible sample.
11. Improve board scanability to match current industry-standard kanban patterns: sticky filter bar, visible result counts, per-column task counts, strong empty states, loading skeletons, active filter chips, and a clear reset action.
12. Add a secondary list view or compact table-style view if the existing page already supports multiple modes cleanly, so users can switch from board scanning to denser triage without overloading the kanban view.
13. Fix task mutation synchronization so editing, deleting, or changing status from the task detail sidebar updates the workspace board immediately without a full page refresh. The board should own a consistent local source of truth and reconcile optimistic updates, successful mutations, removals, and fallback refetches.
14. Replace refresh-only flows with explicit update and delete propagation from task detail interactions into the workspace state, either through a shared task store/composable or through well-defined event handlers that patch or remove tasks in place.
15. Ensure create and save flows re-fetch using the current active filters and scope rather than falling back to a generic assigned-to-me refresh that can desynchronize the visible board.
16. Add a New presentation layer on top of To Do, for example a badge, filter, or pinned subsection for recently created and not-yet-started tasks.
17. Do not add a new persisted status unless you later decide the business needs a formal intake queue with distinct triage ownership.
18. Add backend test coverage for the task queries in /Users/zeeshansabri/laravel/email-approval-app/app/Http/Controllers/Api/TaskController.php, because there is currently no meaningful workspace/task board test coverage in /Users/zeeshansabri/laravel/email-approval-app/tests/Feature.

Relevant files
1. /Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Workspace/Index.vue for the current fetch logic, columns, Done trimming, and board UX.
2. /Users/zeeshansabri/laravel/email-approval-app/app/Http/Controllers/Api/TaskController.php for status filtering, due-date filtering, and any workspace-specific query preset support.
3. /Users/zeeshansabri/laravel/email-approval-app/app/Enums/TaskStatus.php as the canonical status set, which should stay unchanged under the recommended approach.
4. /Users/zeeshansabri/laravel/email-approval-app/app/Models/Task.php because active status rules and activity descriptions assume To Do is the initial stage.
5. /Users/zeeshansabri/laravel/email-approval-app/resources/js/Utils/taskState.js because transitions currently start from To Do and would need redesign if New became real.
6. Any workspace filter or subcomponents under /Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Workspace/components that already manage toolbar controls, filter popovers, or alternate views, because the filter redesign should reuse existing page structure instead of duplicating controls.
7. /Users/zeeshansabri/laravel/email-approval-app/resources/js/Layouts/AuthenticatedLayout.vue because the global task sidebar currently receives task-updated and task-deleted events but only logs them, which is relevant to why workspace changes may not reflect immediately.
8. /Users/zeeshansabri/laravel/email-approval-app/resources/js/Components/Layout/TaskSidebar.vue and /Users/zeeshansabri/laravel/email-approval-app/resources/js/Components/ProjectTasks/TaskDetailSidebar.vue because they emit task mutation events that should be connected to workspace state updates.

Verification
1. Compare All Users and individual-user board results for the same project and confirm that active tasks appear consistently in To Do, In Progress, Paused, and Blocked.
2. Confirm Done still shows only a small recent set while also exposing a total count or view-more path.
3. Verify that primary filters are reflected consistently in both the request layer and the visible UI state, with no hidden narrowing when switching between All Users and a specific user.
4. Verify that common workflows require minimal clicks: a user should be able to reach My Active Work, Team Active Work, Overdue, and Recent Done from visible controls or presets.
5. Verify that active filter chips, counts, reset behavior, and empty states remain correct across board and any secondary list view.
6. Verify that task updates, deletes, and status changes made from the task detail sidebar are reflected in the workspace board immediately, without requiring a manual page refresh.
7. Verify that create and save flows preserve the current filter scope and do not unexpectedly swap the board back to a different dataset.
8. Verify that the hybrid fetch strategy does not introduce duplicate cards, stale column counts, or inconsistent pagination between active work and recent Done.
9. Create a new task and confirm it lands in To Do while being visibly marked as New.
10. Run backend feature tests for task filtering combinations and manually verify assignee, project, search, due window, preset, mutation sync, and completion-summary behavior if frontend automation is not present.

Decision
1. Recommended: active work first, summarized Done, New as UI-only metadata.
2. Not recommended right now: a real New status or default All Users behavior that silently hides most active work via due-date constraints.
3. Recommended: a layered filter experience with a small quick-filter surface and advanced filters hidden behind an intentional control, instead of exposing every filter equally.
4. Recommended: preserve board focus for active work and use presets, summaries, or a denser alternate view for review-oriented workflows.
5. Recommended: a hybrid fetch strategy rather than one request per column by default.
6. Recommended: immediate local reconciliation after task mutations, with targeted refetch only when needed as a safety net.

Findings
1. The mismatch is caused by two different data-loading rules on the same board, not by missing tasks in storage. In /Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Workspace/Index.vue, the All Users path adds a due until today constraint, while the per-user path does not.
2. That due-date constraint is enforced server-side in /Users/zeeshansabri/laravel/email-approval-app/app/Http/Controllers/Api/TaskController.php, so tasks with no due date or a future due date are excluded before the board groups them into columns.
3. The board already intentionally trims Done to the latest 10 items in /Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Workspace/Index.vue, which is a reasonable optimization. The problem is that active work is currently being filtered out too aggressively in All Users mode.
4. A real New status would ripple through the lifecycle rules in /Users/zeeshansabri/laravel/email-approval-app/app/Enums/TaskStatus.php, /Users/zeeshansabri/laravel/email-approval-app/app/Models/Task.php, and /Users/zeeshansabri/laravel/email-approval-app/resources/js/Utils/taskState.js. Today the system assumes To Do is the initial working state.
5. The current filter experience is functionally capable but not yet product-shaped around primary versus advanced use cases, which makes it easier for users to miss what the board is actually showing.
6. Modern team boards usually separate active execution views from review and reporting views; forcing both into one default kanban surface tends to increase noise and reduce trust.
7. The workspace page currently has a local update helper for in-board drag-and-drop changes, but global task-detail sidebar updates are not clearly reconciled back into the workspace board state.
8. The current save flow in the workspace board calls a broad assigned-tasks refresh path, which risks ignoring the active board scope and contributing to stale or inconsistent post-edit behavior.

Why this is the better model
1. Users trust a board when filter changes narrow the same dataset, not when each selection uses different hidden rules.
2. Team boards are most useful when they emphasize work in progress and work waiting to start, not a large completed backlog.
3. A New status is only valuable when there is a real business step called triage or intake. If users can act on a task immediately, To Do is usually the correct first state.
4. A visual New marker gives the same visibility without multiplying workflow rules, drag/drop paths, reporting logic, and API complexity.
5. Industry-standard filter design reduces cognitive load by making the default state obvious, surfacing the most-used controls first, and letting advanced filters refine rather than redefine the entire view.
6. A modern board view should optimize for scanability first: a user should understand scope, workload, blockers, and recent completions within a few seconds without reading every card.
7. One request per column is usually only worth it when each column needs its own pagination or lazy loading; otherwise it increases request count, complicates consistency, and makes filter changes harder to reason about.
8. Immediate local mutation sync makes the product feel reliable; users should not have to refresh the page after editing or deleting a task they just changed.
