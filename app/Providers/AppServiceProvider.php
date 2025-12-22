<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Events\DeviceWentOffline;
use App\Events\FirmwareDeploymentFailed;
use App\Events\DiagnosticTestFailed;
use App\Listeners\RaiseDeviceOfflineAlarm;
use App\Listeners\RaiseFirmwareFailureAlarm;
use App\Listeners\RaiseDiagnosticFailureAlarm;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        
        Event::listen(DeviceWentOffline::class, RaiseDeviceOfflineAlarm::class);
        Event::listen(FirmwareDeploymentFailed::class, RaiseFirmwareFailureAlarm::class);
        Event::listen(DiagnosticTestFailed::class, RaiseDiagnosticFailureAlarm::class);
    }
    
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(
                (int) config('acs.rate_limits.api', 60)
            )->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('tr069', function (Request $request) {
            return Limit::perMinute(
                (int) config('acs.rate_limits.tr069', 300)
            )->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(
                (int) config('acs.rate_limits.login', 5)
            )->by($request->ip())->response(function () {
                return response()->json([
                    'error' => 'Too many login attempts',
                    'message' => 'Please try again in a few minutes.',
                ], 429);
            });
        });

        RateLimiter::for('mobile', function (Request $request) {
            return Limit::perMinute(
                (int) config('acs.rate_limits.mobile', 120)
            )->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('websocket', function (Request $request) {
            return Limit::perMinute(
                (int) config('acs.rate_limits.websocket', 60)
            )->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('bulk', function (Request $request) {
            return Limit::perMinute(
                (int) config('acs.rate_limits.bulk', 10)
            )->by($request->user()?->id ?: $request->ip());
        });
    }
}
