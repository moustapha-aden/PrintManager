<?php

namespace Database\Seeders;

use App\Models\Materielle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MaterielleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
         Materielle::factory()->count(100)->create();
    }
}
