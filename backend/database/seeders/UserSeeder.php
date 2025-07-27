<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'roleDisplay' => 'Administrateur',
            'status' => 'active',
            'statusDisplay' => 'Actif',
            'company_id' => 1,
            'department_id' => 1,
            'requestsHandled' => 'N/A',
        ]);
    }
}
