<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CaptureUserTimezone
{
    /**
     * Keep the logged-in user's timezone (as detected by their browser, see
     * resources/js/bootstrap.js) in sync on the users table, so server-side
     * rendering (PDF/Excel exports) can convert UTC timestamps correctly.
     * The cookie lags one request behind a fresh login/registration, which
     * is fine - by the time anyone reaches an export button they've already
     * loaded at least one authenticated page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $cookieTimezone = $request->cookie('timezone');

        if ($user && $cookieTimezone && $cookieTimezone !== $user->timezone) {
            if (in_array($cookieTimezone, \DateTimeZone::listIdentifiers(), true)) {
                $user->update(['timezone' => $cookieTimezone]);
            }
        }

        return $next($request);
    }
}
