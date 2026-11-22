<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Creates (or updates) an admin login — used on the public deployment, which has no student import.
 *   php artisan smko:make-admin 0912xxxxxxx "Your Name"
 * The password is typed in hidden, never passed on the command line.
 */
class MakeAdmin extends Command
{
    protected $signature = 'smko:make-admin {phone : Login number} {name=Admin : Display name}';

    protected $description = 'Create or update an admin account';

    public function handle(): int
    {
        $phone = preg_replace('/\s+/', '', $this->argument('phone'));
        $password = $this->secret('Password (at least 8 characters)');
        if (! $password || mb_strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }
        if ($password !== $this->secret('Repeat password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['phone' => $phone], [
            'name' => $this->argument('name'),
            'password' => $password,
            'is_admin' => true,
            'must_change_password' => false,
        ]);

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." admin {$user->name} ({$user->phone}). Log in at ".url('/login'));

        return self::SUCCESS;
    }
}
