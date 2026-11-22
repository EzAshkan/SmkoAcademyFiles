<?php

namespace App\Http\Middleware;

use App\Support\Mode;
use Closure;
use Illuminate\Http\Request;

/**
 * Student library pages: open to everyone in public mode; otherwise the visitor must be
 * logged in and have chosen their own password.
 */
class LibraryAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (Mode::isPublic()) {
            return $next($request);
        }
        if (! $request->user()) {
            return $request->expectsJson() ? abort(401) : redirect()->guest(route('login'));
        }
        if ($request->user()->must_change_password) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
