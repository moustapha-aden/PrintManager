<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Intervention;
use App\Models\Printer;
use App\Models\Company;
use App\Models\Department; // N'oubliez pas d'importer le modèle Department
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    /**
     * Helper to apply period filtering to a query.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $period ('Semaine', 'Mois', 'Année', 'Total')
     * @param string $dateColumn The column to filter by (default: 'created_at')
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyPeriodFilter($query, $period, $dateColumn = 'created_at')
    {
        $now = Carbon::now();
        switch ($period) {
            case 'Semaine':
                $query->whereBetween($dateColumn, [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
                break;
            case 'Mois':
                $query->whereBetween($dateColumn, [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
                break;
            case 'Année':
                $query->whereBetween($dateColumn, [$now->copy()->startOfYear(), $now->copy()->endOfYear()]);
                break;
            // 'Total' or default behavior means no date filter applied
            default:
                break;
        }
        return $query;
    }

    /**
     * Get overview statistics for the dashboard.
     */
    public function getOverviewStats(Request $request)
    {
        // Validate the 'period' parameter
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
        ]);

        $period = $request->query('period', 'Mois');

        $totalInterventionsQuery = Intervention::query();
        $totalInterventionsQuery = $this->applyPeriodFilter($totalInterventionsQuery, $period);
        $totalInterventions = $totalInterventionsQuery->count();

        // Ensure status matches your DB values, typically 'Terminée' or 'Résolue'
        $resolvedInterventionsQuery = Intervention::where('status', 'Terminée'); // Or 'Résolue'
        $resolvedInterventionsQuery = $this->applyPeriodFilter($resolvedInterventionsQuery, $period);
        $resolvedInterventions = $resolvedInterventionsQuery->count();

        // Ensure status matches your DB values
        $pendingInterventionsQuery = Intervention::whereIn('status', ['En cours', 'En attente']);
        $pendingInterventionsQuery = $this->applyPeriodFilter($pendingInterventionsQuery, $period);
        $pendingInterventions = $pendingInterventionsQuery->count();

        $resolutionRate = $totalInterventions > 0 ? ($resolvedInterventions / $totalInterventions) * 100 : 0;

        return response()->json([
            'totalInterventions' => $totalInterventions,
            'resolvedInterventions' => $resolvedInterventions,
            'pendingInterventions' => $pendingInterventions,
            'resolutionRate' => round($resolutionRate, 2),
        ]);
    }

    /**
     * Get statistics per company.
     */
    public function getCompanyStats(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
        ]);

        $period = $request->query('period', 'Mois');

        // Eager load printers count for each company
        // You might want to filter companies based on whether they have printers with interventions in the period
        $companies = Company::withCount(['printers' => function ($query) {
            $query->has('interventions'); // Only count printers that have at least one intervention
        }])
        ->get();

        $companyStats = $companies->map(function ($company) use ($period) {
            // Count interventions for printers belonging to this company, filtered by period
            $interventionsQuery = Intervention::whereHas('printer', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            });
            $interventionsQuery = $this->applyPeriodFilter($interventionsQuery, $period);
            $totalInterventions = $interventionsQuery->count();

            $avgFailuresPerPrinter = 0;
            // Use $company->printers_count (from withCount) which is the count of printers associated with the company
            if ($company->printers_count > 0) {
                $avgFailuresPerPrinter = round($totalInterventions / $company->printers_count, 2);
            }

            return [
                'id' => $company->id,
                'name' => $company->name,
                'printerCount' => $company->printers_count, // Count of printers (that have interventions if filtered by has('interventions'))
                'avgFailuresPerPrinter' => $avgFailuresPerPrinter,
            ];
        });

        // Filter out companies that have no printers or no interventions in the selected period for cleaner analytics
        return response()->json($companyStats->filter(fn($stat) => $stat['printerCount'] > 0 || $stat['avgFailuresPerPrinter'] > 0)->values());
    }

    /**
     * Get most frequent errors/issues.
     * Assumes a column named 'intervention_type' in 'interventions' table.
     */
    public function getFrequentErrors(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
        ]);

        $period = $request->query('period', 'Mois');

        $frequentErrorsQuery = Intervention::query();
        $frequentErrorsQuery = $this->applyPeriodFilter($frequentErrorsQuery, $period);

        // Ensure 'intervention_type' column exists in your 'interventions' table
        $frequentErrors = $frequentErrorsQuery
            ->select('intervention_type', DB::raw('count(*) as count'))
            ->whereNotNull('intervention_type')
            ->groupBy('intervention_type')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return response()->json($frequentErrors);
    }

    /**
     * Get top printers needing attention.
     */
    public function getPrintersNeedingAttention(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
        ]);

        $period = $request->query('period', 'Mois');

        $printersAttentionQuery = Printer::with(['company', 'department']) // Add 'department' relation
            ->withCount(['interventions' => function ($query) use ($period) {
                // Count interventions for each printer, filtered by period
                $this->applyPeriodFilter($query, $period);
            }]);

        $printers = $printersAttentionQuery
            ->orderByDesc('interventions_count')
            ->limit(5)
            ->get();

        $attentionPrinters = $printers->map(function ($printer) {
            // Get last intervention date for this specific printer (regardless of period filter)
            $lastIntervention = $printer->interventions()->latest('created_at')->first();

            return [
                'id' => $printer->id,
                'model' => $printer->model,
                'serialNumber' => $printer->serial, // Assuming 'serial' column
                'numero_demande' => $printer->numero_demande ?? 'N/A', // Make sure this column exists in your Printer model/table
                'company_id' => $printer->company_id, // Add company_id for frontend filtering
                'companyName' => $printer->company->name ?? 'N/A',
                'department_id' => $printer->department_id, // Add department_id for frontend filtering
                'departmentName' => $printer->department->name ?? 'N/A', // Assuming 'department' relation
                'status' => $printer->status,
                'statusDisplay' => $printer->status, // If your status is already display-ready
                'lastInterventionDate' => $lastIntervention ? Carbon::parse($lastIntervention->created_at)->format('Y-m-d') : 'N/A',
                'interventionCount' => $printer->interventions_count, // This count is for the selected period
            ];
        });

        return response()->json($attentionPrinters);
    }

    /**
     * List historical reports (dummy data for now).
     */
    public function listReports(Request $request)
    {
        return response()->json([
            [
                'id' => 1,
                'name' => 'Rapport Mensuel Interventions - Juin 2025',
                'createdAt' => '2025-07-01T10:00:00Z',
                'downloadLink' => 'http://localhost:8000/api/reports/download/monthly_interventions_2025_06.pdf',
            ],
            [
                'id' => 2,
                'name' => 'Inventaire Imprimantes Actives - Juillet 2025',
                'createdAt' => '2025-07-15T14:30:00Z',
                'downloadLink' => 'http://localhost:8000/api/reports/download/printer_inventory_2025_07.csv',
            ],
        ]);
    }

    /**
     * Generate a specific report (placeholder).
     */
    public function generateReport(Request $request, string $reportType)
    {
        $content = "Ceci est un rapport simulé de type : " . $reportType . "\n";
        $content .= "Généré le : " . now()->format('Y-m-d H:i:s');

        return response($content, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $reportType . '_report.pdf"');
    }

    /**
     * Search for a printer by request number (as requested by frontend).
     */
    public function search(Request $request) // Renamed from searchPrinterBySerialNumber to be more generic 'search' for the route /api/printers/search
    {
        $request->validate([
            'numero_demande' => 'required_without:serialNumber|string|max:255', // Accepts numero_demande or serialNumber
            'serialNumber' => 'required_without:numero_demande|string|max:255',
        ]);

        $numero_demande = $request->query('numero_demande');
        $serialNumber = $request->query('serialNumber');

        $printerQuery = Printer::with(['company', 'department', 'interventions' => function($query) {
            $query->with(['assignedTo:id,name', 'reportedBy:id,name'])
                  ->latest('created_at')
                  ->limit(5); // Get last 5 interventions for this printer, with user details
        }]);

        if ($numero_demande) {
            $printerQuery->where('numero_demande', $numero_demande); // Assuming 'numero_demande' column
        } elseif ($serialNumber) {
            $printerQuery->where('serial', $serialNumber); // Assuming 'serial' column
        } else {
             return response()->json(['message' => 'Veuillez fournir un numéro de demande ou un numéro de série.'], 400);
        }

        $printer = $printerQuery->first();


        if (!$printer) {
            return response()->json(['message' => 'Aucune imprimante trouvée pour les critères fournis.'], 404);
        }

        return response()->json([
            'id' => $printer->id,
            'model' => $printer->model,
            'serialNumber' => $printer->serial,
            'numero_demande' => $printer->numero_demande ?? 'N/A', // Ensure this column is available
            'companyName' => $printer->company->name ?? 'N/A',
            'departmentName' => $printer->department->name ?? 'N/A',
            'status' => $printer->status,
            'location' => $printer->location ?? 'N/A',
            'interventions' => $printer->interventions->map(function ($intervention) {
                return [
                    'id' => $intervention->id,
                    'description' => $intervention->description,
                    'status' => $intervention->status,
                    'intervention_type' => $intervention->intervention_type, // Ensure this column exists
                    'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                    'assigned_to_user' => $intervention->assignedTo ? ['id' => $intervention->assignedTo->id, 'name' => $intervention->assignedTo->name] : null,
                    'reported_by_user' => $intervention->reportedBy ? ['id' => $intervention->reportedBy->id, 'name' => $intervention->reportedBy->name] : null,
                ];
            }),
        ]);
    }

    /**
     * Get all interventions for a specific company.
     * Includes printer, assigned user, and reported by user details.
     */
    public function getInterventionsByCompany(int $companyId)
    {
        $interventions = Intervention::whereHas('printer', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->with(['printer:id,model,serial,numero_demande,department_id', 'printer.department:id,name', 'assignedTo:id,name', 'reportedBy:id,name'])
        ->orderByDesc('created_at')
        ->get()
        ->map(function ($intervention) {
            return [
                'id' => $intervention->id,
                'description' => $intervention->description,
                'status' => $intervention->status,
                'intervention_type' => $intervention->intervention_type,
                'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                'printer' => [
                    'id' => $intervention->printer->id,
                    'model' => $intervention->printer->model,
                    'serial' => $intervention->printer->serial,
                    'numero_demande' => $intervention->printer->numero_demande ?? 'N/A', // Include numero_demande
                    'departmentName' => $intervention->printer->department->name ?? 'N/A', // Include department name
                ],
                'assigned_to_user' => $intervention->assignedTo ? ['id' => $intervention->assignedTo->id, 'name' => $intervention->assignedTo->name] : null,
                'reported_by_user' => $intervention->reportedBy ? ['id' => $intervention->reportedBy->id, 'name' => $intervention->reportedBy->name] : null,
            ];
        });

        return response()->json($interventions);
    }

    /**
     * Get all interventions for a specific printer.
     * Includes assigned user and reported by user details.
     */
    public function getInterventionsByPrinter(int $printerId)
    {
        $interventions = Intervention::where('printer_id', $printerId)
            ->with(['assignedTo:id,name', 'reportedBy:id,name', 'printer:id,company_id,department_id,model,serial,numero_demande', 'printer.company:id,name', 'printer.department:id,name']) // Load company/department details from printer relation
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($intervention) {
                return [
                    'id' => $intervention->id,
                    'description' => $intervention->description,
                    'status' => $intervention->status,
                    'intervention_type' => $intervention->intervention_type,
                    'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                    'printer' => [ // Add printer details to the intervention response
                        'id' => $intervention->printer->id,
                        'model' => $intervention->printer->model,
                        'serial' => $intervention->printer->serial,
                        'numero_demande' => $intervention->printer->numero_demande ?? 'N/A',
                        'companyName' => $intervention->printer->company->name ?? 'N/A',
                        'departmentName' => $intervention->printer->department->name ?? 'N/A',
                    ],
                    'assigned_to_user' => $intervention->assignedTo ? ['id' => $intervention->assignedTo->id, 'name' => $intervention->assignedTo->name] : null,
                    'reported_by_user' => $intervention->reportedBy ? ['id' => $intervention->reportedBy->id, 'name' => $intervention->reportedBy->name] : null,
                ];
            });

        return response()->json($interventions);
    }
}
