# Global Search Module

## Overview

The Global Search module provides a unified, permission-aware search bar embedded in the top navigation bar. Users can search across multiple resource types — Tasks, Emails, Projects, Proposals (Project Expendables), Bills, Invoices, and Navigation Menu items — using either free-text or a hash-prefix shorthand.

All results are filtered based on the currently authenticated user's permissions, so users only ever see resources they are allowed to access.

---

## Architecture

```
User types in search bar
        │
        ▼
GlobalSearch.vue (frontend component)
        │ debounced 300ms, GET /api/global-search?q=...
        ▼
GlobalSearchController.php (backend)
        │ checks permissions per resource type
        │ applies hash prefix OR text search per model
        ▼
Returns JSON: { tasks: [...], emails: [...], projects: [...], ... }
        │
        ▼
GlobalSearch.vue renders grouped dropdown results
```

---

## Files Modified / Created

### Backend

| File | Role |
|------|------|
| `app/Http/Controllers/GlobalSearchController.php` | Core search controller — handles all resource types and menu search |
| `app/Models/Task.php` | Added `getTaskNumberAttribute()` accessor |
| `app/Models/Email.php` | Added `getEmailNumberAttribute()` accessor |
| `app/Models/Project.php` | Added `getProjectNumberAttribute()` accessor |
| `app/Models/ProjectExpendable.php` | Added `getExpendableNumberAttribute()` accessor |
| `app/Models/Bill.php` | Added `getBillNumberAttribute()` accessor |
| `app/Models/Invoice.php` | Added `getInvoiceNumberAttribute()` accessor |
| `routes/api.php` | Registered the `/api/global-search` route |

### Frontend

| File | Role |
|------|------|
| `resources/js/Components/GlobalSearch.vue` | Search bar UI component with debounced API call and results dropdown |
| `resources/js/Components/Layout/TopNavigation.vue` | Imports and renders `<GlobalSearch />` in the header |

---

## API Route

```
GET /api/global-search?q={query}
```

- **Auth required**: Yes (uses `auth:sanctum` or `auth` middleware via the API routes group)
- **Response**: JSON object where each key is a resource category (`tasks`, `emails`, `projects`, `proposals`, `bills`, `invoices`, `menus`)
- **Max results per category**: 5

### Example Response

```json
{
  "tasks": [
    { "id": 42, "title": "OZ42 - Fix login bug", "url": "http://app.test/tasks/42" }
  ],
  "projects": [
    { "id": 7, "title": "OZP7 - Demo Project", "url": "http://app.test/projects/7" }
  ],
  "menus": [
    { "id": "abc123", "title": "Admin > Projects", "url": "http://app.test/projects" }
  ]
}
```

---

## Hash Prefix System

Each resource type has a unique hash prefix. Users can type `#PREFIX` followed by a number to jump directly to that record by its database ID.

| Prefix | Resource | Example | Resolves To |
|--------|----------|---------|-------------|
| `#OZ` | Task | `#OZ42` | Task with `id = 42` |
| `#OZE` | Email | `#OZE10` | Email with `id = 10` |
| `#OZP` | Project | `#OZP7` | Project with `id = 7` |
| `#OZX` | Project Expendable (Proposal) | `#OZX3` | ProjectExpendable with `id = 3` |
| `#OZB` | Bill | `#OZB5` | Bill with `id = 5` |
| `#OZI` | Invoice | `#OZI9` | Invoice with `id = 9` |

The prefix is **case-insensitive** and the `#` character is **optional**. Both `#OZ42` and `oz42` will work.

### Regex Pattern Used (per resource)

```php
// Example for tasks:
preg_match('/^#?OZ(\d+)$/i', $query, $matches)
// If matched: where('id', $matches[1])
// If not matched: LIKE search on text fields
```

---

## Model Accessors (Number Attributes)

Each model exposes a computed `{type}_number` attribute used as the display label in search results and as the prefix identifier.

### Task — `app/Models/Task.php`

```php
protected $appends = ['creator_name', 'total_time_spent', 'formatted_time_spent', 'task_number'];

public function getTaskNumberAttribute(): string
{
    return 'OZ' . $this->id;
}
```

### Email — `app/Models/Email.php`

```php
// In $appends:
'email_number'

public function getEmailNumberAttribute(): string
{
    return 'OZE' . $this->id;
}
```

### Project — `app/Models/Project.php`

```php
public function getProjectNumberAttribute(): string
{
    return 'OZP' . $this->id;
}
```

### ProjectExpendable — `app/Models/ProjectExpendable.php`

```php
public function getExpendableNumberAttribute(): string
{
    return 'OZX' . $this->id;
}
```

### Bill — `app/Models/Bill.php`

```php
public function getBillNumberAttribute(): string
{
    return 'OZB' . $this->id;
}
```

### Invoice — `app/Models/Invoice.php`

```php
// In $appends:
'invoice_number'

public function getInvoiceNumberAttribute(): string
{
    return 'OZI' . $this->id;
}
```

