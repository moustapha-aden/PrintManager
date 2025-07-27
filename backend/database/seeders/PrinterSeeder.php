<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Printer;

class PrinterSeeder extends Seeder
{
    public function run()
    {
        Printer::create([
            'model' => 'LaserJet Pro M404dn',
            'brand' => 'HP',
            'serial' => 'HP123456789',
            'status' => 'active',
            'statusDisplay' => 'Active',
            'company_id' => 1,      // Assure-toi que la company 1 existe
            'department_id' => 1,   // Assure-toi que le department 1 existe
            'installDate' => '2023-01-10',
        ]);

        Printer::create([
            'model' => 'OfficeJet 5255',
            'brand' => 'HP',
            'serial' => 'HP987654321',
            'status' => 'maintenance',
            'statusDisplay' => 'En maintenance',
            'company_id' => 1,
            'department_id' => 2,
            'installDate' => '2022-11-15',
        ]);
    }
}
