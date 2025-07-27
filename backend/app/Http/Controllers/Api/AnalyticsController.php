<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Pour les requêtes de base de données
use App\Models\Intervention; // Importez les modèles nécessaires
use App\Models\Printer;
use App\Models\Company;
use App\Models\User; // Pour les utilisateurs et techniciens/clients

class AnalyticsController extends Controller
{
    /**
     * Get overview statistics for the dashboard.
     * (Total interventions, resolved, pending, resolution rate)
     */
    public function getOverviewStats(Request $request)
    {
        // --- Logique de récupération des données réelles ---
        // Pour l'instant, des données simulées.
        // Vous devrez implémenter la vraie logique de requête à la base de données ici.

        // Exemple de logique pour des données réelles (non testé, à adapter à votre DB) :
        $totalInterventions = Intervention::count();
        $resolvedInterventions = Intervention::where('status', 'Terminée')->count();
        $pendingInterventions = Intervention::whereIn('status', ['En Cours', 'En Attente'])->count();
        $resolutionRate = $totalInterventions > 0 ? ($resolvedInterventions / $totalInterventions) * 100 : 0;

        return response()->json([
            'totalInterventions' => $totalInterventions,
            'resolvedInterventions' => $resolvedInterventions,
            'pendingInterventions' => $pendingInterventions,
            'resolutionRate' => round($resolutionRate, 2), // Arrondir à 2 décimales
        ]);
    }

    /**
     * Get statistics per company.
     * (Printer count, avg failures per printer)
     */
    public function getCompanyStats(Request $request)
    {
        // --- Logique de récupération des données réelles ---
        // Pour l'instant, des données simulées.
        // Vous devrez implémenter la vraie logique de requête à la base de données ici.

        // Exemple de logique pour des données réelles (non testé, à adapter à votre DB) :
        $companies = Company::all();
        $companyStats = [];

        foreach ($companies as $company) {
            $printerCount = Printer::where('company', $company->name)->count(); // Assurez-vous que 'company' est le bon champ
            // Pour 'Moy. pannes/imprimante', vous aurez besoin d'une relation ou d'une agrégation complexe.
            // Ceci est un placeholder, la logique réelle est plus complexe.
            $interventionsForCompany = Intervention::where('client', $company->name)->count(); // Ou via relation Printer->Intervention
            $avgFailuresPerPrinter = $printerCount > 0 ? round($interventionsForCompany / $printerCount, 2) : 0;

            $companyStats[] = [
                'id' => $company->id,
                'name' => $company->name,
                'printerCount' => $printerCount,
                'avgFailuresPerPrinter' => $avgFailuresPerPrinter,
            ];
        }

        return response()->json($companyStats);
    }

    /**
     * Get most frequent errors/issues.
     */
    public function getFrequentErrors(Request $request)
    {
        // --- Logique de récupération des données réelles ---
        // Pour l'instant, des données simulées.
        // Vous devrez implémenter la vraie logique de requête à la base de données ici.

        // Exemple de logique pour des données réelles (non testé, à adapter à votre DB) :
        // Ceci suppose que votre table 'interventions' a une colonne 'description'
        // et que vous pouvez en extraire des "types de pannes" ou que vous avez une table 'failure_types'.
        $frequentErrors = Intervention::select(DB::raw('SUBSTRING(description, 1, 50) as type'), DB::raw('count(*) as count'))
                                    ->groupBy('type')
                                    ->orderByDesc('count')
                                    ->limit(5)
                                    ->get();

        return response()->json($frequentErrors);
    }

    /**
     * Get top printers needing attention.
     * (e.g., printers with most recent interventions, or specific status)
     */
    public function getPrintersNeedingAttention(Request $request)
    {
        // --- Logique de récupération des données réelles ---
        // Pour l'instant, des données simulées.
        // Vous devrez implémenter la vraie logique de requête à la base de données ici.

        // Exemple de logique pour des données réelles (non testé, à adapter à votre DB) :
        // Ceci est un exemple simple, vous voudrez peut-être des critères plus complexes
        $printers = Printer::orderByDesc('lastMaintenance') // Ou un champ comme 'intervention_count'
                            ->limit(5)
                            ->get();

        $attentionPrinters = [];
        foreach ($printers as $printer) {
            // Récupérer le nom de la société si 'company' est un ID dans Printer
            $companyName = $printer->company; // Si 'company' est déjà le nom
            // Si 'company' est un ID, il faudra charger la relation ou faire un lookup
            // $companyName = Company::find($printer->company_id)->name ?? 'N/A';

            $attentionPrinters[] = [
                'id' => $printer->id,
                'model' => $printer->model,
                'serialNumber' => $printer->serial, // Assurez-vous que 'serial' est le bon nom de colonne
                'companyName' => $companyName,
                'status' => $printer->status,
                'statusDisplay' => $printer->status, // Si vous avez un champ 'statusDisplay' dans votre modèle Printer
                'lastInterventionDate' => 'N/A', // À récupérer depuis les interventions liées
            ];
        }

        return response()->json($attentionPrinters);
    }

    /**
     * List historical reports (if any).
     */
    public function listReports(Request $request)
    {
        // Pour l'instant, une liste simulée de rapports.
        // Si vous stockez des rapports générés en DB, vous les récupéreriez ici.
        return response()->json([
            [
                'id' => 1,
                'name' => 'Rapport Mensuel Interventions - Juin 2025',
                'createdAt' => '2025-07-01T10:00:00Z',
                'downloadLink' => 'http://localhost:8000/api/reports/download/monthly_interventions_2025_06.pdf', // Simulé
            ],
            [
                'id' => 2,
                'name' => 'Inventaire Imprimantes Actives - Juillet 2025',
                'createdAt' => '2025-07-15T14:30:00Z',
                'downloadLink' => 'http://localhost:8000/api/reports/download/printer_inventory_2025_07.csv', // Simulé
            ],
        ]);
    }

    /**
     * Generate a specific report.
     * @param string $reportType The type of report to generate (e.g., 'intervention-summary', 'printer-inventory')
     */
    public function generateReport(Request $request, string $reportType)
    {
        // --- Logique de génération de rapport ---
        // C'est ici que vous généreriez réellement un PDF, CSV, etc.
        // Pour l'instant, nous allons renvoyer un simple fichier texte simulé.

        $content = "Ceci est un rapport simulé de type : " . $reportType . "\n";
        $content .= "Généré le : " . now()->format('Y-m-d H:i:s');

        // Exemple de renvoi d'un fichier PDF simulé (nécessite une bibliothèque comme Dompdf ou Snappy)
        // Pour un vrai PDF, il faudrait générer le contenu HTML, puis le convertir en PDF.
        // Pour un CSV, il faudrait construire les lignes CSV.

        // Pour un test simple, on renvoie un fichier texte avec un header de PDF
        return response($content, 200)
                ->header('Content-Type', 'application/pdf') // Simule un PDF
                ->header('Content-Disposition', 'attachment; filename="' . $reportType . '_report.pdf"');
    }
}
