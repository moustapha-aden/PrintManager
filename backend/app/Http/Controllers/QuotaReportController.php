<?php

namespace App\Http\Controllers;

use App\Models\PrinterQuota;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuotaReportController extends Controller
{
    public function generateGroupReport(Request $request)
    {
        Log::info('Début génération rapport global', ['request' => $request->all()]);

        // 1️⃣ Validation
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'company_id' => 'sometimes|numeric',
            'department_id' => 'sometimes|numeric',
        ]);

        // 2️⃣ Base query
        $query = PrinterQuota::with(['printer.company', 'printer.department'])
            ->whereBetween('mois', [$request->start_date, $request->end_date]);

        // 3️⃣ Filtres optionnels
        if ($request->filled('company_id')) {
            $query->whereHas('printer', fn($q) => $q->where('company_id', $request->company_id));
        }
        if ($request->filled('department_id')) {
            $query->whereHas('printer', fn($q) => $q->where('department_id', $request->department_id));
        }

        $quotas = $query->get();
        if ($quotas->isEmpty()) {
            Log::warning('Aucun quota trouvé pour cette période.');
        }

        // 4️⃣ Calculs globaux côté controller
        $totalDepassementBW = 0;
        $totalDepassementColor = 0;
        $totalPrinters = 0;

        foreach ($quotas as $quota) {
            $totalDepassementBW += $quota->depassementBW ?? 0;
            $totalDepassementColor += $quota->depassementColor ?? 0;
            $totalPrinters++;
        }

        // Bilan général
        $bilan = ($totalDepassementBW > 0 || $totalDepassementColor > 0)
            ? '⚠️ Dépassements détectés'
            : '✅ Aucun dépassement constaté';

        // 5️⃣ Préparer les données pour la vue
        $company = $quotas->isNotEmpty() ? $quotas->first()->printer?->company : null;
        $department = $quotas->pluck('printer.department')->unique('id')->filter();

        $data = [
            'quotas' => $quotas,
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
            'company' => $company,
            'department' => $department,
            'totalDepassementBW' => $totalDepassementBW,
            'totalDepassementColor' => $totalDepassementColor,
            'totalPrinters' => $totalPrinters,
            'bilan' => $bilan,
        ];

        // 6️⃣ Génération PDF
        $pdf = Pdf::loadView('reports.quota_report', $data);

        // 7️⃣ Nom du fichier propre
        $companyName = $company ? $company->name : 'toutes_les_compagnies';
        $fileNameSlug = preg_replace('/[^A-Za-z0-9\_]/', '', str_replace(' ', '_', strtolower($companyName)));
        $filename = "rapport_global_{$fileNameSlug}.pdf";

        return $pdf->stream($filename);
    }
}
