<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Email;
use App\Models\Project;
use App\Models\User;
use App\Policies\ClientPolicy;
use App\Policies\EmailPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    protected $policies = [
//        Client::class => ClientPolicy::class,
        Project::class => ProjectPolicy::class,
        User::class => UserPolicy::class,
        Email::class => EmailPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Gate::before(function ($user, $ability) {
            // These logs are crucial for debugging. Keep them for now.
            Log::info('Gate::before check (from AppServiceProvider):', [
                'user_id' => $user->id ?? 'N/A (guest)',
                'role' => $user->role ?? 'N/A (guest)',
                'is_super_admin_method_result' => ($user && method_exists($user, 'isSuperAdmin')) ? $user->isSuperAdmin() : 'N/A (method missing/guest)',
                'ability' => $ability
            ]);

            // Ensure $user exists and has the isSuperAdmin method before calling it.
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                Log::info('Gate::before - Super Admin bypass activated (from AppServiceProvider).');
                return true; // Grants all permissions to Super Admins
            }
            Log::info('Gate::before - No Super Admin bypass (from AppServiceProvider). Policy will be checked.');

            return null;

        });

        Vite::prefetch(concurrency: 3);
    }
}
