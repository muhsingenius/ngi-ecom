<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Send admins to /admin, shop users to /shop and customers to /.
     * An intended URL (e.g. a page the guest was redirected from) is only
     * honoured for customers so staff always land in their own area.
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $home = $user->homePath();

        return $home === '/'
            ? redirect()->intended($home)
            : new RedirectResponse($home);
    }
}
