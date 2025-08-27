<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Intervention;
use App\Models\Printer;
use App\Models\Company;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    /**
     * Helper to apply period, company, department, and printer filtering to a query.
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
        $printerId = $request->query('printer_id'); // Ajout du filtre imprimante

        $now = Carbon::now();
        switch ($period) {
            case 'Semaine':
                $query->whereBetween('interventions.' . $dateColumn, [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
                break;
            case 'Mois':
                $query->whereBetween('interventions.' . $dateColumn, [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
                break;
            case 'Année':
                $query->whereBetween('interventions.' . $dateColumn, [$now->copy()->startOfYear(), $now->copy()->endOfYear()]);
                break;
            default:
                break;
        }

        if ($companyId && $companyId !== 'all') {
            $query->whereHas('printer', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        if ($departmentId && $departmentId !== 'all') {
            $query->whereHas('printer', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        // Ajout de la logique de filtre pour l'imprimante
        if ($printerId && $printerId !== 'all') {
            $query->where('printer_id', $printerId);
        }

        return $query;
    }

    /**
     * Helper to format duration from seconds into a human-readable string.
     *
     * @param int|null $diffInSeconds
     * @return string
     */
    protected function formatDuration($diffInSeconds): string
    {
        if ($diffInSeconds === null) {
            return 'N/A';
        }
        if ($diffInSeconds < 0) {
            return 'Durée invalide'; // Cas où end_date est avant start_date
        }
        if ($diffInSeconds == 0) {
            return '0s';
        }

        $hours = floor($diffInSeconds / 3600);
        $minutes = floor(($diffInSeconds % 3600) / 60);
        $seconds = $diffInSeconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0 || ($hours > 0 && $seconds == 0) || ($hours > 0 && $minutes == 0 && $seconds == 0 && $diffInSeconds > 0)) {
            $parts[] = "{$minutes}min";
        }
        if ($seconds > 0 || (empty($parts) && $diffInSeconds > 0)) {
            $parts[] = "{$seconds}s";
        }

        return trim(implode(' ', $parts));
    }


    public function getAllInterventions(Request $request)
    {
        $query = Intervention::query();
        $query = $this->applyFilters($query, $request);

        $interventions = $query
            ->with([
                'printer',
                'printer.company',
                'printer.department',
                'assignedTo',
                'reportedBy'
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($intervention) {
                $resolutionTimeSeconds = null;
                if ($intervention->start_date_intervention && $intervention->end_date) {
                    $start = Carbon::parse($intervention->start_date_intervention);
                    $end = Carbon::parse($intervention->end_date);
                    $resolutionTimeSeconds = $end->diffInSeconds($start);
                }

                return [
                    'id' => $intervention->id,
                    'numero_demande' => $intervention->numero_demande,
                    'description' => $intervention->description,
                    'status' => $intervention->status,
                    'intervention_type' => $intervention->intervention_type,
                    'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                    'end_date' => $intervention->end_date ? $intervention->end_date->format('Y-m-d H:i') : null,
                    'resolution_time_minutes' => $this->formatDuration($resolutionTimeSeconds),


                    'printer' => [
                        'id' => $intervention->printer->id ?? null,
                        'model' => $intervention->printer->model ?? 'N/A',
                        'serial' => $intervention->printer->serial ?? 'N/A',
                        'company_name' => $intervention->printer->company->name ?? 'N/A',
                        'department_name' => $intervention->printer->department->name ?? 'N/A',
                    ],
                    'assigned_to' => [
                        'id' => $intervention->assignedTo->id ?? null,
                        'name' => $intervention->assignedTo->name ?? 'Non assigné',
                    ],
                    'reported_by' => [
                        'id' => $intervention->reportedBy->id ?? null,
                        'name' => $intervention->reportedBy->name ?? 'Inconnu',
                    ],
                ];
            });

        return response()->json($interventions);
    }

    public function getDepartmentsWithMostInterventions(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $requestForDepartmentsTop5 = clone $request;
        $requestForDepartmentsTop5->request->remove('department_id');
        $requestForDepartmentsTop5->query->remove('department_id');

        $departmentsAttentionQuery = Intervention::query();
        $departmentsAttentionQuery = $this->applyFilters($departmentsAttentionQuery, $requestForDepartmentsTop5);

        $departmentsAttention = $departmentsAttentionQuery
            ->select(
                DB::raw('departments.id as department_id'),
                DB::raw('departments.name as department_name'),
                DB::raw('count(interventions.id) as interventions_count')
            )
            ->join('printers', 'interventions.printer_id', '=', 'printers.id')
            ->join('departments', 'printers.department_id', '=', 'departments.id')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('interventions_count')
            ->limit(5)
            ->get();

        return response()->json($departmentsAttention);
    }

    // Cette fonction 'getCompaniesWithMostInterventions' est commentée, pas de modification nécessaire.
    /*
    public function getCompaniesWithMostInterventions(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $requestForCompaniesTop5 = clone $request;
        $requestForCompaniesTop5->request->remove('company_id');
        $requestForCompaniesTop5->query->remove('company_id');

        $companiesAttentionQuery = Intervention::query();
        $companiesAttentionQuery = $this->applyFilters($companiesAttentionQuery, $requestForCompaniesTop5);

        $companiesAttention = $companiesAttentionQuery
            ->select(
                DB::raw('companies.id as company_id'),
                DB::raw('companies.name as company_name'),
                DB::raw('count(interventions.id) as interventions_count')
            )
            ->join('printers', 'interventions.printer_id', '=', 'printers.id')
            ->join('companies', 'printers.company_id', '=', 'companies.id')
            ->groupBy('companies.id', 'companies.name')
            ->orderByDesc('interventions_count')
            ->limit(5)
            ->get();

        return response()->json($companiesAttention);
    }
    */

    public function getInterventionsByDepartment(int $departmentId)
    {
        $interventions = Intervention::whereHas('printer', function ($query) use ($departmentId) {
            $query->where('department_id', $departmentId);
        })
        ->with([
            'printer',
            'printer.company',
            'printer.department',
            'assignedTo',
            'reportedBy'
        ])
        ->orderByDesc('created_at')
        ->get()
        ->map(function ($intervention) {
            $resolutionTimeSeconds = null;
            if ($intervention->start_date_intervention && $intervention->end_date) {
                $start = Carbon::parse($intervention->start_date_intervention);
                $end = Carbon::parse($intervention->end_date);
                $resolutionTimeSeconds = $end->diffInSeconds($start);
            }
            return [
                'id' => $intervention->id,
                'numero_demande' => $intervention->numero_demande,
                'description' => $intervention->description,
                'status' => $intervention->status,
                'intervention_type' => $intervention->intervention_type,
                'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                'end_date' => $intervention->end_date ? $intervention->end_date->format('Y-m-d H:i') : null,
                'resolution_time_minutes' => $this->formatDuration($resolutionTimeSeconds),

                'printer' => [
                    'id' => $intervention->printer->id ?? null,
                    'model' => $intervention->printer->model ?? 'N/A',
                    'serial' => $intervention->printer->serial ?? 'N/A',
                    'company_name' => $intervention->printer->company->name ?? 'N/A',
                    'department_name' => $intervention->printer->department->name ?? 'N/A',
                ],
                'assigned_to' => [
                    'id' => $intervention->assignedTo->id ?? null,
                    'name' => $intervention->assignedTo->name ?? 'Non assigné'
                ],
                'reported_by' => [
                    'id' => $intervention->reportedBy->id ?? null,
                    'name' => $intervention->reportedBy->name ?? 'Inconnu'
                ],
            ];
        });

        return response()->json($interventions);
    }

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
                $start = Carbon::parse($intervention->start_date_intervention);
                $end = Carbon::parse($intervention->end_date);
                $totalDurationInSeconds += $end->diffInSeconds($start);
            }

            $averageDurationInSeconds = $totalDurationInSeconds / $resolvedInterventionsWithDates->count();
            $averageResolutionTime = $this->formatDuration(round($averageDurationInSeconds));
        }

        return response()->json([
            'totalInterventions' => $totalInterventions,
            'resolvedInterventions' => $resolvedInterventions,
            'pendingInterventions' => $pendingInterventions,
            'resolutionRate' => round($resolutionRate, 2),
            'averageResolutionTime' => $averageResolutionTime,
        ]);
    }

    public function getCompanyStats(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $companies = Company::query();

        if ($request->query('company_id') && $request->query('company_id') !== 'all') {
            $companies->where('id', $request->query('company_id'));
        }

        $companies = $companies->get();

        $companyStats = $companies->map(function ($company) use ($request) {
            $interventionsQuery = Intervention::whereHas('printer', function ($query) use ($company, $request) {
                $query->where('company_id', $company->id);
                if ($request->query('department_id') && $request->query('department_id') !== 'all') {
                    $query->where('department_id', $request->query('department_id'));
                }
            });
            $interventionsQuery = $this->applyFilters($interventionsQuery, $request);
            $totalInterventions = $interventionsQuery->count();

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
                'totalInterventions' => $totalInterventions,
                'avgFailuresPerPrinter' => $avgFailuresPerPrinter,
            ];
        });

        return response()->json($companyStats->filter(fn($stat) => $stat['printerCount'] > 0 || $stat['totalInterventions'] > 0)->values());
    }

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

    public function getPrintersNeedingAttention(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $printersAttentionQuery = Printer::with(['company', 'department'])
            ->withCount(['interventions' => function ($query) use ($request) {
                $this->applyFilters($query, $request);
            }]);

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
            $lastIntervention = $printer->interventions()->latest('created_at')->first();

            return [
                'id' => $printer->id,
                'model' => $printer->model,
                'serialNumber' => $printer->serial,
                'company_id' => $printer->company_id,
                'companyName' => $printer->company->name ?? 'N/A',
                'department_id' => $printer->department_id,
                'departmentName' => $printer->department->name ?? 'N/A',
                'status' => $printer->status ?? 'N/A',
                'statusDisplay' => $printer->status ?? 'N/A',
                'lastInterventionDate' => $lastIntervention ? Carbon::parse($lastIntervention->created_at)->format('Y-m-d') : 'N/A',
                'interventionCount' => $printer->interventions_count,
            ];
        });

        return response()->json($attentionPrinters);
    }

    public function getInterventionsByTypeOverTime(Request $request)
    {
        $request->validate([
            'period' => ['sometimes', 'string', Rule::in(['Semaine', 'Mois', 'Année', 'Total'])],
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $query = Intervention::query();
        $query = $this->applyFilters($query, $request);

        $dateFormat = '%Y-%m';
        $periodNameAlias = 'period_name';
        $groupByColumn = 'created_at';

        switch ($request->query('period')) {
            case 'Semaine':
                $dateFormat = '%Y-%W';
                break;
            case 'Année':
                $dateFormat = '%Y';
                break;
            case 'Total':
                $dateFormat = null;
                $periodNameAlias = 'name';
                break;
        }

        $selectPeriodColumn = $dateFormat ? DB::raw('DATE_FORMAT(' . $groupByColumn . ', "' . $dateFormat . '") as ' . $periodNameAlias) : DB::raw('\'Total\' as ' . $periodNameAlias);
        $groupByPeriodColumn = $dateFormat ? DB::raw('DATE_FORMAT(' . $groupByColumn . ', "' . $dateFormat . '")') : DB::raw('\'Total\'');

        $rawInterventionsData = $query
            ->select(
                $selectPeriodColumn,
                'intervention_type',
                DB::raw('count(*) as count')
            )
            ->whereNotNull('intervention_type')
            ->groupBy($groupByPeriodColumn, 'intervention_type')
            ->orderBy($periodNameAlias)
            ->get();

        $formattedData = [];
        $periods = $rawInterventionsData->pluck($periodNameAlias)->unique()->sort()->values();
        $allInterventionTypes = $rawInterventionsData->pluck('intervention_type')->unique()->values();

        foreach ($periods as $periodName) {
            $entry = ['name' => $periodName];
            foreach ($allInterventionTypes as $type) {
                $entry[$type] = 0;
            }
            $rawInterventionsData->where($periodNameAlias, $periodName)->each(function ($item) use (&$entry) {
                $entry[$item->intervention_type] = $item->count;
            });
            $formattedData[] = $entry;
        }

        return response()->json($formattedData);
    }

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

    public function generateReport(Request $request, string $reportType)
    {
        $content = "Ceci est un rapport simulé de type : " . $reportType . "\n";
        $content .= "Généré le : " . now()->format('Y-m-d H:i:s');

        return response($content, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $reportType . '_report.pdf"');
    }

    public function getInterventionsByCompany(int $companyId)
    {
        $interventions = Intervention::whereHas('printer', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->with([
            'printer',
            'printer.department',
            'assignedTo',
            'reportedBy'
        ])
        ->orderByDesc('created_at')
        ->get()
        ->map(function ($intervention) {
            $resolutionTimeSeconds = null;
            if ($intervention->start_date_intervention && $intervention->end_date) {
                $start = Carbon::parse($intervention->start_date_intervention);
                $end = Carbon::parse($intervention->end_date);
                $resolutionTimeSeconds = $end->diffInSeconds($start);
            }
            return [
                'id' => $intervention->id,
                'numero_demande' => $intervention->numero_demande,
                'description' => $intervention->description,
                'status' => $intervention->status,
                'intervention_type' => $intervention->intervention_type,
                'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                'end_date' => $intervention->end_date ? $intervention->end_date->format('Y-m-d H:i') : null,
                'resolution_time_minutes' => $this->formatDuration($resolutionTimeSeconds),

                'printer' => [
                    'id' => $intervention->printer->id ?? null,
                    'model' => $intervention->printer->model ?? 'N/A',
                    'serial' => $intervention->printer->serial ?? 'N/A',
                    'department_name' => $intervention->printer->department->name ?? 'N/A',
                ],
                'assigned_to' => [
                    'id' => $intervention->assignedTo->id ?? null,
                    'name' => $intervention->assignedTo->name ?? 'Non assigné'
                ],
                'reported_by' => [
                    'id' => $intervention->reportedBy->id ?? null,
                    'name' => $intervention->reportedBy->name ?? 'Inconnu'
                ],
            ];
        });

        return response()->json($interventions);
    }
    /**
     * Get a printer by its ID.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getImprimanteById(int $id)
    {
        $printer = Printer::with(['company', 'department'])
                          ->find($id);

        if (!$printer) {
            return response()->json(['message' => 'Imprimante non trouvée.'], 404);
        }

        return response()->json($printer);
    }
    public function getInterventionsByPrinter(int $printerId)
    {
        $interventions = Intervention::where('printer_id', $printerId)
            ->with([
                'assignedTo',
                'reportedBy',
                'printer',
                'printer.company',
                'printer.department'
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($intervention) {
                $resolutionTimeSeconds = null;
                if ($intervention->start_date_intervention && $intervention->end_date) {
                    $start = Carbon::parse($intervention->start_date_intervention);
                    $end = Carbon::parse($intervention->end_date);
                    $resolutionTimeSeconds = $end->diffInSeconds($start);
                }
                return [
                    'id' => $intervention->id,
                    'numero_demande' => $intervention->numero_demande,
                    'description' => $intervention->description,
                    'status' => $intervention->status,
                    'intervention_type' => $intervention->intervention_type,
                    'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                    'end_date' => $intervention->end_date ? $intervention->end_date->format('Y-m-d H:i') : null,
                    'resolution_time_minutes' => $this->formatDuration($resolutionTimeSeconds),

                    'printer' => [
                        'id' => $intervention->printer->id ?? null,
                        'model' => $intervention->printer->model ?? 'N/A',
                        'serial' => $intervention->printer->serial ?? 'N/A',
                        'company_name' => $intervention->printer->company->name ?? 'N/A',
                        'department_name' => $intervention->printer->department->name ?? 'N/A',
                    ],
                    'assigned_to' => [
                        'id' => $intervention->assignedTo->id ?? null,
                        'name' => $intervention->assignedTo->name ?? 'Non assigné'
                    ],
                    'reported_by' => [
                        'id' => $intervention->reportedBy->id ?? null,
                        'name' => $intervention->reportedBy->name ?? 'Inconnu'
                    ],
                ];
            });

        return response()->json($interventions);
    }
}
