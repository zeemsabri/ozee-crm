<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Email;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectExpendable;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');
        $user = $request->user();
        $accessibleProjectIds = $this->getAccessibleProjectIds($user);
        $hasGlobalProjectAccess = $accessibleProjectIds === null;

        if (!$query) {
            return response()->json([]);
        }

        $results = [];

        // 1. Search Tasks (Prefix: OZ)
        $tasksQuery = Task::query();
        if (!$hasGlobalProjectAccess) {
            if ($accessibleProjectIds->isEmpty()) {
                $tasksQuery->whereRaw('1 = 0');
            } else {
                $tasksQuery->whereHas('milestone', function ($milestoneQuery) use ($accessibleProjectIds) {
                    $milestoneQuery->whereIn('project_id', $accessibleProjectIds->all());
                });
            }
        }
        if (preg_match('/^#?OZ(\d+)$/i', $query, $matches)) {
            $tasksQuery->where('id', $matches[1]);
        } else {
            $tasksQuery->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('id', 'like', "%{$query}%");
            });
        }
        $tasks = $tasksQuery->with('milestone')->limit(5)->get();
        if ($tasks->isNotEmpty()) {
            $results['tasks'] = $tasks->map(fn($t) => [
                'id' => $t->id,
                'title' => "{$t->task_number} - {$t->name}",
                'project_id' => $t->project_id,
                'url' => route('projects.show', $t->project_id),
            ]);
        }

        // 2. Search Emails (Prefix: OZE)
        {
            $emailsQuery = Email::query();
            if (preg_match('/^#?OZE(\d+)$/i', $query, $matches)) {
                $emailsQuery->where('id', $matches[1]);
            } else {
                $emailsQuery->where(function($q) use ($query) {
                    $q->where('subject', 'like', "%{$query}%")
                      ->orWhere('id', 'like', "%{$query}%");
                });
            }

            if (!$hasGlobalProjectAccess) {
                $emailsQuery->where(function ($q) use ($accessibleProjectIds, $user) {
                    if ($accessibleProjectIds->isNotEmpty()) {
                        $q->whereHas('conversation', function ($conversationQuery) use ($accessibleProjectIds) {
                            $conversationQuery->whereIn('project_id', $accessibleProjectIds->all());
                        });
                    }

                    // Keep sender-owned emails eligible for policy filtering.
                    $q->orWhere('sender_id', $user->id);
                });
            }

            $emails = $emailsQuery
                ->with(['sender', 'conversation'])
                ->limit(50)
                ->get()
                ->filter(fn($email) => $user->can('approveOrView', $email))
                ->take(5)
                ->values();

            if ($emails->isNotEmpty()) {
                $results['emails'] = $emails->map(fn($e) => [
                    'id' => $e->id,
                    'title' => "{$e->email_number} - {$e->subject}",
                    'subject' => $e->subject,
                    'type' => $e->type,
                    'created_at' => $e->created_at,
                    'sender' => $e->sender,
                    'recipient_email' => $e->recipient_email,
                    'url' => route('inbox', ['open_email' => $e->id]),
                ]);
            }
        }

        // 3. Search Projects (Prefix: OZP)
        if ($hasGlobalProjectAccess || $accessibleProjectIds->isNotEmpty()) {
            $projectsQuery = Project::query();
            if (!$hasGlobalProjectAccess) {
                $projectsQuery->whereIn('id', $accessibleProjectIds->all());
            }
            if (preg_match('/^#?OZP(\d+)$/i', $query, $matches)) {
                $projectsQuery->where('id', $matches[1]);
            } else {
                $projectsQuery->where(function($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('id', 'like', "%{$query}%");
                });
            }
            $projects = $projectsQuery->limit(5)->get();
            if ($projects->isNotEmpty()) {
                $results['projects'] = $projects->map(fn($p) => ['id' => $p->id, 'title' => "{$p->project_number} - {$p->name}", 'url' => route('projects.show', $p->id)]);
            }
        }

        // 4. Search Proposals / ProjectExpendable (Prefix: OZX)
        if ($user->hasPermission('view_project_expendable') || $user->hasPermission('view_project_expendables_proposals')) {
            $proposalsQuery = ProjectExpendable::query();
            if (!$hasGlobalProjectAccess) {
                if ($accessibleProjectIds->isEmpty()) {
                    $proposalsQuery->whereRaw('1 = 0');
                } else {
                    $proposalsQuery->whereIn('project_id', $accessibleProjectIds->all());
                }
            }
            if (preg_match('/^#?OZX(\d+)$/i', $query, $matches)) {
                $proposalsQuery->where('id', $matches[1]);
            } else {
                $proposalsQuery->where(function($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('id', 'like', "%{$query}%");
                });
            }
            $proposals = $proposalsQuery->limit(5)->get();
            if ($proposals->isNotEmpty()) {
                $results['proposals'] = $proposals->map(fn($p) => ['id' => $p->id, 'title' => "{$p->expendable_number} - {$p->name}", 'url' => route('project-expendables.index', ['project_id' => $p->project_id, 'search' => $p->name])]);
            }
        }

        // 5. Search Bills (Prefix: OZB)
        if ($user->hasPermission('view_project_bills')) {
            $billsQuery = Bill::query();
            if (!$hasGlobalProjectAccess) {
                if ($accessibleProjectIds->isEmpty()) {
                    $billsQuery->whereRaw('1 = 0');
                } else {
                    $billsQuery->whereIn('project_id', $accessibleProjectIds->all());
                }
            }
            if (preg_match('/^#?OZB(\d+)$/i', $query, $matches)) {
                $billsQuery->where('id', $matches[1]);
            } else {
                $billsQuery->where(function($q) use ($query) {
                    $q->where('reference_number', 'like', "%{$query}%")
                      ->orWhere('id', 'like', "%{$query}%");
                });
            }
            $bills = $billsQuery->limit(5)->get();
            if ($bills->isNotEmpty()) {
                $results['bills'] = $bills->map(fn($b) => ['id' => $b->id, 'title' => "{$b->bill_number} - " . ($b->reference_number ?? 'No Ref'), 'url' => "/bills/{$b->id}"]);
            }
        }

        // 6. Search Invoices (Prefix: OZI)
        if ($user->hasPermission('view_project_invoices')) {
            $invoicesQuery = Invoice::query();
            if (!$hasGlobalProjectAccess) {
                if ($accessibleProjectIds->isEmpty()) {
                    $invoicesQuery->whereRaw('1 = 0');
                } else {
                    $invoicesQuery->whereIn('project_id', $accessibleProjectIds->all());
                }
            }
            if (preg_match('/^#?OZI(\d+)$/i', $query, $matches)) {
                $invoicesQuery->where('id', $matches[1]);
            } else {
                $invoicesQuery->where(function($q) use ($query) {
                    $q->where('invoice_number', 'like', "%{$query}%")
                      ->orWhere('id', 'like', "%{$query}%");
                });
            }
            $invoices = $invoicesQuery->limit(5)->get();
            if ($invoices->isNotEmpty()) {
                $results['invoices'] = $invoices->map(fn($i) => ['id' => $i->id, 'title' => "{$i->invoice_number} - " . ($i->invoice_number ?? 'No Inv Num'), 'url' => "/invoices/{$i->id}"]);
            }
        }

        // 7. Search Menus
        $menus = [
            ['title' => 'Dashboard', 'route' => 'dashboard', 'permission' => null],
            ['title' => 'Inbox', 'route' => 'inbox', 'permission' => null],
            ['title' => 'My Workspace', 'route' => 'workspace.index', 'permission' => null],
            ['title' => 'Attendance', 'route' => 'attendance.index', 'permission' => null],
            ['title' => 'Presentations', 'route' => 'presentations.index', 'permission' => null],
            ['title' => 'Bonus System', 'route' => 'bonus-system.index', 'permission' => null],
            ['title' => 'Leaderboard', 'route' => 'leaderboard.index', 'permission' => null],
            ['title' => 'Kudos', 'route' => 'kudos.index', 'permission' => null],
            
            ['title' => 'Admin > Projects', 'route' => 'projects.index', 'permission' => 'manage_projects'],
            ['title' => 'Admin > Project Expendables', 'route' => 'project-expendables.index', 'permission' => 'add_expendables'],
            ['title' => 'Admin > Clients', 'route' => 'clients.page', 'permission' => 'create_clients'],
            ['title' => 'Admin > Users', 'route' => 'users.page', 'permission' => 'create_users'],
            ['title' => 'Admin > Leads', 'route' => 'leads.page', 'permission' => 'manage_projects'],
            ['title' => 'Admin > Campaigns', 'url' => '/campaigns', 'permission' => 'manage_projects'],
            ['title' => 'Admin > Productivity Report', 'route' => 'admin.productivity.index', 'permission' => 'manage_projects'],
            ['title' => 'Admin > Project Time & Cost Report', 'route' => 'admin.project-time-cost.index', 'permission' => 'manage_projects'],
            ['title' => 'Admin > Project Activity Report', 'route' => 'admin.productivity-projects.index', 'permission' => 'manage_projects'],
            ['title' => 'Admin > Activity Report', 'route' => 'admin.activity-report.index', 'permission' => 'manage_projects'],
            ['title' => 'Admin > User Live Status', 'route' => 'admin.live-status.index', 'permission' => 'manage_projects'],
            ['title' => 'Admin > Weekly Availability', 'route' => 'availability.index', 'permission' => 'create_users'],
            ['title' => 'Admin > Notice Board', 'route' => 'admin.notice-board.index', 'permission' => 'manage_notices'],
            ['title' => 'Admin > Shareable Resources', 'route' => 'shareable-resources.page', 'permission' => 'view_shareable_resources'],
            ['title' => 'Admin > Media Files', 'route' => 'admin.media-files.index', 'permission' => 'manage_projects'],
            ['title' => 'Admin > Task Types', 'route' => 'task-types.page', 'permission' => 'manage_projects'],
            ['title' => 'Admin > Project Tiers', 'url' => '/admin/project-tiers', 'permission' => 'view_project_tiers'],
            ['title' => 'Admin > Email Templates', 'route' => 'email-templates.page', 'permission' => 'manage_email_templates'],
            ['title' => 'Admin > Placeholder Definitions', 'route' => 'placeholder-definitions.page', 'permission' => 'manage_placeholder_definitions'],
            ['title' => 'Admin > Automation', 'route' => 'automation.page', 'permission' => 'create_automations'],
            ['title' => 'Admin > Prompts', 'route' => 'prompts.page', 'permission' => 'create_automations'],
            ['title' => 'Admin > Schedules', 'route' => 'schedules.index', 'permission' => 'create_schedules'],
            ['title' => 'Admin > Categories', 'route' => 'admin.categories.index', 'permission' => null], 
            ['title' => 'Admin > Approval Flows', 'route' => 'admin.approval-flows.index', 'permission' => null],
            ['title' => 'Admin > Financial Dashboard', 'route' => 'admin.financials.dashboard', 'permission' => 'manage_roles'],
            ['title' => 'Admin > Contractor Bills', 'route' => 'admin.financials.bills', 'permission' => 'manage_roles'],
            ['title' => 'Admin > Sales Invoices', 'route' => 'admin.financials.invoices', 'permission' => 'manage_roles'],
            ['title' => 'Admin > Manage Roles', 'route' => 'admin.roles.index', 'permission' => 'manage_roles'],
            ['title' => 'Admin > Manage Permissions', 'route' => 'admin.permissions.index', 'permission' => 'assign_permissions'],
            ['title' => 'Admin > Email Apps', 'route' => 'admin.email-apps.index', 'permission' => 'manage_roles'],
            ['title' => 'Admin > External Tokens', 'route' => 'admin.external-tokens.index', 'permission' => 'manage_roles'],
            ['title' => 'Admin > Stripe Configuration', 'route' => 'admin.stripe-configurations.index', 'permission' => 'manage_roles'],
            ['title' => 'Admin > Xero Integration', 'route' => 'admin.xero.index', 'permission' => 'manage_roles'],
            ['title' => 'Admin > Monthly Budgets', 'url' => '/admin/monthly-budgets', 'permission' => 'manage_monthly_budgets'],
            ['title' => 'Admin > Bonus Calculator', 'url' => '/admin/bonus-calculator', 'permission' => 'view_monthly_budgets'],
        ];

        $matchedMenus = [];
        foreach ($menus as $m) {
            if (stripos($m['title'], $query) !== false) {
                // If it's an admin menu, enforce view_admin_dropdown
                if (str_starts_with($m['title'], 'Admin > ') && !$user->hasPermission('view_admin_dropdown')) {
                    continue;
                }
                
                // Enforce specific permission if set
                if (!empty($m['permission']) && !$user->hasPermission($m['permission'])) {
                    continue;
                }

                $url = $m['url'] ?? (\Illuminate\Support\Facades\Route::has($m['route']) ? route($m['route']) : null);
                if ($url) {
                    $matchedMenus[] = [
                        'id' => md5($m['title']),
                        'title' => $m['title'],
                        'url' => $url
                    ];
                }
            }
        }
        
        if (!empty($matchedMenus)) {
            $results['menus'] = array_slice($matchedMenus, 0, 5);
        }

        return response()->json($results);
    }

    /**
     * Resolve project IDs accessible to the user based on ProjectPolicy::view semantics.
     *
     * Returns null when user has global project visibility.
     */
    private function getAccessibleProjectIds(User $user): ?Collection
    {
        if ($user->hasPermission('view_all_projects')) {
            return null;
        }

        if ($user->hasPermission('view_projects') && !$user->isContractor()) {
            return null;
        }

        $projectIdsWithProjectViewPermission = DB::table('project_user as pu')
            ->join('role_permission as rp', 'pu.role_id', '=', 'rp.role_id')
            ->join('permissions as p', 'rp.permission_id', '=', 'p.id')
            ->where('pu.user_id', $user->id)
            ->where('p.slug', 'view_projects')
            ->pluck('pu.project_id');

        if ($user->isContractor() && $user->hasPermission('view_projects')) {
            $assignedProjectIds = DB::table('project_user')
                ->where('user_id', $user->id)
                ->pluck('project_id');

            return $projectIdsWithProjectViewPermission
                ->merge($assignedProjectIds)
                ->unique()
                ->values();
        }

        return $projectIdsWithProjectViewPermission
            ->unique()
            ->values();
    }
}
