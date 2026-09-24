<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Client;
use App\Models\Department;
use App\Models\Document;
use App\Models\Process;
use App\Models\SerproMonitoring;
use App\Observers\AccountObserver;
use App\Policies\AccountPolicy;
use App\Policies\ClientPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\ProcessPolicy;
use App\Policies\SerproMonitoringPolicy;
use App\Tenant\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Account::observe(AccountObserver::class);

        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(SerproMonitoring::class, SerproMonitoringPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Process::class, ProcessPolicy::class);
    }
}
