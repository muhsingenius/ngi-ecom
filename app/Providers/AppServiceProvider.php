<?php

namespace App\Providers;

use App\Models\CartLine;
use App\Models\Design;
use App\Models\DesignAsset;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        // Image uploads and design saves are the expensive endpoints.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('designs', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        // A guest who signs in keeps the designs and uploads they made before logging in.
        Event::listen(Login::class, function (Login $event) {
            $token = session('designer_token');

            if (! $token) {
                return;
            }

            foreach ([Design::class, DesignAsset::class, CartLine::class] as $model) {
                $model::query()->whereNull('user_id')->where('session_token', $token)
                    ->update(['user_id' => $event->user->getAuthIdentifier(), 'session_token' => null]);
            }
        });
    }
}
