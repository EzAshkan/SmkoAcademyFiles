<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Mode;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/** Who's using the site right now (activity in the last 5 minutes). */
class OnlineNow extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.online-now';

    protected ?string $pollingInterval = '30s';

    protected function getViewData(): array
    {
        $since = now()->subMinutes(5)->timestamp;
        $rows = DB::table('sessions')->where('last_activity', '>=', $since)->get(['user_id', 'last_activity']);
        $ids = $rows->pluck('user_id')->filter()->unique();
        $users = User::whereIn('id', $ids)->get(['id', 'name', 'phone', 'is_admin'])
            ->map(fn ($u) => $u->setAttribute('seen', $rows->where('user_id', $u->id)->max('last_activity')))
            ->sortByDesc('seen')->values();

        return [
            'teachers' => $users->where('is_admin', false)->values(),
            'admins' => $users->where('is_admin', true)->values(),
            'visitors' => $rows->whereNull('user_id')->count(),
            'today' => User::where('is_admin', false)->where('last_login_at', '>=', now()->startOfDay())->count(),
            'public' => Mode::isPublic(),
        ];
    }
}
