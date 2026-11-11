<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Seeds ONE administrator and nothing else. The admin creates every other
     * account (guards, more admins) from the Users page.
     *
     * No password lives in the repository: a random one is generated and printed
     * once. An existing account is never touched, so re-running db:seed cannot
     * reset a password that was changed after the first seed.
     */
    public function run(): void
    {
        // Bootstrap only: if ANY admin exists (even with a changed email), do nothing.
        if (User::where('role', 'admin')->exists()) {
            $this->command->info('An admin account already exists; seeder left it unchanged.');

            return;
        }

        $admin = new User(['email' => 'admin@lsb.local']);

        $password = Str::password(20, symbols: false);

        $admin->fill([
            'name' => 'Admin Account',
            'password' => $password,   // 'hashed' cast
            'role' => 'admin',
            'hrep_id' => '00012',
        ])->save();

        $this->command->warn("Admin created. HREP ID: 0012  Password: {$password}");
        $this->command->warn('Shown once. Sign in, change it, then create all other accounts from the Users page.');
    }
}