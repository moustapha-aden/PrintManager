<?php

namespace App\Http\Controllers;

use App\Models\Inventaire;
use App\Models\Materielle;
use App\Models\Printer;
use Illuminate\Http\Request;

class InventaireController extends Controller
{
    /**
     * Liste tous les inventaires avec leurs relations.
     */
    public function index()
    {
        $inventaires = Inventaire::with(['materiel', 'printer'])->get();

        return response()->json($inventaires);
    }

    /**
     * Ajoute un nouvel inventaire et met à jour le stock.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'materiel_id' => 'required|exists:materielles,id',
            'quantite' => 'required|integer|min:1',
            'printer_id' => 'required|exists:printers,id',
            'date_deplacement' => 'required|date',
        ]);

        $materielle = Materielle::find($validated['materiel_id']);

        if (!$materielle) {
            return response()->json(['message' => 'Matériel non trouvé.'], 404);
        }

        if ($materielle->quantite < $validated['quantite']) {
            return response()->json(['message' => 'Quantité insuffisante en stock.'], 400);
        }

        // Met à jour la quantité disponible
        $materielle->decrement('quantite', $validated['quantite']);
        $materielle->increment('sortie', $validated['quantite']); // Pour suivi des sorties

        // Crée l'entrée d’inventaire
        $inventaire = Inventaire::create($validated);

        return response()->json([
            'message' => 'Inventaire ajouté avec succès.',
            'data' => $inventaire
        ], 201);
    }

    /**
     * Affiche un inventaire spécifique.
     */
    public function show($id)
    {
        $inventaire = Inventaire::with(['materiel', 'printer'])->find($id);

        if (!$inventaire) {
            return response()->json(['message' => 'Inventaire non trouvé.'], 404);
        }

        return response()->json($inventaire);
    }

    /**
     * Met à jour un inventaire.
     */
    public function update(Request $request, $id)
    {
        $inventaire = Inventaire::find($id);

        if (!$inventaire) {
            return response()->json(['message' => 'Inventaire non trouvé.'], 404);
        }

        $validated = $request->validate([
            'materiel_id' => 'sometimes|exists:materielles,id',
            'quantite' => 'sometimes|integer|min:1',
            'printer_id' => 'sometimes|exists:printers,id',
            'date_deplacement' => 'sometimes|date',
        ]);

        // Si on change le matériel ou la quantité, on ajuste le stock
        if (isset($validated['materiel_id']) || isset($validated['quantite'])) {
            $materiel = Materielle::find($validated['materiel_id'] ?? $inventaire->materiel_id);

            if (!$materiel) {
                return response()->json(['message' => 'Matériel non trouvé.'], 404);
            }

            $newQuantite = $validated['quantite'] ?? $inventaire->quantite;
            $difference = $newQuantite - $inventaire->quantite;

            // Si on augmente la quantité déplacée → vérifier le stock
            if ($difference > 0 && $materiel->quantite < $difference) {
                return response()->json(['message' => 'Quantité insuffisante en stock.'], 400);
            }

            // Ajuster le stock selon la différence
            $materiel->decrement('quantite', max($difference, 0));
            $materiel->increment('quantite', max(-$difference, 0)); // Si on réduit la quantité
        }

        $inventaire->update($validated);

        return response()->json([
            'message' => 'Inventaire mis à jour avec succès.',
            'data' => $inventaire
        ]);
    }

    /**
     * Supprime un inventaire et rétablit le stock.
     */
    public function destroy($id)
    {
        $inventaire = Inventaire::find($id);

        if (!$inventaire) {
            return response()->json(['message' => 'Inventaire non trouvé.'], 404);
        }

        // Rétablir le stock du matériel supprimé
        $materiel = Materielle::find($inventaire->materiel_id);
        if ($materiel) {
            $materiel->increment('quantite', $inventaire->quantite);
            $materiel->decrement('sortie', $inventaire->quantite);
        }

        $inventaire->delete();

        return response()->json(['message' => 'Inventaire supprimé avec succès.']);
    }
}
