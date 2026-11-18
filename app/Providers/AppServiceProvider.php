<?php

namespace App\Providers;

use App\Domain\Governance\ApprovalRepository;
use App\Domain\Governance\CurrentApprovalAuthority;
use App\Domain\Identifiers\SystemIdIssuer;
use App\Domain\Registration\RegistrationActivationAuthority;
use App\Domain\Security\SecurityStateAuthority;
use App\Infrastructure\Governance\MongoFeatureGateRepository;
use App\Infrastructure\Governance\UnavailableApprovalAuthority;
use App\Infrastructure\Identity\UnavailableSystemIdIssuer;
use App\Infrastructure\Registration\UnavailableRegistrationActivationAuthority;
use App\Infrastructure\Security\AccessDecisionAuditRecorder;
use App\Infrastructure\Security\MongoAuthorizationRepository;
use App\Infrastructure\Security\UnavailableSecurityStateAuthority;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SecurityStateAuthority::class, UnavailableSecurityStateAuthority::class);
        $this->app->bind(CurrentApprovalAuthority::class, UnavailableApprovalAuthority::class);
        $this->app->bind(ApprovalRepository::class, fn ($app) => new MongoFeatureGateRepository($app['db']->connection('mongodb')->getDatabase()));
        $this->app->bind(SystemIdIssuer::class, UnavailableSystemIdIssuer::class);
        $this->app->bind(RegistrationActivationAuthority::class, UnavailableRegistrationActivationAuthority::class);
        $this->app->bind(MongoAuthorizationRepository::class, fn ($app) => new MongoAuthorizationRepository($app['db']->connection('mongodb')->getDatabase()));
        $this->app->bind(AccessDecisionAuditRecorder::class, fn ($app) => new AccessDecisionAuditRecorder($app['db']->connection('mongodb')->getDatabase()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // This first-party foundation never falls back to personal bearer-token authentication.
        Sanctum::getAccessTokenFromRequestUsing(static fn () => null);
    }
}
