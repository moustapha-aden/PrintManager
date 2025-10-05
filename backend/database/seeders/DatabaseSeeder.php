<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
{
    $this->call([
        CompanySeeder::class,
        DepartmentSeeder::class,
        UserSeeder::class,
        PrinterSeeder::class,
        // InterventionSeeder::class,
        MaterielleSeeder::class,
        // Ajoute d’autres seeders ici...
    ]);
}

}
