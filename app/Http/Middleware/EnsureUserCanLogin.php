<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account that cannot log in.
 *
 * The login form refuses these accounts (FortifyServiceProvider), but a session
 * or remember-me cookie from before that check, or from before the account was
 * turned into a dependent, would otherwise stay signed in. An admin
 * impersonating a dependent is left alone — that session is the admin's.
 */
class EnsureUserCanLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->can_login && ! app('impersonate')->isImpersonating()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account cannot log in. Ask the family member who manages it to sign in instead.']);
        }

        return $next($request);
    }
}
