<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Printer; // Assurez-vous d'importer le modèle Printer
use App\Models\User;    // Assurez-vous d'importer le modèle User
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB; // Pas nécessaire si vous utilisez des modèles Eloquent

class DashboardController extends Controller
{
    // Vous pouvez ajouter un middleware de protection ici si toutes les méthodes nécessitent une authentification
    // public function __construct()
    // {
    //     $this->middleware('auth:sanctum'); // Si vous utilisez Laravel Sanctum pour l'authentification API
    // }

    public function index()
    {
        $usersCount = User::count();
        $companiesCount = Company::count();
        $printersCount = Printer::count();
        $interventionsCount = Intervention::count();

        return view('dashboard', compact('usersCount', 'companiesCount', 'printersCount', 'interventionsCount'));
    }

    public function getStats()
    {
        // Utilisation des modèles Eloquent pour récupérer les nombres réels
        // Correction : Compte les imprimantes qui ne sont ni 'En Maintenance' ni 'Hors Service'
        $printerCount = Printer::whereNotIn('status', ['En Maintenance', 'Hors Service'])->count();
        $userCount = User::count();
        // Correction : Compte seulement les interventions dont le statut est 'Terminée'
        $requestCount = Intervention::where('status', 'Terminée')->count();

        // Nouveau : Compter les imprimantes par statut spécifique
        $maintenancePrinterCount = Printer::where('status', 'En Maintenance')->count();
        $outOfServicePrinterCount = Printer::where('status', 'Hors Service')->count();

        return response()->json([
            'printerCount' => $printerCount, // Cette statistique représente maintenant les imprimantes actives (non en maintenance, non hors service)
            'userCount' => $userCount,
            'requestCount' => $requestCount, // Cette statistique représente les interventions terminées
            'maintenancePrinterCount' => $maintenancePrinterCount, // Compteur pour les imprimantes en maintenance
            'outOfServicePrinterCount' => $outOfServicePrinterCount, // Compteur pour les imprimantes hors service
        ]);
    }

    public function getRecentActivities()
    {
        // Exemple : récupérer les 5 dernières interventions
        $recentActivities = Intervention::orderBy('created_at', 'desc')
                                    ->limit(5) // Limiter à 5 activités récentes
                                    ->get()
                                    ->map(function($intervention) {
                                        return [
                                            'id' => $intervention->id,
                                            'date' => $intervention->created_at->format('d/m/Y H:i'),
                                            'description' => "Intervention #{$intervention->id}: {$intervention->intervention_type} - {$intervention->description}",
                                            'status' => $intervention->status,
                                        ];
                                    });

        return response()->json($recentActivities);
    }
}
