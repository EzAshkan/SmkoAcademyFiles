<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** Who changed what in the admin panel. */
class AdminLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'summary'];

    public static function add(string $action, string $subjectType, ?int $subjectId, string $summary): void
    {
        // Only record changes made by a signed-in admin (not imports or console commands).
        $user = Auth::user();
        if (! $user?->is_admin || app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }
        static::create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => mb_substr($summary, 0, 500),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
