<?php

namespace App\Http\Controllers;

use App\Models\Materielle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterielleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Récupère tous les matériels sans relation
        $materielles = Materielle::all();
        return response()->json($materielles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'reference' => 'required|string|max:255|unique:materielles,reference',
            'type'      => 'required|string|max:255',
            'quantite'  => 'required|integer|min:0',
            'sortie'    => 'nullable|integer|min:0',
        ]);

        // Création
        $materielle = Materielle::create($validated);

        return response()->json([
            'message'    => 'Matériel ajouté avec succès',
            'materielle' => $materielle,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Materielle $materielle)
    {
        return response()->json($materielle->load('inventaires'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Materielle $materielle)
{
    $validated = $request->validate([
        'name'      => 'sometimes|string|max:255',
        'reference' => [
            'sometimes',
            'string',
            'max:255',
            // ✅ Ignore la référence de l'enregistrement actuel
            Rule::unique('materielles', 'reference')->ignore($materielle->id, 'id')
        ],
        'type'      => 'sometimes|string|max:255',
        'quantite'  => 'sometimes|integer|min:0',
        'sortie'    => 'sometimes|integer|min:0',
    ]);

    $materielle->update($validated);

    return response()->json([
        'message'    => 'Matériel mis à jour avec succès',
        'materielle' => $materielle,
    ]);
}

/**
 * Remove the specified resource from storage.
 */
public function destroy(Materielle $materielle)
{
        $materielle->delete();

    return response()->json(['message' => 'Matériel supprimé avec succès']);
}
}
