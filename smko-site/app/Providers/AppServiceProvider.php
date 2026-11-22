<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Support\AdminActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Announcement banners for the signed-in student (student pages use layouts.app).
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            $all = fn () => Announcement::query()->activeBanners()->limit(10)->get();
            $view->with('banners', match (true) {
                (bool) $user => $all()->filter(fn (Announcement $a) => $a->isFor($user))->take(3)->values(),
                // Visitors without an account (public mode): only announcements meant for everyone.
                \App\Support\Mode::isPublic() => $all()->filter(fn (Announcement $a) => $a->isForEveryone())->take(3)->values(),
                default => collect(),
            });
        });

        AdminActivity::register();
    }
}
