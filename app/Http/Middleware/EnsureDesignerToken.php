<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guests own their designs and uploads through a random token kept in the session
 * (it survives the session-ID change at login, so a guest can sign in and keep their work).
 */
class EnsureDesignerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('designer_token')) {
            $request->session()->put('designer_token', (string) Str::uuid());
        }

        return $next($request);
    }
}
