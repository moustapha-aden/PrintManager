<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str; // Import Str facade if you decide to generate UUIDs on backend

class InterventionController extends Controller
{
    /**
     * Liste toutes les interventions avec relations, avec filtres optionnels.
     */
    public function index(Request $request)
    {
        $query = Intervention::query();

        // Charger les relations nécessaires
        $query->with([
            'client.company',
            'client.department',
            'printer.company',
            'printer.department',
            'technician'
        ]);

        // Logique de filtrage basée sur le rôle de l'utilisateur
        $user = Auth::user(); // Récupérer l'utilisateur connecté

        if ($user) { // S'assurer qu'un utilisateur est connecté
            if ($user->role === 'client') {
                // Un client ne voit que ses propres interventions
                $query->where('client_id', $user->id);
            } elseif ($user->role === 'technicien') {
                // Un technicien voit ses interventions assignées OU les interventions en statut 'En Attente'
                $query->where(function($q) use ($user) {
                    $q->where('technician_id', $user->id);
                });
            }
            // Les administrateurs voient toutes les interventions par défaut
        }

        // Ajoutez ici les filtres de recherche et de statut que votre frontend enverra
        // Par exemple, si vous envoyez un paramètre 'status_filter' ou 'priority_filter'
        if ($request->has('status_filter') && $request->input('status_filter') !== 'all') {
            $query->where('status', $request->input('status_filter'));
        }

        if ($request->has('priority_filter') && $request->input('priority_filter') !== 'all') {
            $query->where('priority', $request->input('priority_filter'));
        }

        if ($request->has('intervention_type_filter') && $request->input('intervention_type_filter') !== 'all') {
            $query->where('intervention_type', $request->input('intervention_type_filter'));
        }

        if ($request->has('search_term')) {
            $searchTerm = strtolower($request->input('search_term'));
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(description) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(status) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(priority) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(intervention_type) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhere('id', 'like', "%{$searchTerm}%")
                  ->orWhere('numero_demande', 'like', "%{$searchTerm}%") // Added search by numero_demande
                  ->orWhereHas('printer', function ($pq) use ($searchTerm) {
                      $pq->whereRaw('LOWER(model) LIKE ?', ["%{$searchTerm}%"])
                         ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$searchTerm}%"])
                         ->orWhereRaw('LOWER(serial) LIKE ?', ["%{$searchTerm}%"])
                         ->orWhereHas('company', function ($cq) use ($searchTerm) {
                             $cq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                         })
                         ->orWhereHas('department', function ($dq) use ($searchTerm) {
                             $dq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                         });
                  })
                  ->orWhereHas('technician', function ($tq) use ($searchTerm) {
                      $tq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                  })
                  ->orWhereHas('client', function ($clq) use ($searchTerm) {
                      $clq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                          ->orWhereHas('company', function ($ccq) use ($searchTerm) {
                              $ccq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                          })
                          ->orWhereHas('department', function ($cdq) use ($searchTerm) {
                              $cdq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                          });
                  });
            });
        }


        // Optionnel: Ajouter un orderBy par défaut si non spécifié
        $query->orderByDesc('created_at');

        return $query->get();
    }

    /**
     * Affiche une intervention précise avec relations.
     */
    public function show($id)
    {
        // Assurez-vous de charger toutes les relations nécessaires pour les détails
        return Intervention::with([
            'printer.company',
            'printer.department',
            'client.company',
            'client.department',
            'technician'
        ])->findOrFail($id);
    }

    /**
     * Crée une nouvelle intervention.
     */
public function store(Request $request)
{
    $validated = $request->validate([
        'numero_demande' => 'required|string|unique:interventions,numero_demande|max:255',
        'start_date' => 'required|date',
        // 'end_date' => 'nullable|date|after_or_equal:start_date',
        'client_id' => 'nullable|exists:users,id',
        'technician_id' => 'nullable|exists:users,id',
        'printer_id' => 'required|exists:printers,id',
        'status' => 'required|in:En Attente,En Cours,Terminée,Annulée',
        'description' => 'nullable|string|max:1000',
        'priority' => 'required|in:Haute,Moyenne,Basse',
        'intervention_type' => 'required|string|max:255',
        'image_path' => 'nullable|string|max:2048',
        // 'notes' => 'nullable|string|max:1000',
    ]);

    if (empty($validated['technician_id'])) {
        $validated['technician_id'] = 5; // ID par défaut
    }

    // Crée d’abord l’intervention
    $intervention = Intervention::create($validated);

    // Le fait de retourner $intervention en JSON inclura automatiquement 'image_path'
    return response()->json($intervention, 201);
    }


    /**
     * Met à jour une intervention existante.
     */
    public function update(Request $request, $id)
    {
        $intervention = Intervention::findOrFail($id);

        $validated = $request->validate([
            'numero_demande' => 'sometimes|string|max:255', // numero_demande is usually not updated
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'client_id' => 'nullable|exists:users,id',
            'technician_id' => 'nullable|exists:users,id',
            'printer_id' => 'sometimes|exists:printers,id',
            'status' => 'sometimes|in:En Attente,En Cours,Terminée,Annulée', // Updated allowed statuses
            'description' => 'nullable|string|max:1000',
            'priority' => 'sometimes|in:Haute,Moyenne,Basse',
            'notes' => 'nullable|string|max:1000',
            'intervention_type' => 'sometimes|string|max:255',
            'solution' => 'nullable|string|max:1000', // Added solution validation
            'date_previsionnelle' => 'nullable|date|after_or_equal:start_date',
        ]);
        $intervention->update($validated);

        return response()->json($intervention, 200);
    }

    /**
     * Supprime une intervention.
     */
    public function destroy($id)
    {
        $intervention = Intervention::findOrFail($id);
        $intervention->delete();

        return response()->json(['message' => 'Intervention supprimée.']);
    }
}
