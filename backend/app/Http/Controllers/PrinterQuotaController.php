<?php

namespace App\Http\Controllers;

use App\Models\Printer;
use App\Models\PrinterQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PrinterQuotaController extends Controller
{
    // Liste des quotas
    public function index(Request $request)
    {
        // 1. Eager load les relations imbriquées pour la société et le département
        // return PrinterQuota::with(['printer.company', 'printer.department'])->get();
        $query = PrinterQuota::with(['printer.company', 'printer.department']);

         // Filtre société
    if ($request->has('company_id') && $request->get('company_id') !== 'all') {
        $companyId = $request->get('company_id');
        $query->whereHas('printer.company', function($q) use ($companyId) {
            $q->where('id', $companyId);
        });
    }

    // Filtre département
    if ($request->has('department_id') && $request->get('department_id') !== 'all') {
        $departmentId = $request->get('department_id');
        $query->whereHas('printer.department', function($q) use ($departmentId) {
            $q->where('id', $departmentId);
        });
    }

    if ($request->has('mois')) {
        // Transformer "2025-09" → "2025-09-01"
        $mois = \Carbon\Carbon::createFromFormat('Y-m', $request->get('mois'))->startOfMonth();
        $query->whereDate('mois', $mois);
    }

    return $query->get();
    }

    // Ajouter un quota
public function store(Request $request)
{
    // 1. Validation des données du relevé
    $validated = $request->validate([
        'printer_id' => 'required|exists:printers,id',
        'monthly_quota_bw' => 'required|integer|min:0',
        'monthly_quota_color' => 'required|integer|min:0',
        'date_prelevement'=> 'sometimes|date',
        'mois'=> 'sometimes|date',

    ]);

    // 2. Récupérer l'imprimante pour obtenir les ANCIENNES valeurs
    $printer = Printer::findOrFail($validated['printer_id']);

    $isColor = str_contains($printer->model, 'C');


    // 3. Calculer la consommation réelle du mois en cours
    $bwConsumedThisMonth = max(0, $validated['monthly_quota_bw'] - $printer->monthly_quota_bw);
    $colorConsumedThisMonth = max(0, $validated['monthly_quota_color'] - $printer->monthly_quota_color);

    $totalConsumedThisMonth = $bwConsumedThisMonth + $colorConsumedThisMonth;

    // 4. Calculer le dépassement en fonction du quota mensuel de l'imprimante
    $depassementBW = 0;
    $depassementColor = 0;



    // Assurez-vous que la societe a un quota mensuel défini
    if ($printer->company->quota_monthly) {
            if ($isColor) {
                $quotaColor = ($printer->company->quota_Color/100) * $printer->company->quota_monthly;
                $quotaBW = ($printer->company->quota_BW/100) * $printer->company->quota_monthly;
        } else {
            $quotaBW = $printer->monthly_quota_pages * 1;
            $quotaColor = 0;
        }

        // Si la consommation dépasse le quota, calculer le dépassement
        if ($bwConsumedThisMonth > $quotaBW) {
            $depassementBW = $bwConsumedThisMonth - $quotaBW;
        }

        if ($colorConsumedThisMonth > $quotaColor) {
            $depassementColor = $colorConsumedThisMonth - $quotaColor;
        }
    }
    // Transformer "2025-09" → "2025-09-01"
    $mois = isset($validated['mois'])
    ? \Carbon\Carbon::createFromFormat('Y-m', $validated['mois'])->startOfMonth()
    : now()->startOfMonth(); // valeur par défaut = mois courant

    // 5. Créer le relevé de quota avec les valeurs calculées
    $quotaData = [
        'printer_id' => $validated['printer_id'],
        'monthly_quota_bw' => $bwConsumedThisMonth,
        'monthly_quota_color' => $colorConsumedThisMonth,
        'total_quota' => $totalConsumedThisMonth,
        'depassementBW' => $depassementBW,
        'depassementColor' => $depassementColor,
        'date_prelevement'=>$validated['date_prelevement'],
        'mois'=> $mois,
    ];
    $quota = PrinterQuota::create($quotaData);

    // 6. Mettre à jour l'imprimante avec les NOUVELLES valeurs du relevé
    $printer->update([
        'monthly_quota_bw' => $validated['monthly_quota_bw'],
        'monthly_quota_color' => $validated['monthly_quota_color'],
        'total_quota_pages' => $printer->total_quota_pages + $totalConsumedThisMonth,
    ]);

    // 7. Retourner la réponse
    return response()->json($quota->load(['printer.company', 'printer.department']), 201);
}

    // Afficher un quota
    public function show(PrinterQuota $printerQuota)
    {
        return $printerQuota->load('printer');
    }


   // Modifier un quota// Modifier un quota
 public function update(Request $request, PrinterQuota $quota)
    {
        // Validation des données
        $validated = $request->validate([
            'monthly_quota_bw' => 'sometimes|integer|min:0',
            'monthly_quota_color' => 'sometimes|integer|min:0',
            'date_prelevement' => 'sometimes|date',
            'mois' => 'sometimes|date_format:Y-m',
        ]);

        // Convertir 'mois' en objet Carbon si présent
        if (isset($validated['mois'])) {
            $validated['mois'] = Carbon::createFromFormat('Y-m', $validated['mois'])->startOfMonth();
        }

        // Récupérer l'imprimante avec vérification
        $printer = $quota->printer;

        if (!$printer) {
            return response()->json([
                'error' => 'Imprimante associée non trouvée'
            ], 404);
        }

        // Vérifier si c'est le dernier quota de cette imprimante
        $latestQuota = PrinterQuota::where('printer_id', $printer->id)
            ->orderBy('mois', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestQuota || $latestQuota->id !== $quota->id) {
            return response()->json([
                'error' => 'Seul le dernier quota de l\'imprimante peut être modifié'
            ], 403);
        }

        $isColor = str_contains($printer->model, 'C');

        // Récupérer les valeurs originales du quota avant la mise à jour
        $originalBwQuota = $quota->monthly_quota_bw;
        $originalColorQuota = $quota->monthly_quota_color;
        $originalTotalQuota = $quota->total_quota;

        // Mettre à jour le quota avec les nouvelles données validées
        $quota->update($validated);

        // Maintenant, nous devons ajuster les compteurs de l'imprimante et recalculer les dépassements

        // On récupère le relevé précédent pour calculer la consommation du mois
        $previousQuota = PrinterQuota::where('printer_id', $printer->id)
            ->where('id', '!=', $quota->id)
            ->orderBy('mois', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        // On détermine le relevé du mois précédent
        $oldPrinterBwReading = $previousQuota ? $previousQuota->monthly_quota_bw : 0;
        $oldPrinterColorReading = $previousQuota ? $previousQuota->monthly_quota_color : 0;

        // La nouvelle consommation du mois est la différence entre le nouveau relevé et le relevé du mois précédent
        $newConsumedBwThisMonth = max(0, ($validated['monthly_quota_bw'] ?? 0) - $oldPrinterBwReading);
        $newConsumedColorThisMonth = max(0, ($validated['monthly_quota_color'] ?? 0) - $oldPrinterColorReading);
        $newTotalConsumedThisMonth = $newConsumedBwThisMonth + $newConsumedColorThisMonth;

        // On recalcule les dépassements
        $depassementBW = 0;
        $depassementColor = 0;

        if ($printer->company->quota_monthly) {
            if ($isColor) {
                $quotaColor = ($printer->company->quota_Color/100) * $printer->company->quota_monthly;
                $quotaBW = ($printer->company->quota_BW/100) * $printer->company->quota_monthly;
                // $quotaBW = $printer->monthly_quota_pages * 0.4;
                // $quotaColor = $printer->monthly_quota_pages * 0.6;
            } else {
                $quotaBW = $printer->company->quota_monthlyd * 1;
                $quotaColor = 0;
            }

            if ($newConsumedBwThisMonth > $quotaBW) {
                $depassementBW = $newConsumedBwThisMonth - $quotaBW;
            }

            if ($newConsumedColorThisMonth > $quotaColor) {
                $depassementColor = $newConsumedColorThisMonth - $quotaColor;
            }
        }

        // Mettre à jour le quota avec la nouvelle consommation et les nouveaux dépassements
        $quota->update([
            'monthly_quota_bw' => $newConsumedBwThisMonth,
            'monthly_quota_color' => $newConsumedColorThisMonth,
            'total_quota' => $newTotalConsumedThisMonth,
            'depassementBW' => $depassementBW,
            'depassementColor' => $depassementColor,
        ]);

        // Mettre à jour l'imprimante avec les NOUVELLES valeurs du relevé
        $printer->update([
            'monthly_quota_bw' => ($validated['monthly_quota_bw'] ?? 0),
            'monthly_quota_color' => ($validated['monthly_quota_color'] ?? 0),
            'total_quota_pages' => ($printer->total_quota_pages - $originalTotalQuota) + $newTotalConsumedThisMonth,
        ]);

        return $quota->load(['printer.company', 'printer.department']);
    }
    // Supprimer un quota
    public function destroy($id)
    {
        $printerQuota = PrinterQuota::findOrFail($id);
        $printerQuota->delete();

        return response()->json(['message' => 'Quota supprimé avec succès.']);
    }
}
