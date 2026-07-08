<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'owner', 'description' => 'First user who registers. Master access to everything.'],
            ['name' => 'manager', 'description' => 'Full operational access except subscription changes.'],
            ['name' => 'accountant', 'description' => 'Financial/TVA reporting, closing periods, and finalizing access.'],
            ['name' => 'viewer', 'description' => 'Read-only access to permitted data screens.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}