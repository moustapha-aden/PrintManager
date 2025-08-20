<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Intervention;
use App\Models\Printer;
use App\Models\User;
use Carbon\Carbon; // Import Carbon for date calculations
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
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
        //  UTILISATEURS
        $userCount = User::count();

        //  SOCIÉTÉS
        $companyCount = Company::count();
        $activeCompanyCount = Company::where('status', 'active')->count();
        $inactiveCompanyCount = Company::where('status', 'inactive')->count();

        //  DÉPARTEMENT "ENTREPÔT"
        $warehouseDepartment = Department::where('name', 'Entrepôt')->first();

        //  IMPRIMANTES
        $totalPrinterCount = Printer::count();
        $activePrinterCount = Printer::where('status', 'active')->count();
        $printersOutOfServiceCount = Printer::where('status', 'hors-service')->count();
        $printersMaintainedCount = Printer::where('status', 'maintenance')->count();
        $printersInactiveCount = Printer::where('status', 'inactive')->count();

        // --- DÉBUT DE LA CORRECTION POUR is_purchased ---
        // Ancienne ligne : $is_purchased=Printer::where('is_purchased', '1')->count();
        // Correction : Utilisation d'une variable au nom plus clair et requête sur le booléen 'true'.
        $purchasedPrintersCount = Printer::where('is_purchased', true)->count();
        // --- FIN DE LA CORRECTION POUR is_purchased ---

        // Imprimantes en stock (inactive + entrepôt)
        $printersInStockCount = 0;
        if ($warehouseDepartment) {
            $printersInStockCount = Printer::where('department_id', $warehouseDepartment->id)
                // ->where('status', 'inactive') // Correction : La condition est `in_stock`, pas `inactive`
                ->count();
        }

        // Imprimantes non attribuées à un département
        $unassignedPrintersCount = Printer::whereNull('department_id')->count();

        // Imprimantes retournées à l'entrepôt
        $returnedToWarehousePrinterCount = 0;
        if ($warehouseDepartment) {
            $returnedToWarehousePrinterCount = Printer::where('department_id', $warehouseDepartment->id)
                ->where('is_returned_to_warehouse', true)
                ->count();
        }

        //  INTERVENTIONS
        $totalInterventionCount = Intervention::count();

        $interventionsStatusCounts = Intervention::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $interventionsStatus = [
            'Terminee' => $interventionsStatusCounts['Terminée'] ?? 0,
            'En Attente' => $interventionsStatusCounts['En Attente'] ?? 0,
            'En Cours' => $interventionsStatusCounts['En Cours'] ?? 0,
            'Annulee' => $interventionsStatusCounts['Annulée'] ?? 0,
        ];

        //  POURCENTAGES (Exemple : % imprimantes actives sur le total)
        $printerStatsPercentages = [
            'active' => $totalPrinterCount ? round(($activePrinterCount / $totalPrinterCount) * 100, 1) : 0,
            'hors_service' => $totalPrinterCount ? round(($printersOutOfServiceCount / $totalPrinterCount) * 100, 1) : 0,
            'inactive' => $totalPrinterCount ? round(($printersInactiveCount / $totalPrinterCount) * 100, 1) : 0,
            'in_stock' => $totalPrinterCount ? round(($printersInStockCount / $totalPrinterCount) * 100, 1) : 0,
            // Ajout du pourcentage pour les imprimantes achetées
            'purchased' => $totalPrinterCount ? round(($purchasedPrintersCount / $totalPrinterCount) * 100, 1) : 0,
            'returned_to_warehouse' => $totalPrinterCount ? round(($returnedToWarehousePrinterCount / $totalPrinterCount) * 100, 1) : 0,
        ];

        return response()->json([
            //  Utilisateurs
            'userCount' => $userCount,

            //  Sociétés
            'companyCount' => $companyCount,
            'activeCompanyCount' => $activeCompanyCount,
            'inactiveCompanyCount' => $inactiveCompanyCount,

            //  Imprimantes
            'totalPrinterCount' => $totalPrinterCount,
            'activePrinterCount' => $activePrinterCount,
            'printersOutOfServiceCount' => $printersOutOfServiceCount,
            'printersMaintainedCount' => $printersMaintainedCount,
            'printersInactiveCount' => $printersInactiveCount,
            'printersInStockCount' => $printersInStockCount,
            'unassignedPrintersCount' => $unassignedPrintersCount,
            'returnedToWarehousePrinterCount' => $returnedToWarehousePrinterCount,
            'purchasedPrintersCount' => $purchasedPrintersCount, // Ajout du nouveau compteur

            'printerStatsPercentages' => $printerStatsPercentages,

            //  Interventions
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
            'Annulée' => $interventions['Annulée'] ?? 0, // Correction de la clé 'Annulee' en 'Annulée' pour correspondre au frontend
        ];

        // NOUVEAU : Calcul du nombre d'interventions terminées par ce technicien
        $completedByTechnicianCount = Intervention::where('technician_id', $technicianId)
                                                  ->count();

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
                                                 'assigned_to_user' => $intervention->assignedTo ? ['id' => $intervention->assignedTo->id, 'name' => $intervention->assignedTo->name] : null,
                                                 'reported_by_user' => $intervention->reportedBy ? ['id' => $intervention->reportedBy->id, 'name' => $intervention->reportedBy->name] : null,
                                             ];
                                         });

        return response()->json([
            'interventionsCount' => $interventionsCount,
            'completedByTechnicianCount' => $completedByTechnicianCount, // Ajout de la nouvelle donnée ici
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * Récupère les statistiques spécifiques à un client.
     */
    public function getClientDashboardStats(Request $request)
    {
        $clientId = $request->query('client_id');

        if (!$clientId) {
            return response()->json(['message' => 'L\'ID du client est requis.'], 400);
        }

        $clientUser = User::with('department')->find($clientId);

        if (!$clientUser || $clientUser->role !== 'client') { // Assurez-vous que l'utilisateur est bien un client
            return response()->json(['message' => 'Client non trouvé ou rôle invalide.'], 404);
        }

        $currentUserDepartmentId = $clientUser->department_id;

        // 1. Nombre d'imprimantes dans le département du client
        $myPrintersCount = 0;
        if ($currentUserDepartmentId) {
            $myPrintersCount = Printer::where('department_id', $currentUserDepartmentId)->count();
        }

        // 2. Compteurs d'interventions par statut pour les demandes du client
        $interventionsByClientQuery = Intervention::where('client_id', $clientId); // Assurez-vous que 'reported_by_user_id' est la colonne correcte

        $interventionsStatusCounts = (clone $interventionsByClientQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $myInterventionsStatus = [
            'En Attente' => $interventionsStatusCounts['En Attente'] ?? 0,
            'En Cours' => $interventionsStatusCounts['En Cours'] ?? 0,
            'Terminée' => $interventionsStatusCounts['Terminée'] ?? 0,
            'Annulée' => $interventionsStatusCounts['Annulée'] ?? 0,
        ];

        // 3. Délai moyen de résolution pour les interventions du client
        $resolvedInterventionsWithDates = (clone $interventionsByClientQuery)
            ->where('status', 'Terminée')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date');

        $averageResolutionTime = 'N/A';
        if ($resolvedInterventionsWithDates->count() > 0) {
            $totalDurationInSeconds = 0;
            foreach ($resolvedInterventionsWithDates->get() as $intervention) {
                $start = Carbon::parse($intervention->start_date);
                $end = Carbon::parse($intervention->end_date);
                $totalDurationInSeconds += $end->diffInSeconds($start);
            }

            $averageDurationInSeconds = $totalDurationInSeconds / $resolvedInterventionsWithDates->count();

            // Convert seconds to a human-readable format
            $dt = Carbon::now()->addSeconds($averageDurationInSeconds);
            $base = Carbon::now();

            $days = $base->diffInDays($dt);
            $hours = $base->addDays($days)->diffInHours($dt);
            $minutes = $base->addHours($hours)->diffInMinutes($dt);

            $averageResolutionTime = '';
            if ($days > 0) $averageResolutionTime .= "{$days}j ";
            if ($hours > 0) $averageResolutionTime .= "{$hours}h ";
            if ($minutes > 0 || ($days === 0 && $hours === 0)) $averageResolutionTime .= "{$minutes}min";
            $averageResolutionTime = trim($averageResolutionTime);
        }

        // 4. Activités récentes pour le client
        $recentActivities = (clone $interventionsByClientQuery)
            ->with('printer')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function ($intervention) {
                return [
                    'id' => $intervention->id,
                    'date' => Date::createFromFormat('Y-m-d H:i:s', $intervention->created_at)->format('d/m/Y H:i'),
                    'description' => 'Demande #' . $intervention->id . ' - ' . ($intervention->printer->brand ?? '') . ' ' . ($intervention->printer->model ?? ''),
                    'status' => $intervention->status,
                ];
            });


        return response()->json([
            'myPrintersCount' => $myPrintersCount,
            'myInterventionsCount' => $interventionsByClientQuery->count(), // Total des interventions du client
            'myInterventionsStatus' => $myInterventionsStatus, // Détail par statut
            'averageResolutionTime' => $averageResolutionTime,
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
