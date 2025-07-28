<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Intervention;
use App\Models\Printer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:sanctum'); // Active si tu utilises l'authentification
    }

    /**
     * Renvoie les statistiques générales pour le tableau de bord administrateur.
     */
    public function getStats()
    {
        // --- UTILISATEURS ---
        $userCount = User::count();

        // --- SOCIÉTÉS ---
        $companyCount = Company::count();
        $activeCompanyCount = Company::where('status', 'active')->count();
        $inactiveCompanyCount = Company::where('status', 'inactive')->count();

        // --- DÉPARTEMENT "ENTREPÔT" ---
        $warehouseDepartment = Department::where('name', 'Entrepôt')->first();

        // --- IMPRIMANTES ---
        $totalPrinterCount = Printer::count();
        $activePrinterCount = Printer::where('status', 'active')->count();
        $printersOutOfServiceCount = Printer::where('status', 'hors-service')->count();
        $printersMaintainedCount = Printer::where('status', 'en-maintenance')->count();
        $printersInactiveCount = Printer::where('status', 'inactive')->count();

        // Imprimantes en stock (inactive + entrepôt)
        $printersInStockCount = 0;
        if ($warehouseDepartment) {
            $printersInStockCount = Printer::where('department_id', $warehouseDepartment->id)
                ->where('status', 'inactive')
                ->count();
        }

        // Imprimantes non attribuées à un département
        $unassignedPrintersCount = Printer::whereNull('department_id')->count();

        // --- INTERVENTIONS ---
        $totalInterventionCount = Intervention::count();

        $interventionsStatusCounts = Intervention::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $interventionsStatus = [
            'Terminee' => $interventionsStatusCounts['Terminée'] ?? 0,
            'En Attente' => $interventionsStatusCounts['En Attente'] ?? 0,
            'En Cours' => $interventionsStatusCounts['En Cours'] ?? 0,
            'Annulee' => $interventionsStatusCounts['Annulee'] ?? 0,
        ];

        // --- POURCENTAGES (Exemple : % imprimantes actives sur le total) ---
        $printerStatsPercentages = [
            'active' => $totalPrinterCount ? round(($activePrinterCount / $totalPrinterCount) * 100, 1) : 0,
            'hors_service' => $totalPrinterCount ? round(($printersOutOfServiceCount / $totalPrinterCount) * 100, 1) : 0,
            'inactive' => $totalPrinterCount ? round(($printersInactiveCount / $totalPrinterCount) * 100, 1) : 0,
            'in_stock' => $totalPrinterCount ? round(($printersInStockCount / $totalPrinterCount) * 100, 1) : 0,
        ];

        return response()->json([
            // --- Utilisateurs ---
            'userCount' => $userCount,

            // --- Sociétés ---
            'companyCount' => $companyCount,
            'activeCompanyCount' => $activeCompanyCount,
            'inactiveCompanyCount' => $inactiveCompanyCount,

            // --- Imprimantes ---
            'totalPrinterCount' => $totalPrinterCount,
            'activePrinterCount' => $activePrinterCount,
            'printersOutOfServiceCount' => $printersOutOfServiceCount,
            'printersMaintainedCount' => $printersMaintainedCount,
            'printersInactiveCount' => $printersInactiveCount,
            'printersInStockCount' => $printersInStockCount,
            'unassignedPrintersCount' => $unassignedPrintersCount,
            'printerStatsPercentages' => $printerStatsPercentages,

            // --- Interventions ---
            'totalInterventionCount' => $totalInterventionCount,
            'interventionsStatus' => $interventionsStatus,
        ]);
    }

    /**
     * Récupère les statistiques spécifiques à un technicien.
     */
    public function getTechnicianStats(Request $request)
    {
        $technicianId = $request->query('technician_id');

        if (!$technicianId) {
            return response()->json(['message' => 'L\'ID du technicien est requis.'], 400);
        }

        // Compter les interventions par statut pour ce technicien
        $interventions = Intervention::where('technician_id', $technicianId)
                                     ->select('status', DB::raw('count(*) as count'))
                                     ->groupBy('status')
                                     ->pluck('count', 'status')
                                     ->toArray();

        $interventionsCount = [
            'En Attente' => $interventions['En Attente'] ?? 0,
            'En Cours' => $interventions['En Cours'] ?? 0,
            'Terminée' => $interventions['Terminée'] ?? 0,
            'Annulée' => $interventions['Annulee'] ?? 0,
        ];

        // Récupérer les 5 dernières activités (interventions) pour ce technicien
        $recentActivities = Intervention::with('printer') // Assurez-vous que la relation 'printer' existe
                                        ->where('technician_id', $technicianId)
                                        ->orderByDesc('created_at')
                                        ->take(5)
                                        ->get()
                                        ->map(function ($intervention) {
                                            $printerInfo = $intervention->printer
                                                ? " - {$intervention->printer->brand} {$intervention->printer->model}"
                                                : '';
                                            return [
                                                'id' => $intervention->id,
                                                'date' => $intervention->created_at->format('d/m/Y H:i'),
                                                'description' => "Intervention #{$intervention->id}: {$intervention->intervention_type}{$printerInfo}",
                                                'status' => $intervention->status,
                                            ];
                                        });

        return response()->json([
            'interventionsCount' => $interventionsCount,
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * Récupère les 5 dernières interventions pour l'historique rapide (utilisé par AdminDashboard).
     */
    public function getRecentActivities()
    {
        $recentActivities = Intervention::with('printer')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function ($intervention) {
                $printerInfo = $intervention->printer
                    ? " - {$intervention->printer->brand} {$intervention->printer->model}"
                    : '';
                return [
                    'id' => $intervention->id,
                    'date' => $intervention->created_at->format('d/m/Y H:i'),
                    'description' => "Demande #{$intervention->id}: {$intervention->intervention_type}{$printerInfo}",
                    'status' => $intervention->status,
                ];
            });

        return response()->json($recentActivities);
    }
}
