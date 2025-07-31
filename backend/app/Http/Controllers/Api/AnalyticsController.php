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
     * Helper to apply period, company, and department filtering to a query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Http\Request $request
     * @param string $dateColumn The column to filter by (default: 'created_at')
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyFilters($query, Request $request, $dateColumn = 'created_at')
    {
        $period = $request->query('period', 'Mois');
        $companyId = $request->query('company_id');
        $departmentId = $request->query('department_id');

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

        // Apply company filter if provided and not 'all'
        if ($companyId && $companyId !== 'all') {
            // Assumes the query is on Interventions or a model related to Printer
            // and Printer has a company_id. Adjust if your relationship is different.
            $query->whereHas('printer', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        // Apply department filter if provided and not 'all'
        if ($departmentId && $departmentId !== 'all') {
            // Assumes the query is on Interventions or a model related to Printer
            // and Printer has a department_id. Adjust if your relationship is different.
            $query->whereHas('printer', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        return $query;
    }

    /**
     * Get overview statistics for the dashboard.
     */
    public function getOverviewStats(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $totalInterventionsQuery = Intervention::query();
        $totalInterventionsQuery = $this->applyFilters($totalInterventionsQuery, $request);
        $totalInterventions = $totalInterventionsQuery->count();

        $resolvedInterventionsQuery = Intervention::where('status', 'Terminée');
        $resolvedInterventionsQuery = $this->applyFilters($resolvedInterventionsQuery, $request);
        $resolvedInterventions = $resolvedInterventionsQuery->count();

        $pendingInterventionsQuery = Intervention::whereIn('status', ['En Cours', 'En Attente']);
        $pendingInterventionsQuery = $this->applyFilters($pendingInterventionsQuery, $request);
        $pendingInterventions = $pendingInterventionsQuery->count();

        $resolutionRate = $totalInterventions > 0 ? ($resolvedInterventions / $totalInterventions) * 100 : 0;

        // Calculate Average Resolution Time
        $resolvedInterventionsWithDates = Intervention::where('status', 'Terminée')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date');
        $resolvedInterventionsWithDates = $this->applyFilters($resolvedInterventionsWithDates, $request);

        $averageResolutionTime = 'N/A';
        if ($resolvedInterventionsWithDates->count() > 0) {
            $totalDurationInSeconds = 0;
            foreach ($resolvedInterventionsWithDates->get() as $intervention) {
                $start = Carbon::parse($intervention->start_date);
                $end = Carbon::parse($intervention->end_date);
                $totalDurationInSeconds += $end->diffInSeconds($start);
            }

            $averageDurationInSeconds = $totalDurationInSeconds / $resolvedInterventionsWithDates->count();

            // Convert seconds to a human-readable format (e.g., days, hours, minutes)
            $dt = Carbon::now()->addSeconds($averageDurationInSeconds);
            $base = Carbon::now();

            $days = $base->diffInDays($dt);
            $hours = $base->addDays($days)->diffInHours($dt);
            $minutes = $base->addHours($hours)->diffInMinutes($dt);

            $averageResolutionTime = '';
            if ($days > 0) $averageResolutionTime .= "{$days}j ";
            if ($hours > 0) $averageResolutionTime .= "{$hours}h ";
            if ($minutes > 0 || ($days === 0 && $hours === 0)) $averageResolutionTime .= "{$minutes}min"; // Always show minutes if no days/hours

            $averageResolutionTime = trim($averageResolutionTime);
        }


        return response()->json([
            'totalInterventions' => $totalInterventions,
            'resolvedInterventions' => $resolvedInterventions,
            'pendingInterventions' => $pendingInterventions,
            'resolutionRate' => round($resolutionRate, 2),
            'averageResolutionTime' => $averageResolutionTime, // New KPI
        ]);
    }

    /**
     * Get statistics per company.
     */
    public function getCompanyStats(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $companies = Company::query();

        // Apply company filter to the companies list itself if a specific company is requested
        if ($request->query('company_id') && $request->query('company_id') !== 'all') {
            $companies->where('id', $request->query('company_id'));
        }

        $companies = $companies->get();

        $companyStats = $companies->map(function ($company) use ($request) {
            // Count interventions for printers belonging to this company, filtered by period and department
            $interventionsQuery = Intervention::whereHas('printer', function ($query) use ($company, $request) {
                $query->where('company_id', $company->id);
                // Apply department filter here if present
                if ($request->query('department_id') && $request->query('department_id') !== 'all') {
                    $query->where('department_id', $request->query('department_id'));
                }
            });
            $interventionsQuery = $this->applyFilters($interventionsQuery, $request); // Apply period filter
            $totalInterventions = $interventionsQuery->count();

            // Count printers for this company, potentially filtered by department
            $printerCountQuery = Printer::where('company_id', $company->id);
            if ($request->query('department_id') && $request->query('department_id') !== 'all') {
                $printerCountQuery->where('department_id', $request->query('department_id'));
            }
            $printerCount = $printerCountQuery->count();


            $avgFailuresPerPrinter = 0;
            if ($printerCount > 0) {
                $avgFailuresPerPrinter = round($totalInterventions / $printerCount, 2);
            }

            return [
                'id' => $company->id,
                'name' => $company->name,
                'printerCount' => $printerCount,
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
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $frequentErrorsQuery = Intervention::query();
        $frequentErrorsQuery = $this->applyFilters($frequentErrorsQuery, $request);

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
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $printersAttentionQuery = Printer::with(['company', 'department'])
            ->withCount(['interventions' => function ($query) use ($request) {
                // Count interventions for each printer, filtered by period, company, and department
                $this->applyFilters($query, $request);
            }]);

        // Apply company and department filters directly to the Printer query if present
        if ($request->query('company_id') && $request->query('company_id') !== 'all') {
            $printersAttentionQuery->where('company_id', $request->query('company_id'));
        }
        if ($request->query('department_id') && $request->query('department_id') !== 'all') {
            $printersAttentionQuery->where('department_id', $request->query('department_id'));
        }

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
                // 'numero_demande' is an intervention attribute, not a printer attribute
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
     * Get interventions grouped by type over time for a histogram.
     */
    public function getInterventionsByTypeOverTime(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $query = Intervention::query();
        $query = $this->applyFilters($query, $request);

        $dateFormat = '%Y-%m'; // Default to year-month for monthly grouping
        $periodNameAlias = 'period_name';
        $groupByColumn = 'created_at'; // Column to group by for date functions

        switch ($request->query('period')) {
            case 'Semaine':
                $dateFormat = '%Y-%W'; // Year-week
                break;
            case 'Année':
                $dateFormat = '%Y'; // Year
                break;
            case 'Total':
                $dateFormat = null; // No date grouping for total
                $periodNameAlias = 'name'; // Use 'name' directly for the single 'Total' entry
                break;
        }

        $selectPeriodColumn = $dateFormat ? DB::raw('DATE_FORMAT(' . $groupByColumn . ', "' . $dateFormat . '") as ' . $periodNameAlias) : DB::raw('\'Total\' as name');
        $groupByPeriodColumn = $dateFormat ? DB::raw('DATE_FORMAT(' . $groupByColumn . ', "' . $dateFormat . '")') : DB::raw('\'Total\'');


        $rawInterventionsData = $query
            ->select(
                $selectPeriodColumn,
                'intervention_type',
                DB::raw('count(*) as count')
            )
            ->whereNotNull('intervention_type')
            ->groupBy($groupByPeriodColumn, 'intervention_type') // Group by both period and type
            ->orderBy($periodNameAlias === 'name' ? 'name' : 'period_name') // Order by the alias
            ->get();

        // Reformat data for Recharts histogram
        $formattedData = [];
        // Use the correct alias for period names
        $periods = $rawInterventionsData->pluck($periodNameAlias)->unique()->sort()->values();
        $allInterventionTypes = $rawInterventionsData->pluck('intervention_type')->unique()->values();

        foreach ($periods as $periodName) {
            $entry = ['name' => $periodName];
            foreach ($allInterventionTypes as $type) {
                $entry[$type] = 0; // Initialize type counts
            }
            $rawInterventionsData->where($periodNameAlias, $periodName)->each(function ($item) use (&$entry) {
                $entry[$item->intervention_type] = $item->count;
            });
            $formattedData[] = $entry;
        }

        return response()->json($formattedData);
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
    public function search(Request $request)
    {
        $request->validate([
            'numero_demande' => 'required_without:serialNumber|string|max:255',
            'serialNumber' => 'required_without:numero_demande|string|max:255',
        ]);

        $numero_demande = $request->query('numero_demande');
        $serialNumber = $request->query('serialNumber');

        $printer = null;

        if ($numero_demande) {
            // Search for an intervention by numero_demande and get its associated printer
            $intervention = Intervention::where('numero_demande', $numero_demande)
                                    ->with(['printer.company', 'printer.department', 'printer.interventions' => function($query) {
                                        // Get recent interventions for this printer
                                        $query->latest('created_at')->limit(5);
                                    }])
                                    ->first();
            if ($intervention && $intervention->printer) {
                $printer = $intervention->printer;
                // Add the specific intervention that was searched for to the printer's interventions if not already there
                // Ensure 'numero_demande' is included in the intervention data
                $intervention->load(['assignedTo:id,name', 'reportedBy:id,name']); // Load users for this specific intervention
                $printer->interventions->prepend($intervention);
                $printer->interventions = $printer->interventions->unique('id')->take(5);
            }
        } elseif ($serialNumber) {
            // Search for a printer by serial number directly
            $printer = Printer::where('serial', $serialNumber)
                                ->with(['company', 'department', 'interventions' => function($query) {
                                    // Get recent interventions for this printer
                                    $query->latest('created_at')->limit(5);
                                }])
                                ->first();
        } else {
             return response()->json(['message' => 'Veuillez fournir un numéro de demande ou un numéro de série.'], 400);
        }

        if (!$printer) {
            return response()->json(['message' => 'Aucune imprimante trouvée pour les critères fournis.'], 404);
        }

        return response()->json([
            'id' => $printer->id,
            'model' => $printer->model,
            'serialNumber' => $printer->serial,
            'companyName' => $printer->company->name ?? 'N/A',
            'departmentName' => $printer->department->name ?? 'N/A',
            'status' => $printer->status,
            'location' => $printer->location ?? 'N/A',
            'interventions' => $printer->interventions->map(function ($intervention) {
                return [
                    'id' => $intervention->id,
                    'description' => $intervention->description,
                    'status' => $intervention->status,
                    'intervention_type' => $intervention->intervention_type,
                    'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                    'numero_demande' => $intervention->numero_demande, // This is the intervention's numero_demande
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
        ->with([
            'printer:id,model,serial,department_id', // Removed numero_demande
            'printer.department:id,name',
            'assignedTo:id,name',
            'reportedBy:id,name'
        ])
        ->orderByDesc('created_at')
        ->get()
        ->map(function ($intervention) {
            return [
                'id' => $intervention->id,
                'description' => $intervention->description,
                'status' => $intervention->status,
                'intervention_type' => $intervention->intervention_type,
                'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                'numero_demande' => $intervention->numero_demande, // numero_demande is on intervention
                'printer' => [
                    'id' => $intervention->printer->id,
                    'model' => $intervention->printer->model,
                    'serial' => $intervention->printer->serial,
                    'departmentName' => $intervention->printer->department->name ?? 'N/A',
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
            ->with([
                'assignedTo:id,name',
                'reportedBy:id,name',
                'printer:id,company_id,department_id,model,serial', // Removed numero_demande
                'printer.company:id,name',
                'printer.department:id,name'
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($intervention) {
                return [
                    'id' => $intervention->id,
                    'description' => $intervention->description,
                    'status' => $intervention->status,
                    'intervention_type' => $intervention->intervention_type,
                    'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                    'numero_demande' => $intervention->numero_demande, // numero_demande is on intervention
                    'printer' => [ // Add printer details to the intervention response
                        'id' => $intervention->printer->id,
                        'model' => $intervention->printer->model,
                        'serial' => $intervention->printer->serial,
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
