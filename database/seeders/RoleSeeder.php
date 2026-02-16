<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name'=>'Manager','slug'=>'manager'],
            ['name'=>'Drawer','slug'=>'drawer'],
            ['name'=>'Checker','slug'=>'checker'],
            ['name'=>'QA','slug'=>'qa'],
            ['name'=>'QA Live','slug'=>'qa_live'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug'=>$role['slug']], $role);
        }
    }
}
