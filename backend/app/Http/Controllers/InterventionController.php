<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // Importez le facade Storage
use Illuminate\Support\Str; // Gardé au cas où vous en auriez besoin pour d'autres fonctionnalités (UUID, etc.)

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
        $user = Auth::user();

        if ($user) {
            if ($user->role === 'client') {
                // Un client ne voit que ses propres interventions
                $query->where('client_id', $user->id);
            } elseif ($user->role === 'technicien') {
                // Un technicien voit ses interventions assignées OU les interventions en statut 'En Attente'
                $query->where(function($q) use ($user) {
                    $q->where('technician_id', $user->id)
                      ->orWhere('status', 'En Attente'); // Un technicien peut aussi voir les nouvelles demandes
                });
            }
            // Les administrateurs voient toutes les interventions par défaut
        }

        // Ajoutez ici les filtres de recherche et de statut
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
                  ->orWhere('numero_demande', 'like', "%{$searchTerm}%")
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
        $intervention = Intervention::with([
            'printer.company',
            'printer.department',
            'client.company',
            'client.department',
            'technician'
        ])->findOrFail($id);

        return response()->json($intervention, 200);
    }

    /**
     * Crée une nouvelle intervention.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero_demande' => 'required|string|unique:interventions,numero_demande|max:255',
            'start_date' => 'required|date',
            'client_id' => 'nullable|exists:users,id',
            'technician_id' => 'nullable|exists:users,id',
            'printer_id' => 'required|exists:printers,id',
            'status' => 'required|in:En Attente,En Cours,Terminée,Annulée',
            'description' => 'nullable|string|max:1000',
            'priority' => 'required|in:Haute,Moyenne,Basse',
            'intervention_type' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validation pour l'image
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date_previsionnelle' => 'nullable|date|after_or_equal:start_date',
            'solution' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['technician_id'])) {
            $validated['technician_id'] = 5; // ID par défaut
        }

        $intervention = new Intervention($validated);

        // Gérer l'upload de la photo
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('public/interventions_photos');
            // Sauvegarde le chemin d'accès relatif à 'storage/app/public/'
            // Pour que cela corresponde à votre route /storage/interventions_photos/...
            $intervention->image_path = str_replace('public/', '', $path);
        }

        $intervention->save();

        // Retourne l'intervention avec les relations chargées
        return response()->json($intervention->load(['client', 'technician', 'printer']), 201);
    }

    /**
     * Met à jour une intervention existante.
     */
    public function update(Request $request, $id)
    {
        $intervention = Intervention::findOrFail($id);

        $validated = $request->validate([
            'numero_demande' => 'sometimes|string|max:255|unique:interventions,numero_demande,' . $id, // Permet la mise à jour du numero_demande si unique et si ce n'est pas le sien
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'client_id' => 'nullable|exists:users,id',
            'technician_id' => 'nullable|exists:users,id',
            'printer_id' => 'sometimes|exists:printers,id',
            'status' => 'sometimes|in:En Attente,En Cours,Terminée,Annulée',
            'description' => 'nullable|string|max:1000',
            'priority' => 'sometimes|in:Haute,Moyenne,Basse',
            'notes' => 'nullable|string|max:1000',
            'intervention_type' => 'sometimes|string|max:255',
            'solution' => 'nullable|string|max:1000',
            'date_previsionnelle' => 'nullable|date|after_or_equal:start_date',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Permet la mise à jour de la photo
            'delete_photo' => 'sometimes|boolean', // Permet de supprimer la photo existante
        ]);

        // Gérer la suppression de la photo existante
        if ($request->boolean('delete_photo') && $intervention->image_path) {
            // Le chemin stocké est 'interventions_photos/nom.jpg'
            // Pour supprimer, il faut le préfixer avec 'public/'
            $fullPathToDelete = 'public/' . $intervention->image_path;
            if (Storage::exists($fullPathToDelete)) {
                Storage::delete($fullPathToDelete);
            }
            $intervention->image_path = null; // Supprimer le chemin de la base de données
        }

        // Gérer l'upload de la nouvelle photo si présente
        if ($request->hasFile('photo')) {
            // Supprimer l'ancienne photo si elle existe avant d'en stocker une nouvelle
            if ($intervention->image_path) {
                $fullPathToDelete = 'public/' . $intervention->image_path;
                if (Storage::exists($fullPathToDelete)) {
                    Storage::delete($fullPathToDelete);
                }
            }
            $path = $request->file('photo')->store('public/interventions_photos');
            $intervention->image_path = str_replace('public/', '', $path);
        }

        // Utiliser fill pour les champs validés et ensuite sauvegarder
        $intervention->fill($validated);
        $intervention->save();

        return response()->json($intervention->load(['client', 'technician', 'printer']), 200);
    }

    /**
     * Supprime une intervention.
     */
    public function destroy($id)
    {
        $intervention = Intervention::findOrFail($id);

        // Supprimer la photo associée si elle existe
        if ($intervention->image_path) {
            $photoPath = 'public/' . $intervention->image_path;
            if (Storage::exists($photoPath)) {
                Storage::delete($photoPath);
            }
        }

        $intervention->delete();

        return response()->json(['message' => 'Intervention supprimée avec succès.']);
    }
}
