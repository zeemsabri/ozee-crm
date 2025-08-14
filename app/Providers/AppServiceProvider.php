<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Email;
use App\Models\Kudo;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\EmailObserver;
use App\Observers\KudoObserver;
use App\Observers\TransactionObserver;
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
        // Register the Email observer
        Email::observe(EmailObserver::class);

        // Register the Transaction observer
        Transaction::observe(TransactionObserver::class);

        // Register the Kudo observer
        Kudo::observe(KudoObserver::class);

        Gate::before(function ($user, $ability) {

            // Ensure $user exists and has the isSuperAdmin method before calling it.
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true; // Grants all permissions to Super Admins
            }

            return null;

        });

        Vite::prefetch(concurrency: 3);
    }
}