---

## Permission Gates Per Resource

The controller checks permissions before searching each resource. Resources are skipped entirely if the user lacks the required permission.

| Resource | Permission Required | Notes |
|----------|--------------------|-|
| Tasks | *(none — all authenticated users)* | Tasks are always searched |
| Emails | `view_emails` | Loosely enforced — currently all authenticated users can see results |
| Projects | `view_projects` OR `manage_projects` | Either permission grants access |
| Proposals | `view_project_expendable` OR `view_project_expendables_proposals` | Either permission grants access |
| Bills | `view_project_bills` | Strict single-permission check |
| Invoices | `view_project_invoices` | Strict single-permission check |
| Menus | See Menu Permission Rules below | Two-layer check |

### Menu Permission Rules

Menu items in search results go through **two permission checks**:

1. **Admin dropdown gate**: Any menu item prefixed with `Admin >` requires the user to have `view_admin_dropdown`. If they don't, the item is skipped regardless of other permissions.

2. **Specific permission**: Each menu entry has an optional `permission` key. If set and the user lacks that permission, the item is skipped.

```php
// Layer 1: admin dropdown visibility
if (str_starts_with($m['title'], 'Admin > ') && !$user->hasPermission('view_admin_dropdown')) {
    continue;
}

// Layer 2: item-specific permission
if (!empty($m['permission']) && !$user->hasPermission($m['permission'])) {
    continue;
}
```

---

## Menu Search — Full Registry

The following menu items are registered for search in `GlobalSearchController.php`. To **add a new menu item to search**, add an entry to the `$menus` array following the same format.

### Format

```php
[
    'title'      => 'Display label shown in search results',
    'route'      => 'laravel.route.name',  // Use this OR 'url'
    'url'        => '/absolute/path',       // Use this OR 'route'
    'permission' => 'permission_slug',      // null = no permission required
]
```

### Top-Level (No Admin Permission Required)

| Title | Route Name | Permission |
|-------|-----------|------------|
| Dashboard | `dashboard` | none |
| Inbox | `inbox` | none |
| My Workspace | `workspace.index` | none |
| Attendance | `attendance.index` | none |
| Presentations | `presentations.index` | none |
| Bonus System | `bonus-system.index` | none |
| Leaderboard | `leaderboard.index` | none |
| Kudos | `kudos.index` | none |

### Admin Menu Items (Requires `view_admin_dropdown` + specific permission)

| Title | Route/URL | Specific Permission |
|-------|-----------|---------------------|
| Admin > Projects | `projects.index` | `manage_projects` |
| Admin > Project Expendables | `project-expendables.index` | `add_expendables` |
| Admin > Clients | `clients.page` | `create_clients` |
| Admin > Users | `users.page` | `create_users` |
| Admin > Leads | `leads.page` | `manage_projects` |
| Admin > Campaigns | `/campaigns` | `manage_projects` |
| Admin > Productivity Report | `admin.productivity.index` | `manage_projects` |
| Admin > Project Time & Cost Report | `admin.project-time-cost.index` | `manage_projects` |
| Admin > Project Activity Report | `admin.productivity-projects.index` | `manage_projects` |
| Admin > Activity Report | `admin.activity-report.index` | `manage_projects` |
| Admin > User Live Status | `admin.live-status.index` | `manage_projects` |
| Admin > Weekly Availability | `availability.index` | `create_users` |
| Admin > Notice Board | `admin.notice-board.index` | `manage_notices` |
| Admin > Shareable Resources | `shareable-resources.page` | `view_shareable_resources` |
| Admin > Media Files | `admin.media-files.index` | `manage_projects` |
| Admin > Task Types | `task-types.page` | `manage_projects` |
| Admin > Project Tiers | `/admin/project-tiers` | `view_project_tiers` |
| Admin > Email Templates | `email-templates.page` | `manage_email_templates` |
| Admin > Placeholder Definitions | `placeholder-definitions.page` | `manage_placeholder_definitions` |
| Admin > Automation | `automation.page` | `create_automations` |
| Admin > Prompts | `prompts.page` | `create_automations` |
| Admin > Schedules | `schedules.index` | `create_schedules` |
| Admin > Categories | `admin.categories.index` | none |
| Admin > Approval Flows | `admin.approval-flows.index` | none |
| Admin > Financial Dashboard | `admin.financials.dashboard` | `manage_roles` |
| Admin > Contractor Bills | `admin.financials.bills` | `manage_roles` |
| Admin > Sales Invoices | `admin.financials.invoices` | `manage_roles` |
| Admin > Manage Roles | `admin.roles.index` | `manage_roles` |
| Admin > Manage Permissions | `admin.permissions.index` | `assign_permissions` |
| Admin > Email Apps | `admin.email-apps.index` | `manage_roles` |
| Admin > External Tokens | `admin.external-tokens.index` | `manage_roles` |
| Admin > Stripe Configuration | `admin.stripe-configurations.index` | `manage_roles` |
| Admin > Xero Integration | `admin.xero.index` | `manage_roles` |
| Admin > Monthly Budgets | `/admin/monthly-budgets` | `manage_monthly_budgets` |
| Admin > Bonus Calculator | `/admin/bonus-calculator` | `view_monthly_budgets` |

