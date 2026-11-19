<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use Notifiable;

    /** Starting password for imported / reset accounts (decided by SMKO). */
    public const DEFAULT_PASSWORD = 'smko';

    protected $fillable = [
        'name',
        'phone',
        'telegram_id',
        'password',
        'is_admin',
        'must_change_password',
        'last_login_at',
        'login_count',
        'telegram_notifications',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'telegram_notifications' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(Folder::class)->withPivot('expires_at', 'granted_at');
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AssetEvent::class);
    }

    /** Telegram chat to message, if the student has one. */
    public function telegramChatId(): ?string
    {
        $id = trim((string) ($this->telegram_id ?: $this->phone));

        return preg_match('/^\d{5,15}$/', $id) ? $id : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin && ! $this->must_change_password;
    }

    /** Put the account back on the shared starting password; user must pick a new one at next login. */
    public function resetToDefaultPassword(): void
    {
        $this->forceFill([
            'password' => self::DEFAULT_PASSWORD,
            'must_change_password' => true,
        ])->save();
    }
}
