<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Printer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Pour des requêtes directes à la base de données
// Si vous utilisez des modèles Eloquent, importez-les ici
// use App\Models\Printer;
// use App\Models\User;
// use App\Models\Request as UserRequest; // Renommé pour éviter un conflit avec Request facade

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
        // --- REMPLACEZ CECI PAR VOS VRAIES REQUÊTES À LA BASE DE DONNÉES ---
        // Exemple avec des données factices :
        // Vous devez utiliser vos modèles Eloquent (Printer, User, Request) ou DB::table()
        // pour récupérer les nombres réels.

        // Exemple avec Eloquent (si vous avez des modèles) :
        // $printerCount = Printer::where('status', 'active')->count();
        // $userCount = User::count();
        // $requestCount = UserRequest::count(); // Comptez les requêtes en fonction de votre logique

        // Exemple avec DB (si vous n'avez pas encore de modèles pour tout) :
        $printerCount = DB::table('printers')->where('status', 'active')->count();
        $userCount = DB::table('users')->count();
        $requestCount = DB::table('interventions')->count(); // Assurez-vous que 'requests' est le nom de votre table

        return response()->json([
            'printerCount' => $printerCount,
            'userCount' => $userCount,
            'requestCount' => $requestCount
        ]);
    }

    public function getRecentActivities()
    {
        // --- REMPLACEZ CECI PAR VOS VRAIES REQUÊTES À LA BASE DE DONNÉES ---
        // Exemple avec des données factices :
        // Vous devrez interroger votre BDD pour obtenir les dernières activités.
        // Cela pourrait être des logs, des dernières requêtes, interventions, etc.

        // Exemple : récupérer les 5 dernières requêtes avec l'utilisateur associé
        // $activities = UserRequest::with('user') // Assurez-vous d'avoir une relation 'user' sur votre modèle Request
        //                  ->latest()
        //                  ->limit(5)
        //                  ->get()
        //                  ->map(function($request) {
        //                      return [
        //                          'date' => $request->created_at->format('Y-m-d'),
        //                          'user' => $request->user->name, // ou $request->user->email, etc.
        //                          'action' => 'Demande de maintenance',
        //                          'status' => $request->status,
        //                      ];
        //                  });

        // Données factices pour l'exemple si vous n'avez pas encore la logique DB :
        $recentActivities = [
            ["date" => "2025-07-20", "user" => "Jean Dupont", "action" => "Nouvelle demande", "status" => "En attente"],
            ["date" => "2025-07-19", "user" => "Admin", "action" => "Imprimante ajoutée", "status" => "Terminé"],
            ["date" => "2025-07-18", "user" => "Pierre Martin", "action" => "Intervention N°123", "status" => "En cours"]
        ];

        return response()->json($recentActivities);
    }
}
