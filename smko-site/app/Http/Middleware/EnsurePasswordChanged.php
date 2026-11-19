<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Accounts still on the shared starting password must choose their own before anything else. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->must_change_password) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
