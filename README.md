<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

# Scheduler Module

This application includes a flexible scheduler module that can schedule any Eloquent model (Task, Workflow, etc.) using a polymorphic relation.

Highlights:
- Polymorphic schedules table linking to any model via scheduled_item morph.
- Cron-expression based recurrence (uses dragonmantank/cron-expression).
- Single Artisan command to run via cron: app:run-scheduler.
- Queued execution via RunScheduledItem job.
- Future-proof contract App\Contracts\SchedulableAction for custom executors.

## Database
Run migrations:

php artisan migrate

This creates the schedules table.

## Usage

- For Tasks and Workflows, you can attach schedules via relation:

// Task example
$task->schedules()->create([
    'name' => 'Daily reminder',
    'start_at' => now(),
    'recurrence_pattern' => '0 9 * * *', // every day at 09:00
    'is_active' => true,
]);

// Workflow example
$workflow->schedules()->create([
    'name' => 'Weekly run',
    'start_at' => now(),
    'recurrence_pattern' => '0 8 * * 1-5', // weekdays at 08:00
    'is_active' => true,
]);

If the target model implements App\Contracts\SchedulableAction::runScheduled(), that will be called. Otherwise, the job falls back to runScheduled(), run(), execute(), or for Workflows dispatches the existing RunWorkflowJob.

## Running

You can use ONE cron entry on the server:

* * * * * php /path/to/artisan app:run-scheduler --quiet

Alternatively, if you're already using Laravel's scheduler with schedule:run, the command is registered in Console/Kernel.php to run every minute.

## Notes
- One-time schedules: set is_onetime = true; they will deactivate after first run.
- last_run_at updates after each execution and is used to avoid re-running within the same minute.
- next_run_at is available as an accessor on the Schedule model (computed).

## UI
- Navigate to Schedules: /schedules (requires auth). Create new via the Create Schedule button.
- From Automation > Workflows list, use the “Schedule” link on a workflow to open the create form pre-filled (type=workflow&id=<workflowId>).
- The Create form is a simple 3-step wizard: Basic Details, Recurrence (Once/Daily/Weekly/Monthly/Yearly or custom cron), and Confirmation with a human-readable summary.
- You can also open /schedules/create?type=task&id=<taskId> to prefill for a Task.

## Tasks: Parent/Child (Subtask) behavior
- The `tasks` table now has a `parent_id` that can reference another task.
- When a Schedule is linked to a Task and becomes due, the scheduler creates a new child Task using the parent as a template (keeps task type, milestone, assignee; sets status to "To Do" and parent_id to the original task).
- Frontend remains unchanged; subtasks are just Tasks with `parent_id` set. The legacy `Subtask` model can be ignored in the UI.
