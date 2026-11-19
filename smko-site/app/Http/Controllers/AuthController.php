<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
        ]);

        $phone = preg_replace('/\s+/', '', $data['phone']);
        // 5 tries per minute per number, and 30 per minute per IP address.
        $keys = ['login:phone:'.Str::lower($phone), 'login:ip:'.$request->ip()];
        foreach ([[$keys[0], 5], [$keys[1], 30]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    'phone' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
                ]);
            }
        }

        if (! Auth::attempt(['phone' => $phone, 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($keys[0]);
            RateLimiter::hit($keys[1]);

            throw ValidationException::withMessages(['phone' => 'Wrong number or password.']);
        }

        RateLimiter::clear($keys[0]);
        $request->user()->forceFill(['last_login_at' => now(), 'login_count' => $request->user()->login_count + 1])->saveQuietly();
        $request->session()->regenerate();

        return redirect()->intended(route('library.home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showPassword(Request $request)
    {
        return view('auth.password', ['forced' => $request->user()->must_change_password]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => [$user->must_change_password ? 'nullable' : 'required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if (Hash::check($request->password, $user->password) || Str::lower($request->password) === User::DEFAULT_PASSWORD) {
            throw ValidationException::withMessages(['password' => 'Choose a different password from your current one.']);
        }

        $user->forceFill([
            'password' => $request->password,
            'must_change_password' => false,
        ])->save();

        return redirect()->route('library.home')->with('status', 'Your password has been changed.');
    }
}
