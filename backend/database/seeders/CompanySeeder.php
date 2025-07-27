<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run()
    {
        Company::create([
            'name' => 'Etraffic Djibouti',
        ]);

        Company::create([
            'name' => 'Acme Corporation',
        ]);

        // Ajoute d’autres sociétés ici...
    }
}
