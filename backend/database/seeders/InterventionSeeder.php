<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Intervention;

class InterventionSeeder extends Seeder
{
    public function run()
    {
        Intervention::create([
            'start_date' => '2025-07-20 08:00:00',
            'end_date' => '2025-07-20 12:00:00',
            'client_id' => 1,         // Utilisateur client existant
            'technician_id' => 2,     // Utilisateur technicien existant
            'printer_id' => 1,        // Imprimante existante
            'status' => 'Terminée',
            'description' => 'Remplacement de la cartouche d’encre.',
            'priority' => 'Haute',
        ]);

        Intervention::create([
            'start_date' => '2025-07-22 09:30:00',
            'end_date' => null,
            'client_id' => 1,
            'technician_id' => 3,
            'printer_id' => 2,
            'status' => 'En Cours',
            'description' => 'Diagnostic du problème de connexion réseau.',
            'priority' => 'Moyenne',
        ]);
    }
}
