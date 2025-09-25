<?php

namespace App\Http\Controllers;
// Fichier : app/Http/Controllers/QuotaController.php (exemple)
use App\Models\PrinterQuota;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotaController extends Controller
{
    // ... (autres méthodes du QuotaController)

    public function generateSingleQuotaReport(PrinterQuota $quota)
    {
        // 1. Charger les relations de l'imprimante associée au quota
        // Assurez-vous que les relations sont définies dans le modèle PrinterQuota
        $quota->load('printer.company', 'printer.department');

        // 2. Préparer les données
        $data = [
            'quota' => $quota, // On passe un seul objet quota
            'printer' => $quota->printer, // On passe l'imprimante pour les détails
        ];

        // 3. Utiliser une vue adaptée à un seul quota (ou adapter l'existante)
        $pdf = Pdf::loadView('reports.single_quota_report', $data);

        // 4. Retourner le PDF
        return $pdf->download('rapport_quota_' . $quota->id . '.pdf');
    }
}
