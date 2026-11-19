<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * LOCAL TESTING ONLY — never run on the live server.
 * php artisan db:seed --class=LocalTestAccountsSeeder
 *
 *   test-admin   / smko   (admin)
 *   test-student / smko   (access to "Adults" and "Teens / Timezones 1")
 */
class LocalTestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Refusing to create test accounts in production.');

            return;
        }

        User::updateOrCreate(['phone' => 'test-admin'], [
            'name' => 'Test Admin', 'password' => User::DEFAULT_PASSWORD, 'is_admin' => true, 'must_change_password' => false,
        ]);

        $student = User::updateOrCreate(['phone' => 'test-student'], [
            'name' => 'Test Student', 'password' => User::DEFAULT_PASSWORD, 'is_admin' => false, 'must_change_password' => false,
        ]);

        $folders = Folder::whereNull('parent_id')->where('name', 'Adults')->pluck('id')
            ->merge(Folder::where('name', 'Timezones 1')->pluck('id'));
        $student->folders()->sync($folders);
    }
}
