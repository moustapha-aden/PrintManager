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

        // 1. Validation
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'company_id' => 'sometimes|numeric',
            'department_id' => 'sometimes|numeric',
        ]);
        Log::info('Validation OK');

        // 2. Requête de base
        $query = PrinterQuota::with(['printer.company', 'printer.department'])
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->where(function ($q) {
                $q->where('total_quota', '>', 0)
                  ->orWhere('monthly_quota_bw', '>', 0)
                  ->orWhere('monthly_quota_color', '>', 0);
            });
        Log::info('Requête de base construite');

        // 3. Filtrage conditionnel
        if ($request->filled('company_id')) {
            $query->whereHas('printer', fn($q) => $q->where('company_id', $request->company_id));
            Log::info("Filtre appliqué: company_id={$request->company_id}");
        }

        if ($request->filled('department_id')) {
            $query->whereHas('printer', fn($q) => $q->where('department_id', $request->department_id));
            Log::info("Filtre appliqué: department_id={$request->department_id}");
        }

        $quotas = $query->get();
        Log::info('Quotas récupérés', ['count' => $quotas->count()]);

        // 4. Préparer les données pour la vue
        $company = $quotas->isNotEmpty() ? $quotas->first()->printer?->company : null;
        $department = $quotas->isNotEmpty() ? $quotas->first()->printer?->department : null;
        Log::info('Données pour vue préparées', ['company' => $company?->name, 'department' => $department?->name]);

        $data = [
            'quotas'     => $quotas,
            'startDate'  => $request->start_date,
            'endDate'    => $request->end_date,
            'company'    => $company,
            'department' => $department,
        ];

        if ($quotas->isEmpty()) {
            Log::warning('Aucun quota trouvé pour ces critères', [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'company_id' => $request->company_id ?? null,
                'department_id' => $request->department_id ?? null
            ]);
        }

        // 5. Génération du PDF
        $pdf = Pdf::loadView('reports.quota_report', $data);
        Log::info('PDF généré', $data);

        // 6. Retour du PDF en stream
        return $pdf->stream("rapport_global_{$request->start_date}_{$request->end_date}.pdf");
    }
}
