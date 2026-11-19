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
            $view->with('banners', $user
                ? Announcement::query()->activeBanners()->limit(10)->get()->filter(fn (Announcement $a) => $a->isFor($user))->take(3)->values()
                : collect());
        });

        AdminActivity::register();
    }
}
