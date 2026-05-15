<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        $this->configureRateLimiting();
    }

    /**
     * Named rate limiters for public, unauthenticated endpoints.
     *
     * public-tracking: per-(IP + outlet + order) limit — keeps a legitimate
     * customer refreshing their own order page well under the cap while
     * stopping brute-force scans of order codes from one IP.
     *
     * public-tracking-ip: coarser per-IP cap that applies regardless of
     * which outlet/order is requested, so an attacker can't sidestep the
     * per-resource limit by rotating order codes.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('public-tracking', function (Request $request) {
            $outletId  = (string) $request->route('outletId');
            $orderCode = (string) $request->route('orderCode');

            return [
                Limit::perMinute(30)->by($request->ip().'|'.$outletId.'|'.$orderCode),
                Limit::perMinute(120)->by($request->ip()),
            ];
        });

        RateLimiter::for('public-membership', function (Request $request) {
            $slug = (string) $request->route('outletSlug');

            return [
                Limit::perMinute(20)->by($request->ip().'|'.$slug),
                Limit::perMinute(60)->by($request->ip()),
            ];
        });
    }
}