---

## Frontend Component — `GlobalSearch.vue`

**Location**: `resources/js/Components/GlobalSearch.vue`

**Where rendered**: `resources/js/Components/Layout/TopNavigation.vue`

### Behaviour

- **Debounced**: Waits 300ms after the user stops typing before calling the API (lodash `debounce`)
- **Loading spinner**: Shows a spinning `Loader2` icon while the request is in-flight
- **Empty state**: Shows `"No results found for {query}"` if no results returned
- **Click-outside close**: Listens for `document click` events and hides the dropdown when clicking outside
- **Re-open on focus**: If a query is already typed, refocusing the input reopens the dropdown

### Result Display

- Results are grouped by category (Tasks, Emails, Projects, etc.)
- Category labels are title-cased automatically
- Each result is a plain `<a href="...">` link pointing to the resource URL returned by the backend
- Max 5 results per category (enforced server-side)

### Adding a New Category

To surface a new resource type in the dropdown:

1. Add the search logic to `GlobalSearchController::search()` returning `$results['your_key'] = [...]`
2. Each item must have `id`, `title`, and `url` keys
3. The frontend automatically renders any new key from the response — no frontend changes needed

---

## Text Search Fields Per Resource

| Resource | Fields Searched |
|----------|----------------|
| Task | `name`, `id` |
| Email | `subject`, `id` |
| Project | `name`, `id` |
| ProjectExpendable | `name`, `id` |
| Bill | `reference_number`, `id` |
| Invoice | `invoice_number`, `id` |
| Menu | Title label (case-insensitive `stripos`) |

---

## Routing for Result URLs

| Resource | URL Format | Notes |
|----------|-----------|-------|
| Task | `route('tasks.show', $id)` | Opens task detail page |
| Email | `route('emails.show', $id)` | Opens email detail page |
| Project | `route('projects.show', $id)` | Opens project detail page |
| ProjectExpendable | `route('project-expendables.index', ['project_id' => ..., 'search' => ...])` | Opens expendables index pre-filtered to the project + name |
| Bill | `/bills/{id}` | Direct URL |
| Invoice | `/invoices/{id}` | Direct URL |
| Menu | Named route or absolute URL | Resolved via `Route::has()` + `route()` |

---

## How to Extend the Search Module

### Adding a New Resource Type

1. **Create model accessor** (optional, for hash prefix support):
   ```php
   // In app/Models/YourModel.php
   public function getYourModelNumberAttribute(): string
   {
       return 'OZY' . $this->id; // choose a unique prefix
   }
   ```

2. **Add search block in controller** `app/Http/Controllers/GlobalSearchController.php`:
   ```php
   // Search YourModel (Prefix: OZY)
   if ($user->hasPermission('view_your_models')) {
       $query = YourModel::query();
       if (preg_match('/^#?OZY(\d+)$/i', $query, $matches)) {
           $query->where('id', $matches[1]);
       } else {
           $query->where(function($q) use ($query) {
               $q->where('name', 'like', "%{$query}%")
                 ->orWhere('id', 'like', "%{$query}%");
           });
       }
       $items = $query->limit(5)->get();
       if ($items->isNotEmpty()) {
           $results['your_models'] = $items->map(fn($m) => [
               'id'    => $m->id,
               'title' => "{$m->your_model_number} - {$m->name}",
               'url'   => route('your-models.show', $m->id),
           ]);
       }
   }
   ```

3. The frontend will automatically pick up and render the new category — no Vue changes needed.

### Adding a New Menu Item to Search

Add an entry to the `$menus` array in `GlobalSearchController::search()`:

```php
['title' => 'Admin > New Page', 'route' => 'admin.new-page.index', 'permission' => 'required_permission'],
```

> ⚠️ Remember: Any item with a title starting with `Admin > ` automatically requires `view_admin_dropdown` in addition to its specific permission.

---

## Known Considerations

- **Tasks have no permission guard** — all authenticated users can search tasks. Scope filtering (e.g. only tasks on accessible projects) is not applied at the search level; it is assumed Eloquent global scopes or future enhancement will handle this.
- **Email search** currently has `|| true` bypassing the permission check — this was intentional for development but should be reviewed and tightened before production if email visibility needs strict enforcement.
- **Menu items with `url` keys** bypass Laravel's `Route::has()` check; ensure those URLs are stable.
- **Soft deletes**: All model queries automatically respect `deleted_at` (assuming models use `SoftDeletes` trait).
- **Result limit**: Hard-coded at 5 per category, server-side. Change the `->limit(5)` call in the controller to adjust.
