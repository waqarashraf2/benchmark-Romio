<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class ManagerSeeder extends Seeder
{
    public function run(): void
    {
        $managerRole = Role::where('slug', 'manager')->first();

        if (!$managerRole) {
            $this->command->error('Manager role not found. Run RoleSeeder first.');
            return;
        }

        User::updateOrCreate(
            ['email' => 'manager@romio.com'],
            [
                'name' => 'System Manager',
                'password' => Hash::make('12345678'),
                'role_id' => $managerRole->id,
                'active' => true,
            ]
        );
    }
}
