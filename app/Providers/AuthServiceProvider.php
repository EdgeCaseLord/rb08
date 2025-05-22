<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Policies\PatientPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => PatientPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
