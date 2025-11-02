<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Project;
use App\Policies\ClientPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Client::class => ClientPolicy::class,
        Project::class => ProjectPolicy::class,
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Email::class => \App\Policies\EmailPolicy::class,
        \App\Models\Kudo::class => \App\Policies\KudosPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Implicitly grant "super_admin" all permissions
        // This is a powerful backdoor, use carefully.
        // If the user has the 'super_admin' role, they can perform any action.
        Gate::before(function ($user, $ability) {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
