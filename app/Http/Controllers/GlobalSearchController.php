<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Email;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectExpendable;
use App\Models\Task;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');
        $user = $request->user();

        if (!$query) {
            return response()->json([]);
        }

        $results = [];

        // 1. Search Tasks (Prefix: OZ)
        $tasksQuery = Task::query();
        if (preg_match('/^#?OZ(\d+)$/i', $query, $matches)) {
            $tasksQuery->where('id', $matches[1]);
        } else {
            $tasksQuery->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('id', 'like', "%{$query}%");
            });
        }
        $tasks = $tasksQuery->limit(5)->get();
        if ($tasks->isNotEmpty()) {
            $results['tasks'] = $tasks->map(fn($t) => ['id' => $t->id, 'title' => "{$t->task_number} - {$t->name}", 'url' => route('tasks.show', $t->id)]);
        }

        // 2. Search Emails (Prefix: OZE)
        if ($user->hasPermission('view_emails') || true) {
            $emailsQuery = Email::query();
            if (preg_match('/^#?OZE(\d+)$/i', $query, $matches)) {
                $emailsQuery->where('id', $matches[1]);
            } else {
                $emailsQuery->where(function($q) use ($query) {
                    $q->where('subject', 'like', "%{$query}%")
                      ->orWhere('id', 'like', "%{$query}%");
                });
            }
            $emails = $emailsQuery->limit(5)->get();
            if ($emails->isNotEmpty()) {
                $results['emails'] = $emails->map(fn($e) => ['id' => $e->id, 'title' => "{$e->email_number} - {$e->subject}", 'url' => route('emails.show', $e->id ?? 0)]);
            }
        }

        // 3. Search Projects (Prefix: OZP)
        if ($user->hasPermission('view_projects') || $user->hasPermission('manage_projects')) {
            $projectsQuery = Project::query();
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

        return response()->json($results);
    }
}
