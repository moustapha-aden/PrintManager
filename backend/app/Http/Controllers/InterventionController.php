<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use Illuminate\Http\Request;

class InterventionController extends Controller
{
    /**
     * Liste toutes les interventions avec relations, avec filtres optionnels.
     */
    public function index(Request $request)
    {
        $query = Intervention::query();

        // Filtrer par technicien si le paramètre technician_id est présent
        if ($request->has('technician_id')) {
            $query->where('technician_id', $request->input('technician_id'));
        }

        // Filtrer par client si le paramètre client_id est présent
        if ($request->has('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        // Charger les relations nécessaires
        $query->with([
            'client.company',
            'client.department',
            'printer.company',
            'printer.department',
            'technician'
        ]);

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
            'start_date' => 'required|date', // CORRECTION: Changé 'dateTime' en 'date'
            'client_id' => 'nullable|exists:users,id',
            'technician_id' => 'nullable|exists:users,id',
            'printer_id' => 'required|exists:printers,id',
            'status' => 'required|in:En Cours, Terminée,Annulée',
            'priority' => 'required|in:Haute,Moyenne,Basse',
            'intervention_type' => 'required|string|max:255',
        ]);

        $intervention = Intervention::create($validated);

        return response()->json($intervention, 201);
    }

    /**
     * Met à jour une intervention existante.
     */
    public function update(Request $request, $id)
    {
        $intervention = Intervention::findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'sometimes|date', // CORRECTION: Changé 'dateTime' en 'date'
            'end_date' => 'nullable|date|after_or_equal:start_date', // CORRECTION: Changé 'dateTime' en 'date'
            'client_id' => 'nullable|exists:users,id',
            'technician_id' => 'nullable|exists:users,id',
            'printer_id' => 'sometimes|exists:printers,id',
            'status' => 'sometimes',
            'description' => 'nullable|string|max:1000',
            'priority' => 'sometimes|in:Haute,Moyenne,Basse',
            'notes' => 'nullable|string|max:1000',
            'intervention_type' => 'sometimes|string|max:255',
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
