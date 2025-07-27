<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run()
    {
        Department::create([
            'name' => 'Informatique',
            'company_id' => 1, // Assure-toi que la société 1 existe
        ]);

        Department::create([
            'name' => 'Ressources Humaines',
            'company_id' => 1,
        ]);
    }
}
