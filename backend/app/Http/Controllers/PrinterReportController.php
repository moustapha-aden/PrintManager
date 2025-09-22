<?php

namespace App\Http\Controllers;

use App\Models\Printer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PrinterReportController extends Controller
{
    /**
     * Génère un rapport de production PDF pour une imprimante donnée.
     *
     * @param Printer $printer
     * @return \Illuminate\Http\Response
     */
    public function generateReport(Printer $printer)
    {
        // 1. Charger les relations nécessaires (quotas, compagnie, département)
        $printer->load('quotas', 'company', 'department');

        // 2. Préparer les données pour la vue du rapport
        $data = [
            'printer' => $printer,
        ];

        // 3. Générer le PDF à partir d'une vue Blade
        $pdf = Pdf::loadView('reports.printer_report', $data);

        // 4. Retourner le PDF pour le téléchargement
        return $pdf->download('rapport_production_imprimante_' . $printer->serial . '.pdf');
    }
}
