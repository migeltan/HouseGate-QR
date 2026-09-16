<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Safe to re-run — uses updateOrCreate keyed on email, matching the
     * pattern DatabaseSeeder already uses for buildings/passes.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@lsb.local'],
            [
                'name' => 'Admin Account',
                'password' => 'changeme123',
                'role' => 'admin',
                'hrep_id' => 'HREP-2020-0012',
            ]
        );

        User::updateOrCreate(
            ['email' => 'guard@lsb.local'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => 'changeme123',
                'role' => 'guard',
                'hrep_id' => 'HREP-2024-0451',
            ]
        );

        $this->command->info('Seeded admin@lsb.local and guard@lsb.local (password: changeme123 for both).');
    }
}