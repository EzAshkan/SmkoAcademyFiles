<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Admin panel: an admin still on the starting password is sent to "choose your password"
 * instead of getting a 403 (the panel only admits admins who have their own password).
 */
class AdminMustChoosePassword
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->must_change_password) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
